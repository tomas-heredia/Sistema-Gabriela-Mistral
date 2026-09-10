# ---- Stage 1: compila los assets de Vite (CSS/JS) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# ---- Stage 2: instala dependencias de PHP con Composer ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---- Stage 3: imagen final de runtime (PHP-FPM + Nginx + Supervisor) ----
FROM php:8.3-fpm-alpine

# Ojo: los paquetes "-dev" de Alpine arrastran la libreria runtime como
# dependencia -- un "apk del" de los -dev al final se lleva puesta esa
# runtime tambien (nadie mas la referencia desde el punto de vista de apk),
# y las extensiones de PHP quedan sin poder cargar (.so roto en runtime).
# Se deja instalado todo: son ~20-30MB de mas, nada relevante en este VPS.
RUN apk add --no-cache \
        nginx supervisor mysql-client bash \
        libpng-dev libzip-dev freetype-dev libjpeg-turbo-dev icu-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip bcmath intl opcache mbstring

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

COPY deploy/nginx.conf /etc/nginx/nginx.conf
COPY deploy/supervisord.conf /etc/supervisor.d/app.ini
COPY deploy/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor.d/app.ini"]
