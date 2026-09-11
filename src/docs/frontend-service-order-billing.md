# Guía Frontend — Estado de Facturación de Órdenes de Servicio

> **Versión:** 2026-09-10
> **Ruta afectada:** `/inventory/patient-records`

---

## Contexto

Las órdenes de servicio de pacientes ahora tienen un campo `billing_status` que indica si la orden ya fue registrada en facturación o si fue anulada. Esto permite diferenciar en el listado qué órdenes están pendientes de facturar.

---

## Nuevos campos en la respuesta

### A nivel de orden (`ServiceOrderResource`)

Aparece en las respuestas de `GET /service-orders/{orderNumber}`, `POST /service-orders/{orderNumber}/bill` y `POST /service-orders/{orderNumber}/cancel`:

```json
{
  "order_number": "OS-20260910-000001",
  "order_status": "approved",
  "billing_status": null,
  "total_amount": 250000.00,
  "total_discount": 0.00,
  "net_total": 250000.00,
  "procedures": [...]
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `billing_status` | `string \| null` | Estado de facturación de la orden (ver valores abajo) |

### Valores posibles de `billing_status`

| Valor | Significado |
|---|---|
| `null` | Pendiente de facturar (estado inicial) |
| `"billed"` | Orden ya facturada/registrada |
| `"cancelled"` | Orden anulada |

### A nivel de registro individual (`PatientProcedureRecordResource`)

Aparece en el listado `GET /patient-procedure-records` y en `procedures[]` dentro de la orden:

```json
{
  "id": 1,
  "order_number": "OS-20260910-000001",
  "billing_status": "billed",
  "billed_by_user_id": 3,
  "billed_at": "2026-09-10 14:30:00",
  ...
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `billing_status` | `string \| null` | Mismo valor que a nivel de orden |
| `billed_by_user_id` | `integer \| null` | ID del usuario que facturó/anuló |
| `billed_at` | `string \| null` | Fecha y hora en formato `Y-m-d H:i:s` |

---

## Nuevos Endpoints

### Marcar orden como facturada

```
POST /api/v1/service-orders/{orderNumber}/bill
```

**Permiso requerido:** `ordenes_servicio.ver`

**No requiere body.**

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Orden marcada como facturada",
  "data": {
    "order_number": "OS-20260910-000001",
    "billing_status": "billed",
    "order_status": "approved",
    "total_amount": 250000.00,
    "total_discount": 0.00,
    "net_total": 250000.00,
    "procedures": [...]
  }
}
```

**Errores posibles:**

| HTTP | Caso |
|---|---|
| `403` | Sin permiso `ordenes_servicio.ver` |
| `409` | La orden no existe |
| `409` | La orden ya está en estado `billed` |
| `409` | La orden está `cancelled` (no se puede facturar una orden anulada) |

---

### Anular una orden

```
POST /api/v1/service-orders/{orderNumber}/cancel
```

**Permiso requerido:** `ordenes_servicio.ver`

**No requiere body.**

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Orden anulada exitosamente",
  "data": {
    "order_number": "OS-20260910-000001",
    "billing_status": "cancelled",
    "order_status": "approved",
    "total_amount": 250000.00,
    ...
  }
}
```

**Errores posibles:**

| HTTP | Caso |
|---|---|
| `403` | Sin permiso `ordenes_servicio.ver` |
| `409` | La orden no existe |
| `409` | La orden ya está en estado `cancelled` |

> **Nota:** Una orden `billed` SÍ puede anularse. Una orden `cancelled` NO puede volver a facturarse.

---

## Filtros en el listado

El endpoint `GET /api/v1/patient-procedure-records` acepta el nuevo parámetro `billing_status`:

| Parámetro | Valor | Resultado |
|---|---|---|
| `billing_status=null` | `"null"` (string) | Solo registros sin facturar |
| `billing_status=billed` | `"billed"` | Solo registros facturados |
| `billing_status=cancelled` | `"cancelled"` | Solo registros anulados |

**Ejemplo de uso:**
```
GET /api/v1/patient-procedure-records?billing_status=null
```
Devuelve únicamente las órdenes que aún no han sido facturadas — el filtro recomendado para la vista principal de `/inventory/patient-records`.

---

## Flujo de estados

```
               ┌─────────────────┐
               │  null (inicial) │
               └────────┬────────┘
                        │
              ┌─────────┴──────────┐
              │                    │
              ▼                    ▼
        ┌──────────┐         ┌───────────┐
        │  billed  │────────►│ cancelled │
        └──────────┘         └───────────┘
```

- `null` → `billed`: acción "Facturar" ✅
- `null` → `cancelled`: acción "Anular" ✅
- `billed` → `cancelled`: acción "Anular" ✅ (devolver/reversar)
- `cancelled` → `billed`: ❌ No permitido
- `billed` → `billed`: ❌ Error 409
- `cancelled` → `cancelled`: ❌ Error 409

---

## Comportamiento por registro

Todas las acciones operan a nivel de **orden completa** (`order_number`). Si una orden tiene varios procedimientos, **todos sus registros quedan con el mismo `billing_status`** en una sola operación atómica.

---

## Recomendaciones de UI

### Listado de órdenes (`/inventory/patient-records`)

1. Por defecto, filtrar con `billing_status=null` para mostrar solo las pendientes.
2. Ofrecer un selector/tab para ver: **Pendientes** / **Facturadas** / **Anuladas** / **Todas**.
3. Mostrar una etiqueta de color por estado:

| Estado | Sugerencia de color |
|---|---|
| Sin facturar (`null`) | Gris / Neutro |
| Facturada (`billed`) | Verde |
| Anulada (`cancelled`) | Rojo |

### Botones de acción por orden

Renderizar condicionalmente según `billing_status`:

```
billing_status === null     → mostrar [Facturar] y [Anular]
billing_status === "billed" → mostrar [Anular] (solo)
billing_status === "cancelled" → sin acciones disponibles (solo lectura)
```

### Confirmación antes de anular

Mostrar un diálogo de confirmación antes de llamar a `/cancel`, ya que la acción no es reversible si la orden ya está anulada.

---

## Ejemplo completo de ciclo

```js
// 1. Listar órdenes pendientes de facturar
const { data } = await api.get('/patient-procedure-records?billing_status=null')

// 2. Facturar una orden
await api.post(`/service-orders/${orderNumber}/bill`)

// 3. Si hubo un error, anular la orden
await api.post(`/service-orders/${orderNumber}/cancel`)
```
