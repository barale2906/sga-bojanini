# Guía Frontend — Carga masiva de inventario inicial

## Contexto

Esta funcionalidad permite cargar de una sola vez, vía Excel, el inventario inicial
(entradas) del sistema. El **almacén** destino lo elige el usuario al importar
(`warehouse_id`, opcional); la **zona y el estante** dentro de ese almacén siempre
se calculan automáticamente en el backend — el frontend nunca pide zona ni estante.

- Si no se envía `warehouse_id`, se usa el **primer almacén activo** registrado en
  el sistema.
- Dentro del almacén elegido (o del que se use por defecto), las entradas van a su
  **primera zona activa**.
- Si un producto tiene marcado **"Requiere cadena de frío"**, su entrada se dirige a
  la zona de tipo `cold` del almacén; si no existe ninguna, el backend la crea
  automáticamente (`Zona Refrigerada`).
- Cada entrada se ubica en un estante (location) con espacio disponible. Cada
  estante tiene capacidad de **3 m³ y 500 kg**; si ninguno de los existentes tiene
  espacio, el backend crea uno nuevo con esa misma capacidad. Si la cantidad de una
  sola fila excede la capacidad de un estante completo, esa fila se reporta como
  fallida (no se reparte automáticamente entre varios estantes).

Cada fila importada registra una entrada real (`movement_type = entry`), exactamente
igual que `POST /api/v1/movements/entry` — no es un borrador ni requiere aprobación
posterior.

## 1. Permisos

| Acción | Permiso |
|---|---|
| Descargar la plantilla | `stock.ver` |
| Importar el archivo | `movimientos.importar` |

Por defecto `movimientos.importar` lo tienen `super_administrador`,
`administrador` y `jefe_almacen` (no `operador_almacen` ni `personal_medico`).
Usa `GET /api/v1/auth/me` (campo `permissions`) para mostrar/ocultar la opción de
carga masiva en el menú de Movimientos.

## 2. Endpoints

### 2.1 Descargar plantilla

```
GET /api/v1/movements/initial-entries/template
GET /api/v1/movements/initial-entries/template?warehouse_id=3
Permiso: stock.ver
```

`warehouse_id` es **opcional**: si se envía, la hoja "Almacén y zona destino" (ver
abajo) muestra la zona de ese almacén en concreto; si se omite, muestra la del
primer almacén activo (el que se usaría por defecto al importar).

Devuelve un archivo `.xlsx` (`initial_entries_template.xlsx`) con 5 hojas:

- **Entradas** — la hoja que se diligencia y se vuelve a subir. Fila 1 = encabezado
  técnico (no traducir), fila 2 = ayuda en español (**debe borrarse** antes de
  subir el archivo; si no se borra, se reporta como 1 fila fallida sin afectar a
  las demás), fila 3 = ejemplo.
- **Instrucciones** — detalle de cada columna y reglas de negocio.
- **Productos** — catálogo de referencia (`code`, `name`, `requires_cold_chain`,
  `base_unit`) para que el usuario sepa qué código escribir y si el producto
  requiere frío.
- **Almacenes** — catálogo de referencia (`id`, `code`, `name`) con los almacenes
  activos válidos para enviar como `warehouse_id`.
- **Almacén y zona destino** — informativa: muestra a qué almacén/zona se
  cargarían las entradas en este momento (con el `warehouse_id` indicado, o el
  almacén por defecto si no se indicó ninguno).

Columnas de la hoja **Entradas**:

| Columna | Obligatorio | Tipo | Notas |
|---|---|---|---|
| `product_code` | Sí | Texto | Debe existir en `products.code`. |
| `lot_number` | Sí | Texto (máx. 100) | Si ya existe un lote con ese número para el mismo producto, se suma la cantidad. |
| `quantity` | Sí | Entero ≥ 1 | Siempre en unidad base del producto (no admite presentaciones de compra). |
| `expiration_date` | Sí | Fecha `AAAA-MM-DD` | |
| `manufacturing_date` | No | Fecha `AAAA-MM-DD` | |
| `notes` | No | Texto | |

### 2.2 Importar el archivo

