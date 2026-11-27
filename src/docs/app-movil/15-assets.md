# 15 · Activos

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **53**

## `GET /api/v1/assets/categories`

Listar categorías.

**Permiso requerido:** `activos.ver`

**Query params detectados:** `asset_type`, `is_active`, `search`

**Campos de respuesta (`AssetCategoryResource`):** `id`, `code`, `name`, `asset_type`, `asset_type_label`, `requires_maintenance`, `description`, `is_active`

---

## `GET /api/v1/assets/sites`

Listar sedes con sus sitios (y número de activos por sitio).

**Permiso requerido:** `activos.ver`

**Query params detectados:** `is_active`

**Campos de respuesta (`AssetSiteResource`):** `id`, `name`, `address`, `is_active`

---

## `POST /api/v1/assets/categories`

Crear categoría.

**Permiso requerido:** `activos_catalogos.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `code` | sí | `string`, `max:20` |
| `name` | sí | `string`, `max:120` |
| `asset_type` | sí | `in:biomedical,computing,general` |
| `description` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetCategoryResource`):** `id`, `code`, `name`, `asset_type`, `asset_type_label`, `requires_maintenance`, `description`, `is_active`

---

## `POST /api/v1/assets/sites`

**Permiso requerido:** `activos_catalogos.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:120` |
| `address` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetSiteResource`):** `id`, `name`, `address`, `is_active`

---

## `POST /api/v1/assets/places`

**Permiso requerido:** `activos_catalogos.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `site_id` | sí | `integer`, `exists:asset_sites,id` |
| `name` | sí | `string`, `max:120` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetPlaceResource`):** `id`, `site_id`, `name`, `is_active`

---

## `PUT /api/v1/assets/categories/{id}`

Actualizar categoría.

**Permiso requerido:** `activos_catalogos.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `code` | sí | `string`, `max:20` |
| `name` | sí | `string`, `max:120` |
| `asset_type` | sí | `in:biomedical,computing,general` |
| `description` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetCategoryResource`):** `id`, `code`, `name`, `asset_type`, `asset_type_label`, `requires_maintenance`, `description`, `is_active`

---

## `PUT /api/v1/assets/sites/{id}`

**Permiso requerido:** `activos_catalogos.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:120` |
| `address` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetSiteResource`):** `id`, `name`, `address`, `is_active`

---

## `PUT /api/v1/assets/places/{id}`

**Permiso requerido:** `activos_catalogos.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `site_id` | sí | `integer`, `exists:asset_sites,id` |
| `name` | sí | `string`, `max:120` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetPlaceResource`):** `id`, `site_id`, `name`, `is_active`

---

## `DELETE /api/v1/assets/categories/{id}`

Eliminar categoría (solo si no tiene activos).

**Permiso requerido:** `activos_catalogos.eliminar`

**Parámetros de ruta:** `id`

---

## `DELETE /api/v1/assets/sites/{id}`

**Permiso requerido:** `activos_catalogos.eliminar`

**Parámetros de ruta:** `id`

---

## `DELETE /api/v1/assets/places/{id}`

**Permiso requerido:** `activos_catalogos.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/assets/import/template`

Descargar la plantilla (se genera con los catálogos vigentes).

**Permiso requerido:** `activos.importar`

**Tipo de respuesta:** descarga de archivo

---

## `POST /api/v1/assets/import`

Importar archivo.

**Permiso requerido:** `activos.importar`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:xlsx,csv,xls`, `max:10240` |

---

## `GET /api/v1/assets/reports/inventory`

Inventario de activos en Excel (acepta los mismos filtros del listado).

**Permiso requerido:** `activos.exportar`

**Tipo de respuesta:** descarga de archivo

---

## `GET /api/v1/assets/reports/maintenance-schedule`

Cronograma de mantenimiento en Excel (acepta los filtros del cronograma).

**Permiso requerido:** `activos.exportar`

**Tipo de respuesta:** descarga de archivo

---

## `GET /api/v1/assets/labels`

Etiquetas con código de barras Code128 en PDF.

**Permiso requerido:** `activos.ver`

**Query params detectados:** `ids`

---

## `GET /api/v1/assets/transfers`

Listar traslados.

**Permiso requerido:** `activos.ver`

**Query params detectados:** `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`AssetTransferResource`):** `id`, `asset`, `type`, `type_label`, `status`, `status_label`, `requires_signature`, `from`, `to`, `from_place_id`, `to_place_id`, `external_destination`, `supplier`, `from_responsible`, `to_responsible`, `expected_return_date`, `resulting_status`, `reason`, `requested_by`, `confirmed_at`, `cancel_reason`, `signatures`, `created_at`

