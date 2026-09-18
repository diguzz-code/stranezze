FROM php:8.4-apache

# Dipendenze di sistema per le estensioni PHP
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

# Estensioni PHP
RUN docker-php-ext-install pdo zip mbstring exif pcntl bcmath gd

# mod_rewrite per gestire .htaccess e router.php
RUN a2enmod rewrite

# AllowOverride All: senza questo Apache ignora i file .htaccess del progetto
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 80