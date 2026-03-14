#!/bin/sh
set -e

# Run migrations automatically
php artisan migrate --force

# Cache config/routes/views for performance (env vars available at runtime)
php artisan config:cache
php artisan route:cache

exec "$@"
