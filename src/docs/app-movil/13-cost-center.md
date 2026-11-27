# 13 · Centros de costo, servicios médicos y registros clínicos

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **37**

## `GET /api/v1/cost-centers`

Listar centros de costo.

**Permiso requerido:** `centros_costo.ver`

**Query params detectados:** `type`, `is_active`, `search`

**Campos de respuesta (`CostCenterResource`):** `id`, `code`, `name`, `type`, `type_label`, `is_external`, `description`, `is_active`

---

## `GET /api/v1/cost-centers/{id}`

Obtener un centro de costo.

**Permiso requerido:** `centros_costo.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`CostCenterResource`):** `id`, `code`, `name`, `type`, `type_label`, `is_external`, `description`, `is_active`

---

## `POST /api/v1/cost-centers`

Crear un centro de costo.

**Permiso requerido:** `centros_costo.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `code` | sí | `string`, `max:20`, `unique:cost_centers,code` |
| `name` | sí | `string`, `max:100` |
| `type` | sí | `in:internal,external` |
| `description` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`CostCenterResource`):** `id`, `code`, `name`, `type`, `type_label`, `is_external`, `description`, `is_active`

---

## `PUT /api/v1/cost-centers/{id}`

Actualizar un centro de costo.

**Permiso requerido:** `centros_costo.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `code` | sí | `string`, `max:20`, `unique` |
| `name` | sí | `string`, `max:100` |
| `type` | sí | `in:internal,external` |
| `description` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`CostCenterResource`):** `id`, `code`, `name`, `type`, `type_label`, `is_external`, `description`, `is_active`

---

## `DELETE /api/v1/cost-centers/{id}`

Eliminar un centro de costo.

**Permiso requerido:** `centros_costo.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/medical-services/tree`

Árbol de servicios con sus procedimientos anidados.

**Permiso requerido:** `servicios_medicos.ver`

**Query params detectados:** `only_active`

**Campos de respuesta (`MedicalServiceResource`):** `id`, `type`, `type_label`, `parent_id`, `code`, `external_code`, `name`, `description`, `is_active`

---

## `GET /api/v1/medical-services/search`

Buscar procedimientos por nombre o código, incluyendo la tarifa vigente.

**Permiso requerido:** `servicios_medicos.ver`

**Query params** (validados):

| Campo | Requerido | Reglas |
|---|---|---|
| `q` | sí | `string`, `min:2` |

---

## `GET /api/v1/medical-services`

Listar servicios médicos y/o procedimientos (plano).

**Permiso requerido:** `servicios_medicos.ver`

**Query params detectados:** `type`, `parent_id`, `is_active`, `search`

**Campos de respuesta (`MedicalServiceResource`):** `id`, `type`, `type_label`, `parent_id`, `code`, `external_code`, `name`, `description`, `is_active`

---

## `GET /api/v1/medical-services/{id}`

Obtener un servicio médico o procedimiento.

**Permiso requerido:** `servicios_medicos.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`MedicalServiceResource`):** `id`, `type`, `type_label`, `parent_id`, `code`, `external_code`, `name`, `description`, `is_active`

---

## `GET /api/v1/medical-services/{medical_service}/procedures`

Listar procedimientos de un servicio.

**Permiso requerido:** `servicios_medicos.ver`

**Parámetros de ruta:** `medical_service`

**Campos de respuesta (`MedicalServiceResource`):** `id`, `type`, `type_label`, `parent_id`, `code`, `external_code`, `name`, `description`, `is_active`

---

## `POST /api/v1/medical-services`

Crear un servicio médico o procedimiento.