---

## `GET /api/v1/assets/transfers/{id}`

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `id` (numérico)

**Campos de respuesta (`AssetTransferResource`):** `id`, `asset`, `type`, `type_label`, `status`, `status_label`, `requires_signature`, `from`, `to`, `from_place_id`, `to_place_id`, `external_destination`, `supplier`, `from_responsible`, `to_responsible`, `expected_return_date`, `resulting_status`, `reason`, `requested_by`, `confirmed_at`, `cancel_reason`, `signatures`, `created_at`

---

## `GET /api/v1/assets/transfers/{id}/act`

Acta del traslado en PDF (con firmas si fue firmado).

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `id` (numérico)

---

## `POST /api/v1/assets/{assetId}/transfers`

Registrar un traslado.

**Permiso requerido:** `activos_traslados.solicitar`

**Parámetros de ruta:** `assetId` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `type` | sí | `in:relocation,external_repair,loan,return,retirement` |
| `to_place_id` | condicional | `integer`, `exists:asset_places,id`, `required_if:type,return`, `required_without_all:to_responsible_id,external_destination,reason`, `nullable` |
| `to_responsible_id` | no | `integer`, `exists:users,id`, `nullable` |
| `external_destination` | condicional | `string`, `max:200`, `required_if:type,external_repair`, `required_if:type,loan`, `nullable` |
| `supplier_id` | no | `integer`, `exists:suppliers,id`, `nullable` |
| `expected_return_date` | no | `date`, `after_or_equal:today`, `nullable` |
| `resulting_status` | no | `in:operational,maintenance,out_of_service,available`, `nullable` |
| `reason` | condicional | `string`, `max:1000`, `required_if:type,retirement`, `nullable` |

**Campos de respuesta (`AssetTransferResource`):** `id`, `asset`, `type`, `type_label`, `status`, `status_label`, `requires_signature`, `from`, `to`, `from_place_id`, `to_place_id`, `external_destination`, `supplier`, `from_responsible`, `to_responsible`, `expected_return_date`, `resulting_status`, `reason`, `requested_by`, `confirmed_at`, `cancel_reason`, `signatures`, `created_at`

---

## `POST /api/v1/assets/transfers/{id}/confirm`

Confirmar con firmas un traslado pendiente.

**Permiso requerido:** `activos_traslados.confirmar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `delivered_by` | no | `array` |
| `delivered_by.name` | condicional | `required_with:delivered_by`, `string`, `max:150` |
| `delivered_by.document` | condicional | `required_with:delivered_by`, `string`, `max:50` |
| `delivered_by.signature` | condicional | `required_with:delivered_by`, `string` |
| `received_by` | sí | `array` |
| `received_by.pin` | no | `string`, `digits:3`, `nullable` |
| `received_by.name` | condicional | `exclude_without:received_by`, `exclude_with:received_by.pin`, `string`, `max:150` |
| `received_by.document` | condicional | `exclude_without:received_by`, `exclude_with:received_by.pin`, `string`, `max:50` |
| `received_by.signature` | condicional | `exclude_without:received_by`, `exclude_with:received_by.pin`, `string` |

**Campos de respuesta (`AssetTransferResource`):** `id`, `asset`, `type`, `type_label`, `status`, `status_label`, `requires_signature`, `from`, `to`, `from_place_id`, `to_place_id`, `external_destination`, `supplier`, `from_responsible`, `to_responsible`, `expected_return_date`, `resulting_status`, `reason`, `requested_by`, `confirmed_at`, `cancel_reason`, `signatures`, `created_at`

---

## `POST /api/v1/assets/transfers/{id}/cancel`

**Permiso requerido:** `activos_traslados.confirmar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `reason` | sí | `string`, `max:1000` |

