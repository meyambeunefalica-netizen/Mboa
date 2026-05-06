FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite

# Install PostgreSQL dependencies
RUN apt-get update && apt-get install -y libpq-dev && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Copy application files from the subdirectory
COPY stitch_cameroon_cultural_language_ai /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure port for Railway
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf

ENV PORT=80
EXPOSE ${PORT}

# Start Apache
CMD ["apache2-foreground"]
