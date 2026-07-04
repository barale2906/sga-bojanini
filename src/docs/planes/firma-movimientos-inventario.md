# Plan: Sistema de Firmas en Movimientos de Inventario

**Módulo:** Inventory  
**Fecha:** 2026-07-04  
**Estado:** ✅ Validado — decisiones de diseño confirmadas

## Decisiones de diseño confirmadas

| # | Decisión | Definición |
|---|----------|------------|
| 1 | **Sin reserva de stock** | El FEFO se re-ejecuta al momento de firmar. Si entre creación y firma los lotes/fechas cambian, se adapta al stock actual. Si ya no hay suficiente → error 409. |
| 2 | **Dos firmantes por movimiento** | Quien ejecuta/entrega (`delivered_by`) y quien recibe (`received_by`). Ambas firmas son obligatorias para confirmar. |
| 3 | **PDF solo para movimientos confirmados** | No se genera PDF borrador para movimientos en `pending_signature`. |
| 4 | **Firma digital simple** | Imagen PNG del canvas del frontend en base64. Sin mecanismo de no-repudio adicional. |

---

## Visión general

Los movimientos de inventario (salidas internas, traslados, ajustes, devoluciones, bajas) requieren aprobación mediante firma antes de que el cambio se aplique al stock. Se introduce un campo `status` en `stock_movements` y una tabla nueva `movement_signatures`.

**Principio clave:** Los cambios de stock (batch quantities) **no se aplican** al crear el movimiento. Solo se aplican cuando ambas firmas son registradas y el movimiento pasa a `confirmed`. El FEFO se re-evalúa en el momento de la firma: si entre la creación y la firma los lotes o fechas de vencimiento cambiaron, se toman los disponibles en ese instante.

**Excepción — Salidas a pacientes:** Movimientos de tipo `exit` con centro de costo **externo** (cuando viene `patient_document`) saltan directamente a `confirmed` y aplican stock inmediatamente. Sin cambio de comportamiento para ese flujo.

### Máquina de estados

```
Movimientos con firma requerida:
  [Solicitud] → crear registro → [pending_signature] → POST confirm (2 firmas) → [confirmed]
                                  (stock NO modificado)                          (stock aplicado)

Entradas y salidas a pacientes:
  [Solicitud] → aplicar stock → [confirmed]   (flujo actual sin cambios)
```

### Regla de qué requiere firma

| Tipo              | Condición                           | ¿Requiere firma? | Estado inicial      |
|-------------------|-------------------------------------|------------------|---------------------|
| `entry`           | Siempre                             | No               | `confirmed`         |
| `exit`            | Centro de costo **externo** (paciente) | No — excluido  | `confirmed`         |
| `exit`            | Centro de costo **interno**         | **Sí**           | `pending_signature` |
| `transfer`        | Siempre                             | **Sí**           | `pending_signature` |
| `adjustment`      | Siempre                             | **Sí**           | `pending_signature` |
| `return`          | Siempre                             | **Sí**           | `pending_signature` |
| `expiration_write_off` | Siempre                        | **Sí**           | `pending_signature` |
| `loss`            | Siempre                             | **Sí**           | `pending_signature` |

---

## 01 — Base de datos

### Cambio en `stock_movements`

Editar la migración existente `2026_05_18_200003_create_stock_movements_table.php` (proyecto no está en producción):

```php
$table->enum('status', ['pending_signature', 'confirmed'])
      ->default('confirmed'); // default en confirmed para entradas y salidas a pacientes

$table->index(['status', 'created_at'], 'idx_mov_status_created');
```

### Nueva tabla `movement_signatures`

Nueva migración `2026_07_04_000001_create_movement_signatures_table.php`:

| Columna            | Tipo                                    | Notas                                      |
|--------------------|-----------------------------------------|--------------------------------------------|
| `id`               | `bigIncrements`                         | PK                                         |
| `movement_id`      | `foreignId → stock_movements`           | FK con `cascadeOnDelete`                   |
| `role`             | `enum('delivered_by','received_by')`    | Quién entrega / quién recibe               |
| `signer_name`      | `string(150)`                           | Nombre completo del firmante               |
| `signer_document`  | `string(50)`                            | Cédula o ID del firmante                   |
| `signature_data`   | `longText`                              | PNG en base64 de la firma manuscrita       |
| `signed_at`        | `timestamp` (`useCurrent()`)            | Momento exacto de la firma                 |
| `created_at`       | `timestamp`                             | —                                          |

Índice único compuesto: `unique(['movement_id', 'role'])` — garantiza que no pueda haber dos firmas del mismo rol para el mismo movimiento.

---

## 02 — Dominio

### Nuevo: `MovementStatus` enum

Archivo: `Domain/ValueObjects/MovementStatus.php`

```php
enum MovementStatus: string
{
    case PENDING_SIGNATURE = 'pending_signature';
    case CONFIRMED         = 'confirmed';
}
```

