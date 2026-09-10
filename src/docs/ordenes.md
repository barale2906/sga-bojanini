# Plan de Implementación — Órdenes de Servicio

> **Estado:** BORRADOR — pendiente de revisión y aprobación antes de generar código.
> **Fecha:** 2026-09-09
> **Rama:** main (producción activa — solo migraciones nuevas, sin editar las existentes)

---

## 1. Resumen ejecutivo

Se agrega un nuevo acceso de nivel 1 en el menú llamado **Órdenes de Servicio**. Permite registrar en una sola operación varios procedimientos realizados a un paciente, con sus costos, descuentos (con flujo de aprobación vía correo/push), e insumos consumidos (salidas de inventario). Complementariamente, se gestiona la lista de precios vigente mediante carga masiva por Excel.

### Principio fundamental: reutilizar tablas existentes

- **`patient_procedure_records`** es la tabla de órdenes/líneas. Solo se agregan campos.
- **`procedure_prices`** es la tabla de listas de precios. Solo se agrega un campo.
- **No se crean tablas nuevas.**
- Los endpoints de consulta/edición individual existentes (`GET/PUT/DELETE /patient-procedure-records`) no cambian.
- El listado `GET /patient-procedure-records` se enriquece con los campos nuevos.

---

## 2. Estructura del menú

Nuevo nodo de **nivel 1** con dropdown (mismo patrón que `Compras` / `Monitoreo`):

```
Órdenes de Servicio  (key: service-orders-menu, icono: clipboard-list)
├── Generar Orden            (service-orders.index,     ordenes_servicio.ver)
├── Descuentos               (service-orders.discounts, ordenes_servicio.aprobar)
└── Cargar Lista de Precios  (price-lists.index,        listas_precios.ver)
```

- El grupo se muestra si al menos un hijo es visible para el usuario.
- "Generar Orden" es el acceso principal; visible para todos los roles con `ordenes_servicio.ver`.
- "Descuentos" y "Cargar Lista de Precios" solo para `administrador` y `jefe_almacen`.

---

## 3. Modelo de datos — cambios a tablas existentes

### 3.1 Tabla `patient_procedure_records` — campos a agregar

> Todos nullable para no romper los registros actuales en producción. Los registros creados desde el flujo anterior quedan con estos campos en `null` y siguen funcionando igual.

| Campo nuevo | Tipo | Notas |
|---|---|---|
| `order_number` | varchar(30) nullable, indexed | Agrupa todas las líneas de una misma orden. Formato: `OS-YYYYMMDD-NNNNNN`. Null en registros legacy. |
| `discount_type` | varchar(15) nullable | `'fixed'` o `'percentage'` |
| `discount_value` | decimal(12,4) nullable | Monto fijo o porcentaje (ej. 10.5) según `discount_type` |
| `discount_amount` | decimal(14,2) nullable | Monto calculado del descuento |
| `net_total` | decimal(14,2) nullable | `total - discount_amount` |
| `discount_status` | varchar(20) nullable | `null` (sin descuento), `'pending'` (esperando aprobación), `'approved'` |
| `created_by_user_id` | FK users nullable, nullOnDelete | Usuario que generó la orden |
| `approved_by_user_id` | FK users nullable, nullOnDelete | Usuario que aprobó el descuento |
| `approved_at` | timestamp nullable | Momento de aprobación |
| `patient_email` | varchar(100) nullable | Email del paciente — obtenido de `pacientes.email` en Medsys |
| `patient_address` | varchar(150) nullable | Dirección del paciente — obtenido de `pacientes.direccion` + `direccion2` en Medsys |
| `patient_phone` | varchar(50) nullable | Teléfono del paciente — obtenido de `pacientes.telcelular` o `telefono` en Medsys |

**Origen de los datos de contacto:** El frontend los recibe automáticamente al buscar el paciente en Medsys (`GET /medsys/patients?search=...`) y los pasa pre-completados en el cuerpo del request al crear la orden. El operador **no los digita**, solo confirma o corrige si Medsys los tiene desactualizados.

**Lógica de `discount_status`:**
- Registros sin descuento → `discount_status = null`.
- Registros con descuento al crear → `discount_status = 'pending'`.
- Al aprobar la orden → `discount_status = 'approved'` en todos los registros de esa `order_number` que tengan descuento.
- El estado de una **orden** completa = `'discount_pending'` si ALGÚN registro con esa `order_number` tiene `discount_status = 'pending'`. `'approved'` cuando todos están aprobados o sin descuento.

