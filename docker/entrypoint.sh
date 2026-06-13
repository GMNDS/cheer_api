#!/bin/sh
set -e

php /var/www/html/database/migrate.php

exec docker-php-entrypoint "$@"
