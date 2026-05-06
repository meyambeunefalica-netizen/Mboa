FROM php:8.2-apache

# Enable Apache modules for URL rewriting
RUN a2enmod rewrite

# Install PostgreSQL PDO extension
RUN docker-php-ext-install pdo pdo_pgsql

# Copy application files to Apache document root
COPY . /var/www/html/

# Set proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for HTTP traffic
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
