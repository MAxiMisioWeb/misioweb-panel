#!/usr/bin/env bash
# Instala o actualiza el panel de MisioWeb en Debian 13.
#
# Uso (desde el repo clonado en la VM):
#   sudo deploy/deploy.sh
#
# Se puede correr las veces que haga falta: la primera vez instala todo y las siguientes
# actualiza dependencias, migraciones y caché. Nunca pisa el .env ni la base existente.
#
# Variables opcionales (con sus valores por defecto):
#   DOMAIN=panel.misioweb.com  PORT=8092  PROXY_IP=192.168.100.2  DEPLOY_USER=<quien corre sudo>
set -euo pipefail

DOMAIN="${DOMAIN:-panel.misioweb.com}"
PORT="${PORT:-8092}"
PROXY_IP="${PROXY_IP:-192.168.100.2}"
DEPLOY_USER="${DEPLOY_USER:-${SUDO_USER:-}}"
DB_NAME="${DB_NAME:-misioweb_panel}"
DB_USER="${DB_USER:-misioweb}"
PHP_VERSION="${PHP_VERSION:-8.4}"

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
APP_DIR="$REPO_DIR/backend"

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die() { printf '\033[1;31mError: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Correlo con sudo: sudo deploy/deploy.sh"
[ -n "$DEPLOY_USER" ] && [ "$DEPLOY_USER" != root ] || die "Correlo con sudo desde tu usuario (no como root), o definí DEPLOY_USER."
[ -f "$APP_DIR/artisan" ] || die "No encuentro $APP_DIR/artisan. ¿Estás en el repo misioweb-panel?"
[ -f "$APP_DIR/.env.example" ] || die "Falta $APP_DIR/.env.example. Corré git pull."
as_user() { sudo -u "$DEPLOY_USER" -H "$@"; }
# artisan corre como www-data, el mismo usuario que PHP-FPM, para que logs y caché tengan un solo dueño.
artisan() { sudo -u www-data php "$APP_DIR/artisan" "$@"; }

say "Paquetes del sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q nginx postgresql composer unzip git \
	"php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-pgsql" "php$PHP_VERSION-mbstring" \
	"php$PHP_VERSION-xml" "php$PHP_VERSION-curl" "php$PHP_VERSION-zip" "php$PHP_VERSION-intl" "php$PHP_VERSION-bcmath"

say "Base de datos PostgreSQL"
systemctl enable --now postgresql
ENV_FILE="$APP_DIR/.env"
role_exists() { sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1; }
if [ ! -f "$ENV_FILE" ]; then
	# Sin .env nadie usa la contraseña de la base: se genera una nueva. Cubre también
	# una instalación anterior que se cortó después de crear el usuario.
	DB_PASS="$(openssl rand -hex 24)"
	if role_exists; then
		sudo -u postgres psql -q -c "ALTER ROLE $DB_USER LOGIN PASSWORD '$DB_PASS'"
		echo "Usuario $DB_USER ya existía: contraseña renovada."
	else
		sudo -u postgres psql -q -c "CREATE ROLE $DB_USER LOGIN PASSWORD '$DB_PASS'"
		echo "Usuario $DB_USER creado."
	fi
elif ! role_exists; then
	die "Existe $ENV_FILE pero no el usuario $DB_USER en PostgreSQL. Revisalo a mano antes de seguir."
fi
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1; then
	sudo -u postgres createdb -O "$DB_USER" "$DB_NAME"
	echo "Base $DB_NAME creada."
fi

say "Configuración (.env)"
if [ ! -f "$ENV_FILE" ]; then
	cp "$APP_DIR/.env.example" "$ENV_FILE"
	set_env() { sed -i "s#^\#\?\s*$1=.*#$1=$2#" "$ENV_FILE"; grep -q "^$1=" "$ENV_FILE" || echo "$1=$2" >> "$ENV_FILE"; }
	set_env APP_NAME '"MisioWeb Panel"'
	set_env APP_ENV production
	set_env APP_DEBUG false
	set_env APP_URL "https://$DOMAIN"
	set_env APP_LOCALE es
	set_env APP_FALLBACK_LOCALE es
	set_env APP_FAKER_LOCALE es_AR
	set_env TRUSTED_PROXIES "$PROXY_IP"
	set_env LOG_LEVEL warning
	set_env DB_CONNECTION pgsql
	set_env DB_HOST 127.0.0.1
	set_env DB_PORT 5432
	set_env DB_DATABASE "$DB_NAME"
	set_env DB_USERNAME "$DB_USER"
	set_env DB_PASSWORD "$DB_PASS"
	set_env SESSION_SECURE_COOKIE true
	chown "$DEPLOY_USER:www-data" "$ENV_FILE"
	chmod 640 "$ENV_FILE"
	echo "Creado $ENV_FILE (no se sube al repo)."
else
	echo "Ya existe $ENV_FILE, no se toca."
fi

say "Dependencias de PHP"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$REPO_DIR"
chown "$DEPLOY_USER:www-data" "$ENV_FILE"
as_user composer install --working-dir="$APP_DIR" --no-dev --optimize-autoloader --no-interaction --no-progress

say "Permisos de escritura para www-data"
chgrp -R www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod g+s {} +
# Nginx necesita poder atravesar el home del usuario hasta backend/public.
dir="$APP_DIR"
while [ "$dir" != "/" ]; do chmod o+x "$dir"; dir="$(dirname "$dir")"; done

say "Laravel"
grep -q '^APP_KEY=base64:' "$ENV_FILE" || as_user php "$APP_DIR/artisan" key:generate --force
artisan migrate --force
artisan db:seed --force
artisan optimize

say "Nginx (puerto $PORT)"
cat > /etc/nginx/sites-available/misioweb-panel <<NGINX
# Generado por deploy/deploy.sh. El HTTPS lo resuelve el proxy ($PROXY_IP).
server {
    listen $PORT;
    listen [::]:$PORT;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20m;

    set_real_ip_from $PROXY_IP;
    real_ip_header X-Forwarded-For;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|\$) {
        fastcgi_pass unix:/run/php/php$PHP_VERSION-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)\$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* { deny all; }
    location ~ \.php\$ { return 404; }
}
NGINX
ln -sfn /etc/nginx/sites-available/misioweb-panel /etc/nginx/sites-enabled/misioweb-panel
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php$PHP_VERSION-fpm" nginx
systemctl reload "php$PHP_VERSION-fpm" nginx

say "Comprobación"
code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" "http://127.0.0.1:$PORT/up" || true)"
if [ "$code" = 200 ]; then
	echo "OK: el panel responde en el puerto $PORT. Probá https://$DOMAIN/up"
	echo "Comandos de artisan: sudo -u www-data php $APP_DIR/artisan <comando>"
else
	die "El panel respondió $code en http://127.0.0.1:$PORT/up. Mirá $APP_DIR/storage/logs/laravel.log"
fi
