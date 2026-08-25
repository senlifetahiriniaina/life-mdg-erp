# syntax=docker/dockerfile:1
# Build stage
FROM php:8.5-fpm AS builder

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

# Install PHP dependencies. composer.lock is intentionally gitignored (see
# above), so every build resolves ~150 packages fresh against live
# Packagist — nearly all of them (laravel/framework, spatie/*, stancl/tenancy,
# nwidart/laravel-modules, maatwebsite/excel, ...) ship their dist zip via
# GitHub's own REST API (`api.github.com/repos/.../zipball/...`), which is
# subject to GitHub's unauthenticated rate limit of 60 requests/hour per IP —
# easily exhausted by a single fresh install on this scale, especially on a
# VPS sharing a NAT'd IP with other traffic. This is a well-documented,
# common real-world cause of `composer install` failing mid-build with no
# useful local error (confirmed as a live, currently-real exposure for this
# exact composer.json — every major dependency checked resolves via
# api.github.com, not a Packagist-hosted mirror).
#
# The optional `github_token` BuildKit secret (never baked into image layers,
# unlike an ARG) authenticates Composer against the GitHub API when supplied,
# raising the cap to 5,000/hour — CI already wires this from the workflow's
# own `secrets.GITHUB_TOKEN` (always available, no configuration needed) via
# .github/workflows/{docker-build,deploy}.yml; a server-side build supplies
# it via docker-compose.prod.yml's `github_token` secret (sourced from an
# optional GITHUB_TOKEN in .env, see .env.example). Absent entirely, the
# build proceeds exactly as before (anonymous) — this is a resilience
# improvement, not a new requirement.
RUN --mount=type=secret,id=github_token,required=false \
    sh -c 'if [ -s /run/secrets/github_token ]; then composer config -g github-oauth.github.com "$(cat /run/secrets/github_token)"; fi' \
    && composer install --no-scripts --no-dev --prefer-dist --no-interaction

# Copy application
COPY . .

# Install Node.js (22.x, matching ci.yml's NODE_VERSION — vite requires
# Node 20.19+/22.12+ and fails with "CustomEvent is not defined" on 18.x)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# VITE_* env vars are inlined into the built JS at `npm run build` time, not
# read at container runtime — `.env` is intentionally .dockerignore'd (see
# below), so without these build ARGs the frontend would always fall back to
# bootstrap.js's dev default (`wsHost: 'localhost'`) regardless of what
# REVERB_HOST/.env says in production. docker-compose.prod.yml passes these
# through as build args (sourced from the deploy .env) so the browser
# connects to the real public domain over Caddy/wss, not localhost.
ARG VITE_REVERB_APP_KEY=app-key
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

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

# storage/framework/{sessions,views,cache,testing} aren't git-tracked (git
# doesn't track empty directories, and .gitignore only lists files inside
# them) — artisan view:cache fatals with "Please provide a valid cache path"
# without them existing first.
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/framework/testing bootstrap/cache

# Generate Laravel caches. No view:cache: nwidart/laravel-modules registers a
# view namespace path for every module regardless of whether it ships Blade
# templates, and this repo has 19 API/Vue-Inertia-only modules with no
# resources/views directory at all — artisan view:cache walks every
# registered path via Symfony Finder and hard-fails on the first missing one.
# Views still compile fine lazily at runtime; only the precompile step is skipped.
RUN php artisan config:cache \
    && php artisan route:cache

###############################################################################
# Runtime stage
FROM php:8.5-fpm

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
