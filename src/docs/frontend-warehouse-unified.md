# Gestión unificada de Almacenes, Zonas y Ubicaciones

> Endpoints: `POST /api/v1/warehouses` y `PUT /api/v1/warehouses/{id}`
>
> Permisos requeridos: `almacenes.crear` (POST) · `almacenes.editar` (PUT)

Estos endpoints aceptan el almacén con sus zonas y ubicaciones anidadas en un solo payload.
Si no se envía la clave `zones`, el comportamiento es idéntico al anterior (solo datos del almacén).

---

## Tipos de zona

| Valor | Descripción |
|-------|-------------|
| `ambient` | Temperatura ambiente |
| `cold` | Refrigerado (2 – 8 °C típico) |
| `frozen` | Congelado (< 0 °C) |
| `controlled` | Temperatura controlada con rango personalizado |

---

## POST /api/v1/warehouses — Crear con estructura completa

### Payload

```json
{
  "name": "Almacén Central",
  "code": "ALM-001",
  "address": "Calle 123 # 45-67",
  "description": "Descripción opcional",
  "zones": [
    {
      "name": "Zona Fría",
      "code": "Z-FRI",
      "type": "cold",
      "temp_min": 2,
      "temp_max": 8,
      "humidity_min": null,
      "humidity_max": null,
      "description": null,
      "locations": [
        {
          "name": "Estante A1",
          "code": "A1",
          "volume_cm3": 50000,
          "max_weight_kg": 200,
          "description": null
        },
        {
          "name": "Estante A2",
          "code": "A2"
        }
      ]
    },
    {
      "name": "Zona Ambiente",
      "code": "Z-AMB",
      "type": "ambient",
      "locations": []
    }
  ]
}
```

### Campos del almacén

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | string (max 255) | ✅ | Nombre del almacén |
| `code` | string (max 50) | ✅ | Código único global |
| `address` | string \| null | — | Dirección |
| `description` | string \| null | — | Descripción libre |
| `zones` | array \| null | — | Zonas a crear junto con el almacén |

### Campos de cada zona (dentro de `zones`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | string (max 255) | ✅ | Nombre de la zona |
| `code` | string (max 50) | ✅ | Código único dentro del almacén |
| `type` | string | ✅ | `ambient` \| `cold` \| `frozen` \| `controlled` |
| `temp_min` | number \| null | — | Temperatura mínima (°C) |
| `temp_max` | number \| null | — | Temperatura máxima (°C) |
| `humidity_min` | number 0–100 \| null | — | Humedad mínima (%) |
| `humidity_max` | number 0–100 \| null | — | Humedad máxima (%) |
| `description` | string \| null | — | Descripción libre |
| `locations` | array \| null | — | Ubicaciones a crear dentro de la zona |

### Campos de cada ubicación (dentro de `zones.*.locations`)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | string (max 255) | ✅ | Nombre de la ubicación |
| `code` | string (max 50) | ✅ | Código único dentro de la zona |
| `volume_cm3` | number ≥ 0 \| null | — | Capacidad volumétrica (cm³) |
| `max_weight_kg` | number ≥ 0 \| null | — | Peso máximo soportado (kg) |
| `description` | string \| null | — | Descripción libre |

### Respuesta — 201 Created

```json
{
  "success": true,
  "message": "Almacén creado exitosamente",
  "data": {
    "id": 1,
    "name": "Almacén Central",
    "code": "ALM-001",
    "address": "Calle 123 # 45-67",
    "description": null,
    "is_active": true,
    "zones": [
      {
        "id": 1,
        "warehouse_id": 1,
        "name": "Zona Fría",
        "code": "Z-FRI",
        "type": "cold",
        "temp_min": 2.0,
        "temp_max": 8.0,
        "humidity_min": null,
        "humidity_max": null,
        "description": null,
        "is_active": true,
        "locations": [
          {
            "id": 1,
            "zone_id": 1,
            "name": "Estante A1",
            "code": "A1",
            "volume_cm3": 50000.0,
            "max_weight_kg": 200.0,
            "description": null,
            "is_active": true
          }
        ]
      }
    ]
  }
}
```

---

## PUT /api/v1/warehouses/{id} — Editar con estructura completa

El endpoint acepta el mismo payload que el de creación, con dos diferencias:

