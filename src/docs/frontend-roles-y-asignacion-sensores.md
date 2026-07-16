# Guía Frontend — Módulo de Roles, permisos y asignación de sensores a usuarios

## Contexto

Este documento cubre tres funcionalidades relacionadas:

1. **Gestión de roles**: el frontend puede crear y editar roles personalizados, elegir qué permisos llevan y ver cuántos usuarios los tienen.
2. **Asignación de sensores desde la pantalla del equipo**: un usuario con `sensores.asignar` puede asignar o quitar usuarios directamente desde la pantalla de detalle del sensor, sin necesidad de entrar a la pantalla de usuarios.
3. **Creación de usuarios sin almacén**: la asignación de almacenes **no es obligatoria** al crear un usuario; el backend no la exige y el frontend no debe bloquearla.

---

## 1. Módulo de Roles

### 1.1 Permisos necesarios

| Acción | Permiso requerido |
|---|---|
| Ver lista de roles y permisos disponibles | `roles.ver` |
| Crear un rol nuevo | `roles.crear` |
| Editar nombre o permisos de un rol | `roles.editar` |
| Eliminar un rol | `roles.eliminar` |

El menú del usuario autenticado (`GET /api/v1/auth/me` → campo `permissions`) ya incluye los permisos que tiene. Úsalos para mostrar/ocultar botones y rutas.

---

### 1.2 Endpoints

#### Listar todos los roles

```
GET /api/v1/roles
Permiso: roles.ver
```

Respuesta `200`:
```json
{
  "success": true,
  "message": "Listado de roles",
  "data": [
    {
      "id": 1,
      "name": "super_administrador",
      "permissions": ["usuarios.ver", "usuarios.crear", "..."],
      "users_count": 2
    },
    {
      "id": 4,
      "name": "auditor",
      "permissions": ["almacenes.ver", "sensores.ver", "lecturas.ver"],
      "users_count": 5
    }
  ]
}
```

`users_count` indica cuántos usuarios tienen ese rol asignado. Úsalo para deshabilitar el botón "Eliminar" cuando sea mayor que 0.

#### Ver detalle de un rol

```
GET /api/v1/roles/{id}
Permiso: roles.ver
```

Misma estructura que el ítem de la lista. Útil para precargar el formulario de edición.

#### Listar todos los permisos disponibles (agrupados por módulo)

```
GET /api/v1/permissions
Permiso: roles.ver
```

Respuesta `200`:
```json
{
  "success": true,
  "data": {
    "usuarios":   ["usuarios.ver", "usuarios.crear", "usuarios.editar", "usuarios.eliminar"],
    "roles":      ["roles.ver", "roles.crear", "roles.editar", "roles.eliminar"],
    "almacenes":  ["almacenes.ver", "almacenes.crear", "almacenes.editar", "almacenes.eliminar", "almacenes.asignar"],
    "sensores":   ["sensores.ver", "sensores.crear", "sensores.editar", "sensores.eliminar", "sensores.asignar"],
    "lecturas":   ["lecturas.ver", "lecturas.crear"],
    "reglas_alerta": ["reglas_alerta.ver", "reglas_alerta.crear", "reglas_alerta.editar", "reglas_alerta.eliminar"],
    "movimientos": ["movimientos.entrada", "movimientos.salida", "..."],
    "..."
  }
}
```

Los permisos vienen agrupados por el prefijo antes del punto. Usa esta respuesta para construir el selector de permisos en el formulario de creación/edición de roles.

#### Crear un rol

```
POST /api/v1/roles
Permiso: roles.crear
```

Body:
```json
{
  "name": "operador_monitoreo",
  "permission_ids": [15, 16, 17]
}
```

- `name`: string, obligatorio, único entre roles.
- `permission_ids`: array de IDs de permisos (opcionales al crear; se pueden asignar después vía edición).

