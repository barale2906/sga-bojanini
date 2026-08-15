# Guía Frontend — Consolidación de Órdenes de Compra

## Contexto

El proceso de compras genera múltiples órdenes de compra (OC) al mismo proveedor durante
el mes. Contabilidad exige que cada factura de proveedor esté respaldada por **una sola
orden de compra** que coincida en totales. Este módulo permite **unificar** varias OC de
un mismo proveedor en una **Orden Consolidada** (código `OCC-YYYY-NNNNN`), que es el
documento que se entrega a contabilidad junto con la factura.

### Reglas de negocio que el frontend debe respetar

| Regla | Detalle |
|---|---|
| Solo OC en estado `received` | Solo las órdenes completamente recibidas pueden consolidarse |
| Un solo proveedor por consolidado | El proveedor se elige primero; todas las OC que se seleccionan pertenecen a él |
| Una OC solo se consolida una vez | Una OC ya consolidada queda bloqueada y no puede volver a seleccionarse |
| Agrupación de líneas | Si el mismo producto aparece en varias OC con el **mismo precio**, se genera una sola línea sumando cantidades. Si el precio difiere, aparecen líneas separadas |
| OC pendientes de períodos anteriores | El endpoint de OC consolidables siempre devuelve un grupo `pending` con OC recibidas pero nunca consolidadas que quedan fuera del rango de fechas seleccionado |

---

## 1. Permiso requerido

```
ordenes_compra.consolidar   →  crear un consolidado
ordenes_compra.ver          →  ver listados y detalle
```

Consulta `GET /api/v1/auth/me` para saber si el usuario logueado tiene el permiso
`ordenes_compra.consolidar` y mostrar u ocultar el botón de creación.

---

## 2. Endpoints

### 2.1 Listar proveedores con OC consolidables ← **punto de entrada del flujo**

```
GET /api/v1/purchase-orders/consolidable-suppliers
Permiso: ordenes_compra.ver
```

Devuelve únicamente los proveedores que tienen **al menos una OC** en estado `received`
y aún sin consolidar. Es la primera llamada que debe hacer el frontend al abrir la
pantalla de consolidación, para poblar el selector de proveedor.

**Sin parámetros.**

**Respuesta exitosa `200`:**

```json
{
  "success": true,
  "message": "Proveedores con órdenes consolidables",
  "data": [
    { "id": 3,  "name": "Proveedor XYZ",   "tax_id": "900123456-1" },
    { "id": 11, "name": "Distribuidora ABC","tax_id": "800987654-2" }
  ]
}
```

Si no hay ningún proveedor con OC pendientes de consolidar, `data` llega como array
vacío `[]` — mostrar un mensaje informativo al usuario.

---

### 2.2 Listar OC disponibles para consolidar (de un proveedor)

```
GET /api/v1/purchase-orders/consolidable?supplier_id={id}
Permiso: ordenes_compra.ver
```

Se llama **después** de que el usuario elige un proveedor del selector del paso anterior.
El parámetro `supplier_id` es técnicamente opcional en el backend, pero el frontend
**siempre debe enviarlo** para garantizar que solo se muestran OC del proveedor elegido.

**Query params:**

| Param | Tipo | Descripción |
|---|---|---|
| `supplier_id` | integer | **Requerido desde el frontend** — proveedor seleccionado |
| `date_from` | date `Y-m-d` | Inicio del período (opcional) |
| `date_to` | date `Y-m-d` | Fin del período (opcional) |

**Respuesta exitosa `200`:**

