FROM joseluisq/php-fpm:8.5

RUN apk add gcc make autoconf libc-dev pkgconfig imagemagick-dev imagemagick

WORKDIR /var/www

RUN apk add --no-cache tzdata
ENV TZ=Europe/Amsterdam
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_DISABLE_EXTENSIONS=psr

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

# /entrypoint.sh instead of php because /entrypoint.sh disables the psr extension.
RUN /entrypoint.sh bin/console cache:clear --env=prod
RUN /entrypoint.sh bin/console assets:install html --env=prod
RUN php bin/console cache:clear --env=prod

RUN chown -R www-data:www-data *
