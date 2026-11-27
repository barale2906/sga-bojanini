# 05 · Inventario (lotes, stock, movimientos)

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **27**

## `GET /api/v1/batches`

**Permiso requerido:** `lotes.ver`

**Query params detectados:** `product_variant_id`, `generic_product_id`, `status`, `category_id`, `cost_center_id`, `notes`, `warehouse_id`, `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`BatchResource`):** `id`, `product_variant_id`, `lot_number`, `expiration_date`, `manufacturing_date`, `quantity_received`, `quantity_available`, `status`, `days_until_expiry`, `notes`, `received_at`

---

## `GET /api/v1/batches/expiring`

**Permiso requerido:** `lotes.ver`

**Query params detectados:** `days`

**Campos de respuesta (`BatchResource`):** `id`, `product_variant_id`, `lot_number`, `expiration_date`, `manufacturing_date`, `quantity_received`, `quantity_available`, `status`, `days_until_expiry`, `notes`, `received_at`

---

## `GET /api/v1/batches/expired`

**Permiso requerido:** `lotes.ver`

**Campos de respuesta (`BatchResource`):** `id`, `product_variant_id`, `lot_number`, `expiration_date`, `manufacturing_date`, `quantity_received`, `quantity_available`, `status`, `days_until_expiry`, `notes`, `received_at`

---

## `GET /api/v1/batches/{id}`

**Permiso requerido:** `lotes.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`BatchResource`):** `id`, `product_variant_id`, `lot_number`, `expiration_date`, `manufacturing_date`, `quantity_received`, `quantity_available`, `status`, `days_until_expiry`, `notes`, `received_at`

---

## `GET /api/v1/products/{id}/batches`

Lista los lotes de un producto.

**Permiso requerido:** `lotes.ver`

**Parámetros de ruta:** `id`

**Query params detectados:** `available_for_exit`, `warehouse_id`

**Campos de respuesta (`BatchResource`):** `id`, `product_variant_id`, `lot_number`, `expiration_date`, `manufacturing_date`, `quantity_received`, `quantity_available`, `status`, `days_until_expiry`, `notes`, `received_at`

---

## `GET /api/v1/stock`

**Permiso requerido:** `stock.ver`

**Query params detectados:** `warehouse_id`, `generic_product_id`, `product_variant_id`, `category_id`, `cost_center_id`, `notes`, `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`StockResource`):** `variant`, `warehouse`, `total_quantity`, `reserved_quantity`, `available_quantity`, `last_note`, `last_movement_at`

---

## `GET /api/v1/stock/summary`

Resumen de stock de un producto en un almacén.

**Permiso requerido:** `stock.ver`

**Query params** (validados):

| Campo | Requerido | Reglas |
|---|---|---|
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `product_variant_id` | sí | `integer`, `exists:product_variants,id` |

**Campos de respuesta (`StockResource`):** `variant`, `warehouse`, `total_quantity`, `reserved_quantity`, `available_quantity`, `last_note`, `last_movement_at`

---

## `GET /api/v1/stock/kit-availability`

Returns the number of kit units that can be assembled from current
component stock in a given warehouse.

**Permiso requerido:** `stock.ver`

**Query params** (validados):

| Campo | Requerido | Reglas |
|---|---|---|
| `kit_generic_id` | sí | `integer`, `exists:product_generics,id` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |

---

## `GET /api/v1/stock/low`

**Permiso requerido:** `stock.ver`

**Query params detectados:** `warehouse_id`

**Campos de respuesta (`StockResource`):** `variant`, `warehouse`, `total_quantity`, `reserved_quantity`, `available_quantity`, `last_note`, `last_movement_at`

---

## `GET /api/v1/movements/initial-entries/template`

**Permiso requerido:** `stock.ver`

**Query params detectados:** `warehouse_id`

**Tipo de respuesta:** descarga de archivo

---

## `POST /api/v1/movements/initial-entries/import`

**Permiso requerido:** `movimientos.importar`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:xlsx,csv,xls`, `max:10240` |
| `warehouse_id` | no | `integer`, `exists:warehouses,id`, `nullable` |

---

## `POST /api/v1/movements/entry`

**Permiso requerido:** `movimientos.entrada`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `movement_date` | no | `date`, `before_or_equal:today`, `nullable` |
| `cost_center_id` | no | `integer`, `exists:cost_centers,id`, `nullable` |
| `invoice_number` | no | `string`, `max:100`, `nullable` |
| `entry_temperature` | no | `numeric`, `nullable` |
| `reason` | no | `string`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.product_variant_id` | sí | `integer`, `exists:product_variants,id` |
| `items.*.location_id` | sí | `integer`, `exists:locations,id` |
| `items.*.lot_number` | sí | `string`, `max:100` |
| `items.*.expiration_date` | sí | `date`, `after_or_equal:2000-01-01`, `before:2100-01-01` |
| `items.*.manufacturing_date` | no | `date`, `after_or_equal:2000-01-01`, `before:2100-01-01`, `nullable` |
| `items.*.quantity_base` | no | `numeric`, `min:0.001`, `nullable` |
| `items.*.product_presentation_id` | no | `integer`, `exists:product_presentations,id`, `nullable` |
| `items.*.quantity_in_presentation` | no | `numeric`, `min:0.001`, `nullable` |
| `items.*.notes` | no | `string`, `nullable` |

**Campos de respuesta (`MovementDocumentResource`):** `id`, `document_number`, `document_type`, `warehouse_id`, `warehouse_to_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `invoice_number`, `entry_temperature`, `reason`, `movement_date`, `user_id`, `status`, `created_at`, `warehouse_name`, `warehouse_to_name`, `user_name`, `cost_center`, `medical_service`, `signatures`, `movements`

