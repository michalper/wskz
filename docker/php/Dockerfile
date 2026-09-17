FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY api/composer.json api/composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY api/ ./
RUN composer dump-autoload --optimize

EXPOSE 8080

CMD ["php", "-d", "variables_order=EGPCS", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
