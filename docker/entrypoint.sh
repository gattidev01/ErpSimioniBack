#!/bin/sh
# Inicia o container no papel definido em PAPEL (web | fila | agenda).
set -e
cd /app

# volume de storage pode chegar vazio: recria a estrutura e as permissões
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

como_web() { su -s /bin/sh www-data -c "php artisan $*"; }

# espera o MySQL aceitar conexões (até ~2 min)
tentativas=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }'; do
  tentativas=$((tentativas + 1))
  [ "$tentativas" -ge 40 ] && { echo "[erp] MySQL não respondeu a tempo"; exit 1; }
  echo "[erp] aguardando o MySQL..."
  sleep 3
done

como_web optimize

case "${PAPEL:-web}" in
  web)
    # migrations só no container web (os outros esperam ele subir)
    como_web migrate --force
    if [ "${SEED_ADMIN:-0}" = "1" ]; then
      como_web db:seed --force
    fi
    php-fpm -D
    echo "[erp] API pronta na porta 8080"
    exec nginx -g 'daemon off;'
    ;;
  fila)
    exec su -s /bin/sh www-data -c "php artisan queue:work --tries=3 --backoff=60 --max-time=3600 --sleep=3"
    ;;
  agenda)
    exec su -s /bin/sh www-data -c "php artisan schedule:work"
    ;;
  *)
    echo "[erp] PAPEL desconhecido: $PAPEL"; exit 1
    ;;
esac
