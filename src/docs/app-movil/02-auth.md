# 02 · Autenticación, usuarios y roles

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **25**

## `POST /api/v1/auth/login`

**Auth:** pública (no enviar token)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `email` | sí | `email` |
| `password` | sí | `string` |
| `device_name` | no | `string`, `max:100`, `nullable` |

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `POST /api/v1/auth/logout`

**Permiso requerido:** solo estar autenticado

---

## `GET /api/v1/auth/me`

**Permiso requerido:** solo estar autenticado

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `GET /api/v1/auth/menu`

Devuelve el menú de navegación del usuario autenticado.
Solo incluye secciones e ítems para los que tiene permiso de lectura.
El campo `actions` de cada ítem indica qué operaciones puede ejecutar.

**Permiso requerido:** solo estar autenticado

---

## `PUT /api/v1/auth/password`

**Permiso requerido:** solo estar autenticado

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `current_password` | sí | `string`, `current_password` |
| `new_password` | sí | `string`, `min:8`, `confirmed` |

---

## `POST /api/v1/auth/refresh`

El token anterior no se borra de inmediato: queda vigente un periodo de
gracia corto para que las peticiones en vuelo y las demás pestañas del
navegador (que comparten el token en localStorage) no reciban 401 y
expulsen al usuario al login. Si ya tenía un vencimiento más cercano
(p. ej. un segundo refresh con el mismo token), se conserva ese.

**Permiso requerido:** solo estar autenticado

---

## `GET /api/v1/auth/me/signature`

`data.signature` es null si aún no la ha registrado.

**Permiso requerido:** solo estar autenticado

**Campos de respuesta (`MySignatureResource`):** `signer_name`, `document_type`, `document_type_label`, `document_number`, `signature_data`, `has_pin`, `pin_generated_at`, `registered_at`

---

## `PUT /api/v1/auth/me/signature`

En el primer registro la respuesta incluye `data.pin` (único momento
en que se muestra). En actualizaciones se conserva el PIN vigente y
`data.pin` es null.

**Permiso requerido:** solo estar autenticado · **Rate limit:** `throttle:10,1` (máx. peticiones, minutos)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `current_password` | sí | `string`, `current_password` |
| `document_type` | sí | `string`, `in:CC,CE,TI,PA,PPT` |
| `document_number` | sí | `string`, `min:4`, `max:20`, `regex:/^[A-Za-z0-9]+$/` |
| `signature` | sí | `string`, `max:700000`, `regex:/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/` |

**Campos de respuesta (`MySignatureResource`):** `signer_name`, `document_type`, `document_type_label`, `document_number`, `signature_data`, `has_pin`, `pin_generated_at`, `registered_at`

---

## `POST /api/v1/auth/me/signature/pin`

El PIN anterior deja de funcionar de inmediato.

**Permiso requerido:** solo estar autenticado · **Rate limit:** `throttle:5,1` (máx. peticiones, minutos)

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `current_password` | sí | `string`, `current_password` |

**Campos de respuesta (`MySignatureResource`):** `signer_name`, `document_type`, `document_type_label`, `document_number`, `signature_data`, `has_pin`, `pin_generated_at`, `registered_at`

---

## `POST /api/v1/auth/devices`

Registra (o actualiza) el token push FCM del dispositivo del usuario autenticado. Si el token ya pertenecía a otro usuario (teléfono compartido) se reasigna al actual. Limitado a 30 por minuto.

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `token` | sí | `string`, `max:255` |
| `platform` | sí | `android` o `ios` |
| `device_name` | no | `string`, `max:100` |

**Respuesta:** `200`, `data: null`. **Errores:** `401`, `422`.

---

## `DELETE /api/v1/auth/devices/{token}`

Desvincula el token push del usuario autenticado (llamar antes de cerrar sesión). Solo borra tokens propios: si no existe o es de otro usuario responde `404`.

---

