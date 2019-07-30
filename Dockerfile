FROM php:7.1-apache

WORKDIR /var/www/html
COPY composer-installer ./composer-installer
RUN php /var/www/html/composer-installer --install-dir=/usr/bin --filename=composer

RUN apt-get update\ 
    && apt-get install -y tzdata zip unzip\
    && ln -fs /usr/share/zoneinfo/Asia/Manila /etc/localtime\
    && dpkg-reconfigure -f noninteractive tzdata\
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html
COPY --chown=www-data public ./public
COPY --chown=www-data src ./src
COPY --chown=www-data templates ./templates
COPY --chown=www-data composer.json ./composer.json
COPY --chown=www-data composer.lock ./composer.lock

RUN mkdir -p /var/www/html/public/{downloads,uploads}\
    && chown -hR www-data:www-data /var/www/html/public/downloads\
    && chown -hR www-data:www-data /var/www/html/public/uploads\
    && chmod 777 /var/www/html/public/downloads\
    && chmod 777 /var/www/html/public/uploads\
    && mkdir -p /var/www/html/logs\
    && chown -hR www-data:www-data /var/www/html/logs\
    && chmod 777 /var/www/html/logs

RUN composer dump-autoload\
    && composer install --no-dev\
    && chown -hR www-data:www-data /var/www/html/vendor

RUN a2enmod rewrite
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

VOLUME /var/www/html/logs
VOLUME /var/www/html/public/downloads
VOLUME /var/www/html/public/uploads

CMD ["apache2ctl", "-D", "FOREGROUND"]