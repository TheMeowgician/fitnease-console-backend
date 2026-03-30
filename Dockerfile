FROM php:8.4-apache

# Install system dependencies required by PHP extensions
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
# tokenizer, ctype, json are compiled into PHP by default — no install needed
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    bcmath \
    xml \
    zip

# Enable Apache mod_rewrite for Laravel routing (.htaccess support)
RUN a2enmod rewrite

# Point Apache DocumentRoot at Laravel's public/ directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Rewrite Apache's default vhost to use the new DocumentRoot
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy dependency manifests first to leverage Docker layer caching.
# The vendor install layer is only invalidated when composer.json or
# composer.lock actually changes.
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# Copy the rest of the application code
COPY . .

# Generate the optimised autoloader (skip scripts — no .env during build)
RUN composer dump-autoload --optimize --no-dev --no-scripts

# Ensure Laravel framework directories exist and set permissions
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Run package discovery and cache config at container start (when .env is available)
CMD ["sh", "-c", "php artisan package:discover --ansi && php artisan config:clear && apache2-foreground"]

EXPOSE 80
