# 16 · Flujos de negocio y máquinas de estado

Resumen de los flujos multi-paso que la app debe respetar. Los payloads exactos están en cada módulo.

## 1. Movimientos de inventario con firma (`05-inventory.md`)

Toda operación genera un **comprobante** (`movement_document`) que agrupa las líneas (`movements`) y las firmas. Número visible: `document_number` con formato `TIP-AAAA-NNNNNN` (`ENT`, `SAL`, `TRA`, `AJU`, `DEV`, `VEN` baja por vencimiento, `BAJ` baja/pérdida).

Tipos (`movement_type`): `entry`, `exit`, `transfer`, `adjustment`, `return`, `expiration_write_off`, `loss`. Estados del comprobante (`status`): `pending_signature`, `confirmed`.

```
POST /movements/{entry|exit|transfer|adjustment|return|write-off|loss}
        │  status = pending_signature   (el stock NO cambia todavía)
        ▼
POST /movement-documents/{id}/confirm   (permiso movimientos.confirmar)
        │  body: delivered_by? + received_by   → status = confirmed (el stock se actualiza)
        ▼
GET  /movement-documents/{id}/pdf       (comprobante firmado)

Alternativa: DELETE /movement-documents/{id}/pending   (permiso movimientos.cancelar) → solo si sigue pending_signature
```

- **Entradas** (`entry`) y **salidas a paciente** (`exit` con `patient_document`, centro de costo externo) se confirman automáticamente al crearse: no hay fase de firma.
- Salidas y traslados toman stock por **FEFO** (primero vence, primero sale) y pueden generar varias líneas (una por lote). El comprobante es único.
- **Firmantes** al confirmar:
  - `delivered_by` (quien entrega): opcional; si se omite se usa la **firma registrada** del usuario de la sesión (debe haberla registrado en `PUT /auth/me/signature`; si no, 409).
  - `received_by` (quien recibe): obligatorio. Dos modos: `{ "pin": "123" }` (usuario del sistema con firma registrada; PIN de **3 dígitos**) o `{ "name", "document", "signature": "data:image/png;base64,..." }` (externo, firma en pantalla).
  - Errores: `422` en `received_by.pin` (PIN inválido), `429` tras varios intentos fallidos, `409` si entrega y recibe son la misma persona o falta la firma registrada.
- `GET /movement-documents/{id}/signature/{role}` devuelve la imagen de firma (`role` = `delivered_by` | `received_by`). Las respuestas normales de firmas no incluyen la imagen.
- `POST /movement-documents/{id}/send-email` envía el comprobante a una lista de correos (`recipients[]`).
- Fecha del movimiento (`movement_date`): opcional, no puede ser futura.

## 2. Registro / cambio de "Mi firma" (`02-auth.md`)

1. `GET /auth/me/signature` → `data.signature` es `null` si no está registrada. `document_types` lista los tipos de documento (`CC`, `CE`, `TI`, `PA`, `PPT`).
2. `PUT /auth/me/signature` (contraseña actual + tipo/número de documento + PNG base64). **Primer registro → 201 con `data.pin`** (3 dígitos, se muestra **una sola vez**: la app debe obligar al usuario a anotarlo). Actualización → 200 con `pin: null` (se conserva el PIN).
3. `POST /auth/me/signature/pin` genera un PIN nuevo (el anterior deja de servir).
- Estos endpoints solo operan sobre el usuario del token (sin IDs de usuario).

## 3. Órdenes de compra (`06-purchasing.md`)

```
draft ──submit──► pending_approval ──approve──► approved ──send──► sent ──receive──► partially_received / received
   │                    │ reject → rejected                         
   └── cancel (cualquier estado salvo recibida completamente) ──► cancelled; delete solo en draft
```

- Estados: `draft`, `pending_approval`, `approved`, `rejected`, `sent`, `partially_received`, `received`, `cancelled`.
- `approve`/`reject` aceptan `comments` (y `record_id`; ver tabla del endpoint); los flujos de aprobación se configuran en `approval-flows`.
- `receive` registra la recepción (total o parcial, por ítems); la orden pasa a `partially_received` o `received`.
- `purchase-orders/suggestions`: sugerencias de reorden por stock bajo. `consolidated-orders`: agrupa varias órdenes por proveedor (`consolidable-suppliers` → `consolidable` → `consolidation-preview` → `POST consolidated-orders`).

