# Guía frontend — Módulo de Activos

**Módulo backend:** `app/Modules/Assets` · **Rutas:** `routes/api/assets.php` · **Prefijo:** `/api/v1/assets`
**Frontend:** `src/app/features/assets` (Angular) · **Plan:** `docs/planes/plan-modulo-activos.md`

Inventario y control de activos: equipos biomédicos y de cómputo (con hoja de vida, cronograma de mantenimiento y soportes) y bienes generales (muebles, TV, etc., solo inventario).

---

## 1. Conceptos

| Concepto | Regla |
|---|---|
| **Tipo de activo** | Lo define la categoría: `biomedical`, `computing` o `general`. Solo los dos primeros tienen hoja de vida, planes y órdenes de mantenimiento (`requires_maintenance: true`). En `general` esos endpoints responden **409**. |
| **Código** | Automático y secuencial `000001…999999` (igual que productos). No se envía al crear. Se imprime como Code128 en la etiqueta. |
| **Ubicación** | Dos niveles fijos: **Sede → Sitio** (ej. *Clínica → Consultorio 1*). El activo guarda **historial de ubicaciones** (tramos con fecha desde/hasta). Fuera de las instalaciones: `location.is_off_premises = true` y `off_premises_location`. |
| **Comentarios** | Del equipo (no de la ubicación). Se acumulan: no se editan ni eliminan. |
| **Traslados** | Ubicación y responsable **solo** cambian por traslado. Firma obligatoria **solo si el activo sale del edificio** (otra sede, reparación externa, préstamo, baja). |
| **Firmas** | Misma metodología de inventario: quien entrega firma con su sesión ("Mi firma") o pad; quien recibe con **PIN** o pad. |
| **Mantenimiento** | Planes periódicos generan el cronograma (una orden abierta por plan). Al registrar o cancelar una orden de plan se programa la siguiente. Registrar exige **qué se hizo y quién lo hizo**; soportes y firmas son **opcionales**. |
| **Baja** | Traslado de tipo `retirement` (permiso `activos.dar_baja`, motivo obligatorio, firma). Desactiva planes y cancela órdenes abiertas. Un activo de baja no admite cambios (409). |

### Estados del activo

| `status` | Etiqueta | Cómo cambia |
|---|---|---|
| `operational` | Operativo | Manual, retorno, resultado de mantenimiento |
| `maintenance` | En mantenimiento | Manual, al iniciar orden, al salir a reparación externa |
| `out_of_service` | Fuera de servicio | Manual o resultado de mantenimiento |
| `available` | Disponible | Manual |
| `retired` | De baja | Solo por traslado `retirement` |

---

## 2. Permisos

No hay roles nuevos: se asignan a los usuarios/roles que los requieran (solo `super_administrador` los tiene por defecto).

| Permiso | Habilita |
|---|---|
| `activos.ver` | Listado, ficha, catálogos (lectura), historial, documentos (descarga), etiquetas, hoja de vida PDF, traslados (lectura) |
| `activos.crear` / `activos.editar` / `activos.eliminar` | Registrar / editar datos, hoja de vida y estado / eliminar registros sin historia |
| `activos.dar_baja` | Traslado tipo `retirement` |
| `activos.comentar` | Agregar comentarios |
| `activos.importar` / `activos.exportar` | Plantilla e importación / reportes Excel |
| `activos_catalogos.crear\|editar\|eliminar` | Categorías, sedes y sitios |
| `activos_traslados.solicitar` | Registrar traslados |
| `activos_traslados.confirmar` | Firmar (confirmar) o cancelar traslados pendientes |
| `activos_mantenimientos.ver` | Cronograma, planes y órdenes |
| `activos_mantenimientos.programar` | Planes, órdenes puntuales, reprogramar. Recibe las alertas diarias |
| `activos_mantenimientos.ejecutar` | Iniciar y registrar ejecución |
| `activos_mantenimientos.cancelar` | Cancelar órdenes |
| `activos_documentos.cargar` / `activos_documentos.eliminar` | Documentos y soportes |

