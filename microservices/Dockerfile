FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite headers

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . /var/www/html/UniConnect/microservices/

EXPOSE 80
