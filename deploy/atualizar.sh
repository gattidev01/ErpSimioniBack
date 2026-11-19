#!/usr/bin/env bash
# =============================================================================
#  ERP Simioni Performance — publicar nova versão (rodar na VPS, como root)
#     bash /var/www/erp/back/deploy/atualizar.sh
#  Baixa o código novo dos dois repositórios, atualiza dependências e banco,
#  recompila o front e reinicia a fila — sem derrubar o sistema.
# =============================================================================
set -euo pipefail

BACK=/var/www/erp/back
FRONT=/var/www/erp/front
PHPV=8.4
export COMPOSER_ALLOW_SUPERUSER=1

verde() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
[ "$(id -u)" -eq 0 ] || { echo "Rode como root: sudo bash $0"; exit 1; }
como_web() { sudo -u www-data -H php artisan "$@"; }

verde "Backend"
cd "$BACK"
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction --quiet
chown -R root:www-data "$BACK"
chown -R www-data:www-data storage bootstrap/cache
chmod 640 .env
como_web migrate --force
como_web optimize
como_web queue:restart          # a fila termina o job atual e volta com o código novo

verde "Frontend"
cd "$FRONT"
git pull --ff-only
npm ci --no-audit --no-fund --loglevel=error
npm run build
chown -R root:www-data "$FRONT/dist"

# configs que vêm do repositório
install -m 644 "$BACK/deploy/seguranca-nginx.conf" /etc/nginx/snippets/erp-seguranca.conf
nginx -t && systemctl reload nginx
systemctl reload "php$PHPV-fpm"

verde "Atualizado: $(git -C "$BACK" log -1 --format='back %h %s') | $(git -C "$FRONT" log -1 --format='front %h %s')"
