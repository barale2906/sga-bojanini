# Integración de Evoluciones Clínicas

> **Módulo:** Centro de Costo  
> **Base URL:** `/api/v1`  
> **Auth:** Sanctum Bearer Token  
> **Fecha:** 2026-07-15

---

## Contexto — cambio de enfoque

El campo `notes` de `patient_procedure_records` **ya no debe usarse** para guardar evoluciones médicas. El sistema ahora tiene dos tablas dedicadas:

| Concepto | Tabla | Para qué sirve |
|---|---|---|
| Plantilla clínica | `clinical_templates` | Formulario predefinido por servicio médico. El frontend la carga para pre-rellenar el editor. |
| Evolución clínica | `patient_clinical_evolutions` | Cada anotación que el profesional registra. Puede haber múltiples por procedimiento. |

---

## Flujo completo de la pantalla

1. **Obtener el `patient_procedure_record_id`** del registro que se está atendiendo. Es el eje de todos los endpoints.
2. **Cargar la plantilla del servicio** (`GET /clinical-templates/for-service/{medicalServiceId}`). Si `data` es `null`, mostrar el editor vacío.
3. **Listar evoluciones previas** del registro (`GET /patient-procedure-records/{id}/evolutions`) y mostrarlas en orden cronológico.
4. **El profesional edita y guarda** → `POST /patient-procedure-records/{id}/evolutions` con el `content` resultante.
5. **Actualizar la lista** en pantalla con la respuesta 201 sin redirigir — el profesional puede registrar más de una evolución por sesión.

---

## Endpoints — Plantillas clínicas

### Obtener plantilla por servicio médico

```
GET /clinical-templates/for-service/{medicalServiceId}
Permiso: plantillas_clinicas.ver
```

**Respuesta con plantilla:**
```json
{
  "status": "success",
  "message": "Plantilla aplicable",
  "data": {
    "id": 3,
    "medical_service_id": 7,
    "medical_service_name": "Consulta General",
    "title": "Plantilla consulta general",
    "content": "Motivo de consulta:\n\nExamen físico:\n\nDiagnóstico:\n\nTratamiento:",
    "is_active": true
  }
}
```

**Respuesta sin plantilla:**
```json
{
  "status": "success",
  "message": "Sin plantilla para este servicio/procedimiento",
  "data": null
}
```

> El campo `content` puede ser texto libre o JSON serializado según cómo el administrador haya configurado la plantilla.

### Listar todas las plantillas (módulo de administración)

```
GET /clinical-templates
GET /clinical-templates?medical_service_id=7&is_active=1
Permiso: plantillas_clinicas.ver
```

---

## Endpoints — Evoluciones clínicas

### Listar evoluciones de un registro

```
GET /patient-procedure-records/{patientProcedureRecordId}/evolutions
Permiso: evoluciones_clinicas.ver
```

**Respuesta:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 12,
      "patient_procedure_record_id": 45,
      "content": "Paciente estable. Evoluciona favorablemente...",
      "user_id": 8,
      "user_name": "Dr. Pérez",
      "recorded_at": "2026-07-15 09:30:00"
    }
  ]
}
```

---

### Registrar nueva evolución

```
POST /patient-procedure-records/{patientProcedureRecordId}/evolutions
Permiso: evoluciones_clinicas.crear
```

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `content` | string | ✅ Sí | Contenido de la evolución. |
| `recorded_at` | string | No | Formato `Y-m-d H:i:s`. Si se omite, el backend usa el momento actual. |

```json
{
  "content": "Motivo de consulta: Dolor abdominal leve.\n\nExamen físico: Normal.\n\nDiagnóstico: Gastritis.\n\nTratamiento: Omeprazol 20mg.",
  "recorded_at": "2026-07-15 10:30:00"
}
```

> **Importante:** El `user_id` se extrae automáticamente del token. No debe enviarse en el body.

**Respuesta exitosa (201):**
```json
{
  "status": "success",
  "message": "Evolución clínica registrada",
  "data": {
    "id": 13,
    "patient_procedure_record_id": 45,
    "content": "Motivo de consulta: Dolor abdominal leve...",
    "user_id": 8,
    "user_name": "Dr. Pérez",
    "recorded_at": "2026-07-15 10:30:00"
  }
}
```

---

### Editar una evolución

```
PUT /patient-procedure-records/{patientProcedureRecordId}/evolutions/{evolutionId}
Permiso: evoluciones_clinicas.editar
```

Solo permite modificar `content`. La fecha y el autor no son editables.

**Body:**
```json
{
  "content": "Contenido corregido de la evolución..."
}
```

**Respuesta (200):** objeto `PatientClinicalEvolution` actualizado.

---

### Eliminar una evolución

```
DELETE /patient-procedure-records/{patientProcedureRecordId}/evolutions/{evolutionId}
Permiso: evoluciones_clinicas.eliminar
```

Soft delete. Responde **204 No Content** sin body.

---

## Tabla de permisos

Verificar que el usuario tenga el permiso antes de mostrar o habilitar cada acción en la UI.

| Permiso | Acción |
|---|---|
| `plantillas_clinicas.ver` | Consultar plantillas |
| `evoluciones_clinicas.ver` | Listar evoluciones del registro |
| `evoluciones_clinicas.crear` | Registrar nueva evolución |
| `evoluciones_clinicas.editar` | Modificar el contenido de una evolución |
| `evoluciones_clinicas.eliminar` | Eliminar una evolución |