### 3.2 Tabla `procedure_prices` — campo a agregar

| Campo nuevo | Tipo | Notas |
|---|---|---|
| `loaded_by_user_id` | FK users nullable, nullOnDelete | Usuario que realizó la carga masiva. Null en precios cargados individualmente. |

La versión de la lista de precios se identifica por `effective_from` (ya existe). Al hacer una nueva carga masiva se inactivan los anteriores (vía `effective_to` + `is_active`) y se crean los nuevos con `effective_from = hoy` y `loaded_by_user_id` del usuario autenticado.

---

## 4. Migraciones (2 archivos)

1. `2026_09_09_000001_add_order_and_discount_fields_to_patient_procedure_records.php`
   - Agrega los 12 campos de la sección 3.1 (9 de orden/descuento + 3 de contacto del paciente)
   - Índice en `order_number` para las consultas de agrupación

2. `2026_09_09_000002_add_loaded_by_to_procedure_prices.php`
   - Agrega `loaded_by_user_id`

---

## 5. Permisos

### 5.1 Nuevos permisos (agregar al seeder)

```php
'ordenes_servicio.ver',
'ordenes_servicio.crear',
'ordenes_servicio.editar',
'ordenes_servicio.eliminar',
'ordenes_servicio.aprobar',
'listas_precios.ver',
'listas_precios.crear',
```

### 5.2 Asignación por rol

| Permiso | super_admin | administrador | jefe_almacen | operador_almacen | compras | auditor |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `ordenes_servicio.ver` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `ordenes_servicio.crear` | ✓ | ✓ | ✓ | ✓ | | |
| `ordenes_servicio.editar` | ✓ | ✓ | ✓ | | | |
| `ordenes_servicio.eliminar` | ✓ | ✓ | ✓ | | | |
| `ordenes_servicio.aprobar` | ✓ | ✓ | ✓ | | | |
| `listas_precios.ver` | ✓ | ✓ | ✓ | | | |
| `listas_precios.crear` | ✓ | ✓ | ✓ | | | |

---

## 6. Estructura del módulo backend

Todo en `app/Modules/CostCenter/`. Se extienden entidades y repositorios existentes; se agregan use cases y controladores nuevos. No se crean nuevas entidades de dominio ni nuevos modelos Eloquent.

### 6.1 Integración Medsys — ampliar `MedsysPatientService`

La tabla `pacientes` de Medsys (confirmado consultando la BD real) tiene los campos de contacto del paciente. Hay que incluirlos en los `SELECT` existentes para exponerlos al frontend:

**`MedsysPatientService::findByDocument()`** — agregar al `select()`:
```php
'email',
'direccion',
'direccion2',
'telcelular',
'telefono',
```

**`MedsysPatientService::findByName()`** — mismos campos al `select()`.

La respuesta del endpoint `GET /medsys/patients?search=...` pasará a incluir:
```json
{
  "patient": {
    "codigo": "P00123",
    "tipodoc": "CC",
    "documento": "12345678",
    "nombre": "Juan Carlos Pérez Gómez",
    "email": "juan.perez@email.com",
    "direccion": "Calle 50 # 20-30",
    "direccion2": "Apto 201",
    "telcelular": "3001234567",
    "telefono": "6041234567"
  },
  "appointments": [...]
}
```

El frontend pre-llena los campos de email, dirección y teléfono del formulario con estos valores. El operador puede confirmarlos o ajustarlos antes de crear la orden. No se digitan desde cero.

### 6.2 Entidades de dominio — cambios

**`PatientProcedureRecord.php`** (ampliar, no reescribir):
- Agregar propiedades: `orderNumber`, `discountType`, `discountValue`, `discountAmount`, `netTotal`, `discountStatus`, `createdByUserId`, `approvedByUserId`, `approvedAt`, `patientEmail`, `patientAddress`, `patientPhone`
- Agregar método de fábrica o actualizar `create()` con los nuevos params
- Método `calculateDiscount(string $type, float $value, float $total): float`
- Getters para cada campo nuevo

**`ProcedurePrice.php`** (ampliar):
- Agregar `loadedByUserId` nullable

### 6.3 Repositorios — nuevos métodos

