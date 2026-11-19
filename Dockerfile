# =============================================================================
#  ERP Simioni Performance — imagem do backend (Laravel 13 + PHP 8.3)
#  A mesma imagem roda 3 papéis, escolhidos pela variável PAPEL:
#    web    -> Nginx + PHP-FPM servindo a API na porta 8080 (padrão)
#    fila   -> php artisan queue:work   (e-mail, WhatsApp, relatórios, backup)
#    agenda -> php artisan schedule:work (rotinas diárias)
# =============================================================================
FROM php:8.3-fpm-bookworm

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx unzip git \
 && install-php-extensions pdo_mysql gd intl zip bcmath opcache pcntl exif \
 && apt-get clean && rm -rf /var/lib/apt/lists/* \
 && rm -f /etc/nginx/sites-enabled/default

# PHP de produção
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { \
      echo 'upload_max_filesize = 10M'; \
      echo 'post_max_size = 12M'; \
      echo 'memory_limit = 256M'; \
      echo 'expose_php = Off'; \
      echo 'date.timezone = America/Sao_Paulo'; \
      echo 'opcache.enable = 1'; \
      echo 'opcache.validate_timestamps = 0'; \
      echo 'opcache.memory_consumption = 128'; \
    } > "$PHP_INI_DIR/conf.d/zz-erp.ini" \
 && { \
      echo '[www]'; \
      echo 'listen = 127.0.0.1:9000'; \
      echo 'pm = dynamic'; \
      echo 'pm.max_children = 12'; \
      echo 'pm.start_servers = 3'; \
      echo 'pm.min_spare_servers = 2'; \
      echo 'pm.max_spare_servers = 5'; \
      echo 'clear_env = no'; \
      echo 'catch_workers_output = yes'; \
    } > /usr/local/etc/php-fpm.d/zz-erp.conf

WORKDIR /app

# dependências primeiro (camada em cache entre builds)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && php artisan package:discover --ansi \
 && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/conf.d/erp.conf
COPY docker/entrypoint.sh /usr/local/bin/erp-entrypoint
RUN chmod +x /usr/local/bin/erp-entrypoint

ENV PAPEL=web
EXPOSE 8080
ENTRYPOINT ["erp-entrypoint"]
