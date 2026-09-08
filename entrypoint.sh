#!/bin/sh
set -e

# Garante permissões adequadas no diretório storage montado pelo Docker volume
mkdir -p /var/www/html/showdepremios/storage/logs /var/www/html/showdepremios/storage/backups
chown -R www-data:www-data /var/www/html/showdepremios/storage
chmod -R 775 /var/www/html/showdepremios/storage

exec "$@"
