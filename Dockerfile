FROM php:8.3-fpm

# Instalar todas las dependencias del sistema y extensiones PHP en un solo RUN
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libmariadb-dev-compat \
    libmariadb-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    gd \
    opcache \
    zip \
    mbstring \
    exif \
    pcntl \
    bcmath \
    sockets \
    && rm -rf /var/lib/apt/lists/*

# Copiar código fuente y configurar permisos
COPY . /var/www/html
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/www/html/storage

# Configurar Nginx
COPY ./nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.conf

# Configurar PHP-FPM y Supervisord
COPY ./php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Exponer puerto y comando de inicio
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]