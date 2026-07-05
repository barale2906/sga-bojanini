# Guía Frontend — Permisos y control de acceso

## Contexto

El sistema aplica dos capas de autorización independientes:

1. **Permisos** (`permissions[]`): controlan qué acciones puede realizar un usuario,
   sin importar su rol. Se verifican en la ruta de la API.
2. **Acceso a almacenes** (`user_warehouse`): controlan qué almacenes puede ver y
   operar un usuario. Se verifican en el controlador.

Ambas deben cumplirse para que una operación de inventario sea aceptada. El frontend
debe usarlas para mostrar/ocultar elementos de la UI, pero el backend las valida siempre
de forma independiente.

---

## 1. Cómo obtener los permisos al iniciar sesión

### Login

```
POST /api/v1/auth/login
```

**Respuesta:**

```json
{
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 42,
      "name": "Claudia Herrera",
      "email": "claudia@bojanini.com",
      "is_active": true,
      "roles": [
        { "id": 3, "name": "jefe_almacen" }
      ],
      "permissions": [
        "almacenes.ver",
        "stock.ver",
        "movimientos.entrada",
        "movimientos.ajuste",
        "movimientos.devolucion",
        "movimientos.baja"
      ]
    }
  }
}
```

**Lo que se debe persistir en sesión:**
- `token` → se envía como `Authorization: Bearer {token}` en cada request.
- `permissions[]` → array de strings que controla qué se muestra en la UI.

> **Nota:** Los almacenes asignados al usuario **no vienen en el login**. Se obtienen
> llamando a `GET /api/v1/warehouses`, que el backend ya filtra automáticamente.

### Refrescar datos del usuario

```
GET /api/v1/auth/me
```

Devuelve el mismo objeto `user` con `roles` y `permissions` actualizados. Útil si un
administrador cambió el rol del usuario durante la sesión.

### Refrescar el token

```
POST /api/v1/auth/refresh
```

Revoca el token actual y emite uno nuevo con los permisos vigentes del usuario.

---

## 2. Cómo verificar permisos en el frontend

Guardar el array `permissions` en el store de sesión y exponer una función de consulta:

```js
// usePermissions.js
import { useSessionStore } from '@/stores/session'

export function usePermissions() {
  const session = useSessionStore()

  const can    = (permission)    => session.permissions.includes(permission)
  const canAny = (...perms)      => perms.some(p => can(p))
  const canAll = (...perms)      => perms.every(p => can(p))

  return { can, canAny, canAll }
}
```

```html
<!-- Uso en plantilla Vue -->
<button v-if="can('movimientos.ajuste')">Ajustar stock</button>
<button v-if="can('movimientos.baja')">Dar de baja</button>
<button v-if="can('movimientos.devolucion')">Registrar devolución</button>
```

---

## 3. Catálogo completo de permisos

| Grupo | Permisos |
|---|---|
| Usuarios | `usuarios.ver` `usuarios.crear` `usuarios.editar` `usuarios.eliminar` |
| Roles | `roles.ver` `roles.crear` `roles.editar` `roles.eliminar` |
| Almacenes | `almacenes.ver` `almacenes.crear` `almacenes.editar` `almacenes.eliminar` `almacenes.asignar` |
| Zonas | `zonas.ver` `zonas.crear` `zonas.editar` `zonas.eliminar` |
| Ubicaciones | `ubicaciones.ver` `ubicaciones.crear` `ubicaciones.editar` `ubicaciones.eliminar` |
| Productos | `productos.ver` `productos.crear` `productos.editar` `productos.eliminar` `productos.importar` |
| Proveedores | `proveedores.ver` `proveedores.crear` `proveedores.editar` `proveedores.eliminar` `proveedores.importar` |
| Lotes | `lotes.ver` `lotes.crear` |
| Stock | `stock.ver` |
| Movimientos | `movimientos.entrada` `movimientos.salida` `movimientos.transferir` `movimientos.ajuste` `movimientos.devolucion` `movimientos.baja` `movimientos.importar` `movimientos.confirmar` `movimientos.cancelar` |
| Órdenes de compra | `ordenes_compra.ver` `ordenes_compra.crear` `ordenes_compra.aprobar` `ordenes_compra.enviar` `ordenes_compra.recibir` |
| Sensores | `sensores.ver` `sensores.crear` `sensores.editar` `sensores.eliminar` `sensores.asignar` |
| Lecturas | `lecturas.ver` `lecturas.crear` |
| Reglas de alerta | `reglas_alerta.ver` `reglas_alerta.crear` `reglas_alerta.editar` `reglas_alerta.eliminar` |
| Auditoría | `auditoria.ver` `auditoria.exportar` |
| Reportes | `reportes.ver` `reportes.exportar` |
| Integraciones | `integraciones.ver` `integraciones.configurar` |
| Consumos | `consumos.ver` `consumos.crear` |
| Centros de costo | `centros_costo.ver` `centros_costo.crear` `centros_costo.editar` `centros_costo.eliminar` |
| Servicios médicos | `servicios_medicos.ver` `servicios_medicos.crear` `servicios_medicos.editar` `servicios_medicos.eliminar` `servicios_medicos.importar` |
| Procedimientos | `procedimientos.ver` `procedimientos.crear` `procedimientos.editar` `procedimientos.eliminar` |
| Registros de paciente | `registros_procedimientos.ver` `registros_procedimientos.crear` `registros_procedimientos.editar` `registros_procedimientos.eliminar` |
| General | `tablero.ver` `notificaciones.ver` |