## 4. Órdenes de gasto (`06-purchasing.md`)

Mismos estados que la orden de compra (`draft → pending_approval → approved → sent → partially_received/received`, más `rejected`/`cancelled`). Solo se edita/elimina en `draft`.
Además, en **cualquier estado**: registrar pagos (`payments`, `payment_status`: `unpaid` | `partial` | `paid`; métodos `cash`, `transfer`, `check`, `other`) y factura (`invoice`, `send-accounting`).

## 5. Órdenes de servicio (`14-service-orders.md`)

1. Buscar paciente: `GET /medsys/patients?search=` (documento exacto → un paciente + citas; nombre → lista).
2. Consultar precio: `GET /procedures/{id}/current-price`.
3. `POST /service-orders` crea la orden (agrupa uno o más procedimientos del paciente; devuelve `order_number`).
4. Si hay descuentos que requieren autorización: `GET /service-orders/discounts` (pendientes) → `POST /service-orders/{orderNumber}/approve` (permiso `ordenes_servicio.aprobar`).
5. Facturación (`billing_status`): `null` (pendiente) → `billed` (`POST .../bill`) → `cancelled` (`POST .../cancel`). `null → cancelled` también permitido; `cancelled → billed` no (409); repetir un estado → 409.

## 6. Activos (`15-assets.md`)

**Estados del activo** (`status`): `operational`, `maintenance`, `out_of_service`, `available`, `retired`. Tipos (`asset_type`): `biomedical`, `computing`, `general`.

**Traslados** (`POST /assets/{assetId}/transfers`), tipos: `relocation`, `site_transfer` (se asigna solo cuando la reubicación cambia de sede), `external_repair`, `loan`, `return`, `retirement`.
- Firma opcional en `relocation`/`return`; **obligatoria** (o `pending_signature`) en `external_repair`, `loan`, `retirement` y en cambios de sede.
- Sin firmas y tipo con firma obligatoria → traslado `pending_signature` (el activo no cambia) → `POST /assets/transfers/{id}/confirm` con `delivered_by?`/`received_by` (mismo formato de firmantes que inventario) o `.../cancel` (con `reason`).
- Un traslado pendiente bloquea otro (409). Un activo fuera de las instalaciones solo admite `return` o `retirement`.
- Estados de traslado: `pending_signature`, `confirmed`, `cancelled`. Acta PDF: `GET /assets/transfers/{id}/act`.

**Mantenimiento**: tipos `preventive`, `calibration`, `metrological_verification`, `inspection`, `corrective`; estados `scheduled → in_progress → completed` (o `cancelled`).
- Planes periódicos: `maintenance-plans` (frecuencia en `days`/`months`); generan órdenes programadas automáticamente.
- `POST .../start` pasa el activo a `maintenance`; `POST .../complete` registra actividades/hallazgos/resultado (`operational`, `operational_with_restrictions`, `out_of_service`) y firmas opcionales; el resultado define el estado final del activo.
- Correctivo requiere `reported_failure`. Reporte PDF: `GET /assets/maintenances/{id}/report`.

**Documentos**: `POST /assets/{assetId}/documents` (multipart; `document_type`: `manual`, `invoice`, `warranty`, `invima`, `calibration_certificate`, `service_report`, `photo`, `protocol`, `other`).

## 7. Monitoreo de sensores (`07-monitoring.md`)

- Lecturas manuales desde la app: `POST /sensors/{sensorId}/readings` (`value`, `reading_source`). El usuario solo opera sobre sensores que tenga asignados.
- Lecturas automáticas llegan por dispositivo IoT con `X-IoT-Token` (`POST /sensors/readings/bulk`, hasta 500 lecturas).
- Reglas de alerta por sensor (`alert-rules`) generan notificaciones (`GET /notifications`, `unread-count`, `PUT /notifications/{id}/read`, `POST /notifications/read-all`).

## 8. Reportes asíncronos (`11-reports.md`)

Los reportes (`GET /reports/inventory|movements|expiring|purchases|consumption|conditions|audit`) devuelven datos; las exportaciones pesadas quedan en `reports/exports` con estados `queued → processing → ready | failed`. Sondear `GET /reports/exports/{id}` hasta `ready` y luego descargar con `.../download`.
