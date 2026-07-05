# Guía Frontend — Registro de Procedimiento con Vendedor/Operador y Remitente

**Versión:** 3.1 · **Fecha:** 2026-07-04  
**Módulo:** Centro de Costo → Registros de Procedimientos por Paciente

---

## 1. Contexto

El **vendedor/operador** y el **remitente** son campos del **registro de procedimiento del paciente** (`PatientProcedureRecord`), **no del movimiento de inventario**. Esta separación evita duplicar información: el movimiento de salida captura el trazado del stock; el registro de procedimiento captura el contexto clínico y administrativo.

| Campo | Etiqueta sugerida en UI | Descripción |
|---|---|---|
| `seller` | Vendedor / Operador | Nombre de la persona que despacha o atiende. Texto libre. |
| `referrer` | Remitente | Nombre del médico u profesional que remite al paciente. Texto libre. |

Ambos campos son **opcionales** (nullable). Son texto libre sin vínculo a usuarios del sistema — si la persona no está registrada, se escribe el nombre directamente.

---

## 2. Relación entre movimiento y registro de procedimiento

```
POST /api/v1/movements/exit              → descuenta stock, vincula patient_document y patient_external_id
POST /api/v1/patient-procedure-records   → captura cantidad, precio, vendedor, remitente y notas clínicas
```

Ambas llamadas son independientes y complementarias. El nexo entre ellas es el `patient_external_id`.

---

## 3. Crear un registro de procedimiento

### Endpoint

```
POST /api/v1/patient-procedure-records
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: registros_procedimientos.crear
```

### Body completo

```json
{
  "medical_service_id":  5,
  "patient_external_id": "EXT-00892",
  "patient_document":    "1012345678",
  "patient_first_name":  "Laura",
  "patient_last_name":   "Torres Ríos",
  "quantity":            2,
  "unit_price":          85000,
  "service_date":        "2026-07-04",
  "notes":               "Procedimiento de urgencias",
  "seller":              "Ana Gómez",
  "referrer":            "Dr. Rodríguez Pérez"
}
```

### Descripción de cada campo

| Campo | Tipo | Requerido | Restricciones |
|---|---|---|---|
| `medical_service_id` | integer | Sí | Debe ser un procedimiento (`type=procedure`) |
| `patient_external_id` | string | Sí | ID del paciente en sistema de historia clínica · max 100 |
| `patient_document` | string | Sí | Documento de identidad del paciente · max 50 |
| `patient_first_name` | string | Sí | Nombres del paciente · max 100 |
| `patient_last_name` | string | Sí | Apellidos del paciente · max 100 |
| `quantity` | number | Sí | Cantidad (> 0) |
| `unit_price` | number | Sí | Precio unitario (>= 0) |
| `service_date` | date `Y-m-d` | Sí | Fecha de atención, no puede ser futura |
| `notes` | string | No | Notas internas · max 500 · **no se expone en listados ni detalle** |
| `seller` | string | No | Texto libre · max 150 caracteres |
| `referrer` | string | No | Texto libre · max 150 caracteres |

> **`total` se calcula automáticamente:** el backend computa `quantity × unit_price`. No enviar `total` en el body.

### Response exitosa — `201 Created`

```json
{
  "success": true,
  "message": "Registro creado exitosamente",
  "data": {
    "id":                   42,
    "medical_service_id":   5,
    "medical_service_name": "Curaciones Simples",
    "patient_external_id":  "EXT-00892",
    "patient_document":     "1012345678",
    "patient_first_name":   "Laura",
    "patient_last_name":    "Torres Ríos",
    "quantity":             2,
    "unit_price":           85000,
    "total":                170000,
    "service_date":         "2026-07-04",
    "seller":               "Ana Gómez",
    "referrer":             "Dr. Rodríguez Pérez",
    "is_active":            true
  }
}
```

---

## 4. Actualizar un registro

### Endpoint

```
PUT /api/v1/patient-procedure-records/{id}
Authorization: Bearer {token}
Content-Type: application/json
Permiso requerido: registros_procedimientos.editar
```

### Campos en el body

Los mismos que en `POST`. Los campos de paciente, servicio, cantidad, precio y fecha siguen siendo **obligatorios**. Para borrar el vendedor o remitente, enviar `null`:

```json
{
  "medical_service_id":  5,
  "patient_external_id": "EXT-00892",
  "patient_document":    "1012345678",
  "patient_first_name":  "Laura",
  "patient_last_name":   "Torres Ríos",
  "quantity":            3,
  "unit_price":          85000,
  "service_date":        "2026-07-04",
  "seller":              "Carlos Ruiz",
  "referrer":            null,
  "is_active":           true
}
```

| Campo | Requerido en PUT |
|---|---|
| `medical_service_id`, `patient_external_id`, `patient_document`, `patient_first_name`, `patient_last_name`, `quantity`, `unit_price`, `service_date` | Sí |
| `notes`, `seller`, `referrer` | No (nullable) |
| `is_active` | No (boolean, default `true`) |

### Response exitosa — `200 OK`

Igual que el `POST`, con los datos actualizados.

---

## 5. Listar registros con filtros por seller y referrer

Los filtros `seller` y `referrer` realizan **búsqueda parcial sobre las columnas homónimas** de la tabla `patient_procedure_records` (SQL `LIKE %valor%`). La distinción de mayúsculas depende del cotejamiento configurado en la base de datos.

### Endpoint