**Campos de respuesta (`AssetTransferResource`):** `id`, `asset`, `type`, `type_label`, `status`, `status_label`, `requires_signature`, `from`, `to`, `from_place_id`, `to_place_id`, `external_destination`, `supplier`, `from_responsible`, `to_responsible`, `expected_return_date`, `resulting_status`, `reason`, `requested_by`, `confirmed_at`, `cancel_reason`, `signatures`, `created_at`

---

## `GET /api/v1/assets/maintenances`

Cronograma (paginado). Para la vista de calendario use `from`/`to` y `per_page` alto.

**Permiso requerido:** `activos_mantenimientos.ver`

**Query params detectados:** `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`AssetMaintenanceResource`):** `id`, `asset`, `plan_id`, `maintenance_type`, `maintenance_type_label`, `status`, `status_label`, `is_overdue`, `scheduled_date`, `started_at`, `performed_at`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `external_technician_name`, `reported_failure`, `activities_performed`, `findings`, `recommendations`, `parts_replaced`, `cost`, `result`, `result_label`, `registered_by`, `cancel_reason`, `documents_count`, `documents`, `signatures`, `created_at`

---

## `GET /api/v1/assets/maintenances/{id}`

**Permiso requerido:** `activos_mantenimientos.ver`

**Parámetros de ruta:** `id` (numérico)

**Campos de respuesta (`AssetMaintenanceResource`):** `id`, `asset`, `plan_id`, `maintenance_type`, `maintenance_type_label`, `status`, `status_label`, `is_overdue`, `scheduled_date`, `started_at`, `performed_at`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `external_technician_name`, `reported_failure`, `activities_performed`, `findings`, `recommendations`, `parts_replaced`, `cost`, `result`, `result_label`, `registered_by`, `cancel_reason`, `documents_count`, `documents`, `signatures`, `created_at`

---

## `GET /api/v1/assets/maintenances/{id}/report`

Reporte del mantenimiento en PDF.

**Permiso requerido:** `activos_mantenimientos.ver`

**Parámetros de ruta:** `id` (numérico)

---

## `GET /api/v1/assets/{assetId}/maintenance-plans`

**Permiso requerido:** `activos_mantenimientos.ver`

**Parámetros de ruta:** `assetId` (numérico)

**Campos de respuesta (`AssetMaintenancePlanResource`):** `id`, `asset_id`, `maintenance_type`, `maintenance_type_label`, `frequency_value`, `frequency_unit`, `frequency_label`, `start_date`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `notes`, `is_active`, `next_date`

---

## `POST /api/v1/assets/{assetId}/maintenance-plans`

**Permiso requerido:** `activos_mantenimientos.programar`

**Parámetros de ruta:** `assetId` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `maintenance_type` | sí | `in:preventive,calibration,metrological_verification,inspection` |
| `frequency_value` | sí | `integer`, `min:1`, `max:3650` |
| `frequency_unit` | sí | `in:days,months` |
| `start_date` | sí | `date` |
| `performed_by_type` | sí | `in:internal,external` |
| `technician_id` | no | `integer`, `exists:users,id`, `nullable` |
| `supplier_id` | no | `integer`, `exists:suppliers,id`, `nullable` |
| `notes` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetMaintenancePlanResource`):** `id`, `asset_id`, `maintenance_type`, `maintenance_type_label`, `frequency_value`, `frequency_unit`, `frequency_label`, `start_date`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `notes`, `is_active`, `next_date`

---

## `PUT /api/v1/assets/maintenance-plans/{id}`

**Permiso requerido:** `activos_mantenimientos.programar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `maintenance_type` | sí | `in:preventive,calibration,metrological_verification,inspection` |
| `frequency_value` | sí | `integer`, `min:1`, `max:3650` |
| `frequency_unit` | sí | `in:days,months` |
| `start_date` | sí | `date` |
| `performed_by_type` | sí | `in:internal,external` |
| `technician_id` | no | `integer`, `exists:users,id`, `nullable` |
| `supplier_id` | no | `integer`, `exists:suppliers,id`, `nullable` |
| `notes` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AssetMaintenancePlanResource`):** `id`, `asset_id`, `maintenance_type`, `maintenance_type_label`, `frequency_value`, `frequency_unit`, `frequency_label`, `start_date`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `notes`, `is_active`, `next_date`

---

## `DELETE /api/v1/assets/maintenance-plans/{id}`

**Permiso requerido:** `activos_mantenimientos.programar`

**Parámetros de ruta:** `id` (numérico)

