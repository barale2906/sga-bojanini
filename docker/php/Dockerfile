FROM php:8.3-fpm

WORKDIR /var/www

RUN apt update && apt install -y \
    git unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libzip-dev zip \
    libxml2-dev libgmp-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl xml gmp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && apt clean \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/listen = 127.0.0.1:9000/listen = 9000/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;listen.owner = www-data/listen.owner = www-data/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;listen.group = www-data/listen.group = www-data/' /usr/local/etc/php-fpm.d/www.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