### Menú (`GET /auth/menu`)

```
Activos (asset-management)
├── Activos (assets)            → assets-list, asset-transfers, asset-catalogs   ruta /assets
└── Mantenimiento (asset-maintenance) → maintenance-schedule                    ruta /assets/maintenance
```
Rutas de menú: `assets.index`, `asset-transfers.index`, `asset-catalogs.index` → `/assets` (pestañas internas); `asset-maintenance.index` → `/assets/maintenance`.

---

## 3. Catálogos

### `GET /assets/filters`
Todo lo necesario para filtros y formularios en una sola llamada:
```json
{
  "categories": [{ "id": 1, "code": "BIO", "name": "Equipo biomédico", "asset_type": "biomedical", "requires_maintenance": true }],
  "sites": [{ "id": 1, "name": "Clínica", "places": [{ "id": 3, "name": "Consultorio 1" }] }],
  "users": [{ "id": 5, "name": "Ana Gómez" }],
  "suppliers": [{ "id": 2, "name": "BioService SAS", "tax_id": "900111222" }],
  "asset_types": [{ "value": "biomedical", "label": "Equipo biomédico" }],
  "statuses": [], "settable_statuses": [],
  "transfer_types": [{ "value": "loan", "label": "Préstamo externo", "requires_signature": true }],
  "maintenance_types": [], "plannable_types": [], "maintenance_status": [], "maintenance_results": [], "document_types": []
}
```

### Categorías
| Método | Ruta | Body |
|---|---|---|
| GET | `/assets/categories?asset_type=&is_active=&search=` | — |
| POST / PUT | `/assets/categories` · `/assets/categories/{id}` | `{ code, name, asset_type, description?, is_active? }` |
| DELETE | `/assets/categories/{id}` | 409 si tiene activos |

Cambiar una categoría a `general` responde 409 si sus activos ya tienen hoja de vida o planes.

### Sedes y sitios
| Método | Ruta | Body |
|---|---|---|
| GET | `/assets/sites?is_active=1` | Sedes con `places[]` (incluye `assets_count`) |
| POST / PUT | `/assets/sites` · `/assets/sites/{id}` | `{ name, address?, is_active? }` |
| DELETE | `/assets/sites/{id}` | 409 si tiene sitios |
| POST / PUT | `/assets/places` · `/assets/places/{id}` | `{ site_id, name, is_active? }` |
| DELETE | `/assets/places/{id}` | 409 si tiene activos o historial (desactivar en su lugar) |

---

## 4. Activos

### `GET /assets` (paginado)
Filtros: `search` (código, nombre, serial, marca, modelo), `category_id`, `asset_type`, `site_id`, `place_id`, `status`, `responsible_id`, `is_off_premises`, `include_retired` (por defecto se excluyen las bajas), `warranty_expiring_days`, `maintenance_overdue`, `sort_by` (`code|name|status|acquisition_date|warranty_until|created_at`), `sort_dir`, `per_page` (≤100).

```json
{
  "id": 1, "code": "000001", "name": "Monitor de signos vitales",
  "category": { "id": 1, "code": "BIO", "name": "Equipo biomédico" },
  "asset_type": "biomedical", "asset_type_label": "Equipo biomédico", "requires_maintenance": true,
  "status": "operational", "status_label": "Operativo",
  "location": { "is_off_premises": false, "off_premises_location": null, "place_id": 3, "place_name": "Consultorio 1", "site_id": 1, "site_name": "Clínica" },
  "responsible": { "id": 5, "name": "Ana Gómez" },
  "brand": "Mindray", "model": "iMEC 10", "serial": "SN-458722",
  "acquisition_date": "2024-03-15", "acquisition_value": "12500000.00",
  "supplier": { "id": 2, "name": "BioService SAS", "tax_id": "900111222" }, "invoice_number": "FV-1020",
  "warranty_until": "2026-03-15",
  "next_maintenance": { "id": 9, "type": "preventive", "type_label": "Preventivo", "status": "scheduled", "scheduled_date": "2026-11-02", "is_overdue": false },
  "pending_transfer": null
}
```
`GET /assets/{id}` agrega `technical_profile`, `counts { comments, documents, maintenance_plans }`, `created_by`.