**`PatientProcedureRecordRepositoryInterface`** (agregar, no borrar los existentes):
```php
createBatch(array $records): array; // crea varias líneas de una orden
findByOrderNumber(string $orderNumber): array;
findPendingDiscountOrders(array $filters): array; // agrupado por order_number
approveDiscountsForOrder(string $orderNumber, int $userId, DateTimeImmutable $at): void;
```

**`EloquentPatientProcedureRecordRepository`**: implementar los métodos anteriores.

**`ProcedurePriceRepositoryInterface`** (agregar):
```php
deactivateAllActive(): void;
findCurrentForProcedure(int $medicalServiceId): ?ProcedurePrice;
createBatch(array $prices): void;
```

**`EloquentProcedurePriceRepository`**: implementar los métodos anteriores.

### 6.4 Modelos Eloquent — cambios mínimos

**`PatientProcedureRecordModel`**: agregar los 12 campos a `$fillable` y `$casts`.

**`ProcedurePriceModel`**: agregar `loaded_by_user_id` a `$fillable`.

### 6.6 DTOs nuevos

```
Application/DTOs/
  ServiceOrderLineData.php   ← datos de una línea/procedimiento dentro de la orden
  ServiceOrderData.php       ← datos de cabecera: paciente, contacto (email/dir/tel), fecha + líneas
```

### 6.7 Use Cases nuevos

```
Application/UseCases/
  CreateServiceOrderUseCase.php           ← crea order_number + N patient_procedure_records
  ListServiceOrdersUseCase.php            ← registros agrupados por order_number
  GetServiceOrderUseCase.php              ← todas las líneas de un order_number
  ApproveServiceOrderDiscountUseCase.php  ← aprobación atómica por order_number
  UploadPriceListUseCase.php              ← deactiva anteriores + crea nuevos procedure_prices
  DownloadPriceListTemplateUseCase.php    ← genera Excel con precios vigentes
  GetCurrentPriceForProcedureUseCase.php ← precio vigente de un procedimiento
```

### 6.6 Controladores nuevos

```
Infrastructure/Http/Controllers/
  ServiceOrderController.php   ← index, store (array), show, destroy, discounts, approve
  PriceListController.php      ← template (descarga), import (sube)
```

### 6.7 Requests nuevas

```
Infrastructure/Http/Requests/
  StoreServiceOrderRequest.php   ← valida cabecera + array de procedures
```

### 6.8 Resources

**`PatientProcedureRecordResource`** (ampliar): agregar campos nuevos en el `toArray()`.

**Nuevas:**
```
Infrastructure/Http/Resources/
  ServiceOrderResource.php      ← vista agrupada por order_number (cabecera + líneas)
```

### 6.9 Notificaciones nuevas

```
app/Modules/Shared/Infrastructure/Notifications/
  ServiceOrderDiscountPendingNotification.php   ← notifica a aprobadores
  ServiceOrderDiscountApprovedNotification.php  ← notifica al creador
```

Siguen el mismo patrón que `PurchaseOrderPendingApprovalNotification` (email + FCM via `UsesSgaChannels`).

---

## 7. Endpoints

Archivo nuevo: `routes/api/service-orders.php`. Las rutas de `cost-center.php` no se tocan.

```
# Órdenes de servicio — NUEVOS
POST   /v1/service-orders                      → store     (ordenes_servicio.crear)
GET    /v1/service-orders/discounts            → discounts (ordenes_servicio.aprobar)
POST   /v1/service-orders/{orderNumber}/approve → approve  (ordenes_servicio.aprobar)
GET    /v1/service-orders/{orderNumber}        → show      (ordenes_servicio.ver)

# Precio vigente de un procedimiento (autocompletar al crear orden)
GET    /v1/procedures/{id}/current-price       →           (ordenes_servicio.ver)

# Listas de precios — NUEVOS
GET    /v1/price-lists/template                → template  (listas_precios.ver)  ← descarga Excel
POST   /v1/price-lists/import                  → import    (listas_precios.crear) ← sube Excel

# Endpoints EXISTENTES que se enriquecen (sin cambios en firma):
GET    /v1/patient-procedure-records           → index (ahora incluye campos nuevos en la respuesta)
GET    /v1/patient-procedure-records/{id}      → show  (idem)
PUT    /v1/patient-procedure-records/{id}      → update (campos nuevos opcionales)
DELETE /v1/patient-procedure-records/{id}      → destroy (sin cambios)
GET    /v1/patients/{id}/procedure-records     → history (sin cambios)
```