```json
{
  "success": true,
  "message": "Órdenes consolidables",
  "data": {
    "in_range": [
      {
        "id": 12,
        "code": "OC-2026-00012",
        "status": "received",
        "supplier_id": 3,
        "warehouse_id": 1,
        "subtotal": "250000.00",
        "tax_amount": "47500.00",
        "total_amount": "297500.00",
        "received_at": "2026-08-05T14:30:00+00:00",
        "created_at": "2026-08-02T09:00:00+00:00",
        "supplier": { "id": 3, "name": "Proveedor XYZ" },
        "warehouse": { "id": 1, "name": "Almacén Central", "code": "ALM-01" },
        "items": [ /* ver estructura de item en sección 2.2.1 */ ]
      }
    ],
    "pending": [
      {
        "id": 8,
        "code": "OC-2026-00008",
        "status": "received",
        "supplier_id": 3,
        "received_at": "2026-07-10T10:00:00+00:00",
        "created_at": "2026-07-08T08:00:00+00:00",
        "supplier": { "id": 3, "name": "Proveedor XYZ" },
        "warehouse": { "id": 1, "name": "Almacén Central", "code": "ALM-01" },
        "items": [ /* ... */ ]
      }
    ]
  }
}
```

**Diferencia entre `in_range` y `pending`:**

- **`in_range`** — OC recibidas, no consolidadas, cuya fecha de creación cae **dentro**
  del rango `date_from`/`date_to`.
- **`pending`** — OC recibidas, no consolidadas, cuya fecha de creación es **anterior**
  a `date_from`. Son OC de períodos anteriores que quedaron sin consolidar.
  Mostrarlas siempre con advertencia visual para que el usuario decida si las incluye.
- Si no se envían fechas, **todas** las OC aparecen en `in_range` y `pending` queda vacío.

#### 2.2.1 Estructura de cada item dentro de la OC

```json
{
  "id": 45,
  "product_variant_id": 7,
  "product_presentation_id": 2,
  "quantity_requested": "10.000",
  "quantity_received": "10.000",
  "unit_price": "25000.00",
  "tax_rate": "19.00",
  "tax_amount": "47500.00",
  "total_price": "297500.00",
  "variant": {
    "id": 7,
    "lab_brand": "Genfar",
    "brand_sku": "GEN-001",
    "generic": {
      "id": 4,
      "name": "Ibuprofeno 400mg",
      "barcode": "000004"
    }
  },
  "presentation": {
    "id": 2,
    "code": "CAJ-10",
    "name": "Caja x 10"
  }
}
```

---

### 2.3 Vista previa de consolidación ← **paso 3 del wizard**

```
POST /api/v1/purchase-orders/consolidation-preview
Permiso: ordenes_compra.ver
Content-Type: application/json
```

Recibe los IDs de las OC seleccionadas y devuelve los productos ya **agrupados** con los
totales calculados, **sin persistir nada**. Usar para renderizar el paso 3 de confirmación
antes de que el usuario haga clic en "Crear".

**Regla de agrupamiento aplicada por el backend:**

> Una fila = combinación única de `product_variant_id + product_presentation_id + unit_price`.
> El mismo producto con precios distintos genera filas separadas. La cantidad es la suma
> de `quantity_received` de todas las OC que aportaron esa combinación.

**Body:**

```json
{
  "purchase_order_ids": [12, 15, 18, 21]
}
```

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `purchase_order_ids` | integer[] | Sí (mín. 1) | IDs de las OC seleccionadas en paso 2 |

**Respuesta exitosa `200`:**

```json
{
  "success": true,
  "message": "Vista previa de consolidación",
  "data": {
    "supplier": {
      "id": 5,
      "name": "BIOCARE SAS",
      "tax_id": "900123456-1"
    },
    "purchase_orders": [
      {
        "id": 12,
        "code": "OC-2026-00002",
        "total_amount": 1031740.00,
        "warehouse": { "id": 3, "name": "Consultorio" }
      },
      {
        "id": 15,
        "code": "OC-2026-00005",
        "total_amount": 2567800.00,
        "warehouse": { "id": 1, "name": "Principal" }
      }
    ],
    "items": [
      {
        "product_variant_id": 8,
        "product_presentation_id": 4,
        "quantity": 4,
        "unit_price": 258000.00,
        "tax_rate": 0.00,
        "subtotal": 1032000.00,
        "tax_amount": 0.00,
        "total_price": 1032000.00,
        "source_order_codes": ["OC-2026-00002", "OC-2026-00007"],
        "variant": {
          "id": 8,
          "lab_brand": "Versalu",
          "brand_sku": "ADR-1ML",
          "generic": { "id": 3, "name": "ADRENALINA 1 ML" }
        },
        "presentation": { "id": 4, "code": "6PK", "name": "Six Pack" }
      },
      {
        "product_variant_id": 8,
        "product_presentation_id": 4,
        "quantity": 5,
        "unit_price": 260000.00,
        "tax_rate": 0.00,
        "subtotal": 1300000.00,
        "tax_amount": 0.00,
        "total_price": 1300000.00,
        "source_order_codes": ["OC-2026-00005"],
        "variant": {
          "id": 8,
          "lab_brand": "Versalu",
          "brand_sku": "ADR-1ML",
          "generic": { "id": 3, "name": "ADRENALINA 1 ML" }
        },
        "presentation": { "id": 4, "code": "6PK", "name": "Six Pack" }
      }
    ],
    "subtotal": 3944800.00,
    "tax_breakdown": [
      { "rate": 0,  "taxable_base": 3665800.00, "tax_amount": 0.00    },
      { "rate": 19, "taxable_base": 1485000.00, "tax_amount": 282150.00 }
    ],
    "tax_amount": 79470.00,
    "total_amount": 4024270.00
  }
}
```