**Permiso requerido:** `servicios_medicos.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `type` | no | `string`, `in:service,procedure` |
| `parent_id` | no | `integer`, `exists:medical_services,id`, `nullable` |
| `code` | sí | `string`, `max:20`, `unique:medical_services,code` |
| `external_code` | no | `string`, `max:20`, `unique:medical_services,external_code`, `nullable` |
| `name` | sí | `string`, `max:100` |
| `description` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`MedicalServiceResource`):** `id`, `type`, `type_label`, `parent_id`, `code`, `external_code`, `name`, `description`, `is_active`

---

## `PUT /api/v1/medical-services/{id}`

Actualizar un servicio médico o procedimiento.

**Permiso requerido:** `servicios_medicos.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `type` | no | `string`, `in:service,procedure` |
| `parent_id` | no | `integer`, `exists:medical_services,id`, `nullable` |
| `code` | sí | `string`, `max:20`, `unique` |
| `external_code` | no | `string`, `max:20`, `unique`, `nullable` |
| `name` | sí | `string`, `max:100` |
| `description` | no | `string`, `nullable` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`MedicalServiceResource`):** `id`, `type`, `type_label`, `parent_id`, `code`, `external_code`, `name`, `description`, `is_active`

---

## `DELETE /api/v1/medical-services/{id}`

Eliminar un servicio médico o procedimiento (soft delete).

**Permiso requerido:** `servicios_medicos.eliminar`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/import/medical-services`

**Permiso requerido:** `servicios_medicos.importar`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:xlsx,csv,xls`, `max:10240` |

---

## `GET /api/v1/import/medical-services/template`

**Permiso requerido:** `servicios_medicos.importar`

**Tipo de respuesta:** descarga de archivo

---

## `GET /api/v1/procedures/{procedure}/prices`

Listar tarifas de un procedimiento.

**Permiso requerido:** `procedimientos.ver`

**Parámetros de ruta:** `procedure`

**Query params detectados:** `is_active`, `effective_from`

**Campos de respuesta (`ProcedurePriceResource`):** `id`, `medical_service_id`, `unit_price`, `effective_from`, `effective_to`, `is_active`, `is_currently_valid`, `notes`

---

## `GET /api/v1/procedures/{procedure}/prices/{price}`

Obtener una tarifa.

**Permiso requerido:** `procedimientos.ver`

**Parámetros de ruta:** `procedure`, `price`

**Campos de respuesta (`ProcedurePriceResource`):** `id`, `medical_service_id`, `unit_price`, `effective_from`, `effective_to`, `is_active`, `is_currently_valid`, `notes`

---

## `POST /api/v1/procedures/{procedure}/prices`

Crear una tarifa para un procedimiento.

**Permiso requerido:** `procedimientos.crear`

**Parámetros de ruta:** `procedure`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `unit_price` | sí | `numeric`, `min:0` |
| `effective_from` | sí | `date_format:Y-m-d` |
| `effective_to` | no | `date_format:Y-m-d`, `after_or_equal:effective_from`, `nullable` |
| `is_active` | no | `boolean` |
| `notes` | no | `string`, `max:500`, `nullable` |

**Campos de respuesta (`ProcedurePriceResource`):** `id`, `medical_service_id`, `unit_price`, `effective_from`, `effective_to`, `is_active`, `is_currently_valid`, `notes`

---

## `PUT /api/v1/procedures/{procedure}/prices/{price}`

Actualizar una tarifa.

**Permiso requerido:** `procedimientos.editar`

**Parámetros de ruta:** `procedure`, `price`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `unit_price` | sí | `numeric`, `min:0` |
| `effective_from` | sí | `date_format:Y-m-d` |
| `effective_to` | no | `date_format:Y-m-d`, `after_or_equal:effective_from`, `nullable` |
| `is_active` | no | `boolean` |
| `notes` | no | `string`, `max:500`, `nullable` |

**Campos de respuesta (`ProcedurePriceResource`):** `id`, `medical_service_id`, `unit_price`, `effective_from`, `effective_to`, `is_active`, `is_currently_valid`, `notes`

---

## `DELETE /api/v1/procedures/{procedure}/prices/{price}`

Eliminar una tarifa (soft delete).

**Permiso requerido:** `procedimientos.eliminar`

**Parámetros de ruta:** `procedure`, `price`

---

## `GET /api/v1/patients/{patientExternalId}/procedure-records`

Historial de procedimientos de un paciente.

**Permiso requerido:** `registros_procedimientos.ver`

**Parámetros de ruta:** `patientExternalId`

**Query params detectados:** `service_date_from`, `service_date_to`, `medical_service_id`, `is_active`