**Nota sobre `GET /v1/patient-procedure-records`:** Este sigue siendo el listado principal. Incluye nuevos filtros: `order_number`, `discount_status`. La vista "Generar Orden" del frontend reutiliza este endpoint para mostrar el historial.

---

## 8. Flujos detallados

### 8.1 Cargar lista de precios

**Descargar plantilla:**
```
GET /v1/price-lists/template     (requiere listas_precios.ver)
```
- Genera Excel con columnas: `procedure_code`, `procedure_name`, `service_name`, `unit_price`.
- Incluye TODOS los procedimientos (`type = 'procedure'`) con el precio activo vigente (si existe).
- Usa `PhpSpreadsheet` (ya instalado en el proyecto).
- Respuesta: `StreamedResponse` con Content-Disposition attachment.

**Cargar nueva lista:**
```
POST /v1/price-lists/import   { file: multipart/form-data }   (requiere listas_precios.crear)
```
En transacción:
1. Leer el Excel fila por fila; validar que `procedure_code` exista y `unit_price >= 0`.
2. Si hay errores de validación → retornar 422 con detalle fila por fila (sin tocar la BD).
3. `ProcedurePriceRepository::deactivateAllActive()`:
   - `UPDATE procedure_prices SET is_active = false, effective_to = today - 1 day WHERE is_active = true`.
4. Crear un `procedure_prices` por cada fila del Excel con:
   - `effective_from = today`, `is_active = true`, `loaded_by_user_id = auth()->id()`.
5. Retornar resumen: `{ processed: N, skipped: M, skipped_codes: [...] }`.

### 8.2 Generar orden de servicio

**Request:**

Los campos `patient_email`, `patient_address` y `patient_phone` vienen pre-completados desde la búsqueda en Medsys (`GET /medsys/patients?search=...`). El frontend los envía automáticamente sin que el operador los escriba.

```json
POST /v1/service-orders
{
  "patient_external_id": "PAC-001",
  "patient_document": "12345678",
  "patient_first_name": "Juan Carlos",
  "patient_last_name": "Pérez Gómez",
  "patient_email": "juan.perez@email.com",
  "patient_address": "Calle 50 # 20-30 Apto 201",
  "patient_phone": "3001234567",
  "service_date": "2026-09-09",
  "notes": "Opcional — aplica a toda la orden",
  "procedures": [
    {
      "medical_service_id": 5,
      "unit_price": 50000,
      "quantity": 1,
      "discount_type": "percentage",
      "discount_value": 10,
      "notes": "Opcional por procedimiento",
      "supplies": [
        { "warehouse_id": 1, "product_variant_id": 3, "quantity": 2 }
      ]
    },
    {
      "medical_service_id": 7,
      "unit_price": 30000,
      "quantity": 2,
      "discount_type": null,
      "discount_value": null,
      "supplies": []
    }
  ]
}
```

**Backend — en DB transaction:**
1. Generar `order_number` (formato `OS-YYYYMMDD-NNNNNN`, secuencial por día con `DB::select('SELECT LAST_INSERT_ID()')` o sequence lógica).
2. Para cada elemento en `procedures`:
   - `total = quantity * unit_price`
   - `discount_amount`:
     - `fixed` → `discount_value`
     - `percentage` → `round(total * discount_value / 100, 2)`
     - `null` → `0.00`
   - `net_total = total - discount_amount`
   - `discount_status`:
     - Con descuento → `'pending'`
     - Sin descuento → `null`
   - Crear `patient_procedure_record` con `order_number`, campos de descuento, `created_by_user_id = auth()->id()`.
   - Si `supplies` no está vacío:
     - Crear `movement_document` (tipo salida, destino paciente) — reutilizar lógica existente de salidas de inventario.
     - Crear `stock_movements` por cada ítem.
     - Asignar `movement_document_id` al record.
3. Si ALGÚN procedimiento tiene descuento:
   - Notificar por email + push a todos los usuarios con permiso `ordenes_servicio.aprobar`.
4. Retornar los registros creados bajo el `order_number` generado.

**Nota sobre `unit_price`:** El frontend pre-carga el precio vigente con `GET /v1/procedures/{id}/current-price`. El usuario puede ajustarlo antes de confirmar. El backend recibe el valor final y lo guarda como está.

