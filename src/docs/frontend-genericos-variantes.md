# Guía Frontend — Productos Genéricos y Variantes

> **Alcance:** Esta guía describe todos los cambios de API necesarios para adaptar el frontend al nuevo modelo de productos: `product_generics` (concepto clínico) + `product_variants` (marca/laboratorio). La tabla `products` ya no existe.

---

## 1. Concepto del cambio

### Antes
Un único objeto "Producto" mezclaba el concepto clínico (Aguja 21G) con la marca comercial (BD Becton Dickinson). Dos marcas del mismo producto eran dos filas separadas, con stock y lotes independientes → FEFO imposible entre marcas.

### Ahora

| Entidad | Tabla | Qué representa | Ejemplo |
|---|---|---|---|
| **Genérico** | `product_generics` | Concepto clínico/logístico. Tiene barcode único. | "Aguja 21G" — barcode `000001` |
| **Variante** | `product_variants` | Instancia de marca o laboratorio del genérico. | "BD Becton Dickinson" / "Nipro" |

**Regla de oro:**
- Las **entradas** de inventario ocurren sobre una **variante** (sabemos qué marca recibimos).
- Las **salidas** ocurren sobre un **genérico** (el sistema elige la variante con el lote más próximo a vencer — FEFO cruzado).

---

## 2. Mapa de rutas — qué cambia

### Rutas eliminadas
| Ruta antigua | Reemplazada por |
|---|---|
| `GET /api/v1/products` | `GET /api/v1/generic-products` |
| `POST /api/v1/products` | `POST /api/v1/generic-products` |
| `GET /api/v1/products/{id}` | `GET /api/v1/generic-products/{id}` |
| `PUT /api/v1/products/{id}` | `PUT /api/v1/generic-products/{id}` |
| `DELETE /api/v1/products/{id}` | `DELETE /api/v1/generic-products/{id}` |
| `GET /api/v1/suppliers/{id}/products` | `GET /api/v1/suppliers/{supplierId}/variants` |
| `POST /api/v1/suppliers/{id}/products/{pid}` | `POST /api/v1/suppliers/{supplierId}/variants/{variantId}` |
| `PUT /api/v1/suppliers/{id}/products/{pid}` | `PUT /api/v1/suppliers/{supplierId}/variants/{variantId}` |
| `DELETE /api/v1/suppliers/{id}/products/{pid}` | `DELETE /api/v1/suppliers/{supplierId}/variants/{variantId}` |
| `GET /api/v1/products/{id}/sanitary-registrations` | `GET /api/v1/variants/{variantId}/sanitary-registrations` |
| `POST /api/v1/products/{id}/sanitary-registrations` | `POST /api/v1/variants/{variantId}/sanitary-registrations` |
| `PUT /api/v1/products/{id}/sanitary-registrations/{r}` | `PUT /api/v1/variants/{variantId}/sanitary-registrations/{r}` |
| `DELETE /api/v1/products/{id}/sanitary-registrations/{r}` | `DELETE /api/v1/variants/{variantId}/sanitary-registrations/{r}` |
| `GET /api/v1/products/{id}/kit-components` | `GET /api/v1/generic-products/{id}/kit-components` |
| `PUT /api/v1/products/{id}/kit-components` | `PUT /api/v1/generic-products/{id}/kit-components` |
| `GET /api/v1/stock/kit-availability?kit_product_id=…` | `GET /api/v1/stock/kit-availability?kit_generic_id=…` |

---

## 3. Catálogo de productos genéricos

### 3.1 Listar genéricos

```
GET /api/v1/generic-products
```

**Query params opcionales:**

| Param | Tipo | Descripción |
|---|---|---|
| `category_id` | integer | Filtrar por categoría |
| `product_type` | `simple` \| `kit` | Filtrar por tipo |
| `is_active` | `1` \| `0` | Solo activos/inactivos |
| `search` | string | Búsqueda por nombre |

