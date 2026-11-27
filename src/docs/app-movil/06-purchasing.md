# 06 · Compras y órdenes de gasto

> Base URL: `{BASE_URL}/api/v1`. Todas las rutas requieren `Authorization: Bearer <token>` salvo que se indique lo contrario. Formato de respuestas y errores: ver [01-convenciones](01-convenciones.md).

Endpoints en este módulo: **40**

## `GET /api/v1/purchase-orders`

**Permiso requerido:** `ordenes_compra.ver`

**Query params detectados:** `status`, `supplier_id`, `date_from`, `date_to`, `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `POST /api/v1/purchase-orders`

**Permiso requerido:** `ordenes_compra.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `supplier_id` | sí | `integer`, `exists:suppliers,id` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `cost_center_id` | no | `integer`, `exists:cost_centers,id`, `nullable` |
| `notes` | no | `string`, `nullable` |
| `expected_delivery_date` | no | `date`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.product_variant_id` | sí | `integer`, `exists:product_variants,id` |
| `items.*.product_presentation_id` | sí | `integer`, `exists:product_presentations,id` |
| `items.*.quantity` | sí | `integer`, `min:1` |
| `items.*.unit_price` | sí | `numeric`, `min:0` |
| `items.*.tax_rate` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `items.*.notes` | no | `string`, `nullable` |

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `GET /api/v1/purchase-orders/suggestions`

**Permiso requerido:** `ordenes_compra.ver`

**Campos de respuesta (`ReorderSuggestionResource`):** `generic_product_id`, `product_name`, `product_barcode`, `current_stock`, `reorder_point`, `daily_consumption`, `lead_time_days`, `safety_stock`, `suggested_quantity`, `preferred_supplier`

---

## `GET /api/v1/purchase-orders/consolidable-suppliers`

**Permiso requerido:** `ordenes_compra.ver`

---

## `GET /api/v1/purchase-orders/consolidable`

**Permiso requerido:** `ordenes_compra.ver`

**Query params detectados:** `supplier_id`, `date_from`, `date_to`

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `POST /api/v1/purchase-orders/consolidation-preview`

**Permiso requerido:** `ordenes_compra.ver`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `purchase_order_ids` | sí | `array`, `min:1` |
| `purchase_order_ids.*` | sí | `integer`, `exists:purchase_orders,id` |
| `notes` | no | `string`, `max:1000`, `nullable` |

---

## `GET /api/v1/purchase-orders/{id}`

**Permiso requerido:** `ordenes_compra.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `PUT /api/v1/purchase-orders/{id}`

**Permiso requerido:** `ordenes_compra.crear`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `supplier_id` | sí | `integer`, `exists:suppliers,id` |
| `warehouse_id` | sí | `integer`, `exists:warehouses,id` |
| `cost_center_id` | no | `integer`, `exists:cost_centers,id`, `nullable` |
| `notes` | no | `string`, `nullable` |
| `expected_delivery_date` | no | `date`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.product_variant_id` | sí | `integer`, `exists:product_variants,id` |
| `items.*.product_presentation_id` | sí | `integer`, `exists:product_presentations,id` |
| `items.*.quantity` | sí | `integer`, `min:1` |
| `items.*.unit_price` | sí | `numeric`, `min:0` |
| `items.*.tax_rate` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `items.*.notes` | no | `string`, `nullable` |

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `DELETE /api/v1/purchase-orders/{id}`

**Permiso requerido:** `ordenes_compra.crear`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/purchase-orders/{id}/submit`

**Permiso requerido:** `ordenes_compra.crear`

**Parámetros de ruta:** `id`

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `POST /api/v1/purchase-orders/{id}/approve`

**Permiso requerido:** `ordenes_compra.aprobar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `record_id` | no | `integer`, `exists:approval_records,id`, `nullable` |
| `comments` | no | `string`, `nullable` |

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `POST /api/v1/purchase-orders/{id}/reject`

**Permiso requerido:** `ordenes_compra.aprobar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `record_id` | no | `integer`, `exists:approval_records,id`, `nullable` |
| `comments` | no | `string`, `nullable` |

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `POST /api/v1/purchase-orders/{id}/send`

**Permiso requerido:** `ordenes_compra.enviar`

**Parámetros de ruta:** `id`

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `POST /api/v1/purchase-orders/{id}/receive`

**Permiso requerido:** `ordenes_compra.recibir`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `items` | sí | `array`, `min:1` |
| `items.*.item_id` | sí | `integer`, `exists:purchase_order_items,id` |
| `items.*.quantity_received` | sí | `integer`, `min:1` |
| `items.*.lot_number` | sí | `string`, `max:100` |
| `items.*.expiration_date` | sí | `date`, `after_or_equal:2000-01-01`, `before:2100-01-01` |
| `items.*.location_id` | sí | `integer`, `exists:locations,id` |

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `POST /api/v1/purchase-orders/{id}/cancel`

**Permiso requerido:** `ordenes_compra.crear`

**Parámetros de ruta:** `id`

