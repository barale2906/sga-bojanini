# 04 · Catálogo maestro

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **68**

## `GET /api/v1/product-classifications`

**Permiso requerido:** `generic-products.ver`

**Query params detectados:** `is_active`, `search`

**Campos de respuesta (`ProductClassificationResource`):** `id`, `code`, `name`, `description`, `has_sanitary_registration`, `has_concentration`, `has_risk_level`, `has_pharma_fields`, `has_device_fields`, `has_lab_brand`, `is_active`

---

## `GET /api/v1/product-classifications/{id}`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ProductClassificationResource`):** `id`, `code`, `name`, `description`, `has_sanitary_registration`, `has_concentration`, `has_risk_level`, `has_pharma_fields`, `has_device_fields`, `has_lab_brand`, `is_active`

---

## `POST /api/v1/product-classifications`

**Permiso requerido:** `generic-products.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `code` | sí | `string`, `max:20`, `unique:product_classifications,code` |
| `name` | sí | `string`, `max:100` |
| `description` | no | `string`, `nullable` |
| `has_sanitary_registration` | no | `boolean`, `nullable` |
| `has_concentration` | no | `boolean`, `nullable` |
| `has_risk_level` | no | `boolean`, `nullable` |
| `has_pharma_fields` | no | `boolean`, `nullable` |
| `has_device_fields` | no | `boolean`, `nullable` |
| `has_lab_brand` | no | `boolean`, `nullable` |

**Campos de respuesta (`ProductClassificationResource`):** `id`, `code`, `name`, `description`, `has_sanitary_registration`, `has_concentration`, `has_risk_level`, `has_pharma_fields`, `has_device_fields`, `has_lab_brand`, `is_active`

---

## `PUT /api/v1/product-classifications/{id}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `code` | sí | `string`, `max:20`, `unique:product_classifications,code` |
| `name` | sí | `string`, `max:100` |
| `description` | no | `string`, `nullable` |
| `has_sanitary_registration` | no | `boolean`, `nullable` |
| `has_concentration` | no | `boolean`, `nullable` |
| `has_risk_level` | no | `boolean`, `nullable` |
| `has_pharma_fields` | no | `boolean`, `nullable` |
| `has_device_fields` | no | `boolean`, `nullable` |
| `has_lab_brand` | no | `boolean`, `nullable` |

**Campos de respuesta (`ProductClassificationResource`):** `id`, `code`, `name`, `description`, `has_sanitary_registration`, `has_concentration`, `has_risk_level`, `has_pharma_fields`, `has_device_fields`, `has_lab_brand`, `is_active`

---

## `DELETE /api/v1/product-classifications/{id}`

**Permiso requerido:** `generic-products.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/categories`

**Permiso requerido:** `generic-products.ver`

**Query params detectados:** `parent_id`, `is_active`, `search`

**Campos de respuesta (`CategoryResource`):** `id`, `parent_id`, `name`, `code`, `description`, `is_active`

---

## `GET /api/v1/categories/{id}`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`CategoryResource`):** `id`, `parent_id`, `name`, `code`, `description`, `is_active`

---

## `POST /api/v1/categories`

**Permiso requerido:** `generic-products.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `parent_id` | no | `integer`, `exists:categories,id`, `nullable` |
| `name` | sí | `string`, `max:255` |
| `code` | sí | `string`, `max:50`, `unique:categories,code` |
| `description` | no | `string`, `nullable` |

**Campos de respuesta (`CategoryResource`):** `id`, `parent_id`, `name`, `code`, `description`, `is_active`

---

## `PUT /api/v1/categories/{id}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `parent_id` | no | `integer`, `exists:categories,id`, `nullable` |
| `name` | sí | `string`, `max:255` |
| `code` | sí | `string`, `max:50`, `unique:categories,code` |
| `description` | no | `string`, `nullable` |

**Campos de respuesta (`CategoryResource`):** `id`, `parent_id`, `name`, `code`, `description`, `is_active`

---

## `DELETE /api/v1/categories/{id}`

**Permiso requerido:** `generic-products.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/categories-tree`

**Permiso requerido:** `generic-products.ver`

---

## `GET /api/v1/units-of-measure`

**Permiso requerido:** `generic-products.ver`

**Query params detectados:** `is_active`, `is_base`, `search`

