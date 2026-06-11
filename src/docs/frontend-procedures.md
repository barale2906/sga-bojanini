# Guía Frontend — Procedimientos y Registros de Procedimientos por Paciente

## Contexto

El menú de navegación (`GET /api/v1/auth/menu`) fue **reorganizado** y ahora incluye,
dentro del nuevo grupo **"Configuración" → "Centros de Costo"**, dos ítems que antes no
se exponían:

- **Procedimientos** (`procedures`) → gestión de **tarifas** (`ProcedurePrice`) de los
  procedimientos médicos.
- **Registros de procedimientos** (`patient-procedure-records`) → registro de
  procedimientos ejecutados a pacientes ("procedimientos dependientes" del paciente),
  con cálculo automático de valor total e historial.

Ambos dependen de los **Servicios médicos / Procedimientos** (`MedicalService`), que ya
existían como ítem `medical-services` en el menú.

## 0. Reorganización del menú (`GET /api/v1/auth/menu`)

El árbol de navegación que devuelve el backend tiene **hasta 3 niveles**, pero el
**menú/navegación del frontend solo debe renderizar los niveles 1 y 2**:

- **Nivel 1**: ítems de primer nivel. Algunos son ítems hoja con `route` propio
  (Tablero, Reportes, Auditoría — sin dropdown) y otros son **grupos contenedores**
  (`route: null`) que agrupan secciones relacionadas (Inventario, Compras, Monitoreo,
  Gestión, Configuración) — **estos se renderizan como dropdown**.
- **Nivel 2**: dentro de un grupo contenedor, puede haber **secciones** (`route: null`,
  con sus propios `children`) o **ítems hoja** directos (con `route` y `actions`). Este
  es el **último nivel que se muestra en el menú/dropdown**.
- **Nivel 3** (`children` de las secciones de nivel 2, ej. `catalog`, `warehouse`,
  `inventory`, `cost-center`, `admin`, `integration`): el backend los incluye en la
  respuesta para que el frontend conozca las pantallas/permisos disponibles, pero
  **no se renderizan en el árbol de navegación**. El frontend los usa como **diseño
  interno de cada página** (p. ej. tabs, tarjetas o accesos dentro de la pantalla de
  "Catálogo" para Productos, Categorías, etc.), no como ítems del menú lateral.

**Regla de renderizado para el frontend:**
- Si un ítem de nivel 1 tiene `children` vacío (`[]`) → enlace directo (sin dropdown),
  usando su propio `route`.
- Si un ítem de nivel 1 tiene `children` → renderizar como dropdown, mostrando cada
  hijo de **nivel 2** como enlace directo (con su propio `route`).
- Los `children` de los ítems de **nivel 2** (nivel 3) **no se pintan en el menú**;
  quedan disponibles en el JSON para que cada pantalla decida su propia navegación
  interna.

Como siempre, una sección/grupo solo aparece si el usuario tiene permiso para ver al
menos uno de sus hijos. El campo `actions` (`create`/`edit`/`delete`/etc.) refleja los
permisos `*.crear` / `*.editar` / `*.eliminar` / específicos del usuario autenticado.
**El menú sigue siendo la fuente de verdad** para mostrar/ocultar accesos y botones —
no hardcodear permisos en el frontend.

### Estructura completa (todos los permisos)

