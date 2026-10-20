# Plan: Módulo de Inventario y Control de Activos

**Módulo:** `Assets` (`app/Modules/Assets/`) · **Frontend:** `sga-bojanini-front/src/app/features/assets`
**Fecha:** 2026-10-17
**Estado:** ✅ Implementado (backend + frontend) — rev. 3
**Guía de integración:** `docs/frontend-activos.md`
**Base de referencia:** módulo `Activos` de Carmot_API (`act_categorias`, `act_activos`, `act_movimientos`) y su frontend Vue.

---

## 0. Qué se tomó de Carmot y qué se mejoró

| Carmot | SGA Bojanini |
|---|---|
| Modelos Eloquent planos | Módulo Clean Architecture (Domain / Application / Infrastructure), `Auditable`, soft deletes |
| Categoría simple | Categoría con **tipo** (`biomedical`, `computing`, `general`) que define si hay hoja de vida y mantenimiento |
| `sede_id` + `area_id` | **Sede → Sitio** con **historial de ubicaciones** |
| Código manual | **Código automático** secuencial `000001` (igual que productos), etiqueta Code128 |
| Estados operativo/mantenimiento/disponible/baja | + `out_of_service` |
| Movimientos ingreso/traslado/estado | Traslados tipificados con **firma obligatoria solo al salir del edificio** + bitácora |
| — | **Comentarios acumulables** del equipo |
| — | **Hoja de vida técnica** (biomédico / cómputo) |
| — | **Planes y cronograma** de mantenimiento, registro de ejecución con ejecutor y registrador, soportes opcionales |
| — | Documentos (manuales, factura, INVIMA, certificados…) en disco privado |
| — | Importación masiva con plantilla, PDF (hoja de vida, actas, reporte de mantenimiento, etiquetas), Excel, alertas diarias |

Fuera de alcance: depreciación (la maneja contabilidad). El valor de adquisición es informativo.

---

## 1. Decisiones confirmadas

| # | Decisión |
|---|---|
| D1 | Un solo modelo `assets` para los tres tipos + `asset_technical_profiles` 1:1 para equipos. |
| D2 | El tipo de la categoría define si aplica hoja de vida/mantenimiento; en `general` esos endpoints responden 409. |
| D3 | Ubicación = **Sede → Sitio** (catálogos administrables). Independiente de almacenes de inventario. |
| D4 | **Historial de ubicaciones** por tramos (`asset_location_history`), escrito solo por `AssetCustodyService`. |
| D5 | **Comentarios del equipo** (`asset_comments`), acumulables, sin importar dónde esté. |
| D6 | **Firma obligatoria solo cuando el activo sale del edificio**: traslado a otra sede, reparación externa, préstamo y baja. Sin firmas quedan `pending_signature` y no se aplican; con firmas se aplican en un paso. Traslados internos, cambios de responsable y retornos se aplican de inmediato con firma opcional. |
| D7 | Firmas con la misma metodología de inventario (sesión / PIN / pad). La lógica se extrajo a `Auth` para reutilizarla (ver §3.4). |
| D8 | Mantenimiento: obligatorio registrar qué se hizo, resultado, **quién lo ejecutó** (técnico interno o proveedor + técnico externo) y **quién lo registró** (sesión). Soportes y firmas opcionales. |
| D9 | Sin roles nuevos: solo permisos `activos*`, asignados a quien los requiera. |
| D10 | Importación masiva con plantilla descargable generada con catálogos vigentes. |
| D11 | Código automático secuencial (generador compartido `SequentialCodeGenerator`). |
| D12 | Proyecto en producción → **migraciones nuevas** (no se editaron existentes). |
| D13 | Proveedores de mantenimiento = proveedores existentes (no se modificó el enum `supplier_type`). |

---

## 2. Base de datos (migraciones `2026_10_17_00000{1..4}`)

| Migración | Tablas |
|---|---|
| `create_asset_catalog_tables` | `asset_categories`, `asset_sites`, `asset_places` |
| `create_assets_tables` | `assets`, `asset_transfers`, `asset_location_history`, `asset_comments`, `asset_technical_profiles` |
| `create_asset_maintenance_tables` | `asset_maintenance_plans`, `asset_maintenances`, `asset_documents` |
| `create_asset_signature_and_movement_tables` | `asset_signatures` (polimórfica: `transfer` / `maintenance`), `asset_movements` (bitácora) |

