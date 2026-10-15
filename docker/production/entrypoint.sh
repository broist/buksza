#!/bin/sh
set -eu
mkdir -p /data /app/storage/logs /app/storage/framework/cache/data /app/storage/framework/sessions /app/storage/framework/views /app/bootstrap/cache
chown -R www-data:www-data /data /app/storage /app/bootstrap/cache
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi
exec su-exec www-data "$@"
