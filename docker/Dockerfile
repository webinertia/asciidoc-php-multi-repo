# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Runtime image for the site generator
#
# This is NOT the development image. `../Dockerfile` builds the interactive
# toolchain environment (Mago, Xdebug, PCOV, PHPUnit) that `compose.yml`'s
# `tooling` service and the devcontainer use. This file builds the smallest
# image that can render a site: PHP, the three extensions the package
# requires, the package's runtime dependencies, and the console binary.
#
#   docker build -f docker/Dockerfile -t asciidoc-php-multi-repo:local .
#   docker run --rm asciidoc-php-multi-repo:local --version
#
# The `render`, `watch`, and `preview` services in `compose.yml` build from
# this file.
#
# This is also the image the GitHub container action in `action.yml` runs,
# once the publish workflow has pushed it to GHCR. The action uses the default
# entrypoint below, which is why the action's arguments begin with a verb.
# ---------------------------------------------------------------------------

# PHP version to base the image on. The package supports ~8.4.0 and ~8.5.0;
# 8.4 is the floor, so the image proves the package works on its lowest
# supported runtime.
ARG PHP_VERSION=8.4

# Composer version (copied from the official composer image at build time).
ARG COMPOSER_VERSION=2.11.1

# The root package has no git history inside the build context, so Composer
# cannot infer a version. Passing it explicitly is what makes
# `--version` print a release number instead of the `0.0.0-dev` sentinel.
ARG COMPOSER_ROOT_VERSION=0.0.0-dev

# ---------------------------------------------------------------------------
# Composer, as a build-only source of the composer binary.
# ---------------------------------------------------------------------------
FROM composer:${COMPOSER_VERSION} AS composer

# ---------------------------------------------------------------------------
# Dependencies
#
# Built on the same PHP image as the runtime so the installed packages are
# resolved and compiled against exactly the runtime the site is rendered on.
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS dependencies

ARG COMPOSER_ROOT_VERSION

ENV COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION}

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        libcurl4-openssl-dev \
        libonig-dev \
        unzip \
    ; \
    docker-php-ext-install -j"$(nproc)" \
        curl \
        mbstring \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

# The manifests come first so a change to the source does not invalidate the
# dependency layer.
COPY composer.json composer.lock ./

RUN set -eux; \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

# The autoloader is dumped after the source arrives, and as a classmap, so the
# runtime never has to stat the filesystem to find a class.
COPY src/ src/
COPY bin/ bin/

RUN set -eux; \
    composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------------
# Runtime
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS runtime

LABEL org.opencontainers.image.title="asciidoc-php-multi-repo" \
      org.opencontainers.image.description="Renders a multi-repository AsciiDoc documentation site." \
      org.opencontainers.image.source="https://github.com/webinertia/asciidoc-php-multi-repo"

# git is required: a repository that discovery cannot find locally is fetched
# with a shallow clone. curl is required by the HTTP transport. mbstring and
# json are required by composer.json; json is compiled into PHP 8 and cannot
# be installed as a separate extension, so only mbstring is built here.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libcurl4-openssl-dev \
        libonig-dev \
        unzip \
    ; \
    docker-php-ext-install -j"$(nproc)" \
        curl \
        mbstring \
    ; \
    apt-get purge -y \
        libcurl4-openssl-dev \
        libonig-dev \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=dependencies /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY src/ src/
COPY bin/ bin/
# The JSON schemas and the theme templates, stylesheets, and scripts are read
# from the package root at runtime, so they belong in the image.
COPY config/ config/
COPY resources/ resources/

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN set -eux; \
    chmod 0755 /usr/local/bin/entrypoint.sh /app/bin/asciidoc-php-multi-repo; \
    groupadd --gid 1000 site; \
    useradd --uid 1000 --gid 1000 --create-home --shell /bin/bash site; \
    chown -R site:site /app

# No `USER site`. This image is also a GitHub container action, and the runner
# mounts the calling repository at /github/workspace owned by the runner's own
# uid, which a fixed uid inside the image cannot write to. Container actions
# conventionally run as root for that reason, so the image does too, and
# `compose.yml` pins `user: "1000:1000"` for the local site stack so that local
# rendering stays on the unprivileged `site` account.
#
# The rendered site and the cache are written under the working directory, so
# the mounted site directory is where the caller decides they belong.
ENV ASCIIDOC_SERVE_HOST=0.0.0.0

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["--help"]
