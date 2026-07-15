# Guía Frontend — Evolución Clínica de Pacientes

**Versión:** 2.0 · **Fecha:** 2026-07-15  
**Módulo:** Centro de Costo → Evolución Clínica

---

## 1. Contexto y objetivo

Esta funcionalidad permite registrar la **evolución clínica** del paciente en cada procedimiento atendido, con dos características clave:

- **Plantillas predeterminadas por servicio/procedimiento** que pre-llenan el editor de texto enriquecido y agilizan el trabajo de la enfermera.
- **Visualización automática de medicamentos aplicados**, extraídos directamente del movimiento de inventario vinculado — eliminando el error humano de registro manual.

### Entidades involucradas y su relación

```
MedicalService (servicios/procedimientos)
  └── ClinicalTemplate         ← una plantilla opcional por servicio o procedimiento
                                  (aplica también a sus hijos si el hijo no tiene la propia)

MovementDocument (salida de inventario)
  └── StockMovement[]          ← líneas de productos despachados

PatientProcedureRecord (registro de facturación del procedimiento al paciente)
  ├── movement_document_id     ← FK opcional al documento de salida de inventario
  ├── PatientClinicalEvolution ← 1..N evoluciones clínicas (texto enriquecido)
  └── medicamentos (lectura)   ← extraídos automáticamente de stock_movements donde
                                  classification = 'MED', vía movement_document_id
```

> **Punto clave:** los medicamentos **no se registran manualmente**. El sistema los lee del movimiento de inventario que generó la salida del stock. Si el procedimiento no tuvo movimiento (servicio puro), el endpoint de medicamentos devuelve lista vacía.

---

## 2. Flujo recomendado para la enfermera

```
1. La enfermera abre el detalle de un PatientProcedureRecord
       GET /api/v1/patient-procedure-records/{id}

2. El frontend consulta si existe una plantilla para ese procedimiento
       GET /api/v1/clinical-templates/for-service/{medical_service_id}

3a. Si EXISTE plantilla  → pre-llenar el editor rich text con template.content
3b. Si NO existe         → abrir el editor en blanco

4. La enfermera escribe/edita la evolución y guarda
       POST /api/v1/patient-procedure-records/{id}/evolutions

5. El frontend muestra los medicamentos aplicados (lectura automática del inventario)
       GET /api/v1/patient-procedure-records/{id}/medications
       → Muestra producto, lote, fecha de vencimiento y cantidad de cada MED despachado
       → Si movement_document_id es null, la lista llega vacía (procedimiento sin salida de stock)

6. La enfermera puede ver evoluciones previas del mismo registro
       GET /api/v1/patient-procedure-records/{id}/evolutions
```

### Cuándo se vincula el `movement_document_id`

El campo `movement_document_id` en `PatientProcedureRecord` se asigna al **crear o actualizar el registro de procedimiento**, no al registrar la evolución. Normalmente lo asigna el operador de almacén al procesar la salida de inventario hacia el paciente. La enfermera solo consulta el resultado.

---

## 3. Módulo: Plantillas de Evolución Clínica

Una plantilla es texto enriquecido (HTML) predefinido para un servicio o procedimiento. El administrador o jefe las crea desde un panel de configuración; la enfermera las consume de forma transparente.

### 3.1 Consultar la plantilla aplicable a un procedimiento

Este es el endpoint clave del flujo. Recibe el `medical_service_id` del procedimiento y devuelve la plantilla más cercana: primero busca en el propio procedimiento, y si no existe, sube al servicio padre.

```
GET /api/v1/clinical-templates/for-service/{medicalServiceId}
Authorization: Bearer {token}
Permiso requerido: plantillas_clinicas.ver
```

**Ejemplo:**
```
GET /api/v1/clinical-templates/for-service/5
```