Respuesta `201`:
```json
{
  "success": true,
  "message": "Rol creado exitosamente",
  "data": {
    "id": 9,
    "name": "operador_monitoreo",
    "permissions": ["sensores.ver", "lecturas.ver", "lecturas.crear"],
    "users_count": 0
  }
}
```

#### Editar un rol

```
PUT /api/v1/roles/{id}
Permiso: roles.editar
```

Body:
```json
{
  "name": "operador_monitoreo",
  "permission_ids": [15, 16, 17, 18]
}
```

- `permission_ids` reemplaza completamente los permisos anteriores (es un `sync`).
- Enviar `permission_ids: []` quita todos los permisos del rol.

Respuesta `200` con la misma estructura que el detalle.

#### Eliminar un rol

```
DELETE /api/v1/roles/{id}
Permiso: roles.eliminar
```

El backend rechaza la eliminación si el rol tiene usuarios asignados:

```json
{
  "success": false,
  "message": "No se puede eliminar un rol que tiene usuarios asignados.",
  "status": 409
}
```

Deshabilita el botón de eliminación cuando `users_count > 0` y muestra el mensaje `"Este rol tiene X usuario(s) asignado(s)"`.

---

### 1.3 Pantalla sugerida — Lista de roles

```
┌─────────────────────────────────────────────────────────┐
│ Roles del sistema                        [+ Nuevo rol]  │
├────────────────────┬──────────────┬────────────────────-┤
│ Nombre             │ Permisos     │ Usuarios   │ Acciones│
├────────────────────┼──────────────┼───────────-┼────────-┤
│ super_administrador│ 64 permisos  │ 2          │ Editar  │
│ administrador      │ 58 permisos  │ 3          │ Editar  │
│ operador_monitoreo │ 3 permisos   │ 0          │ Editar  │
│                    │              │            │ Eliminar│
└────────────────────┴──────────────┴────────────┴─────────┘
```

- "Eliminar" solo visible/habilitado si `users_count === 0`.
- El conteo de permisos puede mostrarse como badge: `permissions.length`.

---

### 1.4 Formulario de creación / edición de rol

1. Campo de texto para el nombre del rol.
2. Llamar `GET /api/v1/permissions` para obtener el catálogo completo de permisos.
3. Renderizar los permisos agrupados por módulo (las claves del objeto respuesta): checkboxes o toggle switches.
4. Al abrir un rol existente, precargar el estado marcado de cada permiso comparando con `role.permissions`.
5. Al guardar, enviar el array completo de IDs de los permisos marcados en `permission_ids`.

**Mapeo de nombres de permiso para mostrar en UI** (sugerencia):

| Permiso (backend) | Etiqueta UI |
|---|---|
| `sensores.ver` | Ver equipos de monitoreo |
| `sensores.crear` | Crear equipos de monitoreo |
| `sensores.editar` | Editar equipos de monitoreo |
| `sensores.eliminar` | Eliminar equipos de monitoreo |
| `sensores.asignar` | Asignar usuarios a equipos |
| `lecturas.ver` | Ver lecturas de sensores |
| `lecturas.crear` | Registrar lecturas manuales |
| `reglas_alerta.ver` | Ver reglas de alerta |
| `reglas_alerta.crear` | Crear reglas de alerta |
| `reglas_alerta.editar` | Editar reglas de alerta |
| `reglas_alerta.eliminar` | Eliminar reglas de alerta |
| `almacenes.ver` | Ver almacenes |
| `almacenes.asignar` | Asignar usuarios a almacenes |
| `movimientos.entrada` | Registrar entradas |
| `movimientos.salida` | Registrar salidas |
| `usuarios.ver` | Ver usuarios |
| `usuarios.crear` | Crear usuarios |
| `roles.ver` | Ver roles |
| `roles.crear` | Crear roles |

---

### 1.5 Rol recomendado para operadores solo de monitoreo

Para usuarios que únicamente registran datos en equipos de monitoreo (sin acceso a almacenes ni inventario), crear un rol con estos permisos:

