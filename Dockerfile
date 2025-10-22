FROM php:8.2-cli-alpine

# Install necessary dependencies for Swoole and Composer (Alpine commands)
RUN apk add --no-cache \
    autoconf \
    gcc \
    g++ \
    make \
    linux-headers \
    libstdc++ \
    openssl-dev \
    curl-dev \
    mysql-dev \
    unzip \
    openssl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Update PECL channel and install extensions
RUN pecl channel-update pecl.php.net \
    && pecl install openswoole redis \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && docker-php-ext-enable openswoole redis

# Generate self-signed certificate
RUN mkdir -p /etc/ssl/certs /etc/ssl/private && \
    openssl req -x509 -newkey rsa:4096 -keyout /etc/ssl/private/key.pem \
    -out /etc/ssl/certs/cert.pem -days 365 -nodes \
    -subj "/C=BR/ST=SP/L=SaoPaulo/O=Test/OU=Test/CN=localhost"

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application
COPY . /app

# Final composer install
RUN composer install --no-dev --optimize-autoloader

# Create logs directory
RUN mkdir -p /app/logs && chmod 777 /app/logs

# Expose ports
EXPOSE 9501

# Command to run both servers
CMD ["sh", "-c", "php public/websocket.php & wait"]
