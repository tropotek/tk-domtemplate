#!/bin/sh
set -e
# Install dependencies (just the autoloader for now) on first start
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi
exec "$@"
