FROM node:24-alpine AS frontend
WORKDIR /src
COPY webapp/package*.json ./
RUN npm ci --no-audit --no-fund
COPY webapp/resources ./resources
COPY webapp/tsconfig.json webapp/vite.config.js ./
RUN npm run build

FROM composer:2 AS dependencies
WORKDIR /src
COPY webapp/composer.json webapp/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.5-fpm-alpine AS app
RUN apk add --no-cache su-exec \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j2 bcmath \
    && apk del .build-deps
WORKDIR /app
COPY webapp/ /app/
COPY --from=dependencies /src/vendor /app/vendor
COPY --from=frontend /src/public/build /app/public/build
COPY docker/production/php.ini /usr/local/etc/php/conf.d/buksza.ini
COPY docker/production/entrypoint.sh /usr/local/bin/buksza-entrypoint
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R u=rwX,go=rX /app \
    && chmod 0755 /usr/local/bin/buksza-entrypoint \
    && chmod 0644 /usr/local/etc/php/conf.d/buksza.ini \
    && chown -R www-data:www-data bootstrap/cache storage \
    && php artisan package:discover --no-ansi
ENV APP_ENV=production APP_DEBUG=false
ENTRYPOINT ["buksza-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:alpine AS web
COPY --from=app /app/public /app/public
COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
RUN chmod -R u=rwX,go=rX /app/public \
    && chmod 0644 /etc/nginx/conf.d/default.conf
EXPOSE 8080
