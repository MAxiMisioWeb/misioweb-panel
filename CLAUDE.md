# Contexto del proyecto

MisioWeb es una agencia de desarrollo web en Posadas, Misiones, Argentina. Este proyecto es un panel para gestionar los proyectos web de clientes. Tiene dos vistas:

- **Cliente:** elige widgets para su sitio y completa la información que falta (títulos, textos, fotos, accesos).
- **Agencia:** ve en qué puede trabajar, qué bloquea cada sección, y obtiene la propuesta calculada y un mensaje listo para pedir lo pendiente.

Un plugin de WordPress (`wp-plugin/misioweb-progreso`) muestra el avance dentro del WordPress del cliente, verifica solo parte de los requisitos y los envía al panel.

## Estado actual

- `panel/prototipo/panel-proyecto.html`: prototipo funcional en un solo archivo. Guarda en localStorage, así que cliente y agencia NO comparten datos todavía. Está cargado con el caso de ejemplo Sankalpa.
- `wp-plugin/misioweb-progreso/misioweb-progreso.php`: v0.2.0. Probado con `php -l` y con tests de integración (`wp-plugin/tests/`) en WordPress 7.1.3, WooCommerce 11.2.1 y Mercado Pago 7.8.1, sobre SQLite. Falta probarlo en un WordPress real con el WooCommerce oficial y contra el panel real.

## Decisiones tomadas

- Los widgets suman precio al presupuesto. Precios en USD, convertidos a pesos con la cotización dólar Banco Nación venta (campo editable).
- Precios de EJEMPLO (reemplazar por los reales). Base USD 99 de implementación y 14,99 mensual, más:
  blog 30/3, calendario 30/4, ReelsLoop 40/5, Eventos Misioweb 30/6, tienda con Mercado Pago 70/12, botón de WhatsApp 10/1, chatbot 60/9.
- Prueba de cálculo: base + blog + calendario + ReelsLoop + Eventos + tienda = USD 299 y USD 44,99 mensuales. Con cotización 1530 da $457.470 y $68.834,70 (presupuesto real de Sankalpa). Debe haber un test con estos números.
- Pago de implementación: 50% de anticipo y 50% al publicar.
- El plugin envía solo señales (sí/no y conteos). Nunca credenciales de Mercado Pago ni datos de clientes. Token por sitio, preferentemente en `wp-config.php` como `MW_PROGRESO_TOKEN`. Solo HTTPS.
- Las claves de Mercado Pago se cargan directo en WooCommerce, nunca en el panel.
- Cada widget trae sus propios requisitos; los widgets no elegidos no piden nada.

## Decisiones pendientes

1. Backend: propio (API + base de datos) o uno ya armado como Supabase. Recomendación para validar rápido: Supabase. Maxi tiene disponible hosting de apps PHP en Cloudways o un cluster propio con Proxmox (IP pública, proxy Nginx). Falta confirmar: Supabase (en la nube o instalado en Proxmox) o PHP propio, y en ese caso el framework.
2. Precios reales por widget y si el cliente los ve o solo la agencia (alternativa: paquetes cerrados Esencial, Conectada y Completa, con widgets sueltos como adicionales).
3. Confirmar supuestos del plugin:
   - Páginas legales: privacidad por la página configurada en WordPress o los slugs `privacidad`, `politica-de-privacidad`, `privacy-policy`; condiciones por la página de términos de WooCommerce o `condiciones`, `terminos-y-condiciones`, `terminos`, `terms-and-conditions`. Se pueden cambiar con el filtro `mw_progreso_legal_slugs`.
   - ReelsLoop: se detecta si la ruta del plugin activo contiene `reelsloop`. Falta confirmar el nombre real.
   - Mercado Pago: confirmado con el plugin oficial 7.8.1; sus pasarelas se llaman `woo-mercado-pago-*` y se detectan por `mercado` en el ID.

## Orden de trabajo sugerido

1. ~~Validar y probar el plugin (lint de PHP, WordPress local o de prueba).~~ Hecho en v0.2.0; ver `wp-plugin/tests/README.md`.
2. Definir backend y crear el esquema de `docs/spec.md`.
3. Login separado para cliente y agencia.
4. Llevar el panel a una app real conectada a datos (mantener el diseño del prototipo).
5. Endpoint `POST /v1/sync` y conectar el plugin.
6. Plantillas de requisitos por tipo de servicio, para que un cliente nuevo arranque con su checklist en un clic.

## Convenciones

- Idioma: español rioplatense en la interfaz y la documentación. Código y nombres técnicos en inglés está bien.
- Marca MisioWeb: primario `#007C75`, acento `#1DB9B0`, texto `#353535`, fondo claro `#F4F7F6`. Títulos en Varela Round, cuerpo en Montserrat Light.
- Tests obligatorios para el cálculo de presupuesto y para el mapeo de señales a requisitos.
- Nunca subir secretos. Usar variables de entorno.
- Commits chicos y descriptivos; una rama por tarea.
