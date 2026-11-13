FROM ghcr.io/shyim/wolfi-php/frankenphp:8.5 AS app-runtime

RUN apk add --no-cache php-frankenphp-8.5-curl
RUN apk add --no-cache php-frankenphp-8.5-iconv
RUN apk add --no-cache php-frankenphp-8.5-phar
RUN apk add --no-cache php-frankenphp-8.5-openssl
RUN apk add --no-cache php-frankenphp-8.5-pdo
RUN apk add --no-cache php-frankenphp-8.5-pdo_pgsql
RUN apk add --no-cache php-frankenphp-8.5-sodium
RUN apk add --no-cache php-frankenphp-8.5-mbstring
RUN apk add --no-cache php-frankenphp-8.5-simplexml
RUN apk add --no-cache php-frankenphp-8.5-sockets
RUN apk add --no-cache php-frankenphp-8.5-ctype
RUN apk add --no-cache php-frankenphp-8.5-dom
RUN apk add --no-cache php-frankenphp-8.5-pcntl
RUN apk add --no-cache php-frankenphp-8.5-posix
RUN apk add --no-cache php-frankenphp-8.5-xmlwriter

ARG USER=www-data

COPY .docker/Caddyfile /etc/caddy/Caddyfile
COPY .docker/php/session.ini /etc/php/conf.d/zz-session.ini
COPY .docker/entrypoint.sh /usr/local/bin/app-entrypoint.sh
COPY .docker/healthcheck-app.sh /usr/local/bin/app-healthcheck.sh
COPY .docker/run-migrations.sh /usr/local/bin/run-migrations.sh

RUN \
    mkdir -p /data/caddy /config/caddy; \
    adduser -D ${USER}; \
    chown -R ${USER}:${USER} /data/caddy /config/caddy; \
    chmod +x /usr/local/bin/app-entrypoint.sh /usr/local/bin/app-healthcheck.sh /usr/local/bin/run-migrations.sh

USER ${USER}

ENTRYPOINT ["/usr/local/bin/app-entrypoint.sh"]

FROM app-runtime AS app-base

ARG USER=www-data

USER root

COPY --chown=${USER}:${USER} . /app
RUN rm -rf /app/.docker

USER ${USER}
