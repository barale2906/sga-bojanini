# Guía Frontend — Órdenes de Servicio

> Base URL de todos los endpoints: `POST /api/v1/...`
> Autenticación: header `Authorization: Bearer {token}` en todas las peticiones.

---

## 1. Permisos y visibilidad del menú

El backend devuelve el menú filtrado según los permisos del usuario autenticado.
Dentro del grupo **"Órdenes de Servicio"** pueden aparecer hasta tres hijos:

| Permiso requerido | Sección visible |
|---|---|
| `ordenes_servicio.ver` | **Generar Orden** |
| `ordenes_servicio.aprobar` | **Descuentos** |
| `listas_precios.ver` | **Listas de Precios** |

Si el usuario no tiene ninguno de los tres, el grupo completo no aparece en el menú.

### Permisos por rol

| Rol | Ver | Crear | Editar | Eliminar | Aprobar | Ver precios | Cargar precios |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| super_administrador | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| administrador | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| jefe_almacen | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| operador_almacen | ✓ | ✓ | — | — | — | — | — |
| personal_medico | ✓ | ✓ | — | — | — | — | — |
| auditor | ✓ | — | — | — | — | — | — |
| compras | — | — | — | — | — | — | — |

---

## 2. Flujo completo — Generar Orden

### Paso 1 — Buscar paciente en Medsys

Antes de crear la orden, el cajero busca el paciente. El endpoint de Medsys devuelve
ahora los datos de contacto necesarios para la factura.