**Campos de respuesta (`UnitOfMeasureResource`):** `id`, `name`, `abbreviation`, `is_active`, `is_base`

---

## `GET /api/v1/units-of-measure/{id}`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`UnitOfMeasureResource`):** `id`, `name`, `abbreviation`, `is_active`, `is_base`

---

## `POST /api/v1/units-of-measure`

**Permiso requerido:** `generic-products.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:100` |
| `abbreviation` | sí | `string`, `max:10`, `unique:units_of_measure,abbreviation` |
| `is_base` | no | `boolean`, `nullable` |

**Campos de respuesta (`UnitOfMeasureResource`):** `id`, `name`, `abbreviation`, `is_active`, `is_base`

---

## `PUT /api/v1/units-of-measure/{id}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:100` |
| `abbreviation` | sí | `string`, `max:10`, `unique:units_of_measure,abbreviation` |
| `is_base` | no | `boolean`, `nullable` |

**Campos de respuesta (`UnitOfMeasureResource`):** `id`, `name`, `abbreviation`, `is_active`, `is_base`

---

## `DELETE /api/v1/units-of-measure/{id}`

**Permiso requerido:** `generic-products.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/generic-products`

**Permiso requerido:** `generic-products.ver`

**Query params detectados:** `category_id`, `product_type`, `is_active`, `search`

**Campos de respuesta (`GenericProductResource`):** `id`, `category_id`, `classification_id`, `base_unit_id`, `product_type`, `name`, `barcode`, `description`, `concentration`, `pharmaceutical_form`, `volume_cm3`, `weight_kg`, `requires_cold_chain`, `reorder_point`, `reorder_quantity`, `min_stock`, `max_stock`, `is_active`

---

## `POST /api/v1/generic-products`

**Permiso requerido:** `generic-products.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `category_id` | sí | `integer`, `exists:categories,id` |
| `classification_id` | no | `integer`, `exists:product_classifications,id`, `nullable` |
| `base_unit_id` | sí | `integer`, `exists:units_of_measure,id` |
| `product_type` | no | `string`, `in:simple,kit`, `nullable` |
| `components` | condicional | `array`, `required_if:product_type,kit`, `nullable` |
| `components.*.component_generic_id` | condicional | `required_with:components`, `integer`, `exists:product_generics,id` |
| `components.*.quantity_per_kit` | condicional | `required_with:components`, `integer`, `min:1` |
| `name` | sí | `string`, `max:255` |
| `description` | no | `string`, `nullable` |
| `concentration` | no | `string`, `max:100`, `nullable` |
| `pharmaceutical_form` | no | `string`, `max:150`, `nullable` |
| `volume_cm3` | no | `numeric`, `min:0`, `nullable` |
| `weight_kg` | no | `numeric`, `min:0`, `nullable` |
| `requires_cold_chain` | no | `boolean`, `nullable` |
| `reorder_point` | no | `integer`, `min:0`, `nullable` |
| `reorder_quantity` | no | `integer`, `min:0`, `nullable` |
| `min_stock` | no | `integer`, `min:0`, `nullable` |
| `max_stock` | no | `integer`, `min:0`, `nullable` |

**Campos de respuesta (`GenericProductResource`):** `id`, `category_id`, `classification_id`, `base_unit_id`, `product_type`, `name`, `barcode`, `description`, `concentration`, `pharmaceutical_form`, `volume_cm3`, `weight_kg`, `requires_cold_chain`, `reorder_point`, `reorder_quantity`, `min_stock`, `max_stock`, `is_active`

---

## `GET /api/v1/generic-products/{id}`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`GenericProductResource`):** `id`, `category_id`, `classification_id`, `base_unit_id`, `product_type`, `name`, `barcode`, `description`, `concentration`, `pharmaceutical_form`, `volume_cm3`, `weight_kg`, `requires_cold_chain`, `reorder_point`, `reorder_quantity`, `min_stock`, `max_stock`, `is_active`

---

## `PUT /api/v1/generic-products/{id}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `category_id` | sí | `integer`, `exists:categories,id` |
| `classification_id` | no | `integer`, `exists:product_classifications,id`, `nullable` |
| `base_unit_id` | sí | `integer`, `exists:units_of_measure,id` |
| `product_type` | no | `string`, `in:simple,kit`, `nullable` |
| `name` | sí | `string`, `max:255` |
| `description` | no | `string`, `nullable` |
| `concentration` | no | `string`, `max:100`, `nullable` |
| `pharmaceutical_form` | no | `string`, `max:150`, `nullable` |
| `volume_cm3` | no | `numeric`, `min:0`, `nullable` |
| `weight_kg` | no | `numeric`, `min:0`, `nullable` |
| `requires_cold_chain` | no | `boolean`, `nullable` |
| `reorder_point` | no | `integer`, `min:0`, `nullable` |
| `reorder_quantity` | no | `integer`, `min:0`, `nullable` |
| `min_stock` | no | `integer`, `min:0`, `nullable` |
| `max_stock` | no | `integer`, `min:0`, `nullable` |

