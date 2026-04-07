FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    python3 \
    ffmpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite zip

RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp

WORKDIR /var/www

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
