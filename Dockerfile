FROM php:8.1-fpm

# Install necessary versions (including nodeJS version 14)
RUN rm -rf /etc/apt/sources.list.d/nodesource.list
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash -
RUN apt-get update && apt-get install -y --no-install-recommends unzip imagemagick libmagickwand-dev nodejs wget
RUN pecl install imagick && docker-php-ext-enable imagick

# Copy the necessary directories and files
WORKDIR /var/www/html

COPY assets assets/
COPY bin bin/
COPY config config/
COPY public public/
COPY src src/
COPY templates templates/
COPY translations translations/
COPY var var/
COPY .env .
COPY composer.json .
COPY composer.lock .
COPY package-lock.json .
COPY package.json .
COPY symfony.lock .
COPY webpack.config.js .
COPY yarn.lock .

# Install all composer packages
RUN curl -o composer-setup.php https://getcomposer.org/installer
RUN php composer-setup.php
RUN rm composer-setup.php
RUN php composer.phar install

# Create the volumes that will be mounted so they have the correct permissions
RUN mkdir public/bnls_2022
RUN mkdir public/bnls_2023

RUN chown -R www-data:www-data .

RUN ln -s public/bnls_2022 bnls_2022
RUN ln -s public/bnls_2023 bnls_2023

# Install yarn and webpack
RUN npm install --global yarn
RUN npm install webpack

# Create the production assets
RUN yarn install
RUN yarn add -D webpack-cli
RUN yarn encore production

# Get and run the symfony server
RUN wget https://get.symfony.com/cli/installer -O - | bash
RUN mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

CMD [ "symfony", "server:start", "--no-tls" ]
