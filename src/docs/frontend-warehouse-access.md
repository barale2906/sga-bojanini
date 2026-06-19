# Guía Frontend — Asignación de almacenes a usuarios

## Contexto

Cada usuario (excepto el rol **super_administrador**, que siempre tiene acceso a
**todos** los almacenes) solo puede **ver y operar** los almacenes que le hayan sido
asignados explícitamente. Esto aplica a:

- Almacenes, Zonas y Ubicaciones (CRUD y listados).
- Movimientos de inventario (entradas, salidas, transferencias, ajustes,
  devoluciones, bajas) — en transferencias se valida acceso a **ambos** almacenes
  (origen y destino).
- Listados de stock, resumen de stock y disponibilidad de kits.
- Registro de consumos (Integration).

Cuando un usuario no tiene acceso a un almacén:
- Los **listados** (`GET` de colecciones) simplemente **excluyen** ese almacén — no
  hay error, el usuario solo ve lo que le corresponde.
- Las **operaciones puntuales** sobre un almacén concreto (`GET`/`PUT`/`DELETE` de un
  recurso, o registrar un movimiento) devuelven **403** con el formato estándar de
  error de la API: `{ "success": false, "message": "..." }`.

Al crear un almacén nuevo, el backend lo asigna **automáticamente** a todos los
usuarios con rol `super_administrador` (no requiere ninguna acción del frontend).

## 1. Nuevo permiso: `almacenes.asignar`

Gestionar las asignaciones requiere el permiso `almacenes.asignar`. Por defecto solo
lo tienen `super_administrador` y `administrador`. El menú/permissions de
`GET /api/v1/auth/me` y `GET /api/v1/auth/menu` ya lo incluyen si el rol del usuario
lo tiene — úsalo para mostrar/ocultar la UI de asignación (ej. una pestaña "Almacenes"
dentro de la pantalla de edición de usuario).

## 2. Endpoints

### 2.1 Asignar almacenes a un usuario (reemplaza el conjunto completo)

```
PUT /api/v1/users/{id}/warehouses
Permiso: almacenes.asignar
```

Body:
```json
{ "warehouse_ids": [1, 3, 5] }
```

- `warehouse_ids` es **obligatorio** (array, puede ir vacío `[]` para quitarle todos
  los almacenes al usuario).
- Esta llamada **reemplaza** la asignación previa (es un `sync`, no un `append`). Si
  el usuario ya tenía el almacén 2 y envías `[1, 3]`, el almacén 2 queda desasignado.

Respuesta `200`:
```json
{
  "success": true,
  "message": "Almacenes asignados exitosamente",
  "data": {
    "id": 7,
    "name": "...",
    "email": "...",
    "roles": [...],
    "permissions": [...],
    "warehouses": [
      { "id": 1, "name": "Almacén Central", "code": "ALM-001" },
      { "id": 3, "name": "Almacén Norte", "code": "ALM-003" }
    ],
    "created_at": "..."
  }
}
```

### 2.2 Ver los almacenes asignados a un usuario

```
GET /api/v1/users/{id}/warehouses
Permiso: almacenes.asignar
```

Respuesta `200`: lista de almacenes (mismo formato que `GET /api/v1/warehouses`).
Útil para precargar los checkboxes/multi-select del formulario de asignación.

### 2.3 Ver qué usuarios tienen acceso a un almacén

```
GET /api/v1/warehouses/{id}/users
Permiso: almacenes.asignar
```

Respuesta `200`: lista de usuarios (mismo formato que `GET /api/v1/users`, incluye
`roles`). Útil para una vista "¿quién puede ver este almacén?" desde la pantalla del
almacén.

## 3. Pantalla sugerida

La forma más simple de cubrir el caso de uso es agregar, en la pantalla de **edición
de usuario** (donde ya se gestionan los roles vía `POST /users/{id}/roles`), una
sección o pestaña **"Almacenes"**:

1. Al entrar a editar un usuario, llamar `GET /api/v1/users/{id}/warehouses` para
   precargar los almacenes ya asignados.
2. Mostrar un multi-select (o lista de checkboxes) con el catálogo completo de
   almacenes (`GET /api/v1/warehouses` — si quien edita es `super_administrador` o
   `administrador`, verá todos; si no tiene `almacenes.asignar` no debería ver esta
   sección).
3. Al guardar, enviar `PUT /api/v1/users/{id}/warehouses` con la lista completa de
   IDs seleccionados (no solo los nuevos).
4. Mostrar el mensaje de éxito/error estándar (`success`/`message` de la respuesta).

Como alternativa simétrica, también puede ofrecerse desde la pantalla del **almacén**
un botón "Usuarios con acceso" que liste (`GET /api/v1/warehouses/{id}/users`) y
permita agregar/quitar usuarios — internamente seguirá llamando al mismo endpoint
`PUT /api/v1/users/{id}/warehouses` (recalculando la lista completa de ese usuario).

## 4. Manejo de errores y casos límite

| Caso | Respuesta |
|---|---|
| Usuario sin `almacenes.asignar` intenta asignar | `403` (middleware de permiso, antes de llegar al controlador) |
| Usuario sin acceso al almacén intenta `GET/PUT/DELETE` directo | `403` `{"message": "No tienes acceso al almacén indicado."}` |
| Usuario sin acceso intenta registrar un movimiento en ese almacén | `403` con el mismo mensaje |
| `warehouse_ids` incluye un ID inexistente | `422` de validación estándar (`exists:warehouses,id`) |
| `super_administrador` | Nunca recibe 403 por este motivo; ve y opera todos los almacenes aunque no tenga fila explícita en la asignación |

## 5. Notas para listados existentes

No es necesario cambiar nada en las pantallas de **listado** ya implementadas
(almacenes, zonas, ubicaciones, stock, movimientos, kit-availability): el backend
ya filtra automáticamente por los almacenes asignados al usuario autenticado. Si el
usuario ve una lista vacía o más corta de lo esperado, es síntoma de que le faltan
almacenes asignados — no es un bug del listado.

Si el frontend tiene selects de "almacén" en formularios (ej. el selector de
`warehouse_id` al registrar una entrada), basta con poblarlos desde
`GET /api/v1/warehouses` — esa respuesta ya viene filtrada, por lo que el usuario
nunca podrá seleccionar un almacén al que no tiene acceso.
