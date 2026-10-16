# Guía Frontend — Firma Digital de Movimientos de Inventario

## Índice

1. [Visión general del flujo](#1-visión-general-del-flujo)
2. [Tipos de movimiento y comportamiento](#2-tipos-de-movimiento-y-comportamiento)
3. [Permisos requeridos](#3-permisos-requeridos)
4. [Endpoints](#4-endpoints)
5. [Flujo paso a paso](#5-flujo-paso-a-paso)
6. [Captura de la firma en canvas](#6-captura-de-la-firma-en-canvas)
7. [Gestión de estados en la UI](#7-gestión-de-estados-en-la-ui)
8. [Manejo de errores](#8-manejo-de-errores)
9. [Ejemplos completos](#9-ejemplos-completos)
10. [Firma registrada ("Mi firma") y PIN](#10-firma-registrada-mi-firma-y-pin)

---

## 1. Visión general del flujo

Los movimientos de inventario (salidas, transferencias, ajustes, devoluciones, bajas, pérdidas) siguen un flujo **de dos fases**:

```
┌─────────────────────────────────────────────────────────────┐
│  FASE 1 — Crear movimiento                                  │
│  POST /v1/movements/{tipo}                                  │
│  → Status: pending_signature                                │
│  → El stock NO cambia todavía                               │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  FASE 2 — Confirmar con firmas                              │
│  POST /v1/movements/{id}/confirm                            │
│  → Se registran las firmas de quien entrega y quien recibe  │
│  → El stock se actualiza en este momento                    │
│  → Status: confirmed                                        │
└─────────────────────────────────────────────────────────────┘
```

**Excepción:** Las salidas a **pacientes** (centro de costo externo con `patient_document`) se confirman automáticamente en la Fase 1. No requieren firma y el stock se descuenta de inmediato.

---

## 2. Tipos de movimiento y comportamiento

| Endpoint de creación | Tipo | ¿Requiere firma? | Respuesta Fase 1 |
|---|---|---|---|
| `POST /v1/movements/exit` | Salida interna | **Sí** | Array de movimientos |
| `POST /v1/movements/exit` | Salida a paciente | No (auto-confirmado) | Array de movimientos |
| `POST /v1/movements/transfer` | Transferencia | **Sí** | Array de movimientos |
| `POST /v1/movements/adjustment` | Ajuste | **Sí** | Un movimiento |
| `POST /v1/movements/return` | Devolución a proveedor | **Sí** | Un movimiento |
| `POST /v1/movements/write-off` | Baja por vencimiento | **Sí** | Un movimiento |
| `POST /v1/movements/loss` | Pérdida/robo | **Sí** | Un movimiento |
| `POST /v1/movements/entry` | Entrada de mercancía | No aplica | Un movimiento |

> **Salidas y transferencias pueden generar múltiples movimientos** si el sistema necesita tomar stock de varios lotes (FEFO). Cada movimiento debe confirmarse individualmente.

---

## 3. Permisos requeridos

| Acción | Permiso |
|---|---|
| Crear cualquier movimiento | El permiso específico del tipo (`movimientos.salida`, etc.) |
| Ver listado y detalle | `stock.ver` |
| Confirmar movimiento (firmar) | `movimientos.confirmar` |
| Cancelar movimiento pendiente | `movimientos.cancelar` |
| Ver imagen de firma | `stock.ver` |

---

## 4. Endpoints

### 4.1 Crear movimiento

#### Salida interna
```
POST /api/v1/movements/exit
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id":     1,          // requerido
  "warehouse_id":   2,          // requerido
  "location_id":    5,          // opcional
  "quantity":       10,         // requerido, entero positivo
  "cost_center_id": 3,          // requerido
  "service_id":     null,       // requerido si el centro de costo es externo
  "reason":         "Cirugía"   // opcional
}
```

#### Salida a paciente (auto-confirmada, sin firma)
```
POST /api/v1/movements/exit
{
  "product_id":         1,
  "warehouse_id":       2,
  "quantity":           5,
  "cost_center_id":     4,        // debe ser un centro de costo de tipo "external"
  "service_id":         7,
  "patient_document":   "12345678",  // dispara el flujo sin firma
  "patient_external_id": "HC-001"    // opcional, ID historia clínica
}
```

#### Transferencia
```
POST /api/v1/movements/transfer
{
  "product_id":       1,
  "warehouse_from_id": 2,
  "warehouse_to_id":   3,
  "location_from_id":  5,   // requerido
  "location_to_id":    9,   // requerido
  "quantity":          20,
  "reason":            "Reposición sucursal"
}
```

#### Ajuste de inventario
```
POST /api/v1/movements/adjustment
{
  "product_id":   1,
  "warehouse_id": 2,
  "location_id":  5,    // requerido
  "quantity":     -3,   // negativo = descuento, positivo = incremento
  "reason":       "Conteo físico"  // requerido
}
```

#### Devolución a proveedor
```
POST /api/v1/movements/return
{
  "product_id":   1,
  "warehouse_id": 2,
  "location_id":  5,   // opcional
  "quantity":     10,
  "reason":       "Producto defectuoso"
}
```

#### Baja por vencimiento
```
POST /api/v1/movements/write-off
{
  "product_id":   1,
  "warehouse_id": 2,
  "batch_id":     7,   // requerido: lote específico a dar de baja
  "reason":       "Lote vencido enero 2026"
}
```

#### Pérdida / robo
```
POST /api/v1/movements/loss
{
  "product_id":   1,
  "warehouse_id": 2,
  "batch_id":     7,   // requerido
  "location_id":  5,   // opcional
  "quantity":     2,
  "reason":       "Robo"
}
```

---

### 4.2 Respuesta de creación (Fase 1)

Para movimientos que generan **un solo registro** (ajuste, devolución, baja, pérdida):

```json
{
  "success": true,
  "message": "Ajuste registrado exitosamente",
  "data": {
    "id": 42,
    "warehouse_id": 2,
    "warehouse_to_id": null,
    "product_id": 1,
    "batch_id": 7,
    "movement_type": "adjustment",
    "quantity": -3,
    "reason": "Conteo físico",
    "status": "pending_signature",
    "created_at": "2026-07-04T17:00:00+00:00",
    "product_name": "Agua destilada 21G",
    "batch_lot_number": "LOT-2026-001",
    "batch_expiration_date": "2027-01-15",
    "user_name": "Juan Pérez",
    "signatures": null
  }
}
```

Para movimientos que pueden generar **múltiples registros** (salida, transferencia):

```json
{
  "success": true,
  "message": "Salida registrada exitosamente",
  "data": [
    {
      "id": 43,
      "status": "pending_signature",
      "batch_id": 7,
      "quantity": -8,
      ...
    },
    {
      "id": 44,
      "status": "pending_signature",
      "batch_id": 9,
      "quantity": -2,
      ...
    }
  ]
}
```

> El campo `status: "pending_signature"` indica que el movimiento espera firmas. `status: "confirmed"` indica que ya fue aplicado al inventario.

---

### 4.3 Confirmar movimiento (Fase 2)

```
POST /api/v1/movement-documents/{id}/confirm
Authorization: Bearer {token}
Content-Type: application/json
```

Cada rol puede firmar de dos formas (ver [sección 10](#10-firma-registrada-mi-firma-y-pin)):

| Rol | Forma | Qué enviar |
|---|---|---|
| Quien entrega | **Firma registrada** (por defecto) | Omitir `delivered_by`: se usa la firma del usuario autenticado |
| Quien entrega | Manual (pad) | `delivered_by: { name, document, signature }` |
| Quien recibe | **PIN** (usuario registrado) | `received_by: { pin: "483" }` |
| Quien recibe | Manual (externo / sin registro) | `received_by: { name, document, signature }` |

**Caso típico (entrega con firma registrada, recibe con PIN):**
```json
{
  "received_by": { "pin": "483" }
}
```

**Receptor externo:**
```json
{
  "received_by": {
    "name":      "María López",          // requerido sin pin, máx. 150
    "document":  "87654321",             // requerido sin pin, máx. 50
    "signature": "data:image/png;base64,iVBORw0KGgo..."
  }
}
```

**Flujo original (ambos manuales) — sigue funcionando:**
```json
{
  "delivered_by": { "name": "Juan Pérez", "document": "12345678", "signature": "data:image/png;base64,..." },
  "received_by":  { "name": "María López", "document": "87654321", "signature": "data:image/png;base64,..." }
}
```

> Si llega `received_by.pin`, los demás campos de `received_by` se ignoran.

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Documento confirmado exitosamente",
  "data": {
    "id": 42,
    "status": "confirmed",
    "signatures": [
      {
        "id": 1,
        "role": "delivered_by",
        "method": "stored",
        "signer_name": "Juan Pérez",
        "signer_document": "CC 12345678",
        "signature_data": "data:image/png;base64,...",
        "signed_at": "2026-10-16T17:05:00+00:00"
      },
      {
        "id": 2,
        "role": "received_by",
        "method": "pin",
        "signer_name": "María López",
        "signer_document": "CC 87654321",
        "signature_data": "data:image/png;base64,...",
        "signed_at": "2026-10-16T17:05:00+00:00"
      }
    ],
    ...resto del documento
  }
}
```

- `method`: `pad` (dibujada), `stored` (firma registrada de quien confirma) o `pin` (firma registrada validada con PIN).
- Con firma registrada o PIN el frontend **no conoce de antemano** nombre, documento ni imagen: para imprimir el comprobante use `data.signatures` de esta respuesta.
- La firma se copia al comprobante: si el usuario actualiza su firma después, los comprobantes ya emitidos no cambian.

---

### 4.4 Cancelar movimiento pendiente

Solo funciona si el movimiento está en `pending_signature`. No tiene efecto en el stock (este nunca se modificó).

```
DELETE /api/v1/movements/{id}/pending
Authorization: Bearer {token}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Movimiento pendiente eliminado exitosamente",
  "data": null
}
```

**Error si ya estaba confirmado (422):**
```json
{
  "success": false,
  "message": "Solo se pueden eliminar movimientos pendientes de firma."
}
```

---

### 4.5 Obtener imagen de firma

```
GET /api/v1/movements/{id}/signature/{role}
```

`role` es `delivered_by` o `received_by`.

**Respuesta (200):**
```json
{
  "success": true,
  "message": "Firma del movimiento",
  "data": {
    "role": "delivered_by",
    "signer_name": "Juan Pérez",
    "signer_document": "12345678",
    "signature_data": "data:image/png;base64,iVBORw0KGgo...",
    "signed_at": "2026-07-04T17:05:00+00:00"
  }
}
```

Para mostrar la imagen en el frontend:
```html
<img :src="signature.signature_data" alt="Firma de Juan Pérez" />
```

---

### 4.6 Listado de movimientos con filtro por estado

```
GET /api/v1/movements?status=pending_signature
GET /api/v1/movements?status=confirmed
```

Parámetros de filtro disponibles:

| Parámetro | Tipo | Descripción |
|---|---|---|
| `status` | string | `pending_signature` o `confirmed` |
| `warehouse_id` | integer | Filtrar por almacén |
| `movement_type` | string | `exit`, `transfer`, `adjustment`, `return`, `expiration_write_off`, `loss` |
| `product_id` | integer | Filtrar por producto |
| `cost_center_id` | integer | Filtrar por centro de costo |
| `date_from` | string (Y-m-d) | Fecha inicio |
| `date_to` | string (Y-m-d) | Fecha fin |
| `per_page` | integer | Registros por página (máx. 100) |

---

### 4.7 Detalle de un movimiento

```
GET /api/v1/movements/{id}
```

Incluye las firmas (sin `signature_data`) si el movimiento está confirmado:
```json
{
  "data": {
    "id": 42,
    "status": "confirmed",
    "signatures": [
      { "role": "delivered_by", "signer_name": "Juan Pérez", ... },
      { "role": "received_by",  "signer_name": "María López", ... }
    ]
  }
}
```

---

## 5. Flujo paso a paso

### Caso típico: salida interna con un lote

```
1. Usuario llena formulario de salida
2. POST /movements/exit  →  { id: 42, status: "pending_signature" }
3. UI muestra pantalla de firma con dos campos: "Quien entrega" y "Quien recibe"
4. Cada persona escribe su nombre, documento y dibuja la firma en canvas
5. POST /movements/42/confirm  →  { status: "confirmed" }
6. UI muestra confirmación y permite imprimir PDF
```

### Caso con múltiples lotes (FEFO)

```
1. POST /movements/exit  →  data: [ { id: 43 }, { id: 44 } ]
   (el sistema tomó stock de dos lotes diferentes)
2. UI muestra aviso: "Este movimiento abarca 2 lotes — se firmarán ambos"
3. Primer canvas de firmas → POST /movements/43/confirm
4. Segundo canvas de firmas → POST /movements/44/confirm
   (puede reutilizar los mismos datos de firma para ambos confirmaciones)
5. Ambos confirmados → mostrar resumen
```

### Caso: salida a paciente (sin firma)

```
1. POST /movements/exit con patient_document  →  { status: "confirmed" }
2. No mostrar pantalla de firma
3. UI muestra confirmación directamente
```

---

## 6. Captura de la firma en canvas

### HTML mínimo

```html
<canvas
  id="signature-canvas"
  width="400"
  height="200"
  style="border: 1px solid #ccc; touch-action: none;"
></canvas>
<button onclick="clearSignature()">Limpiar</button>
```

### JavaScript vanilla

```javascript
const canvas = document.getElementById('signature-canvas');
const ctx    = canvas.getContext('2d');
let drawing  = false;

canvas.addEventListener('pointerdown', e => {
  drawing = true;
  ctx.beginPath();
  ctx.moveTo(e.offsetX, e.offsetY);
});

canvas.addEventListener('pointermove', e => {
  if (!drawing) return;
  ctx.lineTo(e.offsetX, e.offsetY);
  ctx.strokeStyle = '#000';
  ctx.lineWidth   = 2;
  ctx.lineCap     = 'round';
  ctx.stroke();
});

canvas.addEventListener('pointerup',    () => { drawing = false; });
canvas.addEventListener('pointerleave', () => { drawing = false; });

function clearSignature() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function getSignatureBase64() {
  return canvas.toDataURL('image/png');
  // Retorna: "data:image/png;base64,iVBORw0KGgo..."
}

function isSignatureEmpty() {
  const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
  return !pixels.some(channel => channel !== 0);
}
```

### Vue 3 (ejemplo de componente)

```vue
<template>
  <div>
    <canvas
      ref="canvasRef"
      width="400"
      height="200"
      style="border: 1px solid #ccc; touch-action: none;"
      @pointerdown="startDraw"
      @pointermove="draw"
      @pointerup="stopDraw"
      @pointerleave="stopDraw"
    />
    <button @click="clear">Limpiar</button>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const canvasRef = ref(null);
let drawing = false;

function getCtx() { return canvasRef.value.getContext('2d'); }

function startDraw(e) {
  drawing = true;
  getCtx().beginPath();
  getCtx().moveTo(e.offsetX, e.offsetY);
}

function draw(e) {
  if (!drawing) return;
  const ctx = getCtx();
  ctx.lineTo(e.offsetX, e.offsetY);
  ctx.strokeStyle = '#000';
  ctx.lineWidth   = 2;
  ctx.lineCap     = 'round';
  ctx.stroke();
}

function stopDraw() { drawing = false; }

function clear() {
  const c = canvasRef.value;
  getCtx().clearRect(0, 0, c.width, c.height);
}

function getBase64() {
  return canvasRef.value.toDataURL('image/png');
}

defineExpose({ getBase64, clear });
</script>
```

### Validación antes de enviar

```javascript
// Verificar que la firma no está vacía
function isCanvasEmpty(canvas) {
  const ctx    = canvas.getContext('2d');
  const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
  return !pixels.some(v => v !== 0);
}

// Antes de llamar al endpoint confirm:
if (isCanvasEmpty(deliveredByCanvas)) {
  alert('La firma de quien entrega es obligatoria.');
  return;
}
if (isCanvasEmpty(receivedByCanvas)) {
  alert('La firma de quien recibe es obligatoria.');
  return;
}
```

---

## 7. Gestión de estados en la UI

### Campo `status` en la respuesta

| Valor | Significado | Acción disponible |
|---|---|---|
| `pending_signature` | Creado, esperando firmas. Stock sin cambiar. | Confirmar / Cancelar |
| `confirmed` | Firmado y aplicado al inventario. | Ver PDF / Ver firmas |

### Indicadores visuales sugeridos

```
pending_signature  →  🟡 Pendiente de firma
confirmed          →  🟢 Confirmado
```

### ¿Cuándo mostrar el botón de imprimir PDF?

Solo cuando `status === "confirmed"`. Un movimiento `pending_signature` no tiene PDF disponible (el stock no ha sido aplicado y el documento no está completo).

### Lista de pendientes

```
GET /api/v1/movements?status=pending_signature&warehouse_id={id}
```

Útil para construir una bandeja de movimientos que esperan firma. Los movimientos pendientes de más de 30 días son eliminados automáticamente por el sistema cada noche.

---

## 8. Manejo de errores

### Errores comunes en creación (Fase 1)

| HTTP | `message` típico | Causa |
|---|---|---|
| 409 | Stock insuficiente | `quantity` supera lo disponible en inventario |
| 409 | Lote vencido | El único stock disponible está vencido (para salidas normales) |
| 422 | Error de validación | Campo faltante o inválido — ver `errors` en la respuesta |
| 403 | No tienes acceso al almacén indicado | Usuario sin acceso a ese almacén |

### Errores en confirmación (Fase 2)

| HTTP | Causa |
|---|---|
| 409 | El documento ya fue confirmado |
| 409 | Se omitió `delivered_by` y el usuario no tiene firma registrada (mensaje: *"No tiene una firma registrada…"*). Ofrecer firma manual |
| 409 | El PIN corresponde al mismo usuario que entrega (*"Quien recibe no puede ser la misma persona que entrega."*) |
| 422 `errors["received_by.pin"]` | PIN con formato inválido o que no corresponde a ninguna firma activa. Limpiar el campo y pedirlo de nuevo |
| 422 | Alguna firma o campo obligatorio faltante (modo manual) |
| 429 | 3 PIN fallidos en 15 minutos por el usuario que confirma. Esperar o usar firma manual |
| 404 | Documento no encontrado |

### Errores en cancelación

| HTTP | Causa |
|---|---|
| 422 | El movimiento ya estaba confirmado — no se puede cancelar |
| 404 | Movimiento no encontrado |

### Estructura de error de validación

```json
{
  "success": false,
  "message": "Error de validación.",
  "errors": {
    "delivered_by.name":      ["El nombre de quien entrega es obligatorio."],
    "delivered_by.signature": ["La firma de quien entrega es obligatoria."],
    "received_by.document":   ["El documento de quien recibe es obligatorio."]
  }
}
```

---

## 9. Ejemplos completos

### Ejemplo completo en JavaScript (fetch)

```javascript
async function registrarYFirmarSalida({ productId, warehouseId, locationId, quantity, costCenterId, canvasEntrega, canvasRecepcion, nombreEntrega, docEntrega, nombreRecepcion, docRecepcion }) {

  // FASE 1: crear el movimiento
  const createRes = await fetch('/api/v1/movements/exit', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({
      product_id:     productId,
      warehouse_id:   warehouseId,
      location_id:    locationId,
      quantity:       quantity,
      cost_center_id: costCenterId,
    }),
  });

  if (!createRes.ok) {
    const err = await createRes.json();
    throw new Error(err.message);
  }

  const createData = await createRes.json();
  // Puede ser array (multi-lote) o un objeto single
  const movements = Array.isArray(createData.data) ? createData.data : [createData.data];

  // Si ya viene confirmado (salida paciente), no hay más pasos
  if (movements[0].status === 'confirmed') {
    return movements;
  }

  // FASE 2: confirmar cada movimiento con firmas
  const signaturePayload = {
    delivered_by: {
      name:      nombreEntrega,
      document:  docEntrega,
      signature: canvasEntrega.toDataURL('image/png'),
    },
    received_by: {
      name:      nombreRecepcion,
      document:  docRecepcion,
      signature: canvasRecepcion.toDataURL('image/png'),
    },
  };

  const confirmed = await Promise.all(
    movements.map(m =>
      fetch(`/api/v1/movements/${m.id}/confirm`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify(signaturePayload),
      }).then(r => r.json())
    )
  );

  return confirmed.map(r => r.data);
}
```

### Flujo de UI recomendado

```
┌─────────────────────────────────────┐
│  Paso 1: Formulario del movimiento  │
│  (producto, cantidad, centro costo) │
│  [Registrar]                        │
└──────────────┬──────────────────────┘
               │ POST /movements/{tipo}
               │ status: pending_signature
               ▼
┌─────────────────────────────────────┐
│  Paso 2: Pantalla de firmas         │
│                                     │
│  Quien entrega                      │
│  Nombre: [__________]               │
│  Documento: [________]              │
│  Firma: [canvas  ] [Limpiar]        │
│                                     │
│  Quien recibe                       │
│  Nombre: [__________]               │
│  Documento: [________]              │
│  Firma: [canvas  ] [Limpiar]        │
│                                     │
│  [Cancelar movimiento]  [Confirmar] │
└──────────────┬──────────────────────┘
               │ POST /movements/{id}/confirm
               │ status: confirmed
               ▼
┌─────────────────────────────────────┐
│  Paso 3: Confirmación               │
│  ✅ Movimiento confirmado           │
│  [Ver detalle]  [Imprimir PDF]      │
└─────────────────────────────────────┘
```

---

## Notas adicionales

- **Tamaño del string base64:** Una firma típica de canvas (400×200 px) pesa entre 8 KB y 20 KB como string. No comprimir ni redimensionar es aceptable para el caso de uso actual.
- **touch-action: none** en el canvas es obligatorio en móviles para evitar que el scroll interfiera con el trazo.
- **No hay expiración del token de firma:** Una vez creado el movimiento `pending_signature`, puede firmarse en cualquier momento hasta que el sistema lo elimine automáticamente a los **30 días**.
- **Movimientos multi-lote:** El backend puede devolver N movimientos por una sola acción del usuario. La UI debe manejar todos y confirmar cada uno. Se recomienda reutilizar los mismos datos de firma para todos los registros de la misma operación.
- **PDF:** Se genera con el endpoint de reporte (fuera del scope de este módulo). Asegurarse de solicitarlo solo cuando `status === "confirmed"`.

---

## 10. Firma registrada ("Mi firma") y PIN

Cada usuario registra **una sola vez** su documento y su firma en *Mi perfil → Mi firma* (`/profile/signature`). Con eso:

- **Quien entrega** (el usuario de la sesión que confirma) no vuelve a firmar: su firma registrada se aplica automáticamente.
- **Quien recibe**, si es usuario del sistema, digita su **PIN de 3 dígitos** en el mismo equipo; no se pide cédula.
- **Externos o usuarios sin firma registrada** siguen firmando en el pad.

### Privacidad

Los endpoints de "Mi firma" **no reciben ID de usuario**: operan siempre sobre el usuario del token. Ningún rol (ni `super_administrador`) puede ver o cambiar la firma de otro usuario. La firma no se guarda en `localStorage`.

### Endpoints

Todos requieren `Authorization: Bearer {token}`; no requieren permisos adicionales.

#### `GET /api/v1/auth/me/signature`

```json
{
  "success": true,
  "message": "Mi firma",
  "data": {
    "signature": {
      "signer_name": "Juan Pérez",
      "document_type": "CC",
      "document_type_label": "Cédula de ciudadanía",
      "document_number": "12345678",
      "signature_data": "data:image/png;base64,...",
      "has_pin": true,
      "pin_generated_at": "2026-10-16T10:00:00+00:00",
      "registered_at": "2026-10-16T10:00:00+00:00"
    },
    "document_types": [
      { "value": "CC", "label": "Cédula de ciudadanía" },
      { "value": "CE", "label": "Cédula de extranjería" },
      { "value": "TI", "label": "Tarjeta de identidad" },
      { "value": "PA", "label": "Pasaporte" },
      { "value": "PPT", "label": "Permiso por protección temporal" }
    ]
  }
}
```

`data.signature` es `null` si el usuario aún no la registra.

#### `PUT /api/v1/auth/me/signature` — registrar o actualizar

```json
{
  "current_password": "********",
  "document_type":    "CC",
  "document_number":  "12345678",
  "signature":        "data:image/png;base64,..."
}
```

| Campo | Regla |
|---|---|
| `current_password` | Obligatoria y correcta (si alguien deja la sesión abierta no puede reemplazar la firma) |
| `document_type` | Uno de `document_types` |
| `document_number` | 4–20 caracteres, solo letras y números (sin puntos ni espacios) |
| `signature` | PNG en base64 (`data:image/png;base64,...`), máx. ~512 KB |

- **Primer registro → 201** con `data.pin` (3 dígitos). **Es la única vez que se muestra**: la UI debe pedir al usuario que lo guarde.
- **Actualización → 200** con `data.pin: null`: se crea una nueva versión de la firma y se conserva el PIN vigente.
- Límite: 10 peticiones por minuto.

#### `POST /api/v1/auth/me/signature/pin` — generar un PIN nuevo

```json
{ "current_password": "********" }
```

Respuesta 200 con `data.pin` (única vez que se muestra). El PIN anterior deja de funcionar de inmediato y queda **180 días en cuarentena**: durante ese tiempo no se asigna a ningún otro usuario. 409 si el usuario aún no registra su firma. Límite: 5 peticiones por minuto.

### Reglas del PIN

- Lo **genera el sistema** (3 dígitos, único, sin secuencias ni dígitos repetidos). El usuario no lo elige: así nadie puede descubrir el PIN de otro probando si "está disponible".
- Se almacena como HMAC, nunca en claro. Si se olvida, se regenera desde *Mi firma*.
- 3 intentos fallidos en 15 minutos (por usuario que confirma) bloquean temporalmente la confirmación con PIN → HTTP 429.
- Un PIN de un usuario inactivo no es válido.
- **Cuarentena de 180 días:** un PIN liberado al regenerarlo no se reasigna a otro usuario hasta pasados 180 días (tabla `retired_signature_pins`), para que quien conocía el PIN viejo no pueda firmar como otra persona. Actualizar la firma no libera el PIN (se conserva).
- Con 3 dígitos hay ~974 PIN válidos, que es también el máximo de usuarios con firma registrada. Los PIN en cuarentena también cuentan como no disponibles. Si se agotan, registrar firma responde 409 (*"No hay PIN de firma disponibles"*).

### Comprobante PDF

El comprobante muestra debajo de cada firma registrada la leyenda *"Firma registrada · sesión autenticada"* (entrega) o *"Firma registrada · validada con PIN"* (recibe), y el documento con su tipo (`CC 12345678`).
