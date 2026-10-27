#!/usr/bin/env bash
# Arma un WordPress de prueba con SQLite, WooCommerce y Mercado Pago para correr tests/test-plugin.php.
# No necesita MySQL. Todo se baja de GitHub y Packagist (sirve aunque wordpress.org esté bloqueado).
#
#   wp-plugin/tests/setup-wp.sh /ruta/de/trabajo
#
# Requiere: php (con pdo_sqlite y gd), git, composer y curl.
set -euo pipefail

DIR="${1:?Uso: setup-wp.sh /ruta/de/trabajo}"
WP_VERSION="${WP_VERSION:-7.1.3}"
SQLITE_VERSION="${SQLITE_VERSION:-v3.1.1}"
WC_VERSION="${WC_VERSION:-11.2.1}"
MP_VERSION="${MP_VERSION:-v.7.8.1}"
TESTS_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$TESTS_DIR/../misioweb-progreso" && pwd)"

mkdir -p "$DIR" && cd "$DIR"
DIR="$(pwd)"
export COMPOSER_ALLOW_SUPERUSER=1 GIT_LFS_SKIP_SMUDGE=1
clone() { git clone -q -c advice.detachedHead=false --depth 1 "$@"; }

[ -f wp-cli.phar ] || curl -sSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
WP="php $DIR/wp-cli.phar --allow-root --path=$DIR/site"

# WordPress + SQLite
[ -d site ] || { clone --branch "$WP_VERSION" https://github.com/WordPress/wordpress site; rm -rf site/.git; }
[ -d sqlite-repo ] || clone --branch "$SQLITE_VERSION" https://github.com/WordPress/sqlite-database-integration sqlite-repo
SQ=site/wp-content/plugins/sqlite-database-integration
if [ ! -d "$SQ" ]; then
	cp -r sqlite-repo/packages/plugin-sqlite-database-integration "$SQ"
	rm -f "$SQ/wp-includes/database"
	cp -r sqlite-repo/packages/mysql-on-sqlite/src "$SQ/wp-includes/database"
	sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$DIR/$SQ#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$SQ/db.copy" > site/wp-content/db.php
fi
[ -f site/wp-config.php ] || $WP config create --dbname=wp --dbuser=x --dbpass=x --skip-check --quiet
$WP core is-installed 2>/dev/null || $WP core install --url=http://localhost:8080 --title=Prueba --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email

# WooCommerce (desde el código fuente; el admin de WooCommerce no anda porque faltan sus JS compilados)
if [ ! -d wc-repo ]; then
	clone --filter=blob:none --sparse --branch "$WC_VERSION" https://github.com/woocommerce/woocommerce wc-repo
	git -C wc-repo sparse-checkout set plugins/woocommerce packages/php
	(cd wc-repo/plugins/woocommerce && composer install --no-dev --no-interaction --no-progress -q && php bin/generate-feature-config.php)
fi
ln -sfn "$DIR/wc-repo/plugins/woocommerce" site/wp-content/plugins/woocommerce

# Mercado Pago para WooCommerce
if [ ! -d mp-repo ]; then
	clone --branch "$MP_VERSION" https://github.com/mercadopago/cart-woocommerce mp-repo
	(cd mp-repo && composer install --no-dev --no-interaction --no-progress -q)
fi
ln -sfn "$DIR/mp-repo" site/wp-content/plugins/woocommerce-mercadopago

ln -sfn "$PLUGIN_DIR" site/wp-content/plugins/misioweb-progreso
$WP plugin activate woocommerce woocommerce-mercadopago misioweb-progreso

echo "Listo. Tests:"
echo "  $WP eval-file $TESTS_DIR/test-plugin.php"
echo "  $WP eval-file $TESTS_DIR/test-plugin.php sin-woo --skip-plugins=woocommerce,woocommerce-mercadopago"