#### Por documento (búsqueda exacta)
```
GET /api/v1/medsys/patients?search={documento}
Permiso: integraciones.ver
```

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Paciente encontrado en MedSys",
  "data": {
    "patient": {
      "codigo": "P00123",
      "tipodoc": "CC",
      "documento": "1234567890",
      "nombre": "JUAN CARLOS GÓMEZ PÉREZ",
      "email": "juan@email.com",
      "direccion": "Calle 10 # 5-20",
      "direccion2": "Apto 301",
      "telcelular": "3001234567",
      "telefono": "6012345678"
    },
    "appointments": [...]
  }
}
```

#### Por nombre (búsqueda parcial, devuelve lista)
```
GET /api/v1/medsys/patients?search={nombre}
Permiso: integraciones.ver
```

**Respuesta 200:**
```json
{
  "success": true,
  "data": {
    "patients": [
      {
        "codigo": "P00123",
        "tipodoc": "CC",
        "documento": "1234567890",
        "nombre": "JUAN CARLOS GÓMEZ PÉREZ",
        "email": "juan@email.com",
        "direccion": "Calle 10 # 5-20",
        "direccion2": "Apto 301",
        "telcelular": "3001234567",
        "telefono": "6012345678"
      }
    ]
  }
}
```

> **Nota:** Los campos de contacto (`email`, `direccion`, `direccion2`, `telcelular`, `telefono`)
> vienen de Medsys y se deben pre-cargar en el formulario de la orden. El usuario no los digita manualmente.

---

### Paso 2 — Consultar precio vigente de cada procedimiento

Antes de mostrar el formulario, el frontend puede pre-cargar el precio de lista de cada procedimiento.

```
GET /api/v1/procedures/{id}/current-price
Permiso: ordenes_servicio.ver
```

**Respuesta 200 — con precio:**
```json
{
  "success": true,
  "message": "Precio vigente",
  "data": {
    "medical_service_id": 5,
    "unit_price": 120000.00,
    "effective_from": "2026-09-01",
    "effective_to": null
  }
}
```

**Respuesta 200 — sin precio configurado:**
```json
{
  "success": true,
  "message": "Sin precio vigente para este procedimiento",
  "data": { "price": null }
}
```

---

### Paso 3 — Crear la orden

```
POST /api/v1/service-orders
Permiso: ordenes_servicio.crear
Content-Type: application/json
```

#### Payload mínimo (un procedimiento sin descuento)
```json
{
  "patient_external_id": "P00123",
  "patient_document": "1234567890",
  "patient_first_name": "JUAN CARLOS",
  "patient_last_name": "GÓMEZ PÉREZ",
  "service_date": "2026-09-09",
  "procedures": [
    {
      "medical_service_id": 5,
      "unit_price": 120000,
      "quantity": 1
    }
  ]
}
```

#### Payload completo (múltiples procedimientos, con descuento e insumos)
```json
{
  "patient_external_id": "P00123",
  "patient_document": "1234567890",
  "patient_first_name": "JUAN CARLOS",
  "patient_last_name": "GÓMEZ PÉREZ",
  "patient_email": "juan@email.com",
  "patient_address": "Calle 10 # 5-20",
  "patient_phone": "3001234567",
  "service_date": "2026-09-09",
  "notes": "Observación general de la orden",
  "seller": "María Ruiz",
  "referrer": "Dr. López",
  "procedures": [
    {
      "medical_service_id": 5,
      "unit_price": 120000,
      "quantity": 1,
      "discount_type": "percentage",
      "discount_value": 10,
      "notes": "Paciente convenio",
      "supplies": [
        {
          "warehouse_id": 1,
          "generic_product_id": 42,
          "quantity": 2
        }
      ]
    },
    {
      "medical_service_id": 8,
      "unit_price": 85000,
      "quantity": 2,
      "discount_type": "fixed",
      "discount_value": 5000
    }
  ]
}
```

#### Descripción de campos

| Campo | Tipo | Req | Descripción |
|---|---|:---:|---|
| `patient_external_id` | string (max 100) | ✓ | Código del paciente en Medsys (`codigo`) |
| `patient_document` | string (max 50) | ✓ | Número de documento |
| `patient_first_name` | string (max 100) | ✓ | Nombres |
| `patient_last_name` | string (max 100) | ✓ | Apellidos |
| `patient_email` | email (max 100) | — | Pre-cargado desde Medsys |
| `patient_address` | string (max 150) | — | Pre-cargado desde Medsys (`direccion`) |
| `patient_phone` | string (max 50) | — | Pre-cargado desde Medsys (`telcelular`) |
| `service_date` | date `Y-m-d` | ✓ | No puede ser futura |
| `notes` | string (max 500) | — | Observación general |
| `seller` | string (max 150) | — | Nombre del vendedor/cajero |
| `referrer` | string (max 150) | — | Médico o entidad que refirió |
| `procedures` | array (min 1) | ✓ | Lista de procedimientos |
| `procedures.*.medical_service_id` | integer | ✓ | ID del servicio médico |
| `procedures.*.unit_price` | numeric ≥ 0 | ✓ | Precio unitario (tomar del paso 2) |
| `procedures.*.quantity` | numeric > 0 | ✓ | Cantidad |
| `procedures.*.discount_type` | `fixed`\|`percentage` | — | Tipo de descuento |
| `procedures.*.discount_value` | numeric ≥ 0 | — | Valor o porcentaje del descuento |
| `procedures.*.notes` | string (max 500) | — | Nota del procedimiento |
| `procedures.*.supplies` | array | — | Insumos a descontar del inventario |
| `procedures.*.supplies.*.warehouse_id` | integer | ✓* | ID del almacén de salida |
| `procedures.*.supplies.*.generic_product_id` | integer | ✓* | ID del producto genérico |
| `procedures.*.supplies.*.quantity` | numeric > 0 | ✓* | Cantidad a descontar |

> `✓*` = requerido cuando se incluye el array `supplies`.

#### Lógica de descuentos
- `discount_type: "fixed"` → descuento en pesos sobre el total de esa línea.
- `discount_type: "percentage"` → descuento porcentual sobre el total de esa línea.
- Si algún procedimiento tiene descuento, toda la orden queda en estado `discount_pending`
  y se notifica por email + push a los usuarios con permiso `ordenes_servicio.aprobar`.
- Sin descuento: la orden queda directamente en estado `approved`.

#### Respuesta 201 — Orden creada
```json
{
  "success": true,
  "message": "Orden de servicio creada exitosamente",
  "data": {
    "order_number": "OS-20260909-000001",
    "patient_external_id": "P00123",
    "patient_document": "1234567890",
    "patient_first_name": "JUAN CARLOS",
    "patient_last_name": "GÓMEZ PÉREZ",
    "patient_email": "juan@email.com",
    "patient_address": "Calle 10 # 5-20",
    "patient_phone": "3001234567",
    "service_date": "2026-09-09",
    "order_status": "discount_pending",
    "total_amount": 290000.00,
    "total_discount": 12000.00,
    "net_total": 278000.00,
    "created_by_user_id": 3,
    "procedures": [
      {
        "id": 101,
        "medical_service_id": 5,
        "medical_service_name": "Consulta Dermatología",
        "movement_document_id": 88,
        "patient_external_id": "P00123",
        "patient_document": "1234567890",
        "patient_first_name": "JUAN CARLOS",
        "patient_last_name": "GÓMEZ PÉREZ",
        "patient_email": "juan@email.com",
        "patient_address": "Calle 10 # 5-20",
        "patient_phone": "3001234567",
        "quantity": 1.0,
        "unit_price": 120000.00,
        "total": 120000.00,
        "discount_type": "percentage",
        "discount_value": 10.0,
        "discount_amount": 12000.00,
        "net_total": 108000.00,
        "discount_status": "pending",
        "order_number": "OS-20260909-000001",
        "created_by_user_id": 3,
        "approved_by_user_id": null,
        "approved_at": null,
        "service_date": "2026-09-09",
        "seller": "María Ruiz",
        "referrer": "Dr. López",
        "is_active": true
      },
      {
        "id": 102,
        "medical_service_id": 8,
        "medical_service_name": "Curación Simple",
        "movement_document_id": null,
        "quantity": 2.0,
        "unit_price": 85000.00,
        "total": 170000.00,
        "discount_type": null,
        "discount_value": null,
        "discount_amount": null,
        "net_total": null,
        "discount_status": null,
        "order_number": "OS-20260909-000001",
        "..."
      }
    ]
  }
}
```

#### Errores posibles

| HTTP | Situación |
|---|---|
| 401 | Sin token o token inválido |
| 403 | Sin permiso `ordenes_servicio.crear` |
| 409 | Error de dominio (ej. servicio no es procedimiento) |
| 422 | Validación fallida |

**Ejemplo 422:**
```json
{
  "success": false,
  "message": "The procedures field is required.",
  "errors": {
    "procedures": ["Debe incluir al menos un procedimiento."],
    "procedures.0.medical_service_id": ["El procedimiento seleccionado no existe."],
    "service_date": ["La fecha de atención no puede ser futura."]
  }
}
```

---

### Paso 4 — Consultar una orden

```
GET /api/v1/service-orders/{orderNumber}
Permiso: ordenes_servicio.ver
```

**Ejemplo:** `GET /api/v1/service-orders/OS-20260909-000001`

**Respuesta 200:** misma estructura que la respuesta del `POST` (ver arriba).

**Error 409** si el número de orden no existe:
```json
{
  "success": false,
  "message": "Orden OS-20260909-000001 no encontrada."
}
```

---

## 3. Flujo — Descuentos (aprobación)

Acceso: **menú → Órdenes de Servicio → Descuentos** (requiere `ordenes_servicio.aprobar`).

### Listar órdenes pendientes de aprobación

```
GET /api/v1/service-orders/discounts
Permiso: ordenes_servicio.aprobar
```

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Órdenes con descuentos pendientes",
  "data": [
    {
      "order_number": "OS-20260909-000001",
      "patient_external_id": "P00123",
      "patient_document": "1234567890",
      "patient_first_name": "JUAN CARLOS",
      "patient_last_name": "GÓMEZ PÉREZ",
      "patient_email": "juan@email.com",
      "patient_address": "Calle 10 # 5-20",
      "patient_phone": "3001234567",
      "service_date": "2026-09-09",
      "created_by_user_id": 3,
      "records": [
        {
          "id": 101,
          "medical_service_name": "Consulta Dermatología",
          "total": 120000.00,
          "discount_type": "percentage",
          "discount_value": 10.0,
          "discount_amount": 12000.00,
          "net_total": 108000.00,
          "discount_status": "pending",
          "..."
        }
      ]
    }
  ]
}
```

