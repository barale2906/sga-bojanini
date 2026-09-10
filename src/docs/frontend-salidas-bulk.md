# Guía Frontend — Registro de salidas de inventario

**Fecha:** 2026-09-10

> Guía complementaria a `frontend-entradas-bulk.md`. Cubre los cinco tipos de salida:
> **salida normal**, **traslado**, **baja por vencimiento**, **merma** y **devolución**.
>
> Los dos primeros aceptan **varios productos en un solo request** (`items[]`).
> Los tres últimos son de un movimiento por request.

---

## Resumen de endpoints

| Tipo | Endpoint | Permiso | ¿Bulk? |
|------|----------|---------|--------|
| Salida normal | `POST /api/v1/movements/exit` | `movimientos.salida` | ✅ `items[]` |
| Traslado entre almacenes | `POST /api/v1/movements/transfer` | `movimientos.transferir` | ✅ `items[]` |
| Baja por vencimiento | `POST /api/v1/movements/write-off` | `movimientos.baja` | ❌ uno por request |
| Merma / pérdida | `POST /api/v1/movements/loss` | `movimientos.baja` | ❌ uno por request |
| Devolución | `POST /api/v1/movements/return` | `movimientos.devolucion` | ❌ uno por request |
| Ajuste de inventario | `POST /api/v1/movements/adjustment` | `movimientos.ajuste` | ❌ uno por request |

---

## 1. Salida normal

```
POST /api/v1/movements/exit
Authorization: Bearer {token}
Permiso: movimientos.salida
```

### Campos de cabecera (nivel raíz)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `warehouse_id` | integer | ✅ | Almacén origen. El usuario debe tener acceso. |
| `cost_center_id` | integer | ✅ | Centro de costo al que se carga la salida. |
| `movement_date` | date | — | Fecha del movimiento (`YYYY-MM-DD`). Máx: hoy. Default: ahora. |
| `service_id` | integer | — | Servicio médico vinculado (dentro del centro de costo). |
| `patient_document` | string | — | Documento del paciente. Máx. 50 caracteres. |
| `patient_external_id` | string | — | ID externo del paciente (HIS/MedSys). Máx. 100 caracteres. |
| `reason` | string | — | Observación general de la salida. |
| `items` | array | ✅ min:1 | Lista de productos a despachar. |

### Campos por ítem (`items[]`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `generic_product_id` | integer | ✅ | ID del producto genérico. |
| `batch_id` | integer | — | ID del lote específico a despachar. Si se envía, el backend usa **ese lote exacto** (no aplica FEFO). Si se omite, FEFO elige el lote automáticamente. |
| `quantity` | number | ✅ | Cantidad en unidad base a sacar del lote (o que el FEFO debe cubrir). Mín: 0.001. |
| `location_id` | integer | — | Ubicación/estante de origen. Si se omite, el backend usa el stock disponible en cualquier ubicación. |

> **Estrategia recomendada para el frontend:**
> 1. Consultar los lotes disponibles del producto ordenados por fecha de vencimiento (FEFO).
> 2. Mostrar los lotes al usuario con su stock y fecha de vencimiento.
> 3. El usuario ajusta cuánto saca de cada lote.
> 4. Enviar **un ítem por lote** con `batch_id` + `quantity`.
>
> Si el frontend envía `batch_id` el backend valida que:
> - El lote pertenece al `generic_product_id` indicado.
> - El lote tiene suficiente `quantity_available`.
>
> Si NO se envía `batch_id`, FEFO selecciona automáticamente y puede descontar de varios lotes (uno por línea de movimiento).

### Ejemplo A — Lotes seleccionados por el usuario (recomendado)

Dos lotes del mismo producto, cantidades elegidas manualmente:

```json
{
  "warehouse_id":   1,
  "cost_center_id": 3,
  "service_id":     12,
  "patient_document": "10234567",
  "movement_date":  "2026-09-10",
  "reason":         "Despacho cirugía programada",

  "items": [
    {
      "generic_product_id": 8,
      "batch_id":           101,
      "quantity":           3,
      "location_id":        4
    },
    {
      "generic_product_id": 8,
      "batch_id":           102,
      "quantity":           2,
      "location_id":        4
    },
    {
      "generic_product_id": 15,
      "batch_id":           87,
      "quantity":           1
    }
  ]
}
```

