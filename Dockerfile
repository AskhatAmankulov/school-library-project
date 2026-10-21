# Web server for the School Library: PHP 8.2 + Apache (the same stack as XAMPP)
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo_mysql \
    # let the project's .htaccess files block access to .sql/.md files and includes/
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && a2enmod rewrite \
    && { echo 'upload_max_filesize = 10M'; echo 'post_max_size = 12M'; echo 'output_buffering = 4096'; } > /usr/local/etc/php/conf.d/library.ini

COPY . /var/www/html/

# Apache must be able to save covers, photos and class settings
RUN chown -R www-data:www-data /var/www/html/uploads /var/www/html/includes
