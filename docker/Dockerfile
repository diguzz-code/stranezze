# docker/Dockerfile
FROM php:8.4-apache

# Installa le dipendenze di sistema necessarie per le estensioni PHP
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Installa le estensioni PHP necessarie per il progetto
RUN docker-php-ext-install pdo pdo_sqlite zip mbstring exif pcntl bcmath gd

# Abilita il modulo mod_rewrite di Apache per supportare .htaccess e router.php
RUN a2enmod rewrite

# Installa Composer (versione stabile più recente)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro di Apache
WORKDIR /var/www/html

# (Opzionale) Copia il php.ini personalizzato se ne hai bisogno
# COPY ./php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Configura Apache per permettere l'uso di .htaccess (AllowOverride All)
# e imposta il DocumentRoot sulla cartella public/
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Espone la porta 80 di Apache
EXPOSE 80