---

## `POST /api/v1/assets/{assetId}/maintenances`

Programar una orden puntual (correctivo o no planificado).

**Permiso requerido:** `activos_mantenimientos.programar`

**Parámetros de ruta:** `assetId` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `maintenance_type` | sí | `in:preventive,calibration,metrological_verification,inspection,corrective` |
| `scheduled_date` | sí | `date` |
| `reported_failure` | condicional | `string`, `required_if:maintenance_type,corrective`, `nullable` |
| `performed_by_type` | no | `in:internal,external`, `nullable` |
| `technician_id` | no | `integer`, `exists:users,id`, `nullable` |
| `supplier_id` | no | `integer`, `exists:suppliers,id`, `nullable` |

**Campos de respuesta (`AssetMaintenanceResource`):** `id`, `asset`, `plan_id`, `maintenance_type`, `maintenance_type_label`, `status`, `status_label`, `is_overdue`, `scheduled_date`, `started_at`, `performed_at`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `external_technician_name`, `reported_failure`, `activities_performed`, `findings`, `recommendations`, `parts_replaced`, `cost`, `result`, `result_label`, `registered_by`, `cancel_reason`, `documents_count`, `documents`, `signatures`, `created_at`

---

## `PUT /api/v1/assets/maintenances/{id}`

**Permiso requerido:** `activos_mantenimientos.programar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `scheduled_date` | sí | `date` |
| `reported_failure` | no | `string`, `nullable` |

**Campos de respuesta (`AssetMaintenanceResource`):** `id`, `asset`, `plan_id`, `maintenance_type`, `maintenance_type_label`, `status`, `status_label`, `is_overdue`, `scheduled_date`, `started_at`, `performed_at`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `external_technician_name`, `reported_failure`, `activities_performed`, `findings`, `recommendations`, `parts_replaced`, `cost`, `result`, `result_label`, `registered_by`, `cancel_reason`, `documents_count`, `documents`, `signatures`, `created_at`

---

## `POST /api/v1/assets/maintenances/{id}/start`

**Permiso requerido:** `activos_mantenimientos.ejecutar`

**Parámetros de ruta:** `id` (numérico)

**Campos de respuesta (`AssetMaintenanceResource`):** `id`, `asset`, `plan_id`, `maintenance_type`, `maintenance_type_label`, `status`, `status_label`, `is_overdue`, `scheduled_date`, `started_at`, `performed_at`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `external_technician_name`, `reported_failure`, `activities_performed`, `findings`, `recommendations`, `parts_replaced`, `cost`, `result`, `result_label`, `registered_by`, `cancel_reason`, `documents_count`, `documents`, `signatures`, `created_at`

---

## `POST /api/v1/assets/maintenances/{id}/complete`

Registrar la ejecución.

**Permiso requerido:** `activos_mantenimientos.ejecutar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `performed_at` | sí | `date`, `before_or_equal:now` |
| `performed_by_type` | sí | `in:internal,external` |
| `technician_id` | condicional | `integer`, `exists:users,id`, `required_if:performed_by_type,internal`, `nullable` |
| `supplier_id` | condicional | `integer`, `exists:suppliers,id`, `required_if:performed_by_type,external`, `nullable` |
| `external_technician_name` | condicional | `string`, `max:150`, `required_if:performed_by_type,external`, `nullable` |
| `activities_performed` | sí | `string` |
| `findings` | no | `string`, `nullable` |
| `recommendations` | no | `string`, `nullable` |
| `result` | sí | `in:operational,operational_with_restrictions,out_of_service` |
| `parts_replaced` | no | `array`, `nullable` |
| `parts_replaced.*.name` | sí | `string`, `max:150` |
| `parts_replaced.*.quantity` | sí | `numeric`, `min:0.01` |
| `parts_replaced.*.cost` | no | `numeric`, `min:0`, `nullable` |
| `cost` | no | `numeric`, `min:0`, `nullable` |

**Campos de respuesta (`AssetMaintenanceResource`):** `id`, `asset`, `plan_id`, `maintenance_type`, `maintenance_type_label`, `status`, `status_label`, `is_overdue`, `scheduled_date`, `started_at`, `performed_at`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `external_technician_name`, `reported_failure`, `activities_performed`, `findings`, `recommendations`, `parts_replaced`, `cost`, `result`, `result_label`, `registered_by`, `cancel_reason`, `documents_count`, `documents`, `signatures`, `created_at`

