# Dev/demo container: FrankenPHP serving the example/ site.
FROM dunglas/frankenphp:php8.5

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Match the host user so files written to the bind mount (vendor/) stay editable
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app \
    && useradd -m -u ${UID} -g ${GID} app \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R app:app /config/caddy /data/caddy
USER app

ENTRYPOINT ["/bin/sh", "/app/docker/entry.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
