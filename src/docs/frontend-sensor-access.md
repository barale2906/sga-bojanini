# Guía Frontend — Asignación de sensores (equipos de monitoreo) a usuarios

## Contexto

Cada usuario (excepto el rol **super_administrador**, que siempre tiene acceso a
**todos** los sensores) solo puede **ver y operar** los sensores (equipos de
monitoreo) que le hayan sido asignados explícitamente. Esto aplica a:

- Sensores: listado, detalle, edición y eliminación (`GET/PUT/DELETE /sensors/{id}`).
- Lecturas de sensores: registro manual, listado, estadísticas y tendencia
  (`/sensors/{sensorId}/readings`, `/statistics`, `/trend`).
- Reglas de alerta: listado y creación por sensor, edición y eliminación de una
  regla puntual (se resuelve el sensor de la regla internamente).
- Generación de reportes de condiciones (`POST /monitoring/reports/generate`),
  validado contra el `sensor_id` indicado en el body.

Cuando un usuario no tiene acceso a un sensor:
- Los **listados** (`GET /sensors`) simplemente **excluyen** ese sensor — no hay
  error, el usuario solo ve lo que le corresponde.
- Las **operaciones puntuales** sobre un sensor concreto (`GET`/`PUT`/`DELETE` de
  un recurso, registrar una lectura, gestionar sus reglas de alerta o generar un
  reporte) devuelven **403** con el formato estándar de error de la API:
  `{ "success": false, "message": "No tienes acceso al sensor indicado." }`.

Al crear un sensor nuevo, el backend lo asigna **automáticamente** a todos los
usuarios con rol `super_administrador` (no requiere ninguna acción del frontend).

**Nota:** la ruta IoT de lecturas masivas (`POST /sensors/readings/bulk`) no se ve
afectada por esta restricción: se autentica con el middleware `iot.token` (sin
usuario humano de por medio), no con Sanctum.

## 1. Nuevo permiso: `sensores.asignar`

Gestionar las asignaciones requiere el permiso `sensores.asignar`. Por defecto solo
lo tienen `super_administrador` y `administrador`. El menú/permissions de
`GET /api/v1/auth/me` y `GET /api/v1/auth/menu` ya lo incluyen si el rol del usuario
lo tiene — úsalo para mostrar/ocultar la UI de asignación (ej. una pestaña
"Sensores" o "Equipos de monitoreo" dentro de la pantalla de edición de usuario).

## 2. Endpoints

### 2.1 Asignar sensores a un usuario (reemplaza el conjunto completo)

```
PUT /api/v1/users/{id}/sensors
Permiso: sensores.asignar
```

Body:
```json
{ "sensor_ids": [1, 3, 5] }
```

- `sensor_ids` es **obligatorio** (array, puede ir vacío `[]` para quitarle todos
  los sensores al usuario).
- Esta llamada **reemplaza** la asignación previa (es un `sync`, no un `append`). Si
  el usuario ya tenía el sensor 2 y envías `[1, 3]`, el sensor 2 queda desasignado.

Respuesta `200`:
```json
{
  "success": true,
  "message": "Sensores asignados exitosamente",
  "data": {
    "id": 7,
    "name": "...",
    "email": "...",
    "roles": [...],
    "permissions": [...],
    "sensors": [
      { "id": 1, "name": "Sensor Cámara Fría 1", "code": "TEMP-ZR01-01" },
      { "id": 3, "name": "Sensor Cámara Fría 2", "code": "TEMP-ZR02-01" }
    ],
    "created_at": "..."
  }
}
```

### 2.2 Ver los sensores asignados a un usuario

```
GET /api/v1/users/{id}/sensors
Permiso: sensores.asignar
```

Respuesta `200`: lista de sensores (mismo formato que `GET /api/v1/sensors`). Útil
para precargar los checkboxes/multi-select del formulario de asignación.

### 2.3 Ver qué usuarios tienen acceso a un sensor

```
GET /api/v1/sensors/{id}/users
Permiso: sensores.asignar
```

Respuesta `200`: lista de usuarios (mismo formato que `GET /api/v1/users`, incluye
`roles`). Útil para una vista "¿quién puede ver este sensor?" desde la pantalla del
sensor.

## 3. Pantalla sugerida

La forma más simple de cubrir el caso de uso es agregar, en la pantalla de **edición
de usuario** (donde ya se gestionan los roles vía `POST /users/{id}/roles` y,
si aplica, los almacenes vía `PUT /users/{id}/warehouses`), una sección o pestaña
**"Equipos de monitoreo"**:

1. Al entrar a editar un usuario, llamar `GET /api/v1/users/{id}/sensors` para
   precargar los sensores ya asignados.
2. Mostrar un multi-select (o lista de checkboxes) con el catálogo completo de
   sensores (`GET /api/v1/sensors` — si quien edita es `super_administrador` o
   `administrador`, verá todos; si no tiene `sensores.asignar` no debería ver esta
   sección). Se sugiere agrupar/mostrar por zona (`zone_id`) para facilitar la
   ubicación física del equipo.
3. Al guardar, enviar `PUT /api/v1/users/{id}/sensors` con la lista completa de
   IDs seleccionados (no solo los nuevos).
4. Mostrar el mensaje de éxito/error estándar (`success`/`message` de la respuesta).

Como alternativa simétrica, también puede ofrecerse desde la pantalla del **sensor**
un botón "Usuarios con acceso" que liste (`GET /api/v1/sensors/{id}/users`) y permita
agregar/quitar usuarios — internamente seguirá llamando al mismo endpoint
`PUT /api/v1/users/{id}/sensors` (recalculando la lista completa de ese usuario).

## 4. Manejo de errores y casos límite

| Caso | Respuesta |
|---|---|
| Usuario sin `sensores.asignar` intenta asignar | `403` (middleware de permiso, antes de llegar al controlador) |
| Usuario sin acceso al sensor intenta `GET/PUT/DELETE` directo | `403` `{"message": "No tienes acceso al sensor indicado."}` |
| Usuario sin acceso intenta registrar una lectura o gestionar reglas de alerta de ese sensor | `403` con el mismo mensaje |
| Usuario sin acceso intenta generar un reporte de condiciones de ese sensor | `403` con el mismo mensaje |
| `sensor_ids` incluye un ID inexistente | `422` de validación estándar (`exists:sensors,id`) |
| `super_administrador` | Nunca recibe 403 por este motivo; ve y opera todos los sensores aunque no tenga fila explícita en la asignación |
| Lecturas masivas vía IoT (`/sensors/readings/bulk`) | No aplica esta restricción (autenticación por `iot.token`, no por usuario) |

## 5. Notas para listados existentes

No es necesario cambiar nada en la pantalla de **listado de sensores** ya
implementada: el backend ya filtra automáticamente por los sensores asignados al
usuario autenticado. Si el usuario ve una lista vacía o más corta de lo esperado,
es síntoma de que le faltan sensores asignados — no es un bug del listado.

Si el frontend tiene selects de "sensor" en formularios (ej. el selector de
`sensor_id` al generar un reporte de condiciones), basta con poblarlos desde
`GET /api/v1/sensors` — esa respuesta ya viene filtrada, por lo que el usuario nunca
podrá seleccionar un sensor al que no tiene acceso.
