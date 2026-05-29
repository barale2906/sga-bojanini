# Guía Frontend — Clasificación de Productos y Registros Sanitarios

## Contexto

Los productos ahora tienen dos conceptos de "tipo" que conviven:

| Campo | Valores | Propósito |
|---|---|---|
| `product_type` | `simple` / `kit` | Estructura del producto (sin cambios) |
| `classification_id` | ID de la tabla `product_classifications` | Naturaleza del producto (nuevo) |

Las clasificaciones base son **MED** (Medicamento), **DM** (Dispositivo Médico) y **OTR** (Otro), pero el catálogo es administrable.

---

## 1. Clasificaciones de Producto

### Obtener el catálogo

```
GET /api/v1/product-classifications
GET /api/v1/product-classifications?is_active=1
GET /api/v1/product-classifications?search=medic
```

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "MED",
      "name": "Medicamento",
      "description": "...",
      "has_sanitary_registration": true,
      "has_concentration": true,
      "has_risk_level": false,
      "has_pharma_fields": true,
      "has_device_fields": false,
      "has_lab_brand": true,
      "is_active": true
    },
    {
      "id": 2,
      "code": "DM",
      "name": "Dispositivo Médico",
      "has_sanitary_registration": true,
      "has_concentration": false,
      "has_risk_level": true,
      "has_pharma_fields": false,
      "has_device_fields": true,
      "has_lab_brand": true,
      "is_active": true
    },
    {
      "id": 3,
      "code": "OTR",
      "name": "Otro",
      "has_sanitary_registration": false,
      "has_concentration": false,
      "has_risk_level": false,
      "has_pharma_fields": false,
      "has_device_fields": false,
      "has_lab_brand": false,
      "is_active": true
    }
  ]
}
```

### CRUD de clasificaciones

```
POST   /api/v1/product-classifications
GET    /api/v1/product-classifications/{id}
PUT    /api/v1/product-classifications/{id}
DELETE /api/v1/product-classifications/{id}
```

**Body (POST / PUT):**
```json
{
  "code": "DIAG",
  "name": "Diagnóstico in vitro",
  "description": "...",
  "has_sanitary_registration": true,
  "has_concentration": false,
  "has_risk_level": true,
  "has_pharma_fields": false,
  "has_device_fields": true,
  "has_lab_brand": true
}
```

---

## 2. Cómo usar los flags para renderizar el formulario de producto

Cuando el usuario selecciona una clasificación, usa sus flags para mostrar/ocultar campos:

```
has_concentration    → campo "Concentración"            (ej: 500mg/5ml)
has_risk_level       → campo "Nivel de Riesgo"          (ej: Clase I, Clase IIA)
has_lab_brand        → campo "Laboratorio / Marca"      (requerido si flag = true)
has_pharma_fields    → campos "Forma Farmacéutica"
                              "Presentación Comercial"
has_device_fields    → campos "Serie / Referencia"
                              "Vida Útil"
