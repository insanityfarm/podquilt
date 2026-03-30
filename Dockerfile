ARG COMPOSER_VERSION=2.9.5
FROM composer:${COMPOSER_VERSION} AS composer-bin
FROM php:8.5-cli

RUN apt-get update \
    && apt-get install --yes --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/tmp/composer

EXPOSE 8000

CMD ["composer", "serve"]
