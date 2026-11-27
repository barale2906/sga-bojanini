# 07 · Monitoreo (sensores, lecturas, calibración)

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **23**

## `GET /api/v1/sensors`

**Permiso requerido:** `sensores.ver`

**Query params detectados:** `zone_id`, `type`, `is_active`

---

## `GET /api/v1/sensors/{id}`

**Permiso requerido:** `sensores.ver`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/sensors`

Crea el sensor. Si llega `certificate_file` (multipart) se registra
en la misma transacción como su certificado de calibración vigente.

**Permiso requerido:** `sensores.crear`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `zone_id` | sí | `integer`, `exists:zones,id` |
| `code` | no | `string`, `max:50`, `unique:sensors,code`, `nullable` |
| `name` | sí | `string`, `max:255` |
| `type` | sí | `in:temperature,humidity` |
| `unit` | sí | `string`, `max:10` |
| `certificate_file` | condicional | `required_with:certificate_expires_at`, `file`, `mimes:pdf,jpg,jpeg,png`, `max:10240`, `nullable` |
| `certificate_expires_at` | condicional | `required_with:certificate_file`, `date`, `after_or_equal:certificate_calibrated_at`, `nullable` |
| `certificate_calibrated_at` | no | `date`, `before_or_equal:today`, `nullable` |
| `certificate_certificate_number` | no | `string`, `max:100`, `nullable` |
| `certificate_laboratory` | no | `string`, `max:150`, `nullable` |
| `certificate_notes` | no | `string`, `max:1000`, `nullable` |

---

## `PUT /api/v1/sensors/{id}`

**Permiso requerido:** `sensores.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `zone_id` | sí | `integer`, `exists:zones,id` |
| `code` | no | `string`, `max:50`, `unique`, `nullable` |
| `name` | sí | `string`, `max:255` |
| `type` | sí | `in:temperature,humidity` |
| `unit` | sí | `string`, `max:10` |
| `is_active` | no | `boolean` |

---

## `DELETE /api/v1/sensors/{id}`

**Permiso requerido:** `sensores.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/sensors/{id}/users`

Lista los usuarios con acceso explícito a un sensor.

**Permiso requerido:** `sensores.asignar`

**Parámetros de ruta:** `id`

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `GET /api/v1/sensors/{sensorId}/calibrations`

**Permiso requerido:** `sensores.ver`

**Parámetros de ruta:** `sensorId`

**Campos de respuesta (`CalibrationCertificateResource`):** `id`, `sensor_id`, `certificate_number`, `laboratory`, `calibrated_at`, `expires_at`, `days_left`, `is_current`, `status`, `status_label`, `replaced_at`, `original_name`, `mime_type`, `size`, `notes`, `uploaded_by`, `created_at`

---

## `POST /api/v1/sensors/{sensorId}/calibrations`

**Permiso requerido:** `sensores.editar`

**Parámetros de ruta:** `sensorId`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:pdf,jpg,jpeg,png`, `max:10240` |
| `expires_at` | sí | `date`, `after_or_equal:calibrated_at` |
| `calibrated_at` | no | `date`, `before_or_equal:today`, `nullable` |
| `certificate_number` | no | `string`, `max:100`, `nullable` |
| `laboratory` | no | `string`, `max:150`, `nullable` |
| `notes` | no | `string`, `max:1000`, `nullable` |

**Campos de respuesta (`CalibrationCertificateResource`):** `id`, `sensor_id`, `certificate_number`, `laboratory`, `calibrated_at`, `expires_at`, `days_left`, `is_current`, `status`, `status_label`, `replaced_at`, `original_name`, `mime_type`, `size`, `notes`, `uploaded_by`, `created_at`

---

## `GET /api/v1/calibrations/{id}/download`

**Permiso requerido:** `sensores.ver`

**Parámetros de ruta:** `id`

**Tipo de respuesta:** descarga de archivo

---

## `DELETE /api/v1/calibrations/{id}`

**Permiso requerido:** `sensores.editar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/sensors/{sensorId}/readings/latest`

Último registro del equipo (fecha/hora de la lectura y quién la hizo).
`data` es null si el equipo aún no tiene lecturas.

**Permiso requerido:** `lecturas.ver`

**Parámetros de ruta:** `sensorId`

---

## `GET /api/v1/sensors/{sensorId}/readings/export`

Descarga en Excel las lecturas del periodo con el resumen estadístico.

**Permiso requerido:** `lecturas.ver`

**Parámetros de ruta:** `sensorId`

**Query params** (validados):

| Campo | Requerido | Reglas |
|---|---|---|
| `date_from` | sí | `date_format:Y-m-d` |
| `date_to` | sí | `date_format:Y-m-d`, `after_or_equal:date_from` |

**Tipo de respuesta:** descarga de archivo

---

## `POST /api/v1/sensors/{sensorId}/readings`

Registra una lectura manual.

**Permiso requerido:** `lecturas.crear`

**Parámetros de ruta:** `sensorId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `value` | sí | `numeric` |
| `reading_source` | sí | `in:manual,iot` |
| `recorded_at` | sí | `date` |

