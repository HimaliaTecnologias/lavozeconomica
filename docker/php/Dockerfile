FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl xz-utils libicu-dev libzip-dev libmagickwand-dev libjpeg-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql intl opcache gd \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && curl -fsSL "https://github.com/typst/typst/releases/download/v0.13.1/typst-x86_64-unknown-linux-musl.tar.xz" \
        | tar -xJ -C /usr/local/bin --strip-components=1 typst-x86_64-unknown-linux-musl/typst \
    && chmod +x /usr/local/bin/typst \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer vía getcomposer.org (evita pull de composer:2 en Docker Hub).
RUN curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

WORKDIR /var/www/html

COPY . .

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-mymedia.ini
COPY docker/php/entrypoint.sh /usr/local/bin/mymedia-entrypoint
RUN chmod +x /usr/local/bin/mymedia-entrypoint \
    && mkdir -p public/media var/cache var/log \
    && chown -R www-data:www-data public/media var

USER www-data
ENTRYPOINT ["mymedia-entrypoint"]
CMD ["php-fpm"]
