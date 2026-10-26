# Despliegue en la VM (Debian 13)

Instala el panel en `panel.misioweb.com` detrás del proxy Nginx (`192.168.100.2`), que termina el HTTPS y reenvía a la VM (`192.168.100.87`) por el puerto 8092.

## Primera instalación

En la VM, con el usuario `panel`:

```bash
git clone https://github.com/MAxiMisioWeb/misioweb-panel.git
cd misioweb-panel
git checkout claude/loving-ride-bq7zts   # hasta que se mergee a main
sudo deploy/deploy.sh
```

El script:

1. Instala Nginx, PHP 8.4-FPM, PostgreSQL 17 y Composer.
2. Crea la base `misioweb_panel` y el usuario `misioweb`, con una contraseña aleatoria.
3. Crea `backend/.env`. Ahí queda la contraseña de la base: el archivo no se sube al repo y tiene permisos 640.
4. Instala las dependencias, migra la base y carga el catálogo de widgets y requisitos.
5. Configura Nginx en el puerto 8092 y comprueba que responda.

Al final tiene que decir `OK: el panel responde en el puerto 8092`. Después abrí https://panel.misioweb.com.

Si algo falla, copiá la salida completa.

## Actualizar

```bash
cd ~/misioweb-panel
git pull
sudo deploy/deploy.sh
```

Se puede correr las veces que haga falta: no pisa el `.env` ni borra datos.

## Correo, Google y cuentas de agencia

Se configuran una sola vez editando `backend/.env` en la VM:

```bash
sudo nano ~/misioweb-panel/backend/.env
```

### Correo (Rackspace)

Agregá o completá estas líneas. La contraseña es la de la casilla `contacto@misioweb.com`:

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=secure.emailsrvr.com
MAIL_PORT=465
MAIL_USERNAME=contacto@misioweb.com
MAIL_PASSWORD=la-contraseña-de-la-casilla
MAIL_FROM_ADDRESS=contacto@misioweb.com
MAIL_FROM_NAME="MisioWeb"
```

Para que el mail de aprobación salga desde ventas, agregá también:

```
PANEL_SALES_FROM=ventas@misioweb.com
```

Rackspace solo lo deja salir si `contacto@` tiene permiso para enviar como `ventas@`. Ese permiso se da en el panel de Rackspace (*Send As*), o `ventas@` tiene que ser un alias de la misma casilla. Si no, dejá `PANEL_SALES_FROM` vacío y sale desde `contacto@`.

### Ingreso con Google

En Google Cloud, en el mismo proyecto de la otra app, entrá en *APIs y servicios → Credenciales* y abrí el ID de cliente OAuth. Hay dos opciones:

- agregar en *URI de redireccionamiento autorizados* esta dirección: `https://panel.misioweb.com/auth/google/callback`;
- o crear un ID de cliente nuevo de tipo *Aplicación web* con esa misma dirección.

Después agregá al `.env`:

```
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://panel.misioweb.com/auth/google/callback
```

### Aplicar los cambios

Después de editar el `.env`:

```bash
cd ~/misioweb-panel && sudo deploy/deploy.sh
```

### Cuentas de agencia

```bash
cd ~/misioweb-panel/backend
sudo -u www-data php artisan panel:agencia contacto@misioweb.com --name="MisioWeb"
sudo -u www-data php artisan panel:agencia otra-persona@ejemplo.com --name="Nombre"
```

Cada comando muestra un enlace para elegir contraseña, que vence en 60 minutos. Si el email es de Google, también se puede ingresar con "Ingresar con Google".

## Comandos útiles

Los comandos de `artisan` corren como `www-data`, el mismo usuario que el servidor web:

```bash
cd ~/misioweb-panel/backend
sudo -u www-data php artisan db:seed --class=SankalpaDemoSeeder   # carga el caso de ejemplo
sudo -u www-data php artisan panel:propuesta                      # muestra la propuesta del primer proyecto
tail -n 50 storage/logs/laravel.log                               # errores
```

## Valores por defecto

Se cambian con variables de entorno al correr el script, por ejemplo `sudo PORT=8093 deploy/deploy.sh`.

| Variable | Valor |
|---|---|
| `DOMAIN` | `panel.misioweb.com` |
| `PORT` | `8092` |
| `PROXY_IP` | `192.168.100.2` |
| `DB_NAME` / `DB_USER` | `misioweb_panel` / `misioweb` |
| `PHP_VERSION` | `8.4` |

El firewall de la VM tiene que permitir el puerto 8092 solo desde el proxy:

```bash
sudo ufw allow from 192.168.100.2 to any port 8092 proto tcp
```