**Campos de respuesta (`PatientProcedureHistoryResource`):** `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `summary`, `records`

---

## `GET /api/v1/patient-procedure-records`

Listar registros de procedimientos.

**Permiso requerido:** `registros_procedimientos.ver`

**Query params detectados:** `medical_service_id`, `patient_external_id`, `patient_document`, `service_date_from`, `service_date_to`, `is_active`, `seller`, `referrer`, `billing_status`, `order_number`

**Campos de respuesta (`PatientProcedureRecordResource`):** `id`, `medical_service_id`, `medical_service_name`, `movement_document_id`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `quantity`, `unit_price`, `total`, `discount_type`, `discount_value`, `discount_amount`, `net_total`, `discount_status`, `order_number`, `created_by_user_id`, `approved_by_user_id`, `approved_at`, `billing_status`, `billed_by_user_id`, `billed_at`, `service_date`, `seller`, `referrer`, `is_active`

---

## `GET /api/v1/patient-procedure-records/{id}`

Obtener un registro.

**Permiso requerido:** `registros_procedimientos.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`PatientProcedureRecordResource`):** `id`, `medical_service_id`, `medical_service_name`, `movement_document_id`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `quantity`, `unit_price`, `total`, `discount_type`, `discount_value`, `discount_amount`, `net_total`, `discount_status`, `order_number`, `created_by_user_id`, `approved_by_user_id`, `approved_at`, `billing_status`, `billed_by_user_id`, `billed_at`, `service_date`, `seller`, `referrer`, `is_active`

---

## `POST /api/v1/patient-procedure-records`

Crear un registro de procedimiento para un paciente.

**Permiso requerido:** `registros_procedimientos.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `medical_service_id` | sí | `integer`, `exists:medical_services,id` |
| `patient_external_id` | sí | `string`, `max:100` |
| `patient_document` | sí | `string`, `max:50` |
| `patient_first_name` | sí | `string`, `max:100` |
| `patient_last_name` | sí | `string`, `max:100` |
| `quantity` | sí | `numeric`, `min:0.0001` |
| `unit_price` | sí | `numeric`, `min:0` |
| `service_date` | sí | `date_format:Y-m-d`, `before_or_equal:today` |
| `notes` | no | `string`, `max:500`, `nullable` |
| `seller` | no | `string`, `max:150`, `nullable` |
| `referrer` | no | `string`, `max:150`, `nullable` |
| `movement_document_id` | no | `integer`, `exists:movement_documents,id`, `nullable` |

**Campos de respuesta (`PatientProcedureRecordResource`):** `id`, `medical_service_id`, `medical_service_name`, `movement_document_id`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `quantity`, `unit_price`, `total`, `discount_type`, `discount_value`, `discount_amount`, `net_total`, `discount_status`, `order_number`, `created_by_user_id`, `approved_by_user_id`, `approved_at`, `billing_status`, `billed_by_user_id`, `billed_at`, `service_date`, `seller`, `referrer`, `is_active`

---

## `PUT /api/v1/patient-procedure-records/{id}`

Actualizar un registro de procedimiento.

**Permiso requerido:** `registros_procedimientos.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `medical_service_id` | sí | `integer`, `exists:medical_services,id` |
| `patient_external_id` | sí | `string`, `max:100` |
| `patient_document` | sí | `string`, `max:50` |
| `patient_first_name` | sí | `string`, `max:100` |
| `patient_last_name` | sí | `string`, `max:100` |
| `quantity` | sí | `numeric`, `min:0.0001` |
| `unit_price` | sí | `numeric`, `min:0` |
| `service_date` | sí | `date_format:Y-m-d`, `before_or_equal:today` |
| `notes` | no | `string`, `max:500`, `nullable` |
| `seller` | no | `string`, `max:150`, `nullable` |
| `referrer` | no | `string`, `max:150`, `nullable` |
| `is_active` | no | `boolean` |
| `movement_document_id` | no | `integer`, `exists:movement_documents,id`, `nullable` |

