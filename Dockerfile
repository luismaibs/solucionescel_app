FROM php:8.2-apache

# Apache modules required for .htaccess (rewrite, cache headers, gzip)
RUN a2enmod rewrite expires headers deflate

# CA certificates for SSL verification against Supabase
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Allow .htaccess to override Apache config
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Composer for autoloader generation
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy full source first (classmap autoloader needs src/ to exist)
COPY . .

# Generate autoloader (no external deps, classmap scans src/)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Correct ownership so Apache can read all files
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