### Modificación: `MovementType`

Agregar método `requiresSignature(bool $isPatientExit): bool` que encapsula la regla de negocio en dominio, evitando que quede dispersa en los Use Cases:

```php
public function requiresSignature(bool $isPatientExit = false): bool
{
    if ($this === self::EXIT && $isPatientExit) {
        return false; // salidas a pacientes excluidas
    }
    return match($this) {
        self::ENTRY => false,
        default     => true,
    };
}
```

### Opcional: entidad `MovementSignature`

Archivo: `Domain/Entities/MovementSignature.php` — clase simple con las propiedades de la firma (sin lógica de persistencia). Mantiene la arquitectura Clean Architecture consistente.

---

## 03 — Capa de aplicación

### Refactorización de Use Cases existentes

**Patrón de refactorización:** Cada Use Case que actualmente hace todo en `execute()` pasa a tener dos responsabilidades separadas:

- `createPending(array $data): StockMovementModel` — validación + registro en BD, **sin tocar stock**
- `applyStock(StockMovementModel $movement): void` — FEFO + modificación de batches + eventos

El nuevo `ConfirmMovementUseCase` llama a `applyStock()` del Use Case correspondiente según `movement_type`.

| Use Case               | Método actual | Nuevos métodos                   |
|------------------------|---------------|----------------------------------|
| `RegisterExitUseCase`  | `execute()`   | `createPending()` · `applyStock()` |
| `TransferStockUseCase` | `execute()`   | `createPending()` · `applyStock()` |
| `AdjustStockUseCase`   | `execute()`   | `createPending()` · `applyStock()` |
| `RegisterReturnUseCase`| `execute()`   | `createPending()` · `applyStock()` |
| `WriteOffExpiredUseCase`| `execute()`  | `createPending()` · `applyStock()` |
| `RegisterLossUseCase`  | `execute()`   | `createPending()` · `applyStock()` |

> **FEFO en la confirmación (decisión confirmada):** El FEFO se re-ejecuta al momento de firmar. Los lotes y fechas de vencimiento se determinan con el stock disponible en ese instante — si entre la creación del pendiente y la firma los lotes cambiaron (otro movimiento confirmado los agotó, o vencieron), el sistema toma los lotes disponibles actuales. Si ya no hay stock suficiente → `InsufficientStockException` → HTTP 409.

### Nuevo: `ConfirmMovementUseCase`

Archivo: `Application/UseCases/ConfirmMovementUseCase.php`

Flujo de `execute(ConfirmMovementDTO $dto)`:

1. Carga el `StockMovementModel` por ID. Si no existe → `ModelNotFoundException`.
2. Verifica que `status === 'pending_signature'`. Si ya está confirmado → `DomainException` ("Movimiento ya confirmado").
3. Verifica que los datos de firma incluyen ambos roles (`delivered_by` y `received_by`), cada uno con `signer_name`, `signer_document` y `signature_data`.
4. Dentro de `DB::transaction()`: delega a `applyStock(StockMovementModel)` del Use Case correspondiente según `movement_type`.
5. Crea los dos registros en `movement_signatures`.
6. Actualiza `movement.status = 'confirmed'`. Dispara `StockMovementCreated` (ya existente).
7. Retorna el movimiento cargado con sus firmas.

### Nuevo: `ConfirmMovementDTO`

```php
class ConfirmMovementDTO
{
    public function __construct(
        public readonly int    $movementId,
        public readonly string $deliveredByName,
        public readonly string $deliveredByDocument,
        public readonly string $deliveredBySignature,  // base64
        public readonly string $receivedByName,
        public readonly string $receivedByDocument,
        public readonly string $receivedBySignature,   // base64
    ) {}
}
```

---

## 04 — Infraestructura

### Archivos nuevos

| Archivo | Descripción |
|---------|-------------|
| `Persistence/Models/MovementSignatureModel.php` | Eloquent model para `movement_signatures`. Relación `belongsTo` a `StockMovementModel`. Sin soft deletes. |
| `Http/Requests/ConfirmMovementRequest.php` | Valida los 6 campos de firma (2 roles × 3 campos). `signature_data` debe ser string base64 válido. |
| `Http/Resources/MovementSignatureResource.php` | Retorna todos los campos excepto `signature_data` (omitir por tamaño). Endpoint separado para la imagen si se requiere. |

### Archivos a modificar

| Archivo | Cambio |
|---------|--------|
| `Persistence/Models/StockMovementModel.php` | Agregar `status` a `$fillable`, cast como `MovementStatus`, relación `hasMany(MovementSignatureModel)`. |
| `Http/Controllers/MovementController.php` | Agregar método `confirm(int $id, ConfirmMovementRequest $request)`. Filtrar por `status` en `index()`. Cargar relación `signatures` en `show()`. |
| `Http/Resources/MovementResource.php` | Agregar campo `status` y colección `signatures` (si la relación está cargada). |
| `app/Providers/ModuleServiceProvider.php` | Registrar binding de `ConfirmMovementUseCase` si es necesario. |