### 8.3 Aprobar descuento

**Ver pendientes:**
```
GET /v1/service-orders/discounts    (requiere ordenes_servicio.aprobar)
```
- Retorna registros agrupados por `order_number` donde al menos uno tiene `discount_status = 'pending'`.
- Cada grupo incluye: info del paciente, fecha, lista de procedimientos con sus descuentos, totales calculados, nombre del creador.

**Aprobar una orden:**
```
POST /v1/service-orders/{orderNumber}/approve    (requiere ordenes_servicio.aprobar)
```
En transacción con `lockForUpdate()`:
1. Buscar registros con `order_number = {orderNumber}`.
2. Si no existen → 404.
3. Si ninguno tiene `discount_status = 'pending'` → `DomainException` ("La orden no tiene descuentos pendientes de aprobación") → 409.
4. Actualizar todos los registros con `discount_status = 'pending'` de esa `order_number`:
   - `discount_status = 'approved'`
   - `approved_by_user_id = auth()->id()`
   - `approved_at = now()`
5. Notificar por email al `created_by_user_id` de los registros (es el mismo para toda la orden).
6. Retornar el estado actualizado de la orden.

Si mientras se procesa otro usuario también aprueba (race condition): el `lockForUpdate()` garantiza exclusión; el segundo recibirá el resultado del paso 3 (ninguno en 'pending') → 409.

---

## 9. Respuesta del listado enriquecido

`GET /v1/patient-procedure-records` ahora incluye:

```json
{
  "id": 10,
  "order_number": "OS-20260909-000001",
  "medical_service_id": 5,
  "medical_service_name": "Curaciones Simples",
  "patient_external_id": "PAC-001",
  "patient_document": "12345678",
  "patient_first_name": "Juan Carlos",
  "patient_last_name": "Pérez Gómez",
  "quantity": 1,
  "unit_price": 50000,
  "total": 50000,
  "discount_type": "percentage",
  "discount_value": 10,
  "discount_amount": 5000,
  "net_total": 45000,
  "discount_status": "approved",
  "service_date": "2026-09-09",
  "notes": null,
  "seller": null,
  "referrer": null,
  "created_by_user_id": 3,
  "created_by_name": "Nombre Operador",
  "approved_by_user_id": 1,
  "approved_by_name": "Nombre Aprobador",
  "approved_at": "2026-09-09T14:35:00Z",
  "movement_document_id": 3,
  "is_active": true
}
```

`GET /v1/service-orders/{orderNumber}` — vista agrupada (todos los registros de la orden + totales calculados):

```json
{
  "order_number": "OS-20260909-000001",
  "patient_document": "12345678",
  "patient_first_name": "Juan Carlos",
  "patient_last_name": "Pérez Gómez",
  "service_date": "2026-09-09",
  "order_status": "approved",
  "total_amount": 80000,
  "total_discount": 5000,
  "net_total": 75000,
  "created_by_name": "Nombre Operador",
  "approved_by_name": "Nombre Aprobador",
  "approved_at": "2026-09-09T14:35:00Z",
  "procedures": [ ... ]
}
```

Filtros adicionales para `GET /v1/patient-procedure-records`:
`order_number`, `discount_status`.

---

## 10. Compatibilidad con módulos existentes

| Módulo | Impacto | Acción requerida |
|---|---|---|
| `patient_procedure_records` (tabla) | 12 campos nuevos nullable (9 orden/descuento + 3 contacto) | Migración nueva |
| `procedure_prices` (tabla) | 1 campo nuevo nullable | Migración nueva |
| `MedsysPatientService` | Agregar `email`, `direccion`, `direccion2`, `telcelular`, `telefono` al SELECT | Ampliar |
| `PatientProcedureRecord` (entidad) | Agregar 12 propiedades y método de cálculo de descuento | Ampliar (no reescribir) |
| `ProcedurePrice` (entidad) | Agregar `loadedByUserId` | Ampliar |
| `PatientProcedureRecordModel` | Agregar 12 campos a `$fillable` y `$casts` | Ampliar |
| `ProcedurePriceModel` | Agregar `loaded_by_user_id` a `$fillable` | Ampliar |
| `PatientProcedureRecordController` | Sin cambios en firma de endpoints | Ninguna |
| `ProcedurePriceController` | Sin cambios | Ninguna |
| `PatientProcedureRecordResource` | Agregar campos nuevos en `toArray()` | Ampliar |
| `PatientClinicalEvolution` | Sin cambios en backend | Ninguna |
| `MenuBuilder` | Agregar `buildServiceOrdersSection()` | Ampliar |
| Repositorios existentes | Agregar nuevos métodos (no borrar) | Ampliar interfaces e implementaciones |