---

## `POST /api/v1/movements/exit`

**Permiso requerido:** `movimientos.salida`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `movement_date` | no | `date`, `before_or_equal:today`, `nullable` |
| `cost_center_id` | sí | `integer`, `exists:cost_centers,id` |
| `service_id` | no | `integer`, `exists:medical_services,id`, `nullable` |
| `patient_document` | no | `string`, `max:50`, `nullable` |
| `patient_external_id` | no | `string`, `max:100`, `nullable` |
| `reason` | no | `string`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.generic_product_id` | sí | `integer`, `exists:product_generics,id` |
| `items.*.batch_id` | no | `integer`, `exists:batches,id`, `nullable` |
| `items.*.quantity` | sí | `numeric`, `min:0.001` |
| `items.*.notes` | no | `string`, `nullable` |
| `items.*.location_id` | no | `integer`, `exists:locations,id`, `nullable` |

**Campos de respuesta (`MovementDocumentResource`):** `id`, `document_number`, `document_type`, `warehouse_id`, `warehouse_to_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `invoice_number`, `entry_temperature`, `reason`, `movement_date`, `user_id`, `status`, `created_at`, `warehouse_name`, `warehouse_to_name`, `user_name`, `cost_center`, `medical_service`, `signatures`, `movements`

---

## `POST /api/v1/movements/transfer`

**Permiso requerido:** `movimientos.transferir`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `warehouse_from_id` | sí | `integer`, `exists:warehouses,id` |
| `warehouse_to_id` | sí | `integer`, `exists:warehouses,id` |
| `movement_date` | no | `date`, `before_or_equal:today`, `nullable` |
| `reason` | no | `string`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.product_variant_id` | sí | `integer`, `exists:product_variants,id` |
| `items.*.batch_id` | no | `integer`, `exists:batches,id`, `nullable` |
| `items.*.location_from_id` | no | `integer`, `exists:locations,id`, `nullable` |
| `items.*.location_to_id` | sí | `integer`, `exists:locations,id` |
| `items.*.quantity` | sí | `numeric`, `min:0.001` |
| `items.*.notes` | no | `string`, `nullable` |

**Campos de respuesta (`MovementDocumentResource`):** `id`, `document_number`, `document_type`, `warehouse_id`, `warehouse_to_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `invoice_number`, `entry_temperature`, `reason`, `movement_date`, `user_id`, `status`, `created_at`, `warehouse_name`, `warehouse_to_name`, `user_name`, `cost_center`, `medical_service`, `signatures`, `movements`

---

## `POST /api/v1/movements/adjustment`

**Permiso requerido:** `movimientos.ajuste`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `product_variant_id` | sí | `integer`, `exists:product_variants,id` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `location_id` | sí | `integer`, `exists:locations,id` |
| `batch_id` | no | `integer`, `exists:batches,id`, `nullable` |
| `quantity` | sí | `numeric`, `not_in:0` |
| `reason` | sí | `string` |
| `notes` | no | `string`, `nullable` |

**Campos de respuesta (`MovementResource`):** `id`, `warehouse_id`, `product_variant_id`, `batch_id`, `location_from_id`, `location_to_id`, `movement_type`, `quantity`, `reason`, `reference_type`, `reference_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `user_id`, `created_at`

---

## `POST /api/v1/movements/return`

**Permiso requerido:** `movimientos.devolucion`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `product_variant_id` | sí | `integer`, `exists:product_variants,id` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `location_id` | no | `integer`, `exists:locations,id`, `nullable` |
| `batch_id` | no | `integer`, `exists:batches,id`, `nullable` |
| `quantity` | sí | `numeric`, `min:0.001` |
| `reason` | no | `string`, `nullable` |
| `notes` | no | `string`, `nullable` |

**Campos de respuesta (`MovementResource`):** `id`, `warehouse_id`, `product_variant_id`, `batch_id`, `location_from_id`, `location_to_id`, `movement_type`, `quantity`, `reason`, `reference_type`, `reference_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `user_id`, `created_at`

---

## `POST /api/v1/movements/write-off`

**Permiso requerido:** `movimientos.baja`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `batch_id` | sí | `integer`, `exists:batches,id` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `location_id` | no | `integer`, `exists:locations,id`, `nullable` |
| `reason` | no | `string`, `nullable` |
| `notes` | no | `string`, `nullable` |

