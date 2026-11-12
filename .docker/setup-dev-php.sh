#!/bin/sh
# One-shot dev setup: vendor/, development config, release metadata.
set -eu
cd "${1:-/app}"

if [ ! -f vendor/autoload.php ] || [ ! vendor/composer/installed.json -nt composer.lock ]; then
    composer install --no-interaction --no-progress
fi
if [ ! -f config/development.config.php ]; then
    composer development-enable
fi
composer generate-release-metadata
