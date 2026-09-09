#!/bin/sh
set -e

# Garante permissões adequadas no diretório storage montado pelo Docker volume
mkdir -p /var/www/html/showdepremios/storage/logs /var/www/html/showdepremios/storage/backups
chmod -R a+rX /var/www/html/showdepremios
chown -R www-data:www-data /var/www/html/showdepremios
chmod -R 775 /var/www/html/showdepremios/storage

exec "$@"