**Los ítems vienen ordenados alfabéticamente** por nombre de producto genérico (`variant.generic.name`).

**Mapeo de campos para la tabla del paso 3:**

| Columna UI | Campo en `items[]` |
|---|---|
| Producto | `variant.generic.name` |
| Marca | `variant.lab_brand` |
| Presentación | `presentation.name` |
| Cant. total | `quantity` |
| P. Unit. | `unit_price` |
| % IVA | `tax_rate` (e.g. `19`, `5`, `0`) |
| Valor IVA línea | `tax_amount` |
| Total línea | `total_price` |
| OC origen | `source_order_codes` (array — renderizar en tipografía monoespaciada pequeña) |

**Lista compacta de OC incluidas** (encabezado del paso 3):
usar el array `purchase_orders[]` — cada entrada tiene `code`, `warehouse.name` y `total_amount`.

**Totales del pie** — campos del objeto raíz:

| Campo | Descripción |
|---|---|
| `subtotal` | Base gravable total (sin IVA) |
| `tax_breakdown` | Array con el IVA desglosado por tasa (ver abajo) |
| `tax_amount` | Total IVA (suma de todas las tasas) |
| `total_amount` | Total general (subtotal + tax_amount) |

**Estructura de `tax_breakdown`** — ordenado por tasa ascendente:

```json
"tax_breakdown": [
  { "rate": 0,  "taxable_base": 500000.00, "tax_amount": 0.00    },
  { "rate": 5,  "taxable_base": 200000.00, "tax_amount": 10000.00 },
  { "rate": 19, "taxable_base": 350000.00, "tax_amount": 66500.00 }
]
```

| Campo | Descripción |
|---|---|
| `rate` | Porcentaje de IVA (0, 5, 19…) |
| `taxable_base` | Suma de subtotales de los ítems que tienen esta tasa |
| `tax_amount` | IVA generado para esta tasa |

Renderizar una fila por cada entrada del array: `IVA {rate}% → $ {tax_amount}`.
Solo aparecen las tasas que efectivamente se usaron en la consolidación.

**Errores posibles:**

| HTTP | Mensaje | Causa |
|---|---|---|
| `422` | `"Una o más órdenes de compra no fueron encontradas."` | Algún ID no existe |
| `422` | `"Solo se pueden consolidar órdenes en estado 'received'. Inválidas: OC-…"` | OC no recibida |
| `422` | `"Las siguientes órdenes ya fueron consolidadas: OC-…"` | OC ya consolidada |
| `422` | `"Todas las órdenes deben pertenecer al mismo proveedor."` | Proveedor mezclado |
| `422` (validación) | `"Debe seleccionar al menos una orden de compra."` | Array vacío o ausente |

```json
{ "success": false, "message": "Las siguientes órdenes ya fueron consolidadas: OC-2026-00008.", "data": null }
```

---

### 2.4 Crear un consolidado

```
POST /api/v1/consolidated-orders
Permiso: ordenes_compra.consolidar
Content-Type: application/json
```