---

## 11. Tests

### 11.1 Tests nuevos

**Unit:**

`tests/Unit/CostCenter/PatientProcedureRecordEntityTest.php` (agregar casos):
- `calculateDiscount()` con tipo `fixed`
- `calculateDiscount()` con tipo `percentage`
- `calculateDiscount()` con tipo `null` → retorna 0
- `netTotal` = `total - discount_amount`
- `discount_status` inicial al crear con/sin descuento

**Feature:**

`tests/Feature/ServiceOrderTest.php`:
- Crear orden sin descuentos (array de 1 procedimiento) → todos los records sin `discount_status`
- Crear orden con descuentos → `discount_status = 'pending'`, notificación enviada (Notification::fake)
- Crear orden con múltiples procedimientos (array de 3) → todos tienen el mismo `order_number`
- Crear orden con insumos → verifica `movement_document_id` asignado a los records correspondientes
- Crear orden con datos de contacto → verifica `patient_email`, `patient_address`, `patient_phone` guardados
- Crear orden sin datos de contacto (campos nullable) → sigue funcionando
- Listar órdenes con filtro `order_number` → retorna solo los records de esa orden
- Listar con filtro `discount_status = pending`
- Ver orden agrupada `GET /service-orders/{orderNumber}` → respuesta incluye email, dirección, teléfono
- Crear orden con `medical_service_id` de tipo servicio (no procedimiento) → 409
- Sin permiso `ordenes_servicio.crear` → 403

`tests/Feature/MedsysPatientServiceTest.php` (ampliar test existente si hay, o crear):
- Buscar por documento → respuesta incluye `email`, `direccion`, `direccion2`, `telcelular`, `telefono`
- Buscar por nombre → respuesta incluye los mismos campos de contacto

`tests/Feature/ServiceOrderDiscountTest.php`:
- Aprobar descuento → `discount_status = 'approved'`, `approved_by_user_id` guardado
- Aprobar orden ya aprobada → 409
- Intentar aprobar orden sin descuentos → 409
- Aprobar sin permiso `ordenes_servicio.aprobar` → 403
- `GET /service-orders/discounts` → solo muestra órdenes con algún `discount_status = 'pending'`
- `GET /service-orders/discounts` sin permiso → 403

`tests/Feature/PriceListTest.php`:
- `GET /price-lists/template` → StreamedResponse, verifica nombre de columnas y filas con procedimientos
- `POST /price-lists/import` Excel válido → procedure_prices anteriores inactivados, nuevos creados con `loaded_by_user_id`
- `POST /price-lists/import` Excel con código de procedimiento no existente → 422, BD sin cambios
- `POST /price-lists/import` sin permiso → 403
- `GET /procedures/{id}/current-price` → retorna precio activo vigente
- `GET /procedures/{id}/current-price` sin precio activo → 404 o respuesta con `price: null`

### 11.2 Tests existentes a actualizar

`tests/Feature/PatientProcedureRecordTest.php`:
- Verificar que `store` y `update` siguen funcionando con los nuevos campos en `null` (compatibilidad)
- Verificar que el recurso incluye los nuevos campos en la respuesta (sin romper assertions existentes)

`tests/Unit/Shared/MenuBuilderTest.php`:
- Agregar casos: nuevo nodo `service-orders-menu` aparece con `ordenes_servicio.ver`
- `discounts` solo aparece con `ordenes_servicio.aprobar`
- `price-lists` solo aparece con `listas_precios.ver`
- Sin ninguno de esos permisos → el grupo `service-orders-menu` no aparece

`tests/Feature/MenuTest.php`:
- Agregar casos equivalentes a nivel de feature (solicitud autenticada al endpoint del menú)

---

## 12. Orden de implementación sugerido