**Campos de respuesta (`GenericProductResource`):** `id`, `category_id`, `classification_id`, `base_unit_id`, `product_type`, `name`, `barcode`, `description`, `concentration`, `pharmaceutical_form`, `volume_cm3`, `weight_kg`, `requires_cold_chain`, `reorder_point`, `reorder_quantity`, `min_stock`, `max_stock`, `is_active`

---

## `DELETE /api/v1/generic-products/{id}`

**Permiso requerido:** `generic-products.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/generic-products/{id}/batches`

Lista los lotes de un producto genérico (todos sus variantes) ordenados FEFO.
Acepta los mismos filtros que byProduct: available_for_exit y warehouse_id.

**Permiso requerido:** `lotes.ver`

**Parámetros de ruta:** `id`

**Query params detectados:** `available_for_exit`, `warehouse_id`

**Campos de respuesta (`BatchResource`):** `id`, `product_variant_id`, `lot_number`, `expiration_date`, `manufacturing_date`, `quantity_received`, `quantity_available`, `status`, `days_until_expiry`, `notes`, `received_at`

---

## `GET /api/v1/generic-products/{id}/batches/other-warehouses-expiring`

Saldos de un producto genérico en OTROS almacenes que vencen pronto.

**Permiso requerido:** `lotes.ver`

**Parámetros de ruta:** `id`

**Query params detectados:** `exclude_warehouse_id`

---

## `GET /api/v1/generic-products/{id}/kit-availability`

**Permiso requerido:** `stock.ver`

**Parámetros de ruta:** `id`

**Query params detectados:** `warehouse_id`

---

## `GET /api/v1/generic-products/barcode/{value}`

**Permiso requerido:** `generic-products.barcode`

**Parámetros de ruta:** `value`

**Campos de respuesta (`GenericProductResource`):** `id`, `category_id`, `classification_id`, `base_unit_id`, `product_type`, `name`, `barcode`, `description`, `concentration`, `pharmaceutical_form`, `volume_cm3`, `weight_kg`, `requires_cold_chain`, `reorder_point`, `reorder_quantity`, `min_stock`, `max_stock`, `is_active`

---

## `GET /api/v1/generic-products/barcodes/list`

**Permiso requerido:** `generic-products.barcode`

**Query params detectados:** `active`, `category_id`

**Tipo de respuesta:** SVG/HTML

---

## `GET /api/v1/generic-products/{id}/barcode`

**Permiso requerido:** `generic-products.barcode`

**Parámetros de ruta:** `id`

**Tipo de respuesta:** SVG/HTML

---

## `GET /api/v1/generic-products/{id}/barcode/print`

**Permiso requerido:** `generic-products.barcode`

**Parámetros de ruta:** `id`

**Tipo de respuesta:** SVG/HTML

---

## `GET /api/v1/generic-products/{genericId}/variants`

**Permiso requerido:** `product-variants.ver`

**Parámetros de ruta:** `genericId`

**Campos de respuesta (`ProductVariantResource`):** `id`, `generic_product_id`, `lab_brand`, `brand_sku`, `commercial_presentation`, `serie_reference`, `useful_life`, `risk_level`, `is_active`

---

## `POST /api/v1/generic-products/{genericId}/variants`

**Permiso requerido:** `product-variants.crear`

