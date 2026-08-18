# Guía Frontend — Órdenes de Compra de Gastos

## Contexto

Las **órdenes de compra de gastos** (`order_type: expense`) cubren adquisiciones que
**no ingresan al inventario**: uniformes, grabadoras, obras civiles, servicios, papelería,
y cualquier bien o servicio de uso no regular en la clínica.

Se diferencian de las órdenes de inventario en tres aspectos clave:

| Aspecto | OC Inventario | OC Gastos |
|---|---|---|
| Ítems | Productos del catálogo | Texto libre (descripción) |
| Almacén | Obligatorio | No aplica |
| Inventario | Se afecta al recibir | No se afecta |
| Pagos/Anticipos | No existe | Registro de abonos parciales |
| Factura | No existe | Se registra número y fecha |
| Envío a contabilidad | No existe | Email con OC + factura adjunta |
| Código | `OC-YYYY-NNNNN` | `OG-YYYY-NNNNN` |

---

## 1. Permisos requeridos

| Permiso | Qué habilita |
|---|---|
| `ordenes_gasto.ver` | Listar y ver detalle |
| `ordenes_gasto.crear` | Crear, editar, eliminar, enviar a aprobación, cancelar |
| `ordenes_gasto.aprobar` | Aprobar o rechazar |
| `ordenes_gasto.enviar` | Enviar al proveedor (email) |
| `ordenes_gasto.recibir` | Registrar recepción parcial o total |
| `ordenes_gasto.pagos` | Registrar y eliminar pagos/anticipos |
| `ordenes_gasto.factura` | Registrar factura y enviar a contabilidad |

Consulta `GET /api/v1/auth/me` para saber qué permisos tiene el usuario logueado
y mostrar u ocultar acciones en consecuencia.

---

## 2. Flujo de estados

```
draft ──[submit]──► pending_approval ──[approve]──► approved ──[send]──► sent
                         │                  │                               │
                    [reject]           [cancel]                       [receive]
                         ▼                  ▼                          ▌     ▌
                      rejected          cancelled         partially_received  received
```

Los **pagos** se pueden registrar en **cualquier estado** (anticipos incluidos).
La **factura** se puede registrar en **cualquier estado**.
El **envío a contabilidad** requiere que la factura esté registrada.

### Tabla de transiciones

| Estado actual | Acción | Estado resultante | Permiso |
|---|---|---|---|
| `draft` | submit | `pending_approval` | `ordenes_gasto.crear` |
| `draft` | editar | `draft` | `ordenes_gasto.crear` |
| `draft` | eliminar | — | `ordenes_gasto.crear` |
| `pending_approval` | approve | `approved` | `ordenes_gasto.aprobar` |
| `pending_approval` | reject | `rejected` | `ordenes_gasto.aprobar` |
| `approved` | send | `sent` | `ordenes_gasto.enviar` |
| `approved` | cancel | `cancelled` | `ordenes_gasto.crear` |
| `sent` | receive | `partially_received` o `received` | `ordenes_gasto.recibir` |
| `partially_received` | receive | `partially_received` o `received` | `ordenes_gasto.recibir` |
| Cualquiera | Registrar pago | — (no cambia status) | `ordenes_gasto.pagos` |
| Cualquiera | Registrar factura | — (no cambia status) | `ordenes_gasto.factura` |
| Con factura | send-accounting | — (no cambia status) | `ordenes_gasto.factura` |

---

## 3. Proveedores de gastos

Los proveedores de órdenes de gasto son independientes de los proveedores de inventario.
La tabla `suppliers` ahora tiene el campo `supplier_type`:

| Valor | Descripción |
|---|---|
| `inventory` | Solo para OC de inventario (catálogo médico) |
| `expense` | Solo para OC de gastos (uniformes, servicios, obras, etc.) |
| `both` | Puede usarse en ambos tipos de orden |

### 3.1 Búsqueda typeahead (autocompletar)