**Campos de respuesta (`PurchaseOrderResource`):** `id`, `code`, `status`, `supplier_id`, `warehouse_id`, `cost_center_id`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `created_at`, `updated_at`, `supplier`, `warehouse`, `cost_center`, `tax_breakdown`, `items`

---

## `GET /api/v1/consolidated-orders`

**Permiso requerido:** `ordenes_compra.ver`

**Query params detectados:** `supplier_id`, `date_from`, `date_to`, `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`ConsolidatedPurchaseOrderResource`):** `id`, `code`, `supplier_id`, `period_from`, `period_to`, `subtotal`, `tax_breakdown`, `tax_amount`, `total_amount`, `notes`, `created_by`, `created_at`, `updated_at`, `supplier`, `items`, `purchase_orders`

---

## `POST /api/v1/consolidated-orders`

**Permiso requerido:** `ordenes_compra.consolidar`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `purchase_order_ids` | sí | `array`, `min:1` |
| `purchase_order_ids.*` | sí | `integer`, `exists:purchase_orders,id` |
| `notes` | no | `string`, `max:1000`, `nullable` |

**Campos de respuesta (`ConsolidatedPurchaseOrderResource`):** `id`, `code`, `supplier_id`, `period_from`, `period_to`, `subtotal`, `tax_breakdown`, `tax_amount`, `total_amount`, `notes`, `created_by`, `created_at`, `updated_at`, `supplier`, `items`, `purchase_orders`

---

## `GET /api/v1/consolidated-orders/{id}`

**Permiso requerido:** `ordenes_compra.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ConsolidatedPurchaseOrderResource`):** `id`, `code`, `supplier_id`, `period_from`, `period_to`, `subtotal`, `tax_breakdown`, `tax_amount`, `total_amount`, `notes`, `created_by`, `created_at`, `updated_at`, `supplier`, `items`, `purchase_orders`

---

## `POST /api/v1/expense-orders/supplier`

**Permiso requerido:** `ordenes_gasto.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `tax_id` | no | `string`, `max:50`, `unique:suppliers,tax_id`, `nullable` |
| `contact_name` | no | `string`, `max:255`, `nullable` |
| `phone` | no | `string`, `max:50`, `nullable` |
| `email` | no | `email`, `max:255`, `nullable` |
| `notes` | no | `string`, `max:500`, `nullable` |

**Campos de respuesta (`SupplierResource`):** `id`, `name`, `tax_id`, `contact_name`, `phone`, `email`, `address`, `notes`, `is_active`, `supplier_type`

---

## `GET /api/v1/expense-orders`

**Permiso requerido:** `ordenes_gasto.ver`

**Query params detectados:** `status`, `payment_status`, `supplier_id`, `date_from`, `date_to`, `per_page`

**Tipo de respuesta:** paginada (`data` + `meta`)

Acepta `page` y `per_page` (ver convenciones).

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders`

**Permiso requerido:** `ordenes_gasto.crear`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `supplier_id` | sí | `integer`, `exists:suppliers,id`, `new \App\Rules\ExpenseSupplierRule()` |
| `expected_delivery_date` | no | `date`, `nullable` |
| `notes` | no | `string`, `max:500`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.description` | sí | `string`, `max:255` |
| `items.*.unit` | sí | `string`, `max:50` |
| `items.*.quantity` | sí | `numeric`, `min:0.001` |
| `items.*.unit_price` | sí | `numeric`, `min:0` |
| `items.*.tax_rate` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `items.*.notes` | no | `string`, `max:255`, `nullable` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `GET /api/v1/expense-orders/{id}`

**Permiso requerido:** `ordenes_gasto.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `PUT /api/v1/expense-orders/{id}`

**Permiso requerido:** `ordenes_gasto.crear`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `supplier_id` | sí | `integer`, `exists:suppliers,id`, `new \App\Rules\ExpenseSupplierRule()` |
| `expected_delivery_date` | no | `date`, `nullable` |
| `notes` | no | `string`, `max:500`, `nullable` |
| `items` | sí | `array`, `min:1` |
| `items.*.description` | sí | `string`, `max:255` |
| `items.*.unit` | sí | `string`, `max:50` |
| `items.*.quantity` | sí | `numeric`, `min:0.001` |
| `items.*.unit_price` | sí | `numeric`, `min:0` |
| `items.*.tax_rate` | no | `numeric`, `min:0`, `max:100`, `nullable` |
| `items.*.notes` | no | `string`, `max:255`, `nullable` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `DELETE /api/v1/expense-orders/{id}`

**Permiso requerido:** `ordenes_gasto.crear`

**Parámetros de ruta:** `id`

---

## `POST /api/v1/expense-orders/{id}/submit`

**Permiso requerido:** `ordenes_gasto.crear`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders/{id}/approve`

**Permiso requerido:** `ordenes_gasto.aprobar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `record_id` | no | `integer`, `exists:approval_records,id`, `nullable` |
| `comments` | no | `string`, `nullable` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders/{id}/reject`

