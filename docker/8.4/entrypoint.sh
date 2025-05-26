#!/usr/bin/env sh
set -e

# wait for the database to be ready
# until php artisan migrate:status > /dev/null 2>&1; do
#   echo "• waiting for database…"
#   sleep 2
# done

echo "• running migrations & seeders"
cd /var/www/html
# /usr/bin/php /var/www/html/artisan migrate:fresh --seed

# then launch the main process (php-fpm by default)
exec "$@"
