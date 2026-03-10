# ── Stage 1: composer dependencies ────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ── Stage 2: production image ────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# Install only what Laravel actually needs
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite-libs \
    && docker-php-ext-install pdo_mysql opcache \
    && rm -rf /var/cache/apk/*

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-onbarber.ini"
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Copy app + vendor from builder
COPY --from=vendor /app /var/www/html

# Create required directories and set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    storage/logs \
    bootstrap/cache \
    database \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