**Parámetros de ruta:** `genericId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `lab_brand` | sí | `string`, `max:255` |
| `brand_sku` | no | `string`, `max:100`, `nullable` |
| `commercial_presentation` | no | `string`, `max:150`, `nullable` |
| `serie_reference` | no | `string`, `max:150`, `nullable` |
| `useful_life` | no | `string`, `max:100`, `nullable` |
| `risk_level` | no | `string`, `max:100`, `nullable` |

**Campos de respuesta (`ProductVariantResource`):** `id`, `generic_product_id`, `lab_brand`, `brand_sku`, `commercial_presentation`, `serie_reference`, `useful_life`, `risk_level`, `is_active`

---

## `GET /api/v1/generic-products/{genericId}/variants/{variantId}`

**Permiso requerido:** `product-variants.ver`

**Parámetros de ruta:** `genericId`, `variantId`

**Campos de respuesta (`ProductVariantResource`):** `id`, `generic_product_id`, `lab_brand`, `brand_sku`, `commercial_presentation`, `serie_reference`, `useful_life`, `risk_level`, `is_active`

---

## `PUT /api/v1/generic-products/{genericId}/variants/{variantId}`

**Permiso requerido:** `product-variants.editar`

**Parámetros de ruta:** `genericId`, `variantId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `lab_brand` | sí | `string`, `max:255` |
| `brand_sku` | no | `string`, `max:100`, `nullable` |
| `commercial_presentation` | no | `string`, `max:150`, `nullable` |
| `serie_reference` | no | `string`, `max:150`, `nullable` |
| `useful_life` | no | `string`, `max:100`, `nullable` |
| `risk_level` | no | `string`, `max:100`, `nullable` |

**Campos de respuesta (`ProductVariantResource`):** `id`, `generic_product_id`, `lab_brand`, `brand_sku`, `commercial_presentation`, `serie_reference`, `useful_life`, `risk_level`, `is_active`

---

## `DELETE /api/v1/generic-products/{genericId}/variants/{variantId}`

**Permiso requerido:** `product-variants.eliminar`

**Parámetros de ruta:** `genericId`, `variantId`

---

## `GET /api/v1/variants/{variantId}/sanitary-registrations`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `variantId`

**Query params detectados:** `only_active`

**Campos de respuesta (`ProductSanitaryRegistrationResource`):** `id`, `product_variant_id`, `registration_number`, `expiry_date`, `is_active`, `is_expired`

---

## `POST /api/v1/variants/{variantId}/sanitary-registrations`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `variantId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `registration_number` | sí | `string`, `max:100` |
| `expiry_date` | sí | `date_format:Y-m-d` |
| `is_active` | no | `boolean`, `nullable` |

**Campos de respuesta (`ProductSanitaryRegistrationResource`):** `id`, `product_variant_id`, `registration_number`, `expiry_date`, `is_active`, `is_expired`

---

## `PUT /api/v1/variants/{variantId}/sanitary-registrations/{registration}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `variantId`, `registration`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `registration_number` | sí | `string`, `max:100` |
| `expiry_date` | sí | `date_format:Y-m-d` |
| `is_active` | no | `boolean`, `nullable` |

**Campos de respuesta (`ProductSanitaryRegistrationResource`):** `id`, `product_variant_id`, `registration_number`, `expiry_date`, `is_active`, `is_expired`

---

## `DELETE /api/v1/variants/{variantId}/sanitary-registrations/{registration}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `variantId`, `registration`

---

## `GET /api/v1/variants/{variantId}/suppliers`

**Permiso requerido:** `proveedores.ver`

**Parámetros de ruta:** `variantId`

---

## `GET /api/v1/presentations`

**Permiso requerido:** `generic-products.ver`

**Campos de respuesta (`ProductPresentationResource`):** `id`, `parent_id`, `name`, `code`, `units_of_measure_id`, `quantity_per_parent`, `factor_to_base`, `level`, `is_active`, `sort_order`

---

## `GET /api/v1/presentations/tree`

**Permiso requerido:** `generic-products.ver`

---

## `POST /api/v1/presentations`

