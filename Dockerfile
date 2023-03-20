FROM php:7.4-fpm
RUN apt-get update && apt-get install -y --no-install-recommends unzip imagemagick libmagickwand-dev npm wget
RUN pecl install imagick && docker-php-ext-enable imagick
COPY . .
RUN curl -o composer-setup.php https://getcomposer.org/installer
RUN php composer-setup.php
RUN rm composer-setup.php
RUN php composer.phar install
RUN npm install --global yarn
RUN yarn install
RUN yarn encore dev
RUN wget https://get.symfony.com/cli/installer -O - | bash
RUN mv /root/.symfony5/bin/symfony /usr/local/bin/symfony
CMD [ "symfony", "server:start", "--no-tls" ]
