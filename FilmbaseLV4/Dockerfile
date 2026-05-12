FROM php:8.2-apache

# MySQL ekstenzije za PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Kopiraj projekt
COPY . /var/www/html/

# Permisije
RUN chown -R www-data:www-data /var/www/html

# Apache koristi port 80
EXPOSE 80
