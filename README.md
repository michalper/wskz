# AI Message Router — PoC

[![CI](https://github.com/michalper/wskz/actions/workflows/ci.yml/badge.svg)](https://github.com/michalper/wskz/actions/workflows/ci.yml)
[![Coverage](https://github.com/michalper/wskz/actions/workflows/coverage.yml/badge.svg)](https://github.com/michalper/wskz/actions/workflows/coverage.yml)
[![E2E](https://github.com/michalper/wskz/actions/workflows/e2e.yml/badge.svg)](https://github.com/michalper/wskz/actions/workflows/e2e.yml)
[![CodeQL](https://github.com/michalper/wskz/actions/workflows/codeql.yml/badge.svg)](https://github.com/michalper/wskz/actions/workflows/codeql.yml)
[![Docker security](https://github.com/michalper/wskz/actions/workflows/docker-security.yml/badge.svg)](https://github.com/michalper/wskz/actions/workflows/docker-security.yml)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/michalper/wskz/badge)](https://securityscorecards.dev/viewer/?uri=github.com/michalper/wskz)
[![codecov](https://codecov.io/gh/michalper/wskz/graph/badge.svg)](https://codecov.io/gh/michalper/wskz)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=michalper_wskz&metric=alert_status)](https://sonarcloud.io/project/overview?id=michalper_wskz)
[![PHP](https://img.shields.io/badge/php-8.4%2B-777bb4)](api/composer.json)
[![Symfony](https://img.shields.io/badge/symfony-8.1-000000?logo=symfony)](api/composer.json)
[![Dependabot](https://img.shields.io/badge/dependabot-enabled-025E8C?logo=dependabot&logoColor=white)](.github/dependabot.yml)
[![Open Issues](https://img.shields.io/github/issues/michalper/wskz)](https://github.com/michalper/wskz/issues)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Microservice PoC for an intelligent message router: it takes a user's request (sender email +
free-form text), classifies it with a local LLM (Ollama) through an AI agent using
function-calling (`neuron-core/neuron-ai`), and emails it to the right department, captured by
MailHog.

📸 **[See it running — screenshots of Swagger, a routed email, and the fallback in action](https://github.com/michalper/wskz/wiki)**

## Running it

```bash
docker compose up -d
```

After a bit (the first start pulls the `llama3.2:3b` model weights, ~2 GB, a few minutes), you
get:

- API: http://localhost:8080
- Swagger docs: http://localhost:8080/api/v1/docs
- MailHog (captured-mail inbox): http://localhost:8025

Watch the model download progress in the Ollama container's logs:

```bash
docker compose logs -f ollama
```

## Example request

```bash
curl -X POST http://localhost:8080/api/v1/route-message \
  -H "Content-Type: application/json" \
  -d '{"email": "jan.nowak@example.com", "message": "My computer is broken"}'
```

Response:

```json
{
  "department": "it@example.com",
  "subject": "Broken computer"
}
```

(the exact wording of `subject` comes from the LLM, so it varies between runs — the department
is what matters and is what the fallback/tests actually assert on)

The message shows up in MailHog (http://localhost:8025), addressed to the chosen department,
with the `Reply-To` header set to `jan.nowak@example.com`.

`GET /api/v1/health` reports whether the API and Ollama are reachable (200 `{"status":"ok",...}`,
or 503 if Ollama isn't).

## Architecture and decisions

```mermaid
sequenceDiagram
    actor User
    participant API as Symfony API
    participant Agent as MessageRoutingAgent
    participant Ollama as Ollama (llama3.2:3b)
    participant Mail as MailHog

    User->>API: POST /api/v1/route-message {email, message}
    API->>Agent: route(email, message)
    Agent->>Ollama: chat + send_email tool definition
    Ollama-->>Agent: tool_calls: send_email(department, subject, body)
    alt tool call succeeds
        Agent->>Mail: SMTP send (Reply-To: email)
    else LLM errors, times out, or skips the tool call
        Agent->>Mail: SMTP send to other@example.com (safety net)
    end
    Agent-->>API: department, subject
    API-->>User: 200 {department, subject}
```

- **Symfony** (skeleton, no ORM/Twig beyond what Swagger UI needs) as the API framework —
  `#[Route]`/`#[MapRequestPayload]` attributes plus the Symfony Validator for request validation.
- **neuron-core/neuron-ai** as the agent library: the agent gets one tool (`SendEmailTool`),
  which the LLM invokes via function-calling, picking a department address from a closed list (`App\Dto\Department`).
- **Ollama** (`llama3.2:3b`) as the local LLM engine. The `1b` model was unreliable at actually
  calling the tool (it would answer with plain text instead of emitting `tool_calls`) — `3b`
  called `send_email` reliably in testing, at the cost of a slower (CPU-only) response time.
- The HTTP timeout for Ollama calls is raised to 300s, and generation is capped at 200 tokens (`num_predict`) — local
  CPU inference is much slower than a hosted API, and without a cap a
  small model can occasionally ramble well past what a tool call or a one-line confirmation
  needs.
- **MailHog** captures mail sent through Symfony Mailer (SMTP) — lets you verify the body,
  recipient, and `Reply-To` header without a real send.
- **Safety-net fallback**: if the LLM fails to call the tool (error, timeout, malformed
  response), `MessageRoutingAgent` sends the request to `other@example.com` itself, so no
  request is ever silently dropped.
- **Swagger/OpenAPI** (`nelmio/api-doc-bundle`) exposed at `/api/v1/docs`.
- **The API is served by PHP's built-in server** (`php -S`) inside the container — good enough
  for a PoC, no extra nginx container needed.
- **Structured logging** via `symfony/monolog-bundle` — JSON to stderr in `prod` (captured by
  `docker logs`), human-readable in `dev`. The test environment overrides the `logger` service
  with a `NullLogger` (see `config/services.yaml`): without one, Symfony's debug error handler
  writes every caught-and-handled exception straight to STDERR, which made Infection's initial
  test run kill the process the moment a validation test logged its (expected, already-handled)
  422 exception.
- **Docker healthchecks** on all three services (`docker-compose.yml`), with `api` waiting on
  `mailhog`/`ollama` to report healthy before it depends on them. The Ollama healthcheck confirms
  the server is accepting requests, not that the model has finished downloading — that gap is
  covered by the API's own generous timeout and safety-net fallback.

## Repo layout

```
docker-compose.yml
docker/php/Dockerfile        # API image (PHP 8.4 CLI + built-in server)
docker/ollama/entrypoint.sh  # pulls the model, then starts the Ollama server
api/                         # the Symfony application
  src/Controller/            # HTTP endpoints (routing + health)
  src/Service/               # AI agent orchestration
  src/Tool/                  # the email-sending tool (function calling)
  src/Dto/                   # request DTO, department list, routing outcome
  tests/                     # PHPUnit (unit + functional)
.github/workflows/           # CI: tests, PHPStan, PHP-CS-Fixer, Rector, coverage, Infection, e2e
```

## Acceptance criteria (DoD)

- [x] `docker compose up -d` brings up a fully working API, MailHog, and Ollama.
- [x] The API exposes Swagger documentation at `/api/v1/docs`.
- [x] The repo includes a `README.md` with setup instructions and a project description.
- [x] Hitting the API endpoint analyzes the message and produces a new message in MailHog.
- [x] The captured message is addressed to the correct department (per the allowed list).
- [x] The captured message carries a correctly-set `Reply-To` header.

Every one of these (besides the README itself) is verified automatically in CI — see
`.github/workflows/e2e.yml`: it runs `docker compose up -d --build`, waits for the model to be
pulled, sends a real request to the API, and checks via MailHog's API that the message landed in
the right department mailbox with the correct `Reply-To` header.

## Tests and code quality, locally

All commands run from the `api/` directory:

```bash
composer install
composer test            # PHPUnit
composer phpstan          # PHPStan (max level)
composer cs-check         # PHP-CS-Fixer (dry-run)
composer rector-check     # Rector (dry-run)
composer infection        # mutation testing
```

CI (`.github/workflows/ci.yml`, `coverage.yml`) runs the same steps on every push/PR to
`main`/`master`, plus:

- **Coverage** uploads to [Codecov](https://codecov.io/gh/michalper/wskz) and runs
  [Infection](https://infection.github.io/) mutation testing (minimum MSI: 50% — the remaining
  escaped mutants are concatenation-order/removal mutations on natural-language LLM
  prompt/description strings, not meaningfully testable without brittle exact-wording
  assertions).
- **[SonarCloud](https://sonarcloud.io/project/overview?id=michalper_wskz)** static analysis runs
  automatically on push.
- Every GitHub Action used anywhere in `.github/workflows/` (including `actions/checkout` and
  `github/codeql-action`, not just third-party ones) is pinned to a full commit SHA rather than a
  mutable version tag, per SonarCloud's `githubactions:S7637` and OpenSSF Scorecard's
  Pinned-Dependencies check. The Docker base images (`php:8.4-cli`, `composer:2`) are pinned by
  digest for the same reason.
- **[CodeQL](https://github.com/michalper/wskz/security/code-scanning)** (`codeql.yml`) — CodeQL
  doesn't support PHP, so this scans the `.github/workflows/*.yml` files themselves (the `actions`
  language) for things like script-injection via untrusted inputs.
- **[Docker security](.github/workflows/docker-security.yml)** — Hadolint lints `docker/php/Dockerfile`;
  Trivy builds the image and scans it for CRITICAL/HIGH CVEs, uploaded to the Security tab.
- **[OpenSSF Scorecard](https://securityscorecards.dev/viewer/?uri=github.com/michalper/wskz)**
  (`scorecard.yml`) — scores the repo's supply-chain security posture (pinned dependencies, branch
  protection, etc.) weekly.
- **PHPStan strict rules** (`phpstan/phpstan-strict-rules`) layered on top of `level: max`.
- **Dependabot** keeps Composer dependencies (`api/`) and GitHub Actions up to date weekly.
- Every workflow declares an explicit, read-only top-level `permissions:` block (OpenSSF
  Scorecard's Token-Permissions check), and [`SECURITY.md`](SECURITY.md) documents how to report
  a vulnerability privately.