**Response con plantilla — `200 OK`:**
```json
{
  "success": true,
  "message": "Plantilla aplicable",
  "data": {
    "id":                   3,
    "medical_service_id":   5,
    "medical_service_name": "Curaciones Simples",
    "title":                "Evolución de Curación",
    "content":              "<p>El paciente ingresa en <strong>buen estado general</strong>.</p><ul><li>Se realiza curación con técnica aséptica.</li><li>Herida en proceso de cicatrización.</li></ul><p>Se indica continuar manejo ambulatorio.</p>",
    "is_active":            true
  }
}
```

**Response sin plantilla — `200 OK`:**
```json
{
  "success": true,
  "message": "Sin plantilla para este servicio/procedimiento",
  "data": null
}
```

> Cuando `data` es `null`, abrir el editor en blanco. **No es un error:** simplemente no hay plantilla configurada para ese procedimiento ni para su servicio padre.

---

### 3.2 Listar todas las plantillas

```
GET /api/v1/clinical-templates
Authorization: Bearer {token}
Permiso requerido: plantillas_clinicas.ver
```

**Query params disponibles:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `medical_service_id` | integer | Filtrar por servicio o procedimiento exacto |
| `is_active` | boolean | Filtrar por estado (`true` / `false`) |

**Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Plantillas de evolución clínica",
  "data": [
    {
      "id":                   3,
      "medical_service_id":   5,
      "medical_service_name": "Curaciones Simples",
      "title":                "Evolución de Curación",
      "content":              "<p>El paciente ingresa...</p>",
      "is_active":            true
    }
  ]
}
```

---

### 3.3 Detalle de una plantilla

```
GET /api/v1/clinical-templates/{id}
Authorization: Bearer {token}
Permiso requerido: plantillas_clinicas.ver
```

**Response — `200 OK`:** misma estructura que el ítem del listado.

---

### 3.4 Crear una plantilla

Solo puede existir **una plantilla activa por servicio/procedimiento**. Si ya existe una para ese `medical_service_id`, el backend retorna `409`.

```
POST /api/v1/clinical-templates
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: plantillas_clinicas.crear
```

**Body:**
```json
{
  "medical_service_id": 5,
  "title":              "Evolución de Curación",
  "content":            "<p>El paciente ingresa en <strong>buen estado general</strong>.</p>"
}
```

**Campos:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `medical_service_id` | integer | Sí | ID del servicio o procedimiento (`medical_services.id`). Puede ser padre o hijo. |
| `title` | string | Sí | Título descriptivo · max 200 caracteres |
| `content` | string | Sí | HTML del editor rich text · sin límite práctico de tamaño |

**Response — `201 Created`:**
```json
{
  "success": true,
  "message": "Plantilla creada exitosamente",
  "data": {
    "id":                   3,
    "medical_service_id":   5,
    "medical_service_name": "Curaciones Simples",
    "title":                "Evolución de Curación",
    "content":              "<p>El paciente ingresa...</p>",
    "is_active":            true
  }
}
```

---

### 3.5 Actualizar una plantilla

```
PUT /api/v1/clinical-templates/{id}
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: plantillas_clinicas.editar
```

**Body:**
```json
{
  "title":     "Evolución de Curación (revisada)",
  "content":   "<p>Nuevo contenido de la plantilla...</p>",
  "is_active": true
}
```

**Campos:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `title` | string | Sí | Nuevo título · max 200 caracteres |
| `content` | string | Sí | Nuevo HTML |
| `is_active` | boolean | No | `true` (default) para activar, `false` para desactivar sin eliminar |

> El `medical_service_id` **no se puede cambiar** en una actualización. Para cambiar el servicio asociado, eliminar la plantilla y crear una nueva.

**Response — `200 OK`:** misma estructura que en la creación.

---

### 3.6 Eliminar una plantilla (soft delete)

```
DELETE /api/v1/clinical-templates/{id}
Authorization: Bearer {token}
Permiso requerido: plantillas_clinicas.eliminar
```

**Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Plantilla eliminada"
}
```

---

## 4. Módulo: Evoluciones Clínicas

Cada vez que la enfermera registra la evolución de un paciente en un procedimiento, se crea un nuevo registro. Se permiten **múltiples evoluciones** por registro de procedimiento (por ejemplo, si hay varias anotaciones en el turno).