---

## `POST /api/v1/assets/maintenances/{id}/cancel`

**Permiso requerido:** `activos_mantenimientos.cancelar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `reason` | sí | `string`, `max:1000` |

**Campos de respuesta (`AssetMaintenanceResource`):** `id`, `asset`, `plan_id`, `maintenance_type`, `maintenance_type_label`, `status`, `status_label`, `is_overdue`, `scheduled_date`, `started_at`, `performed_at`, `performed_by_type`, `performed_by_type_label`, `technician`, `supplier`, `external_technician_name`, `reported_failure`, `activities_performed`, `findings`, `recommendations`, `parts_replaced`, `cost`, `result`, `result_label`, `registered_by`, `cancel_reason`, `documents_count`, `documents`, `signatures`, `created_at`

---

## `GET /api/v1/assets/{assetId}/documents`

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `assetId` (numérico)

**Query params detectados:** `maintenance_id`

**Campos de respuesta (`AssetDocumentResource`):** `id`, `asset_id`, `maintenance_id`, `document_type`, `document_type_label`, `title`, `original_name`, `mime_type`, `size`, `issued_at`, `expires_at`, `uploaded_by`, `created_at`

---

## `GET /api/v1/assets/documents/{id}/download`

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `id` (numérico)

**Tipo de respuesta:** descarga de archivo

---

## `POST /api/v1/assets/{assetId}/documents`

Cargar documento (multipart/form-data). PDF/JPG/PNG, máx. 10 MB.

**Permiso requerido:** `activos_documentos.cargar`

**Parámetros de ruta:** `assetId` (numérico)

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:pdf,jpg,jpeg,png`, `max:10240` |
| `document_type` | sí | `in:manual,invoice,warranty,invima,calibration_certificate,service_report,photo,protocol,other` |
| `title` | sí | `string`, `max:150` |
| `maintenance_id` | no | `integer`, `nullable` |
| `issued_at` | no | `date`, `nullable` |
| `expires_at` | no | `date`, `after_or_equal:issued_at`, `nullable` |

**Campos de respuesta (`AssetDocumentResource`):** `id`, `asset_id`, `maintenance_id`, `document_type`, `document_type_label`, `title`, `original_name`, `mime_type`, `size`, `issued_at`, `expires_at`, `uploaded_by`, `created_at`

---

## `DELETE /api/v1/assets/documents/{id}`

**Permiso requerido:** `activos_documentos.eliminar`

**Parámetros de ruta:** `id` (numérico)

---

## `GET /api/v1/assets`

Listar activos (paginado).

**Permiso requerido:** `activos.ver`

**Query params detectados:** `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`AssetResource`):** `id`, `code`, `name`, `description`, `category`, `asset_type`, `asset_type_label`, `requires_maintenance`, `status`, `status_label`, `location`, `responsible`, `brand`, `model`, `serial`, `acquisition_date`, `acquisition_value`, `supplier`, `supplier_id`, `invoice_number`, `warranty_until`, `retired_at`, `retirement_reason`, `notes`, `next_maintenance`, `pending_transfer`, `technical_profile`, `counts`, `created_by`, `created_at`, `updated_at`

---

## `GET /api/v1/assets/filters`

Catálogos para filtros y formularios: categorías, sedes/sitios, usuarios
activos (responsables y técnicos), proveedores y enumeraciones.

**Permiso requerido:** `activos.ver`

---

## `GET /api/v1/assets/statistics`

Indicadores (por estado, tipo, sede, valor, mantenimientos y traslados pendientes).

**Permiso requerido:** `activos.ver`

---

## `GET /api/v1/assets/{id}`

Ficha del activo (incluye hoja de vida y contadores).

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `id` (numérico)

**Campos de respuesta (`AssetResource`):** `id`, `code`, `name`, `description`, `category`, `asset_type`, `asset_type_label`, `requires_maintenance`, `status`, `status_label`, `location`, `responsible`, `brand`, `model`, `serial`, `acquisition_date`, `acquisition_value`, `supplier`, `supplier_id`, `invoice_number`, `warranty_until`, `retired_at`, `retirement_reason`, `notes`, `next_maintenance`, `pending_transfer`, `technical_profile`, `counts`, `created_by`, `created_at`, `updated_at`

---

## `GET /api/v1/assets/{id}/movements`

Bitácora de eventos del activo.

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `id` (numérico)

**Query params detectados:** `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`AssetMovementResource`):** `id`, `type`, `type_label`, `from_status`, `from_status_label`, `to_status`, `to_status_label`, `description`, `transfer_id`, `maintenance_id`, `user`, `created_at`