Todas las FK quedan en línea (sin `ALTER` posteriores). Validado en MySQL (migrate, seed y rollback) y SQLite (tests).

Campos clave de `assets`: `code char(6)`, `category_id`, `current_place_id` (null fuera de las instalaciones), `is_off_premises`, `off_premises_location`, `responsible_id`, `status`, datos de adquisición y garantía, `retired_at`, `retirement_reason`.

---

## 3. Backend

### 3.1 Estructura
```
app/Modules/Assets/
├── Domain/
│   ├── Entities/      Asset (reglas: ensureNotRetired, ensureSupportsMaintenance, changeStatus), AssetCategory, AssetSite, AssetPlace
│   ├── Enums/         AssetType, AssetStatus, AssetTransferType (requiresSignature, leavesPremises), AssetTransferStatus,
│   │                  MaintenanceType, MaintenanceStatus, MaintenanceResult, PerformedByType, FrequencyUnit,
│   │                  AssetMovementType, AssetDocumentType, RiskClass, BiomedicalClass, AcquisitionMode, PowerSource
│   ├── Repositories/  Category / Site / Place / Asset (interfaces)
│   └── Services/      AssetTransferPolicy (tipo final y precondiciones), MaintenanceScheduleCalculator (fechas)
├── Application/
│   ├── DTOs/          AssetData, AssetCategoryData, AssetSiteData, AssetPlaceData, AssetTransferRequestData,
│   │                  MaintenancePlanData, ScheduleMaintenanceData, CompleteMaintenanceData, AssetDocumentData
│   ├── Services/      AssetCustodyService (único escritor de ubicación/responsable/historial), AssetMovementRecorder,
│   │                  AssetSignatureRecorder, AssetTransferApplier, MaintenancePlanScheduler
│   ├── Import/        AssetImportLayout (formato único de plantilla), AssetImportService, RowFailed
│   └── UseCases/      Catálogos (Save/Delete/List), Create/Update/Delete/ChangeStatus/AddComment de activos,
│                      Request/Confirm/Cancel traslado, UpsertTechnicalProfile, Save/Delete plan,
│                      Schedule/Reschedule/Start/Complete/Cancel mantenimiento, Upload/Delete documento,
│                      ImportAssets, CheckAssetAlerts
└── Infrastructure/
    ├── Persistence/   Modelos Eloquent + repositorios
    ├── Queries/       AssetQuery, AssetMaintenanceQuery, AssetTransferQuery (lado de lectura: listados, ficha, estadísticas)
    ├── Http/          Controllers (10), Requests (+ Concerns/AssetDetailRules), Resources
    ├── Import/        AssetsImport (multi-hoja)
    ├── Export/        AssetImportTemplateBuilder, AssetInventoryExport, MaintenanceScheduleExport
    ├── Pdf/           AssetPdfRenderer (hoja de vida, acta de traslado, reporte de mantenimiento, etiquetas)
    └── Notifications/ AssetMaintenanceAlertNotification, AssetExpirationAlertNotification
```
Escrituras por casos de uso; lecturas por clases `Queries` (CQRS ligero). Vistas PDF en `resources/views/reports/assets/`.

### 3.2 Reglas de negocio
1. Activo de baja: no se edita, traslada, comenta en la UI ni recibe mantenimientos (409).
2. Categoría `general`: sin hoja de vida, planes ni órdenes (409). No se puede pasar a `general` una categoría/activo con hoja de vida o planes.
3. Traslados: un pendiente bloquea otro; fuera de las instalaciones solo `return` o `retirement`; un traslado sin cambios es 409; `relocation` entre sedes se registra como `site_transfer` (firma obligatoria).
4. Baja: permiso `activos.dar_baja`, motivo obligatorio, firma; desactiva planes y cancela órdenes abiertas.
5. Cada plan activo mantiene una orden abierta; al registrar o cancelar una orden de plan se programa la siguiente (meses respetan fin de mes).
6. Registrar mantenimiento: fecha (no futura), actividades, resultado y ejecutor obligatorios; `registered_by` = sesión. El resultado fija el estado del activo.
7. Eliminar un activo solo si no tiene historia (mantenimientos realizados o traslados confirmados).
8. Todo cambio de ubicación, responsable o estado queda en `asset_movements` en la misma transacción.