**Campos de respuesta (`PatientProcedureRecordResource`):** `id`, `medical_service_id`, `medical_service_name`, `movement_document_id`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `patient_email`, `patient_address`, `patient_phone`, `quantity`, `unit_price`, `total`, `discount_type`, `discount_value`, `discount_amount`, `net_total`, `discount_status`, `order_number`, `created_by_user_id`, `approved_by_user_id`, `approved_at`, `billing_status`, `billed_by_user_id`, `billed_at`, `service_date`, `seller`, `referrer`, `is_active`

---

## `DELETE /api/v1/patient-procedure-records/{id}`

Eliminar un registro (soft delete).

**Permiso requerido:** `registros_procedimientos.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/clinical-templates/for-service/{medicalServiceId}`

**Permiso requerido:** `plantillas_clinicas.ver`

**Parámetros de ruta:** `medicalServiceId`

**Campos de respuesta (`ClinicalTemplateResource`):** `id`, `medical_service_id`, `medical_service_name`, `title`, `content`, `is_active`

---

## `GET /api/v1/clinical-templates`

**Permiso requerido:** `plantillas_clinicas.ver`

**Query params detectados:** `medical_service_id`, `is_active`

**Campos de respuesta (`ClinicalTemplateResource`):** `id`, `medical_service_id`, `medical_service_name`, `title`, `content`, `is_active`

---

## `GET /api/v1/clinical-templates/{id}`

**Permiso requerido:** `plantillas_clinicas.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ClinicalTemplateResource`):** `id`, `medical_service_id`, `medical_service_name`, `title`, `content`, `is_active`

---

## `POST /api/v1/clinical-templates`

**Permiso requerido:** `plantillas_clinicas.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `medical_service_id` | sí | `integer`, `exists:medical_services,id` |
| `title` | sí | `string`, `max:200` |
| `content` | sí | `string` |

**Campos de respuesta (`ClinicalTemplateResource`):** `id`, `medical_service_id`, `medical_service_name`, `title`, `content`, `is_active`

---

## `PUT /api/v1/clinical-templates/{id}`

**Permiso requerido:** `plantillas_clinicas.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `title` | sí | `string`, `max:200` |
| `content` | sí | `string` |
| `is_active` | no | `boolean` |

**Campos de respuesta (`ClinicalTemplateResource`):** `id`, `medical_service_id`, `medical_service_name`, `title`, `content`, `is_active`

---

## `DELETE /api/v1/clinical-templates/{id}`

**Permiso requerido:** `plantillas_clinicas.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/patient-procedure-records/{patientProcedureRecord}/evolutions`

**Permiso requerido:** `evoluciones_clinicas.ver`

**Parámetros de ruta:** `patientProcedureRecord`

**Campos de respuesta (`PatientClinicalEvolutionResource`):** `id`, `patient_procedure_record_id`, `content`, `user_id`, `user_name`, `recorded_at`

---

## `POST /api/v1/patient-procedure-records/{patientProcedureRecord}/evolutions`

**Permiso requerido:** `evoluciones_clinicas.crear`

**Parámetros de ruta:** `patientProcedureRecord`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `content` | sí | `string` |
| `recorded_at` | no | `date_format:Y-m-d H:i:s` |

**Campos de respuesta (`PatientClinicalEvolutionResource`):** `id`, `patient_procedure_record_id`, `content`, `user_id`, `user_name`, `recorded_at`

---

## `PUT /api/v1/patient-procedure-records/{patientProcedureRecord}/evolutions/{evolution}`

**Permiso requerido:** `evoluciones_clinicas.editar`

**Parámetros de ruta:** `patientProcedureRecord`, `evolution`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `content` | sí | `string` |

**Campos de respuesta (`PatientClinicalEvolutionResource`):** `id`, `patient_procedure_record_id`, `content`, `user_id`, `user_name`, `recorded_at`

---

## `DELETE /api/v1/patient-procedure-records/{patientProcedureRecord}/evolutions/{evolution}`

**Permiso requerido:** `evoluciones_clinicas.eliminar`

**Parámetros de ruta:** `patientProcedureRecord`, `evolution`

---

## `GET /api/v1/patient-procedure-records/{patientProcedureRecord}/medications`

**Permiso requerido:** `medicamentos_procedimiento.ver`

**Parámetros de ruta:** `patientProcedureRecord`

---