**Respuesta:**
```json
{
  "success": true,
  "message": "Listado de productos genéricos",
  "data": [
    {
      "id": 1,
      "name": "Aguja 21G",
      "barcode": "000001",
      "description": "Aguja hipodérmica 21G",
      "product_type": "simple",
      "concentration": null,
      "pharmaceutical_form": null,
      "volume_cm3": null,
      "weight_kg": null,
      "requires_cold_chain": false,
      "reorder_point": 500,
      "reorder_quantity": 0,
      "min_stock": 100,
      "max_stock": 0,
      "is_active": true,
      "created_at": "2026-07-06T10:00:00+00:00"
    }
  ]
}
```

> El listado devuelve los genéricos sin variantes anidadas. Para ver variantes usar el endpoint `show` o el endpoint de variantes.

---

### 3.2 Crear genérico

```
POST /api/v1/generic-products
```

**Body:**
```json
{
  "name": "Acetaminofén 500mg",
  "category_id": 3,
  "base_unit_id": 1,
  "classification_id": 2,
  "product_type": "simple",
  "concentration": "500mg",
  "pharmaceutical_form": "Tableta",
  "description": "Analgésico y antipirético",
  "requires_cold_chain": false,
  "reorder_point": 200,
  "reorder_quantity": 500,
  "min_stock": 50,
  "max_stock": 2000
}
```

**Para crear un kit**, incluir `product_type: "kit"` y el array `components`:
```json
{
  "name": "Paquete cirugía básica",
  "category_id": 3,
  "base_unit_id": 5,
  "product_type": "kit",
  "components": [
    { "component_generic_id": 2, "quantity_per_kit": 5 },
    { "component_generic_id": 1, "quantity_per_kit": 10 }
  ]
}
```

**Respuesta:** El campo `barcode` se genera automáticamente (6 dígitos, secuencial: `000001`, `000002`, …).

---

### 3.3 Ver genérico con variantes

```
GET /api/v1/generic-products/{id}
```