### 4.1 Listar las evoluciones de un registro

```
GET /api/v1/patient-procedure-records/{patientProcedureRecordId}/evolutions
Authorization: Bearer {token}
Permiso requerido: evoluciones_clinicas.ver
```

**Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Evoluciones clínicas del registro",
  "data": [
    {
      "id":                          10,
      "patient_procedure_record_id": 42,
      "content":                     "<p>Paciente ingresa consciente y orientado...</p>",
      "user_id":                     3,
      "user_name":                   "Ana Gómez",
      "recorded_at":                 "2026-07-15 09:30:00"
    }
  ]
}
```

> Las evoluciones vienen ordenadas de **más reciente a más antigua** (`recorded_at` DESC).

---

### 4.2 Crear una evolución

```
POST /api/v1/patient-procedure-records/{patientProcedureRecordId}/evolutions
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: evoluciones_clinicas.crear
```

**Body:**
```json
{
  "content":     "<p>Paciente ingresa consciente y orientado...</p>",
  "recorded_at": "2026-07-15 09:30:00"
}
```

**Campos:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `content` | string | Sí | HTML del editor rich text. Puede contener el texto de la plantilla editado por la enfermera. |
| `recorded_at` | datetime `Y-m-d H:i:s` | No | Fecha y hora del registro. Si no se envía, el backend usa el momento exacto de la llamada. |

> El `user_id` **no se envía en el body**: el backend lo extrae automáticamente del token de autenticación.

**Response — `201 Created`:**
```json
{
  "success": true,
  "message": "Evolución clínica registrada",
  "data": {
    "id":                          10,
    "patient_procedure_record_id": 42,
    "content":                     "<p>Paciente ingresa consciente y orientado...</p>",
    "user_id":                     3,
    "user_name":                   "Ana Gómez",
    "recorded_at":                 "2026-07-15 09:30:00"
  }
}
```

---

### 4.3 Actualizar una evolución

Solo se puede actualizar el `content`. La fecha de registro y el usuario quedan fijos desde la creación.

```
PUT /api/v1/patient-procedure-records/{patientProcedureRecordId}/evolutions/{evolutionId}
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: evoluciones_clinicas.editar
```

**Body:**
```json
{
  "content": "<p>Paciente ingresa consciente y orientado. <em>Corrección: se añade valoración de dolor.</em></p>"
}
```

**Response — `200 OK`:** misma estructura que en la creación.

---

### 4.4 Eliminar una evolución (soft delete)

```
DELETE /api/v1/patient-procedure-records/{patientProcedureRecordId}/evolutions/{evolutionId}
Authorization: Bearer {token}
Permiso requerido: evoluciones_clinicas.eliminar
```

**Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Evolución clínica eliminada"
}
```

---

## 5. Módulo: Medicamentos Aplicados (solo lectura)

Los medicamentos utilizados en el procedimiento se obtienen **automáticamente del movimiento de inventario** vinculado al registro de procedimiento. El sistema no acepta registro manual de medicamentos — la fuente de verdad es el stock despachado, eliminando el error humano.

### Cómo funciona el vínculo

```
PatientProcedureRecord.movement_document_id  →  MovementDocument
                                                     └── StockMovement[] (líneas de productos)
                                                              └── filter: classification.code = 'MED'
```

- Cuando el operador de almacén procesa una salida de inventario para un paciente, el `movement_document_id` queda registrado en el `PatientProcedureRecord` (lo asigna quien crea/actualiza el registro).
- Si el procedimiento **no tuvo salida de inventario** (ej. consulta médica pura), el campo es `null` y el endpoint devuelve lista vacía — esto es normal, no es un error.

### 5.1 Consultar los medicamentos aplicados

```
GET /api/v1/patient-procedure-records/{patientProcedureRecordId}/medications
Authorization: Bearer {token}
Permiso requerido: medicamentos_procedimiento.ver
```

