# Guía Frontend — Entradas de Inventario

> **Módulo:** Inventory · **Fecha:** Julio 2026 · **Versión:** v1 — campos de entrada

Cambios realizados en el backend sobre el flujo de registro de entradas de inventario. Se añadieron dos campos nuevos y se actualizó el proceso de importación masiva vía Excel.

---

## Qué cambió

Se añadieron dos campos opcionales a cada movimiento de tipo **entrada** (`entry`): el número de factura del proveedor y la temperatura registrada al momento de la recepción. Ambos campos están disponibles tanto en el formulario manual como en la carga masiva por Excel.

| Campo | Tipo | Aplica a | Descripción |
|---|---|---|---|
| `invoice_number` ⭐ | `string \| null` | Manual · Masivo | Número de factura del proveedor. Máx. 100 caracteres. |
| `entry_temperature` ⭐ | `number \| null` | Manual · Masivo | Temperatura de recepción en °C. Admite negativos y decimales (ej. `-18`, `4.5`). |

> Ambos campos son **opcionales (nullable)** en todos los endpoints. No se requiere ningún cambio en lógica de negocio del frontend — solo añadir los controles de formulario correspondientes y enviarlos cuando el usuario los llene.

---

## Endpoints

### 1. Registro manual de entrada

```
POST /api/v1/movements/entry
```

Registra una entrada de inventario individual. Se añadieron dos campos nuevos al body; el resto del contrato no cambia.

#### Campos del body

| Campo | Tipo | Estado | Descripción |
|---|---|---|---|
| `product_id` | `integer` | requerido | ID del producto. |
| `warehouse_id` | `integer` | requerido | ID del almacén destino. |
| `location_id` | `integer` | requerido | ID del estante/ubicación destino. |
| `lot_number` | `string` | requerido | Número de lote. Puede ser alfanumérico, solo letras o solo números. |
| `expiration_date` | `string (date)` | requerido | Fecha de vencimiento. Formato `YYYY-MM-DD`. |
| `manufacturing_date` | `string (date)` | opcional | Fecha de fabricación. Formato `YYYY-MM-DD`. |
| `quantity_base` | `integer` | opcional* | Cantidad en unidad base. Requerido si no se envía `product_presentation_id`. |
| `product_presentation_id` | `integer` | opcional* | ID de la presentación de compra. Requiere `quantity_in_presentation`. |
| `quantity_in_presentation` | `integer` | opcional* | Cantidad en la presentación indicada. |
| `notes` | `string` | opcional | Observaciones de la entrada. |
| `invoice_number` ⭐ | `string` | opcional | Número de factura del proveedor. Máx. 100 caracteres. |
| `entry_temperature` ⭐ | `number` | opcional | Temperatura de recepción en °C. Admite decimales y negativos. |

#### Ejemplo de request

```json
{
  "product_id":          42,
  "warehouse_id":        1,
  "location_id":         7,
  "lot_number":          "LOT-2025-0385",
  "expiration_date":     "2027-06-30",
  "manufacturing_date":  "2025-01-15",
  "quantity_base":       200,
  "invoice_number":      "FAC-2025-00892",
  "entry_temperature":   4.5,
  "notes":               "Recepción bodega norte"
}
```

#### Respuesta (201 Created) — campos nuevos

El objeto `data` ahora incluye los dos campos nuevos. El resto de la estructura no cambia.

```json
{
  "id":                 1089,
  "movement_type":      "entry",
  "quantity":           200,
  "reason":             "Recepción bodega norte",
  "invoice_number":     "FAC-2025-00892",
  "entry_temperature":  4.5,
  "created_at":         "2026-07-02T14:30:00+00:00"
}
```

---

### 2. Descarga de plantilla Excel

```
GET /api/v1/movements/initial-entries/template
```

Descarga la plantilla Excel con el formato actualizado. Ahora incluye las columnas `invoice_number` y `entry_temperature`. El query param `warehouse_id` sigue siendo opcional.

> ⚠️ **La plantilla cambió.** Si el frontend distribuye un archivo Excel estático pre-descargado, debe reemplazarlo. La plantilla descargada desde este endpoint siempre estará actualizada. También se actualizó el archivo preparado en `/Documentos/CDBJ/carga masiva/5 Entrdas.xlsx`.

#### Columnas de la plantilla actualizada

La hoja **Entradas** ahora tiene 8 columnas en este orden:

