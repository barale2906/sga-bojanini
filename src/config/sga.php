<?php

/**
 * Configuración general del Sistema de Gestión de Almacén (SGA).
 */
return [

    'fefo' => [
        'alert_days' => (int) env('SGA_FEFO_ALERT_DAYS', 30),
    ],

    'reorder' => [
        'check_interval'   => (int) env('SGA_REORDER_CHECK_INTERVAL', 60),
        'consumption_days' => (int) env('SGA_REORDER_CONSUMPTION_DAYS', 90),
    ],

    'purchasing' => [
        'tax_rate' => (float) env('SGA_PURCHASING_TAX_RATE', 0),
    ],

    'monitoring' => [
        'reading_interval' => (int) env('SGA_MONITORING_READING_INTERVAL', 15),
    ],

    'iot' => [
        'token' => env('IOT_TOKEN', 'changeme-generate-a-secure-token'),
    ],

    'integrations' => [
        'use_mock' => env('SGA_INTEGRATIONS_USE_MOCK', true),
    ],

    'service_product_map' => [
        'Consulta Dermatológica' => [
            ['product_code' => 'AGU-21G', 'quantity_per_service' => 2],
            ['product_code' => 'GAS-10X10', 'quantity_per_service' => 1],
        ],
        'Aplicación de Botox' => [
            ['product_code' => 'AGU-21G', 'quantity_per_service' => 1],
            ['product_code' => 'GAS-10X10', 'quantity_per_service' => 2],
        ],
        'Peeling Químico' => [
            ['product_code' => 'GAS-10X10', 'quantity_per_service' => 5],
        ],
        'Láser CO2' => [
            ['product_code' => 'GAS-10X10', 'quantity_per_service' => 3],
        ],
        'Mesoterapia' => [
            ['product_code' => 'AGU-21G', 'quantity_per_service' => 1],
        ],
    ],

    'exports' => [
        'pdf_storage_path'   => env('SGA_PDF_STORAGE_PATH', 'exports/pdf'),
        'excel_storage_path' => env('SGA_EXCEL_STORAGE_PATH', 'exports/excel'),
    ],

    'pagination' => [
        'default_per_page' => (int) env('SGA_DEFAULT_PER_PAGE', 25),
        'max_per_page'     => (int) env('SGA_MAX_PER_PAGE', 100),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/firebase/service-account.json'),
    ],

    'notification_recipients' => [
        'batch_expiring_soon'             => ['administrador', 'jefe_almacen', 'operador_almacen'],
        'batch_expired'                   => ['administrador', 'jefe_almacen', 'operador_almacen'],
        'stock_below_reorder'             => ['administrador', 'jefe_almacen', 'compras'],
        'purchase_order_pending_approval' => ['administrador', 'jefe_almacen'],
        'purchase_order_approved'         => ['compras', 'jefe_almacen'],
        'purchase_order_rejected'         => ['compras'],
        'condition_out_of_range'          => ['administrador', 'jefe_almacen'],
        'condition_trend_alert'           => ['administrador'],
        'consumption_sync_failed'         => ['administrador'],
    ],
];