```
1. Tablero                              (key: dashboard, ítem hoja, sin dropdown)
2. Inventario                           (key: inventory-management, grupo, dropdown)
   ├── Catálogo                         (key: catalog, nivel 2 — diseño interno: nivel 3 ↓)
   │    ├── Productos                   (products)
   │    ├── Categorías                  (categories)
   │    ├── Unidades de medida          (units-of-measure)
   │    ├── Clasificaciones             (product-classifications)
   │    └── Proveedores                 (suppliers)
   ├── Almacén                          (key: warehouse, nivel 2 — diseño interno: nivel 3 ↓)
   │    ├── Almacenes                   (warehouses)
   │    ├── Zonas                       (zones)
   │    └── Ubicaciones                 (locations)
   └── Inventario                       (key: inventory, nivel 2 — diseño interno: nivel 3 ↓)
        ├── Stock actual                (stock)
        ├── Lotes                       (batches)
        └── Movimientos                 (movements)
3. Compras                              (key: purchasing, grupo, dropdown)
   └── Órdenes de compra                (purchase-orders, nivel 2)
4. Monitoreo                            (key: monitoring, grupo, dropdown)
   ├── Sensores                         (sensors, nivel 2)
   ├── Lecturas                         (sensor-readings, nivel 2)
   └── Reglas de alerta                 (alert-rules, nivel 2)
5. Gestión                              (key: management, grupo, dropdown)
   ├── Reportes                         (reports, nivel 2, ítem hoja)
   └── Auditoría                        (audit, nivel 2, ítem hoja)
6. Configuración                        (key: configuration, grupo, dropdown)
   ├── Centros de Costo                 (key: cost-center, nivel 2 — diseño interno: nivel 3 ↓)
   │    ├── Centros de costo            (cost-centers)
   │    ├── Servicios médicos           (medical-services)
   │    ├── Procedimientos              (procedures)            ← nuevo
   │    └── Registros de procedimientos (patient-procedure-records) ← nuevo
   ├── Administración                   (key: admin, nivel 2 — diseño interno: nivel 3 ↓)
   │    ├── Usuarios                    (users)
   │    └── Roles y permisos            (roles)
   └── Integraciones                    (key: integration, nivel 2 — diseño interno: nivel 3 ↓)
        ├── Consumos clínicos           (consumptions)
        └── Integraciones externas      (integrations)
```

> **Resumen para frontend:**
> - El menú lateral/dropdowns se construye **solo con los niveles 1 y 2** de este
>   árbol (6 ítems de nivel 1; los grupos despliegan sus secciones/ítems de nivel 2).
> - Los `children` de nivel 2 (Productos, Categorías, Almacenes, Stock actual,
>   Procedimientos, Usuarios, Consumos clínicos, etc.) **no van en el menú** — son
>   las pantallas/accesos que cada sección de nivel 2 organiza internamente
>   (tabs, tarjetas, sub-rutas dentro de su propia página).
> - Ningún `key`, `route`, `permission` ni `actions` de los ítems existentes cambió.

### Árbol del grupo `configuration` → sección `cost-center`

```json
{
  "key": "configuration",
  "label": "Configuración",
  "icon": "sliders-horizontal",
  "route": null,
  "permission": null,
  "actions": [],
  "children": [
    {
      "key": "cost-center",
      "label": "Centros de Costo",
      "icon": "landmark",
      "route": null,
      "permission": null,
      "actions": [],
      "children": [
        {
          "key": "cost-centers",
          "label": "Centros de costo",
          "icon": "landmark",
          "route": "cost-centers.index",
          "permission": "centros_costo.ver",
          "actions": { "create": true, "edit": true, "delete": true }
        },
        {
          "key": "medical-services",
          "label": "Servicios médicos",
          "icon": "stethoscope",
          "route": "medical-services.index",
          "permission": "servicios_medicos.ver",
          "actions": { "create": true, "edit": true, "delete": true }
        },
        {
          "key": "procedures",
          "label": "Procedimientos",
          "icon": "clipboard-list",
          "route": "procedures.index",
          "permission": "procedimientos.ver",
          "actions": { "create": true, "edit": true, "delete": true }
        },
        {
          "key": "patient-procedure-records",
          "label": "Registros de procedimientos",
          "icon": "clipboard-check",
          "route": "patient-procedure-records.index",
          "permission": "registros_procedimientos.ver",
          "actions": { "create": true, "edit": true, "delete": true }
        }
      ]
    },
    { "key": "admin", "label": "Administración", "...": "..." },
    { "key": "integration", "label": "Integraciones", "...": "..." }
  ]
}
```

### Permisos involucrados (Procedimientos / Registros)

| Permiso | Uso |
|---|---|
| `servicios_medicos.ver/crear/editar/eliminar` | CRUD de servicios y procedimientos (`medical-services`) |
| `procedimientos.ver/crear/editar/eliminar` | CRUD de **tarifas** de procedimientos (`procedures/{id}/prices`) |
| `registros_procedimientos.ver/crear/editar/eliminar` | CRUD de registros de procedimientos por paciente e historial |

---

## 1. Modelo de datos

`MedicalService` es un árbol de **2 niveles**:

- **Nodo raíz** → `type = "service"` (ej. "Urgencias", "Hospitalización"). No tiene `parent_id`.
- **Nodo hijo** → `type = "procedure"` (ej. "Curaciones simples"). Tiene `parent_id` apuntando
  al servicio padre. **Solo los procedimientos** pueden tener tarifas (`ProcedurePrice`) y
  registros de pacientes (`PatientProcedureRecord`).