Devuelve el genérico con todas sus relaciones anidadas:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Aguja 21G",
    "barcode": "000001",
    "product_type": "simple",
    "classification": {
      "id": 1,
      "code": "DM",
      "name": "Dispositivo médico",
      "has_sanitary_registration": true,
      "has_risk_level": true,
      "has_lab_brand": true,
      "has_concentration": false,
      "has_pharma_fields": false,
      "has_device_fields": true
    },
    "category": { "id": 2, "name": "Insumos médicos", "code": "INS-MED" },
    "base_unit": { "id": 1, "name": "Unidad", "abbreviation": "UND" },
    "variants": [
      {
        "id": 1,
        "generic_product_id": 1,
        "lab_brand": "BD Becton Dickinson",
        "brand_sku": "BD-AGU-21G",
        "commercial_presentation": null,
        "serie_reference": null,
        "useful_life": null,
        "risk_level": null,
        "is_active": true,
        "sanitary_registrations": []
      }
    ],
    "components": []
  }
}
```

---

### 3.4 Actualizar genérico

```
PUT /api/v1/generic-products/{id}
```

Mismos campos que `store`. El `barcode` **no se puede cambiar** vía este endpoint (usar `/barcode/regenerate` si fuera necesario, aunque raramente se necesita).

---

### 3.5 Eliminar genérico

```
DELETE /api/v1/generic-products/{id}
```

Soft-delete. Fallará si el genérico tiene lotes con stock activo.

---

## 4. Variantes

Una variante representa una marca/laboratorio específico de un genérico. Un genérico puede tener múltiples variantes.

### 4.1 Listar variantes de un genérico

```
GET /api/v1/generic-products/{genericId}/variants
```

### 4.2 Crear variante

```
POST /api/v1/generic-products/{genericId}/variants
```

**Body:**
```json
{
  "lab_brand": "Nipro",
  "brand_sku": "NPR-AGU-21G",
  "commercial_presentation": "Caja x 100 und",
  "serie_reference": null,
  "useful_life": null,
  "risk_level": "Clase IIA"
}
```

> `lab_brand` es requerido al crear una variante vía API. En importaciones masivas puede ser nulo si el fabricante es desconocido.

**Validación por clasificación:** Si el genérico tiene una clasificación con `has_lab_brand: true`, el backend retorna 422 si `lab_brand` está vacío.

### 4.3 Actualizar variante

```
PUT /api/v1/generic-products/{genericId}/variants/{variantId}
```

### 4.4 Eliminar variante

```
DELETE /api/v1/generic-products/{genericId}/variants/{variantId}
```

---

## 5. Códigos de barras (barcodes)

Cada genérico tiene un barcode de **6 dígitos** en formato Code128, auto-generado al crearlo.

### Obtener imagen SVG del barcode

```
GET /api/v1/generic-products/{id}/barcode
```

Devuelve `Content-Type: image/svg+xml`. Se puede usar directamente en un `<img src="...">` o en una etiqueta `<object>`.

### Página de impresión — etiqueta individual

```
GET /api/v1/generic-products/{id}/barcode/print
```

Devuelve HTML listo para imprimir (`Content-Type: text/html`). Contiene el nombre del producto, el barcode Code128 en SVG y `@media print` configurado. Abrir en ventana nueva y usar `window.print()`.

### Página de impresión — lista completa

```
GET /api/v1/generic-products/barcodes/list?active=1&category_id=2
```

Devuelve HTML con una tabla de todos los genéricos y sus barcodes, apta para impresión masiva de etiquetas.

### Buscar por barcode escaneado

```
GET /api/v1/generic-products/barcode/{value}
```

Donde `{value}` es el código de 6 dígitos leído por un escáner (ej: `000001`). Devuelve el genérico completo o 404.

**Flujo típico con escáner:**
1. Usuario escanea código de barras.
2. Frontend llama `GET /api/v1/generic-products/barcode/000001`.
3. Si 200: precarga el genérico en el formulario de salida.
4. Si 404: mostrar "Producto no encontrado".

---

## 6. Movimientos de inventario — campos que cambiaron

### 6.1 Entrada (`POST /api/v1/movements/entry`)

La entrada se registra sobre una **variante** específica (sabemos qué marca ingresa).

```json
{
  "product_variant_id": 1,
  "warehouse_id": 1,
  "location_id": 5,
  "lot_number": "LOT-2026-001",
  "expiration_date": "2028-12-31",
  "manufacturing_date": "2026-01-15",
  "quantity_base": 500,
  "notes": "Factura F-2026-0045",
  "invoice_number": "F-2026-0045",
  "entry_temperature": 22.5
}
```

> Alternativa: en vez de `quantity_base` usar `product_presentation_id` + `quantity_in_presentation` (ej: 10 cajas × 50 und/caja).

**Campo clave cambiado:** `product_id` → `product_variant_id`

---

### 6.2 Salida (`POST /api/v1/movements/exit`)

La salida se registra sobre un **genérico**. El backend aplica FEFO cruzado: elige automáticamente el lote más próximo a vencer entre todas las variantes activas del genérico.

```json
{
  "generic_product_id": 1,
  "warehouse_id": 1,
  "quantity": 20,
  "cost_center_id": 3,
  "reason": "Uso en cirugía #CIR-2026-045",
  "location_id": null
}
```

**Para salida de kit:** el `generic_product_id` es el ID del genérico de tipo `kit`. El backend explota los componentes y descuenta proporcionalmente.

**Para salida con datos de paciente** (centro de costo externo):
```json
{
  "generic_product_id": 1,
  "warehouse_id": 1,
  "quantity": 2,
  "cost_center_id": 5,
  "service_id": 12,
  "patient_document": "12345678",
  "patient_external_id": "HC-2026-001"
}
```

**Campo clave cambiado:** `product_id` → `generic_product_id`

---

### 6.3 Ajuste, transferencia, devolución, baja, pérdida

Estos movimientos operan sobre **variantes** (se necesita trazar exactamente qué marca se está ajustando).

| Endpoint | Campo |
|---|---|
| `POST /api/v1/movements/adjustment` | `product_variant_id` |
| `POST /api/v1/movements/transfer` | `product_variant_id` |
| `POST /api/v1/movements/return` | `product_variant_id` |
| `POST /api/v1/movements/write-off` | `product_variant_id` |
| `POST /api/v1/movements/loss` | `product_variant_id` |

---

### 6.4 Respuesta de movimiento

El recurso devuelto incluye los campos de la variante anidados:

```json
{
  "id": 101,
  "movement_type": "exit",
  "product_variant_id": 1,
  "warehouse_id": 1,
  "quantity": 20,
  "status": "confirmed",
  "variant_lab_brand": "BD Becton Dickinson",
  "batch_lot_number": "LOT-2026-001",
  "batch_expiration_date": "2028-12-31",
  "user_name": "Enfermera Jefa",
  "cost_center": { "id": 3, "code": "UCI", "name": "UCI", "type": "internal" },
  "medical_service": null,
  "created_at": "2026-07-06T14:30:00+00:00"
}
```

**Para obtener el nombre del producto:** el movimiento ahora no tiene un campo `product_name` directo. Para mostrarlo, usar el endpoint `GET /api/v1/movements/{id}` que carga la relación `variant.genericProduct` y devuelve el nombre del genérico.

---

## 7. Stock y disponibilidad

### Consultar stock de kit antes de salida

```
GET /api/v1/stock/kit-availability?kit_generic_id={id}&warehouse_id={id}
```

**Cambio:** antes era `kit_product_id`, ahora es `kit_generic_id`.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "generic_product_id": 3,
    "warehouse_id": 1,
    "available_kits": 3
  }
}
```

