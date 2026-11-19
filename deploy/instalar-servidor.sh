#!/usr/bin/env bash
# =============================================================================
#  ERP Simioni Performance — instalação na VPS (Ubuntu 22.04 / 24.04, sem painel)
#
#  Uso (como root, na VPS):
#     bash instalar-servidor.sh erp.seudominio.com.br seu-email@exemplo.com
#
#  Pode ser rodado de novo com segurança: o que já existe é mantido
#  (banco, .env e senhas não são recriados).
# =============================================================================
set -euo pipefail

DOMINIO="${1:-}"
EMAIL="${2:-}"
BASE=/var/www/erp
BACK=$BASE/back
FRONT=$BASE/front
REPO_BACK=git@github-erp-back:gattidev01/ErpSimioniBack.git
REPO_FRONT=git@github-erp-front:gattidev01/ErpSimioni.git
PHPV=8.3
BANCO=erp_oficina
USUARIO_BANCO=erp

verde() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
aviso() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
erro()  { printf '\033[1;31m[x] %s\033[0m\n' "$*"; exit 1; }
senha() { openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | cut -c1-"${1:-24}"; }

[ "$(id -u)" -eq 0 ] || erro "Rode como root (ou: sudo bash instalar-servidor.sh ...)"
. /etc/os-release
[ "${ID:-}" = "ubuntu" ] || erro "Este script é para Ubuntu (encontrado: ${PRETTY_NAME:-desconhecido})."

[ -n "$DOMINIO" ] || read -rp "Subdomínio do sistema (ex.: erp.suaoficina.com.br): " DOMINIO
[ -n "$EMAIL" ]   || read -rp "Seu e-mail (avisos do certificado HTTPS): " EMAIL
[[ "$DOMINIO" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]] || erro "Domínio inválido: $DOMINIO"
[[ "$EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || erro "E-mail inválido: $EMAIL"

export DEBIAN_FRONTEND=noninteractive

# -----------------------------------------------------------------------------
verde "1/11 Atualizando o sistema e instalando pacotes básicos"
apt-get update -q
apt-get upgrade -yq
apt-get install -yq git unzip curl ca-certificates gnupg software-properties-common ufw \
  nginx mysql-server supervisor certbot python3-certbot-nginx dnsutils

# -----------------------------------------------------------------------------
verde "2/11 PHP $PHPV"
if ! apt-cache show "php$PHPV-fpm" >/dev/null 2>&1; then
  # Ubuntu 22.04 não traz o PHP 8.3: usa o repositório mantido por Ondřej Surý
  add-apt-repository -y ppa:ondrej/php
  apt-get update -q
fi
apt-get install -yq "php$PHPV-fpm" "php$PHPV-cli" "php$PHPV-mysql" "php$PHPV-mbstring" "php$PHPV-xml" \
  "php$PHPV-curl" "php$PHPV-zip" "php$PHPV-gd" "php$PHPV-intl" "php$PHPV-bcmath" "php$PHPV-sqlite3"
update-alternatives --set php "/usr/bin/php$PHPV" >/dev/null 2>&1 || true
for ini in "/etc/php/$PHPV/fpm/php.ini" "/etc/php/$PHPV/cli/php.ini"; do
  sed -i -E 's/^;?upload_max_filesize.*/upload_max_filesize = 10M/; s/^;?post_max_size.*/post_max_size = 12M/;
             s/^;?expose_php.*/expose_php = Off/; s#^;?date.timezone.*#date.timezone = America/Sao_Paulo#' "$ini"
done
systemctl enable --now "php$PHPV-fpm"
systemctl restart "php$PHPV-fpm"
PHP_SOCK="/run/php/php$PHPV-fpm.sock"

# -----------------------------------------------------------------------------
verde "3/11 Composer (com verificação de assinatura)"
if ! command -v composer >/dev/null; then
  ESPERADO="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  REAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  [ "$ESPERADO" = "$REAL" ] || { rm -f /tmp/composer-setup.php; erro "Instalador do Composer com assinatura inválida."; }
  php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi
export COMPOSER_ALLOW_SUPERUSER=1

# -----------------------------------------------------------------------------
verde "4/11 Node.js 22 (para compilar o front)"
NODE_MAJOR="$(node -v 2>/dev/null | sed -E 's/^v([0-9]+).*/\1/' || echo 0)"
if [ "${NODE_MAJOR:-0}" -lt 22 ]; then
  install -d -m 0755 /etc/apt/keyrings
  curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
  echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
  apt-get update -q
  apt-get install -yq nodejs
fi

# -----------------------------------------------------------------------------
verde "5/11 Firewall (SSH, HTTP e HTTPS)"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null
ufw status | sed 's/^/    /'

# -----------------------------------------------------------------------------
verde "6/11 Chaves de acesso ao GitHub (somente leitura)"
install -d -m 700 /root/.ssh
for repo in back front; do
  chave="/root/.ssh/erp_$repo"
  [ -f "$chave" ] || ssh-keygen -t ed25519 -N '' -C "deploy-erp-$repo@$DOMINIO" -f "$chave" >/dev/null
done
if ! grep -q 'Host github-erp-back' /root/.ssh/config 2>/dev/null; then
  cat >> /root/.ssh/config <<'CFG'

Host github-erp-back
  HostName github.com
  User git
  IdentityFile /root/.ssh/erp_back
  IdentitiesOnly yes

Host github-erp-front
  HostName github.com
  User git
  IdentityFile /root/.ssh/erp_front
  IdentitiesOnly yes
CFG
  chmod 600 /root/.ssh/config
fi
# só confia no GitHub se a impressão digital bater com a publicada oficialmente
# (docs.github.com -> "GitHub's SSH key fingerprints")
FP_GITHUB='SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU'
if ! ssh-keygen -F github.com -f /root/.ssh/known_hosts >/dev/null 2>&1; then
  ssh-keyscan -t ed25519 github.com 2>/dev/null > /tmp/github.key
  FP="$(ssh-keygen -lf /tmp/github.key | awk '{print $2}')"
  [ "$FP" = "$FP_GITHUB" ] || { rm -f /tmp/github.key; erro "Chave do GitHub não confere ($FP). Conexão suspeita, abortando."; }
  cat /tmp/github.key >> /root/.ssh/known_hosts
  rm -f /tmp/github.key
fi

acesso_ok() { GIT_SSH_COMMAND='ssh -o BatchMode=yes' git ls-remote "$1" >/dev/null 2>&1; }
if ! acesso_ok "$REPO_BACK" || ! acesso_ok "$REPO_FRONT"; then
  cat <<TXT

  Cadastre estas duas chaves no GitHub (uma em cada repositório), SEM marcar "Allow write access":

  1) github.com/gattidev01/ErpSimioniBack  -> Settings -> Deploy keys -> Add deploy key
     Título: VPS ERP
     Chave:
$(sed 's/^/     /' /root/.ssh/erp_back.pub)

  2) github.com/gattidev01/ErpSimioni      -> Settings -> Deploy keys -> Add deploy key
     Título: VPS ERP
     Chave:
