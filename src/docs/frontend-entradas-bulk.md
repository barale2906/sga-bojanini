# Guía Frontend — Registro de entradas con múltiples productos

**Fecha:** 2026-09-10 · **Endpoint:** `POST /api/v1/movements/entry`

> La guía anterior (`guia_entradas.md`) describe el contrato viejo donde se enviaba un producto por request.
> El endpoint actual acepta **varios productos en un solo llamado**, todos al mismo almacén.

---

## Qué cambió respecto al contrato anterior

```
- Antes: product_id, location_id, lot_number, quantity_base… al nivel raíz. Un producto por request.
+ Ahora: items[] con todos esos campos. Varios productos en un solo request.
```

Los campos de cabecera (`warehouse_id`, `invoice_number`, `entry_temperature`, etc.) siguen al nivel raíz y aplican a toda la entrada.

---

## Endpoint

```
POST /api/v1/movements/entry
Authorization: Bearer {token}
Permiso: movimientos.entrada
```

---

## Body

### Campos de cabecera (nivel raíz)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `warehouse_id` | integer | ✅ | Almacén destino. El usuario debe tener acceso a él. |
| `movement_date` | date | — | Fecha del movimiento (`YYYY-MM-DD`). Máx: hoy. Default: ahora. |
| `invoice_number` | string | — | Número de factura del proveedor. Máx. 100 caracteres. |
| `entry_temperature` | number | — | Temperatura de recepción en °C (admite negativos y decimales). |
| `reason` | string | — | Observación general de la entrada. |
| `items` | array | ✅ min:1 | Lista de productos a ingresar. |

### Campos por ítem (`items[]`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `product_variant_id` | integer | ✅ | ID de la variante del producto. |
| `location_id` | integer | ✅ | ID de la ubicación/estante dentro del almacén. |
| `lot_number` | string | ✅ | Número de lote. Máx. 100 caracteres. |
| `expiration_date` | date | ✅ | Fecha de vencimiento (`YYYY-MM-DD`). |
| `manufacturing_date` | date | — | Fecha de fabricación (`YYYY-MM-DD`). |
| `quantity_base` | number | ⚠️ ver nota | Cantidad en unidad base. Mín: 0.001. |
| `product_presentation_id` | integer | ⚠️ ver nota | ID de presentación de compra (caja, ampolla, etc.). |
| `quantity_in_presentation` | number | ⚠️ ver nota | Cantidad en la presentación indicada. Mín: 0.001. |
| `notes` | string | — | Observación del ítem. |

> **⚠️ Cantidad (obligatoria por ítem):** se debe enviar **una de las dos opciones**:
> - Opción A — `quantity_base`: cantidad directa en unidad base.
> - Opción B — `product_presentation_id` + `quantity_in_presentation`: el backend convierte a unidad base automáticamente.
>
> Si no se envía ninguna de las dos, el ítem falla con error de validación.

---

## Ejemplo de request

```json
{
  "warehouse_id":      1,
  "movement_date":     "2026-09-10",
  "invoice_number":    "FAC-2026-00892",
  "entry_temperature": 4.5,
  "reason":            "Recepción bodega norte",

  "items": [
    {
      "product_variant_id": 42,
      "location_id":        7,
      "lot_number":         "LOT-2026-001",
      "expiration_date":    "2027-06-30",
      "manufacturing_date": "2026-01-15",
      "quantity_base":      200,
      "notes":              "Revisar temperatura en 48h"
    },
    {
      "product_variant_id":      55,
      "location_id":             8,
      "lot_number":              "LOT-2026-002",
      "expiration_date":         "2028-03-31",
      "product_presentation_id": 3,
      "quantity_in_presentation": 10
    },
    {
      "product_variant_id": 78,
      "location_id":        8,
      "lot_number":         "LOT-2026-003",
      "expiration_date":    "2027-12-01",
      "quantity_base":      50
    }
  ]
}
```

---

## Respuesta (201 Created)

Se devuelve **un solo documento** que agrupa todos los movimientos. El campo `movements[]` contiene el detalle de cada producto ingresado.

```json
{
  "success": true,
  "message": "Entrada registrada exitosamente",
  "data": {
    "id":                1089,
    "document_number":   "ENT-20260910-000042",
    "document_type":     "entry",
    "warehouse_id":      1,
    "warehouse_name":    "Bodega Norte",
    "invoice_number":    "FAC-2026-00892",
    "entry_temperature": 4.5,
    "reason":            "Recepción bodega norte",
    "movement_date":     "2026-09-10T00:00:00+00:00",
    "status":            "confirmed",
    "user_id":           5,
    "user_name":         "Admin",
    "created_at":        "2026-09-10T14:32:00+00:00",

    "movements": [
      {
        "id":                 3301,
        "product_variant_id": 42,
        "movement_type":      "entry",
        "quantity":           200,
        "reason":             "Revisar temperatura en 48h"
      },
      {
        "id":                 3302,
        "product_variant_id": 55,
        "movement_type":      "entry",
        "quantity":           500
      },
      {
        "id":                 3303,
        "product_variant_id": 78,
        "movement_type":      "entry",
        "quantity":           50
      }
    ]
  }
}
```

---

## Errores frecuentes

| HTTP | Causa | Cómo identificarla |
|------|-------|--------------------|
| 403 | Sin permiso `movimientos.entrada` o sin acceso al almacén | `success: false` |
| 422 | Validación fallida (ítem sin cantidad, variante inexistente, etc.) | `errors.items.N.campo` |
| 409 | Variante es de tipo kit (no admite entradas directas) | mensaje de dominio |

### Ejemplo de error 422

```json
{
  "success": false,
  "errors": {
    "items.1.quantity_base": [
      "Debe indicar quantity_base o product_presentation_id con quantity_in_presentation."
    ],
    "items.2.expiration_date": [
      "La fecha de vencimiento es obligatoria en cada ítem."
    ]
  }
}
```

Los errores incluyen el índice del ítem (`items.1`, `items.2`…) para que el frontend pueda destacar la fila exacta en el formulario.

---

## Notas adicionales

- Todos los ítems van al mismo `warehouse_id`. No se puede mezclar almacenes en un solo request.
- Cada ítem puede ir a una `location_id` distinta dentro del mismo almacén.
- Si ya existe un lote con el mismo `product_variant_id` + `lot_number`, el backend **suma** la cantidad en lugar de crear un lote duplicado.
- La operación es atómica: si un ítem falla, **ningún movimiento** se confirma.
