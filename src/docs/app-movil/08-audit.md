# 08 · Auditoría

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **4**

## `GET /api/v1/audit-logs/summary`

**Permiso requerido:** `auditoria.ver`

**Query params detectados:** `date_from`, `date_to`

---

## `GET /api/v1/audit-logs/export`

**Permiso requerido:** `auditoria.exportar`

---

## `GET /api/v1/audit-logs/{id}`

**Permiso requerido:** `auditoria.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`AuditLogResource`):** `id`, `user_id`, `user_name`, `action`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`

---

## `GET /api/v1/audit-logs`

**Permiso requerido:** `auditoria.ver`

**Query params detectados:** `user_id`, `action`, `auditable_type`, `date_from`, `date_to`, `page`, `per_page`

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`AuditLogResource`):** `id`, `user_id`, `user_name`, `action`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`

---