```
GET /api/v1/suppliers/search?q=Uniforme&type=expense
Permiso: proveedores.ver
```

Llamar desde el input de búsqueda mientras el usuario escribe. Recomendado: activar
a partir del **segundo carácter** e incluir siempre `type=expense` para filtrar solo
los habilitados para gastos (incluye `both`).

**Query params:**

| Parámetro | Descripción |
|---|---|
| `q` | Texto de búsqueda (nombre, NIT o contacto). Mínimo 2 caracteres para filtrar. |
| `type` | `expense` — devuelve `expense` y `both`. Omitir devuelve todos. |

**Respuesta `200`:** máximo 20 resultados, ordenados por nombre.

```json
{
  "success": true,
  "message": "Búsqueda de proveedores",
  "data": [
    {
      "id": 12,
      "name": "Uniformes y Dotaciones S.A.S",
      "tax_id": "900555123-1",
      "email": "ventas@uniformes.com",
      "supplier_type": "expense"
    }
  ]
}
```

### 3.2 Crear proveedor rápido (sin salir del formulario)

Cuando el usuario no encuentra el proveedor en el typeahead, puede crearlo directamente
con un mini-formulario inline (modal o panel lateral).

```
POST /api/v1/expense-orders/supplier
Permiso: ordenes_gasto.crear
```

Solo requiere `name`. El backend asigna `supplier_type: expense` automáticamente.

```json
{
  "name": "Ferretería El Perno Feliz",
  "email": "ventas@perno.com",
  "phone": "3109876543",
  "tax_id": "890123456-7",
  "notes": "Proveedor de ferretería industrial"
}
```

**Respuesta `201`:**

```json
{
  "success": true,
  "message": "Proveedor creado",
  "data": {
    "id": 25,
    "name": "Ferretería El Perno Feliz",
    "tax_id": "890123456-7",
    "email": "ventas@perno.com",
    "supplier_type": "expense",
    ...
  }
}
```

Tras crear, seleccionar automáticamente el nuevo proveedor en el formulario de la orden.

### UX recomendada para el selector de proveedor

```
[ Buscar proveedor...                    ] ← input typeahead
  ┌─────────────────────────────────────┐
  │ ✓ Uniformes y Dotaciones S.A.S      │ ← resultados del search
  │   Ferretería Nacional               │
  │   ─────────────────────────────     │
  │ + Crear "Almacén XYZ" como nuevo    │ ← si no hay coincidencia exacta
  └─────────────────────────────────────┘
```

El botón "Crear nuevo" abre un modal ligero con solo: Nombre*, NIT, Email, Teléfono.
Al guardar, el proveedor queda seleccionado automáticamente.

---

## 4. Endpoints

### 4.1 Listar órdenes de gasto

```
GET /api/v1/expense-orders
Permiso: ordenes_gasto.ver
```

**Query params opcionales:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `status` | string | Filtrar por estado: `draft`, `pending_approval`, `approved`, `sent`, `partially_received`, `received`, `rejected`, `cancelled` |
| `payment_status` | string | Filtrar por estado de pago: `unpaid`, `partial`, `paid` |
| `supplier_id` | integer | Filtrar por proveedor |
| `date_from` | date (Y-m-d) | Fecha de creación desde |
| `date_to` | date (Y-m-d) | Fecha de creación hasta |
| `per_page` | integer | Registros por página (máx. 100, default 25) |

**Respuesta `200`:** los ítems vienen directamente en `data` (no en `data.data`).
La paginación va en `meta`.

