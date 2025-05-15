FROM php:8.1-apache

# 📦 doinstaluj potrzebne rozszerzenia
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl json

# 📂 utwórz i skonfiguruj katalogi
RUN mkdir -p /var/www/html/tmp \
    && mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/tmp \
    && chown -R www-data:www-data /var/www/html/logs \
    && chmod -R 775 /var/www/html/logs

# 💡 skopiuj kod aplikacji
COPY . /var/www/html/

# 📝 utwórz plik settings.json jeśli nie istnieje
RUN touch /var/www/html/settings.json \
    && chown www-data:www-data /var/www/html/settings.json \
    && chmod 664 /var/www/html/settings.json

# 🔧 konfiguracja Apache
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "php_value upload_max_filesize 10M" >> /etc/apache2/apache2.conf \
    && echo "php_value post_max_size 10M" >> /etc/apache2/apache2.conf

# 🔐 ustaw właściciela na apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
