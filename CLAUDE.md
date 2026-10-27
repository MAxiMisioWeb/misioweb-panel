# Contexto del proyecto

MisioWeb es una agencia de desarrollo web en Posadas, Misiones, Argentina. Este proyecto es un panel para gestionar los proyectos web de clientes. Tiene dos vistas:

- **Cliente:** elige widgets para su sitio y completa la información que falta (títulos, textos, fotos, accesos).
- **Agencia:** ve en qué puede trabajar, qué bloquea cada sección, y obtiene la propuesta calculada y un mensaje listo para pedir lo pendiente.

Un plugin de WordPress (`wp-plugin/misioweb-progreso`) muestra el avance dentro del WordPress del cliente, verifica solo parte de los requisitos y los envía al panel.

## Estado actual

- `panel/prototipo/panel-proyecto.html`: prototipo original en un solo archivo, con datos en localStorage. Quedó como referencia de diseño: el panel real está en `backend/`.
- `backend/`: Laravel 13 + PostgreSQL. Tiene el esquema de `docs/spec.md`, el catálogo de widgets y requisitos, el cálculo de la propuesta, el mapeo de señales, el login, el circuito de pedidos, el panel de cliente y agencia conectado a la base y la API del plugin (`POST /api/v1/sync`), todo con tests.
- `deploy/deploy.sh`: instala o actualiza el backend en la VM (Debian 13, Nginx en el puerto 8092 detrás del proxy). Ver `deploy/README.md`.
- `wp-plugin/misioweb-progreso/misioweb-progreso.php`: v0.4.0 (detecta Reels Loop y su configuración, trae la URL del panel cargada, avisa si falta configurar la conexión y tiene un diagnóstico local en Ajustes que no se envía al panel). Probado con `php -l` y con tests de integración (`wp-plugin/tests/`) en WordPress 7.1.3, WooCommerce 11.2.1 y Mercado Pago 7.8.1, sobre SQLite. Instalado en el WordPress real de Sankalpa y sincronizando con el panel de producción.

## Decisiones tomadas

- Backend propio: Laravel 13 + PostgreSQL 17 en una VM Debian 13 del cluster Proxmox de MisioWeb (`192.168.100.87`, 2 vCPU, 4 GB). Publicado en `https://panel.misioweb.com` a través del proxy Nginx (`192.168.100.2`), que termina el HTTPS y reenvía al puerto 8092. El firewall de la VM solo acepta el 8092 desde el proxy.
- Acceso y alta de clientes:
  - La persona crea su cuenta con Google o con email, y queda como `prospect`.
  - Elige widgets, ve la inversión y manda un pedido (el proyecto queda en estado `requested`).
  - La agencia lo aprueba: el usuario pasa a `client` y recibe un mail con el acceso. O lo rechaza, y puede mandar otro.
  - Una cuenta por cliente.
- Agencia: las cuentas se crean con `php artisan panel:agencia <email>`; no hay registro abierto para la agencia.
- Clientes que ya trabajan con MisioWeb: la agencia los invita desde el proyecto ("Invitar al cliente"). Se crea o vincula la cuenta como `client` y le llega un enlace para elegir contraseña.
- Correo: Rackspace (`secure.emailsrvr.com`, SMTP 465 SSL) desde `contacto@misioweb.com`. El mail de aprobación puede salir desde `ventas@misioweb.com` (`PANEL_SALES_FROM`).
- Los widgets suman precio al presupuesto. Precios en USD, convertidos a pesos con la cotización dólar Banco Nación venta (campo editable).
- Precios de EJEMPLO (reemplazar por los reales). Base USD 99 de implementación y 14,99 mensual, más:
  blog 30/3, calendario 30/4, ReelsLoop 40/5, Eventos Misioweb 30/6, tienda con Mercado Pago 70/12, botón de WhatsApp 10/1, chatbot 60/9.