```
Servicio (type=service)
 └── Procedimiento (type=procedure)
      ├── Tarifas (histórico de precios)
      └── Registros de pacientes (consumo/atención)
```

El `type` de un registro **no se puede cambiar** después de creado.

---

## 2. Servicios médicos y procedimientos

Base: `/api/v1/medical-services`
Permiso de lectura: `servicios_medicos.ver`

### 2.1 Listar (plano, con filtros)

```
GET /api/v1/medical-services
GET /api/v1/medical-services?type=procedure
GET /api/v1/medical-services?type=procedure&parent_id=1
GET /api/v1/medical-services?is_active=true
GET /api/v1/medical-services?search=cirugia
```

**Query params:**

| Param | Tipo | Descripción |
|---|---|---|
| `type` | `service` \| `procedure` | Filtra por tipo |
| `parent_id` | integer | Filtra procedimientos de un servicio padre |
| `is_active` | boolean | Filtra por estado |
| `search` | string | Busca por código o nombre |

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Listado de servicios médicos",
  "data": [
    {
      "id": 1,
      "type": "service",
      "type_label": "Servicio",
      "parent_id": null,
      "code": "URG",
      "name": "Urgencias",
      "description": null,
      "is_active": true
    },
    {
      "id": 2,
      "type": "procedure",
      "type_label": "Procedimiento",
      "parent_id": 1,
      "code": "CUR-001",
      "name": "Curaciones simples",
      "description": "Curación menor sin sutura",
      "is_active": true
    }
  ]
}
```

> Nota: este endpoint plano **no** trae `children`. Úsalo para tablas/listados.

### 2.2 Árbol completo (servicios con sus procedimientos anidados)

Útil para selectores tipo "Servicio → Procedimiento" en formularios (p. ej. registro
de salidas de inventario o registros de pacientes).

```
GET /api/v1/medical-services/tree
GET /api/v1/medical-services/tree?only_active=true
```

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Árbol de servicios médicos",
  "data": [
    {
      "id": 1,
      "type": "service",
      "type_label": "Servicio",
      "parent_id": null,
      "code": "URG",
      "name": "Urgencias",
      "description": null,
      "is_active": true,
      "children": [
        {
          "id": 2,
          "type": "procedure",
          "type_label": "Procedimiento",
          "parent_id": 1,
          "code": "CUR-001",
          "name": "Curaciones simples",
          "description": "Curación menor sin sutura",
          "is_active": true,
          "children": []
        }
      ]
    }
  ]
}
```

### 2.3 Procedimientos de un servicio

```
GET /api/v1/medical-services/{medical_service}/procedures
```

**Respuesta 200:** array de `MedicalServiceResource` con `type=procedure`.

**Errores:**
- `409` — `"Servicio médico con id 99 no encontrado."`
- `409` — `"Los procedimientos no pueden contener otros procedimientos."` (si el id es de tipo `procedure`)

### 2.4 Obtener uno

```
GET /api/v1/medical-services/{id}
```

- `200` → `MedicalServiceResource`
- `404` → `{"success": false, "message": "Servicio médico no encontrado"}`

### 2.5 Crear

```
POST /api/v1/medical-services
```
Permiso: `servicios_medicos.crear`