```json
{
  "name": "operador_monitoreo",
  "permission_ids": [<ids de los siguientes permisos>]
}
```

| Permiso | Motivo |
|---|---|
| `sensores.ver` | Ver la lista de sensores asignados y su detalle |
| `lecturas.ver` | Consultar historial de lecturas |
| `lecturas.crear` | Registrar una lectura manual |
| `reglas_alerta.ver` | Ver las reglas configuradas (para entender los umbrales) |
| `tablero.ver` | Acceso al tablero general |
| `notificaciones.ver` | Recibir alertas del sistema |

Los IDs concretos de estos permisos se obtienen de `GET /api/v1/permissions` y pueden variar según el orden de siembra. Recomendamos usar los nombres para buscarlos y presentarlos en la UI.

> **Nota**: los usuarios con este rol **no necesitan tener almacenes asignados**. Solo necesitan tener los sensores asignados vía `PUT /api/v1/users/{id}/sensors` (ver sección 2 de este documento y el documento `frontend-sensor-access.md`).

---

## 2. Asignar usuarios a un sensor desde la pantalla del equipo

Además del flujo desde la pantalla de usuario (documentado en `frontend-sensor-access.md`), el backend expone un endpoint para **consultar qué usuarios tienen acceso a un sensor concreto**. Esto permite construir un panel de asignación directamente desde la pantalla de detalle del equipo de monitoreo.

### 2.1 Endpoint: usuarios con acceso a un sensor

```
GET /api/v1/sensors/{id}/users
Permiso: sensores.asignar
```

Respuesta `200`:
```json
{
  "success": true,
  "message": "Usuarios con acceso al sensor",
  "data": [
    {
      "id": 3,
      "name": "María Pérez",
      "email": "mariap@bojanini.com",
      "is_active": true,
      "roles": [{ "id": 9, "name": "operador_monitoreo" }]
    }
  ]
}
```

### 2.2 Flujo de asignación desde la pantalla del sensor

El endpoint de asignación real sigue siendo `PUT /api/v1/users/{id}/sensors` (trabaja sobre el usuario, no sobre el sensor). Para asignar o quitar un usuario desde la pantalla del sensor, el frontend debe:

1. Llamar `GET /api/v1/sensors/{id}/users` para obtener la lista actual de usuarios con acceso.
2. Llamar `GET /api/v1/users` (con búsqueda opcional) para obtener el catálogo de usuarios disponibles.
3. Mostrar un multi-select o lista de checkboxes con todos los usuarios; preseleccionar los que ya tienen acceso.
4. Al confirmar, **para cada usuario cuyo estado haya cambiado**:
   - Obtener sus sensores actuales: `GET /api/v1/users/{id}/sensors` → array de IDs.
   - Si se está **agregando** el sensor al usuario: enviar `PUT /api/v1/users/{id}/sensors` con `[...sensor_ids_actuales, sensor_nuevo_id]`.
   - Si se está **quitando** el sensor al usuario: enviar `PUT /api/v1/users/{id}/sensors` con la lista sin ese ID.

> El endpoint `PUT /api/v1/users/{id}/sensors` siempre reemplaza la lista completa de sensores del usuario (sync), por lo que hay que enviar el conjunto resultante, no solo el delta.

### 2.3 Panel sugerido en la pantalla del sensor

```
┌────────────────────────────────────────────────────────────┐
│ Sensor: TEMP-ZR01-01 · Zona: Cámara Fría 1                │
│                                                            │
│  [Detalle]  [Lecturas]  [Reglas de alerta]  [Usuarios ▾]  │
│                                                            │
│  Usuarios con acceso                    [+ Agregar usuario]│
│  ┌───────────────────────────────────────────────────────┐ │
│  │ María Pérez       operador_monitoreo      [Quitar]    │ │
│  │ Juan García       jefe_almacen            [Quitar]    │ │
│  └───────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────┘
```