### Filtrar movimientos por variante

```
GET /api/v1/movements?product_variant_id={id}
```

**Cambio:** antes era `product_id`, ahora es `product_variant_id`.

---

## 8. Proveedores y variantes

La relación proveedor ↔ producto ahora es proveedor ↔ **variante**. Esto permite que el proveedor X suministre la variante "Nipro" y el proveedor Y suministre "BD", ambas del mismo genérico "Aguja 21G".

### Listar variantes de un proveedor

```
GET /api/v1/suppliers/{supplierId}/variants
```

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "lab_brand": "BD Becton Dickinson",
      "brand_sku": "BD-AGU-21G",
      "is_active": true,
      "generic": {
        "id": 1,
        "name": "Aguja 21G",
        "barcode": "000001",
        "category": { "id": 2, "name": "Insumos médicos", "code": "INS-MED" }
      },
      "pivot": {
        "supplier_sku": "STO-BD-21G",
        "lead_time_days": 7,
        "unit_price": 150000,
        "is_preferred": true,
        "product_presentation_id": null
      }
    }
  ]
}
```

### Asignar variante a proveedor

```
POST /api/v1/suppliers/{supplierId}/variants/{variantId}
```

```json
{
  "supplier_sku": "STO-BD-21G",
  "lead_time_days": 7,
  "unit_price": 150000,
  "is_preferred": true,
  "product_presentation_id": null
}
```

### Asignar todas las variantes de una categoría

```
POST /api/v1/suppliers/{supplierId}/variants/by-category
```

```json
{ "category_id": 2 }
```

### Ver proveedores de una variante

```
GET /api/v1/variants/{variantId}/suppliers
```

---

## 9. Registros sanitarios

Los registros sanitarios ahora pertenecen a la **variante** (cada marca tiene su propio registro INVIMA/sanitario).

```
GET    /api/v1/variants/{variantId}/sanitary-registrations
POST   /api/v1/variants/{variantId}/sanitary-registrations
PUT    /api/v1/variants/{variantId}/sanitary-registrations/{id}
DELETE /api/v1/variants/{variantId}/sanitary-registrations/{id}
```

**Respuesta de un registro:**
```json
{
  "id": 1,
  "product_variant_id": 1,
  "registration_number": "2024DM-0099999",
  "expiry_date": "2099-12-31",
  "is_active": true,
  "is_expired": false
}
```

---

## 10. Importación masiva de productos

```
POST /api/v1/import/products
Content-Type: multipart/form-data
```

La plantilla Excel ahora crea **un genérico + una variante por fila**. Los campos de la hoja:

| Columna | Obligatorio | Va a |
|---|---|---|
| `name` | Sí | Genérico |
| `category_code` | Sí | Genérico |
| `unit_abbreviation` | Sí | Genérico |
| `classification_code` | No | Genérico |
| `description` | No | Genérico |
| `requires_cold_chain` | No | Genérico |
| `reorder_point` | No | Genérico |
| `concentration` | No | Genérico |
| `pharmaceutical_form` | No | Genérico |
| `volume_cm3` | No | Genérico |
| `weight_kg` | No | Genérico |
| `lab_brand` | No | Variante |
| `sku` | No | Variante (`brand_sku`) |
| `risk_level` | No | Variante |
| `commercial_presentation` | No | Variante |
| `sanitary_registration` | No | Variante |

Descargar plantilla: `GET /api/v1/import/templates/products`

---

## 11. Importación de inventario inicial

La columna `product_barcode` (antes `product_code`) identifica el **genérico** por su barcode de 6 dígitos.

Descargar plantilla: `GET /api/v1/movements/initial-entries/template`

---

## 12. Permisos

Los permisos de `productos.*` fueron reemplazados:

| Permiso nuevo | Descripción |
|---|---|
| `generic-products.ver` | Ver listados, detalle, barcodes, clasificaciones, categorías, unidades |
| `generic-products.crear` | Crear genéricos, categorías, clasificaciones, unidades |
| `generic-products.editar` | Editar genéricos, gestionar presentaciones/kits/registros sanitarios |
| `generic-products.eliminar` | Eliminar genéricos, categorías, etc. |
| `generic-products.importar` | Importar desde Excel |
| `generic-products.barcode` | Ver SVG, páginas de impresión, buscar por escáner |
| `product-variants.ver` | Ver variantes |
| `product-variants.crear` | Crear variantes |
| `product-variants.editar` | Editar variantes |
| `product-variants.eliminar` | Eliminar variantes |

---

## 13. Flujos de pantalla sugeridos

### Pantalla de catálogo de productos

```
[Lista de genéricos]  → clic en fila  →  [Detalle del genérico]
                                              ├─ Pestaña "Variantes"
                                              │    └─ [Lista de variantes] + [Crear variante]
                                              ├─ Pestaña "Presentaciones"
                                              ├─ Pestaña "Componentes" (solo si es kit)
                                              └─ Botón "Imprimir etiqueta" → barcode/print
