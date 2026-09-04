FROM php:8.2-apache

# Install SQLite and PDO extensions
RUN apt-get update && apt-get install -y libsqlite3-dev && \
    docker-php-ext-install pdo pdo_mysql pdo_sqlite && \
    rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Increase PHP file upload size limits to 50MB
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Copy application source code
COPY . /var/www/html/

# Ensure writable upload & database directories
RUN mkdir -p /var/www/html/uploads/temp /var/www/html/database && \
    chmod -R 777 /var/www/html/uploads /var/www/html/database

EXPOSE 80