```json
{
  "success": true,
  "message": "Listado de órdenes de compra de gastos",
  "data": [
    {
      "id": 1,
      "order_type": "expense",
      "code": "OG-2026-00001",
      "status": "draft",
      "payment_status": "unpaid",
      "supplier_id": 5,
      "subtotal": "1200000.00",
      "tax_amount": "228000.00",
      "total_amount": "1428000.00",
      "amount_paid": "0.00",
      "invoice_number": null,
      "invoice_date": null,
      "notes": "Uniformes área administrativa",
      "expected_delivery_date": "2026-09-01",
      "created_by": 2,
      "sent_at": null,
      "received_at": null,
      "accounting_sent_at": null,
      "created_at": "2026-08-17T10:00:00+00:00",
      "updated_at": "2026-08-17T10:00:00+00:00",
      "supplier": {
        "id": 5,
        "name": "Uniformes y Dotaciones S.A.S",
        "email": "ventas@uniformes.com"
      },
      "items": [...],
      "payments": []
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1,
    "last_page": 1
  }
}
```

---

### 4.2 Crear orden de gasto

```
POST /api/v1/expense-orders
Permiso: ordenes_gasto.crear
Content-Type: application/json
```

**Body:**

```json
{
  "supplier_id": 5,
  "expected_delivery_date": "2026-09-01",
  "notes": "Uniformes área administrativa — tallas según lista adjunta",
  "items": [
    {
      "description": "Uniforme pantalón azul marino talla 32",
      "unit": "und",
      "quantity": 5,
      "unit_price": 85000,
      "tax_rate": 19,
      "notes": "Color azul marino"
    },
    {
      "description": "Blusa manga larga blanca talla M",
      "unit": "und",
      "quantity": 5,
      "unit_price": 65000,
      "tax_rate": 19,
      "notes": null
    }
  ]
}
```

**Campos de ítems:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `description` | string (máx 255) | Sí | Descripción libre del ítem |
| `unit` | string (máx 50) | Sí | Unidad: `und`, `m`, `kg`, `hora`, `global`, etc. |
| `quantity` | number (> 0) | Sí | Cantidad solicitada |
| `unit_price` | number (≥ 0) | Sí | Precio unitario |
| `tax_rate` | number (0–100) | No | % de IVA, default 0 |
| `notes` | string | No | Nota de la línea |

El backend calcula automáticamente `tax_amount` y `total_price` por ítem, y `subtotal`,
`tax_amount` y `total_amount` de la orden.

**Respuesta `201`:**

```json
{
  "success": true,
  "message": "Orden de compra de gastos creada",
  "data": {
    "id": 1,
    "code": "OG-2026-00001",
    "status": "draft",
    "payment_status": "unpaid",
    ...
    "items": [
      {
        "id": 1,
        "description": "Uniforme pantalón azul marino talla 32",
        "unit": "und",
        "quantity_requested": "5.000",
        "quantity_received": "0.000",
        "unit_price": "85000.00",
        "tax_rate": "19.00",
        "tax_amount": "80750.00",
        "total_price": "504750.00",
        "notes": "Color azul marino"
      }
    ],
    "payments": []
  }
}
```

---

### 4.3 Ver detalle

```
GET /api/v1/expense-orders/{id}
Permiso: ordenes_gasto.ver
```

Devuelve la misma estructura que el listado pero incluyendo `payments` con el detalle
de cada pago y el usuario que lo registró.

---

### 4.4 Actualizar orden

```
PUT /api/v1/expense-orders/{id}
Permiso: ordenes_gasto.crear
```

Solo disponible en estado `draft`. El body tiene la misma estructura que el POST.
Los ítems se **reemplazan completamente** (no se hace merge): enviar siempre la lista
completa de ítems que debe quedar.

**Error si no está en draft (`409`):**

```json
{
  "success": false,
  "message": "Solo se pueden editar órdenes de gasto en estado borrador."
}
```

---

### 4.5 Eliminar orden

```
DELETE /api/v1/expense-orders/{id}
Permiso: ordenes_gasto.crear
```

Solo disponible en estado `draft`. Responde `200` con `data: null`.

---

## 5. Flujo de aprobación

### 5.1 Enviar a aprobación

```
POST /api/v1/expense-orders/{id}/submit
Permiso: ordenes_gasto.crear
Body: vacío {}
```

