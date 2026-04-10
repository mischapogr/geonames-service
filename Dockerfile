FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN pecl install apcu && docker-php-ext-enable apcu

RUN a2enmod rewrite headers

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html
