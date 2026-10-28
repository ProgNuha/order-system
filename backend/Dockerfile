# --- Dev-stage Dockerfile ---
# Ini versi "cepat jalan dulu" buat dipakai selama development.
# Sebelum submit, upgrade ke multi-stage + non-root user (poin Infrastruktur & CI).
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        zip \
        libpq-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000