**Permiso requerido:** `generic-products.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `parent_id` | no | `integer`, `exists:product_presentations,id`, `nullable` |
| `name` | sí | `string`, `max:255` |
| `code` | sí | `string`, `max:50`, `unique:product_presentations,code` |
| `units_of_measure_id` | sí | `integer`, `exists:units_of_measure,id` |
| `quantity_per_parent` | no | `integer`, `min:1`, `nullable` |
| `factor_to_base` | sí | `integer`, `min:1` |
| `level` | sí | `integer`, `min:1` |
| `sort_order` | no | `integer`, `min:0`, `nullable` |

**Campos de respuesta (`ProductPresentationResource`):** `id`, `parent_id`, `name`, `code`, `units_of_measure_id`, `quantity_per_parent`, `factor_to_base`, `level`, `is_active`, `sort_order`

---

## `PUT /api/v1/presentations/{presentation}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `presentation`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `parent_id` | no | `integer`, `exists:product_presentations,id`, `nullable` |
| `name` | no | `string`, `max:255` |
| `code` | no | `string`, `max:50`, `unique` |
| `units_of_measure_id` | no | `integer`, `exists:units_of_measure,id` |
| `quantity_per_parent` | no | `integer`, `min:1`, `nullable` |
| `factor_to_base` | no | `integer`, `min:1` |
| `level` | no | `integer`, `min:1` |
| `is_active` | no | `boolean`, `nullable` |
| `sort_order` | no | `integer`, `min:0`, `nullable` |

**Campos de respuesta (`ProductPresentationResource`):** `id`, `parent_id`, `name`, `code`, `units_of_measure_id`, `quantity_per_parent`, `factor_to_base`, `level`, `is_active`, `sort_order`

---

## `DELETE /api/v1/presentations/{presentation}`

**Permiso requerido:** `generic-products.eliminar`

**Parámetros de ruta:** `presentation`

---

## `POST /api/v1/presentations/validate-hierarchy`

**Permiso requerido:** `generic-products.ver`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `parent_id` | no | `integer`, `exists:product_presentations,id`, `nullable` |
| `factor_to_base` | sí | `integer`, `min:1` |
| `quantity_per_parent` | no | `integer`, `min:1`, `nullable` |
| `level` | sí | `integer`, `min:1` |

---

## `POST /api/v1/presentations/convert-to-base`

**Permiso requerido:** `generic-products.ver`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `presentation_id` | sí | `integer`, `exists:product_presentations,id` |
| `quantity` | sí | `integer`, `min:1` |

---

## `GET /api/v1/generic-products/{genericId}/presentations`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `genericId`

**Campos de respuesta (`ProductPresentationResource`):** `id`, `parent_id`, `name`, `code`, `units_of_measure_id`, `quantity_per_parent`, `factor_to_base`, `level`, `is_active`, `sort_order`

---

## `GET /api/v1/generic-products/{genericId}/presentations/tree`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `genericId`

---

## `POST /api/v1/generic-products/{genericId}/presentations/{presentationId}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `genericId`, `presentationId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `is_purchase_default` | no | `boolean`, `nullable` |
| `sort_order` | no | `integer`, `min:0`, `nullable` |

---

## `DELETE /api/v1/generic-products/{genericId}/presentations/{presentationId}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `genericId`, `presentationId`

---

## `GET /api/v1/generic-products/{genericId}/kit-components`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `genericId`

---

## `PUT /api/v1/generic-products/{genericId}/kit-components`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `genericId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `components` | sí | `array`, `min:1` |
| `components.*.component_generic_id` | sí | `integer`, `exists:product_generics,id` |
| `components.*.quantity_per_kit` | sí | `integer`, `min:1` |
| `components.*.sort_order` | no | `integer`, `min:0`, `nullable` |
| `components.*.notes` | no | `string`, `max:500`, `nullable` |

---

## `DELETE /api/v1/generic-products/{genericId}/kit-components/{componentId}`

**Permiso requerido:** `generic-products.editar`

**Parámetros de ruta:** `genericId`, `componentId`

---

## `POST /api/v1/generic-products/{genericId}/kit-components/explode`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `genericId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `quantity_kits` | sí | `integer`, `min:1` |

---

## `GET /api/v1/suppliers/search`

**Permiso requerido:** `proveedores.ver`

**Query params detectados:** `q`, `type`

---

## `GET /api/v1/suppliers`

**Permiso requerido:** `proveedores.ver`

**Query params detectados:** `is_active`, `search`, `type`

**Campos de respuesta (`SupplierResource`):** `id`, `name`, `tax_id`, `contact_name`, `phone`, `email`, `address`, `notes`, `is_active`, `supplier_type`

---

## `GET /api/v1/suppliers/{id}`