**Response — `200 OK` con medicamentos:**
```json
{
  "success": true,
  "message": "Medicamentos aplicados en el procedimiento",
  "data": [
    {
      "generic_product_id": 12,
      "product_name":       "Amoxicilina 500mg",
      "batch_id":           88,
      "lot_number":         "LOT-2025-001",
      "expiration_date":    "2027-06-30",
      "quantity":           2.0
    },
    {
      "generic_product_id": 15,
      "product_name":       "Suero Fisiológico 500ml",
      "batch_id":           91,
      "lot_number":         "SF-2026-003",
      "expiration_date":    "2028-01-15",
      "quantity":           1.0
    }
  ]
}
```

**Response — `200 OK` sin salida de inventario:**
```json
{
  "success": true,
  "message": "Medicamentos aplicados en el procedimiento",
  "data": []
}
```

> `data: []` significa que el procedimiento no tuvo salida de inventario asociada, **o** que todos los productos despachados son de clasificación diferente a `MED` (ej. solo dispositivos médicos). No mostrar mensaje de error en la UI — mostrar "Sin medicamentos registrados para este procedimiento".

### 5.2 Campos de la respuesta

| Campo | Tipo | Descripción |
|---|---|---|
| `generic_product_id` | integer | ID del producto genérico en el catálogo |
| `product_name` | string | Nombre del medicamento |
| `batch_id` | integer | ID del lote despachado |
| `lot_number` | string | Número de lote para trazabilidad |
| `expiration_date` | string `Y-m-d` | Fecha de vencimiento del lote |
| `quantity` | decimal | Cantidad despachada (siempre positiva) |

> **Solo lectura:** No existen endpoints `POST`, `PUT` ni `DELETE` para medicamentos. El sistema no acepta modificación manual de esta información.

---

## 6. Vincular un movimiento de inventario al registro de procedimiento

El `movement_document_id` se asigna al **crear o actualizar** un `PatientProcedureRecord`. Esto lo realiza el operador de almacén en el momento de procesar la salida.

### Al crear el registro de procedimiento

```
POST /api/v1/patient-procedure-records
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: registros_procedimientos.crear
```

**Body (fragmento relevante):**
```json
{
  "patient_external_id":  "PAC-001",
  "medical_service_id":   5,
  "cost_center_id":       2,
  "movement_document_id": 34
}
```

### Al actualizar el registro (agregar el movimiento después)

```
PUT /api/v1/patient-procedure-records/{id}
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: registros_procedimientos.editar
```

**Body (fragmento relevante):**
```json
{
  "movement_document_id": 34
}
```

> El campo `movement_document_id` es **opcional y nullable**. Si se omite al crear, queda `null`. Se puede asignar después con una actualización parcial.

---

## 7. Errores comunes

### `422 Unprocessable Entity` — validación de campos

```json
{
  "success": false,
  "message": "El contenido de la evolución es obligatorio.",
  "errors": {
    "content": ["El contenido de la evolución es obligatorio."]
  }
}
```

### `409 Conflict` — regla de negocio

| Situación | Mensaje |
|---|---|
| Ya existe plantilla para el servicio (POST plantilla) | `"Ya existe una plantilla para 'Curaciones Simples'. Edítela en lugar de crear una nueva."` |
| Servicio no encontrado al crear plantilla | `"Servicio/procedimiento con id X no encontrado."` |
| Plantilla no encontrada en PUT/DELETE | `"Plantilla con id X no encontrada."` |
| Evolución no encontrada en PUT/DELETE | `"Evolución clínica con id X no encontrada."` |
| Registro de procedimiento no existe | `"Registro de procedimiento con id X no encontrado."` |

### `403 Forbidden` — sin permiso

El token autenticado no tiene el permiso necesario para la acción. Verificar que el rol del usuario incluya el permiso requerido.

---

## 8. Matriz de permisos por rol