Cambia el estado de `draft` a `pending_approval`.

> El flujo de aprobación usa la misma configuración de `ApprovalFlow` que las
> órdenes de inventario. Si no hay flujo configurado para `expense_order`, consultar
> con el administrador.

### 5.2 Aprobar

```
POST /api/v1/expense-orders/{id}/approve
Permiso: ordenes_gasto.aprobar
```

```json
{
  "record_id": 12,
  "comments": "Aprobado — presupuesto disponible"
}
```

### 5.3 Rechazar

```
POST /api/v1/expense-orders/{id}/reject
Permiso: ordenes_gasto.aprobar
```

```json
{
  "record_id": 12,
  "comments": "Excede el presupuesto del mes"
}
```

---

## 6. Enviar al proveedor (email)

```
POST /api/v1/expense-orders/{id}/send
Permiso: ordenes_gasto.enviar
Body: vacío {}
```

- Solo disponible en estado `approved`.
- El proveedor debe tener email registrado en el catálogo.
- Envía un correo al proveedor con los ítems de la orden y un PDF adjunto.
- Cambia el estado a `sent` y registra `sent_at`.

**Error si el proveedor no tiene email (`409`):**

```json
{
  "success": false,
  "message": "El proveedor no tiene correo electrónico registrado."
}
```

---

## 7. Registrar recepción

```
POST /api/v1/expense-orders/{id}/receive
Permiso: ordenes_gasto.recibir
```

Solo disponible en estados `sent` o `partially_received`. Se puede recibir parcialmente:
enviar solo los ítems que llegaron, con la cantidad que llegó.

**Body:**

```json
{
  "items": [
    { "item_id": 1, "quantity_received": 3 },
    { "item_id": 2, "quantity_received": 5 }
  ]
}
```

- `item_id`: ID del ítem (`expense_order_items.id`), no del producto.
- `quantity_received`: cuánto llegó en esta entrega. No puede superar el pendiente
  por recibir (`quantity_requested - quantity_received` acumulado).
- Si todos los ítems quedan completamente recibidos → estado `received`.
- Si queda pendiente algún ítem → estado `partially_received`.

**Respuesta `200`:** devuelve la orden actualizada con los ítems y sus nuevas cantidades.

**Cómo mostrar el progreso por ítem:**

```
quantity_received / quantity_requested × 100 = % recibido
```

Mostrar barra de progreso o badge por ítem:
- 0% → Pendiente
- 1–99% → Parcial
- 100% → Completo

---

## 8. Gestión de pagos y anticipos

Los pagos se pueden registrar en **cualquier estado** de la orden.

### 8.1 Listar pagos

```
GET /api/v1/expense-orders/{orderId}/payments
Permiso: ordenes_gasto.ver
```

**Respuesta `200`:**

```json
{
  "success": true,
  "message": "Pagos de la orden",
  "data": [
    {
      "id": 1,
      "amount": "500000.00",
      "payment_date": "2026-08-15",
      "payment_method": "transfer",
      "reference": "TRF-20260815-001",
      "notes": "Anticipo 35%",
      "registered_by": { "id": 2, "name": "María García" },
      "created_at": "2026-08-17T10:30:00+00:00"
    }
  ]
}
```

### 8.2 Registrar pago

```
POST /api/v1/expense-orders/{orderId}/payments
Permiso: ordenes_gasto.pagos
```

```json
{
  "amount": 500000,
  "payment_date": "2026-08-15",
  "payment_method": "transfer",
  "reference": "TRF-20260815-001",
  "notes": "Anticipo 35%"
}
```

**Métodos de pago válidos:**

| Valor | Etiqueta |
|---|---|
| `cash` | Efectivo |
| `transfer` | Transferencia |
| `check` | Cheque |
| `other` | Otro |

Tras registrar un pago, la respuesta incluye la **orden actualizada** con los nuevos
valores de `amount_paid` y `payment_status`.