> Enviar los ítems ordenados de menor a mayor fecha de vencimiento para que los movimientos de la respuesta queden en orden FEFO.

### Ejemplo B — FEFO automático (el backend elige el/los lote/s)

```json
{
  "warehouse_id":   1,
  "cost_center_id": 3,
  "movement_date":  "2026-09-10",

  "items": [
    {
      "generic_product_id": 8,
      "quantity":           5,
      "location_id":        4
    },
    {
      "generic_product_id": 15,
      "quantity":           2
    }
  ]
}
```

### Respuesta (201 Created)

Devuelve un `MovementDocumentResource` idéntico al de entradas. El campo `movements[]` lista el detalle de cada línea despachada.

```json
{
  "success": true,
  "message": "Salida registrada exitosamente",
  "data": {
    "id":              2210,
    "document_number": "SAL-20260910-000031",
    "document_type":   "exit",
    "warehouse_id":    1,
    "warehouse_name":  "Bodega Norte",
    "cost_center":     { "id": 3, "code": "CC-003", "name": "Cirugía", "type": "internal" },
    "medical_service": { "id": 12, "code": "SRV-012", "name": "Cirugía General" },
    "patient_document": "10234567",
    "movement_date":   "2026-09-10T00:00:00+00:00",
    "status":          "confirmed",
    "user_id":         5,
    "user_name":       "Admin",
    "created_at":      "2026-09-10T09:15:00+00:00",
    "movements": [
      {
        "id":                 4501,
        "product_variant_id": 42,
        "movement_type":      "exit",
        "quantity":           5,
        "batch_id":           88
      },
      {
        "id":                 4502,
        "product_variant_id": 61,
        "movement_type":      "exit",
        "quantity":           2,
        "batch_id":           91
      }
    ]
  }
}
```

### Errores frecuentes

| HTTP | Causa |
|------|-------|
| 403 | Sin permiso `movimientos.salida` o sin acceso al almacén |
| 422 | `cost_center_id` ausente, producto inexistente, cantidad ≤ 0 |
| 422 | `batch_id` no existe en la base de datos |
| 409 | El `batch_id` enviado no pertenece al `generic_product_id` indicado |
| 409 | El lote especificado no tiene suficiente stock (`quantity_available` < `quantity`) |
| 409 | FEFO no encuentra stock suficiente para cubrir la cantidad (cuando no se envía `batch_id`) |

---

## 2. Traslado entre almacenes

```
POST /api/v1/movements/transfer
Authorization: Bearer {token}
Permiso: movimientos.transferir
```

El usuario debe tener acceso **tanto al almacén origen como al destino**.

### Campos de cabecera (nivel raíz)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `warehouse_from_id` | integer | ✅ | Almacén origen. |
| `warehouse_to_id` | integer | ✅ | Almacén destino (puede ser el mismo que el origen si las ubicaciones difieren). |
| `movement_date` | date | — | Fecha del movimiento (`YYYY-MM-DD`). Máx: hoy. |
| `reason` | string | — | Motivo del traslado. |
| `items` | array | ✅ min:1 | Lista de variantes a trasladar. |

### Campos por ítem (`items[]`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `product_variant_id` | integer | ✅ | ID de la variante específica a mover. |
| `batch_id` | integer | — | Lote específico a trasladar. Si se omite, FEFO elige automáticamente. |
| `location_from_id` | integer | — | Preferencia de ubicación de origen. Se respeta si el lote tiene stock allí; si no, el backend usa la ubicación con mayor stock del lote. |
| `location_to_id` | integer | ✅ | Ubicación de destino (debe pertenecer a `warehouse_to_id`). |
| `quantity` | number | ✅ | Cantidad en unidad base. Mín: 0.001. |

