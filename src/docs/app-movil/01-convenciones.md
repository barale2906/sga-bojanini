# 01 · Convenciones generales de la API

## 1. Headers

| Header | Valor | Cuándo |
|---|---|---|
| `Accept` | `application/json` | Siempre |
| `Content-Type` | `application/json` | Peticiones con body JSON |
| `Content-Type` | `multipart/form-data` | Subida de archivos (Excel de importación, certificados, documentos) |
| `Authorization` | `Bearer <token>` | Todas las rutas, excepto `POST /auth/login` |
| `X-IoT-Token` | token del dispositivo | Solo `POST /sensors/readings/bulk` (dispositivos IoT; **no** lo usa la app) |

La API fuerza respuestas JSON incluso si no se envía `Accept`, pero se recomienda enviarlo.

## 2. Autenticación y sesión

**Login** — `POST /api/v1/auth/login` (único endpoint público)

```json
// Request
{ "email": "claudia@bojanini.com", "password": "secreto123", "device_name": "iPhone de Claudia" }
```
`device_name` es opcional (máx. 100); sirve para identificar el dispositivo en el token.

```json
// 200
{
  "success": true,
  "message": "Login exitoso",
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 42,
      "name": "Claudia Herrera",
      "email": "claudia@bojanini.com",
      "phone": "3001234567",
      "is_active": true,
      "roles": [ { "id": 3, "name": "jefe_almacen" } ],
      "permissions": ["almacenes.ver", "stock.ver", "movimientos.entrada"],
      "created_at": "2026-01-10T15:00:00-05:00"
    }
  }
}
```

Errores de login: `401` credenciales inválidas (`"Credenciales inválidas."`), `409` cuenta desactivada, `422` campos faltantes/mal formados.

**Ciclo de vida del token**

- El token **no tiene expiración automática**; vive hasta `POST /auth/logout`, hasta que un administrador lo revoque o hasta que el usuario sea desactivado (en ese caso la siguiente petición devuelve `403` y el token se elimina).
- `POST /auth/refresh` emite un token nuevo con los permisos vigentes. El token anterior sigue siendo válido solo **120 segundos** (ventana de gracia para peticiones en vuelo). Reemplazar el token guardado inmediatamente.
- `GET /auth/me` devuelve el usuario con `roles` y `permissions` actualizados (útil al abrir la app o tras un `403` inesperado).
- Almacenamiento: Keychain (iOS) / Keystore + EncryptedSharedPreferences (Android). Nunca en texto plano.

**Cambio de contraseña** — `PUT /auth/password` con `current_password`, `new_password` (mín. 8) y `new_password_confirmation`.

## 3. Envoltorio de respuestas

Todas las respuestas JSON siguen la misma estructura.

**Éxito (objeto o lista simple)**
```json
{ "success": true, "message": "Mensaje legible", "data": { } }
```

**Éxito paginado**
```json
{
  "success": true,
  "message": "OK",
  "data": [ { }, { } ],
  "meta": { "current_page": 1, "last_page": 4, "per_page": 25, "total": 92 }
}
```

**Eliminación**: `{ "success": true, "message": "Recurso eliminado" }` (HTTP 200, sin `data`).

**Creación**: HTTP `201` con el recurso en `data`.

**Error**
```json
{ "success": false, "message": "Descripción del error", "errors": { "campo": ["mensaje"] } }
```
`errors` solo aparece en validación (422).

## 4. Códigos HTTP

| Código | Significado | Qué hacer en la app |
|---|---|---|
| 200 / 201 | OK / creado | — |
| 401 | Token ausente, inválido o revocado. En login: credenciales incorrectas | Borrar token y llevar al login |
| 403 | Sin permiso (`"No tienes el permiso 'x' ..."`), sin acceso al almacén/sensor, o cuenta desactivada | Mostrar mensaje; ocultar la acción. Si es "cuenta desactivada", cerrar sesión |
| 404 | Recurso no existe (`"Recurso no encontrado."`) | Mensaje / volver a la lista |
| 405 | Método no permitido | Error de programación |
| 409 | **Regla de negocio violada** (stock insuficiente, estado inválido, lote vencido, etc.). El `message` es apto para mostrar al usuario | Mostrar `message` |
| 422 | Validación fallida; `errors` mapea campo → lista de mensajes. Las claves de arreglos vienen como `items.0.quantity_base` | Marcar los campos en el formulario |
| 429 | Demasiadas peticiones (límites en firma/PIN) | Reintentar más tarde |
| 500 | Error interno | Mensaje genérico, reintentar |

Caso especial `409` con `error_code: "EXPIRED_STOCK"`: se intentó mover stock de un lote vencido.

**Límites de tasa conocidos**: `PUT /auth/me/signature` 10/min; `POST /auth/me/signature/pin` 5/min; un PIN de firma incorrecto devuelve `422` (campo `received_by.pin`); tras varios intentos fallidos el PIN se bloquea temporalmente y responde `429`.

## 5. Paginación y filtros

