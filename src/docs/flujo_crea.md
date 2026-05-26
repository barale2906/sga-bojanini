# Flujo: Registrar un Producto y Cargarlo en Inventario

> **Audiencia:** Equipo Frontend / Integradores  
> **Base URL:** `http://<host>/api/v1`  
> **Autenticación:** Bearer Token (Sanctum) — obligatorio en todos los endpoints salvo `/auth/login`

---

## Tabla de Contenido

1. [Visión General del Flujo](#1-visión-general-del-flujo)
2. [Paso 0 – Autenticación](#paso-0--autenticación)
3. [Paso 1 – Obtener datos maestros (categorías y unidades)](#paso-1--obtener-datos-maestros)
4. [Paso 2 – Crear el Producto](#paso-2--crear-el-producto)
5. [Paso 3 – Gestionar Presentaciones](#paso-3--gestionar-presentaciones)
6. [Paso 4 – Obtener Almacén y Ubicación](#paso-4--obtener-almacén-y-ubicación)
7. [Paso 5 – Registrar Entrada de Inventario](#paso-5--registrar-entrada-de-inventario)
8. [Paso 6 – Verificar Stock](#paso-6--verificar-stock)
9. [Mapa de Permisos](#mapa-de-permisos)
10. [Catálogo de Errores](#catálogo-de-errores)
11. [Diagrama de Secuencia](#diagrama-de-secuencia)

---

## 1. Visión General del Flujo

```
[Login] → [Cargar categorías + unidades] → [Crear Producto]
                                                  ↓
                                   [Gestionar Presentaciones] ← OBLIGATORIO
                                    (crear y/o asignar al producto)
                                                  ↓
              [Seleccionar Almacén + Ubicación] → [Registrar Entrada] → [Verificar Stock]
```

El flujo está dividido en **dos etapas principales**:

| Etapa | Módulo | Descripción |
|-------|--------|-------------|
| **Catálogo** | `Catalog` | Define qué es el producto (nombre, código, unidad, categoría…) y sus presentaciones |
| **Inventario** | `Inventory` | Registra físicamente cuánto hay, en qué lote y dónde |

> ### ⚠️ Cambio importante — Presentaciones (M:N)
>
> Las **presentaciones** (empaques) ahora son **entidades independientes** del catálogo.
> Un mismo tipo de empaque ("Caja × 100 UND") puede reutilizarse en varios productos.
>
> **Flujo correcto:**
> 1. Crear la presentación en el catálogo global → `POST /api/v1/presentations`
> 2. Asignarla al producto → `POST /api/v1/products/{productId}/presentations/{presentationId}`
>
> El campo `product_id` **ya no existe** en las presentaciones. El concepto de
> "presentación por defecto de compra" se configura al momento de **asignar** la
> presentación al producto (campo `is_purchase_default` en el body del `attach`).

---

## Paso 0 – Autenticación

Antes de cualquier llamada se debe obtener un Bearer Token.

### `POST /api/v1/auth/login`

**Request**
```json
{
  "email": "usuario@ejemplo.com",
  "password": "mi_contraseña"
}
```

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Login exitoso",
  "data": {
    "token": "1|abc123xyz...",
    "user": {
      "id": 1,
      "name": "Juan Pérez",
      "email": "usuario@ejemplo.com"
    }
  }
}
```

**Uso del token en todas las solicitudes siguientes:**
```
Authorization: Bearer 1|abc123xyz...
Accept: application/json
Content-Type: application/json
```

---

## Paso 1 – Obtener Datos Maestros

Antes de mostrar el formulario de creación de producto, el frontend debe cargar las listas de **categorías** y **unidades de medida** para poblar los selectores.

### 1a. Listar Categorías

**Permiso requerido:** `productos.ver`

#### `GET /api/v1/categories`

**Query params opcionales:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `is_active` | `boolean` | Filtrar solo activas (`1`) |
| `parent_id` | `integer` | Filtrar por categoría padre |
| `search` | `string` | Búsqueda por nombre o código |

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Listado de categorías",
  "data": [
    {
      "id": 1,
      "parent_id": null,
      "name": "Medicamentos",
      "code": "MED",
      "description": "Productos farmacéuticos",
      "is_active": true,
      "created_at": "2025-01-10T08:00:00+00:00"
    },
    {
      "id": 2,
      "parent_id": 1,
      "name": "Antibióticos",
      "code": "MED-ANT",
      "description": null,
      "is_active": true,
      "created_at": "2025-01-10T08:00:00+00:00"
    }
  ]
}
```

> **Tip:** Para obtener el árbol jerárquico completo usa `GET /api/v1/categories-tree`. Útil para mostrar un selector anidado (treeview).

---

### 1b. Listar Unidades de Medida

**Permiso requerido:** `productos.ver`

#### `GET /api/v1/units-of-measure`

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Listado de unidades de medida",
  "data": [
    {
      "id": 1,
      "name": "Unidad",
      "abbreviation": "UND",
      "is_active": true,
      "is_base": true,
      "created_at": "2025-01-10T08:00:00+00:00"
    },
    {
      "id": 2,
      "name": "Miligramo",
      "abbreviation": "mg",
      "is_active": true,
      "is_base": false,
      "created_at": "2025-01-10T08:00:00+00:00"
    }
  ]
}
```

---

## Paso 2 – Crear el Producto

**Permiso requerido:** `productos.crear`

### `POST /api/v1/products`

Este endpoint crea el producto en el catálogo. **No carga unidades al inventario**; eso se hace en el Paso 5. Una vez creado, el frontend debe continuar al **Paso 3** para gestionar las presentaciones antes de permitir cualquier entrada de inventario.

---

### 2a. Producto Simple

**Request Body**
```json
{
  "name": "Amoxicilina 500mg",
  "code": "AMOX-500",
  "sku": "7702001234560",
  "category_id": 2,
  "base_unit_id": 1,
  "product_type": "simple",
  "description": "Antibiótico de amplio espectro",
  "requires_cold_chain": false,
  "reorder_point": 50,
  "reorder_quantity": 200,
  "min_stock": 20,
  "max_stock": 1000
}
```

**Response `201 Created`**
```json
{
  "success": true,
  "message": "Producto creado exitosamente",
  "data": {
    "id": 10,
    "name": "Amoxicilina 500mg",
    "code": "AMOX-500",
    "sku": "7702001234560",
    "product_type": "simple",
    "category_id": 2,
    "base_unit_id": 1,
    "description": "Antibiótico de amplio espectro",
    "requires_cold_chain": false,
    "reorder_point": 50,
    "reorder_quantity": 200,
    "min_stock": 20,
    "max_stock": 1000,
    "is_active": true
  }
}
```

> ⚠️ **Guarda el `id` devuelto** — lo necesitarás en los pasos siguientes.

---

### 2b. Producto tipo Kit

Un Kit es un producto compuesto que **no tiene entradas directas de inventario**. Cuando se registra una salida de kit, el sistema descuenta automáticamente sus componentes.

**Request Body**
```json
{
  "name": "Kit Curación Básica",
  "code": "KIT-CUR-01",
  "category_id": 3,
  "base_unit_id": 1,
  "product_type": "kit",
  "description": "Kit para curación de heridas leves",
  "components": [
    { "component_product_id": 5, "quantity_per_kit": 2 },
    { "component_product_id": 8, "quantity_per_kit": 1 },
    { "component_product_id": 12, "quantity_per_kit": 3 }
  ]
}
```

**Campos de `components[]`:**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `component_product_id` | integer | ✅ | ID de un producto `simple` ya existente |
| `quantity_per_kit` | integer ≥ 1 | ✅ | Unidades del componente por cada kit |

---

### Validaciones del Endpoint `POST /api/v1/products`

| Campo | Tipo | Req. | Restricciones |
|-------|------|------|---------------|
| `name` | string | ✅ | max 255 chars |
| `code` | string | ✅ | max 50 chars, **único** en la tabla |
| `sku` | string | ❌ | max 100 chars, **único** si se envía |
| `category_id` | integer | ✅ | debe existir en `categories` |
| `base_unit_id` | integer | ✅ | debe existir en `units_of_measure` |
| `product_type` | string | ❌ | `"simple"` (default) o `"kit"` |
| `components` | array | ✅ si `product_type=kit` | ver estructura arriba |
| `description` | string | ❌ | — |
| `requires_cold_chain` | boolean | ❌ | default `false` |
| `reorder_point` | integer ≥ 0 | ❌ | default `0` |
| `reorder_quantity` | integer ≥ 0 | ❌ | default `0` |
| `min_stock` | integer ≥ 0 | ❌ | default `0` |
| `max_stock` | integer ≥ 0 | ❌ | default `0` |

---

## Paso 3 – Gestionar Presentaciones

> ⚠️ **Este paso es OBLIGATORIO** antes de registrar cualquier entrada de inventario.
> La presentación define la unidad de embalaje en la que el producto ingresa físicamente
> (ej: caja, blíster, frasco). Sin al menos una presentación asignada al producto, el
> formulario de entrada no debe habilitarse.

### Modelo de datos

Las presentaciones son **entidades independientes** del catálogo. Un mismo tipo de empaque
puede usarse en varios productos (relación **muchos a muchos**). El flujo es:

```
1. ¿Ya existe la presentación en el catálogo? → consultar GET /api/v1/presentations
      Sí → usar su id
      No → crearla con POST /api/v1/presentations

2. Asignar la presentación al producto → POST /api/v1/products/{productId}/presentations/{presentationId}
```

---

### 3a. Listar todas las presentaciones del catálogo

**Permiso requerido:** `productos.ver`

#### `GET /api/v1/presentations`

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Presentaciones",
  "data": [
    {
      "id": 1,
      "parent_id": null,
      "name": "Caja Maestra x5000",
      "code": "CJ-M5000",
      "units_of_measure_id": 3,
      "factor_to_base": 5000,
      "level": 1,
      "quantity_per_parent": null,
      "is_active": true,
      "sort_order": 1
    },
    {
      "id": 2,
      "parent_id": 1,
      "name": "Caja x500",
      "code": "CJ-500",
      "units_of_measure_id": 3,
      "factor_to_base": 500,
      "level": 2,
      "quantity_per_parent": 10,
      "is_active": true,
      "sort_order": 2
    }
  ]
}
```

> **Tip:** Para ver el árbol completo de jerarquías usa `GET /api/v1/presentations/tree`.

---

### 3b. Crear una nueva presentación

**Permiso requerido:** `productos.crear`

> Crear la presentación **no la vincula aún a ningún producto**. Después de crear,
> usa el endpoint de asignación (3c).

#### `POST /api/v1/presentations`

**Request Body**
```json
{
  "name": "Caja × 100",
  "code": "CAJ-100-UND",
  "units_of_measure_id": 3,
  "factor_to_base": 100,
  "level": 1,
  "quantity_per_parent": null,
  "sort_order": 1
}
```

| Campo | Tipo | Req. | Descripción |
|-------|------|------|-------------|
| `name` | string | ✅ | Nombre de la presentación |
| `code` | string | ✅ | Código único global (max 50) |
| `units_of_measure_id` | integer | ✅ | ID de la unidad de esta presentación |
| `factor_to_base` | integer ≥ 1 | ✅ | Cuántas unidades base equivale 1 de esta presentación |
| `level` | integer ≥ 1 | ✅ | Nivel en la jerarquía (1 = primer nivel sobre base) |
| `parent_id` | integer | ❌ | ID de presentación padre (para jerarquías multi-nivel) |
| `quantity_per_parent` | integer ≥ 1 | ❌ | Cuántas de esta presentación caben en el padre |
| `sort_order` | integer ≥ 0 | ❌ | Orden en listas |

**Response `201 Created`**
```json
{
  "success": true,
  "message": "Presentación creada",
  "data": {
    "id": 7,
    "parent_id": null,
    "name": "Caja × 100",
    "code": "CAJ-100-UND",
    "units_of_measure_id": 3,
    "factor_to_base": 100,
    "level": 1,
    "quantity_per_parent": null,
    "is_active": true,
    "sort_order": 1
  }
}
```

> ⚠️ **Nota:** el `id` devuelto aquí se usa en el siguiente paso para asignarla al producto.

---

### 3c. Asignar una presentación a un producto

**Permiso requerido:** `productos.editar`

#### `POST /api/v1/products/{productId}/presentations/{presentationId}`

**Request Body** *(todos los campos son opcionales)*
```json
{
  "is_purchase_default": true,
  "sort_order": 1
}
```

| Campo | Tipo | Req. | Descripción |
|-------|------|------|-------------|
| `is_purchase_default` | boolean | ❌ | `true` = marcar como presentación de compra por defecto para este producto |
| `sort_order` | integer ≥ 0 | ❌ | Orden de visualización en el contexto del producto |

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Presentación asignada al producto",
  "data": null
}
```

> **Restricciones de negocio:**
> - Los productos tipo `kit` **no admiten presentaciones de empaque**. El endpoint devolverá `409`.
> - Si `is_purchase_default: true`, el sistema automáticamente desmarca la presentación
>   anterior como default para ese producto.

---

### 3d. Listar presentaciones asignadas a un producto

**Permiso requerido:** `productos.ver`

#### `GET /api/v1/products/{productId}/presentations`

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Presentaciones del producto",
  "data": [
    {
      "id": 7,
      "parent_id": null,
      "name": "Caja × 100",
      "code": "CAJ-100-UND",
      "units_of_measure_id": 3,
      "factor_to_base": 100,
      "level": 1,
      "quantity_per_parent": null,
      "is_active": true,
      "sort_order": 1
    }
  ]
}
```

> **Tip:** Usa este endpoint para verificar si el producto ya tiene presentaciones asignadas
> antes de habilitar el botón de "Registrar Entrada".

---

### 3e. Desvincular una presentación de un producto

**Permiso requerido:** `productos.editar`

#### `DELETE /api/v1/products/{productId}/presentations/{presentationId}`

**Response `204 No Content`**

> La presentación **no se elimina del catálogo**; solo se desvincula de ese producto.

---

### 3f. Editar una presentación

**Permiso requerido:** `productos.editar`

#### `PUT /api/v1/presentations/{presentation}`

**Request Body** *(todos los campos son opcionales — PATCH semántico)*
```json
{
  "name": "Caja × 100 (actualizada)",
  "is_active": false
}
```

---

### 3g. Eliminar una presentación del catálogo

**Permiso requerido:** `productos.eliminar`

#### `DELETE /api/v1/presentations/{presentation}`

> ⚠️ Eliminar una presentación del catálogo la desvincula de **todos los productos** a los
> que estaba asignada. Úsalo con precaución.

**Response `204 No Content`**

---

### Jerarquías multi-nivel de presentaciones

Las presentaciones pueden anidarse en niveles (ej: Unidad → Caja → Caja Maestra):

| Campo | Significado |
|-------|-------------|
| `level = 1` | Presentación raíz (no tiene padre) |
| `level = 2` | Hija de una presentación nivel 1 |
| `parent_id` | ID de la presentación padre (debe existir) |
| `quantity_per_parent` | Cuántas de esta presentación caben en el padre |
| `factor_to_base` | Siempre igual a `padre.factor_to_base × quantity_per_parent` |

**Ejemplo: Aguja 21G**
```
Caja Maestra × 5000 UND (level=1, factor=5000)
  └─ Caja × 500 UND (level=2, factor=500, qty_per_parent=10)
       └─ Paquete × 100 UND (level=3, factor=100, qty_per_parent=5)
```

Estas tres presentaciones se crean **una sola vez** y pueden asignarse a cualquier producto
que las necesite (ej: también a "Gasa estéril 10x10").

**Endpoint de validación de jerarquía** (útil antes de enviar el `store`):
```
POST /api/v1/presentations/validate-hierarchy
Body: {
  "parent_id": 2,
  "factor_to_base": 100,
  "quantity_per_parent": 5,
  "level": 3
}
→ { "valid": true }
```

---

## Paso 4 – Obtener Almacén y Ubicación

Antes de registrar la entrada, el frontend necesita los IDs de **almacén** y **ubicación**.

**Permiso requerido:** `almacenes.ver` / `ubicaciones.ver`

### `GET /api/v1/warehouses`

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Listado de almacenes",
  "data": [
    {
      "id": 1,
      "name": "Bodega Central",
      "code": "BC-01",
      "address": "Calle 10 # 5-40",
      "description": null,
      "is_active": true,
      "created_at": "2025-01-10T08:00:00+00:00"
    }
  ]
}
```

### `GET /api/v1/warehouses/{id}/locations`

Obtener todas las ubicaciones de un almacén específico.

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Listado de ubicaciones",
  "data": [
    {
      "id": 3,
      "zone_id": 1,
      "name": "Estante A-01",
      "code": "EST-A01",
      "capacity": 500,
      "description": null,
      "is_active": true,
      "created_at": "2025-01-10T08:00:00+00:00"
    }
  ]
}
```

---

## Paso 5 – Registrar Entrada de Inventario

Con el producto creado, las presentaciones asignadas y los IDs de almacén/ubicación, se registra el ingreso físico al inventario.

**Permiso requerido:** `movimientos.entrada`

> ⚠️ **Restricción:** Los productos de tipo `kit` **no admiten entradas directas**. El sistema retornará un error `409`.

### `POST /api/v1/movements/entry`

---

### 5a. Entrada con Presentación (camino estándar del frontend)

El frontend siempre debe usar una presentación que esté **asignada al producto**. El sistema convierte automáticamente a la unidad base usando el `factor_to_base` de la presentación.

```json
{
  "product_id": 10,
  "warehouse_id": 1,
  "location_id": 3,
  "lot_number": "LOTE-2025-001",
  "expiration_date": "2027-06-30",
  "product_presentation_id": 7,
  "quantity_in_presentation": 5,
  "notes": "5 cajas × 100 tabletas = 500 tabletas"
}
```

> El sistema internamente calculará: `5 cajas × factor_to_base(100) = 500 unidades base`.

---

### 5b. Entrada con Unidad Base (uso interno / avanzado)

> ⚠️ **El frontend NO debe exponer esta opción en el flujo normal de registro.** Se reserva para ajustes técnicos o integraciones directas. Requiere que el operador conozca la cantidad exacta en unidades base.

```json
{
  "product_id": 10,
  "warehouse_id": 1,
  "location_id": 3,
  "lot_number": "LOTE-2025-001",
  "expiration_date": "2027-06-30",
  "manufacturing_date": "2025-01-15",
  "quantity_base": 500,
  "notes": "Ajuste directo en unidad base"
}
```

---

### Campos del Endpoint `POST /api/v1/movements/entry`

| Campo | Tipo | Req. | Restricciones |
|-------|------|------|---------------|
| `product_id` | integer | ✅ | debe existir; **no puede ser kit** |
| `warehouse_id` | integer | ✅ | debe existir en `warehouses` |
| `location_id` | integer | ✅ | debe existir en `locations` |
| `lot_number` | string | ✅ | max 100 chars; si ya existe para ese producto, **suma al lote** |
| `expiration_date` | date | ✅ | formato `YYYY-MM-DD` |
| `manufacturing_date` | date | ❌ | formato `YYYY-MM-DD` |
| `quantity_base` | integer ≥ 1 | ✅* | *requerido si no se usa presentación |
| `product_presentation_id` | integer | ✅* | *requerido si se usa `quantity_in_presentation`; **debe estar asignado al producto** |
| `quantity_in_presentation` | integer ≥ 1 | ✅* | *requerido si se usa `product_presentation_id` |
| `notes` | string | ❌ | Observaciones libres |

> ⚠️ **Regla:** Debe enviarse **`quantity_base`** OR **(`product_presentation_id` + `quantity_in_presentation`)**. Enviar ambas o ninguna retorna error `422`.

**Response `201 Created`**
```json
{
  "success": true,
  "message": "Entrada registrada exitosamente",
  "data": {
    "id": 55,
    "warehouse_id": 1,
    "product_id": 10,
    "batch_id": 4,
    "location_from_id": null,
    "location_to_id": 3,
    "movement_type": "entry",
    "quantity": 500,
    "reason": "Compra OC-2025-0042",
    "reference_type": null,
    "reference_id": null,
    "user_id": 1,
    "created_at": "2025-05-26T10:30:00+00:00",
    "product_name": "Amoxicilina 500mg",
    "batch_lot_number": "LOTE-2025-001",
    "user_name": "Juan Pérez"
  }
}
```

---

## Paso 6 – Verificar Stock

Después de registrar la entrada, confirmar que el stock fue actualizado.

**Permiso requerido:** `stock.ver`

### `GET /api/v1/stock?product_id={id}&warehouse_id={id}`

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Stock del producto",
  "data": [
    {
      "product_id": 10,
      "product_name": "Amoxicilina 500mg",
      "warehouse_id": 1,
      "warehouse_name": "Bodega Central",
      "total_available": 500,
      "batches_count": 1
    }
  ]
}
```

También se puede consultar directamente los lotes del producto:

### `GET /api/v1/products/{id}/batches`

**Response `200 OK`**
```json
{
  "success": true,
  "message": "Lotes del producto",
  "data": [
    {
      "id": 4,
      "product_id": 10,
      "lot_number": "LOTE-2025-001",
      "expiration_date": "2027-06-30",
      "manufacturing_date": "2025-01-15",
      "quantity_received": 500,
      "quantity_available": 500,
      "status": "active",
      "notes": "Compra OC-2025-0042",
      "received_at": "2025-05-26T10:30:00+00:00"
    }
  ]
}
```

---

## Mapa de Permisos

| Acción | Permiso requerido |
|--------|------------------|
| Ver categorías / unidades / productos | `productos.ver` |
| Ver presentaciones (catálogo) | `productos.ver` |
| Ver presentaciones de un producto | `productos.ver` |
| Crear producto | `productos.crear` |
| Crear presentación (catálogo) | `productos.crear` |
| Editar producto | `productos.editar` |
| Asignar / desvincular presentación a producto | `productos.editar` |
| Editar presentación | `productos.editar` |
| Eliminar producto | `productos.eliminar` |
| Eliminar presentación del catálogo | `productos.eliminar` |
| Ver almacenes | `almacenes.ver` |
| Ver ubicaciones | `ubicaciones.ver` |
| Registrar entrada de inventario | `movimientos.entrada` |
| Ver stock | `stock.ver` |
| Ver lotes | `lotes.ver` |

---

## Catálogo de Errores

### Errores HTTP estándar

| Código | Cuándo ocurre |
|--------|---------------|
| `401 Unauthorized` | Token no enviado, expirado o inválido |
| `403 Forbidden` | El usuario autenticado no tiene el permiso requerido |
| `404 Not Found` | El recurso (producto, categoría, lote…) no existe |
| `405 Method Not Allowed` | Método HTTP incorrecto para la ruta |
| `422 Unprocessable Entity` | Error de validación de campos |
| `429 Too Many Requests` | Rate limit alcanzado |
| `409 Conflict` | Regla de negocio violada (DomainException) |
| `500 Internal Server Error` | Error inesperado del servidor |

---

### Estructura de respuesta de error

**Error de validación `422`**
```json
{
  "success": false,
  "message": "Error de validación.",
  "errors": {
    "code": ["Ya existe un producto con este código."],
    "base_unit_id": ["La unidad de medida base es obligatoria."]
  }
}
```

**Error de dominio / negocio `409`**
```json
{
  "success": false,
  "message": "No se pueden registrar entradas directas de productos tipo kit."
}
```

**Sin autenticación `401`**
```json
{
  "success": false,
  "message": "No autenticado. Envía un token válido en el header Authorization."
}
```

**Sin permiso `403`**
```json
{
  "success": false,
  "message": "No tienes permiso para realizar esta acción."
}
```

---

### Errores específicos por paso

#### Al crear el producto (`POST /api/v1/products`)

| Error | Código | Mensaje |
|-------|--------|---------|
| `code` duplicado | `422` | `Ya existe un producto con este código.` |
| `sku` duplicado | `422` | `Ya existe un producto con este SKU.` |
| `category_id` inexistente | `422` | `La categoría seleccionada no existe.` |
| `base_unit_id` inexistente | `422` | `La unidad de medida base seleccionada no existe.` |
| `product_type` inválido | `422` | `El tipo de producto debe ser simple o kit.` |
| Kit sin componentes | `422` | `The components field is required when product type is kit.` |
| `components[].component_product_id` inexistente | `422` | Validación de FK |

#### Al crear una presentación (`POST /api/v1/presentations`)

| Error | Código | Mensaje |
|-------|--------|---------|
| `code` duplicado en el catálogo global | `422` | `The code has already been taken.` |
| `parent_id` inexistente | `422` | Validación de FK |
| `level` de raíz distinto de 1 | `409` | `Las presentaciones raíz deben tener level = 1.` |
| `factor_to_base` no coincide con `padre × qty` | `409` | `factor_to_base debe ser N (padre × quantity_per_parent).` |

#### Al asignar presentación a producto (`POST /api/v1/products/{productId}/presentations/{presentationId}`)

| Error | Código | Mensaje |
|-------|--------|---------|
| Producto no existe | `409` | `Producto no encontrado.` |
| Producto es kit | `409` | `Los kits no admiten presentaciones de empaque.` |
| Presentación no existe | `409` | `Presentación no encontrada.` |

#### Al registrar entrada (`POST /api/v1/movements/entry`)

| Error | Código | Mensaje |
|-------|--------|---------|
| Producto es kit | `409` | `No se pueden registrar entradas directas de productos tipo kit.` |
| Producto no existe | `422` | Validación de FK en `product_id` |
| Presentación no asignada al producto | `409` | `La presentación no está asignada al producto (línea 0).` |
| Ni `quantity_base` ni presentación enviada | `422` | `Debe indicar quantity_base o product_presentation_id con quantity_in_presentation.` |
| `quantity_base` < 1 | `422` | Validación `min:1` |
| `expiration_date` inválida | `422` | Validación `date` |

---

## Diagrama de Secuencia

```
Frontend                    API (Back)                   BD
   |                           |                          |
   |-- POST /auth/login -----> |                          |
   |<-- 200 { token } -------- |                          |
   |                           |                          |
   |-- GET /categories ------> |-- SELECT categories ---> |
   |<-- 200 [categorías] ----- |<-- rows ---------------- |
   |                           |                          |
   |-- GET /units-of-measure ->|-- SELECT units ----------|
   |<-- 200 [unidades] ------- |<-- rows ---------------- |
   |                           |                          |
   | [Usuario llena formulario]|                          |
   |                           |                          |
   |-- POST /products -------> |-- INSERT products -----> |
   |<-- 201 { id: 10 } ------- |<-- product_id ---------- |
   |                           |                          |
   | [OBLIGATORIO: al menos    |                          |
   |  una presentación]        |                          |
   |                           |                          |
   | -- GET /presentations --> |-- SELECT presentations ->|
   |<-- 200 [presentaciones] - |<-- rows ---------------- |
   |  (¿ya existe la necesaria?|                          |
   |   si no, crear primero)   |                          |
   |                           |                          |
   |-- POST /presentations --> |-- INSERT presentations ->|
   |<-- 201 { id: 7 } -------- |<-- presentation_id ----- |
   |                           |                          |
   |-- POST /products/10/      |                          |
   |   presentations/7 ------> |-- INSERT product_        |
   |                           |   presentation (pivot)-> |
   |<-- 200 OK --------------- |                          |
   |                           |                          |
   |-- GET /warehouses ------> |-- SELECT warehouses ---> |
   |<-- 200 [almacenes] ------ |<-- rows ---------------- |
   |                           |                          |
   |-- GET /warehouses/1/      |                          |
   |   locations ------------> |-- SELECT locations -----> |
   |<-- 200 [ubicaciones] ---- |<-- rows ---------------- |
   |                           |                          |
   | [Usuario selecciona       |                          |
   |  almacén, ubicación,      |                          |
   |  lote, fecha, cantidad]   |                          |
   |                           |                          |
   |-- POST /movements/entry ->|-- BEGIN TRANSACTION ---> |
   |                           |-- UPSERT batches ------> |
   |                           |-- UPSERT batch_location->|
   |                           |-- INSERT movements -----> |
   |                           |-- COMMIT ---------------- |
   |<-- 201 { movement } ----- |                          |
   |                           |                          |
   |-- GET /stock?product_id=10|-- SELECT stock view ---> |
   |<-- 200 { stock } -------- |<-- aggregated rows ----- |
```

---

## Notas para el Frontend

### 1. Manejo de lotes duplicados
Si se registra una entrada con el mismo `lot_number` para el mismo `product_id`, el sistema **no rechaza ni duplica el lote**, sino que **suma la cantidad** al lote existente. El frontend puede informarlo como "acumulación de lote".

### 2. Producto tipo Kit
- Los kits **no aceptan** `POST /movements/entry` → mostrar esa opción deshabilitada o no renderizarla cuando `product_type === "kit"`.
- Los kits tampoco aceptan asignación de presentaciones.
- Para consultar cuántos kits se pueden armar, usar `GET /api/v1/products/{id}/kit-availability?warehouse_id={id}` (requiere permiso `stock.ver`).

### 3. Factor de conversión (previsualización antes de confirmar entrada)
Antes de enviar la entrada, el frontend puede mostrar al usuario cuántas unidades base equivale la cantidad que escribió:
```
POST /api/v1/presentations/convert-to-base
Body: { "presentation_id": 7, "quantity": 5 }
→ { "quantity_base": 500 }
```
Útil para un mensaje de confirmación tipo: *"Estás ingresando 5 Cajas × 100 = 500 tabletas"*.

### 4. Reutilización de presentaciones entre productos
Si ya existen presentaciones en el catálogo que aplican a un nuevo producto, **no es necesario crearlas de nuevo**; solo asignarlas. Por ejemplo, si "Caja × 100 UND" ya existe con `id: 7`, basta con:
```
POST /api/v1/products/15/presentations/7
Body: { "is_purchase_default": true, "sort_order": 1 }
```

### 5. Orden obligatorio de llamadas en el formulario de creación
```
1. (Al montar) GET /categories-tree          → árbol para selector de categoría
2. (Al montar) GET /units-of-measure         → lista para selector de unidad base
3. (Al submit producto) POST /products       → crea el producto → guarda el id

4a. (Al iniciar paso presentaciones)
    GET /presentations                       → listar presentaciones existentes del catálogo
4b. (Si ninguna aplica) POST /presentations  → crear nueva presentación en el catálogo
4c. (Obligatorio) POST /products/{id}/presentations/{presId}
                                             → asignar al producto; repetir para cada
                                               empaque que maneje el producto

5. (Al montar form de entrada) GET /warehouses
6. (Al seleccionar almacén) GET /warehouses/{id}/locations
7. (Al submit entrada) POST /movements/entry → usar product_presentation_id
8. (Confirmación) GET /products/{id}/batches → mostrar lote creado y stock disponible
```

> ⚠️ **El botón / paso de "Registrar Entrada" debe permanecer deshabilitado hasta que el
> producto tenga al menos una presentación asignada.** El frontend puede verificarlo con
> `GET /api/v1/products/{id}/presentations` antes de habilitar el formulario de entrada.

### 6. Autenticación y usuario activo
Todos los endpoints requieren:
- `Authorization: Bearer {token}` — token obtenido en `/auth/login`
- El usuario debe estar **activo** (`is_active = true`) — si no, recibirá `403`

---

*Actualizado el 2026-05-26 — Refactoring M:N en presentaciones (product_presentation pivot).*