> **Selección de lote:** si se envía `batch_id`, el backend valida que pertenezca a `product_variant_id` y que haya suficiente stock en el almacén de origen. Si no se envía, FEFO elige el lote más próximo a vencer.
>
> **Validaciones cruzadas:**
> - Si `warehouse_from_id === warehouse_to_id` y se envía `location_from_id`, el backend verifica que origen ≠ destino.
> - Si se envía `location_from_id`, debe pertenecer a `warehouse_from_id`.
> - `location_to_id` siempre debe pertenecer a `warehouse_to_id`.
> - Los errores apuntan al ítem exacto: `items.N.location_from_id` o `items.N.location_to_id`.

### Ejemplo de request

```json
{
  "warehouse_from_id": 1,
  "warehouse_to_id":   2,
  "movement_date":     "2026-09-10",
  "reason":            "Reabastecimiento sucursal sur",

  "items": [
    {
      "product_variant_id": 42,
      "location_from_id":   4,
      "location_to_id":     11,
      "quantity":           100
    },
    {
      "product_variant_id": 55,
      "location_from_id":   4,
      "location_to_id":     12,
      "quantity":           50
    }
  ]
}
```

### Respuesta (201 Created)

`MovementDocumentResource` con `document_type: "transfer"` y `warehouse_to_id` / `warehouse_to_name` poblados.

```json
{
  "success": true,
  "message": "Traslado registrado exitosamente",
  "data": {
    "id":                2211,
    "document_number":   "TRA-20260910-000008",
    "document_type":     "transfer",
    "warehouse_id":      1,
    "warehouse_name":    "Bodega Norte",
    "warehouse_to_id":   2,
    "warehouse_to_name": "Bodega Sur",
    "movement_date":     "2026-09-10T00:00:00+00:00",
    "status":            "confirmed",
    "movements": [
      {
        "id":                 4503,
        "product_variant_id": 42,
        "movement_type":      "transfer",
        "quantity":           100,
        "location_from_id":   4,
        "location_to_id":     11
      },
      {
        "id":                 4504,
        "product_variant_id": 55,
        "movement_type":      "transfer",
        "quantity":           50,
        "location_from_id":   4,
        "location_to_id":     12
      }
    ]
  }
}
```

### Errores frecuentes

| HTTP | Causa |
|------|-------|
| 403 | Sin acceso a `warehouse_from_id` o `warehouse_to_id` |
| 422 | Misma ubicación origen y destino dentro del mismo almacén |
| 422 | Ubicación no pertenece al almacén indicado (`items.N.location_from_id`) |

---

## 3. Baja por vencimiento

```
POST /api/v1/movements/write-off
Authorization: Bearer {token}
Permiso: movimientos.baja
```

Da de baja **un lote completo** (o su stock en una ubicación). **Un request por lote.**

### Campos (nivel raíz — sin `items[]`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `batch_id` | integer | ✅ | ID del lote a dar de baja. |
| `warehouse_id` | integer | ✅ | Almacén donde reside el lote. |
| `location_id` | integer | — | Ubicación específica dentro del almacén. |
| `reason` | string | — | Motivo de la baja (ej. "Lote vencido", "Contaminado"). |

### Ejemplo de request

```json
{
  "batch_id":    88,
  "warehouse_id": 1,
  "location_id":  4,
  "reason":       "Lote vencido — temperatura fuera de rango"
}
```

### Respuesta (201 Created)

Devuelve un `MovementResource` (movimiento individual).

```json
{
  "success": true,
  "message": "Baja por vencimiento registrada exitosamente",
  "data": {
    "id":                 4510,
    "movement_document_id": 2215,
    "warehouse_id":       1,
    "product_variant_id": 42,
    "batch_id":           88,
    "location_from_id":   4,
    "movement_type":      "write_off",
    "quantity":           150,
    "reason":             "Lote vencido — temperatura fuera de rango",
    "movement_date":      "2026-09-10T09:30:00+00:00",
    "status":             "confirmed",
    "user_id":            5,
    "user_name":          "Admin",
    "product_name":       "Amoxicilina 500mg",
    "batch_lot_number":   "LOT-2025-088",
    "batch_expiration_date": "2026-08-31"
  }
}
```