### Registrar / editar
- `POST /assets` — `{ name*, category_id*, place_id*, responsible_id?, status? (no retired), comment?, description?, brand?, model?, serial?, acquisition_date?, acquisition_value?, supplier_id?, invoice_number?, warranty_until?, notes? }` → 201 con el activo (incluye `code`).
- `PUT /assets/{id}` — mismos datos generales; **ignora** `place_id`, `responsible_id` y `status`.
- `DELETE /assets/{id}` — solo registros sin historia (409 si tiene mantenimientos realizados o traslados confirmados → usar baja).
- `POST /assets/{id}/status` — `{ status: operational|maintenance|out_of_service|available, reason? }`.

### Indicadores — `GET /assets/statistics?site_id=&asset_type=&category_id=`
```json
{ "total": 120, "by_status": { "operational": 100, "maintenance": 5, "out_of_service": 3, "available": 10, "retired": 2 },
  "by_type": { "biomedical": 40, "computing": 50, "general": 30 },
  "by_site": [{ "site_id": 1, "site": "Clínica", "total": 80 }],
  "off_premises": 2, "total_value": 950000000, "pending_transfers": 1,
  "maintenance": { "overdue": 3, "upcoming": 7, "in_progress": 1 } }
```

### Historial y comentarios
- `GET /assets/{id}/location-history` → tramos `{ location: "Clínica → Consultorio 1", from_date, to_date, is_current, is_off_premises, transfer_type, transfer_label, assigned_by }`.
- `GET /assets/{id}/movements` (paginado) → bitácora `{ type, type_label, from_status, to_status, description, user, created_at }`.
- `GET /assets/{id}/comments` · `POST /assets/{id}/comments` `{ comment }` (permiso `activos.comentar`).

### Hoja de vida (solo biomédicos y cómputo)
- `GET /assets/{id}/technical-profile` (`data: null` si no existe).
- `PUT /assets/{id}/technical-profile` — campos comunes (`manufacturer`, `country_of_origin`, `manufacture_year`, `useful_life_years`, `acquisition_mode`, `technical_specs {clave: valor}`, `accessories`, `usage_recommendations`), biomédicos (`invima_registry`, `risk_class I|IIa|IIb|III`, `biomedical_class`, `power_source`, `voltage`, `current`, `power`, `frequency`, `requires_calibration`, `operating_conditions`) y de cómputo (`processor`, `ram`, `storage`, `operating_system`, `ip_address`, `mac_address`, `hostname`, `licenses [{name, key?, expires_at?}]`). Los campos del otro tipo se descartan.
- `GET /assets/{id}/life-sheet` → **PDF** con datos, hoja de vida, planes, historial de mantenimientos y ubicaciones, documentos y comentarios.
- `GET /assets/labels?ids=1,2,3` → **PDF** de etiquetas Code128 (máx. 200; sin `ids` usa los filtros del listado).

---

## 5. Traslados

### Tipos
| `type` (solicitud) | Requiere | Firma | Efecto |
|---|---|---|---|
| `relocation` | `to_place_id` y/o `to_responsible_id` | Opcional — **obligatoria si el sitio es de otra sede** (se registra como `site_transfer`) | Cambia sitio/responsable |
| `external_repair` | `external_destination`, opc. `supplier_id`, `expected_return_date` | Obligatoria | Sale del edificio; estado → `maintenance` |
| `loan` | `external_destination`, opc. `expected_return_date` | Obligatoria | Sale del edificio |
| `return` | `to_place_id`, opc. `to_responsible_id`, `resulting_status` | Opcional | Regresa a un sitio |
| `retirement` | `reason` (+ permiso `activos.dar_baja`), opc. `external_destination` | Obligatoria | Baja definitiva |

