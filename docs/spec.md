# Especificación técnica (borrador)

## Modelo de datos

- `users`: id, email, google_id, password (nulo si entra con Google), role (`prospect` | `client` | `agency`), client_id (nulo para agencia y para prospectos sin pedido)
- `clients`: id, name
- `projects`: id, client_id, name, plan, fx_rate, status (`requested` | `rejected` | `draft` | `in_progress` | `published`), request_note, requested_at, reviewed_at, reviewed_by, published_at, created_at
- `widgets`: id, key, name, description, price_usd_impl, price_usd_month, active
- `project_widgets`: project_id, widget_id
- `requirement_groups`: id, key, widget_key (nulo = base), name, description, sort. Secciones con nombre y descripción, como las muestra el prototipo.
- `requirement_templates`: id, group (→ `requirement_groups.key`), key, subgroup, label, type (`text` | `area` | `check`), hint, sort. El widget sale de la sección.
- `project_sections`: project_id, group, work_status (`todo` | `doing` | `done`). Estado interno de la agencia por sección; el cliente no lo ve.
- `project_requirements`: id, project_id, template_key, value, done, source (`manual` | `plugin`), updated_at
- `sites`: id, project_id, url, token_hash, last_sync_at
- `signals`: id, site_id, key, ok, detail, created_at

El token del sitio se guarda solo como hash (SHA-256).

Implementado en `backend/database/migrations/2026_10_26_000001_create_panel_tables.php`. El catálogo de widgets y requisitos está en `backend/database/seeders/CatalogSeeder.php`.

## Acceso y pedidos

| Rol | Cómo llega | Qué ve |
|---|---|---|
| `prospect` | Se registra con Google o con email (el email hay que verificarlo) | `/pedido`: elige widgets, ve la inversión estimada y manda el pedido |
| `client` | La agencia aprueba su pedido | `/mi-panel`: su proyecto, el avance y la propuesta |
| `agency` | `php artisan panel:agencia <email>` | `/agencia`: pedidos para aprobar o rechazar y proyectos activos |

- Al mandar el pedido se crea el cliente y un proyecto `requested` con los widgets elegidos. Se avisa por mail a la agencia.
- Al aprobar, el proyecto pasa a `in_progress`, el usuario a `client` y se le manda un mail con el acceso. El remitente es `PANEL_SALES_FROM` si está definido.
- Al rechazar, el proyecto pasa a `rejected` y el prospecto puede mandar otro pedido.
- Con Google solo se aceptan emails verificados. Si el email ya tiene cuenta, se vincula.
- Si un mail no sale, la acción se completa igual: el error queda en el log y se avisa en pantalla.

## Cálculo de la propuesta

- implementación = base + suma de widgets elegidos (USD); abono igual.
- pesos = USD × `fx_rate`, redondeado a 2 decimales.
- anticipo 50% y saldo 50%.
- El abono se computa desde el mes calendario siguiente a la publicación y se paga a mes vencido; los días del mes de publicación quedan bonificados. El mes se toma en hora de Argentina.
- El precio del sitio base está en `backend/config/panel.php`.

Implementado en `backend/app/Services/ProposalCalculator.php`, con tests en `backend/tests/Feature/ProposalCalculatorTest.php`. Los montos se calculan en centavos enteros. Si el anticipo cae en medio centavo, se redondea hacia arriba y el saldo es el resto, así los dos siempre suman el total.

## Endpoints

- `POST /api/v1/sync` (Authorization: Bearer token del sitio). En el plugin, "URL del panel" es `https://panel.misioweb.com/api`.
  - La agencia registra el sitio y genera el token desde la vista del proyecto. El token se muestra una sola vez y se guarda como hash.
  - `site_id` tiene que coincidir con el sitio del token (si no, 403). Sin token o con token inválido, 401.
  - Cada señal acepta solo `ok` (booleano) y `detail` (texto de hasta 255 caracteres), con un máximo de 50 señales. Cualquier otro campo se rechaza (422).
  - Las señales se guardan 90 días. Límite: 30 pedidos por minuto.
  - `items` devuelve una línea por sección (por ejemplo, "Blog (2 de 6)").
  - Body: `{ site_id, site, plugin, wp, signals: { <key>: { ok: bool, detail: string } } }`
  - Respuesta: `{ progress: 0-100, message: string, items: [{ label, status: "done" | "pending" }] }`
- Implementado como web (sesión y CSRF), no como API:
  - `PUT /proyectos/{id}/requisitos`: cliente (solo su proyecto) o agencia. Acepta JSON `{key, value}` para guardar campo por campo, o el formulario `values[key]` de una sección.
  - Un texto cuenta como hecho si no está vacío; una casilla, si está marcada. Lo cargado a mano queda `source = manual`.
  - `PUT /agencia/proyectos/{id}`: cotización y widgets (solo agencia).
  - `PUT /agencia/proyectos/{id}/secciones/{group}`: estado interno de la sección.
  - `POST /agencia/proyectos/{id}/publicar`: pasa a `published` y fija `published_at`, que define desde cuándo se cobra el abono.

## Señales del plugin a requisitos

| Señal | Requisitos que marca |
|---|---|
| `blog_entradas` | Blog: entradas 1 y 2 completas (`b1t`, `b1x`, `b1i`, `b2t`, `b2x`, `b2i`) |
| `paginas_legales` | Privacidad y condiciones aprobados (`mkp`) |
| `tienda_productos` | Cantidad de productos cargados (`wcp`) |
| `tienda_pasarela` | Mercado Pago listo y conectado (`wcm`, `wcc`) |
| `reels_plugin` | Plugin ReelsLoop activo (`igp`) |

Reglas:

- Una señal con `ok = true` marca el requisito como hecho (`source = plugin`).
- Una señal con `ok = false` no desmarca lo que se completó a mano. Solo desmarca lo que había marcado el plugin.
- Solo se tocan los requisitos de secciones que aplican al proyecto. Las señales de widgets no elegidos y las señales desconocidas se ignoran.
- El detalle de la señal no se copia al requisito: queda en `signals`.

Implementado en `backend/app/Services/SignalMapper.php`, con tests en `backend/tests/Feature/SignalMapperTest.php`.
