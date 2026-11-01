# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Webware alignment development environment image
#
# Builds a self-contained, interactive PHP development environment carrying
# PHP CLI, Composer, PCOV, and Xdebug. Developers work inside this container:
# either through the VS Code Dev Container (backed by compose.yml) or by
# `docker compose up -d` + `docker compose exec tooling`. The host machine
# (Windows, WSL, Linux, or macOS) never needs a native PHP toolchain.
#
# Copied from webinertia/webware-tools presets/webware-alignment/artifacts/.
# One local change: the Mago install stage is omitted. This package pins no
# webware/webware-tools revision (it still uses PHPStan), so that stage would
# abort the build, and the Mago migration is a separate, deferred task.
# ---------------------------------------------------------------------------

# PHP version to base the image on (latest 8.5 patch release). Keep in sync
# with the package's supported PHP versions and `config.platform.php`.
ARG PHP_VERSION=8.5.12

# Composer version (copied from the official composer image at build time).
ARG COMPOSER_VERSION=2.11.1

# Named stage so Composer can be copied from it (--from does not support
# inline variable expansion).
FROM composer:${COMPOSER_VERSION} AS composer

FROM php:${PHP_VERSION}-cli

# PECL extension versions, pinned so a rebuild cannot silently pick up a
# different extension release.
ARG PCOV_VERSION=1.0.12
ARG XDEBUG_VERSION=3.5.3

# ---------------------------------------------------------------------------
# System packages + PHP extensions
# ---------------------------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
    ; \
    docker-php-ext-install -j"$(nproc)" \
        intl \
        pcntl \
        zip \
    ; \
    pecl install "pcov-${PCOV_VERSION}" \
    && docker-php-ext-enable pcov \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Xdebug (step debugging)
# ---------------------------------------------------------------------------
# Installed but disabled by default (xdebug.mode=off) so it does not slow down
# normal Composer/PHPUnit/Mago runs. Enable it with XDEBUG_MODE=debug (see
# compose.yml) and connect the IDE to host.docker.internal.
RUN set -eux; \
    pecl install "xdebug-${XDEBUG_VERSION}" \
    && docker-php-ext-enable xdebug \
    ; \
    { \
        echo 'xdebug.mode=off'; \
        echo 'xdebug.start_with_request=yes'; \
        echo 'xdebug.client_host=host.docker.internal'; \
    } >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# ---------------------------------------------------------------------------
# Composer
# ---------------------------------------------------------------------------
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app
