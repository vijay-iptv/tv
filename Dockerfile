FROM php:8.2-apache

# Set working directory for Apache
WORKDIR /var/www/html

# Copy public app files (served by Apache)
COPY ./public/ /var/www/html/

# Copy secret files into a non-public folder (safe from web access)
COPY creds.jtv credskey.jtv /var/www/secrets/

# Change Apache port to 10000 (Render requirement)
RUN sed -i 's/80/10000/' /etc/apache2/ports.conf && \
    sed -i 's/:80/:10000/' /etc/apache2/sites-available/000-default.conf

# Expose Render port
EXPOSE 10000

# Enable Apache rewrite module (for pretty URLs/htaccess)
RUN a2enmod rewrite
