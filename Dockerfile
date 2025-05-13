FROM php:8.1-apache

# 📦 doinstaluj potrzebne rozszerzenia
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl

# 📂 upewnij się, że katalog tmp/ istnieje i ma prawa zapisu
RUN mkdir -p /var/www/html/tmp && chown -R www-data:www-data /var/www/html/tmp



# 💡 skopiuj kod aplikacji
COPY . /var/www/html/

RUN touch /var/www/html/settings.json && chown www-data:www-data /var/www/html/settings.json

# 🔐 ustaw właściciela na apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