**Campos de respuesta (`ReadingResource`):** `id`, `sensor_id`, `value`, `reading_source`, `recorded_at`, `user_id`

---

## `GET /api/v1/sensors/{sensorId}/readings`

Lista las lecturas de un sensor en un periodo.

**Permiso requerido:** `lecturas.ver`

**Parámetros de ruta:** `sensorId`

**Query params detectados:** `date_from`, `date_to`

**Campos de respuesta (`ReadingResource`):** `id`, `sensor_id`, `value`, `reading_source`, `recorded_at`, `user_id`

---

## `GET /api/v1/sensors/{sensorId}/statistics`

Retorna el gráfico de control estadístico.

**Permiso requerido:** `lecturas.ver`

**Parámetros de ruta:** `sensorId`

**Query params detectados:** `date_from`, `date_to`

---

## `GET /api/v1/sensors/{sensorId}/trend`

Retorna análisis de tendencia del sensor.

**Permiso requerido:** `lecturas.ver`

**Parámetros de ruta:** `sensorId`

**Query params detectados:** `date_from`, `date_to`

---

## `GET /api/v1/sensors/{sensorId}/alert-rules`

**Permiso requerido:** `reglas_alerta.ver`

**Parámetros de ruta:** `sensorId`

**Campos de respuesta (`AlertRuleResource`):** `id`, `sensor_id`, `condition_type`, `threshold`, `consecutive_readings`, `notification_channels`, `is_active`

---

## `POST /api/v1/sensors/{sensorId}/alert-rules`

**Permiso requerido:** `reglas_alerta.crear`

**Parámetros de ruta:** `sensorId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `condition_type` | sí | `in:above,below,trend_up,trend_down,out_of_range` |
| `threshold` | no | `numeric`, `nullable` |
| `consecutive_readings` | no | `integer`, `min:1`, `max:100` |
| `notification_channels` | sí | `array`, `min:1` |
| `notification_channels.*` | no | `in:internal,email,push` |

**Campos de respuesta (`AlertRuleResource`):** `id`, `sensor_id`, `condition_type`, `threshold`, `consecutive_readings`, `notification_channels`, `is_active`

---

## `PUT /api/v1/alert-rules/{id}`

**Permiso requerido:** `reglas_alerta.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `condition_type` | no | `in:above,below,trend_up,trend_down,out_of_range` |
| `threshold` | no | `numeric`, `nullable` |
| `consecutive_readings` | no | `integer`, `min:1`, `max:100` |
| `notification_channels` | no | `array`, `min:1` |
| `notification_channels.*` | no | `in:internal,email,push` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`AlertRuleResource`):** `id`, `sensor_id`, `condition_type`, `threshold`, `consecutive_readings`, `notification_channels`, `is_active`

---

## `DELETE /api/v1/alert-rules/{id}`

**Permiso requerido:** `reglas_alerta.eliminar`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/monitoring/reports/generate`

**Permiso requerido:** `lecturas.ver`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `sensor_id` | sí | `integer`, `exists:sensors,id` |
| `date_from` | sí | `date` |
| `date_to` | sí | `date`, `after_or_equal:date_from` |

---

## `GET /api/v1/monitoring/reports`

**Permiso requerido:** `lecturas.ver`

---

## `POST /api/v1/sensors/readings/bulk`

Registra lecturas masivas desde dispositivo IoT.

**Auth:** header `X-IoT-Token` (NO usa Bearer; solo dispositivos IoT)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `readings` | sí | `array`, `min:1`, `max:500` |
| `readings.*.sensor_code` | sí | `string`, `max:50` |
| `readings.*.value` | sí | `numeric` |
| `readings.*.recorded_at` | sí | `date` |

---