> **Para dar de baja varios lotes:** envía una petición independiente por cada `batch_id`. No hay endpoint bulk para write-off.

---

## 4. Merma / pérdida de inventario

```
POST /api/v1/movements/loss
Authorization: Bearer {token}
Permiso: movimientos.baja
```

Registra la pérdida **parcial o total** de unidades de un lote (rotura, derrame, hurto, etc.). **Un request por movimiento.**

### Campos (nivel raíz — sin `items[]`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `product_variant_id` | integer | ✅ | ID de la variante afectada. |
| `warehouse_id` | integer | ✅ | Almacén donde ocurrió la pérdida. |
| `location_id` | integer | ✅ | Ubicación específica dentro del almacén. |
| `batch_id` | integer | ✅ | Lote del que se descuenta. Debe pertenecer a `product_variant_id`. |
| `quantity` | number | ✅ | Cantidad perdida en unidad base. Mín: 0.001. |
| `reason` | string | ✅ | Descripción de la causa de la pérdida (obligatorio). |

### Ejemplo de request

```json
{
  "product_variant_id": 42,
  "warehouse_id":       1,
  "location_id":        4,
  "batch_id":           88,
  "quantity":           12,
  "reason":             "Rotura de frascos durante reubicación"
}
```

### Respuesta (201 Created)

Devuelve un `MovementResource` (movimiento individual).

```json
{
  "success": true,
  "message": "Baja de inventario registrada exitosamente",
  "data": {
    "id":                 4511,
    "movement_document_id": 2216,
    "warehouse_id":       1,
    "product_variant_id": 42,
    "batch_id":           88,
    "location_from_id":   4,
    "movement_type":      "loss",
    "quantity":           12,
    "reason":             "Rotura de frascos durante reubicación",
    "movement_date":      "2026-09-10T10:00:00+00:00",
    "status":             "confirmed",
    "user_id":            5,
    "product_name":       "Amoxicilina 500mg",
    "batch_lot_number":   "LOT-2025-088"
  }
}
```

### Errores frecuentes

| HTTP | Causa |
|------|-------|
| 422 | `reason` ausente (es obligatorio en merma) |
| 422 | El `batch_id` no pertenece a `product_variant_id` |
| 422 | Stock insuficiente en el lote/ubicación indicada |

---

## 5. Devolución

```
POST /api/v1/movements/return
Authorization: Bearer {token}
Permiso: movimientos.devolucion
```

Reingresa unidades al almacén (p. ej. devolución de paciente o de quirófano). **Un request por movimiento.**

### Campos (nivel raíz — sin `items[]`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `product_variant_id` | integer | ✅ | ID de la variante que se devuelve. |
| `warehouse_id` | integer | ✅ | Almacén que recibe la devolución. |
| `location_id` | integer | — | Ubicación de origen dentro del almacén. |
| `batch_id` | integer | — | Lote específico a devolver. Si se omite, FEFO elige el más próximo a vencer (incluye vencidos). |
| `quantity` | number | ✅ | Cantidad devuelta en unidad base. Mín: 0.001. |
| `reason` | string | — | Motivo de la devolución. |

### Ejemplo de request

```json
{
  "product_variant_id": 42,
  "warehouse_id":       1,
  "location_id":        4,
  "batch_id":           88,
  "quantity":           3,
  "reason":             "No se usó en procedimiento"
}
```

### Respuesta (201 Created)

Devuelve un `MovementResource` (movimiento individual).

```json
{
  "success": true,
  "message": "Devolución registrada exitosamente",
  "data": {
    "id":                 4512,
    "movement_document_id": 2217,
    "warehouse_id":       1,
    "product_variant_id": 42,
    "location_to_id":     4,
    "movement_type":      "return",
    "quantity":           3,
    "reason":             "No se usó en procedimiento",
    "movement_date":      "2026-09-10T10:30:00+00:00",
    "status":             "confirmed",
    "user_id":            5,
    "product_name":       "Amoxicilina 500mg"
  }
}
```

