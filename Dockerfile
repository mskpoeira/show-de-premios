FROM php:8.2-apache

# Install PDO dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libsqlite3-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Copy application files
WORKDIR /var/www/html/showdepremios
COPY . /var/www/html/showdepremios

# Setup storage permissions
RUN mkdir -p storage/logs storage/backups \
    && chown -R www-data:www-data /var/www/html/showdepremios \
    && chmod -R 775 /var/www/html/showdepremios/storage

# Apache virtual host configuration (suporta raiz / e subdiretorio /showdepremios)
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/showdepremios\n\
    Alias /showdepremios /var/www/html/showdepremios\n\
    <Directory /var/www/html/showdepremios>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
