# Plan: Jerarquía Genérico/Variante y Códigos de Barras Internos

**Fecha:** 2026-07-05  
**Módulo afectado:** Catalog, Inventory  
**Motivación:** El modelo actual trata cada marca de un mismo producto como un registro independiente, haciendo imposible aplicar FEFO entre marcas intercambiables. Se necesita además un sistema de códigos de barras internos para la operación física del almacén.

---

## 1. Problema actual y causa raíz

La tabla `products` mezcla dos conceptos distintos:

| Campo actual | Concepto real |
|---|---|
| `name`, `concentration`, `pharmaceutical_form`, `category_id`, `base_unit_id`, `reorder_point`, `min_stock`, `max_stock` | **Producto Genérico** — el concepto clínico/logístico |
| `lab_brand`, `sku`, `commercial_presentation`, `serie_reference`, `useful_life`, `risk_level` | **Variante comercial** — la instancia física de un proveedor/marca |

La entidad `Batch` referencia `product_id`. Si "Acetaminofén 500mg" existe como dos registros (Genfar y Lafrancol), sus lotes viven en silos separados y el FEFO no puede cruzarlos.

---

## 2. Modelo de datos propuesto

### 2.1 Tabla `product_generics` (nueva — reemplaza semánticamente a `products`)

```
product_generics
├── id
├── category_id                FK → categories
├── classification_id          FK → product_classifications (nullable)
├── base_unit_id               FK → units_of_measure
├── product_type               enum('simple', 'kit')
├── name                       varchar(255)   — nombre genérico clínico
├── barcode                    char(6) UNIQUE — número secuencial 000001…999999
├── description                text (nullable)
├── concentration              varchar(100) (nullable)
├── pharmaceutical_form        varchar(150) (nullable)
├── volume_cm3                 decimal (nullable)
├── weight_kg                  decimal (nullable)
├── requires_cold_chain        boolean
├── reorder_point              unsignedInteger
├── reorder_quantity           unsignedInteger
├── min_stock                  unsignedInteger
├── max_stock                  unsignedInteger
├── is_active                  boolean
├── timestamps
└── softDeletes
```

**Nota:** `sku` desaparece de este nivel. `internal_code` es el identificador interno del sistema; `barcode` es el valor que se imprime y escanea (puede ser igual al internal_code o derivado de él).

### 2.2 Tabla `product_variants` (nueva — absorbe los campos de marca)

```
product_variants
├── id
├── generic_product_id         FK → product_generics
├── lab_brand                  varchar(255)         — marca/laboratorio
├── brand_sku                  varchar(100) (nullable) — SKU del proveedor
├── commercial_presentation    varchar(150) (nullable)
├── serie_reference            varchar(150) (nullable)  — dispositivos médicos
├── useful_life                varchar(100) (nullable)  — dispositivos médicos
├── risk_level                 varchar(100) (nullable)  — dispositivos médicos
├── is_active                  boolean
├── timestamps
└── softDeletes
```

### 2.3 Productos de una sola marca

Cuando un producto tiene exclusivamente un laboratorio/proveedor (no existe equivalente de otra marca), el modelo aplica exactamente igual: se crea un `GenericProduct` con una única `ProductVariant`. No hay caso especial en el código ni en la lógica de FEFO. Si en el futuro aparece otro laboratorio, se añade una segunda variante sin modificar el genérico.

### 2.5 Tabla `product_variant_supplier` (pivote existente adaptada)

La tabla `product_supplier` actual se convierte en `product_variant_supplier`:

```
product_variant_supplier
├── product_variant_id         FK → product_variants
├── supplier_id                FK → suppliers
└── (campos de precio/referencia que ya existan)
```

### 2.6 Tabla `batches` — ajuste de FK

El campo `product_id` pasa a llamarse `product_variant_id`:

```
batches
├── product_variant_id         FK → product_variants  ← antes: product_id → products
└── (resto igual)
```

### 2.7 Impacto en `product_sanitary_registrations`

El registro sanitario pertenece a la variante comercial (es el INVIMA de un laboratorio específico). FK cambia de `product_id` → `product_variant_id`.

### 2.8 Impacto en `product_kit_components`

Los kits se arman de genéricos (el sistema elegirá la variante con mejor FEFO en el momento del despacho). FK cambia de `product_id` → `generic_product_id`.

---

## 3. FEFO multi-variante

Con la nueva estructura, el algoritmo de despacho funciona así:

```
Buscar por generic_product_id
  → Obtener todas las variantes activas del genérico
    → Obtener todos los lotes activos de todas las variantes
      → Ordenar por expiration_date ASC
        → Deducir de los más próximos a vencer primero
```