## `GET /api/v1/users`

**Permiso requerido:** `usuarios.ver`

**Query params detectados:** `search`, `is_active`, `role`, `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `POST /api/v1/users`

**Permiso requerido:** `usuarios.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `email` | sí | `email`, `max:255`, `unique:users,email` |
| `password` | sí | `string`, `min:8` |
| `phone` | no | `string`, `max:20`, `nullable` |
| `is_active` | no | `boolean`, `nullable` |
| `role_ids` | no | `array`, `nullable` |
| `role_ids.*` | no | `integer`, `exists:roles,id` |

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `GET /api/v1/users/{id}`

**Permiso requerido:** `usuarios.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `PUT /api/v1/users/{id}`

**Permiso requerido:** `usuarios.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `email` | sí | `email`, `max:255`, `unique:users,email` |
| `password` | no | `string`, `min:8`, `nullable` |
| `phone` | no | `string`, `max:20`, `nullable` |
| `is_active` | no | `boolean`, `nullable` |
| `role_ids` | no | `array`, `nullable` |
| `role_ids.*` | no | `integer`, `exists:roles,id` |

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `DELETE /api/v1/users/{id}`

**Permiso requerido:** `usuarios.eliminar`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/users/{id}/roles`

**Permiso requerido:** `roles.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `role_ids` | sí | `array` |
| `role_ids.*` | no | `integer`, `exists:roles,id` |

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `GET /api/v1/users/{id}/warehouses`

Lista los almacenes asignados a un usuario.

**Permiso requerido:** `almacenes.asignar`

**Parámetros de ruta:** `id`

**Campos de respuesta (`WarehouseResource`):** `id`, `name`, `code`, `address`, `description`, `is_active`

---

## `PUT /api/v1/users/{id}/warehouses`

Reemplaza el conjunto completo de almacenes asignados a un usuario.

**Permiso requerido:** `almacenes.asignar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `warehouse_ids` | no | `present`, `array` |
| `warehouse_ids.*` | no | `integer`, `exists:warehouses,id` |

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `GET /api/v1/users/{id}/sensors`

Lista los sensores (equipos de monitoreo) asignados a un usuario.

**Permiso requerido:** `sensores.asignar`

**Parámetros de ruta:** `id`

---

## `PUT /api/v1/users/{id}/sensors`

Reemplaza el conjunto completo de sensores (equipos de monitoreo)
asignados a un usuario.

**Permiso requerido:** `sensores.asignar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `sensor_ids` | no | `present`, `array` |
| `sensor_ids.*` | no | `integer`, `exists:sensors,id` |

**Campos de respuesta (`UserResource`):** `id`, `name`, `email`, `phone`, `is_active`, `roles`, `permissions`, `warehouses`, `sensors`, `created_at`

---

## `GET /api/v1/roles`

**Permiso requerido:** `roles.ver`

**Campos de respuesta (`RoleResource`):** `id`, `name`, `permissions`

---

## `POST /api/v1/roles`

**Permiso requerido:** `roles.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:100`, `unique:roles,name` |
| `permission_ids` | no | `array`, `nullable` |
| `permission_ids.*` | no | `integer`, `exists:permissions,id` |

**Campos de respuesta (`RoleResource`):** `id`, `name`, `permissions`

---

## `GET /api/v1/roles/{id}`

**Permiso requerido:** `roles.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`RoleResource`):** `id`, `name`, `permissions`

---

## `PUT /api/v1/roles/{id}`

**Permiso requerido:** `roles.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:100`, `unique` |
| `permission_ids` | no | `array`, `nullable` |
| `permission_ids.*` | no | `integer`, `exists:permissions,id` |

**Campos de respuesta (`RoleResource`):** `id`, `name`, `permissions`

---

## `DELETE /api/v1/roles/{id}`

**Permiso requerido:** `roles.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/permissions`

**Permiso requerido:** `roles.ver`

---
