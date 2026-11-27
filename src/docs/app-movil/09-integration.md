# 09 · Integraciones y consumos

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **12**

## `GET /api/v1/integrations`

**Permiso requerido:** `integraciones.ver`

**Campos de respuesta (`ExternalIntegrationResource`):** `id`, `name`, `type`, `base_url`, `auth_type`, `is_active`, `last_sync_at`, `created_at`, `updated_at`

---

## `POST /api/v1/integrations`

**Permiso requerido:** `integraciones.configurar`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `type` | sí | `in:scheduling,clinical_records` |
| `base_url` | sí | `url`, `max:500` |
| `auth_type` | sí | `in:bearer,api_key,oauth2,basic` |
| `credentials` | sí | `array` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`ExternalIntegrationResource`):** `id`, `name`, `type`, `base_url`, `auth_type`, `is_active`, `last_sync_at`, `created_at`, `updated_at`

---

## `GET /api/v1/integrations/{id}`

**Permiso requerido:** `integraciones.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ExternalIntegrationResource`):** `id`, `name`, `type`, `base_url`, `auth_type`, `is_active`, `last_sync_at`, `created_at`, `updated_at`

---

## `PUT /api/v1/integrations/{id}`

**Permiso requerido:** `integraciones.configurar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `type` | sí | `in:scheduling,clinical_records` |
| `base_url` | sí | `url`, `max:500` |
| `auth_type` | sí | `in:bearer,api_key,oauth2,basic` |
| `credentials` | sí | `array` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`ExternalIntegrationResource`):** `id`, `name`, `type`, `base_url`, `auth_type`, `is_active`, `last_sync_at`, `created_at`, `updated_at`

---

## `DELETE /api/v1/integrations/{id}`

**Permiso requerido:** `integraciones.configurar`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/integrations/{id}/test`

**Permiso requerido:** `integraciones.configurar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/scheduling/appointments`

**Permiso requerido:** `integraciones.ver`

**Query params detectados:** `date_from`, `date_to`

---

## `GET /api/v1/scheduling/demand-analysis`

**Permiso requerido:** `integraciones.ver`

**Query params detectados:** `days_ahead`

---

## `GET /api/v1/consumptions`

**Permiso requerido:** `consumos.ver`

**Query params detectados:** `sync_status`, `appointment_id`, `patient_identifier`, `date_from`, `date_to`, `page`, `per_page`

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`ConsumptionRecordResource`):** `id`, `appointment_id`, `patient_identifier`, `service_type`, `product_id`, `product_code`, `product_name`, `batch_id`, `lot_number`, `quantity`, `user_id`, `consumed_at`, `sync_status`, `synced_to_hc_at`

---

## `POST /api/v1/consumptions`

**Permiso requerido:** `consumos.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `appointment_id` | no | `string`, `max:100`, `nullable` |
| `patient_identifier` | no | `string`, `max:100`, `nullable` |
| `service_type` | no | `string`, `max:255`, `nullable` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `cost_center_id` | sí | `integer`, `exists:cost_centers,id` |
| `service_id` | no | `integer`, `exists:medical_services,id`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.generic_product_id` | sí | `integer`, `exists:product_generics,id` |
| `items.*.quantity` | sí | `integer`, `min:1` |

**Campos de respuesta (`ConsumptionRecordResource`):** `id`, `appointment_id`, `patient_identifier`, `service_type`, `product_id`, `product_code`, `product_name`, `batch_id`, `lot_number`, `quantity`, `user_id`, `consumed_at`, `sync_status`, `synced_to_hc_at`

---

## `GET /api/v1/consumptions/{id}`

**Permiso requerido:** `consumos.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ConsumptionRecordResource`):** `id`, `appointment_id`, `patient_identifier`, `service_type`, `product_id`, `product_code`, `product_name`, `batch_id`, `lot_number`, `quantity`, `user_id`, `consumed_at`, `sync_status`, `synced_to_hc_at`

---

## `POST /api/v1/consumptions/{id}/retry-sync`

**Permiso requerido:** `consumos.crear`

**Parámetros de ruta:** `id`

---
