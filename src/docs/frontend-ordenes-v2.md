# Ajustes en búsqueda de pacientes y procedimientos

**Fecha:** 2026-09-10 · **Rama:** main · **Endpoints afectados:** 2

---

## 1. Búsqueda de pacientes ahora incluye sus últimas citas

**Cambio:** el endpoint existente devuelve un campo `recent_appointments` por cada paciente con sus 3 citas más recientes (más reciente primero). Ya no es necesario un segundo llamado para obtener el historial inicial.

### Endpoint

```
GET /api/v1/medsys/patients?search={término}
Permiso: integraciones.ver
```

| Parámetro | Tipo | Regla | Descripción |
|-----------|------|-------|-------------|
| `search` | string | requerido, mín. 3 chars | Solo dígitos → busca por documento. Mixto → busca por nombre. |

### Diferencia respecto a la versión anterior

```
- Antes: patients[] sin información de citas. Se necesitaba GET extra a /patients/{codigo}/appointments.
+ Ahora: cada paciente incluye recent_appointments[] con sus últimas 3 citas (más reciente primero).
```

### Respuesta 200

```json
{
  "success": true,
  "message": "Resultados de búsqueda en MedSys",
  "data": {
    "patients": [
      {
        "codigo":     "P00123",
        "tipodoc":    "CC",
        "documento":  "1020304050",
        "nombre":     "María García López",
        "email":      "mgarcia@example.com",
        "direccion":  "Calle 45 # 12-34",
        "telcelular": "3001234567",
        "telefono":   null,

        "recent_appointments": [
          {
            "codcontrol":     "C099",
            "fecha":          "2026-09-05",
            "hora":           "10:30:00",
            "codtipocontrol": "DER",
            "servicio":       "Dermatología",
            "estado":         "Atendido"
          },
          {
            "codcontrol":     "C087",
            "fecha":          "2026-08-12",
            "hora":           "09:00:00",
            "codtipocontrol": "DER",
            "servicio":       "Dermatología",
            "estado":         "Atendido"
          },
          {
            "codcontrol":     "C071",
            "fecha":          "2026-07-20",
            "hora":           "14:00:00",
            "codtipocontrol": "CTL",
            "servicio":       "Control",
            "estado":         "Atendido"
          }
        ]
      }
    ]
  }
}
```

> **Nota:** `recent_appointments` puede ser un array vacío si el paciente no tiene citas en MedSys.
> El endpoint `GET /patients/{codigo}/appointments` sigue disponible para consultar citas activas con filtro de fecha — es un caso de uso distinto.

---

## 2. Nuevo endpoint: búsqueda de procedimientos con tarifa incluida

Pensado para el formulario de órdenes de servicio. Con un solo llamado se obtiene el procedimiento y su tarifa vigente lista para prellenar el campo `unit_price`.

### Endpoint

```
GET /api/v1/medical-services/search?q={término}
Permiso: servicios_medicos.ver
```

| Parámetro | Tipo | Regla | Descripción |
|-----------|------|-------|-------------|
| `q` | string | requerido, mín. 2 chars | Busca en `name` y `code` del procedimiento (LIKE %q%). |

### Comportamiento

| Condición | Resultado |
|-----------|-----------|
| Solo registros con `type = 'procedure'` y `is_active = true` | Los nodos de tipo *service* (padre) nunca aparecen |
| Tarifa con `is_active = true` y vigente a hoy | `current_price` con el objeto de tarifa |
| Sin tarifa activa o tarifa expirada | `current_price: null` |
| Sin resultados | Array vacío `[]`, status 200 |
| Límite de respuesta | Máximo 20 resultados, ordenados por nombre A→Z |

### Respuesta 200

```json
{
  "success": true,
  "message": "Resultados de búsqueda de procedimientos",
  "data": [
    {
      "id":        5,
      "code":      "CUR-001",
      "name":      "Curaciones simples",
      "parent_id": 2,
      "current_price": {
        "id":             18,
        "unit_price":     25000,
        "effective_from": "2026-01-01",
        "effective_to":   null
      }
    },
    {
      "id":        9,
      "code":      "CUR-002",
      "name":      "Curaciones complejas",
      "parent_id": 2,
      "current_price": null
    }
  ]
}
```

### Flujo sugerido en el formulario de orden

```js
// 1. El usuario escribe en el buscador de procedimientos
const results = await get('/api/v1/medical-services/search', { q: input })

// 2. Al seleccionar un resultado, prellenar la línea de la orden
const line = {
  medical_service_id: result.id,
  unit_price:         result.current_price?.unit_price ?? null,
  // Si current_price es null, el usuario debe ingresar el precio manualmente
}

// 3. Enviar la orden con el payload normal (sin cambios en este endpoint)
post('/api/v1/service-orders', { procedures: [line], ... })
```

> **Advertencia:** cuando `current_price` es `null`, el usuario debe ingresar el precio manualmente.
> Se recomienda mostrar un aviso visible indicando que el procedimiento no tiene tarifa activa.