### Permisos de movimientos por rol

| Permiso | super_admin | administrador | jefe_almacen | operador_almacen | compras | auditor | personal_medico |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `movimientos.entrada` | ✓ | ✓ | ✓ | ✓ | | | |
| `movimientos.salida` | ✓ | ✓ | ✓ | ✓ | | | ✓ |
| `movimientos.transferir` | ✓ | ✓ | ✓ | ✓ | | | |
| `movimientos.ajuste` | ✓ | ✓ | ✓ | | | | |
| `movimientos.devolucion` | ✓ | ✓ | ✓ | | | | |
| `movimientos.baja` | ✓ | ✓ | ✓ | | | | |
| `movimientos.confirmar` | ✓ | ✓ | ✓ | ✓ | | | |
| `movimientos.cancelar` | ✓ | ✓ | ✓ | ✓ | | | |
| `stock.ver` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `almacenes.asignar` | ✓ | ✓ | | | | | |

---

## 4. Control de acceso por almacén

Cada usuario tiene asignados explícitamente los almacenes que puede gestionar. El
backend filtra automáticamente todos los endpoints.

**El `super_administrador` tiene acceso a todos los almacenes sin necesitar asignación
explícita.** Para los demás roles aplica la restricción.

### Qué filtra el backend automáticamente

| Endpoint | Comportamiento para usuario restringido |
|---|---|
| `GET /warehouses` | Solo devuelve almacenes asignados al usuario |
| `GET /warehouses/{id}` | 403 si el almacén no está asignado |
| `GET /stock` | Solo stock de almacenes asignados |
| `GET /movements` | Solo movimientos donde `warehouse_id` o `warehouse_to_id` esté asignado |
| `GET /batches` | Solo lotes con ubicaciones en almacenes asignados |
| `GET /sensors` | Solo sensores de almacenes asignados (sincronización automática) |
| `POST /movements/*` | 403 si el `warehouse_id` enviado no está asignado al usuario |

### Cómo poblar los selectores de almacén

Siempre cargar desde el endpoint; nunca hardcodear IDs:

```js
const warehouses = await api.get('/api/v1/warehouses')
// → Solo los almacenes asignados al usuario logueado
```

En traslados, usar el mismo listado para los selectores de origen y destino. El backend
valida que el usuario tenga acceso a ambos.

### Asignación de almacenes (solo administradores)

Solo usuarios con `almacenes.asignar` pueden gestionar asignaciones:

```
GET /api/v1/users/{id}/warehouses  → ver almacenes de un usuario
PUT /api/v1/users/{id}/warehouses  → asignar/reemplazar almacenes
```

> **Importante — asignación de sensores:** Los sensores se sincronizan automáticamente
> con los almacenes asignados. **No mostrar la pantalla de asignación manual de sensores**
> (`PUT /users/{id}/sensors`); si se usa, sobreescribe la sincronización automática.
> `GET /users/{id}/sensors` puede usarse en modo solo lectura.

---

## 5. Reglas por tipo de movimiento

Cada movimiento requiere **permiso** + **acceso al almacén**. Ambas condiciones deben
cumplirse.

| Tipo | Endpoint | Permiso requerido | Restricción de almacén |
|---|---|---|---|
| Entrada | `POST /movements/entry` | `movimientos.entrada` | `warehouse_id` debe ser asignado |
| Salida | `POST /movements/exit` | `movimientos.salida` | `warehouse_id` debe ser asignado |
| Traslado | `POST /movements/transfer` | `movimientos.transferir` | `warehouse_from_id` **y** `warehouse_to_id` deben ser asignados |
| Ajuste | `POST /movements/adjustment` | `movimientos.ajuste` | `warehouse_id` debe ser asignado |
| Devolución | `POST /movements/return` | `movimientos.devolucion` | `warehouse_id` debe ser asignado |
| Baja por vencimiento | `POST /movements/write-off` | `movimientos.baja` | `warehouse_id` debe ser asignado |
| Baja de inventario | `POST /movements/loss` | `movimientos.baja` | `warehouse_id` debe ser asignado |
| Importación masiva | `POST /movements/initial-entries/import` | `movimientos.importar` | `warehouse_id` (opcional) debe ser asignado |
| Confirmar | `POST /movements/{id}/confirm` | `movimientos.confirmar` | Acceso al almacén del movimiento |
| Cancelar pendiente | `DELETE /movements/{id}/pending` | `movimientos.cancelar` | Acceso al almacén del movimiento |

