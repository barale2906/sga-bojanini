# 11 · Notificaciones y reportes

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **14**

## `GET /api/v1/notifications`

**Permiso requerido:** `notificaciones.ver`

**Query params detectados:** `per_page`, `unread_only`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

---

## `GET /api/v1/notifications/unread-count`

**Permiso requerido:** `notificaciones.ver`

---

## `PUT /api/v1/notifications/{id}/read`

**Permiso requerido:** `notificaciones.ver`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/notifications/read-all`

**Permiso requerido:** `notificaciones.ver`

---

## `GET /api/v1/reports/inventory`

**Permiso requerido:** `reportes.ver`

**Query params:**

| Campo | Requerido | Reglas |
|---|---|---|
| `format` | no | `in:pdf,excel,csv` (por defecto `pdf`) |
| `warehouse_id` | no | integer |
| `category_id` | no | integer |

**Respuesta:** archivo binario (PDF/Excel/CSV) si el reporte es pequeño. Si supera el umbral de filas (por defecto 1000) responde **HTTP 202** con JSON `{ "data": { "mode": "async", "export_id": 12, "status": "queued" } }`; entonces consultar `GET /reports/exports/{export_id}` hasta `status = ready` y descargar con `GET /reports/exports/{export_id}/download`.

---

## `GET /api/v1/reports/movements`

**Permiso requerido:** `reportes.ver`

**Query params:**

| Campo | Requerido | Reglas |
|---|---|---|
| `format` | no | `in:pdf,excel,csv` (por defecto `pdf`) |
| `date_from` | no | date |
| `date_to` | no | date, >= date_from |
| `type` | no | string (movement_type) |

**Respuesta:** archivo binario (PDF/Excel/CSV) si el reporte es pequeño. Si supera el umbral de filas (por defecto 1000) responde **HTTP 202** con JSON `{ "data": { "mode": "async", "export_id": 12, "status": "queued" } }`; entonces consultar `GET /reports/exports/{export_id}` hasta `status = ready` y descargar con `GET /reports/exports/{export_id}/download`.

---

## `GET /api/v1/reports/expiring`

**Permiso requerido:** `reportes.ver`

**Query params:**

| Campo | Requerido | Reglas |
|---|---|---|
| `format` | no | `in:pdf,excel,csv` (por defecto `pdf`) |
| `days` | no | integer 1–365 |
| `warehouse_id` | no | integer |

**Respuesta:** archivo binario (PDF/Excel/CSV) si el reporte es pequeño. Si supera el umbral de filas (por defecto 1000) responde **HTTP 202** con JSON `{ "data": { "mode": "async", "export_id": 12, "status": "queued" } }`; entonces consultar `GET /reports/exports/{export_id}` hasta `status = ready` y descargar con `GET /reports/exports/{export_id}/download`.

---

## `GET /api/v1/reports/purchases`

**Permiso requerido:** `reportes.ver`

**Query params:**

| Campo | Requerido | Reglas |
|---|---|---|
| `format` | no | `in:pdf,excel,csv` (por defecto `pdf`) |
| `status` | no | string (estado de la orden) |
| `date_from` | no | date |
| `date_to` | no | date, >= date_from |

**Respuesta:** archivo binario (PDF/Excel/CSV) si el reporte es pequeño. Si supera el umbral de filas (por defecto 1000) responde **HTTP 202** con JSON `{ "data": { "mode": "async", "export_id": 12, "status": "queued" } }`; entonces consultar `GET /reports/exports/{export_id}` hasta `status = ready` y descargar con `GET /reports/exports/{export_id}/download`.

---

## `GET /api/v1/reports/consumption`

**Permiso requerido:** `reportes.ver`

**Query params:**

| Campo | Requerido | Reglas |
|---|---|---|
| `format` | no | `in:pdf,excel,csv` (por defecto `pdf`) |
| `date_from` | no | date |
| `date_to` | no | date, >= date_from |

**Respuesta:** archivo binario (PDF/Excel/CSV) si el reporte es pequeño. Si supera el umbral de filas (por defecto 1000) responde **HTTP 202** con JSON `{ "data": { "mode": "async", "export_id": 12, "status": "queued" } }`; entonces consultar `GET /reports/exports/{export_id}` hasta `status = ready` y descargar con `GET /reports/exports/{export_id}/download`.

---

## `GET /api/v1/reports/conditions`

**Permiso requerido:** `reportes.ver`

**Query params:**

| Campo | Requerido | Reglas |
|---|---|---|
| `format` | no | `in:pdf,excel,csv` (por defecto `pdf`) |
| `sensor_id` | condicional | integer — **obligatorio si format=pdf** |
| `date_from` | no | date |
| `date_to` | no | date, >= date_from |

**Respuesta:** archivo binario (PDF/Excel/CSV) si el reporte es pequeño. Si supera el umbral de filas (por defecto 1000) responde **HTTP 202** con JSON `{ "data": { "mode": "async", "export_id": 12, "status": "queued" } }`; entonces consultar `GET /reports/exports/{export_id}` hasta `status = ready` y descargar con `GET /reports/exports/{export_id}/download`.

---

## `GET /api/v1/reports/audit`

**Permiso requerido:** `reportes.ver`

**Query params:**

| Campo | Requerido | Reglas |
|---|---|---|
| `format` | no | `in:pdf,excel,csv` (por defecto `pdf`) |
| `user_id` | no | integer |
| `action` | no | string |
| `date_from` | no | date |
| `date_to` | no | date, >= date_from |

**Respuesta:** archivo binario (PDF/Excel/CSV) si el reporte es pequeño. Si supera el umbral de filas (por defecto 1000) responde **HTTP 202** con JSON `{ "data": { "mode": "async", "export_id": 12, "status": "queued" } }`; entonces consultar `GET /reports/exports/{export_id}` hasta `status = ready` y descargar con `GET /reports/exports/{export_id}/download`.

---

## `GET /api/v1/reports/exports`

**Permiso requerido:** `reportes.ver`

**Query params detectados:** `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

---

## `GET /api/v1/reports/exports/{id}`

**Permiso requerido:** `reportes.ver`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/reports/exports/{id}/download`

**Permiso requerido:** `reportes.ver`

**Parámetros de ruta:** `id`

**Tipo de respuesta:** descarga de archivo

---