**Body:**

```json
{
  "purchase_order_ids": [12, 8, 15],
  "notes": "Consolidado agosto 2026 — factura 00123"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `purchase_order_ids` | integer[] | Sí (mín. 1) | IDs de las OC a consolidar |
| `notes` | string | No | Observación libre (máx. 1000 caracteres) |

**Respuesta exitosa `201`:**

```json
{
  "success": true,
  "message": "Orden consolidada creada",
  "data": {
    "id": 1,
    "code": "OCC-2026-00001",
    "supplier_id": 3,
    "period_from": "2026-07-08",
    "period_to": "2026-08-02",
    "subtotal": "850000.00",
    "tax_amount": "161500.00",
    "total_amount": "1011500.00",
    "notes": "Consolidado agosto 2026 — factura 00123",
    "created_by": 1,
    "created_at": "2026-08-10T16:00:00+00:00",
    "supplier": { "id": 3, "name": "Proveedor XYZ" },
    "items": [
      {
        "id": 1,
        "product_variant_id": 7,
        "product_presentation_id": 2,
        "quantity": "20.000",
        "unit_price": "25000.00",
        "tax_rate": "19.00",
        "tax_amount": "95000.00",
        "total_price": "595000.00",
        "variant": {
          "id": 7,
          "lab_brand": "Genfar",
          "brand_sku": "GEN-001",
          "generic": { "id": 4, "name": "Ibuprofeno 400mg", "barcode": "000004" }
        },
        "presentation": { "id": 2, "code": "CAJ-10", "name": "Caja x 10" }
      }
    ],
    "purchase_orders": [
      {
        "id": 12,
        "code": "OC-2026-00012",
        "status": "received",
        "total_amount": "297500.00"
      }
    ]
  }
}
```

`period_from` / `period_to` son calculados automáticamente por el backend a partir de la
fecha de creación más antigua y más reciente entre las OC incluidas.

**Errores posibles:**

| HTTP | Escenario |
|---|---|
| `422` | `purchase_order_ids` vacío o algún ID no existe en la base de datos |
| `409` | Alguna OC no está en estado `received`, ya fue consolidada, o se mezclaron proveedores distintos |

```json
{ "success": false, "message": "Las siguientes órdenes ya fueron consolidadas: OC-2026-00008." }
{ "success": false, "message": "Solo se pueden consolidar órdenes en estado 'received'. Inválidas: OC-2026-00015." }
{ "success": false, "message": "Todas las órdenes deben pertenecer al mismo proveedor." }
```

---

### 2.5 Listar consolidados

```
GET /api/v1/consolidated-orders
Permiso: ordenes_compra.ver
```

**Query params opcionales:**

| Param | Tipo | Descripción |
|---|---|---|
| `supplier_id` | integer | Filtrar por proveedor |
| `date_from` | date `Y-m-d` | Desde (fecha de creación del consolidado) |
| `date_to` | date `Y-m-d` | Hasta (fecha de creación del consolidado) |
| `per_page` | integer | Ítems por página (default 25, máx. 100) |
| `page` | integer | Página |

**Respuesta `200` (paginada):** array de consolidados sin `items` ni `purchase_orders`
(se cargan solo en el detalle). Incluye `supplier` y `createdBy`.

---

### 2.6 Detalle de un consolidado

```
GET /api/v1/consolidated-orders/{id}
Permiso: ordenes_compra.ver
```

Devuelve el consolidado completo con `items` (líneas agregadas) y `purchase_orders`
(las OC originales incluidas, cada una con sus propios ítems).

```json
{
  "success": true,
  "message": "Detalle de orden consolidada",
  "data": {
    "id": 1,
    "code": "OCC-2026-00001",
    "supplier_id": 3,
    "period_from": "2026-07-08",
    "period_to": "2026-08-02",
    "subtotal": "850000.00",
    "tax_amount": "161500.00",
    "total_amount": "1011500.00",
    "notes": "...",
    "supplier": { "id": 3, "name": "Proveedor XYZ" },
    "items": [ /* líneas consolidadas */ ],
    "purchase_orders": [
      {
        "id": 8,
        "code": "OC-2026-00008",
        "status": "received",
        "total_amount": "297500.00",
        "received_at": "2026-07-10T10:00:00+00:00",
        "warehouse": { "id": 1, "name": "Almacén Central", "code": "ALM-01" },
        "items": [ /* ítems originales de esta OC */ ]
      }
    ]
  }
}
```

---

## 3. Flujo completo paso a paso

### Paso 1 — Selección de proveedor

1. El usuario abre la pantalla **"Consolidar OC"**.
2. El frontend llama a `GET /api/v1/purchase-orders/consolidable-suppliers` (sin
   parámetros).
3. Se muestra un **selector/dropdown** con los proveedores devueltos.
   - Si el array llega vacío: mostrar _"No hay órdenes pendientes de consolidar"_ y
     deshabilitar el resto del formulario.
4. El usuario elige un proveedor. A partir de este momento todas las OC que se listen
   pertenecen a ese proveedor — no es posible mezclar proveedores.

### Paso 2 — Selección del período y carga de OC

5. Con el proveedor ya elegido, el usuario puede (opcionalmente) ingresar `date_from` y
   `date_to`.
6. El frontend llama a
   `GET /api/v1/purchase-orders/consolidable?supplier_id={id}&date_from=…&date_to=…`.
7. Se renderizan **dos secciones**:
   - **"Órdenes en el período"** → array `data.in_range`
   - **"Pendientes de períodos anteriores"** → array `data.pending` (mostrar con badge o
     alerta de advertencia si no está vacío, para que el usuario las note)

### Paso 3 — Selección de OC a consolidar

8. El usuario marca mediante checkboxes las OC que quiere incluir (de cualquier sección).
9. Mostrar un **resumen en tiempo real** mientras selecciona:
   - Número de OC seleccionadas
   - Subtotal acumulado
   - IVA acumulado
   - Total acumulado

### Paso 3 → 4 — Vista previa agrupada

10. El usuario hace clic en **"Siguiente"** / avanza al paso 3 de confirmación.
11. El frontend llama `POST /api/v1/purchase-orders/consolidation-preview` con los IDs
    seleccionados.
12. Con la respuesta se renderiza:
    - **Lista compacta de OC incluidas**: recorrer `data.purchase_orders[]` mostrando
      `code`, `warehouse.name` y `total_amount` por fila.
    - **Tabla de productos agrupados**: recorrer `data.items[]` — ver mapeo en sección 2.3.
    - **Totales**: `data.subtotal`, `data.tax_amount`, `data.total_amount`.
    - **Campo de observaciones** (texto libre, opcional, máx. 1000 chars).
13. Si la respuesta es `422`: mostrar el mensaje de error y no avanzar al paso de creación.

### Paso 4 — Confirmación y creación

14. El usuario revisa la vista previa y hace clic en **"Crear consolidado"**.
15. El frontend llama `POST /api/v1/consolidated-orders` con el array de IDs y las notas.
16. Si la respuesta es `201`:
    - Mostrar mensaje de éxito con el código generado (`OCC-YYYY-NNNNN`).
    - Redirigir al detalle del consolidado o al listado.
    - Las OC consolidadas dejan de aparecer en próximas consultas a `/consolidable` y
      `/consolidable-suppliers` — el backend las bloquea automáticamente.
17. Si la respuesta es `422`: mostrar el mensaje devuelto por la API tal cual.

### Paso 5 — Impresión

18. Desde el detalle del consolidado, renderizar el documento imprimible usando los datos
    de `items` y la información del `supplier`. Ver sección 4.

---

## 4. Estructura sugerida del documento imprimible

```
┌─────────────────────────────────────────────────────────────────┐
│  ORDEN DE COMPRA CONSOLIDADA           Código: OCC-2026-00001   │
│                                        Fecha:  10/08/2026       │
├─────────────────────────────────────────────────────────────────┤
│  Proveedor:  Proveedor XYZ                                       │
│  Período:    08/07/2026  –  02/08/2026                          │
│  Órdenes incluidas:  OC-2026-00008, OC-2026-00012               │
├──────────────────────────┬──────────┬───────────┬───────────────┤
│  Producto / Presentación │ Cantidad │ Vlr. Unit │ Total         │
├──────────────────────────┼──────────┼───────────┼───────────────┤
│  Ibuprofeno 400mg        │  20.000  │ $25.000   │ $500.000      │
│  Caja x 10               │          │           │               │
├──────────────────────────┼──────────┼───────────┼───────────────┤
│  Amoxicilina 500mg       │   8.000  │ $43.750   │ $350.000      │
│  Blíster x 10            │          │ (p.d. 19%)│ +$66.500 IVA  │
├──────────────────────────┴──────────┴───────────┴───────────────┤
│                             Subtotal:          $850.000          │
│                             IVA:               $161.500          │
│                             TOTAL:           $1.011.500          │
└─────────────────────────────────────────────────────────────────┘
```

**Campos del encabezado:**

| Campo | Fuente en la API |
|---|---|
| Código | `data.code` |
| Fecha de generación | `data.created_at` |
| Proveedor | `data.supplier.name` |
| Período cubierto | `data.period_from` – `data.period_to` |
| Órdenes incluidas | `data.purchase_orders[].code` (lista separada por comas) |
| Elaborado por | `data.createdBy.name` |

**Campos de cada línea (fuente: `data.items`):**

| Campo | Fuente |
|---|---|
| Nombre del producto | `item.variant.generic.name` |
| Presentación | `item.presentation.name` |
| Marca / SKU | `item.variant.lab_brand` + `item.variant.brand_sku` |
| Cantidad | `item.quantity` |
| Precio unitario | `item.unit_price` |
| % IVA | `item.tax_rate` |
| Valor IVA | `item.tax_amount` |
| Total línea | `item.total_price` |

**Pie del documento:**

| Campo | Fuente |
|---|---|
| Subtotal | `data.subtotal` |
| Total IVA | `data.tax_amount` |
| Total general | `data.total_amount` |
| Observaciones | `data.notes` |

---

## 5. Indicador de OC bloqueada en la pantalla de OC

Cuando una OC ya fue consolidada, el campo `consolidated_order_id` en la respuesta de
`GET /api/v1/purchase-orders/{id}` (y en el listado de OC) ya no es `null`.

Usa ese campo para:
- Mostrar un badge **"Consolidada"** en el listado de OC junto al estado.
- Deshabilitar las acciones que no aplican sobre una OC consolidada.
- Mostrar un enlace **"Ver consolidado"** que navega al detalle
  `GET /api/v1/consolidated-orders/{consolidated_order_id}`.

```json
{
  "id": 12,
  "code": "OC-2026-00012",
  "status": "received",
  "consolidated_order_id": 1
}
```

---

## 6. Resumen de endpoints del módulo

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/v1/purchase-orders/consolidable-suppliers` | Proveedores con OC consolidables (paso 1) |
| `GET` | `/api/v1/purchase-orders/consolidable` | OC consolidables de un proveedor (paso 2) |
| `POST` | `/api/v1/purchase-orders/consolidation-preview` | Vista previa agrupada sin persistir (paso 3) |
| `POST` | `/api/v1/consolidated-orders` | Crear consolidado (paso 4) |
| `GET` | `/api/v1/consolidated-orders` | Listar consolidados |
| `GET` | `/api/v1/consolidated-orders/{id}` | Detalle de un consolidado |

---

## 7. Resumen de permisos por acción de UI

| Acción UI | Permiso necesario |
|---|---|
| Ver selector de proveedores | `ordenes_compra.ver` |
| Ver listado de OC consolidables | `ordenes_compra.ver` |
| Ver vista previa agrupada (paso 3) | `ordenes_compra.ver` |
| Ver listado de consolidados | `ordenes_compra.ver` |
| Ver detalle de un consolidado | `ordenes_compra.ver` |
| Imprimir consolidado | `ordenes_compra.ver` |
| Crear un consolidado | `ordenes_compra.consolidar` |

Roles que tienen `ordenes_compra.consolidar` por defecto:
`super_administrador`, `administrador`, `compras`, `jefe_almacen`.
