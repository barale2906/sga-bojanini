# 03 · Almacenes, zonas y ubicaciones

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **21**

## `GET /api/v1/warehouses`

**Permiso requerido:** `almacenes.ver`

**Query params detectados:** `search`, `is_active`

**Campos de respuesta (`WarehouseResource`):** `id`, `name`, `code`, `address`, `description`, `is_active`

---

## `GET /api/v1/warehouses/{id}`

**Permiso requerido:** `almacenes.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`WarehouseResource`):** `id`, `name`, `code`, `address`, `description`, `is_active`

---

## `POST /api/v1/warehouses`

**Permiso requerido:** `almacenes.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `code` | no | `string`, `max:50`, `unique:warehouses,code`, `nullable` |
| `address` | no | `string`, `nullable` |
| `description` | no | `string`, `nullable` |
| `zones` | no | `array`, `nullable` |
| `zones.*.name` | sí | `string`, `max:255` |
| `zones.*.code` | no | `string`, `max:50`, `nullable` |
| `zones.*.type` | sí | `in:ambient,cold,frozen,controlled` |
| `zones.*.temp_min` | no | `numeric`, `nullable` |
| `zones.*.temp_max` | no | `numeric`, `nullable` |
| `zones.*.humidity_min` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `zones.*.humidity_max` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `zones.*.description` | no | `string`, `nullable` |
| `zones.*.locations` | no | `array`, `nullable` |
| `zones.*.locations.*.name` | sí | `string`, `max:255` |
| `zones.*.locations.*.code` | no | `string`, `max:50`, `nullable` |
| `zones.*.locations.*.volume_cm3` | no | `numeric`, `min:0`, `nullable` |
| `zones.*.locations.*.max_weight_kg` | no | `numeric`, `min:0`, `nullable` |
| `zones.*.locations.*.description` | no | `string`, `nullable` |

**Campos de respuesta (`WarehouseResource`):** `id`, `name`, `code`, `address`, `description`, `is_active`

**Campos de respuesta (`WarehouseWithZonesResource`):** `id`, `name`, `code`, `address`, `description`, `is_active`, `zones`

---

## `PUT /api/v1/warehouses/{id}`

**Permiso requerido:** `almacenes.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `code` | no | `string`, `max:50`, `unique:warehouses,code`, `nullable` |
| `address` | no | `string`, `nullable` |
| `description` | no | `string`, `nullable` |
| `zones` | no | `array`, `nullable` |
| `zones.*.id` | no | `integer`, `exists:zones,id`, `nullable` |
| `zones.*.name` | sí | `string`, `max:255` |
| `zones.*.code` | no | `string`, `max:50`, `nullable` |
| `zones.*.type` | sí | `in:ambient,cold,frozen,controlled` |
| `zones.*.temp_min` | no | `numeric`, `nullable` |
| `zones.*.temp_max` | no | `numeric`, `nullable` |
| `zones.*.humidity_min` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `zones.*.humidity_max` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `zones.*.description` | no | `string`, `nullable` |
| `zones.*.locations` | no | `array`, `nullable` |
| `zones.*.locations.*.id` | no | `integer`, `exists:locations,id`, `nullable` |
| `zones.*.locations.*.name` | sí | `string`, `max:255` |
| `zones.*.locations.*.code` | no | `string`, `max:50`, `nullable` |
| `zones.*.locations.*.volume_cm3` | no | `numeric`, `min:0`, `nullable` |
| `zones.*.locations.*.max_weight_kg` | no | `numeric`, `min:0`, `nullable` |
| `zones.*.locations.*.description` | no | `string`, `nullable` |

**Campos de respuesta (`WarehouseResource`):** `id`, `name`, `code`, `address`, `description`, `is_active`

**Campos de respuesta (`WarehouseWithZonesResource`):** `id`, `name`, `code`, `address`, `description`, `is_active`, `zones`

---

## `DELETE /api/v1/warehouses/{id}`

**Permiso requerido:** `almacenes.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/warehouses/{id}/zones`

**Permiso requerido:** `zonas.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ZoneResource`):** `id`, `warehouse_id`, `name`, `code`, `type`, `temp_min`, `temp_max`, `humidity_min`, `humidity_max`, `description`, `is_active`

---

## `GET /api/v1/warehouses/{id}/locations`

**Permiso requerido:** `ubicaciones.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`LocationResource`):** `id`, `zone_id`, `name`, `code`, `volume_cm3`, `max_weight_kg`, `description`, `is_active`

---

## `GET /api/v1/warehouses/{id}/users`

Lista los usuarios con acceso explícito a un almacén.

