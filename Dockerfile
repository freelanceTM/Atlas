FROM php:8.2-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
  libpng-dev \
  libjpeg-dev \
  libfreetype6-dev \
  libzip-dev \
  unzip \
  git \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd zip pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . .

ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer config --global policy.advisories.block false && \
    composer update --no-interaction --no-scripts --optimize-autoloader

EXPOSE 9000

CMD ["php-fpm"]
