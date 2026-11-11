FROM php:8.3-cli

# تثبيت الاعتماديات
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# تثبيت Composer من الصورة الرسمية
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تحديد مجلد العمل
WORKDIR /var/www

# نسخ المشروع
COPY . .

# تثبيت حزم PHP
RUN composer install --ignore-platform-reqs --optimize-autoloader --no-dev

# صلاحيات التخزين
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 8000

# config:cache و route:cache هنا وليس في RUN لأنها تحتاج متغيرات البيئة
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force \
    && php artisan db:seed --class=AdminSeeder --force \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
