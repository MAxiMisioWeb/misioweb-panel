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

    // Porcentaje de la implementación que se paga como anticipo; el resto, al publicar.
    'deposit_percent' => 50,

];