- Parámetros: `page` (por defecto 1) y `per_page` (por defecto **25**, máximo **100**).
- Las listas paginadas devuelven `meta` (ver arriba). Las que no usan `meta` devuelven todo el arreglo en `data`.
- Los filtros se envían como query string (`?warehouse_id=2&status=active`). Los filtros disponibles por endpoint están en la referencia de cada módulo (tabla de *Query params* o lista *detectados*).
- Fechas en filtros: `YYYY-MM-DD`.

## 6. Tipos de datos

| Tipo | Formato |
|---|---|
| Fecha | `YYYY-MM-DD` |
| Fecha-hora | ISO 8601 con zona (`2026-11-24T14:30:00-05:00`). La zona horaria de negocio es Colombia (UTC-5) |
| Cantidades / precios | Números JSON (pueden ser decimales). En envío usar número, no string |
| IDs | Enteros. Excepciones: activos y órdenes de servicio usan además códigos (`000001`, `orderNumber`) como se indica por endpoint |
| Booleanos | `true` / `false` |
| Enums | Strings en minúsculas con guion bajo (`pending_signature`, `out_of_service`). Valores válidos en la columna *Reglas* (`in:...`) |
| Firma | Data URL PNG base64: `data:image/png;base64,....` (máx. ~512 KB) |

## 7. Permisos y control de acceso

Hay **tres capas independientes**; todas deben cumplirse:

1. **Permisos** — cada ruta exige un permiso (ver `Permiso requerido`). La app debe leer `user.permissions` (login / `auth/me`) y **ocultar** las acciones que no corresponden. El backend siempre valida; un `403` nunca debe ser el camino normal.
2. **Acceso a almacenes** — el usuario solo ve y opera sobre almacenes asignados (`GET /users/{id}/warehouses`). Los listados se filtran solos; operar sobre un almacén ajeno devuelve `403 "No tienes acceso al almacén indicado."`. El rol `super_administrador` ve todos.
3. **Acceso a sensores** — igual que almacenes, para monitoreo (`GET /users/{id}/sensors`).

**Menú dinámico**: `GET /api/v1/auth/menu` devuelve el árbol de navegación ya filtrado por permisos. Cada nodo:

```json
{
  "key": "service-orders",
  "label": "Generar Orden",
  "icon": "file-plus",
  "route": "service-orders.index",
  "permission": "ordenes_servicio.ver",
  "actions": { "create": true, "edit": false, "delete": false },
  "children": []
}
```
`actions` indica qué botones habilitar (crear / editar / eliminar / aprobar…). `route` es un identificador lógico del frontend web, no una URL; la app móvil debe mapearlo a sus propias pantallas. Se recomienda usar el menú como fuente de verdad de qué módulos mostrar.

## 8. Archivos

**Descargas** (se piden con el mismo Bearer; la respuesta es el archivo binario, no el envoltorio JSON):

| Endpoint | Contenido |
|---|---|
| `GET /movement-documents/{id}/pdf` | PDF del comprobante de movimiento |
| `GET /calibrations/{id}/download` | Certificado de calibración |
| `GET /assets/documents/{id}/download` | Documento del activo |
| `GET /assets/{assetId}/life-sheet` | Hoja de vida (PDF) |
| `GET /assets/transfers/{id}/act` | Acta de traslado |
| `GET /assets/maintenances/{id}/report` | Reporte de mantenimiento |
| `GET /reports/exports/{id}/download` | Exportación de reporte generada |
| `GET /generic-products/{id}/barcode` · `/barcode/print` | Código de barras (SVG / HTML imprimible) |
| `GET /import/templates/{entity}`, `/movements/initial-entries/template`, `/assets/import/template`, etc. | Plantillas Excel |

Para visualizar PDFs descargue el binario con el header `Authorization` (los enlaces directos sin header devuelven 401).

**Subidas** (multipart): importaciones masivas (`import/*`, `movements/initial-entries/import`, `assets/import`, `price-lists/import`), certificados de calibración (PDF/JPG/PNG), documentos de activos (PDF/JPG/PNG, máx. 10 MB). Los endpoints de importación son pensados para escritorio; en móvil su uso es opcional.

## 9. Recomendaciones de implementación

- **Capa de red única** (Retrofit/OkHttp, Alamofire/URLSession, Dio…) con interceptor que agregue headers y centralice 401 y el parseo de `{success, message, data, errors}`.
- **Idempotencia**: la API **no** soporta claves de idempotencia. En creaciones de movimientos/órdenes deshabilite el botón mientras la petición está en vuelo y, si hay timeout, **consulte antes de reintentar** (p. ej. `GET /movements` filtrado) para evitar duplicados.
- **Offline**: la API no ofrece sincronización diferencial. Cachee solo catálogos de lectura (productos, almacenes, ubicaciones) y vuelva a validar online antes de cualquier movimiento.
- **Tiempo de respuesta de reportes**: `GET /reports/*` pueden tardar; muestre indicador y use las exportaciones asíncronas (`reports/exports`) para volúmenes grandes.
- **Cámara**: lectura de códigos de barras (6 dígitos) y captura de firma en canvas → PNG base64 (≤512 KB; reduzca dimensiones si hace falta).
