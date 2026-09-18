FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

RUN printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/cafe.conf \
    && a2enconf cafe
