# 10 · Integración MedSys

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **4**

## `GET /api/v1/medsys/patients`

Buscar paciente en MedSys por un término único.

**Permiso requerido:** `integraciones.ver` **o** `registros_procedimientos.crear` **o** `ordenes_servicio.crear` (cualquiera de los tres)

**Query params** (validados):

| Campo | Requerido | Reglas |
|---|---|---|
| `search` | sí | `string`, `min:3` |

---

## `GET /api/v1/medsys/patients/{codigo}/appointments`

Citas activas de un paciente en MedSys.

**Permiso requerido:** `integraciones.ver` **o** `registros_procedimientos.crear` **o** `ordenes_servicio.crear` (cualquiera de los tres)

**Parámetros de ruta:** `codigo`

**Query params detectados:** `date`

---

## `GET /api/v1/medsys/procedure-types`

Listar tipos de procedimiento activos de MedSys con su mapeo a servicios de SGA.

**Permiso requerido:** `integraciones.ver`

---

## `GET /api/v1/medsys/consumption-projection`

Proyección de consumo de insumos basada en citas futuras + promedio histórico.

**Permiso requerido:** `integraciones.ver`

**Query params detectados:** `days`, `historical_days`

---