**Permiso requerido:** `almacenes.asignar`

**Parámetros de ruta:** `id`

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `GET /api/v1/warehouses/{id}/capacity`

**Permiso requerido:** `almacenes.ver`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/zones`

**Permiso requerido:** `zonas.ver`

**Query params detectados:** `warehouse_id`, `is_active`, `type`, `search`

**Campos de respuesta (`ZoneResource`):** `id`, `warehouse_id`, `name`, `code`, `type`, `temp_min`, `temp_max`, `humidity_min`, `humidity_max`, `description`, `is_active`

---

## `GET /api/v1/zones/{id}`

**Permiso requerido:** `zonas.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ZoneResource`):** `id`, `warehouse_id`, `name`, `code`, `type`, `temp_min`, `temp_max`, `humidity_min`, `humidity_max`, `description`, `is_active`

---

## `POST /api/v1/zones`

**Permiso requerido:** `zonas.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `name` | sí | `string`, `max:255` |
| `code` | sí | `string`, `max:50` |
| `type` | sí | `in:ambient,cold,frozen,controlled` |
| `temp_min` | no | `numeric`, `nullable` |
| `temp_max` | no | `numeric`, `gte:temp_min`, `nullable` |
| `humidity_min` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `humidity_max` | no | `numeric`, `gte:humidity_min`, `max:100`, `nullable` |
| `description` | no | `string`, `nullable` |

**Campos de respuesta (`ZoneResource`):** `id`, `warehouse_id`, `name`, `code`, `type`, `temp_min`, `temp_max`, `humidity_min`, `humidity_max`, `description`, `is_active`

---

## `PUT /api/v1/zones/{id}`

**Permiso requerido:** `zonas.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `name` | sí | `string`, `max:255` |
| `code` | sí | `string`, `max:50` |
| `type` | sí | `in:ambient,cold,frozen,controlled` |
| `temp_min` | no | `numeric`, `nullable` |
| `temp_max` | no | `numeric`, `gte:temp_min`, `nullable` |
| `humidity_min` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `humidity_max` | no | `numeric`, `gte:humidity_min`, `max:100`, `nullable` |
| `description` | no | `string`, `nullable` |

**Campos de respuesta (`ZoneResource`):** `id`, `warehouse_id`, `name`, `code`, `type`, `temp_min`, `temp_max`, `humidity_min`, `humidity_max`, `description`, `is_active`

---

## `DELETE /api/v1/zones/{id}`

**Permiso requerido:** `zonas.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/zones/{id}/capacity`

**Permiso requerido:** `zonas.ver`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/locations`

**Permiso requerido:** `ubicaciones.ver`

**Query params detectados:** `zone_id`, `is_active`, `search`

**Campos de respuesta (`LocationResource`):** `id`, `zone_id`, `name`, `code`, `volume_cm3`, `max_weight_kg`, `description`, `is_active`

---

## `GET /api/v1/locations/{id}`

**Permiso requerido:** `ubicaciones.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`LocationResource`):** `id`, `zone_id`, `name`, `code`, `volume_cm3`, `max_weight_kg`, `description`, `is_active`

---

## `POST /api/v1/locations`

**Permiso requerido:** `ubicaciones.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `zone_id` | sí | `integer`, `exists:zones,id` |
| `name` | sí | `string`, `max:255` |
| `code` | sí | `string`, `max:50` |
| `volume_cm3` | no | `numeric`, `min:0`, `nullable` |
| `max_weight_kg` | no | `numeric`, `min:0`, `nullable` |
| `description` | no | `string`, `nullable` |

**Campos de respuesta (`LocationResource`):** `id`, `zone_id`, `name`, `code`, `volume_cm3`, `max_weight_kg`, `description`, `is_active`

---

## `PUT /api/v1/locations/{id}`

**Permiso requerido:** `ubicaciones.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `zone_id` | sí | `integer`, `exists:zones,id` |
| `name` | sí | `string`, `max:255` |
| `code` | sí | `string`, `max:50` |
| `volume_cm3` | no | `numeric`, `min:0`, `nullable` |
| `max_weight_kg` | no | `numeric`, `min:0`, `nullable` |
| `description` | no | `string`, `nullable` |

**Campos de respuesta (`LocationResource`):** `id`, `zone_id`, `name`, `code`, `volume_cm3`, `max_weight_kg`, `description`, `is_active`

---

## `DELETE /api/v1/locations/{id}`

**Permiso requerido:** `ubicaciones.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/locations/{id}/capacity`

**Permiso requerido:** `ubicaciones.ver`

**Parámetros de ruta:** `id`

---
