# Especificación técnica (borrador)

## Modelo de datos

- `users`: id, email, role (`agency` | `client`), client_id (nulo para agencia)
- `clients`: id, name
- `projects`: id, client_id, name, plan, fx_rate, status, created_at
- `widgets`: id, key, name, description, price_usd_impl, price_usd_month, active
- `project_widgets`: project_id, widget_id
- `requirement_templates`: id, widget_key (nulo = base), group, key, label, type (`text` | `area` | `check`), hint
- `project_requirements`: id, project_id, template_key, value, done, source (`manual` | `plugin`), updated_at
- `sites`: id, project_id, url, token_hash, last_sync_at
- `signals`: id, site_id, key, ok, detail, created_at

El token del sitio se guarda solo como hash.

## Cálculo de la propuesta

- implementación = base + suma de widgets elegidos (USD); abono igual.
- pesos = USD × `fx_rate`, redondeado a 2 decimales.
- anticipo 50% y saldo 50%.
- El abono se computa desde el mes calendario siguiente a la publicación y se paga a mes vencido; los días del mes de publicación quedan bonificados.

## Endpoints

- `POST /v1/sync` (Authorization: Bearer token del sitio)
  - Body: `{ site_id, site, plugin, wp, signals: { <key>: { ok: bool, detail: string } } }`
  - Respuesta: `{ progress: 0-100, message: string, items: [{ label, status: "done" | "pending" }] }`
- `GET /v1/projects/:id/requirements` y `PUT` para completar (cliente o agencia)
- `PUT /v1/projects/:id/widgets` para elegir widgets
- `GET /v1/projects/:id/proposal` devuelve el detalle en USD y pesos más el texto

## Señales del plugin a requisitos

| Señal | Requisitos que marca |
|---|---|
| `blog_entradas` | Blog: entradas 1 y 2 completas |
| `paginas_legales` | Privacidad y condiciones aprobados |
| `tienda_productos` | Cantidad de productos cargados |
| `tienda_pasarela` | Mercado Pago listo y conectado |
| `reels_plugin` | Plugin de Instagram activo |

Regla: una señal con `ok = true` marca el requisito como hecho (`source = plugin`). Una señal con `ok = false` no desmarca lo que se completó a mano.
