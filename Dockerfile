FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    librdkafka-dev \
    default-mysql-client \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
