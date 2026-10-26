<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sitio base
    |--------------------------------------------------------------------------
    |
    | Precio del sitio base en USD, al que se suman los widgets elegidos.
    | Precios de EJEMPLO: reemplazar por los reales (ver CLAUDE.md).
    |
    */

    'base' => [
        'price_usd_impl' => env('PANEL_BASE_USD_IMPL', '99.00'),
        'price_usd_month' => env('PANEL_BASE_USD_MONTH', '14.99'),
    ],

    // Cotización dólar Banco Nación venta para los pedidos nuevos. Cada proyecto guarda la suya
    // y la agencia la puede ajustar al aprobar.
    'fx_rate' => env('PANEL_FX_RATE', '1530'),

    // Remitente del mail de aprobación de pedidos (ej: ventas@misioweb.com). Si queda vacío,
    // sale desde MAIL_FROM_ADDRESS. La cuenta SMTP tiene que poder enviar como esta dirección.
    'sales_from' => [
        'address' => env('PANEL_SALES_FROM'),
        'name' => env('PANEL_SALES_FROM_NAME', 'MisioWeb'),
    ],

    // Porcentaje de la implementación que se paga como anticipo; el resto, al publicar.
    'deposit_percent' => 50,

];
