FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application source code
COPY . /var/www/html/

# Ensure writable upload & database directories
RUN mkdir -p /var/www/html/uploads/temp /var/www/html/database && \
    chmod -R 777 /var/www/html/uploads /var/www/html/database

EXPOSE 80
