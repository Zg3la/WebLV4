FROM php:8.2-apache

# 1. Postavi zadani port na 80 (Render će ovu varijablu automatski prepisati svojim portom u oblaku)
ENV PORT=80

# 2. Prisili Apache da čita dinamički dodijeljeni port iz varijable okruženja
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# 3. Instalacija potrebnih PHP ekstenzija za spajanje na MySQL bazu
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 4. Kopiranje datoteka projekta u kontejner
COPY . /var/www/html/

# 5. Postavljanje ispravnih vlasničkih prava nad mapom
RUN chown -R www-data:www-data /var/www/html

# 6. Otvaranje ispravnog porta
EXPOSE ${PORT}