---

## 6. Ajuste de inventario

```
POST /api/v1/movements/adjustment
Authorization: Bearer {token}
Permiso: movimientos.ajuste
```

Aumenta o disminuye el stock de una variante en una ubicación. **Un request por movimiento.**

- `quantity > 0` → ajuste positivo (aumenta stock).
- `quantity < 0` → ajuste negativo (disminuye stock).

### Campos (nivel raíz — sin `items[]`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `product_variant_id` | integer | ✅ | ID de la variante a ajustar. |
| `warehouse_id` | integer | ✅ | Almacén donde se realiza el ajuste. |
| `location_id` | integer | ✅ | Ubicación específica dentro del almacén. |
| `batch_id` | integer | — | Lote específico a ajustar. Si se omite: para negativos FEFO elige el lote; para positivos se elige el lote activo más próximo a vencer. |
| `quantity` | number | ✅ | Cantidad del ajuste (positiva o negativa, ≠ 0). |
| `reason` | string | ✅ | Motivo del ajuste (obligatorio). |

> **Selección de lote:**
> - Con `batch_id` explícito: el backend valida que pertenezca a la variante y (para negativos) que haya stock suficiente.
> - Sin `batch_id` en ajuste negativo: FEFO selecciona el lote más próximo a vencer (incluye vencidos).
> - Sin `batch_id` en ajuste positivo: se usa el primer lote activo disponible.

### Ejemplo — Ajuste negativo con lote explícito

```json
{
  "product_variant_id": 42,
  "warehouse_id":       1,
  "location_id":        4,
  "batch_id":           88,
  "quantity":           -10,
  "reason":             "Conteo físico: diferencia detectada en bodega"
}
```

### Ejemplo — Ajuste positivo sin lote (FEFO / primer activo)

```json
{
  "product_variant_id": 42,
  "warehouse_id":       1,
  "location_id":        4,
  "quantity":           5,
  "reason":             "Corrección por error de digitación en entrada"
}
```

### Respuesta (201 Created)

Devuelve un `MovementResource` (movimiento individual). El `quantity` refleja el valor enviado (positivo o negativo).

### Errores frecuentes

| HTTP | Causa |
|------|-------|
| 422 | `reason` ausente, `quantity` igual a cero, variante o almacén no existen |
| 409 | `batch_id` no pertenece a `product_variant_id` |
| 409 | Ajuste negativo con `batch_id` sin stock suficiente |
| 409 | Ajuste negativo sin `batch_id` y FEFO no encuentra stock suficiente |

---

## Diferencias clave respecto a entradas

| Aspecto | Entrada | Salida normal | Traslado | Baja / Merma | Devolución | Ajuste |
|---------|---------|---------------|----------|--------------|------------|--------|
| Identificador de producto | `product_variant_id` | `generic_product_id` | `product_variant_id` | `product_variant_id` | `product_variant_id` | `product_variant_id` |
| Selección de lote | Manual (`lot_number`) | FEFO o explícito (`batch_id`) | FEFO o explícito (`batch_id`) | `batch_id` requerido | FEFO o explícito (`batch_id`) | FEFO o explícito (`batch_id`) |
| Centro de costo | — | ✅ Obligatorio | — | — | — | — |
| Bulk (`items[]`) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Tipo de respuesta | `MovementDocumentResource` | `MovementDocumentResource` | `MovementDocumentResource` | `MovementResource` | `MovementResource` | `MovementResource` |

---

## Notas generales

- Todos los endpoints son **atómicos** para los que usan `items[]`: si un ítem falla, ningún movimiento se confirma.
- Los endpoints de baja y devolución no tienen `items[]`, por lo que para procesar varios productos el frontend debe encolar varias peticiones secuenciales (o en paralelo si el orden no importa).
- Los errores de validación de ítems incluyen el índice: `items.0.quantity`, `items.2.location_from_id`, etc.
- El campo `status` en la respuesta será `"confirmed"` cuando el movimiento se aplica de inmediato, o `"pending_signature"` si el documento requiere firmas antes de confirmar.
