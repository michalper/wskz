FROM php:8.4-cli

# Pinned exact apt versions would break on every Debian point-release used by
# this base image tag; the PHP version itself is already pinned via the tag.
# `apt-get upgrade` pulls current security patches for whatever base-image
# packages are already installed (gzip, libpcre2, ...) from that same tag's
# Debian repos, closing known CVEs without pinning to individual package
# versions.
# hadolint ignore=DL3008
RUN apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY api/composer.json api/composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY api/ ./
RUN composer dump-autoload --optimize

RUN useradd --uid 1000 --create-home --shell /usr/sbin/nologin appuser \
    && chown -R appuser:appuser /app
USER 1000

EXPOSE 8080

CMD ["php", "-d", "variables_order=EGPCS", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