---

## 05 — API

### Nuevo endpoint: confirmar movimiento

```
POST /v1/movements/{id}/confirm
Permiso: movimientos.confirmar
```

**Body:**
```json
{
  "delivered_by": {
    "name": "Nombre completo del que entrega",
    "document": "Número de cédula",
    "signature": "data:image/png;base64,..."
  },
  "received_by": {
    "name": "Nombre completo del que recibe",
    "document": "Número de cédula",
    "signature": "data:image/png;base64,..."
  }
}
```

**Respuestas:**
- `200` — Movimiento confirmado con firmas
- `404` — Movimiento no encontrado
- `409` — Ya confirmado, o stock insuficiente (race condition)
- `422` — Validación de campos

### Nuevo endpoint: obtener imagen de firma

```
GET /v1/movements/{id}/signature/{role}
Permiso: stock.ver
```

Retorna la imagen base64 de la firma de un rol específico (`delivered_by` o `received_by`). Separado por el tamaño de los datos; se usa en el PDF y en la vista de detalle.

### Modificación: listado de movimientos

```
GET /v1/movements?status=pending_signature
```

El endpoint existente acepta el nuevo filtro `status` para mostrar la cola de movimientos pendientes de firma.

### Nuevo permiso en seeder

```php
// RolesAndPermissionsSeeder.php
'movimientos.confirmar', // confirmar movimiento pendiente de firma
```

---

## 06 — PDF con firmas

El sistema ya usa DomPDF (barryvdh). Solo los movimientos `confirmed` incluyen el bloque de firmas en el PDF.

La imagen base64 de la firma se incrusta directamente en el HTML del Blade → DomPDF la renderiza sin dependencias externas.

### Archivos a modificar

| Archivo | Cambio |
|---------|--------|
| `resources/views/reports/movements.blade.php` | Agregar bloque condicional al final. Si `$movement->signatures->count() > 0` renderiza la tabla de firmas con imágenes base64. |
| `Shared/Application/Services/ReportDataCollector.php` | Cargar la relación `signatures` en los movimientos cuando el reporte sea de tipo `movements`. |

### Estructura del bloque de firmas en PDF

```
┌──────────────────────────┬──────────────────────────┐
│ ENTREGADO POR            │ RECIBIDO POR              │
│                          │                           │
│ Nombre: _____________    │ Nombre: _____________     │
│ Documento: ___________   │ Documento: ___________    │
│                          │                           │
│ [ imagen firma ]         │ [ imagen firma ]          │
│                          │                           │
│ 2026-07-04 10:32:15      │ 2026-07-04 10:33:02       │
└──────────────────────────┴──────────────────────────┘
```

---

## 07 — Orden de implementación

Las fases tienen dependencias. Seguir este orden:

1. **Migraciones** — Editar `stock_movements` (agregar `status`). Crear migración de `movement_signatures`. Correr `php artisan migrate:fresh --seed`.
2. **Dominio** — Crear `MovementStatus` enum. Agregar `requiresSignature()` a `MovementType`.
3. **Modelo Eloquent** — Crear `MovementSignatureModel`. Actualizar `StockMovementModel`.
4. **Refactorizar Use Cases uno a uno** — Comenzar por `RegisterExitUseCase` (el más complejo, con lógica de salidas a pacientes). Verificar tests existentes. Continuar con los demás.
5. **`ConfirmMovementUseCase`** — Implementar con su DTO y lógica de delegación por `movement_type`.
6. **HTTP Layer** — `ConfirmMovementRequest`, método en `MovementController`, nuevas rutas, nuevo permiso en seeder.
7. **Resources** — Actualizar `MovementResource`, crear `MovementSignatureResource`.
8. **Tests** — Actualizar Feature tests existentes (ahora los movimientos quedan en `pending_signature`). Agregar tests de `ConfirmMovementUseCase`.
9. **PDF** — Modificar Blade de reporte de movimientos para incluir bloque de firmas.

> **Punto de corte viable:** Las fases 1–7 dan funcionalidad completa a nivel de API. La fase 9 (PDF) puede dejarse para una segunda iteración si urge entregar.
>
> **PDF:** Solo se genera para movimientos en estado `confirmed` (decisión confirmada). Los movimientos en `pending_signature` no tienen PDF.

### Impacto en tests existentes

> ⚠️ Los Feature tests actuales crean movimientos y luego verifican el stock. Al introducir `pending_signature`, el stock ya **no** se reduce al crear — los tests de stock deberán llamar a `ConfirmMovementUseCase` o pasar por el endpoint `confirm` para que el stock cambie. Planificar actualización de todos los tests de movimiento como parte de la fase 4.