**Payload:**
```json
{
  "type": "procedure",
  "parent_id": 1,
  "code": "CUR-001",
  "name": "Curaciones simples",
  "description": "Curación menor sin sutura",
  "is_active": true
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `type` | string | opcional, `service` (default) \| `procedure` |
| `parent_id` | integer | requerido si `type=procedure`; debe existir y ser de `type=service` |
| `code` | string | requerido, máx 20, único (se guarda en MAYÚSCULAS) |
| `name` | string | requerido, máx 100 |
| `description` | string | opcional |
| `is_active` | boolean | opcional, default `true` |

**Respuestas:**
- `201` → `MedicalServiceResource`
- `422` → errores de validación estándar de Laravel
- `409`:
  - `"Ya existe un servicio médico con el código 'CUR-001'."`
  - `"Un procedimiento debe tener un servicio padre (parent_id es requerido)."`
  - `"El servicio padre con id {id} no existe."`
  - `"Un procedimiento no puede ser hijo de otro procedimiento. Solo puede ser hijo de un servicio."`
  - `"Un servicio no puede tener un padre. Solo los procedimientos tienen padre."`

### 2.6 Actualizar

```
PUT /api/v1/medical-services/{medical_service}
```
Permiso: `servicios_medicos.editar`

Mismo payload que crear (todos los campos requeridos salvo `type`/`parent_id`/`is_active`,
ver reglas de `UpdateMedicalServiceRequest`).

**Errores 409 adicionales:**
- `"Servicio médico con id {id} no encontrado."`
- `"No se puede cambiar el tipo de 'Servicio' a 'Procedimiento' una vez creado."` (el `type` es inmutable)
- `"Un procedimiento no puede ser su propio padre."`

### 2.7 Eliminar (soft delete)

```
DELETE /api/v1/medical-services/{medical_service}
```
Permiso: `servicios_medicos.eliminar`

**Respuestas:**
- `200` → `{"success": true, "message": "Servicio médico eliminado"}`
- `409`:
  - `"No se puede eliminar el servicio porque tiene movimientos de inventario asociados."`
  - `"No se puede eliminar el servicio porque tiene procedimientos asociados. Elimine primero los procedimientos."` (solo si es `service`)
  - `"No se puede eliminar el procedimiento porque tiene tarifas registradas. Elimine primero las tarifas."` (solo si es `procedure`)
  - `"No se puede eliminar el procedimiento porque tiene registros de pacientes asociados."` (solo si es `procedure`)

---

## 3. Procedimientos — Tarifas (Item de menú "Procedimientos")

Base: `/api/v1/procedures/{procedure}/prices`
`{procedure}` = ID de un `medical_service` con `type=procedure`.

### 3.1 Listar tarifas de un procedimiento

```
GET /api/v1/procedures/{procedure}/prices
GET /api/v1/procedures/{procedure}/prices?is_active=true
GET /api/v1/procedures/{procedure}/prices?effective_from=2026-01-01
```
Permiso: `procedimientos.ver`

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Tarifas del procedimiento",
  "data": [
    {
      "id": 1,
      "medical_service_id": 2,
      "unit_price": 50000,
      "effective_from": "2026-01-01",
      "effective_to": null,
      "is_active": true,
      "is_currently_valid": true,
      "notes": "Tarifa con IVA incluido"
    }
  ]
}
```

> `is_currently_valid`: `true` si `is_active = true`, la fecha actual ≥ `effective_from`
> y (sin `effective_to` o fecha actual ≤ `effective_to`). Útil para resaltar la tarifa
> vigente en la UI.

**Error 409:** `"Procedimiento con id 99 no encontrado."` o
`"El registro con id {id} es un servicio, no un procedimiento."`

### 3.2 Obtener una tarifa

```
GET /api/v1/procedures/{procedure}/prices/{price}
```
- `200` → `ProcedurePriceResource`
- `404` → `{"success": false, "message": "Tarifa no encontrada"}`

### 3.3 Crear tarifa

```
POST /api/v1/procedures/{procedure}/prices
```
Permiso: `procedimientos.crear`

