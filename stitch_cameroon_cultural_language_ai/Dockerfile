FROM php:8.2-apache

# Enable Apache modules for URL rewriting
RUN a2enmod rewrite

# Install system dependencies required for pdo_pgsql
RUN apt-get update && apt-get install -y libpq-dev && rm -rf /var/lib/apt/lists/*

# Install PostgreSQL PDO extension
RUN docker-php-ext-install pdo pdo_pgsql

# Copy application files to Apache document root
COPY . /var/www/html/

# Set proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to listen on Railway's PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf && \
    sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf

ENV PORT=80

EXPOSE ${PORT}

# Start Apache in foreground
CMD ["apache2-foreground"]
