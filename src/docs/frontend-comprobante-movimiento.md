# Guía Frontend — Comprobantes de Movimiento (Multi-producto)

**Versión:** 1.0 · **Fecha:** 2026-07-14  
**Módulo:** Inventario → Movimientos

---

## 1. Contexto y cambio de paradigma

Antes, cada línea de producto en una operación generaba un movimiento independiente con su propio ID usado como número de comprobante. Esto impedía reimprimir el comprobante completo cuando la operación tenía varios productos.

Ahora toda operación genera un **documento de movimiento** (`movement_document`) que agrupa todas las líneas:

```
movement_documents          ←  comprobante (SAL-2026-000001)
  └── stock_movements[]     ←  líneas de producto
  └── movement_signatures[] ←  firmas (delivered_by / received_by)
```

El número visible en el comprobante es `document_number`, con formato `TIP-AAAA-NNNNNN`:

| Tipo de operación | Prefijo | Ejemplo |
|---|---|---|
| Salida | `SAL` | `SAL-2026-000001` |
| Entrada | `ENT` | `ENT-2026-000001` |
| Traslado | `TRA` | `TRA-2026-000001` |
| Ajuste | `AJU` | `AJU-2026-000001` |
| Devolución | `DEV` | `DEV-2026-000001` |
| Baja por vencimiento | `VEN` | `VEN-2026-000001` |
| Baja / pérdida | `BAJ` | `BAJ-2026-000001` |

---

## 2. Cambios en los endpoints de registro

### 2.1 Salida — `POST /api/v1/movements/exit`

**Body (nuevo formato):**
```json
{
  "warehouse_id":    1,
  "cost_center_id":  5,
  "service_id":      2,
  "patient_document": "1012345678",
  "patient_external_id": "EXT-00892",
  "reason": "Entrega a paciente",
  "items": [
    { "generic_product_id": 10, "quantity": 3 },
    { "generic_product_id": 11, "quantity": 1, "location_id": 7 }
  ]
}
```

> `service_id`, `patient_document` y `patient_external_id` solo son obligatorios cuando `cost_center_id` es de tipo `external`.  
> `location_id` dentro de cada ítem es opcional; si se omite el sistema elige la ubicación FEFO.

**Respuesta `201`:** `MovementDocumentResource` (ver sección 4).

---

### 2.2 Entrada — `POST /api/v1/movements/entry`

**Body (nuevo formato):**
```json
{
  "warehouse_id":     1,
  "invoice_number":   "FAC-2026-00123",
  "entry_temperature": 4.5,
  "reason":           "Compra ordinaria",
  "items": [
    {
      "product_variant_id": 3,
      "location_id":        12,
      "lot_number":         "LOT-ABC",
      "expiration_date":    "2027-06-30",
      "manufacturing_date": "2025-01-10",
      "quantity_base":      100,
      "notes":              "Revisado OK"
    },
    {
      "product_variant_id":      4,
      "location_id":             12,
      "lot_number":              "LOT-XYZ",
      "expiration_date":         "2027-03-15",
      "product_presentation_id": 2,
      "quantity_in_presentation": 5
    }
  ]
}
```

> Cada ítem requiere `quantity_base` **o** `product_presentation_id + quantity_in_presentation`.

**Respuesta `201`:** `MovementDocumentResource`.

---

### 2.3 Traslado — `POST /api/v1/movements/transfer`

**Body (nuevo formato):**
```json
{
  "warehouse_from_id": 1,
  "warehouse_to_id":   2,
  "reason": "Rebalanceo de stock",
  "items": [
    {
      "product_variant_id": 3,
      "location_from_id":   7,
      "location_to_id":     15,
      "quantity":           20
    }
  ]
}
```

**Respuesta `201`:** `MovementDocumentResource` con `status: "pending_signature"`.

---

### 2.4 Operaciones de un solo producto (sin cambio de estructura)

Los endpoints de ajuste, devolución, baja y baja por vencimiento mantienen el mismo body plano de siempre. La respuesta ahora incluye el campo `movement_document_id` que apunta al documento creado.

| Endpoint | Body |
|---|---|
| `POST /movements/adjustment` | `{ warehouse_id, product_variant_id, location_id, quantity, reason }` |
| `POST /movements/return` | `{ warehouse_id, product_variant_id, location_id, quantity, reason }` |
| `POST /movements/loss` | `{ warehouse_id, product_variant_id, location_id, batch_id, quantity, reason }` |
| `POST /movements/write-off` | `{ warehouse_id, product_variant_id, batch_id, quantity, reason }` |

> La respuesta de estos cuatro es `MovementResource` (línea individual), que incluye el campo `movement_document_id` para poder confirmar después.

---

## 3. Nuevos endpoints de documentos

### 3.1 Listar comprobantes

```
GET /api/v1/movement-documents
Permiso: movimientos.ver
```

**Query params opcionales:**

| Parámetro | Descripción |
|---|---|
| `document_type` | `exit`, `entry`, `transfer`, `adjustment`, `return`, `loss`, `expiration_write_off` |
| `warehouse_id` | Filtra por almacén origen |
| `cost_center_id` | Filtra por centro de costo |
| `status` | `pending_signature` o `confirmed` |
| `document_number` | Búsqueda parcial (LIKE) |
| `date_from` / `date_to` | Rango de fechas |
| `per_page` | Paginación (máx. 100) |