**`payment_status` se actualiza automáticamente:**

| Condición | payment_status |
|---|---|
| `amount_paid == 0` | `unpaid` |
| `0 < amount_paid < total_amount` | `partial` |
| `amount_paid >= total_amount` | `paid` |

### 8.3 Eliminar pago

```
DELETE /api/v1/expense-orders/{orderId}/payments/{paymentId}
Permiso: ordenes_gasto.pagos
```

Responde `200` con `data: null`. El `payment_status` y `amount_paid` de la orden
se recalculan automáticamente.

### Cómo mostrar el panel de pagos

```
Total orden:    $ 1.428.000
Pagado:         $   500.000  ████░░░░░ 35%
Saldo pendiente:$   928.000
```

---

## 9. Registrar factura

```
POST /api/v1/expense-orders/{id}/invoice
Permiso: ordenes_gasto.factura
```

Disponible en **cualquier estado**. Se puede actualizar llamando el endpoint de nuevo.

```json
{
  "invoice_number": "FV-2026-4821",
  "invoice_date": "2026-08-20"
}
```

Tras registrar la factura, aparecerán `invoice_number` e `invoice_date` en el detalle
de la orden. Habilita el botón de "Enviar a contabilidad".

---

## 10. Enviar a contabilidad

```
POST /api/v1/expense-orders/{id}/send-accounting
Permiso: ordenes_gasto.factura
```

**Requisito:** la orden debe tener `invoice_number` registrado.

```json
{
  "recipients": [
    "contabilidad@clinicabojanini.com",
    "jefe.financiero@clinicabojanini.com"
  ]
}
```

- Envía un email a los destinatarios indicados con:
  - Datos de la orden (proveedor, ítems, totales)
  - Estado de pagos y anticipos registrados
  - Número y fecha de factura
  - PDF de la orden adjunto
- Registra `accounting_sent_at` en la orden.
- Se puede llamar múltiples veces (reenvío).

**Error si no hay factura registrada (`409`):**

```json
{
  "success": false,
  "message": "Debe registrar la factura antes de enviar a contabilidad."
}
```

---

## 11. Cancelar orden

```
POST /api/v1/expense-orders/{id}/cancel
Permiso: ordenes_gasto.crear
Body: vacío {}
```

Disponible en estados `draft`, `pending_approval` y `approved`.

---

## 12. Estructura completa del recurso

```json
{
  "id": 1,
  "order_type": "expense",
  "code": "OG-2026-00001",
  "status": "sent",
  "payment_status": "partial",
  "supplier_id": 5,
  "subtotal": "1200000.00",
  "tax_amount": "228000.00",
  "total_amount": "1428000.00",
  "amount_paid": "500000.00",
  "invoice_number": "FV-2026-4821",
  "invoice_date": "2026-08-20",
  "notes": "Uniformes área administrativa",
  "expected_delivery_date": "2026-09-01",
  "created_by": 2,
  "sent_at": "2026-08-18T14:00:00+00:00",
  "received_at": null,
  "accounting_sent_at": null,
  "created_at": "2026-08-17T10:00:00+00:00",
  "updated_at": "2026-08-18T14:00:00+00:00",
  "supplier": {
    "id": 5,
    "name": "Uniformes y Dotaciones S.A.S",
    "email": "ventas@uniformes.com"
  },
  "items": [
    {
      "id": 1,
      "description": "Uniforme pantalón azul marino talla 32",
      "unit": "und",
      "quantity_requested": "5.000",
      "quantity_received": "3.000",
      "unit_price": "85000.00",
      "tax_rate": "19.00",
      "tax_amount": "80750.00",
      "total_price": "504750.00",
      "notes": "Color azul marino"
    }
  ],
  "payments": [
    {
      "id": 1,
      "amount": "500000.00",
      "payment_date": "2026-08-15",
      "payment_method": "transfer",
      "reference": "TRF-20260815-001",
      "notes": "Anticipo 35%",
      "registered_by": { "id": 2, "name": "María García" },
      "created_at": "2026-08-17T10:30:00+00:00"
    }
  ]
}
```