Reglas: un activo fuera de las instalaciones solo admite `return` o `retirement`; un traslado pendiente bloquea otro (409); un traslado que no cambia nada responde 409.

### Endpoints
- `POST /assets/{id}/transfers` — body del tipo + **firmas opcionales**:
  ```json
  { "type": "external_repair", "external_destination": "Taller BioService", "supplier_id": 2,
    "received_by": { "pin": "123" } }
  ```
  - Sin firmas y tipo con firma obligatoria → `status: "pending_signature"` (el activo no cambia).
  - Con firmas, o tipo sin firma obligatoria → se aplica (`status: "confirmed"`).
- `POST /assets/transfers/{id}/confirm` — `{ delivered_by?, received_by* }` (permiso `activos_traslados.confirmar`).
- `POST /assets/transfers/{id}/cancel` — `{ reason* }`.
- `GET /assets/transfers?status=pending_signature&type=&asset_id=&search=` (paginado) · `GET /assets/transfers/{id}`.
- `GET /assets/transfers/{id}/act` → **PDF** (acta con firmas).

### Firmantes (igual que inventario)
```json
"delivered_by": { "name": "...", "document": "...", "signature": "data:image/png;base64,..." },  // opcional: si se omite, firma registrada de la sesión
"received_by":  { "pin": "123" }                                                                // o { name, document, signature } para externos
```
Errores: **422** `received_by.pin` (PIN inválido), **429** (3 intentos fallidos / 15 min), **409** si "Mi firma" no está registrada y se omitió `delivered_by`, o si entrega y recibe la misma persona. La respuesta incluye `signatures[]` **sin la imagen** (solo nombre, documento, método y fecha); la imagen va en el acta PDF.

Componente reutilizable del front: `app-signers-form` (`shared/components/signers-form`) — inputs `deliveredLabel`, `receivedLabel`, `initialReceivedMode`, `deliveredByPad`; método `collect()` → `{ valid, payload | errors }`; `resetPin()`.

---

## 6. Mantenimiento

### Planes
- `GET /assets/{id}/maintenance-plans` → `{ maintenance_type, frequency_value, frequency_unit, frequency_label, start_date, performed_by_type, technician, supplier, is_active, next_date }`.
- `POST /assets/{id}/maintenance-plans` · `PUT /assets/maintenance-plans/{planId}` — `{ maintenance_type (preventive|calibration|metrological_verification|inspection), frequency_value, frequency_unit (days|months), start_date, performed_by_type (internal|external), technician_id?, supplier_id?, notes?, is_active? }`. Si `start_date` ya pasó, se programa la siguiente fecha futura. Desactivar cancela la orden programada.
- `DELETE /assets/maintenance-plans/{planId}`.

### Órdenes y cronograma
- `GET /assets/maintenances` (paginado, `per_page` ≤ 500) — filtros `from`, `to` (fecha programada), `status`, `maintenance_type`, `asset_id`, `site_id`, `asset_type`, `overdue`, `search`. Cada orden trae `is_overdue`, `asset { id, code, name, asset_type, location }`, ejecutor, resultado, `documents_count`.
- `GET /assets/maintenances/{id}` (incluye `documents[]` y `signatures[]`).
- `POST /assets/{id}/maintenances` — orden puntual `{ maintenance_type, scheduled_date, reported_failure (obligatorio en corrective), performed_by_type?, technician_id?, supplier_id? }`.
- `PUT /assets/maintenances/{id}` — reprogramar `{ scheduled_date, reported_failure? }` (solo `scheduled`).
- `POST /assets/maintenances/{id}/start` → activo en `maintenance`.
- `POST /assets/maintenances/{id}/complete`:
  ```json
  { "performed_at": "2026-10-17 08:30:00", "performed_by_type": "external",
    "supplier_id": 2, "external_technician_name": "Carlos Pérez",       // o technician_id si es internal
    "activities_performed": "Limpieza y prueba de alarmas", "findings": null, "recommendations": null,
    "result": "operational", "parts_replaced": [{ "name": "Batería", "quantity": 1, "cost": 85000 }], "cost": 150000,
    "delivered_by": { "...pad del técnico externo..." }, "received_by": { "pin": "123" } }   // firmas opcionales
  ```
  `registered_by` = usuario de la sesión. El resultado define el estado del activo (`out_of_service` → Fuera de servicio; los demás → Operativo). Los soportes se cargan después con `maintenance_id`.