$(sed 's/^/     /' /root/.ssh/erp_front.pub)

TXT
  until acesso_ok "$REPO_BACK" && acesso_ok "$REPO_FRONT"; do
    read -rp "  Depois de cadastrar as duas, aperte ENTER para testar o acesso... " _
    acesso_ok "$REPO_BACK"  || aviso "Ainda sem acesso ao ErpSimioniBack."
    acesso_ok "$REPO_FRONT" || aviso "Ainda sem acesso ao ErpSimioni."
  done
fi
echo "    Acesso aos dois repositórios OK."

# -----------------------------------------------------------------------------
verde "7/11 Baixando o código"
install -d -m 755 "$BASE"
[ -d "$BACK/.git" ]  && git -C "$BACK" pull --ff-only  || git clone -q "$REPO_BACK" "$BACK"
[ -d "$FRONT/.git" ] && git -C "$FRONT" pull --ff-only || git clone -q "$REPO_FRONT" "$FRONT"

# -----------------------------------------------------------------------------
verde "8/11 Banco de dados MySQL"
NOVO_ENV=0
if [ ! -f "$BACK/.env" ]; then
  NOVO_ENV=1
  SENHA_BANCO="$(senha 28)"
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$BANCO\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$USUARIO_BANCO'@'localhost' IDENTIFIED BY '$SENHA_BANCO';
ALTER USER '$USUARIO_BANCO'@'localhost' IDENTIFIED BY '$SENHA_BANCO';
GRANT ALL PRIVILEGES ON \`$BANCO\`.* TO '$USUARIO_BANCO'@'localhost';
FLUSH PRIVILEGES;
SQL
  echo "    Banco '$BANCO' e usuário '$USUARIO_BANCO' prontos (senha aleatória gravada só no .env)."
else
  echo "    .env já existe: banco e senhas mantidos."
fi

# -----------------------------------------------------------------------------
verde "9/11 Backend (Laravel)"
cd "$BACK"
definir() { # definir CHAVE valor  -> grava/atualiza no .env
  local k="$1" v="$2"
  v="${v//\\/\\\\}"; v="${v//&/\\&}"; v="${v//|/\\|}"
  if grep -qE "^$k=" .env; then sed -i -E "s|^$k=.*|$k=$v|" .env; else printf '%s=%s\n' "$1" "$2" >> .env; fi
}
SENHA_ADMIN=""
if [ "$NOVO_ENV" = 1 ]; then
  cp .env.example .env
  SENHA_ADMIN="$(senha 16)"
  definir APP_ENV production
  definir APP_DEBUG false
  definir APP_URL "https://$DOMINIO"
  definir LOG_LEVEL warning
  definir DB_CONNECTION mysql
  definir DB_HOST 127.0.0.1
  definir DB_PORT 3306
  definir DB_DATABASE "$BANCO"
  definir DB_USERNAME "$USUARIO_BANCO"
  definir DB_PASSWORD "$SENHA_BANCO"
  definir SESSION_DOMAIN null
  definir SESSION_SECURE_COOKIE true
  definir SANCTUM_STATEFUL_DOMAINS "$DOMINIO"
  definir FRONTEND_URL "https://$DOMINIO"
  definir MAIL_FROM_ADDRESS "\"nao-responda@${DOMINIO#*.}\""
  definir ADMIN_EMAIL "admin@${DOMINIO#*.}"
  definir ADMIN_PASSWORD "$SENHA_ADMIN"
fi

composer install --no-dev --optimize-autoloader --no-interaction --quiet
if [ "$NOVO_ENV" = 1 ]; then
  php artisan key:generate --force   # como root: precisa escrever no .env
fi

chown -R root:www-data "$BACK"
chmod 640 .env                        # www-data só lê; ninguém mais vê as senhas
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} +

como_web() { sudo -u www-data -H php artisan "$@"; }
como_web migrate --force
if [ "$NOVO_ENV" = 1 ]; then
  como_web db:seed --force
  definir ADMIN_PASSWORD ""   # não deixa a senha do admin guardada em texto no .env
fi
como_web optimize          # cache de config, rotas e views
como_web queue:restart || true

# -----------------------------------------------------------------------------
verde "10/11 Frontend (React)"
cd "$FRONT"
npm ci --no-audit --no-fund --loglevel=error
npm run build
chown -R root:www-data "$FRONT/dist"

# -----------------------------------------------------------------------------
verde "11/11 Nginx, fila de jobs, rotinas diárias e HTTPS"
install -m 644 "$BACK/deploy/seguranca-nginx.conf" /etc/nginx/snippets/erp-seguranca.conf
if [ ! -f /etc/nginx/sites-available/erp ]; then
  sed -e "s#__DOMINIO__#$DOMINIO#g" -e "s#__BACK__#$BACK#g" -e "s#__FRONT__#$FRONT#g" -e "s#__PHP_SOCK__#$PHP_SOCK#g" \
    "$BACK/deploy/nginx-erp.conf" > /etc/nginx/sites-available/erp
fi
ln -sf /etc/nginx/sites-available/erp /etc/nginx/sites-enabled/erp
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

sed "s#__BACK__#$BACK#g" "$BACK/deploy/supervisor-erp.conf" > /etc/supervisor/conf.d/erp.conf
supervisorctl reread >/dev/null
supervisorctl update >/dev/null
supervisorctl status erp-fila | sed 's/^/    /' || true

cat > /etc/cron.d/erp-oficina <<CRON
# Rotinas do ERP (orçamentos vencidos, alertas, lembretes, backup)
* * * * * www-data cd $BACK && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/erp-oficina

# HTTPS: só tenta se o subdomínio já aponta para esta VPS
IP_VPS="$(curl -fsS4 https://api.ipify.org || true)"
IP_DNS="$(dig +short A "$DOMINIO" | tail -n1)"
if [ -n "$IP_VPS" ] && [ "$IP_VPS" = "$IP_DNS" ]; then
  certbot --nginx -d "$DOMINIO" -m "$EMAIL" --agree-tos --redirect --non-interactive
  HTTPS_OK=1
else
  HTTPS_OK=0
  aviso "O DNS de $DOMINIO ainda não aponta para esta VPS (DNS: ${IP_DNS:-vazio} | VPS: ${IP_VPS:-?})."
  aviso "Crie o registro A e, quando propagar, rode:  certbot --nginx -d $DOMINIO -m $EMAIL --agree-tos --redirect"
fi

# -----------------------------------------------------------------------------
verde "Pronto!"
echo "    Endereço:  https://$DOMINIO"
if [ -n "$SENHA_ADMIN" ]; then
  echo "    Login do administrador:  admin@${DOMINIO#*.}"
  echo "    Senha (ANOTE AGORA, não será mostrada de novo):  $SENHA_ADMIN"
fi
[ "$HTTPS_OK" = 1 ] || echo "    (HTTPS pendente: veja o aviso acima)"
echo
echo "    Próximos passos:"
echo "     - Configure o envio de e-mail (SMTP) em $BACK/.env e rode: bash $BACK/deploy/atualizar.sh"
echo "     - Para publicar novas versões depois de um git push: bash $BACK/deploy/atualizar.sh"