| # | Paso | Archivos involucrados |
|---|---|---|
| 1 | Migraciones (2) | `database/migrations/2026_09_09_000001_*`, `_000002_*` |
| 2 | Permisos en seeder | `RolesAndPermissionsSeeder.php` |
| 3 | Ampliar `MedsysPatientService` (campos de contacto) | `Integration/ExternalServices/MedsysPatientService.php` |
| 4 | Ampliar entidad `PatientProcedureRecord` | `Domain/Entities/PatientProcedureRecord.php` |
| 5 | Ampliar entidad `ProcedurePrice` | `Domain/Entities/ProcedurePrice.php` |
| 6 | Ampliar interfaces de repositorios | `PatientProcedureRecordRepositoryInterface.php`, `ProcedurePriceRepositoryInterface.php` |
| 7 | Ampliar modelos Eloquent | `PatientProcedureRecordModel.php`, `ProcedurePriceModel.php` |
| 8 | Ampliar repositorios Eloquent | `EloquentPatientProcedureRecordRepository.php`, `EloquentProcedurePriceRepository.php` |
| 9 | DTOs nuevos | `ServiceOrderData.php`, `ServiceOrderLineData.php` |
| 10 | Use Cases (sin notificaciones) | `CreateServiceOrderUseCase`, `ListServiceOrdersUseCase`, `GetServiceOrderUseCase`, `ApproveServiceOrderDiscountUseCase` |
| 10 | Use Cases de precios | `GetCurrentPriceForProcedureUseCase`, `UploadPriceListUseCase`, `DownloadPriceListTemplateUseCase` |
| 11 | `StoreServiceOrderRequest` | Requests/ |
| 12 | Ampliar `PatientProcedureRecordResource` + nueva `ServiceOrderResource` | Resources/ |
| 13 | Controladores nuevos | `ServiceOrderController.php`, `PriceListController.php` |
| 14 | Rutas + registro en `api.php` | `routes/api/service-orders.php` |
| 15 | `MenuBuilder` — nuevo método `buildServiceOrdersSection()` | `MenuBuilder.php` |
| 16 | Notificaciones | `ServiceOrderDiscountPendingNotification.php`, `ServiceOrderDiscountApprovedNotification.php` |
| 17 | Integrar notificaciones en use cases | `CreateServiceOrderUseCase`, `ApproveServiceOrderDiscountUseCase` |
| 18 | Tests unitarios (nuevos + actualizados) | `PatientProcedureRecordEntityTest.php` |
| 19 | Tests Feature nuevos | `ServiceOrderTest.php`, `ServiceOrderDiscountTest.php`, `PriceListTest.php` |
| 20 | Tests existentes actualizados | `PatientProcedureRecordTest.php`, `MenuBuilderTest.php`, `MenuTest.php` |
| 21 | Documentación frontend | `src/docs/frontend-ordenes-servicio.md` |

---

## 13. Puntos abiertos / decisiones a confirmar

1. **¿La aprobación del descuento es por orden completa o por procedimiento individual?**
   El plan aprueba toda la orden (`order_number`) de una vez. Si se necesita aprobar procedimiento por procedimiento, el endpoint y la lógica cambian.

2. **¿El operador puede modificar el `unit_price` respecto al de la lista vigente?**
   El plan dice sí. El precio de la lista es solo una sugerencia para autocompletar.

3. **¿Qué muestra el submenu "Descuentos"?** ¿Solo pendientes, o también el historial de órdenes ya aprobadas con descuento?

4. **Formato del `order_number`:** Se propone `OS-YYYYMMDD-NNNNNN`. ¿Es adecuado o se prefiere otro?

5. **¿Pueden editarse los procedimientos de una orden después de creada?** Los endpoints PUT existentes permiten editar registros individualmente. ¿Debe respetarse ese flujo para el nuevo concepto de orden, o solo admin/jefe pueden hacerlo?

6. **¿Soft delete de una orden borra todos sus records?** El plan propone soft delete registro por registro (usando el endpoint existente). Si se requiere eliminar todos los records de una `order_number` en un solo paso, hay que agregar ese endpoint.

---

## 14. Lo que NO cambia

- Endpoints existentes de `patient-procedure-records` (firmas idénticas)
- Endpoints existentes de `procedures/{id}/prices` (CRUD individual de precios)
- `PatientClinicalEvolutionController` y sus rutas
- `ClinicalTemplateController` y sus rutas
- Módulos `Inventory`, `Purchasing`, `Monitoring`
- Tests existentes pasan sin modificación (campos nuevos son nullable)

---

*Revisar y ajustar este documento antes de iniciar la implementación.*
