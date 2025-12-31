FROM pvdleek/server-configuration:base_image

ARG IMAGICK_PHP83_FIX_COMMIT=9df92616f577e38625b96b7b903582a46c064739

RUN apk add gcc make autoconf libc-dev pkgconfig imagemagick-dev imagemagick

RUN curl -L https://github.com/remicollet/imagick/archive/${IMAGICK_PHP83_FIX_COMMIT}.zip -o /tmp/imagick-issue-php83.zip  \
    && unzip /tmp/imagick-issue-php83.zip -d /tmp \
    && pecl install /tmp/imagick-${IMAGICK_PHP83_FIX_COMMIT}/package.xml
RUN docker-php-ext-enable imagick

WORKDIR /var/www

RUN apk add --no-cache tzdata
ENV TZ=Europe/Amsterdam
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY .env.local ./.env
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
RUN php bin/console assets:install html
RUN php bin/console cache:clear --env=prod

RUN chown -R www-data:www-data *
