FROM php:8.2-apache

# Install mysqli and other required extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install additional dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libssl-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Run composer install
RUN composer install --no-dev --optimize-autoloader

# Create uploads directory with correct permissions
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/uploads/storage \
    && mkdir -p /var/www/html/uploads/submissions \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

# Fix MPM conflict and enable rewrite
RUN a2dismod mpm_event mpm_worker 2>/dev/null; \
    a2enmod mpm_prefork rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 80
