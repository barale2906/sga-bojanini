# 14 · Órdenes de servicio y listas de precios

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **9**

## `POST /api/v1/service-orders`

Crear una orden de servicio con uno o más procedimientos.

**Permiso requerido:** `ordenes_servicio.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `patient_external_id` | sí | `string`, `max:100` |
| `patient_document` | sí | `string`, `max:50` |
| `patient_first_name` | sí | `string`, `max:100` |
| `patient_last_name` | sí | `string`, `max:100` |
| `patient_email` | no | `email`, `max:100`, `nullable` |
| `patient_address` | no | `string`, `max:150`, `nullable` |
| `patient_phone` | no | `string`, `max:50`, `nullable` |
| `service_date` | sí | `date_format:Y-m-d`, `before_or_equal:today` |
| `notes` | no | `string`, `max:500`, `nullable` |
| `seller` | no | `string`, `max:150`, `nullable` |
| `referrer` | no | `string`, `max:150`, `nullable` |
| `procedures` | sí | `array`, `min:1` |
| `procedures.*.medical_service_id` | sí | `integer`, `exists:medical_services,id` |
| `procedures.*.unit_price` | sí | `numeric`, `min:0` |
| `procedures.*.quantity` | sí | `numeric`, `min:0.0001` |
| `procedures.*.discount_type` | no | `string`, `in:fixed,percentage`, `nullable` |
| `procedures.*.discount_value` | no | `numeric`, `min:0`, `nullable` |
| `procedures.*.notes` | no | `string`, `max:500`, `nullable` |
| `procedures.*.supplies` | no | `array`, `nullable` |
| `procedures.*.supplies.*.warehouse_id` | condicional | `required_with:procedures.*.supplies`, `integer`, `exists:warehouses,id` |
| `procedures.*.supplies.*.generic_product_id` | condicional | `required_with:procedures.*.supplies`, `integer`, `exists:product_generics,id` |
| `procedures.*.supplies.*.quantity` | condicional | `required_with:procedures.*.supplies`, `numeric`, `min:0.0001` |

**Campos de respuesta (`ServiceOrderResource`):** `order_number`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `service_date`, `order_status`, `billing_status`, `total_amount`, `total_discount`, `net_total`, `created_by_user_id`, `procedures`

---

## `GET /api/v1/service-orders/discounts`

Listar órdenes con descuentos pendientes de aprobación.

**Permiso requerido:** `ordenes_servicio.aprobar`

**Campos de respuesta (`PatientProcedureRecordResource`):** `id`, `medical_service_id`, `medical_service_name`, `movement_document_id`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `quantity`, `unit_price`, `total`, `discount_type`, `discount_value`, `discount_amount`, `net_total`, `discount_status`, `order_number`, `created_by_user_id`, `approved_by_user_id`, `approved_at`, `billing_status`, `billed_by_user_id`, `billed_at`, `service_date`, `seller`, `referrer`, `is_active`

---

## `GET /api/v1/service-orders/{orderNumber}`

Obtener una orden de servicio por su número.

**Permiso requerido:** `ordenes_servicio.ver`

**Parámetros de ruta:** `orderNumber`

**Campos de respuesta (`ServiceOrderResource`):** `order_number`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `service_date`, `order_status`, `billing_status`, `total_amount`, `total_discount`, `net_total`, `created_by_user_id`, `procedures`

---

## `POST /api/v1/service-orders/{orderNumber}/approve`

Aprobar el descuento de una orden.

**Permiso requerido:** `ordenes_servicio.aprobar`

**Parámetros de ruta:** `orderNumber`

**Campos de respuesta (`ServiceOrderResource`):** `order_number`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `service_date`, `order_status`, `billing_status`, `total_amount`, `total_discount`, `net_total`, `created_by_user_id`, `procedures`

---

## `POST /api/v1/service-orders/{orderNumber}/bill`

Marcar una orden como facturada.

**Permiso requerido:** `ordenes_servicio.ver`

**Parámetros de ruta:** `orderNumber`

**Campos de respuesta (`ServiceOrderResource`):** `order_number`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `service_date`, `order_status`, `billing_status`, `total_amount`, `total_discount`, `net_total`, `created_by_user_id`, `procedures`

---

## `POST /api/v1/service-orders/{orderNumber}/cancel`

Anular una orden de servicio.

**Permiso requerido:** `ordenes_servicio.ver`

**Parámetros de ruta:** `orderNumber`

**Campos de respuesta (`ServiceOrderResource`):** `order_number`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `service_date`, `order_status`, `billing_status`, `total_amount`, `total_discount`, `net_total`, `created_by_user_id`, `procedures`

---

## `GET /api/v1/procedures/{id}/current-price`

Precio vigente de un procedimiento.

**Permiso requerido:** `ordenes_servicio.ver`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/price-lists/template`

Descargar plantilla de precios (Excel).

**Permiso requerido:** `listas_precios.ver`

**Tipo de respuesta:** descarga de archivo

---

## `POST /api/v1/price-lists/import`

Importar lista de precios desde Excel.

**Permiso requerido:** `listas_precios.crear`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:xlsx,xls` |

---