| Permiso | `super_administrador` | `administrador` | `jefe_almacen` | `operador_almacen` | `personal_medico` |
|---|:-:|:-:|:-:|:-:|:-:|
| `plantillas_clinicas.ver` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `plantillas_clinicas.crear` | ✅ | ✅ | ✅ | — | — |
| `plantillas_clinicas.editar` | ✅ | ✅ | ✅ | — | — |
| `plantillas_clinicas.eliminar` | ✅ | ✅ | — | — | — |
| `evoluciones_clinicas.ver` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `evoluciones_clinicas.crear` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `evoluciones_clinicas.editar` | ✅ | ✅ | ✅ | — | — |
| `evoluciones_clinicas.eliminar` | ✅ | ✅ | — | — | — |
| `medicamentos_procedimiento.ver` | ✅ | ✅ | ✅ | ✅ | ✅ |

> `medicamentos_procedimiento` solo tiene el permiso `.ver` — no existen `.crear`, `.editar` ni `.eliminar` porque los medicamentos son de solo lectura.

---

## 9. Referencia rápida de endpoints

### Plantillas de Evolución Clínica

| Acción | Método | URL | Permiso |
|---|---|---|---|
| Plantilla aplicable a un procedimiento | `GET` | `/api/v1/clinical-templates/for-service/{medicalServiceId}` | `plantillas_clinicas.ver` |
| Listar todas las plantillas | `GET` | `/api/v1/clinical-templates` | `plantillas_clinicas.ver` |
| Detalle de una plantilla | `GET` | `/api/v1/clinical-templates/{id}` | `plantillas_clinicas.ver` |
| Crear plantilla | `POST` | `/api/v1/clinical-templates` | `plantillas_clinicas.crear` |
| Actualizar plantilla | `PUT` | `/api/v1/clinical-templates/{id}` | `plantillas_clinicas.editar` |
| Eliminar plantilla | `DELETE` | `/api/v1/clinical-templates/{id}` | `plantillas_clinicas.eliminar` |

### Evoluciones Clínicas

| Acción | Método | URL | Permiso |
|---|---|---|---|
| Listar evoluciones del registro | `GET` | `/api/v1/patient-procedure-records/{id}/evolutions` | `evoluciones_clinicas.ver` |
| Crear evolución | `POST` | `/api/v1/patient-procedure-records/{id}/evolutions` | `evoluciones_clinicas.crear` |
| Actualizar evolución | `PUT` | `/api/v1/patient-procedure-records/{id}/evolutions/{evId}` | `evoluciones_clinicas.editar` |
| Eliminar evolución | `DELETE` | `/api/v1/patient-procedure-records/{id}/evolutions/{evId}` | `evoluciones_clinicas.eliminar` |

### Medicamentos Aplicados (solo lectura)

| Acción | Método | URL | Permiso |
|---|---|---|---|
| Consultar medicamentos del registro | `GET` | `/api/v1/patient-procedure-records/{id}/medications` | `medicamentos_procedimiento.ver` |

---

## 10. Consideraciones de UX

- **Editor rich text:** El campo `content` (tanto en plantillas como en evoluciones) contiene HTML. Usar un editor como Quill, TipTap o TinyMCE. Guardar el HTML que el editor produce; el backend lo almacena tal cual sin sanitizar.
- **Pre-llenado de la plantilla:** Al abrir el formulario de nueva evolución, llamar primero a `GET /clinical-templates/for-service/{medical_service_id}`. Si viene `data != null`, cargar `data.content` en el editor. El usuario puede editarlo libremente antes de guardar.
- **Múltiples evoluciones:** Mostrar las evoluciones existentes en orden cronológico inverso (el backend ya las devuelve así). Cada una muestra el `user_name` y `recorded_at`.
- **Medicamentos — panel informativo:** Mostrar los medicamentos como una tarjeta o tabla de solo lectura en el detalle del registro de procedimiento. Incluir: nombre, lote, fecha de vencimiento y cantidad. Resaltar visualmente si la fecha de vencimiento ya pasó (advertencia informativa, no bloqueante).
- **Lista vacía de medicamentos:** Cuando `data: []`, mostrar "Sin medicamentos despachados para este procedimiento" — no es un error ni requiere acción de la enfermera.
- **Soft delete:** Los registros eliminados no aparecen en los listados pero permanecen en la base de datos. No implementar "papelera" en el frontend — la eliminación es definitiva para el usuario.
