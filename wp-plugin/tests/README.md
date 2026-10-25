# Tests del plugin MisioWeb Progreso

Tests de integración que corren dentro de un WordPress real con WP-CLI. No hace falta MySQL: se usa SQLite.

## Armar el WordPress de prueba

```bash
wp-plugin/tests/setup-wp.sh /ruta/de/trabajo
```

Instala WordPress 7.1.3, el plugin de SQLite, WooCommerce 11.2.1 y Mercado Pago 7.8.1, todo desde GitHub y Packagist. Enlaza el plugin del repo, así que los cambios se prueban sin copiar nada. Las versiones se pueden cambiar con `WP_VERSION`, `WC_VERSION`, `MP_VERSION` y `SQLITE_VERSION`.

Requiere php (con `pdo_sqlite` y `gd`), git, composer y curl.

## Correr los tests

```bash
WP="php /ruta/de/trabajo/wp-cli.phar --allow-root --path=/ruta/de/trabajo/site"

# Con WooCommerce y Mercado Pago
$WP eval-file wp-plugin/tests/test-plugin.php

# Sin WooCommerce (las señales de tienda tienen que llegar igual con ok = false)
$WP eval-file wp-plugin/tests/test-plugin.php sin-woo --skip-plugins=woocommerce,woocommerce-mercadopago
```

Sale con código 1 si algo falla. Cada test crea lo que necesita y lo borra. Las entradas y páginas que ya existían se pasan a borrador mientras corre y después se restauran.

## Qué cubren

- Señales: `blog_entradas`, `paginas_legales`, `tienda_productos`, `tienda_pasarela` y `reels_plugin`.
- Sincronización: el cuerpo y los encabezados que se envían a `POST /v1/sync`, contra un panel simulado con el filtro `pre_http_request`. Que el token no viaje en el cuerpo y qué pasa cuando el panel falla.
- Widget del escritorio, ajustes (token oculto, solo HTTPS) y cron.

## Limitaciones del entorno de prueba

- WooCommerce instalado desde el código fuente no tiene los JS compilados de su panel de administración: WP-CLI anda, pero las páginas de wp-admin dan error con WooCommerce activo. Para mirar el plugin en el navegador, desactivar WooCommerce (`$WP plugin deactivate woocommerce-mercadopago woocommerce`) o usar un WordPress con el WooCommerce oficial.
- ReelsLoop y multisitio se prueban simulando la lista de plugins activos, no con los plugins reales.