### Aprobar el descuento de una orden

```
POST /api/v1/service-orders/{orderNumber}/approve
Permiso: ordenes_servicio.aprobar
Body: vacío (sin payload)
```

**Respuesta 200 — aprobado:**
```json
{
  "success": true,
  "message": "Descuento aprobado exitosamente",
  "data": {
    "order_number": "OS-20260909-000001",
    "order_status": "approved",
    "total_amount": 120000.00,
    "total_discount": 12000.00,
    "net_total": 108000.00,
    "procedures": [
      {
        "discount_status": "approved",
        "approved_by_user_id": 1,
        "approved_at": "2026-09-09 10:45:00",
        "..."
      }
    ]
  }
}
```

**Errores posibles:**

| HTTP | Mensaje | Causa |
|---|---|---|
| 403 | Forbidden | Sin permiso `ordenes_servicio.aprobar` |
| 409 | "La orden ... no tiene descuentos pendientes de aprobación." | Ya fue aprobada o no tenía descuentos |
| 409 | "Orden ... no encontrada." | Número de orden inexistente |

---

## 4. Flujo — Listas de Precios

Acceso: **menú → Órdenes de Servicio → Listas de Precios** (requiere `listas_precios.ver`).

### Descargar plantilla Excel

```
GET /api/v1/price-lists/template
Permiso: listas_precios.ver
```