| Col | Nombre técnico | Obligatorio | Descripción |
|---|---|---|---|
| A | `product_code` | sí | Código del producto |
| B | `lot_number` | sí | Número de lote |
| C | `quantity` | sí | Cantidad en unidad base |
| D | `expiration_date` | sí | Fecha de vencimiento AAAA-MM-DD |
| E | `manufacturing_date` | no | Fecha de fabricación AAAA-MM-DD |
| **F** | **`invoice_number`** ⭐ | no | **N° factura del proveedor — nuevo** |
| **G** | **`entry_temperature`** ⭐ | no | **Temperatura de entrada en °C — nuevo** |
| H | `notes` | no | Notas u observaciones |

---

### 3. Carga masiva (Excel)

```
POST /api/v1/movements/initial-entries/import
Content-Type: multipart/form-data
```

Importa un archivo Excel con múltiples entradas de inventario. Devuelve un resumen con el total de filas procesadas, exitosas y fallidas.

#### Parámetros

| Campo | Tipo | Estado | Descripción |
|---|---|---|---|
| `file` | `file (.xlsx)` | requerido | Archivo Excel con la estructura de la plantilla. |
| `warehouse_id` | `integer` | opcional | ID del almacén destino. Si se omite, se usa el primer almacén activo del sistema. |

#### Estructura de la respuesta

```json
{
  "success": true,
  "message": "Importación de entradas iniciales finalizada",
  "data": {
    "total":   5,
    "success": 4,
    "failed":  1,
    "errors": [
      {
        "row": 4,
        "errors": {
          "product_code": ["No existe ningún producto con este código."]
        }
      }
    ]
  }
}
```

> 💡 `errors[].row` es el número de fila del Excel (la primera fila de datos es la fila 2). Muéstralo en el feedback al usuario para que pueda corregir el archivo.

---

### 4. Listado y detalle de movimientos

```
GET /api/v1/movements
GET /api/v1/movements/{id}
```

Los objetos de movimiento ahora incluyen los dos campos nuevos en la respuesta. El resto de filtros, paginación y estructura no cambia.

| Campo | Tipo | Valor si no aplica |
|---|---|---|
| `invoice_number` ⭐ | `string \| null` | `null` si no se registró, o si el movimiento es de otro tipo (salida, traslado, etc.). |
| `entry_temperature` ⭐ | `number \| null` | `null` si no se registró. |

---

## Formato del archivo Excel

La hoja activa del archivo siempre debe llamarse **Entradas** y tener la fila 1 con los nombres técnicos de columna en minúsculas. El **orden de las columnas no importa** — el backend las identifica por nombre, no por posición.

> 📋 La fila 2 de la plantilla descargada es una fila de ejemplo en español (fondo gris). El usuario debe borrarla antes de subir el archivo. Si no la borra, esa fila fallará en la validación pero **no afecta al resto de las filas**.

---

## Tipos de datos en Excel

Excel puede interpretar celdas como **número** aunque el usuario haya escrito un código o número de lote compuesto solo de dígitos. El backend normaliza estos casos automáticamente para los campos de texto.

> ⚠️ Si el usuario escribe `20250603` en la celda de `lot_number`, Excel lo puede guardar como entero. El backend lo convierte a string `"20250603"` antes de validarlo. Lo mismo aplica a `invoice_number` y `notes`. Se recomienda guiar al usuario a formatear esas celdas como **Texto** en Excel para evitar confusiones.

### Regla de conversión por campo

| Campo | Si Excel entrega número | Resultado |
|---|---|---|
| `lot_number` | Entero (ej. `20250603`) | Se castea a string → `"20250603"` ✅ |
| `invoice_number` | Entero o decimal | Se castea a string ✅ |
| `product_code` | Entero (poco probable) | Se castea a string ✅ |
| `notes` | Número | Se castea a string ✅ |
| `expiration_date` | Serial de Excel (ej. `46387`) | Se convierte a `YYYY-MM-DD` ✅ |
| `quantity` | Número | Se deja como número ✅ |
| `entry_temperature` | Número / negativo | Se deja como número ✅ |

---

## Stock y lotes vencidos

> ⚠️ **Lotes con fecha de vencimiento pasada no se contabilizan en el stock disponible.** La importación procesa el lote sin errores, pero el `stock_summary` solo suma lotes cuya `expiration_date` sea mayor o igual a hoy. Si el usuario carga un lote ya vencido, el backend lo acepta pero no aparece en el conteo de disponibles.

Este es el comportamiento intencional del sistema.

### Recomendación UX

Tras una importación exitosa donde `failed === 0`, si el stock no refleja el total esperado, mostrar al usuario un mensaje explicativo:

> *"Algunos lotes pueden estar excluidos del stock disponible por tener fecha de vencimiento anterior a hoy."*
