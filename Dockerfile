FROM php:8.2-cli

# Install mysqli and other required extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install dependencies
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app/

# Run composer install
RUN composer install --no-dev --optimize-autoloader

# Create uploads directories
RUN mkdir -p /app/uploads/storage \
    && mkdir -p /app/uploads/submissions \
    && chmod -R 755 /app/uploads

EXPOSE 80

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-80} -t /app"]