---

## 13. Sugerencias de UX

### Badges de estado de orden

| status | Color sugerido | Etiqueta |
|---|---|---|
| `draft` | Gris | Borrador |
| `pending_approval` | Amarillo | En aprobación |
| `approved` | Azul | Aprobado |
| `rejected` | Rojo | Rechazado |
| `sent` | Morado | Enviado |
| `partially_received` | Naranja | Recibido parcial |
| `received` | Verde | Recibido |
| `cancelled` | Rojo oscuro | Cancelado |

### Badges de estado de pago

| payment_status | Color sugerido | Etiqueta |
|---|---|---|
| `unpaid` | Rojo | Sin pago |
| `partial` | Amarillo | Pago parcial |
| `paid` | Verde | Pagado |

### Acciones disponibles según estado

Mostrar solo las acciones válidas para el estado actual del usuario:

```
draft            → [Editar] [Enviar a aprobación] [Eliminar]
pending_approval → [Aprobar] [Rechazar]   (si tiene ordenes_gasto.aprobar)
approved         → [Enviar al proveedor] [Cancelar]
sent             → [Registrar recepción]
partially_received → [Registrar recepción]
received / cualquier estado con factura.factura perm → [Registrar factura] [Enviar a contabilidad]
```

Pagos: mostrar el panel de pagos siempre que `order_type === 'expense'` y el usuario
tenga `ordenes_gasto.pagos`.

---

## 14. Casos de prueba para el frontend

Escenarios mínimos que el equipo frontend debe cubrir en sus propias pruebas (e2e o integración):

### 14.1 CRUD básico

| # | Caso | Resultado esperado |
|---|---|---|
| 1 | Crear orden con 2 ítems, IVA 19% en uno | `status: draft`, totales calculados correctos |
| 2 | Crear orden sin ítems | Formulario no debe enviarse (campo requerido) |
| 3 | Crear con proveedor sin email | Se crea en `draft`; el botón Enviar aparece deshabilitado hasta aprobar |
| 4 | Editar orden en `draft` | Ítems reemplazados, totales actualizados |
| 5 | Intentar editar en `pending_approval` | API devuelve 409; mostrar mensaje de error |
| 6 | Eliminar orden en `draft` | Orden desaparece del listado |

### 14.2 Flujo de estados

| # | Caso | Resultado esperado |
|---|---|---|
| 7 | Enviar a aprobación | Estado cambia a `pending_approval`; botones Editar/Eliminar desaparecen |
| 8 | Aprobar | Estado cambia a `approved`; aparece botón Enviar al proveedor |
| 9 | Rechazar con comentario | Estado `rejected`; mostrar comentario en el detalle |
| 10 | Enviar al proveedor (proveedor con email) | Estado `sent`; `sent_at` visible en UI |
| 11 | Enviar con proveedor sin email | API devuelve 409; mostrar mensaje claro al usuario |
| 12 | Recibir parcialmente (1 de 3 unidades del ítem 1) | Estado `partially_received`; barra de progreso al 33% |
| 13 | Recibir resto hasta completar todos los ítems | Estado `received`; `received_at` visible |
| 14 | Intentar recibir más de lo solicitado | API devuelve 409; mostrar error por ítem |
| 15 | Cancelar orden en `approved` | Estado `cancelled`; sin opción de recuperar |

### 14.3 Pagos

| # | Caso | Resultado esperado |
|---|---|---|
| 16 | Registrar anticipo en estado `draft` | `payment_status: partial`; barra de pago actualizada |
| 17 | Registrar pago igual al total | `payment_status: paid`; barra al 100% |
| 18 | Registrar pago con método `check` y referencia | Referencia visible en lista de pagos |
| 19 | Eliminar único pago | `payment_status` vuelve a `unpaid`; `amount_paid: 0` |
| 20 | Registrar dos pagos parciales que sumen el total | `payment_status: paid` tras el segundo |