1. **`zones.*.id`** (integer | null): si se envía, la zona existente se actualiza en lugar de crearse una nueva.  
2. **`zones.*.locations.*.id`** (integer | null): igual para ubicaciones.

### Reglas de upsert

| Caso | Comportamiento |
|------|----------------|
| Zona con `id` | Se actualiza la zona existente. Debe pertenecer al almacén que se está editando. |
| Zona sin `id` | Se crea una nueva zona en el almacén. |
| Ubicación con `id` | Se actualiza la ubicación existente. Debe pertenecer a la zona que la contiene en el payload. |
| Ubicación sin `id` | Se crea una nueva ubicación en la zona. |
| Zona/Ubicación no incluida en el payload | **No se elimina.** Para eliminar, usar `DELETE /api/v1/zones/{id}` o `DELETE /api/v1/locations/{id}`. |

### Payload de ejemplo

```json
{
  "name": "Almacén Central Actualizado",
  "code": "ALM-001",
  "address": "Nueva dirección 456",
  "zones": [
    {
      "id": 1,
      "name": "Zona Fría Actualizada",
      "code": "Z-FRI",
      "type": "cold",
      "temp_min": 1,
      "temp_max": 7,
      "locations": [
        {
          "id": 1,
          "name": "Estante A1 ampliado",
          "code": "A1",
          "volume_cm3": 75000,
          "max_weight_kg": 250
        },
        {
          "name": "Estante A3 nuevo",
          "code": "A3"
        }
      ]
    },
    {
      "name": "Zona Congelados",
      "code": "Z-CON",
      "type": "frozen",
      "temp_max": -18,
      "locations": []
    }
  ]
}
```

### Respuesta — 200 OK

La estructura de respuesta es idéntica a la de creación: almacén con todas sus zonas y ubicaciones **activas** al momento de la respuesta (incluyendo las que no vinieron en el payload).

---

## Errores comunes

| HTTP | Causa |
|------|-------|
| 422 | Validación fallida (código duplicado, tipo de zona inválido, campo requerido ausente, dimensión negativa) |
| 409 | Conflicto de negocio: código de almacén/zona/ubicación ya existe, zona no pertenece al almacén, ubicación no pertenece a la zona |
| 403 | Sin permiso `almacenes.crear` / `almacenes.editar` |
| 404 | Almacén no encontrado |

---

## Endpoints individuales (siguen funcionando igual)

Si necesitas crear, editar o eliminar zonas y ubicaciones por separado, los endpoints existentes siguen disponibles:

```
POST   /api/v1/zones                   # Crear zona sola
PUT    /api/v1/zones/{id}              # Editar zona sola
DELETE /api/v1/zones/{id}              # Eliminar zona

POST   /api/v1/locations               # Crear ubicación sola
PUT    /api/v1/locations/{id}          # Editar ubicación sola
DELETE /api/v1/locations/{id}          # Eliminar ubicación

GET    /api/v1/warehouses/{id}/zones      # Listar zonas de un almacén
GET    /api/v1/warehouses/{id}/locations  # Listar ubicaciones de un almacén
GET    /api/v1/warehouses/{id}/capacity   # Capacidad completa con jerarquía
```

---

## Flujo sugerido para el formulario del frontend

```
┌─────────────────────────────────────────────────────┐
│  Formulario de Almacén                              │
│  ┌─ Datos del almacén ─────────────────────────┐   │
│  │  Nombre / Código / Dirección / Descripción  │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  ┌─ Zona 1 ────────────────────────────────────┐   │
│  │  Nombre / Código / Tipo / Temp / Humedad    │   │
│  │  ┌─ Ubicación 1 ──────────────────────────┐ │   │
│  │  │  Nombre / Código / Volumen / Peso      │ │   │
│  │  └────────────────────────────────────────┘ │   │
│  │  [+ Agregar ubicación]                      │   │
│  └─────────────────────────────────────────────┘   │
│  [+ Agregar zona]                                   │
│                                                     │
│  [Guardar]  →  POST (nueva) / PUT (edición)         │
└─────────────────────────────────────────────────────┘
```

Al guardar, construir el payload completo con la estructura anidada y enviar en **una sola llamada**.  
Para edición, incluir `id` en cada zona/ubicación que ya existe; omitir `id` para las nuevas.  
Para eliminar una zona o ubicación, usar los endpoints `DELETE` por separado.
