# Guía de integración frontend — MedSys

> **Fecha:** 2026-08-19  
> **Base URL:** `/api/v1`  
> **Autenticación:** Bearer token (Sanctum) en header `Authorization: Bearer {token}`  
> **Permiso requerido:** `integraciones.ver` para todos los endpoints MedSys

---

## Índice

1. [Campo `external_code` en Servicios Médicos](#external-code)
2. [Búsqueda de pacientes en MedSys](#busqueda-pacientes)
3. [Citas activas de un paciente](#citas-paciente)
4. [Tipos de procedimiento y mapeo](#procedure-types)
5. [Proyección de consumo](#proyeccion-consumo)
6. [Sincronización de evolución clínica](#evolucion-clinica)
7. [Notas UX y comportamientos esperados](#notas-ux)
8. [Formato estándar de respuesta](#formato-respuesta)
9. [Códigos de error](#codigos-error)

---

## 1. Campo `external_code` en Servicios Médicos {#external-code}

Se agregó el campo `external_code` al catálogo de servicios médicos. Este código vincula cada servicio SGA con su equivalente en MedSys (`tiposproc.codigo`).

### En formularios de creación / edición

**Endpoint create:** `POST /medical-services`  
**Endpoint update:** `PUT /medical-services/{id}`

Campo nuevo a incluir (opcional):

```json
{
  "code": "DERM-001",
  "name": "Consulta Dermatológica",
  "type": "procedure",
  "parent_id": 1,
  "external_code": "CONS-DER",
  "is_active": true
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `external_code` | `string\|null` | Máx. 20 caracteres, único en la tabla, nullable |

### En respuestas (GET)

Todos los endpoints de servicios médicos incluyen ahora `external_code`:

```json
{
  "id": 5,
  "type": "procedure",
  "type_label": "Procedimiento",
  "parent_id": 2,
  "code": "DERM-001",
  "external_code": "CONS-DER",
  "name": "Consulta Dermatológica",
  "description": null,
  "is_active": true
}
```

> **UX:** En la pantalla de administración de servicios médicos, el campo `external_code` puede mostrarse como un input de texto con placeholder _"Código MedSys (opcional)"_. La pantalla de mapeo formal está en la sección 4.

---

## 2. Búsqueda de pacientes en MedSys {#busqueda-pacientes}

Permite buscar un paciente en la base de datos de MedSys con un **único campo de búsqueda**. El backend detecta automáticamente el tipo:

- **Solo dígitos** → búsqueda exacta por número de documento
- **Letras / mixto** → búsqueda parcial por nombre o apellido

### `GET /medsys/patients`

| Param | Tipo | Descripción |
|---|---|---|
| `search` | string (mín. 3 chars) | Documento (numérico) o nombre/apellido |

---

#### Cuando el término es numérico (búsqueda por documento)

```http
GET /api/v1/medsys/patients?search=1234567890
Authorization: Bearer {token}
```

**Respuesta 200 — paciente encontrado:**

Devuelve el paciente junto con un resumen de sus citas: las **3 más recientes** (cualquier estado) más la **próxima cita futura activa** si no está ya entre las 3. La lista viene ordenada de más reciente a más antigua.

```json
{
  "success": true,
  "message": "Paciente encontrado en MedSys",
  "data": {
    "patient": {
      "codigo": "P00123",
      "tipodoc": "CC",
      "documento": "1234567890",
      "nombre": "María Alejandra García López"
    },
    "appointments": [
      {
        "codcontrol": "C00789",
        "fecha": "2027-03-10",
        "hora": "10:00:00",
        "codtipocontrol": "CONS-DER",
        "servicio": "Consulta Dermatológica",
        "estado": "Cita Agendada",
        "medical_service_id": 5,
        "medical_service_name": "Consulta Dermatológica",
        "is_mapped": true
      },
      {
        "codcontrol": "C00456",
        "fecha": "2026-08-19",
        "hora": "09:30:00",
        "codtipocontrol": "CONS-DER",
        "servicio": "Consulta Dermatológica",
        "estado": "Cita Atendida",
        "medical_service_id": 5,
        "medical_service_name": "Consulta Dermatológica",
        "is_mapped": true
      },
      {
        "codcontrol": "C00321",
        "fecha": "2026-05-14",
        "hora": "14:00:00",
        "codtipocontrol": "CONS-DER",
        "servicio": "Consulta Dermatológica",
        "estado": "Cita Atendida",
        "medical_service_id": 5,
        "medical_service_name": "Consulta Dermatológica",
        "is_mapped": true
      }
    ]
  }
}
```

> La cita futura activa puede ya estar entre las 3 más recientes — en ese caso no se duplica.

**Respuesta 404 — no encontrado:**

```json
{
  "success": false,
  "message": "Paciente no encontrado en MedSys"
}
```

---

#### Cuando el término tiene letras (búsqueda por nombre)

```http
GET /api/v1/medsys/patients?search=García
Authorization: Bearer {token}
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Resultados de búsqueda en MedSys",
  "data": {
    "patients": [
      {
        "codigo": "P00123",
        "tipodoc": "CC",
        "documento": "1234567890",
        "nombre": "María Alejandra García López"
      },
      {
        "codigo": "P00456",
        "tipodoc": "CC",
        "documento": "9876543210",
        "nombre": "Carlos García Ruiz"
      }
    ]
  }
}
```

> La búsqueda por nombre devuelve **máximo 20 resultados** y no incluye citas. Para ver las citas, el usuario debe seleccionar un paciente de la lista y usar el endpoint de citas (sección 3).

---

### Flujo de UI sugerido

```
┌─────────────────────────────────────────────┐
│  Buscar paciente                            │
│  ┌─────────────────────────────────────┐   │
│  │  García  (o  1234567890)        🔍  │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  Resultados (2):                            │
│  • María García López — CC 1234567890  [>] │
│  • Carlos García Ruiz — CC 9876543210  [>] │
└─────────────────────────────────────────────┘
          ↓ al seleccionar (o buscar por documento)
┌─────────────────────────────────────────────┐
│  Paciente: María García López               │
│  Historial de citas:                        │
│  • 2027-03-10  Consulta Derm.  Agendada ▶  │  ← próxima
│  • 2026-08-19  Consulta Derm.  Atendida ✓  │  ← última
│  • 2026-05-14  Consulta Derm.  Atendida ✓  │
└─────────────────────────────────────────────┘
```

---

## 3. Citas activas de un paciente {#citas-paciente}

### `GET /medsys/patients/{codigo}/appointments`

| Param | Lugar | Tipo | Descripción |
|---|---|---|---|
| `codigo` | URL | string | Código del paciente en MedSys (`pacientes.codigo`) |
| `date` | Query (opcional) | `Y-m-d` | Filtrar por fecha. Si se omite, devuelve **todas** las citas activas |

```http
GET /api/v1/medsys/patients/P00123/appointments?date=2026-08-19
Authorization: Bearer {token}
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Citas activas del paciente en MedSys",
  "data": [
    {
      "codcontrol": "C00456",
      "fecha": "2026-08-19",
      "hora": "09:30:00",
      "codtipocontrol": "CONS-DER",
      "servicio": "Consulta Dermatológica",
      "estado": "Cita Agendada",
      "medical_service_id": 5,
      "medical_service_name": "Consulta Dermatológica",
      "is_mapped": true
    }
  ]
}
```

### Estados de cita activos

Solo se devuelven citas con estado activo:

| Código | Descripción |
|---|---|
| `PEN` | Cita Agendada |
| `CNF` | Cita Confirmada |
| `CON` | En Consultorio |

### Campo `is_mapped`

| Valor | Significado | Acción sugerida en UI |
|---|---|---|
| `true` | `codtipocontrol` tiene `external_code` vinculado en SGA | Mostrar normal |
| `false` | Sin mapeo — el servicio no está vinculado | Mostrar advertencia + enlace a pantalla de mapeo |

---

## 4. Tipos de procedimiento y mapeo {#procedure-types}

El mapeo entre tipos de procedimiento de MedSys y servicios SGA es **automático**: el scheduler corre `medsys:sync-procedure-types` cada lunes a las 4 AM. El comando detecta coincidencias por nombre y asigna `external_code` sin intervención humana.

La pantalla de administración existe solo para **correcciones manuales** en caso de error o cuando el nombre en MedSys difiere demasiado del nombre en SGA.

### `GET /medsys/procedure-types`

```http
GET /api/v1/medsys/procedure-types
Authorization: Bearer {token}
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Tipos de procedimiento MedSys",
  "data": [
    {
      "codigo": "CONS-DER",
      "descripcion": "Consulta Dermatológica",
      "medical_service_id": 5,
      "is_mapped": true
    },
    {
      "codigo": "APLIC-BOT",
      "descripcion": "Aplicación de Botox",
      "medical_service_id": null,
      "is_mapped": false
    }
  ]
}
```

### Guardar un mapeo

Para vincular un `tiposproc` con un servicio de SGA, actualizar el `external_code` del servicio:

```http
PUT /api/v1/medical-services/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "DERM-001",
  "name": "Consulta Dermatológica",
  "type": "procedure",
  "parent_id": 2,
  "external_code": "CONS-DER",
  "is_active": true
}
```

### UI sugerida — Pantalla de mapeo

```
┌─────────────────────────────────────────────────────────────────┐
│  Mapeo MedSys ↔ Servicios SGA                                  │
├─────────────────────┬───────────────────────────┬──────────────┤
│  Código MedSys      │  Descripción              │  Servicio SGA│
├─────────────────────┼───────────────────────────┼──────────────┤
│  CONS-DER           │  Consulta Dermatológica   │  [✓ DERM-001]│
│  APLIC-BOT          │  Aplicación de Botox      │  [Seleccionar│
│  PEELING            │  Peeling Químico          │  [Seleccionar│
└─────────────────────┴───────────────────────────┴──────────────┘
```

El selector de "Servicio SGA" puede usar el endpoint `GET /medical-services?type=procedure` para listar opciones.

---

## 5. Proyección de consumo {#proyeccion-consumo}

Proyección de insumos necesarios basada en citas futuras + promedio histórico de consumo por servicio.

**Fórmula:** `unidades_proyectadas = citas_futuras × promedio_histórico_por_cita`

### `GET /medsys/consumption-projection`

| Query param | Tipo | Default | Descripción |
|---|---|---|---|
| `days` | integer | `30` | Horizonte de proyección (días hacia adelante) |
| `historical_days` | integer | `90` | Período para calcular el promedio histórico |

```http
GET /api/v1/medsys/consumption-projection?days=30&historical_days=90
Authorization: Bearer {token}
```

**Respuesta 200:**

```json
{
  "success": true,
  "message": "Proyección de consumo basada en citas futuras",
  "data": [
    {
      "service_id": 5,
      "service_name": "Consulta Dermatológica",
      "external_code": "CONS-DER",
      "future_appointments": 12,
      "avg_units_per_appointment": 2.50,
      "projected_units": 30
    },
    {
      "service_id": 7,
      "service_name": "Aplicación de Botox",
      "external_code": "APLIC-BOT",
      "future_appointments": 5,
      "avg_units_per_appointment": 1.00,
      "projected_units": 5
    }
  ]
}
```

| Campo | Descripción |
|---|---|
| `future_appointments` | Citas con estado PEN o CNF en el período |
| `avg_units_per_appointment` | Promedio de unidades consumidas por cita en el período histórico |
| `projected_units` | `ceil(future_appointments × avg_units_per_appointment)` |

> **Nota:** Solo aparecen servicios que tienen `external_code` configurado y citas futuras en MedSys. Si un servicio no tiene historial de consumo, `avg_units_per_appointment` = 0 y `projected_units` = 0.

---

## 6. Sincronización de evolución clínica {#evolucion-clinica}

**Esta funcionalidad es completamente automática — no requiere ninguna llamada adicional del frontend.**

Cuando el profesional registra o actualiza una evolución clínica desde la pantalla de atención de pacientes, el backend dispara en segundo plano un job que copia la evolución a MedSys.

### Flujo

```
Frontend                  SGA Backend               MedSys BD
    │                         │                         │
    │  POST /patient-clinical-│                         │
    │  evolutions             │                         │
    │────────────────────────>│                         │
    │                         │  Guarda evolución       │
    │  HTTP 201               │  en BD SGA              │
    │<────────────────────────│                         │
    │                         │  ←async job→            │
    │                         │  Envía evolución ──────>│
    │                         │  (con párrafo de        │
    │                         │   inyectables si aplica)│
```

### Qué se sincroniza

- El **texto completo** de la evolución clínica.
- Si en la atención se suministraron **medicamentos inyectables**, se agrega automáticamente un párrafo al final:

```
Medicamentos inyectables aplicados:
- Toxina Botulínica | Lote: L2026-001 | Vence: 2027-06-30 | Cant: 2
```

> Los inyectables se identifican por `pharmaceutical_form` que contenga "inyect" y clasificación `MED`.

### Variables de entorno (para el equipo de infraestructura)

```env
MEDSYS_EVOLUTION_ENABLED=true        # false en desarrollo/staging
MEDSYS_EVOLUTION_TABLE=sga_evoluciones
```

---

## 7. Notas UX y comportamientos esperados {#notas-ux}

### Citas sin mapeo (`is_mapped: false`)

Cuando una cita tiene un `codtipocontrol` que no está vinculado a ningún servicio SGA:

- Mostrar el nombre del procedimiento MedSys tal como viene (`servicio`).
- Mostrar un **indicador de advertencia** (ícono, badge, color ámbar).
- Incluir un enlace o botón que lleve a la pantalla de mapeo (sección 4).

Ejemplo de mensaje: _"Procedimiento 'APLIC-BOT' sin servicio SGA asignado. [Configurar mapeo]"_

### Búsqueda por nombre — rendimiento

- Requiere mínimo **3 caracteres** para ejecutar la búsqueda.
- El resultado está limitado a **20 pacientes**.
- Mostrar un indicador de carga mientras se consulta MedSys (la latencia puede variar).

### Flujo recomendado en pantalla de atención de pacientes

1. El operador busca al paciente por documento o nombre.
2. Si busca por nombre, selecciona el paciente de la lista resultante (la búsqueda por documento resuelve directamente).
3. El sistema muestra las **3 citas más recientes + la próxima futura activa**. El operador identifica la cita vigente.
4. El operador selecciona la cita y procede al registro de la atención.
5. El `codcontrol` de la cita seleccionada se usa como referencia en el registro de atención.

### Registros de procedimiento y citas

El campo `codcontrol` de la cita en MedSys corresponde al campo `appointment_code` en `patient_procedure_records` de SGA. El frontend debe enviar este dato al crear un registro de procedimiento:

```json
{
  "patient_document": "1234567890",
  "medical_service_id": 5,
  "patient_external_id": "P00123",
  "appointment_code": "C00456",
  ...
}
```

---

## 8. Formato estándar de respuesta {#formato-respuesta}

Todos los endpoints siguen la misma estructura:

```json
{
  "success": true | false,
  "message": "Descripción legible",
  "data": { } | [ ]
}
```

En caso de error de validación (422):

```json
{
  "success": false,
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "document": ["El campo document es requerido cuando name no está presente."]
  }
}
```

---

## 9. Códigos de error {#codigos-error}

| Código | Causa más común |
|---|---|
| `401` | Token ausente o expirado |
| `403` | Usuario sin permiso `integraciones.ver` |
| `404` | Paciente no encontrado en MedSys |
| `422` | Parámetros inválidos (ej: `name` menor a 3 caracteres) |
| `500` | Error de conexión con la base de datos de MedSys |

> Si MedSys no está disponible, los endpoints devuelven 500 con mensaje descriptivo. El resto de SGA sigue funcionando normalmente.
