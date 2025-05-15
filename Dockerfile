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

EXPOSE 80