**Campos de respuesta (`MovementResource`):** `id`, `warehouse_id`, `product_variant_id`, `batch_id`, `location_from_id`, `location_to_id`, `movement_type`, `quantity`, `reason`, `reference_type`, `reference_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `user_id`, `created_at`

---

## `POST /api/v1/movements/loss`

**Permiso requerido:** `movimientos.baja`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `product_variant_id` | sí | `integer`, `exists:product_variants,id` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `location_id` | sí | `integer`, `exists:locations,id` |
| `batch_id` | sí | `integer`, `exists:batches,id` |
| `quantity` | sí | `numeric`, `min:0.001` |
| `reason` | sí | `string` |
| `notes` | no | `string`, `nullable` |

**Campos de respuesta (`MovementResource`):** `id`, `warehouse_id`, `product_variant_id`, `batch_id`, `location_from_id`, `location_to_id`, `movement_type`, `quantity`, `reason`, `reference_type`, `reference_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `user_id`, `created_at`

---

## `GET /api/v1/movement-documents`

Lista documentos de movimiento con filtros (para comprobantes y reimpresión).

**Permiso requerido:** `stock.ver`

**Query params detectados:** `date_from`, `date_to`, `warehouse_id`, `document_type`, `status`, `cost_center_id`, `notes`, `document_number`, `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`MovementDocumentResource`):** `id`, `document_number`, `document_type`, `warehouse_id`, `warehouse_to_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `invoice_number`, `entry_temperature`, `reason`, `movement_date`, `user_id`, `status`, `created_at`, `warehouse_name`, `warehouse_to_name`, `user_name`, `cost_center`, `medical_service`, `signatures`, `movements`

---

## `GET /api/v1/movement-documents/{id}`

Devuelve el comprobante completo con todas sus líneas.

**Permiso requerido:** `stock.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`MovementDocumentResource`):** `id`, `document_number`, `document_type`, `warehouse_id`, `warehouse_to_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `invoice_number`, `entry_temperature`, `reason`, `movement_date`, `user_id`, `status`, `created_at`, `warehouse_name`, `warehouse_to_name`, `user_name`, `cost_center`, `medical_service`, `signatures`, `movements`

---

## `GET /api/v1/movement-documents/{id}/pdf`

Descarga el PDF del comprobante completo con todas sus líneas y firmas.

**Permiso requerido:** `stock.ver`

**Parámetros de ruta:** `id`

**Tipo de respuesta:** PDF

---

## `DELETE /api/v1/movement-documents/{id}/pending`

**Permiso requerido:** `movimientos.cancelar`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/movement-documents/{id}/confirm`

**Permiso requerido:** `movimientos.confirmar`

**Parámetros de ruta:** `id`

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

**Campos de respuesta (`MovementDocumentResource`):** `id`, `document_number`, `document_type`, `warehouse_id`, `warehouse_to_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `invoice_number`, `entry_temperature`, `reason`, `movement_date`, `user_id`, `status`, `created_at`, `warehouse_name`, `warehouse_to_name`, `user_name`, `cost_center`, `medical_service`, `signatures`, `movements`

---

## `POST /api/v1/movement-documents/{id}/send-email`

**Permiso requerido:** `movimientos.enviar_correo`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `recipients` | sí | `array`, `min:1` |
| `recipients.*` | sí | `email` |

---

## `GET /api/v1/movement-documents/{id}/signature/{role}`

**Permiso requerido:** `stock.ver`

**Parámetros de ruta:** `id`, `role`

---

## `GET /api/v1/movements`

Lista movimientos individuales con filtros (para reportes detallados).

**Permiso requerido:** `stock.ver`

**Query params** (validados):

| Campo | Requerido | Reglas |
|---|---|---|
| `date_from` | no | `date_format:Y-m-d`, `nullable` |
| `date_to` | no | `date_format:Y-m-d`, `after_or_equal:date_from`, `nullable` |
| `warehouse_id` | no | `integer`, `nullable` |
| `generic_product_id` | no | `integer`, `nullable` |
| `product_variant_id` | no | `integer`, `nullable` |
| `category_id` | no | `integer`, `nullable` |
| `movement_type` | no | `string`, `nullable` |
| `cost_center_id` | no | `integer`, `nullable` |
| `cost_center_type` | no | `string`, `in:internal,external`, `nullable` |
| `notes` | no | `string`, `max:200`, `nullable` |
| `warehouse_to_id` | no | `integer`, `nullable` |
| `per_page` | no | `integer`, `min:1`, `nullable` |

**Tipo de respuesta:** paginada (`data` + `meta`)

**Campos de respuesta (`MovementResource`):** `id`, `warehouse_id`, `product_variant_id`, `batch_id`, `location_from_id`, `location_to_id`, `movement_type`, `quantity`, `reason`, `reference_type`, `reference_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `user_id`, `created_at`

---

## `GET /api/v1/movements/{id}`

Endpoint de detalle de movimiento individual (para reportes de línea).

**Permiso requerido:** `stock.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`MovementResource`):** `id`, `warehouse_id`, `product_variant_id`, `batch_id`, `location_from_id`, `location_to_id`, `movement_type`, `quantity`, `reason`, `reference_type`, `reference_id`, `cost_center_id`, `service_id`, `patient_document`, `patient_external_id`, `user_id`, `created_at`

---
