FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . .

# Create and set permissions for required directories
RUN mkdir -p /var/www/html/tmp \
    && chown -R www-data:www-data /var/www/html/tmp \
    && chown -R www-data:www-data /var/www/html

# Configure Apache
RUN a2enmod rewrite

# Install Composer
RUN apt-get update && \
    apt-get install -y git unzip && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files and install dependencies
COPY composer.json composer.json
RUN composer install --no-dev --no-interaction --optimize-autoloader

EXPOSE 80
