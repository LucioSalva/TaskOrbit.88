# =============================================================
#  TaskOrbit — PHP 8.2 + Apache (modo Laragon, subpath dinamico)
# =============================================================
FROM php:8.2-apache

# ---- Paquetes del sistema y libs nativas ----
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        unzip \
        git \
        ca-certificates \
        tzdata \
    && rm -rf /var/lib/apt/lists/*

# ---- Extensiones PHP requeridas por TaskOrbit ----
#  pdo_pgsql / pgsql -> conexion a PostgreSQL
#  zip / mbstring / gd / fileinfo -> evidencias, validacion MIME, manejo de archivos
RUN docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        mbstring \
        gd \
        fileinfo \
        opcache

# ---- Apache: habilitar rewrite, headers, alias ----
RUN a2enmod rewrite headers expires alias

# ---- Document root -> /var/www/html/public ----
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ---- VirtualHost template (con placeholder __APP_BASE_PATH__) ----
COPY docker/apache/taskorbit.conf /etc/apache2/sites-available/000-default.conf.tpl

# ---- php.ini con limites razonables para subida de evidencias ----
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-taskorbit.ini

# ---- Entrypoint que substituye el subpath antes de arrancar Apache ----
COPY docker/entrypoint.sh /usr/local/bin/taskorbit-entrypoint.sh
RUN chmod +x /usr/local/bin/taskorbit-entrypoint.sh

# ---- Codigo del proyecto ----
WORKDIR /var/www/html
COPY . /var/www/html

# ---- Permisos de storage ----
RUN mkdir -p /var/www/html/storage/evidencias \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/taskorbit-entrypoint.sh"]
