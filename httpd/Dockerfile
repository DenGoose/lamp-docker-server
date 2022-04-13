FROM php:8.0-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y git unzip zip nano mc

WORKDIR /var/www/html

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd
RUN install-php-extensions pdo
RUN install-php-extensions pdo_mysql
RUN install-php-extensions mysqli
RUN install-php-extensions bcmath
RUN install-php-extensions zip
RUN install-php-extensions intl
RUN install-php-extensions opcache
RUN install-php-extensions xdebug


COPY --from=composer:2.0 /usr/bin/composer /usr/local/bin/composer

RUN export PATH=~/.composer/vendor/bin:$PATH

RUN mkdir /tmp/php_sessions
RUN chmod 777 /tmp/php_sessions
