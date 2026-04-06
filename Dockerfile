FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Set working directory
WORKDIR /var/www

# Copy composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI (optional but useful)
# RUN curl -sS https://get.symfony.com/cli/installer | bash \
#    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony
