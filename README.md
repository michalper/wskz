# AI Message Router — PoC

Mikroserwisowy PoC routera wiadomości: przyjmuje zgłoszenie użytkownika (email + treść),
klasyfikuje je lokalnym modelem LLM (Ollama) za pomocą agenta AI z function-calling
(`neuron-core/neuron-ai`) i wysyła e-mail do odpowiedniego działu, przechwytywany przez MailHog.

## Uruchomienie

```bash
docker compose up -d
```

Po chwili (pierwszy start pobiera wagi modelu `llama3.2:3b`, ok. 2 GB, kilka minut) dostępne są:

- API: http://localhost:8080
- Dokumentacja Swagger: http://localhost:8080/api/v1/docs
- MailHog (panel przechwyconych maili): http://localhost:8025

Postęp pobierania modelu można obejrzeć w logach kontenera Ollama:

```bash
docker compose logs -f ollama
```

## Przykładowe zapytanie

```bash
curl -X POST http://localhost:8080/api/v1/route-message \
  -H "Content-Type: application/json" \
  -d '{"email": "jan.nowak@example.com", "message": "Nie dziala mi komputer"}'
```

Odpowiedź:

```json
{"department": "it@example.com", "subject": "..."}
```

Wiadomość pojawi się w MailHog (http://localhost:8025), zaadresowana do wybranego działu,
z nagłówkiem `Reply-To` ustawionym na `jan.nowak@example.com`.

## Architektura i decyzje

- **Symfony** (szkielet, bez ORM/Twig poza tym co wymaga Swagger UI) jako framework API —
  atrybuty `#[Route]`, `#[MapRequestPayload]` + Symfony Validator do walidacji requestu.
- **neuron-core/neuron-ai** jako biblioteka agentowa: agent dostaje jedno narzędzie
  (`SendEmailTool`), które LLM wywołuje przez function-calling, wybierając adres działu
  z zamkniętej listy (`App\Dto\Department`).
- **Ollama** (`llama3.2:3b`) jako lokalny silnik LLM. Model `1b` bywał zawodny przy
  wywoływaniu narzędzia (odpowiadał zwykłym tekstem zamiast `tool_calls`) — `3b` w testach
  wywoływał `send_email` niezawodnie, kosztem wolniejszego (CPU-only) czasu odpowiedzi.
- HTTP timeout dla wywołań Ollamy jest podniesiony do 300s (lokalne wnioskowanie na CPU
  jest znacznie wolniejsze niż hostowane API).
- **MailHog** przechwytuje pocztę wysyłaną przez Symfony Mailer (SMTP) — pozwala zweryfikować
  treść, adresata i nagłówek `Reply-To` bez realnej wysyłki.
- **Bezpiecznik (fallback)**: jeśli LLM nie wywoła narzędzia (błąd, timeout, nieprawidłowa
  odpowiedź), `MessageRoutingAgent` samodzielnie wysyła zgłoszenie na `other@example.com`,
  żeby żadne zgłoszenie nie zostało zgubione.
- **Swagger/OpenAPI** (`nelmio/api-doc-bundle`) wystawiony pod `/api/v1/docs`.
- **API serwowane przez wbudowany serwer PHP** (`php -S`) w kontenerze — wystarczające dla
  PoC, bez dodatkowego kontenera nginx.

## Struktura repo

```
docker-compose.yml
docker/php/Dockerfile        # obraz API (PHP 8.4 CLI + wbudowany serwer)
docker/ollama/entrypoint.sh  # pull modelu + start serwera Ollama
api/                         # aplikacja Symfony
  src/Controller/            # endpoint HTTP
  src/Service/               # orkiestracja agenta AI
  src/Tool/                  # narzędzie wysyłki e-mail (function calling)
  src/Dto/                   # DTO requestu, lista działów, wynik routingu
  tests/                     # PHPUnit (unit + funkcjonalne)
.github/workflows/           # CI: testy, PHPStan, PHP-CS-Fixer, Rector, coverage, Infection
```

## Kryteria akceptacji (DoD)

- [x] `docker compose up -d` podnosi w pełni działające API, MailHog i Ollamę.
- [x] API udostępnia dokumentację Swagger pod adresem `/api/v1/docs`.
- [x] W repozytorium znajduje się `README.md` z instrukcją uruchomienia i opisem projektu.
- [x] Request na endpoint API skutkuje analizą treści i pojawieniem się nowej wiadomości w MailHog.
- [x] Przechwycona wiadomość jest zaadresowana do prawidłowego działu (zgodnie z listą).
- [x] Przechwycona wiadomość zawiera prawidłowo ustawiony nagłówek `Reply-To`.

Każdy z powyższych punktów (poza samym README) jest zweryfikowany automatycznie w CI —
zobacz `.github/workflows/e2e.yml`: uruchamia `docker compose up -d --build`, czeka na
pobranie modelu, wysyła realny request do API i sprawdza przez API MailHoga, że wiadomość
trafiła do prawidłowego działu z poprawnym nagłówkiem `Reply-To`.

## Testy i jakość kodu lokalnie

Wszystkie polecenia uruchamiane w katalogu `api/`:

```bash
composer install
composer test            # PHPUnit
composer phpstan          # PHPStan (level max)
composer cs-check         # PHP-CS-Fixer (dry-run)
composer rector-check     # Rector (dry-run)
composer infection        # mutation testing
```

CI (`.github/workflows/ci.yml`, `coverage.yml`) uruchamia te same kroki na każdym
push/PR do `main`/`master`.