```

### Formulario de entrada de inventario

1. Seleccionar genérico (buscador o escáner de barcode).
2. Seleccionar **variante** del genérico (dropdown).
3. Completar número de lote, fecha de vencimiento, cantidad, almacén, ubicación.
4. `POST /api/v1/movements/entry` con `product_variant_id`.

### Formulario de salida de inventario

1. Seleccionar **genérico** (buscador o escáner de barcode) — no la variante.
2. El sistema aplica FEFO automáticamente.
3. Seleccionar almacén, cantidad, centro de costo.
4. `POST /api/v1/movements/exit` con `generic_product_id`.

### Salida de kit

1. Seleccionar genérico de tipo `kit`.
2. Consultar disponibilidad: `GET /api/v1/stock/kit-availability?kit_generic_id={id}&warehouse_id={id}`.
3. Mostrar `available_kits` al usuario.
4. `POST /api/v1/movements/exit` con `generic_product_id` del kit.

---

## 14. Errores frecuentes y cómo manejarlos

| Código | Causa probable | Mensaje de usuario sugerido |
|---|---|---|
| 422 `INSUFFICIENT_STOCK` | No hay stock del genérico en ese almacén | "Stock insuficiente para completar la salida" |
| 422 `EXPIRED_STOCK` | Solo hay lotes vencidos | "El stock disponible está vencido. Gestione los lotes antes de continuar." |
| 422 `REQUIRES_LAB_BRAND` | Clasificación requiere `lab_brand` y no se envió | "El laboratorio/marca es obligatorio para esta clasificación" |
| 409 | Nombre de genérico ya existe | "Ya existe un producto con ese nombre" |
| 404 | Genérico/variante no encontrado | "Producto no encontrado" |