**Respuesta `200`:** paginado de `MovementDocumentResource` (sin el array `movements` para no sobrecargar el listado).

---

### 3.2 Ver comprobante completo (reimpresión)

```
GET /api/v1/movement-documents/{id}
Permiso: movimientos.ver
```

**Respuesta `200`:** `MovementDocumentResource` completo con `movements` y `signatures`.

---

### 3.3 Confirmar / firmar documento

```
POST /api/v1/movement-documents/{id}/confirm
Permiso: movimientos.confirmar
```

> Antes era `POST /movements/{id}/confirm`. **El ID ahora es el del documento, no del movimiento.**

**Body:**
```json
{
  "delivered_by": {
    "name":      "Juan Pérez",
    "document":  "12345678",
    "signature": "data:image/png;base64,..."
  },
  "received_by": {
    "name":      "María López",
    "document":  "87654321",
    "signature": "data:image/png;base64,..."
  }
}
```

**Respuesta `200`:** `MovementDocumentResource` con `status: "confirmed"` y `signatures` cargadas.

---

### 3.4 Cancelar documento pendiente

```
DELETE /api/v1/movement-documents/{id}/pending
Permiso: movimientos.cancelar
```

> Antes era `DELETE /movements/{id}/pending`.

**Respuesta `200`:** `{ "success": true, "message": "Documento pendiente cancelado exitosamente" }`.

---

### 3.5 Ver firma individual

```
GET /api/v1/movement-documents/{id}/signature/{role}
role: delivered_by | received_by
```

---

## 4. Estructura de `MovementDocumentResource`

```json
{
  "id": 1,
  "document_number": "SAL-2026-000001",
  "document_type": "exit",
  "status": "confirmed",
  "warehouse_id": 1,
  "warehouse_name": "Almacén Central",
  "warehouse_to_id": null,
  "warehouse_to_name": null,
  "cost_center_id": 5,
  "cost_center": { "id": 5, "code": "CC-05", "name": "Quirófano 2", "type": "external" },
  "service_id": 2,
  "medical_service": { "id": 2, "code": "SVC-02", "name": "Cirugía General" },
  "patient_document": "1012345678",
  "patient_external_id": "EXT-00892",
  "invoice_number": null,
  "entry_temperature": null,
  "reason": "Entrega a paciente",
  "user_id": 3,
  "user_name": "Admin SGA",
  "created_at": "2026-07-14T10:30:00+00:00",
  "signatures": [
    {
      "role": "delivered_by",
      "signer_name": "Juan Pérez",
      "signer_document": "12345678",
      "signature_data": "data:image/png;base64,...",
      "signed_at": "2026-07-14T10:35:00+00:00"
    }
  ],
  "movements": [
    {
      "id": 1,
      "movement_document_id": 1,
      "product_variant_id": 3,
      "product_name": "Jeringa 10ml",
      "variant_lab_brand": "BD",
      "batch_id": 7,
      "batch_lot_number": "LOT-ABC",
      "batch_expiration_date": "2027-06-30",
      "movement_type": "exit",
      "quantity": -3,
      "status": "confirmed",
      "warehouse_id": 1,
      "location_from_id": 12,
      "cost_center_id": 5,
      "service_id": 2,
      "patient_document": "1012345678",
      "created_at": "2026-07-14T10:30:00+00:00"
    }
  ]
}
```

> El campo `quantity` es **negativo** para salidas/bajas y **positivo** para entradas.

---

## 5. Flujo de pantallas sugerido

### Salida / traslado (con firma)

```
1. Formulario → seleccionar almacén, centro de costo, agregar productos (items[])
2. POST /movements/exit  →  recibe document_number + id + status=pending_signature
3. Mostrar comprobante preliminar con QR o código para imprimir
4. Pantalla de firma (canvas x2: entregado por / recibido por)
5. POST /movement-documents/{id}/confirm  →  status=confirmed
6. Comprobante final listo para imprimir / descargar
```

### Reimpresión

```
GET /movement-documents?document_number=SAL-2026-000001
  → obtiene id
GET /movement-documents/{id}
  → devuelve comprobante completo con todos los productos y firmas
```

---

## 6. Migraciones requeridas

Las siguientes tablas fueron **creadas o modificadas** en migraciones del período de diseño. Es necesario correr:

```bash
make migrate-fresh-seed
# equivale a: php artisan migrate:fresh --seed
```

**Tablas afectadas:**

| Tabla | Cambio |
|---|---|
| `document_sequences` | Nueva — controla los consecutivos por tipo y año |
| `movement_documents` | Nueva — cabecera de cada comprobante |
| `stock_movements` | Columna `movement_document_id` (NOT NULL FK) agregada a migración existente |
| `movement_signatures` | FK cambiada de `movement_id → stock_movements` a `movement_document_id → movement_documents` |

> **Importante:** como las columnas se agregaron editando migraciones ya ejecutadas (práctica de diseño), un `migrate` simple no es suficiente. Se requiere `migrate:fresh` para recrear el esquema completo.