```
GET /api/v1/patient-procedure-records
Authorization: Bearer {token}
Permiso requerido: registros_procedimientos.ver
```

### Parámetros de query disponibles

| Parámetro | Tipo | Descripción |
|---|---|---|
| `medical_service_id` | integer | Filtrar por procedimiento (ID exacto) |
| `patient_external_id` | string | Filtrar por ID de paciente (valor exacto) |
| `patient_document` | string | Búsqueda parcial por documento del paciente |
| `service_date_from` | date `Y-m-d` | Desde esta fecha de atención |
| `service_date_to` | date `Y-m-d` | Hasta esta fecha de atención |
| `is_active` | boolean | Filtrar por estado activo/inactivo |
| `seller` | string | Búsqueda parcial por nombre del vendedor/operador |
| `referrer` | string | Búsqueda parcial por nombre del remitente |

### Ejemplos de llamadas

```
# Buscar por vendedor parcial
GET /api/v1/patient-procedure-records?seller=Ana

# Buscar por remitente parcial
GET /api/v1/patient-procedure-records?referrer=Rodríguez

# Combinar vendedor + rango de fechas
GET /api/v1/patient-procedure-records?seller=Gómez&service_date_from=2026-07-01

# Todos los registros de un paciente
GET /api/v1/patient-procedure-records?patient_external_id=EXT-00892
```

### Response — `200 OK`

```json
{
  "success": true,
  "message": "Registros de procedimientos",
  "data": [
    {
      "id":                   42,
      "medical_service_id":   5,
      "medical_service_name": "Curaciones Simples",
      "patient_external_id":  "EXT-00892",
      "patient_document":     "1012345678",
      "patient_first_name":   "Laura",
      "patient_last_name":    "Torres Ríos",
      "quantity":             2,
      "unit_price":           85000,
      "total":                170000,
      "service_date":         "2026-07-04",
      "seller":               "Ana Gómez",
      "referrer":             "Dr. Rodríguez Pérez",
      "is_active":            true
    }
  ]
}
```

> `seller` y `referrer` pueden ser `null` si no fueron registrados. `notes` **nunca se incluye** en los listados ni en el detalle — es información interna.

---

## 6. Detalle de un registro

```
GET /api/v1/patient-procedure-records/{id}
Authorization: Bearer {token}
Permiso requerido: registros_procedimientos.ver
```

La respuesta tiene la misma estructura que cada ítem del listado, **excepto** que `medical_service_name` no se incluye en este endpoint.

---

## 7. Historial de procedimientos de un paciente

```
GET /api/v1/patients/{patientExternalId}/procedure-records
Authorization: Bearer {token}
Permiso requerido: registros_procedimientos.ver
```

Retorna todos los procedimientos del paciente con un resumen agregado (total de registros, monto total, primera y última fecha de atención).

---

## 8. Eliminar un registro (soft delete)

```
DELETE /api/v1/patient-procedure-records/{id}
Authorization: Bearer {token}
Permiso requerido: registros_procedimientos.eliminar
```

Response `200 OK`: `{ "success": true, "message": "Registro eliminado" }`. El registro queda en la base de datos con `deleted_at` marcado, no aparece en listados.

---

## 9. Errores comunes

### `422 Unprocessable Entity` — campo inválido

```json
{
  "success": false,
  "message": "El campo seller no debe superar los 150 caracteres.",
  "errors": {
    "seller": ["El campo seller no debe superar los 150 caracteres."]
  }
}
```

### `409 Conflict` — regla de negocio

| Situación | Mensaje |
|---|---|
| `medical_service_id` es un servicio, no un procedimiento | `"El registro ... es un servicio, no un procedimiento."` |
| Registro no encontrado en PUT/DELETE | `"Registro de procedimiento con id X no encontrado."` |

### `403 Forbidden` — sin permiso

Cuando el token no tiene el permiso requerido para la acción.

---

## 10. Consideraciones de UX

- Los campos `seller` y `referrer` admiten cualquier texto UTF-8 (tildes, ñ, espacios). No aplicar restricciones de formato en el frontend.
- Se recomienda un *combobox* que sugiera nombres ya usados en registros anteriores y permita escribir uno nuevo libremente. El autocompletado puede implementarse consultando `GET /api/v1/patient-procedure-records?seller=<texto_parcial>` y extrayendo los valores únicos del campo `seller` de los resultados.
- Mostrar `seller` y `referrer` en la tabla de registros como columnas opcionales — pueden llegar como `null`.

---

## 11. Endpoints de referencia rápida

| Acción | Método | URL | Permiso |
|---|---|---|---|
| Listar registros | `GET` | `/api/v1/patient-procedure-records` | `registros_procedimientos.ver` |
| Detalle de un registro | `GET` | `/api/v1/patient-procedure-records/{id}` | `registros_procedimientos.ver` |
| Crear registro | `POST` | `/api/v1/patient-procedure-records` | `registros_procedimientos.crear` |
| Actualizar registro | `PUT` | `/api/v1/patient-procedure-records/{id}` | `registros_procedimientos.editar` |
| Eliminar registro | `DELETE` | `/api/v1/patient-procedure-records/{id}` | `registros_procedimientos.eliminar` |
| Historial del paciente | `GET` | `/api/v1/patients/{externalId}/procedure-records` | `registros_procedimientos.ver` |
| Listar procedimientos disponibles | `GET` | `/api/v1/medical-services?type=procedure` | `servicios_medicos.ver` |