El `RegisterExitUseCase` y `RegisterKitExitUseCase` deben recibir `generic_product_id` en lugar de `product_id`. El servicio de FEFO resuelve internamente qué variante y qué lote se consume.

---

## 4. Sistema de códigos de barras internos

### 4.1 Generación del código

- **Formato del valor:** número secuencial de exactamente 6 dígitos, siempre con cero-relleno a la izquierda.  
  Ejemplos: `000001`, `000123`, `004782`, `999999`.
- **Rango:** 1 a 999 999 (suficiente para cualquier catálogo real).
- **Tipo de código de barras:** Code128 (numérico, compacto, lectura universal con cualquier scanner).
- **Cuándo se genera:** Al crear el `ProductGeneric`, el sistema toma `MAX(barcode) + 1` de la tabla (o `000001` si está vacía), formatea a 6 dígitos con `str_pad` y lo almacena. La generación ocurre dentro de una transacción para evitar duplicados.
- **Biblioteca PHP:** `picqer/php-barcode-generator` (composer, sin dependencias de imagen GD/Imagick).

### 4.2 Endpoints requeridos

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/generic-products/{id}/barcode` | Devuelve el SVG del código de barras |
| `GET` | `/api/generic-products/{id}/barcode/print` | Devuelve HTML imprimible con etiqueta |
| `GET` | `/api/generic-products/barcode/{value}` | Busca un genérico por valor de barcode (para scanner) |
| `POST` | `/api/generic-products/{id}/barcode/regenerate` | Regenera el barcode (raro, pero puede necesitarse) |

### 4.3 Contenido de la etiqueta imprimible

```
┌────────────────────────────────┐
│  [BARCODE IMAGE — Code128]     │
│           000123               │
│  Acetaminofén 500mg Tableta    │
│  Tableta    |  Analgésicos     │
└────────────────────────────────┘
```

El endpoint `/barcode/print` devuelve HTML con estilos CSS `@media print` para imprimir directamente desde el navegador. No se requiere PDF por ahora. El HTML incluye la imagen SVG del código embebida (no depende de rutas externas).

### 4.4 Lectura por scanner

El endpoint `GET /barcode/{value}` permite que el frontend pase el valor escaneado y obtenga el `ProductGeneric` con sus variantes y stock consolidado. Este es el punto de integración para despachos rápidos por scanner. **El scanner solo busca — no despacha directamente.** La confirmación del despacho sigue el flujo normal de salida.

### 4.5 Listado imprimible de códigos de barras

Para validar y auditar físicamente el catálogo, se añade un endpoint de listado:

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/generic-products/barcodes/list` | HTML imprimible con tabla nombre genérico + código de barras |

La tabla contiene **únicamente dos columnas: nombre genérico y código de barras.** No incluye marcas, laboratorios ni ningún otro campo.

El HTML usa `@media print` para suprimir navegación y ajustar márgenes. Acepta los parámetros opcionales `?category_id=` y `?active=1` para filtrar el listado. El `BarcodeController` delega la generación al `BarcodeService` igual que los demás endpoints de impresión.

---

## 5. Capa de dominio — cambios en Catalog

### Entidades

| Antes | Después |
|---|---|
| `Product` | `GenericProduct` |
| — | `ProductVariant` (nueva) |

### Repositorios

| Antes | Después |
|---|---|
| `ProductRepositoryInterface` | `GenericProductRepositoryInterface` |
| — | `ProductVariantRepositoryInterface` (nuevo) |

### Servicios de dominio

- `BarcodeValueGenerator` (Value Object / Domain Service): genera el string del barcode dado un ID.
- `FefoResolver` (Domain Service): dada una `generic_product_id` y cantidad, resuelve qué variante/lote consumir.

### DTOs

| Antes | Después |
|---|---|
| `ProductData` | `GenericProductData` |
| — | `ProductVariantData` (nuevo) |

---

## 6. Capa de dominio — cambios en Inventory

- `Batch::$productId` → `Batch::$productVariantId`
- `StockSummary` necesita poder agregarse por `generic_product_id` (suma de stock de todas las variantes).
- `RegisterExitUseCase` recibe `generic_product_id` y delega la resolución de variante/lote al `FefoResolver`.
- `StockCalculator` y `BatchLocationService` operan a nivel de variante (no cambian su lógica interna, solo el punto de entrada).

---

## 7. Capa de infraestructura — BarcodeService

Ubicación: `app/Modules/Catalog/Infrastructure/Services/BarcodeService.php`

