# Use the official PHP Apache image (includes PHP 8.2)
FROM php:8.2-apache

# Enable mod_rewrite for clean URLs (if needed)
RUN a2enmod rewrite

# Copy all your PHP files into the Apache document root
COPY . /var/www/html/

# Set proper permissions so Apache can write to registered_users.json
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    touch /var/www/html/registered_users.json && \
    chmod 666 /var/www/html/registered_users.json

# (Optional) If you have a custom php.ini, copy it
# COPY php.ini /usr/local/etc/php/

# Expose port 80 (Render automatically maps PORT to this)
EXPOSE 80