has_sanitary_registration → habilitar sección de Registros Sanitarios
```

> **Importante:** `lab_brand` es **obligatorio** cuando `has_lab_brand = true`. El backend lo valida y responde `409` si falta.

### Lógica sugerida (pseudocódigo)

```js
const fields = {
  concentration:          classification.has_concentration,
  risk_level:             classification.has_risk_level,
  lab_brand:              classification.has_lab_brand,       // required si true
  pharmaceutical_form:    classification.has_pharma_fields,
  commercial_presentation:classification.has_pharma_fields,
  serie_reference:        classification.has_device_fields,
  useful_life:            classification.has_device_fields,
  sanitaryRegistrations:  classification.has_sanitary_registration,
}
```

---

## 3. Crear / Actualizar Producto

Los nuevos campos se envían en el mismo body que siempre.

### POST /api/v1/products — Medicamento

```json
{
  "category_id": 1,
  "base_unit_id": 1,
  "classification_id": 1,
  "product_type": "simple",
  "name": "Acetaminofén 500mg",
  "code": "MED-ACE-500",
  "concentration": "500mg",
  "lab_brand": "Genfar",
  "pharmaceutical_form": "Tableta",
  "commercial_presentation": "Caja x 100 Tab"
}
```

### POST /api/v1/products — Dispositivo Médico

```json
{
  "category_id": 1,
  "base_unit_id": 1,
  "classification_id": 2,
  "name": "Monitor de signos vitales",
  "code": "DM-MON-001",
  "risk_level": "Clase IIA",
  "lab_brand": "Philips Healthcare",
  "serie_reference": "SRP-1000X",
  "useful_life": "10 años"
}
```

### Todos los campos nuevos (todos opcionales excepto `lab_brand` según clasificación)

| Campo | Tipo | Max | Aplica a |
|---|---|---|---|
| `classification_id` | integer | — | Todos |
| `concentration` | string | 100 | Medicamentos |
| `risk_level` | string | 100 | Dispositivos Médicos |
| `lab_brand` | string | 255 | Med + DM (requerido), Otros (opcional) |
| `pharmaceutical_form` | string | 150 | Medicamentos |
| `commercial_presentation` | string | 150 | Medicamentos |
| `serie_reference` | string | 150 | Dispositivos Médicos |
| `useful_life` | string | 100 | Dispositivos Médicos |

---

## 4. Respuesta de GET /api/v1/products/{id}

El detalle del producto ahora incluye la clasificación y los registros sanitarios embebidos:

```json
{
  "success": true,
  "data": {
    "id": 5,
    "name": "Catéter venoso",
    "code": "CAT-VEN-001",
    "classification_id": 2,
    "risk_level": "Clase IIA",
    "lab_brand": "Medline",
    "serie_reference": "CV-200",
    "useful_life": "Uso único",
    "classification": {
      "id": 2,
      "code": "DM",
      "name": "Dispositivo Médico",
      "has_sanitary_registration": true,
      "has_risk_level": true,
      "has_device_fields": true,
      "has_lab_brand": true
    },
    "sanitary_registrations": [
      {
        "id": 1,
        "registration_number": "INVIMA-2024-001",
        "expiry_date": "2028-12-31",
        "is_active": true,
        "is_expired": false
      }
    ],
    "concentration": null,
    "pharmaceutical_form": null,
    "commercial_presentation": null
  }
}
```

---

## 5. Registros Sanitarios

Los registros sanitarios se gestionan en endpoints anidados bajo el producto.

### Listar

```
GET /api/v1/products/{productId}/sanitary-registrations
GET /api/v1/products/{productId}/sanitary-registrations?only_active=true
```

`only_active=true` retorna solo los que tienen `is_active = true` y `expiry_date >= hoy`.

### Crear

```
POST /api/v1/products/{productId}/sanitary-registrations
```

```json
{
  "registration_number": "INVIMA-2024-123456",
  "expiry_date": "2028-12-31",
  "is_active": true
}
```

### Actualizar

```
PUT /api/v1/products/{productId}/sanitary-registrations/{registrationId}
```

```json
{
  "registration_number": "INVIMA-2024-123456",
  "expiry_date": "2030-06-30",
  "is_active": true
}
```

### Eliminar

```
DELETE /api/v1/products/{productId}/sanitary-registrations/{registrationId}
```

### Respuesta de registro sanitario

```json
{
  "id": 1,
  "product_id": 5,
  "registration_number": "INVIMA-2024-123456",
  "expiry_date": "2028-12-31",
  "is_active": true,
  "is_expired": false
}
```

> `is_expired: true` cuando `expiry_date < hoy`. Útil para mostrar alertas visuales.

---

## 6. Permisos requeridos

| Acción | Permiso |
|---|---|
| Ver clasificaciones / productos | `productos.ver` |
| Crear clasificaciones / productos | `productos.crear` |
| Editar clasificaciones / productos / registros sanitarios | `productos.editar` |
| Eliminar clasificaciones | `productos.eliminar` |

---

## 7. Códigos de respuesta relevantes

| Código | Significado |
|---|---|
| `201` | Recurso creado exitosamente |
| `200` | OK (incluye eliminación exitosa) |
| `409` | Conflicto de dominio (ej: `lab_brand` requerido, registro sanitario duplicado, producto inexistente) |
| `422` | Error de validación de formato (campo requerido, tipo incorrecto, valor ya existe en BD) |
| `404` | Recurso no encontrado |