**Payload:**
```json
{
  "unit_price": 50000,
  "effective_from": "2026-01-01",
  "effective_to": "2026-12-31",
  "is_active": true,
  "notes": "Tarifa con IVA incluido"
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `unit_price` | number | requerido, `>= 0` |
| `effective_from` | string | requerido, formato `Y-m-d` |
| `effective_to` | string\|null | opcional, formato `Y-m-d`, `>= effective_from` |
| `is_active` | boolean | opcional, default `true` |
| `notes` | string\|null | opcional, máx 500 |

**Respuestas:**
- `201` → `ProcedurePriceResource`
- `422` → ej. `{"effective_to": ["La fecha de vencimiento debe ser igual o posterior a la fecha de vigencia."]}`
- `409`:
  - `"Procedimiento con id {id} no encontrado."`
  - `"El registro '{nombre}' es un servicio, no un procedimiento. Las tarifas solo aplican a procedimientos."`
  - `"La fecha de vencimiento no puede ser anterior a la fecha de vigencia."`

### 3.4 Actualizar tarifa

```
PUT /api/v1/procedures/{procedure}/prices/{price}
```
Permiso: `procedimientos.editar`

Mismo payload que crear. Error 409 adicional: `"Tarifa con id {id} no encontrada."`

### 3.5 Eliminar tarifa (soft delete)

```
DELETE /api/v1/procedures/{procedure}/prices/{price}
```
Permiso: `procedimientos.eliminar`

- `200` → `{"success": true, "message": "Tarifa eliminada"}`
- `409` → `"Tarifa con id {id} no encontrada."`

### Flujo sugerido de UI — "Procedimientos"

1. Listar procedimientos: `GET /api/v1/medical-services?type=procedure` (con `search` para
   filtrar). Mostrar `code`, `name`, servicio padre (resolver `parent_id` contra
   `GET /api/v1/medical-services/tree` o `GET /api/v1/medical-services?type=service`).
2. Al seleccionar un procedimiento, mostrar su histórico de tarifas con
   `GET /api/v1/procedures/{id}/prices`, ordenable por `effective_from`.
3. Resaltar la tarifa con `is_currently_valid = true` como "tarifa vigente".
4. Crear/editar/eliminar tarifas con los endpoints de la sección 3.

---

## 4. Registros de Procedimientos por Paciente (Item de menú "Registros de procedimientos")

Base: `/api/v1/patient-procedure-records`
Solo aplica a `medical_service` con `type=procedure`. El campo `total` se calcula
automáticamente como `quantity * unit_price` — **no se envía en el payload**.

### 4.1 Listar (con filtros)

```
GET /api/v1/patient-procedure-records
GET /api/v1/patient-procedure-records?medical_service_id=2
GET /api/v1/patient-procedure-records?patient_external_id=PAC-001
GET /api/v1/patient-procedure-records?patient_document=12345678
GET /api/v1/patient-procedure-records?service_date_from=2026-01-01&service_date_to=2026-12-31
GET /api/v1/patient-procedure-records?is_active=true
```
Permiso: `registros_procedimientos.ver`

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Registros de procedimientos",
  "data": [
    {
      "id": 1,
      "medical_service_id": 2,
      "patient_external_id": "PAC-001",
      "patient_document": "12345678",
      "patient_first_name": "Juan Carlos",
      "patient_last_name": "Pérez Gómez",
      "quantity": 2,
      "unit_price": 50000,
      "total": 100000,
      "service_date": "2026-06-01",
      "is_active": true
    }
  ]
}
```

### 4.2 Obtener uno

```
GET /api/v1/patient-procedure-records/{id}
```
- `200` → `PatientProcedureRecordResource`
- `404` → `{"success": false, "message": "Registro de procedimiento no encontrado"}`

### 4.3 Crear registro

```
POST /api/v1/patient-procedure-records
```
Permiso: `registros_procedimientos.crear`