**Permiso requerido:** `ordenes_gasto.aprobar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `record_id` | no | `integer`, `exists:approval_records,id`, `nullable` |
| `comments` | no | `string`, `nullable` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders/{id}/send`

**Permiso requerido:** `ordenes_gasto.enviar`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders/{id}/receive`

**Permiso requerido:** `ordenes_gasto.recibir`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `items` | sí | `array`, `min:1` |
| `items.*.item_id` | sí | `integer` |
| `items.*.quantity_received` | sí | `numeric`, `min:0.001` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders/{id}/cancel`

**Permiso requerido:** `ordenes_gasto.crear`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders/{id}/invoice`

**Permiso requerido:** `ordenes_gasto.factura`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `invoice_number` | sí | `string`, `max:100` |
| `invoice_date` | sí | `date` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `POST /api/v1/expense-orders/{id}/send-accounting`

**Permiso requerido:** `ordenes_gasto.factura`

**Parámetros de ruta:** `id`

**Body** (`multipart/form-data`):

| Campo | Requerido | Reglas |
|---|---|---|
| `recipients` | sí | `array`, `min:1` |
| `recipients.*` | sí | `email` |
| `attachments` | no | `array` |
| `attachments.*` | no | `file`, `max:10240`, `mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `GET /api/v1/expense-orders/{orderId}/payments`

**Permiso requerido:** `ordenes_gasto.ver`

**Parámetros de ruta:** `orderId`

**Campos de respuesta (`PurchaseOrderPaymentResource`):** `id`, `amount`, `payment_date`, `payment_method`, `reference`, `notes`, `registered_by`, `created_at`

---

## `POST /api/v1/expense-orders/{orderId}/payments`

**Permiso requerido:** `ordenes_gasto.pagos`

**Parámetros de ruta:** `orderId`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `amount` | sí | `numeric`, `min:0.01` |
| `payment_date` | sí | `date` |
| `payment_method` | sí | `string`, `in:cash,transfer,check,other` |
| `reference` | no | `string`, `max:100`, `nullable` |
| `notes` | no | `string`, `max:500`, `nullable` |

**Campos de respuesta (`ExpenseOrderResource`):** `id`, `order_type`, `code`, `status`, `payment_status`, `supplier_id`, `subtotal`, `tax_amount`, `total_amount`, `amount_paid`, `invoice_number`, `invoice_date`, `notes`, `expected_delivery_date`, `created_by`, `sent_at`, `received_at`, `accounting_sent_at`, `created_at`, `updated_at`, `supplier`, `items`, `payments`

---

## `DELETE /api/v1/expense-orders/{orderId}/payments/{paymentId}`

**Permiso requerido:** `ordenes_gasto.pagos`

**Parámetros de ruta:** `orderId`, `paymentId`

---

## `GET /api/v1/approval-flows`

**Permiso requerido:** `ordenes_compra.ver`

**Query params detectados:** `entity_type`

**Campos de respuesta (`ApprovalFlowResource`):** `id`, `name`, `entity_type`, `conditions`, `is_active`, `steps`, `created_at`, `updated_at`

---

## `POST /api/v1/approval-flows`

**Permiso requerido:** `ordenes_compra.aprobar`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `entity_type` | sí | `string`, `max:100` |
| `conditions` | no | `array`, `nullable` |
| `is_active` | no | `boolean` |
| `steps` | sí | `array`, `min:1` |
| `steps.*.step_order` | sí | `integer`, `min:1` |
| `steps.*.role_id` | sí | `integer`, `exists:roles,id` |
| `steps.*.is_required` | no | `boolean` |

**Campos de respuesta (`ApprovalFlowResource`):** `id`, `name`, `entity_type`, `conditions`, `is_active`, `steps`, `created_at`, `updated_at`

---

## `GET /api/v1/approval-flows/{id}`

**Permiso requerido:** `ordenes_compra.ver`

**Parámetros de ruta:** `id`

**Campos de respuesta (`ApprovalFlowResource`):** `id`, `name`, `entity_type`, `conditions`, `is_active`, `steps`, `created_at`, `updated_at`

---

## `PUT /api/v1/approval-flows/{id}`

**Permiso requerido:** `ordenes_compra.aprobar`

**Parámetros de ruta:** `id`

**Body** (`application/json`):

| Campo | Requerido | Reglas |
|---|---|---|
| `name` | sí | `string`, `max:255` |
| `entity_type` | sí | `string`, `max:100` |
| `conditions` | no | `array`, `nullable` |
| `is_active` | no | `boolean` |
| `steps` | sí | `array`, `min:1` |
| `steps.*.step_order` | sí | `integer`, `min:1` |
| `steps.*.role_id` | sí | `integer`, `exists:roles,id` |
| `steps.*.is_required` | no | `boolean` |

**Campos de respuesta (`ApprovalFlowResource`):** `id`, `name`, `entity_type`, `conditions`, `is_active`, `steps`, `created_at`, `updated_at`

---

## `DELETE /api/v1/approval-flows/{id}`

**Permiso requerido:** `ordenes_compra.aprobar`

**Parámetros de ruta:** `id`

---
