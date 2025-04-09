# Imagen base con PHP-FPM
FROM php:8.4-fpm

# Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    nginx \
    libfreetype-dev \
	libjpeg62-turbo-dev \
	libpng-dev \
	libwebp-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copiar configuración de Nginx y manejar el enlace simbólico
COPY nginx.conf /etc/nginx/sites-available/default
# Eliminar el enlace existente si existe y luego crearlo
RUN rm -f /etc/nginx/sites-enabled/default && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de la aplicación
COPY public/ .

# Dar permisos al usuario www-data
RUN chown -R www-data:www-data /var/www/html

# Exponer puerto 80
EXPOSE 80

# Copiar script de entrada
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Usar el script como punto de entrada
ENTRYPOINT ["entrypoint.sh"]