- Prueba de cálculo: base + blog + calendario + ReelsLoop + Eventos + tienda = USD 299 y USD 44,99 mensuales. Con cotización 1530 da $457.470 y $68.834,70 (presupuesto real de Sankalpa). Debe haber un test con estos números.
- Pago de implementación: 50% de anticipo y 50% al publicar.
- El plugin envía solo señales (sí/no y conteos). Nunca credenciales de Mercado Pago ni datos de clientes. Token por sitio, preferentemente en `wp-config.php` como `MW_PROGRESO_TOKEN`. Solo HTTPS.
- Las claves de Mercado Pago se cargan directo en WooCommerce, nunca en el panel.
- Cada widget trae sus propios requisitos; los widgets no elegidos no piden nada.
- Después de aprobado el pedido, solo la agencia cambia widgets y cotización. El cliente los ve, pero para cambiarlos tiene que escribir.
- Fotos: los campos de tipo `image` se suben desde el panel con **recorte guiado** (Cropper.js 1.6.2, servido desde `backend/public/vendor/cropperjs`):
  - Banner principal: 16:9 para computadora (1920 × 1080) y 4:5 para tablet y celular (1080 × 1350).
  - Bloques 1 a 3 de la portada: 7:5 para computadora, en medio contenedor (1400 × 1000), y 4:5 para tablet y celular.
  - Imagen de portada de cada entrada del blog: 16:9.
  - Se guarda la foto recortada al tamaño del formato (sin agrandar fotos chicas) y también la original, que la agencia puede descargar.
  - Se guardan en `backend/storage/app/private/projects/{id}/`, fuera de `public/`, y solo las ven el cliente del proyecto y la agencia. JPG, PNG o WebP de hasta 10 MB.
  - Si una foto no tiene el formato pedido (por ejemplo, se subió sin JavaScript), el panel avisa.
- Precios ocultos al prospecto y al cliente durante el período de desarrollo (`PANEL_SHOW_PRICES=false`). La agencia los ve siempre.

## Decisiones pendientes

1. Precios reales por widget. El cliente los ve al armar su pedido. Sigue abierta la alternativa de paquetes cerrados: Esencial, Conectada y Completa, con widgets sueltos como adicionales.
2. Confirmar supuestos del plugin:
   - Páginas legales: privacidad por la página configurada en WordPress o los slugs `privacidad`, `politica-de-privacidad`, `privacy-policy`; condiciones por la página de términos de WooCommerce o `condiciones`, `terminos-y-condiciones`, `terminos`, `terms-and-conditions`. Se pueden cambiar con el filtro `mw_progreso_legal_slugs`.
   - ~~ReelsLoop~~: confirmado con el código de Reels Loop 0.2.1 (plugin de MisioWeb). La carpeta es `reels-loop` y la clase `RL_Plugin`; guarda su configuración en `rl_settings`, `rl_success` y `rl_media`. El plugin de progreso lo detecta por la clase o la carpeta, y lee la cuenta, el token, los hashtags y la sincronización con Meta, siempre como sí/no.
   - Mercado Pago: confirmado con el plugin oficial 7.8.1; sus pasarelas se llaman `woo-mercado-pago-*` y se detectan por `mercado` en el ID.

## Orden de trabajo sugerido

1. ~~Validar y probar el plugin (lint de PHP, WordPress local o de prueba).~~ Hecho en v0.2.0; ver `wp-plugin/tests/README.md`.
2. ~~Definir backend y crear el esquema de `docs/spec.md`.~~ Hecho: Laravel + PostgreSQL, ver `backend/README.md`.
3. ~~Login separado para cliente y agencia.~~ Hecho: Google o email, roles prospect, client y agency, y circuito de pedido y aprobación.
4. ~~Llevar el panel a una app real conectada a datos (mantener el diseño del prototipo).~~ Hecho:
   - Cliente: `/mi-panel` con carga de requisitos y guardado automático.
   - Agencia: `/agencia/proyectos/{id}` con bloqueos por sección, estado interno, propuesta editable, mensaje para el cliente y publicación.
5. ~~Endpoint `POST /v1/sync` y conectar el plugin.~~ Hecho:
   - `POST /api/v1/sync`. La agencia registra el sitio y genera el token desde la vista del proyecto.
   - Conectado y sincronizando con el WordPress real de Sankalpa (sitio ID 1) desde el 26/10/2026.
6. Plantillas de requisitos por tipo de servicio, para que un cliente nuevo arranque con su checklist en un clic.

## Convenciones

- Idioma: español rioplatense en la interfaz y la documentación. Código y nombres técnicos en inglés está bien.
- Marca MisioWeb: primario `#007C75`, acento `#1DB9B0`, texto `#353535`, fondo claro `#F4F7F6`. Títulos en Varela Round, cuerpo en Montserrat Light.
- Tests obligatorios para el cálculo de presupuesto y para el mapeo de señales a requisitos.
- Nunca subir secretos. Usar variables de entorno.
- Commits chicos y descriptivos; una rama por tarea.