- La pestaña "Usuarios" solo se muestra si el usuario autenticado tiene el permiso `sensores.asignar`.
- "Quitar" llama a `GET /api/v1/users/{id}/sensors` y luego `PUT /api/v1/users/{id}/sensors` con la lista sin ese sensor.
- "+ Agregar usuario" abre un modal con buscador de usuarios y llama a la misma secuencia.

### 2.4 Manejo de errores

| Caso | Respuesta |
|---|---|
| Sin permiso `sensores.asignar` | `403` — ocultar sección completa |
| `user_id` o `sensor_id` inexistente | `404` del endpoint correspondiente |
| `sensor_ids` con ID inexistente en el PUT | `422` de validación |

---

## 3. Crear usuarios sin almacén obligatorio

### 3.1 Validación del backend

El endpoint de creación de usuario **no exige** ningún almacén:

```
POST /api/v1/users
Permiso: usuarios.crear
```

Body mínimo válido:
```json
{
  "name": "Carlos Méndez",
  "email": "carlosm@bojanini.com",
  "password": "Monitoreo2026!",
  "role_ids": [9]
}
```

Campos opcionales: `phone`, `is_active` (default `true`). Los campos `warehouse_ids` y `sensor_ids` **no existen** en este endpoint — se asignan en pasos posteriores y separados.

### 3.2 Flujo correcto de alta de usuario

```
1. POST /api/v1/users            → crear usuario con rol(es)
        ↓ (si rol = super_administrador)
   Backend asigna automáticamente todos los almacenes y sensores existentes

2. PUT  /api/v1/users/{id}/warehouses  → (opcional) asignar almacenes
        ↓ (el backend sincroniza los sensores de esas zonas automáticamente)

3. PUT  /api/v1/users/{id}/sensors     → (opcional) ajuste fino de sensores
```

Para usuarios que **solo manejan equipos de monitoreo** (rol `operador_monitoreo` u otro sin permisos de almacén), el paso 2 no aplica. Solo hay que asignarles los sensores en el paso 3.

### 3.3 Lo que el frontend debe cambiar

- Remover cualquier validación de cliente que marque el campo de almacenes como obligatorio al crear o guardar un usuario.
- El formulario de creación de usuario debe mostrar la sección de almacenes y sensores como **opcional**, no como un step bloqueante.
- Se recomienda separar el formulario en dos partes:
  - **Datos básicos + rol** (obligatorios): nombre, email, contraseña, rol.
  - **Asignación de recursos** (opcional, se puede hacer después): almacenes, sensores.

### 3.4 Respuesta al crear un usuario sin almacén

```json
{
  "success": true,
  "message": "Usuario creado exitosamente",
  "data": {
    "id": 12,
    "name": "Carlos Méndez",
    "email": "carlosm@bojanini.com",
    "phone": null,
    "is_active": true,
    "roles": [{ "id": 9, "name": "operador_monitoreo" }],
    "permissions": ["sensores.ver", "lecturas.ver", "lecturas.crear", "tablero.ver", "notificaciones.ver"],
    "warehouses": [],
    "sensors": [],
    "created_at": "2026-07-15T10:00:00+00:00"
  }
}
```

`warehouses` y `sensors` vendrán vacíos — es el comportamiento correcto para roles sin almacén.

---

## 4. Resumen de cambios en el frontend

| Pantalla | Cambio necesario |
|---|---|
| **Roles → Lista** | Página nueva: listar roles con `users_count` y permisos |
| **Roles → Crear/Editar** | Formulario con selector de permisos agrupados por módulo |
| **Roles → Eliminar** | Deshabilitar si `users_count > 0`, mostrar 409 si llega del backend |
| **Sensores → Detalle** | Agregar pestaña/sección "Usuarios" (solo si `sensores.asignar`) |
| **Usuarios → Crear** | Quitar validación que hace obligatorio asignar almacén |
| **Usuarios → Crear** | Separar sección de asignación de recursos (almacenes/sensores) como opcional |
