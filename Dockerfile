FROM php:8.2-cli

WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Increase PHP memory limit
RUN echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/memory-limit.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy app
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Generate key if not set
RUN php artisan key:generate || true

# Cache config & views (skip route cache if memory is low)
RUN php artisan config:cache \
    && php artisan view:cache

# Expose Railway port
EXPOSE 8080

# Use artisan serve so HTTP requests work
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
