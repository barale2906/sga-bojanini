<?php

/**
 * Archivo principal de rutas API.
 *
 * URL base: /api
 *
 * @see REF-12 en backend.md
 */

// Fase 1: Autenticación
require __DIR__ . '/api/auth.php';

// Fase 2: Almacenes, Zonas, Ubicaciones
require __DIR__ . '/api/warehouse.php';

// Fase 3: Catálogo Maestro
require __DIR__ . '/api/catalog.php';

// Fase 4: Inventario
require __DIR__ . '/api/inventory.php';

// Fase 5: Compras
require __DIR__ . '/api/purchasing.php';

// Fase 6: Monitoreo
require __DIR__ . '/api/monitoring.php';

// Fase 7: Auditoría
require __DIR__ . '/api/audit.php';

// Fase 8: Integraciones
require __DIR__ . '/api/integration.php';

// Fase 9: Reportes y Dashboard
require __DIR__ . '/api/reports.php';
require __DIR__ . '/api/dashboard.php';

// Fase 10: Centros de Costo y Servicios Médicos
require __DIR__ . '/api/cost-center.php';