```
POST /api/v1/movements/initial-entries/import
Permiso: movimientos.importar
Content-Type: multipart/form-data
```

Body:
- `file` (obligatorio) — archivo `.xlsx`/`.xls`/`.csv`, máx. 10 MB.
- `warehouse_id` (opcional, entero) — ID de un almacén activo (ver hoja
  "Almacenes" de la plantilla). Si se omite, se usa el primer almacén activo del
  sistema.

Respuesta `200`:
```json
{
  "success": true,
  "message": "Importación de entradas iniciales finalizada",
  "data": {
    "total": 3,
    "success": 2,
    "failed": 1,
    "errors": [
      {
        "row": 4,
        "errors": {
          "product_code": ["No existe ningún producto con este código. Revise la hoja \"Productos\" de la plantilla."]
        }
      }
    ]
  }
}
```

- `row` es el número de fila **dentro del archivo Excel** (la fila 2, en español,
  cuenta como dato si no se borró).
- `errors` es un objeto de validación estándar de Laravel (clave = nombre de
  columna, valor = lista de mensajes) **o**, si la fila falló por una regla de
  negocio (producto kit, capacidad de estante excedida, etc.), un objeto
  `{"exception": ["mensaje legible"]}`.
- La importación es resiliente: una fila con error no detiene el procesamiento de
  las demás.

## 3. Pantalla sugerida

1. Selector de **almacén** (opcional): poblar con `GET /api/v1/warehouses` (ya
   filtrado por los almacenes a los que el usuario tiene acceso — ver
   `frontend-warehouse-access.md`). Si el usuario no elige ninguno, no enviar
   `warehouse_id` y se usará el almacén por defecto.
2. Botón **"Descargar plantilla"** → `GET .../initial-entries/template` (incluyendo
   `?warehouse_id=` si ya hay uno seleccionado en el paso 1, para que la hoja
   "Almacén y zona destino" sea precisa) y disparar la descarga del blob recibido.
3. Selector de archivo + botón **"Importar"** → `POST .../initial-entries/import`
   con `FormData` (`file` y, si aplica, `warehouse_id`).
4. Mostrar el resumen de la respuesta:
   - `total` / `success` / `failed` como contadores (ej. tarjetas o badges).
   - Si `failed > 0`, una tabla con `errors` (columna "Fila" + columna "Detalle",
     uniendo los mensajes de cada campo).
5. No es necesario pedir zona ni estante en el formulario — el backend siempre los
   resuelve solo dentro del almacén elegido.

## 4. Manejo de errores y casos límite

| Caso | Respuesta |
|---|---|
| Usuario sin `movimientos.importar` | `403` (middleware de permiso) |
| Usuario sin acceso al almacén destino (elegido o por defecto) | `403` `{"message": "No tienes acceso al almacén indicado."}` |
| `warehouse_id` enviado no existe o pertenece a un almacén inactivo | `422` de validación estándar (`exists:warehouses,id`) |
| No se envía `warehouse_id` y no hay ningún almacén activo registrado | `409` `{"message": "No hay almacenes registrados en el sistema..."}` — se detecta antes de leer el archivo, la importación completa no se ejecuta |
| El almacén destino no tiene ninguna zona activa | La importación sí corre, pero **todas** las filas quedan `failed` con el mensaje `"El almacén destino no tiene zonas registradas..."` en `errors` (se detecta fila por fila, no detiene el archivo) |
| Archivo sin la columna `product_code` esperada o vacío | Cada fila aparece como fallida con error de validación estándar |
| Fila con cantidad que excede la capacidad de un estante completo (3 m³ / 500 kg) | Fila fallida con mensaje pidiendo dividir la cantidad en varias filas |
| Producto de tipo `kit` | Fila fallida: "No se pueden registrar entradas directas de productos tipo kit." |

## 5. Notas adicionales

- Cada importación queda registrada en el log de auditoría de importaciones
  (`entity_type = "initial_entries"`), igual que las importaciones de productos y
  proveedores.
- Tras importar, las pantallas de **Stock**, **Lotes** y **Movimientos** ya
  reflejarán los datos cargados sin necesidad de ninguna acción adicional — no hay
  caché que invalidar del lado del backend.
