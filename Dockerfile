# Build stage
FROM php:8.4-fpm AS builder

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd exif pcntl pdo pdo_mysql pdo_pgsql zip bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer manifest (composer.lock is intentionally gitignored in this
# repo, see CLAUDE.md — composer install below resolves fresh)
COPY composer.json ./

# Install PHP dependencies
RUN composer install --no-scripts --no-dev --prefer-dist --no-interaction

# Copy application
COPY . .

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node dependencies and build (package-lock.json is intentionally
# gitignored — npm install resolves fresh; npm ci would need an existing
# lockfile, see the .github/workflows/ commit history). --legacy-peer-deps
# matches the same flag already used in ci.yml, needed because
# vue-apexcharts@1.7.0 (Vue 2 peer dep) coexists with vue@^3.5.0 in
# package.json. No --omit=dev here: the build tooling itself (vite, ...)
# lives in devDependencies and is required to run `npm run build` — the
# same reason ci.yml's own frontend-build job never omits dev deps either.
COPY package.json ./
RUN npm install --legacy-peer-deps && npm run build

# Generate Laravel caches
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

###############################################################################
# Runtime stage
FROM php:8.4-fpm

WORKDIR /app

# Install runtime dependencies only (package names below match Debian
# trixie, the base image's current release — libpng16-16t64/libzip5/
# mariadb-client-compat replace the older libpng6/libzip4/mysql-client
# names used on Debian bookworm)
RUN apt-get update && apt-get install -y \
    libpq5 \
    libfreetype6 \
    libjpeg62-turbo \
    libpng16-16t64 \
    libzip5 \
    redis-tools \
    mariadb-client-compat \
    git \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Copy application from builder
COPY --from=builder /app /app

# Create app user
RUN groupadd -g 1000 appgroup && \
    useradd -u 1000 -G www-data -d /app appuser

# Set permissions
RUN chown -R appuser:appgroup /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Copy supervisor config
COPY docker/supervisor.conf /etc/supervisor/conf.d/

# PHP Configuration
COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD php /app/docker/health-check.php

USER appuser

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