- Responde con un archivo `.xlsx` descargable.
- El archivo trae todos los procedimientos activos con su precio vigente actual.
- Columnas: `A = procedure_code`, `B = procedure_name`, `C = service_name`, `D = unit_price`.
- El usuario solo debe editar la columna **D** con los nuevos precios.

> **Implementación sugerida:** hacer la petición con `axios` con `responseType: 'blob'`
> y generar un `<a>` para disparar la descarga.

### Cargar lista de precios

```
POST /api/v1/price-lists/import
Permiso: listas_precios.crear
Content-Type: multipart/form-data
```

**Payload:** un campo `file` con el archivo Excel (`.xlsx` o `.xls`).

**Comportamiento del backend:**
1. Lee el archivo fila por fila (omite la primera fila de encabezado).
2. Busca el procedimiento por código en columna A.
3. Valida que el precio en columna D sea numérico y ≥ 0.
4. Si hay al menos un precio válido: **inactiva toda la lista anterior** y crea la nueva.
5. Los precios inválidos o códigos inexistentes se reportan en `skipped_detail` (no detienen la carga).
6. Si **ninguna** fila es válida, devuelve 409 sin modificar la base de datos.

**Respuesta 200:**
```json
{
  "success": true,
  "message": "Lista de precios cargada exitosamente",
  "data": {
    "processed": 45,
    "skipped": 2,
    "skipped_detail": [
      { "row": 5, "code": "PROC-999", "reason": "Código no encontrado" },
      { "row": 12, "code": "PROC-010", "reason": "Precio inválido" }
    ]
  }
}
```

**Errores posibles:**

| HTTP | Causa |
|---|---|
| 403 | Sin permiso `listas_precios.crear` |
| 409 | "El archivo no contiene precios válidos." |
| 422 | Campo `file` ausente o formato incorrecto (solo `.xlsx` / `.xls`) |

