#!/bin/bash
set -e

# Install system dependencies
apt-get update && apt-get install -y unzip libzip-dev git curl

# Install PHP extensions
docker-php-ext-install pdo pdo_mysql zip

# Install Composer
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Install Node.js
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

# Install Project Dependencies
composer install --no-interaction --prefer-dist

# Build Assets
if [ -f package.json ]; then
    npm install
    npm run build
fi

# Initialize Laravel
if [ ! -f .env ]; then
    cp .env.example .env
fi
php artisan key:generate --force
php artisan migrate --force

# Start PHP-FPM
php-fpm