### 14.4 Factura y contabilidad

| # | Caso | Resultado esperado |
|---|---|---|
| 21 | Registrar factura en cualquier estado | `invoice_number` e `invoice_date` visibles; botón "Enviar a contabilidad" habilitado |
| 22 | Actualizar número de factura | Nuevo valor guardado correctamente |
| 23 | Enviar a contabilidad sin factura registrada | API devuelve 409; botón debe estar deshabilitado en UI |
| 24 | Enviar a contabilidad con 2 destinatarios | `accounting_sent_at` se registra; mensaje de confirmación |
| 25 | Reenviar a contabilidad (segunda llamada) | Permitido; `accounting_sent_at` se actualiza |

### 14.5 Filtros y listado

| # | Caso | Resultado esperado |
|---|---|---|
| 26 | Filtrar por `status=draft` | Solo órdenes en borrador |
| 27 | Filtrar por `payment_status=paid` | Solo órdenes completamente pagadas |
| 28 | Filtrar por `supplier_id` | Solo órdenes del proveedor seleccionado |
| 29 | Filtrar por rango de fechas | Órdenes dentro del rango |
| 30 | Listado vacío | Mostrar estado vacío, no error |

### 14.6 Control de permisos

| # | Caso | Resultado esperado |
|---|---|---|
| 31 | Usuario sin `ordenes_gasto.crear` intenta crear | Botón "Nueva orden" oculto; POST devuelve 403 |
| 32 | Usuario sin `ordenes_gasto.aprobar` ve orden en `pending_approval` | Botones Aprobar/Rechazar ocultos |
| 33 | Usuario sin `ordenes_gasto.pagos` ve detalle de orden | Panel de pagos oculto |
| 34 | Usuario sin `ordenes_gasto.factura` | Sección de factura y botón "Enviar a contabilidad" ocultos |

### 14.7 Selector de proveedor

| # | Caso | Resultado esperado |
|---|---|---|
| 35 | Escribir 1 carácter en el campo de proveedor | No llama al API (esperar mínimo 2 caracteres) |
| 36 | Escribir 2+ caracteres | Llamada a `GET /api/v1/suppliers/search?q=XX&type=expense`; resultados en dropdown |
| 37 | Buscar nombre existente de proveedor `expense` o `both` | Aparece en resultados; tipo `inventory` no aparece |
| 38 | Seleccionar proveedor del dropdown | Campo queda con el proveedor seleccionado; `supplier_id` se asigna al formulario |
| 39 | Escribir nombre que no existe → clic "Crear nuevo" | Abre modal/panel con campos: Nombre*, NIT, Email, Teléfono |
| 40 | Crear proveedor solo con nombre (campo mínimo) | POST exitoso; proveedor queda seleccionado automáticamente en la orden |
| 41 | Crear proveedor con todos los campos | `supplier_type: expense` asignado por el backend (no configurable por usuario) |
| 42 | Intentar usar proveedor `inventory` en orden de gasto | API devuelve 422 con mensaje de tipo de proveedor; mostrar error en campo |

### Datos de prueba sugeridos

```json
{
  "supplier_id": "<ID del proveedor con email configurado>",
  "items": [
    {
      "description": "Grabadora Sony ICD-PX470",
      "unit": "und",
      "quantity": 3,
      "unit_price": 250000,
      "tax_rate": 19
    },
    {
      "description": "Servicio de fumigación",
      "unit": "global",
      "quantity": 1,
      "unit_price": 350000,
      "tax_rate": 0
    }
  ]
}
```

Resultado esperado de totales:
- Ítem 1: subtotal 750.000 + IVA 142.500 = 892.500
- Ítem 2: subtotal 350.000 + IVA 0 = 350.000
- **Total: 1.242.500**