**Permiso requerido:** `proveedores.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`SupplierResource`):** `id`, `name`, `tax_id`, `contact_name`, `phone`, `email`, `address`, `notes`, `is_active`, `supplier_type`

---

## `POST /api/v1/suppliers`

**Permiso requerido:** `proveedores.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `tax_id` | no | `string`, `max:50`, `unique:suppliers,tax_id`, `nullable` |
| `contact_name` | no | `string`, `max:255`, `nullable` |
| `phone` | no | `string`, `max:50`, `nullable` |
| `email` | no | `email`, `max:255`, `nullable` |
| `address` | no | `string`, `nullable` |
| `notes` | no | `string`, `nullable` |
| `supplier_type` | no | `string`, `in:inventory,expense,both`, `nullable` |

**Campos de respuesta (`SupplierResource`):** `id`, `name`, `tax_id`, `contact_name`, `phone`, `email`, `address`, `notes`, `is_active`, `supplier_type`

---

## `PUT /api/v1/suppliers/{id}`

**Permiso requerido:** `proveedores.editar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `tax_id` | no | `string`, `max:50`, `unique:suppliers,tax_id`, `nullable` |
| `contact_name` | no | `string`, `max:255`, `nullable` |
| `phone` | no | `string`, `max:50`, `nullable` |
| `email` | no | `email`, `max:255`, `nullable` |
| `address` | no | `string`, `nullable` |
| `notes` | no | `string`, `nullable` |
| `supplier_type` | no | `string`, `in:inventory,expense,both`, `nullable` |

**Campos de respuesta (`SupplierResource`):** `id`, `name`, `tax_id`, `contact_name`, `phone`, `email`, `address`, `notes`, `is_active`, `supplier_type`

---

## `DELETE /api/v1/suppliers/{id}`

**Permiso requerido:** `proveedores.eliminar`

**Parámetros de ruta:** `id`

---

## `GET /api/v1/suppliers/{supplierId}/variants`

**Permiso requerido:** `proveedores.ver`

**Parámetros de ruta:** `supplierId`

---

## `POST /api/v1/suppliers/{supplierId}/variants/by-category`

**Permiso requerido:** `proveedores.editar`

**Parámetros de ruta:** `supplierId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `category_id` | sí | `integer`, `exists:categories,id` |
| `lead_time_days` | no | `integer`, `min:0`, `nullable` |
| `unit_price` | no | `numeric`, `min:0`, `nullable` |
| `is_preferred` | no | `boolean`, `nullable` |

---

## `DELETE /api/v1/suppliers/{supplierId}/variants/by-category`

**Permiso requerido:** `proveedores.editar`

**Parámetros de ruta:** `supplierId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `category_id` | sí | `integer`, `exists:categories,id` |

---

## `POST /api/v1/suppliers/{supplierId}/variants/{variantId}`

**Permiso requerido:** `proveedores.editar`

**Parámetros de ruta:** `supplierId`, `variantId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `supplier_sku` | no | `string`, `max:100`, `nullable` |
| `product_presentation_id` | no | `integer`, `exists:product_presentations,id`, `nullable` |
| `lead_time_days` | no | `integer`, `min:0`, `nullable` |
| `unit_price` | no | `numeric`, `min:0`, `nullable` |
| `is_preferred` | no | `boolean`, `nullable` |

---

## `PUT /api/v1/suppliers/{supplierId}/variants/{variantId}`

**Permiso requerido:** `proveedores.editar`

**Parámetros de ruta:** `supplierId`, `variantId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `supplier_sku` | no | `string`, `max:100`, `nullable` |
| `product_presentation_id` | no | `integer`, `exists:product_presentations,id`, `nullable` |
| `lead_time_days` | no | `integer`, `min:0`, `nullable` |
| `unit_price` | no | `numeric`, `min:0`, `nullable` |
| `is_preferred` | no | `boolean`, `nullable` |

---

## `DELETE /api/v1/suppliers/{supplierId}/variants/{variantId}`

**Permiso requerido:** `proveedores.editar`

**Parámetros de ruta:** `supplierId`, `variantId`

---

## `POST /api/v1/import/products`

**Permiso requerido:** `generic-products.importar`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:xlsx,csv,xls`, `max:10240` |

---

## `POST /api/v1/import/suppliers`

**Permiso requerido:** `proveedores.importar`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `file` | sí | `file`, `mimes:xlsx,csv,xls`, `max:10240` |

---

## `GET /api/v1/import/templates/{entity}`

**Permiso requerido:** `generic-products.ver`

**Parámetros de ruta:** `entity`

**Tipo de respuesta:** descarga de archivo

---