### 3.3 Integración con el sistema
- Bindings en `ModuleServiceProvider` (Fase 12) y rutas en `routes/api/assets.php` (registradas en `routes/api.php`).
- Permisos en `RolesAndPermissionsSeeder`; categorías base en `AssetSeeder` (incluido en `DatabaseSeeder`).
- Menú: grupo `asset-management` con secciones `assets` y `asset-maintenance` (`MenuBuilder`).
- Comando `sga:check-asset-alerts` programado a las 06:15 (`routes/console.php`).

### 3.4 Refactors reutilizables (fuera del módulo)
| Cambio | Motivo |
|---|---|
| `SignerInput` movido de Inventory a `Auth/Application/DTOs` | Lo usan Inventario y Activos |
| Nuevo `Auth/Application/Services/SignerSnapshotResolver` | Lógica única de firma (stored/pin/pad + misma persona); `ConfirmMovementUseCase` ahora lo usa |
| Nuevo trait `Auth/Infrastructure/Http/Requests/Concerns/ValidatesSigners` | Reglas y mensajes de firmantes (obligatorios u opcionales); `ConfirmMovementRequest` lo usa |
| Nuevo `Shared/Domain/Services/SequentialCodeGenerator` | `BarcodeValueGenerator` de productos ahora lo extiende |
| `NotificationRecipientService::notifyByPermission()` | Notificar por permiso (el módulo no tiene roles) |
| `ExcelTemplateWriter::writeDataSheet(..., $helpComment)` | Comentario de ayuda configurable |
| Nuevo `Shared/Infrastructure/Import/HeadingRowSheetImport` | Lector de hojas con normalización (códigos/seriales numéricos → texto) |

### 3.5 Pruebas
- Feature: `tests/Feature/Assets/` — catálogos, activos, traslados/firmas, mantenimiento/documentos/alertas/exportes, importación (69 pruebas).
- Unit: `tests/Unit/Assets/` — `MaintenanceScheduleCalculator`, `AssetTransferPolicy`.
- Actualizadas: `MenuTest` (8 grupos), `MenuBuilderTest` (casos del grupo Activos).
- Suite completa: mismas 17 fallas preexistentes que en `main` (compras, centros de costo, conversión de presentaciones); ninguna nueva.

---

## 4. Frontend (Angular 21 + Material)

```
src/app/features/assets/
├── assets.models.ts · assets.service.ts · asset-maintenance.service.ts · asset-catalog.store.ts · assets.routes.ts
├── assets-page/            Inventario (indicadores, distribución por sede, filtros, tabla, etiquetas, importar/exportar),
│                           Traslados (pendientes de firma) y Categorías y ubicaciones
├── asset-detail/           Ficha: Resumen + comentarios, Hoja de vida*, Mantenimiento*, Documentos, Historial
├── maintenance/            Cronograma: calendario mensual + lista, semáforo y acciones
├── dialogs/                Activo, traslado, firma de traslado, estado, catálogos, importación, plan, orden,
│                           registro de ejecución (repuestos, soportes y firmas opcionales), carga de documento
└── shared/                 ToneChip, ReasonDialog, SitePlacePicker, MaintenanceActionsService, signature-errors
src/app/shared/components/signers-form/   Firma de entrega/recibido reutilizable (sesión, PIN, pad)
src/app/shared/services/file-download.service.ts   Descarga/apertura de PDF y Excel con manejo de errores
```
Rutas: `/assets`, `/assets/:id`, `/assets/maintenance` (lazy, con `permissionGuard`); `menu.service` mapea las rutas del menú.
Pruebas Vitest: modelos, calendario, servicios y `SignersFormComponent` (+ spec existente del diálogo de firma de inventario).

---

## 5. Pendientes y mejoras sugeridas
1. Migrar `MovementConfirmDialogComponent` (inventario) a `app-signers-form` para unificar la UI de firma (hoy son equivalentes; se dejó intacto para no romper sus specs).
2. Tarjeta en el Tablero con mantenimientos vencidos/próximos (el endpoint `GET /assets/statistics` ya lo provee).
3. Ejecutar en el servidor: `php artisan migrate`, `php artisan db:seed --class=RolesAndPermissionsSeeder`, `php artisan db:seed --class=AssetSeeder`, y asignar los permisos `activos*` a los roles que correspondan.