### Ajuste, devolución y baja — doble restricción

Estas tres operaciones requieren el permiso específico **y además** acceso al almacén.
El `operador_almacen` tiene acceso al almacén pero **no** tiene esos permisos — no
mostrar esas opciones en su interfaz.

---

## 6. Qué ocultar según permiso en cada módulo

### Inventario — Stock y movimientos

- Sección de stock y movimientos → `stock.ver`
- Botón "Nueva entrada" → `movimientos.entrada`
- Botón "Nueva salida" → `movimientos.salida`
- Botón "Nuevo traslado" → `movimientos.transferir`
- Botón "Ajuste de stock" → `movimientos.ajuste`
- Botón "Devolución" → `movimientos.devolucion`
- Botón "Baja por vencimiento" y "Baja de inventario" → `movimientos.baja`
- Botón "Confirmar movimiento" → `movimientos.confirmar`
- Botón "Cancelar pendiente" → `movimientos.cancelar`

### Almacenes, zonas y ubicaciones

- Módulo visible → `almacenes.ver`
- Crear / editar / eliminar almacén → `almacenes.crear` / `.editar` / `.eliminar`
- Opción "Asignar almacenes a usuarios" → `almacenes.asignar`

### Monitoreo

- Módulo visible → `sensores.ver`
- Registrar lectura manual → `lecturas.crear`
- Crear / editar / eliminar regla de alerta → `reglas_alerta.crear` / `.editar` / `.eliminar`
- **No mostrar** pantalla de asignación manual de sensores (ver nota sección 4)

### Órdenes de compra

- Ver listado → `ordenes_compra.ver`
- Crear orden → `ordenes_compra.crear`
- Aprobar → `ordenes_compra.aprobar`
- Enviar a proveedor → `ordenes_compra.enviar`
- Recibir mercancía → `ordenes_compra.recibir`

### Módulo clínico

- Consumos → `consumos.ver` / `consumos.crear`
- Centros de costo → `centros_costo.ver`
- Servicios médicos → `servicios_medicos.ver`
- Procedimientos → `procedimientos.ver`
- Registros por paciente → `registros_procedimientos.ver` / `registros_procedimientos.crear`

---

## 7. Manejo de errores de autorización

El backend siempre devuelve JSON. Los errores de autorización tienen este formato:

```json
// HTTP 403 — sin permiso o sin acceso al almacén
{
  "success": false,
  "message": "No tienes acceso al almacén indicado.",
  "errors": []
}

// HTTP 401 — token inválido o expirado
{
  "message": "Unauthenticated."
}
```

| Código | Causa | Acción en UI |
|---|---|---|
| 401 | Token inválido o expirado | Redirigir al login y limpiar sesión |
| 403 | Sin permiso o sin acceso al almacén | Mostrar el campo `message` del JSON. No dejar pantalla en blanco. |
| 422 | Validación fallida | Mostrar los errores del campo `errors` en los inputs correspondientes |
| 404 | Recurso no encontrado | Mostrar mensaje o redirigir al listado |

Interceptor recomendado:

```js
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status

    if (status === 401) {
      sessionStore.clear()
      router.push('/login')
    }

    if (status === 403) {
      const msg = error.response?.data?.message ?? 'Sin acceso'
      toast.error(msg)
    }

    return Promise.reject(error)
  }
)
```

> **Ocultar ≠ proteger.** Esconder botones en la UI es un patrón de UX, no de
> seguridad. El backend rechaza la operación de todas formas. Siempre manejar el 403
> en la capa de API para no dejar al usuario con un spinner infinito o un error genérico.

---

## 8. Flujo de inicialización de sesión recomendado

1. `POST /auth/login` → guardar `token` y `permissions[]` en el store de sesión.
2. `GET /warehouses` → poblar el selector global de almacén (la respuesta ya está filtrada).
3. `GET /auth/menu` → construir la navegación lateral (el backend devuelve solo los ítems accesibles).

Si el rol de un usuario cambia durante la sesión activa, llamar a `POST /auth/refresh`
para emitir un nuevo token con los permisos vigentes y actualizar el store.