---

## 5. Estados de una orden

| `order_status` en `ServiceOrderResource` | Significado |
|---|---|
| `approved` | Ningún procedimiento tiene descuento pendiente |
| `discount_pending` | Al menos un procedimiento tiene `discount_status = 'pending'` |

### Estados por procedimiento (`discount_status`)

| Valor | Significado |
|---|---|
| `null` | El procedimiento no tiene descuento |
| `"pending"` | Descuento solicitado, esperando aprobación |
| `"approved"` | Descuento aprobado |

---

## 6. Notificaciones

Cuando se crea una orden **con descuento**, el sistema envía automáticamente:
- **Push notification** (FCM) a todos los usuarios con rol que tenga `ordenes_servicio.aprobar`.
- **Email** a los mismos usuarios.

Cuando se **aprueba** el descuento:
- **Email** al usuario que creó la orden (`created_by_user_id`).

El frontend puede escuchar las notificaciones vía el endpoint existente de notificaciones del usuario para actualizar el badge de la sección Descuentos.

---

## 7. Número de orden

El formato es: `OS-{YYYYMMDD}-{secuencial 6 dígitos}`

Ejemplo: `OS-20260909-000001`

- Se genera automáticamente en el backend.
- El secuencial reinicia cada día.
- Es único e inmutable.

---

## 8. Resumen de endpoints

| Método | Endpoint | Permiso | Descripción |
|---|---|---|---|
| `GET` | `/api/v1/medsys/patients?search=` | `integraciones.ver` | Buscar paciente (doc o nombre) |
| `GET` | `/api/v1/procedures/{id}/current-price` | `ordenes_servicio.ver` | Precio vigente de un procedimiento |
| `POST` | `/api/v1/service-orders` | `ordenes_servicio.crear` | Crear orden de servicio |
| `GET` | `/api/v1/service-orders/{orderNumber}` | `ordenes_servicio.ver` | Ver una orden |
| `GET` | `/api/v1/service-orders/discounts` | `ordenes_servicio.aprobar` | Listar órdenes con descuentos pendientes |
| `POST` | `/api/v1/service-orders/{orderNumber}/approve` | `ordenes_servicio.aprobar` | Aprobar descuento de una orden |
| `GET` | `/api/v1/price-lists/template` | `listas_precios.ver` | Descargar plantilla Excel de precios |
| `POST` | `/api/v1/price-lists/import` | `listas_precios.crear` | Cargar nueva lista de precios |

---

## 9. Consideraciones de implementación frontend

1. **Búsqueda de paciente:** usar el campo `codigo` como `patient_external_id` y los campos de nombre separados para llenar el formulario. El backend espera `patient_first_name` y `patient_last_name` por separado.

2. **Precio pre-cargado:** al seleccionar un procedimiento, llamar a `GET /procedures/{id}/current-price` y pre-llenar el campo `unit_price`. El cajero puede modificarlo antes de guardar.

3. **Descuento condicionado:** solo mostrar los campos de descuento si el usuario tiene permiso `ordenes_servicio.aprobar` — o, según el flujo de negocio, siempre mostrarlos pero que requieran aprobación. El backend acepta el descuento independientemente del rol; la aprobación es lo que está protegida.

4. **Insumos opcionales:** el array `supplies` se incluye solo cuando el procedimiento consume inventario. Si el usuario no especifica insumos, simplemente omitir el campo.

5. **Carga de precios:** hacer la petición de descarga con `responseType: 'blob'` para recibir el Excel. Para la carga, usar `FormData` con el campo `file`.

6. **Polling de descuentos:** el listado `GET /service-orders/discounts` solo retorna órdenes con `discount_status = 'pending'`. Una vez aprobadas desaparecen de la lista automáticamente.

7. **Error 409:** siempre mostrar el campo `message` de la respuesta ya que contiene el detalle legible para el usuario.