**Payload:**
```json
{
  "medical_service_id": 2,
  "patient_external_id": "PAC-001",
  "patient_document": "12345678",
  "patient_first_name": "Juan Carlos",
  "patient_last_name": "Pérez Gómez",
  "quantity": 2,
  "unit_price": 50000,
  "service_date": "2026-06-01",
  "notes": "Curación post-quirúrgica"
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `medical_service_id` | integer | requerido, debe existir y ser `type=procedure` |
| `patient_external_id` | string | requerido, máx 100 — ID del paciente en el sistema externo (HIS) |
| `patient_document` | string | requerido, máx 50 |
| `patient_first_name` | string | requerido, máx 100 |
| `patient_last_name` | string | requerido, máx 100 |
| `quantity` | number | requerido, `> 0` (mínimo `0.0001`) |
| `unit_price` | number | requerido, `>= 0` |
| `service_date` | string | requerido, formato `Y-m-d`, **no puede ser futura** |
| `notes` | string\|null | opcional, máx 500 |

> Sugerencia: precargar `unit_price` con la tarifa vigente del procedimiento
> (`is_currently_valid = true` en `GET /api/v1/procedures/{id}/prices`), pero permitir
> que el usuario lo edite manualmente si aplica un valor distinto.

**Respuestas:**
- `201` → `PatientProcedureRecordResource` (incluye `total` calculado)
- `422` → validación estándar, ej.:
  - `"Los nombres del paciente son obligatorios."`
  - `"Los apellidos del paciente son obligatorios."`
  - `"La cantidad debe ser mayor a cero."`
  - `"La fecha de atención no puede ser futura."`
  - `"El procedimiento seleccionado no existe."`
- `409`:
  - `"Procedimiento con id {id} no encontrado."`
  - `"El registro '{nombre}' es un servicio, no un procedimiento. Los registros de paciente solo aplican a procedimientos."`

### 4.4 Actualizar registro

```
PUT /api/v1/patient-procedure-records/{patient_procedure_record}
```
Permiso: `registros_procedimientos.editar`

Mismo payload que crear, más `is_active` (boolean, opcional). El `total` se recalcula
automáticamente.

**Error 409:** `"Registro de procedimiento con id {id} no encontrado."`

### 4.5 Eliminar (soft delete)

```
DELETE /api/v1/patient-procedure-records/{patient_procedure_record}
```
Permiso: `registros_procedimientos.eliminar`

- `200` → `{"success": true, "message": "Registro eliminado"}`
- `409` → `"Registro de procedimiento con id {id} no encontrado."`

### 4.6 Historial de un paciente (con resumen)

Endpoint clave para la ficha del paciente — trae todos sus procedimientos enriquecidos
con nombre del procedimiento y del servicio padre, más un resumen agregado.

```
GET /api/v1/patients/{patientExternalId}/procedure-records
GET /api/v1/patients/PAC-001/procedure-records?service_date_from=2026-01-01&service_date_to=2026-12-31
GET /api/v1/patients/PAC-001/procedure-records?medical_service_id=2
GET /api/v1/patients/PAC-001/procedure-records?is_active=true
```
Permiso: `registros_procedimientos.ver`

**Query params:** `service_date_from`, `service_date_to`, `medical_service_id`, `is_active`.

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Historial del paciente PAC-001",
  "data": {
    "patient_external_id": "PAC-001",
    "patient_document": "12345678",
    "patient_first_name": "Juan Carlos",
    "patient_last_name": "Pérez Gómez",
    "summary": {
      "total_records": 3,
      "total_amount": 150000.00,
      "first_service_date": "2026-01-15",
      "last_service_date": "2026-06-01"
    },
    "records": [
      {
        "id": 1,
        "service_date": "2026-06-01",
        "service_id": 1,
        "service_name": "Urgencias",
        "medical_service_id": 2,
        "procedure_code": "CUR-001",
        "procedure_name": "Curaciones simples",
        "quantity": 2,
        "unit_price": 50000,
        "total": 100000,
        "is_active": true
      }
    ]
  }
}
```

> Si el paciente no tiene registros, `data.records` es `[]`, `summary.total_records = 0`,
> `summary.total_amount = 0` y las fechas `first_service_date`/`last_service_date` son `null`.
> `patient_document`/`patient_first_name`/`patient_last_name` también serán `null` en ese caso.

### Flujo sugerido de UI — "Registros de procedimientos"

1. **Listado general** (`patient-procedure-records.index`): tabla con filtros por
   procedimiento, paciente (ID externo o documento) y rango de fechas
   (`GET /api/v1/patient-procedure-records`).
2. **Crear/editar registro**: formulario con selector de Servicio → Procedimiento (usar
   `GET /api/v1/medical-services/tree?only_active=true`, solo permitir seleccionar nodos
   `type=procedure`), datos del paciente, cantidad, precio unitario (precargado desde la
   tarifa vigente) y fecha de atención. Mostrar `total` calculado como
   `quantity * unit_price` en tiempo real (de referencia visual; el backend recalcula al
   guardar).
3. **Ficha de paciente / historial**: usar `GET /api/v1/patients/{id}/procedure-records`
   para mostrar el resumen (`summary`) en cards (total de registros, valor acumulado,
   primera/última atención) y el detalle (`records`) en una tabla o línea de tiempo.

---

## 5. Resumen de errores comunes

| Código | Cuándo ocurre |
|---|---|
| `401` | Token ausente/ inválido (`/api/v1/auth/menu` y todas las rutas requieren `auth:sanctum` + usuario activo) |
| `403` | Usuario autenticado sin el permiso requerido para la acción |
| `404` | Recurso no encontrado en `show` (`medical-services/{id}`, `patient-procedure-records/{id}`, `procedures/{p}/prices/{id}`) |
| `409` | Violación de regla de negocio (`DomainException`): código duplicado, tipo inmutable, jerarquía inválida, eliminar con dependencias, IDs no encontrados en operaciones de escritura, fechas/cantidades inválidas |
| `422` | Validación de payload (`FormRequest`) — estructura estándar `{"success": false, "message": "...", "errors": {"campo": ["mensaje"]}}` |

Todas las respuestas siguen el envoltorio estándar `{ "success": bool, "message": string, "data"?: ..., "errors"?: ... }`.
