FROM php:8.5.12-fpm-alpine

WORKDIR /var/www

# PHP extensions needed by the application that aren't in the official image by default.
RUN apk add --no-cache tzdata imagemagick imagemagick-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS imagemagick-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'memory_limit = 512M\nsession.gc_maxlifetime = 7200\n' > "$PHP_INI_DIR/conf.d/bnls-images.ini"

ENV TZ=Europe/Amsterdam
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY .env ./
COPY composer.json ./
COPY composer.lock ./
COPY composer.phar ./
COPY symfony.lock ./
COPY bin bin/
COPY config config/
COPY html html/
COPY src src/
COPY templates templates/
COPY translations translations/

RUN php composer.phar install --no-plugins --no-scripts
RUN php composer.phar dump-autoload --no-dev --classmap-authoritative

RUN php bin/console cache:clear --env=prod
RUN php bin/console assets:install html --env=prod

RUN chown -R www-data:www-data *
