FROM php:8.2-apache

# Install PHP extensions for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Configure Apache to use Render's default port
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set the project directory
WORKDIR /var/www/html

# Copy your PHP project into the web server
COPY . /var/www/html/

# Set file permissions
RUN chown -R www-data:www-data /var/www/html

# Expose the web server port
EXPOSE 10000

# Start Apache
CMD ["apache2-foreground"]