---

## `GET /api/v1/assets/{id}/location-history`

Historial de ubicaciones (más reciente primero).

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `id` (numérico)

**Campos de respuesta (`AssetLocationHistoryResource`):** `id`, `place_id`, `location`, `is_off_premises`, `from_date`, `to_date`, `is_current`, `transfer_id`, `transfer_type`, `transfer_label`, `assigned_by`

---

## `GET /api/v1/assets/{id}/comments`

Comentarios acumulados del activo (más reciente primero).

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `id` (numérico)

**Campos de respuesta (`AssetCommentResource`):** `id`, `comment`, `user`, `created_at`

---

## `GET /api/v1/assets/{assetId}/technical-profile`

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `assetId` (numérico)

**Campos de respuesta (`AssetTechnicalProfileResource`):** `updated_at`

---

## `GET /api/v1/assets/{assetId}/life-sheet`

Hoja de vida completa en PDF (datos, ubicaciones, mantenimientos, documentos y comentarios).

**Permiso requerido:** `activos.ver`

**Parámetros de ruta:** `assetId` (numérico)

---

## `POST /api/v1/assets/{id}/comments`

Agregar comentario (no se edita ni elimina).

**Permiso requerido:** `activos.comentar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `comment` | sí | `string`, `max:2000` |

**Campos de respuesta (`AssetCommentResource`):** `id`, `comment`, `user`, `created_at`

---

## `POST /api/v1/assets`

Registrar activo. El código se genera automáticamente (000001…).

**Permiso requerido:** `activos.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:200` |
| `category_id` | sí | `integer`, `exists:asset_categories,id` |
| `description` | no | `string`, `nullable` |
| `brand` | no | `string`, `max:100`, `nullable` |
| `model` | no | `string`, `max:100`, `nullable` |
| `serial` | no | `string`, `max:100`, `nullable` |
| `acquisition_date` | no | `date`, `before_or_equal:today`, `nullable` |
| `acquisition_value` | no | `numeric`, `min:0`, `max:9999999999999`, `nullable` |
| `supplier_id` | no | `integer`, `exists:suppliers,id`, `nullable` |
| `invoice_number` | no | `string`, `max:50`, `nullable` |
| `warranty_until` | no | `date`, `nullable` |
| `notes` | no | `string`, `nullable` |
| `place_id` | sí | `integer`, `exists:asset_places,id` |
| `responsible_id` | no | `integer`, `exists:users,id`, `nullable` |
| `status` | no | `in:operational,maintenance,out_of_service,available`, `nullable` |
| `comment` | no | `string`, `max:2000`, `nullable` |

**Campos de respuesta (`AssetResource`):** `id`, `code`, `name`, `description`, `category`, `asset_type`, `asset_type_label`, `requires_maintenance`, `status`, `status_label`, `location`, `responsible`, `brand`, `model`, `serial`, `acquisition_date`, `acquisition_value`, `supplier`, `supplier_id`, `invoice_number`, `warranty_until`, `retired_at`, `retirement_reason`, `notes`, `next_maintenance`, `pending_transfer`, `technical_profile`, `counts`, `created_by`, `created_at`, `updated_at`

---

## `PUT /api/v1/assets/{id}`

Actualizar datos generales.

**Permiso requerido:** `activos.editar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:200` |
| `category_id` | sí | `integer`, `exists:asset_categories,id` |
| `description` | no | `string`, `nullable` |
| `brand` | no | `string`, `max:100`, `nullable` |
| `model` | no | `string`, `max:100`, `nullable` |
| `serial` | no | `string`, `max:100`, `nullable` |
| `acquisition_date` | no | `date`, `before_or_equal:today`, `nullable` |
| `acquisition_value` | no | `numeric`, `min:0`, `max:9999999999999`, `nullable` |
| `supplier_id` | no | `integer`, `exists:suppliers,id`, `nullable` |
| `invoice_number` | no | `string`, `max:50`, `nullable` |
| `warranty_until` | no | `date`, `nullable` |
| `notes` | no | `string`, `nullable` |

**Campos de respuesta (`AssetResource`):** `id`, `code`, `name`, `description`, `category`, `asset_type`, `asset_type_label`, `requires_maintenance`, `status`, `status_label`, `location`, `responsible`, `brand`, `model`, `serial`, `acquisition_date`, `acquisition_value`, `supplier`, `supplier_id`, `invoice_number`, `warranty_until`, `retired_at`, `retirement_reason`, `notes`, `next_maintenance`, `pending_transfer`, `technical_profile`, `counts`, `created_by`, `created_at`, `updated_at`

---

## `POST /api/v1/assets/{id}/status`

Cambiar estado (operativo, en mantenimiento, fuera de servicio, disponible).

**Permiso requerido:** `activos.editar`

**Parámetros de ruta:** `id` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `status` | sí | `in:operational,maintenance,out_of_service,available` |
| `reason` | no | `string`, `max:1000`, `nullable` |

**Campos de respuesta (`AssetResource`):** `id`, `code`, `name`, `description`, `category`, `asset_type`, `asset_type_label`, `requires_maintenance`, `status`, `status_label`, `location`, `responsible`, `brand`, `model`, `serial`, `acquisition_date`, `acquisition_value`, `supplier`, `supplier_id`, `invoice_number`, `warranty_until`, `retired_at`, `retirement_reason`, `notes`, `next_maintenance`, `pending_transfer`, `technical_profile`, `counts`, `created_by`, `created_at`, `updated_at`

---

## `PUT /api/v1/assets/{assetId}/technical-profile`

Crear o actualizar la hoja de vida.

**Permiso requerido:** `activos.editar`

**Parámetros de ruta:** `assetId` (numérico)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `manufacturer` | no | `string`, `max:150`, `nullable` |
| `country_of_origin` | no | `string`, `max:80`, `nullable` |
| `manufacture_year` | no | `integer`, `'between:1900,' . ((int) date('Y') + 1)`, `nullable` |
| `useful_life_years` | no | `integer`, `between:1,100`, `nullable` |
| `acquisition_mode` | no | `in:purchase,donation,comodato,leasing`, `nullable` |
| `technical_specs` | no | `array`, `nullable` |
| `technical_specs.*` | no | `string`, `max:255`, `nullable` |
| `accessories` | no | `string`, `nullable` |
| `usage_recommendations` | no | `string`, `nullable` |
| `invima_registry` | no | `string`, `max:80`, `nullable` |
| `risk_class` | no | `in:I,IIa,IIb,III`, `nullable` |
| `biomedical_class` | no | `in:diagnosis,treatment,rehabilitation,prevention,laboratory`, `nullable` |
| `power_source` | no | `in:electric,battery,pneumatic,mechanical,other`, `nullable` |
| `voltage` | no | `string`, `max:40`, `nullable` |
| `current` | no | `string`, `max:40`, `nullable` |
| `power` | no | `string`, `max:40`, `nullable` |
| `frequency` | no | `string`, `max:40`, `nullable` |
| `requires_calibration` | no | `boolean` |
| `operating_conditions` | no | `string`, `nullable` |
| `processor` | no | `string`, `max:120`, `nullable` |
| `ram` | no | `string`, `max:40`, `nullable` |
| `storage` | no | `string`, `max:80`, `nullable` |
| `operating_system` | no | `string`, `max:80`, `nullable` |
| `ip_address` | no | `ip`, `nullable` |
| `mac_address` | no | `mac_address`, `nullable` |
| `hostname` | no | `string`, `max:80`, `nullable` |
| `licenses` | no | `array`, `nullable` |
| `licenses.*.name` | sí | `string`, `max:120` |
| `licenses.*.key` | no | `string`, `max:255`, `nullable` |
| `licenses.*.expires_at` | no | `date`, `nullable` |

**Campos de respuesta (`AssetTechnicalProfileResource`):** `updated_at`

---

## `DELETE /api/v1/assets/{id}`

Eliminar un activo registrado por error (si tiene historia, debe darse de baja).

**Permiso requerido:** `activos.eliminar`

**Parámetros de ruta:** `id` (numérico)

---