Responsabilidades:
- `generateValue(): string` — obtiene `MAX(barcode)+1` y formatea a 6 dígitos (`str_pad($next, 6, '0', STR_PAD_LEFT)`), dentro de una transacción.
- `renderSvg(string $value): string` — devuelve SVG embebible.
- `renderPrintableHtml(GenericProduct $product): string` — devuelve HTML de etiqueta.

El `BarcodeController` es thin y delega todo al `BarcodeService`.

---

## 8. Migración del esquema existente

Dado que el sistema está en **fase de diseño** (no hay datos en producción), el enfoque es:

1. **Editar** la migración `create_products_table` para convertirla en `create_product_generics_table`.
2. **Editar** la migración `add_presentation_fields_to_products_table` para incorporar sus campos directamente en la migración base del genérico.
3. **Crear** nueva migración `create_product_variants_table`.
4. **Editar** `create_product_sanitary_registrations_table` para referenciar `product_variants`.
5. **Editar** `create_product_kit_components_table` para referenciar `product_generics`.
6. **Editar** `create_product_supplier_table` para referenciar `product_variants`.
7. **Editar** la migración de `batches` para renombrar `product_id` → `product_variant_id`.
8. Añadir columna `barcode` en la migración del genérico.

---

## 9. Impacto en importación Excel

El template de importación de productos necesita una nueva columna `lab_brand` como identificador de la variante. El `ProductImportService` se bifurca:

1. Buscar/crear el `GenericProduct` por nombre + concentración + forma farmacéutica.
2. Buscar/crear la `ProductVariant` por `generic_product_id` + `lab_brand`.
3. El `brand_sku` (antes `sku`) va en la variante.

El template de inventario inicial (`InitialEntryTemplateBuilder`) también necesita incluir `lab_brand` para identificar la variante.

---

## 10. Permisos (Spatie)

Se añaden permisos nuevos para el nuevo recurso:

```
generic-products.index
generic-products.show
generic-products.create
generic-products.update
generic-products.delete
generic-products.barcode
product-variants.index
product-variants.show
product-variants.create
product-variants.update
product-variants.delete
```

---

## 11. Fases de implementación

### Fase 1 — Base de datos y dominio (sin romper Inventory)
1. Editar migraciones según §8.
2. Crear entidades `GenericProduct` y `ProductVariant`.
3. Crear repositorios e interfaces.
4. Crear `BarcodeValueGenerator` (domain service).
5. Actualizar seeders y `TestProductsSeeder`.

### Fase 2 — Casos de uso de Catalog
6. `CreateGenericProductUseCase` (incluye generación de barcode).
7. `UpdateGenericProductUseCase`.
8. `DeleteGenericProductUseCase`.
9. `CreateProductVariantUseCase`.
10. `UpdateProductVariantUseCase`.
11. `DeleteProductVariantUseCase`.
12. Adaptar `AttachPresentationToProductUseCase` al nuevo modelo.

### Fase 3 — Infraestructura de Catalog
13. `BarcodeService` con `picqer/php-barcode-generator`.
14. `GenericProductController` (CRUD + barcode endpoints).
15. `ProductVariantController` (CRUD).
16. Rutas y permisos.
17. Resources / DTOs de respuesta.

### Fase 4 — Ajuste de Inventory
18. `Batch` entity: `productVariantId`.
19. `FefoResolver` domain service.
20. `RegisterExitUseCase` recibe `generic_product_id`.
21. `StockSummary` agrega por genérico.
22. Ajustar `RegisterEntryUseCase` para recibir `product_variant_id`.

### Fase 5 — Importación y tests
23. Actualizar `ProductImportService`.
24. Actualizar `InitialEntryTemplateBuilder`.
25. Actualizar/crear tests unitarios y de feature.
26. Actualizar seeders de permisos.

---

## 12. Preguntas resueltas

1. ~~**¿El `internal_code` y `barcode` son el mismo valor?**~~ **Resuelto:** campo único `barcode` de 6 dígitos. `internal_code` eliminado.
2. ~~**¿Se necesitan múltiples etiquetas en una impresión?**~~ **Resuelto:** No. El endpoint de etiqueta individual imprime una sola etiqueta. No se añade parámetro `?qty`.
3. ~~**¿El scanner va a despachar directamente o solo buscar?**~~ **Resuelto:** Solo busca, tal como está definido en §4.4. El despacho sigue el flujo normal.
4. ~~**¿`product_type` (kit/simple) vive en el genérico o en la variante?**~~ **Resuelto:** En el genérico (`product_generics.product_type`).
5. ~~**¿Las presentaciones colapsan en `commercial_presentation` de la variante?**~~ **Resuelto:** Las presentaciones se aplican al genérico. La tabla `product_presentations` referencia `generic_product_id`.
