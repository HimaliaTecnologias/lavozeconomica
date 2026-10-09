#!/bin/sh
set -e

cd /var/www/html
umask 0002
mkdir -p public/media var/cache var/log var/share

# Como Territorio Madrid: permisos básicos al arrancar como root.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data public/media var/cache var/log var/share
    chmod -R ug+rwX public/media var/cache var/log var/share
fi

if [ "$(id -u)" != "0" ]; then
    exec docker-php-entrypoint "$@"
fi

case "$1" in
    php-fpm|"")
        exec docker-php-entrypoint php-fpm
        ;;
    composer)
        exec docker-php-entrypoint "$@"
        ;;
    *)
        exec runuser -u www-data -- docker-php-entrypoint "$@"
        ;;
esac
