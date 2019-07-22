FROM php:7.1-apache

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"\
    && php -r "if (hash_file('sha384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"\
    && php composer-setup.php --install-dir=/usr/bin --filename=composer\
    && php -r "unlink('composer-setup.php');"

RUN apt-get update\ 
    && apt-get install -y tzdata zip unzip\
    && ln -fs /usr/share/zoneinfo/Asia/Manila /etc/localtime\
    && dpkg-reconfigure -f noninteractive tzdata\
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html/cpanel
COPY --chown=www-data cpanel/logs ./logs
COPY --chown=www-data cpanel/public ./public
COPY --chown=www-data cpanel/src ./src
COPY --chown=www-data cpanel/templates ./templates
COPY --chown=www-data cpanel/composer.json ./composer.json
COPY --chown=www-data cpanel/composer.lock ./composer.lock

RUN composer dump-autoload\
    && composer install --no-dev\
    && chown -hR www-data:www-data /var/www/html/cpanel/vendor

WORKDIR /var/www/html/web
COPY --chown=www-data web/logs ./logs
COPY --chown=www-data web/public ./public
COPY --chown=www-data web/src ./src
COPY --chown=www-data web/templates ./templates
COPY --chown=www-data web/composer.json ./composer.json
COPY --chown=www-data web/composer.lock ./composer.lock

RUN composer dump-autoload\
    && composer install --no-dev\
    && chown -hR www-data:www-data /var/www/html/web/vendor

RUN a2enmod rewrite
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]