- `POST /assets/maintenances/{id}/cancel` — `{ reason* }`. Si estaba en ejecución, el activo vuelve a operativo; si venía de un plan, se programa la siguiente.
- `GET /assets/maintenances/{id}/report` → **PDF**.

### Alertas
Comando diario `sga:check-asset-alerts` (06:15): mantenimientos vencidos y de los próximos 15 días, garantías y certificados de calibración que vencen en 30 días. Notifica (BD + correo) a quien tenga `activos_mantenimientos.programar`. Tipos de notificación: `asset_maintenance_alert`, `asset_expiration_alert`.

---

## 7. Documentos y soportes

- `GET /assets/{id}/documents?maintenance_id=` · `POST /assets/{id}/documents` (multipart): `file*` (PDF/JPG/PNG ≤ 10 MB), `document_type*` (`manual|invoice|warranty|invima|calibration_certificate|service_report|photo|protocol|other`), `title*`, `maintenance_id?`, `issued_at?`, `expires_at?`.
- `GET /assets/documents/{docId}/download` (blob, autenticado) · `DELETE /assets/documents/{docId}`.

---

## 8. Importación masiva y reportes

- `GET /assets/import/template` → Excel generado con los catálogos vigentes. Hojas: **Activos**, **Hoja de vida biomédica**, **Hoja de vida cómputo** (relacionadas por `serial`), **Categorías** y **Sedes y sitios** (referencia; las filas nuevas se crean), **Instrucciones**. La fila 2 (ayuda en español) se ignora; la fila 3 es un ejemplo.
- `POST /assets/import` (multipart `file`) →
  ```json
  { "total": 10, "created": 9, "failed": 1,
    "errors": [{ "sheet": "Activos", "row": 5, "errors": { "category_code": ["La categoría no existe."] } }],
    "related_catalogs": { "categories": { "total": 2, "created": 1, "skipped": 1, "failed": 0, "errors": [] }, "places": { } },
    "technical_profiles": { "total": 3, "created": 3, "skipped": 0, "failed": 0, "errors": [] } }
  ```
  Si una fila trae `maintenance_type`, se crea el plan y la próxima orden desde `last_maintenance_date` (o hoy).
- `GET /assets/reports/inventory` y `GET /assets/reports/maintenance-schedule` → Excel (aceptan los filtros de sus listados).

---

## 9. Pantallas implementadas (Angular)

| Ruta | Componente | Contenido |
|---|---|---|
| `/assets` | `AssetsPageComponent` | Pestañas **Inventario** (indicadores, distribución por sede, filtros, tabla, etiquetas, importar/exportar), **Traslados** (pendientes de firma, firmar/cancelar, acta) y **Categorías y ubicaciones** (`?tab=transfers|catalogs`) |
| `/assets/:id` | `AssetDetailComponent` | Encabezado con estado y ubicación; pestañas Resumen (+ comentarios), Hoja de vida*, Mantenimiento*, Documentos, Historial |
| `/assets/maintenance` | `MaintenanceSchedulePageComponent` | Calendario mensual y lista con semáforo, filtros y acciones por orden |

\* Solo equipos biomédicos y de cómputo.

Piezas reutilizables: `AssetCatalogStore` (catálogos cacheados), `MaintenanceActionsService` (iniciar/registrar/reprogramar/cancelar/reporte), `SitePlacePickerComponent`, `ToneChipComponent`, `ReasonDialogComponent`, `FileDownloadService` (shared), `SignersFormComponent` (shared).
