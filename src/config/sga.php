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

    'monitoring' => [
        'reading_interval' => (int) env('SGA_MONITORING_READING_INTERVAL', 15),
    ],

    'exports' => [
        'pdf_storage_path'   => env('SGA_PDF_STORAGE_PATH', 'exports/pdf'),
        'excel_storage_path' => env('SGA_EXCEL_STORAGE_PATH', 'exports/excel'),
    ],

    'pagination' => [
        'default_per_page' => (int) env('SGA_DEFAULT_PER_PAGE', 25),
        'max_per_page'     => (int) env('SGA_MAX_PER_PAGE', 100),
    ],
];
