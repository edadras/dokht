FROM composer:2 AS php-dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine AS application
# nodejs برای اندازه کردنِ آواتار به تنِ مشتری در زمانِ اجرا (AvatarFitService → tests/js/avatar-body.mjs)
RUN apk add --no-cache icu-libs libpng libjpeg-turbo freetype libzip nginx supervisor nodejs \
    && apk add --no-cache --virtual .build-deps icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) bcmath exif gd intl opcache pcntl pdo_mysql zip \
    && apk del .build-deps

WORKDIR /var/www/html
COPY . .
COPY --from=php-dependencies /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/dokht.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-dokht.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/dokht-entrypoint

RUN chmod +x /usr/local/bin/dokht-entrypoint \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080
ENTRYPOINT ["dokht-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]


