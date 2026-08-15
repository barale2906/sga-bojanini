# Fecha de Movimiento (`movement_date`)

Permite registrar entradas, salidas y traslados con una fecha operativa distinta a la del sistema, útil para correcciones retroactivas.

---

## El campo

| Propiedad   | Valor                                                        |
|-------------|--------------------------------------------------------------|
| Nombre      | `movement_date`                                              |
| Tipo        | `string` — fecha o datetime ISO 8601                         |
| Requerido   | No — si se omite el sistema usa `now()`                      |
| Validación  | `nullable · date · before_or_equal:today`                    |
| Aplica a    | `movement_documents` + `stock_movements`                     |

Formatos aceptados: `"2026-08-10"`, `"2026-08-10T14:30:00"`, `"2026-08-10 14:30:00"`.  
No se aceptan fechas futuras — el servidor responde `422` si se envía una.

---

## Endpoints afectados

### `POST /api/v1/movements/entry`
**Permiso:** `movimientos.entrada`

```json
{
  "warehouse_id": 1,
  "movement_date": "2026-08-10",
  "invoice_number": "FAC-2026-001",
  "entry_temperature": 4.5,
  "reason": "Compra de agosto",
  "items": [
    {
      "product_variant_id": 12,
      "location_id": 3,
      "lot_number": "LOTE-001",
      "expiration_date": "2027-12-31",
      "quantity_base": 50
    }
  ]
}
```

---

### `POST /api/v1/movements/exit`
**Permiso:** `movimientos.salida`

```json
{
  "warehouse_id": 1,
  "movement_date": "2026-08-10",
  "cost_center_id": 2,
  "reason": "Dispensación retroactiva",
  "items": [
    {
      "generic_product_id": 5,
      "quantity": 10
    }
  ]
}
```

---

### `POST /api/v1/movements/transfer`
**Permiso:** `movimientos.transferir`

```json
{
  "warehouse_from_id": 1,
  "warehouse_to_id": 2,
  "movement_date": "2026-08-10",
  "reason": "Redistribución mensual",
  "items": [
    {
      "product_variant_id": 12,
      "location_from_id": 3,
      "location_to_id": 8,
      "quantity": 20
    }
  ]
}
```

---

## Cambios en la respuesta

Los tres endpoints devuelven un `MovementDocument`. El campo `movement_date` aparece ahora en el documento raíz y en cada línea de movimiento.

| Objeto            | Campo nuevo     | Tipo                   | Valor cuando no se envió             |
|-------------------|-----------------|------------------------|--------------------------------------|
| `movement_document` | `movement_date` | string ISO 8601 \| null | Fecha/hora del POST (igual a `created_at`) |
| `movements[*]`    | `movement_date` | string ISO 8601 \| null | Hereda el valor del documento padre  |

**Ejemplo (fragmento):**

```json
{
  "id": 47,
  "document_number": "SAL-20260815-047",
  "document_type": "exit",
  "movement_date": "2026-08-10T00:00:00+00:00",
  "created_at": "2026-08-15T10:23:41+00:00",
  "movements": [
    {
      "id": 83,
      "movement_type": "exit",
      "quantity": -10,
      "movement_date": "2026-08-10T00:00:00+00:00",
      "created_at": "2026-08-15T10:23:41+00:00"
    }
  ]
}
```

---

## Consideraciones

**`created_at` vs `movement_date`**  
`created_at` siempre refleja cuándo se creó el registro en la BD. `movement_date` refleja cuándo ocurrió operativamente el movimiento. Usar `movement_date` en comprobantes, reportes y listados visibles al usuario.

**Filtros de listado**  
Los filtros `date_from` / `date_to` en `GET /api/v1/movements` y `GET /api/v1/movement-documents` filtran por `created_at`. Esto no cambia en esta versión — coordinar con backend si se necesita filtrar por `movement_date`.

**Zona horaria**  
El servidor opera en UTC. Si se envía solo la fecha (`"2026-08-10"`), se almacena como `2026-08-10 00:00:00 UTC`.
