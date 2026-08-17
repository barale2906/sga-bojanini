# Envío de comprobantes por correo — Guía de integración frontend

## Descripción general

Permite enviar el PDF de un comprobante de movimiento de inventario a uno o más destinatarios. El envío es **bajo demanda** (el usuario elige cuándo y a quién) y **asíncrono** (el backend encola el correo y responde de inmediato; el usuario no espera a que el SMTP confirme).

Condición obligatoria: el documento debe estar en estado **`confirmed`**. Si está `pending_signature`, el backend responde 409.

---

## Flujos de usuario recomendados

Se recomiendan **dos puntos de acceso** complementarios: uno inmediato al confirmar y uno diferido desde el detalle o la lista.

---

### Flujo A — Prompt inmediato post-confirmación

Al completar la confirmación (`POST /movement-documents/{id}/confirm` → 200), mostrar un paso final inline antes de cerrar el flujo:

```
[Firma entregado] → [Firma recibido] → ✓ Confirmado
                                            ↓
                        ┌─────────────────────────────────┐
                        │  Comprobante confirmado          │
                        │  ¿Desea enviarlo por correo?     │
                        │                                  │
                        │  [Sí, enviar]   [Ahora no]       │
                        └─────────────────────────────────┘
```

- "Ahora no" cierra el flujo de confirmación sin acción adicional.
- "Sí, enviar" abre el modal de destinatarios (ver sección [Modal de envío](#comportamiento-del-modal-de-envío)).
- Este prompt aparece **una sola vez**; no volver a mostrarlo si el usuario lo descarta.

---

### Flujo B — Acción diferida desde detalle o lista

Para reenvíos o envíos que no se realizaron al confirmar. El botón debe estar disponible en dos lugares:

**En el detalle del comprobante** — junto a "Descargar PDF":

```
Comprobante SAL-2026-0042        [pending_signature]  →  sin botón
Comprobante SAL-2026-0043        [confirmed]          →  [Descargar PDF]  [Enviar por correo]
```

**En la tabla de documentos** — como opción en el menú de acciones (⋮) de cada fila:

```
N° Comprobante   Tipo      Estado       Acciones
SAL-2026-0042    Salida    Confirmado   ⋮  → Ver · Descargar PDF · Enviar por correo
SAL-2026-0043    Traslado  Pendiente    ⋮  → Ver · (Enviar por correo deshabilitado)
```

En ambos casos, al hacer clic se abre el mismo modal de destinatarios.

---

### Modal de envío (compartido por ambos flujos)

```
Modal "Enviar comprobante {document_number}"
  ├─ Buscador de usuarios internos   →  GET /api/v1/auth/users?search=...
  ├─ Campo de correo externo manual  →  validación de formato en cliente
  ├─ Lista de destinatarios añadidos (chips removibles, mínimo 1)
  └─ Botón "Enviar"
       └─ POST /api/v1/movement-documents/{id}/send-email
            ├─ 200 → cerrar modal + toast "Correo en cola para envío"
            └─ error → mensaje dentro del modal (no cerrar)
```

---

## Endpoint: Enviar comprobante

```
POST /api/v1/movement-documents/{id}/send-email
```

### Autenticación y permiso

| Campo | Valor |
|---|---|
| Header | `Authorization: Bearer <token>` |
| Permiso requerido | `movimientos.enviar_correo` |

> El usuario también debe tener acceso al almacén del documento (misma regla que ver/descargar el PDF).

### Parámetros de ruta

| Parámetro | Tipo | Descripción |
|---|---|---|
| `id` | integer | ID del `MovementDocument` |

### Body (JSON)

```json
{
  "recipients": [
    "correo1@ejemplo.com",
    "correo2@ejemplo.com"
  ]
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `recipients` | `string[]` | Requerido. Mínimo 1 elemento. Cada entrada debe ser un email válido (formato RFC). |

### Respuestas

#### 200 — Éxito

```json
{
  "success": true,
  "message": "Comprobante en cola para envío por correo electrónico.",
  "data": null
}
```

#### 409 — Documento no confirmado

```json
{
  "success": false,
  "message": "Solo se pueden enviar comprobantes de documentos confirmados.",
  "error_code": "DOMAIN_ERROR"
}
```

#### 422 — Validación fallida

```json
{
  "success": false,
  "message": "The recipients field is required.",
  "errors": {
    "recipients": ["Debe indicar al menos un destinatario."],
    "recipients.1": ["Uno de los correos ingresados no tiene un formato válido."]
  }
}
```

#### 403 — Sin permiso

```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

#### 404 — Documento no encontrado

```json
{
  "success": false,
  "message": "No query results for model [MovementDocumentModel]."
}
```

---

## Endpoint auxiliar: Búsqueda de usuarios internos

Para poblar el selector de destinatarios con usuarios del sistema (ya existe, no requiere cambios en backend):

```
GET /api/v1/auth/users?search={término}
```

| Parámetro | Tipo | Descripción |
|---|---|---|
| `search` | string | Filtra por `name` o `email` (LIKE). |

### Respuesta (fragmento relevante)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Ing. Alexander Barajas",
      "email": "alexanderbarajas@gmail.com",
      "is_active": true,
      "roles": ["super_administrador"]
    }
  ]
}
```

> Solo devuelve usuarios activos (`is_active: true`). Usar el campo `email` como valor del destinatario.
>
> Permiso requerido: `usuarios.ver`.

---

## Visibilidad del botón "Enviar por correo"

Mostrar el botón / acción únicamente si se cumplen **ambas** condiciones:

```js
const canSendEmail =
  document.status === 'confirmed' &&
  userHasPermission('movimientos.enviar_correo');
```

| Punto de acceso | Condición |
|---|---|
| Prompt post-confirmación (flujo A) | Aparece automáticamente al recibir 200 en `/confirm`, si el usuario tiene el permiso |
| Detalle del comprobante (flujo B) | Botón visible solo si `canSendEmail === true` |
| Menú ⋮ en tabla (flujo B) | Opción activa solo si `canSendEmail === true`; deshabilitada (no oculta) si el documento está pendiente |

> Mostrar la opción deshabilitada en la tabla (en lugar de ocultarla) ayuda al usuario a entender que la acción existe pero requiere que el documento esté confirmado primero.

---

## Comportamiento del modal de envío

### Agregar destinatarios

- **Desde usuarios internos**: campo de búsqueda con debounce (≥ 300 ms) que llama a `GET /auth/users?search=...`. Al seleccionar un resultado se agrega el email como chip.
- **Correo externo**: campo de texto libre con validación `email` en cliente. Al presionar Enter o hacer clic en "Agregar" se añade como chip.
- **Deduplicación**: ignorar silenciosamente si el mismo correo ya está en la lista.
- **Eliminar**: cada chip tiene un botón ×.

### Restricciones

- El botón "Enviar" permanece deshabilitado mientras la lista esté vacía.
- Mostrar spinner en el botón durante la llamada al API.
- No cerrar el modal si la respuesta es un error; mostrar el mensaje dentro del modal.

### Feedback post-envío

| Resultado | Acción |
|---|---|
| 200 | Cerrar modal + toast de éxito (3 s) |
| 409 | Mensaje en modal: "El comprobante debe estar confirmado antes de enviarse." |
| 422 | Marcar los correos inválidos en la lista de chips |
| 403 | Toast de error: "No tienes permiso para realizar esta acción." |
| 5xx | Toast de error genérico + botón "Reintentar" |

---

## Notas de implementación

### El envío es asíncrono

El 200 confirma que el correo fue **encolado**, no que ya fue entregado. No mostrar "El correo fue enviado exitosamente" — mostrar "El correo está siendo enviado" o "Correo en cola".

Para producción el worker de colas debe estar corriendo:

```bash
php artisan queue:work --queue=default
```

### Qué incluye el correo automáticamente

El backend genera el cuerpo y el PDF sin intervención del frontend. El correo contiene:

- Resumen del documento (tipo, número, fecha, almacén, centro de costo, usuario)
- Tabla de líneas (producto, lote, vencimiento, cantidad)
- PDF adjunto: `comprobante-{document_number}.pdf`

### Pruebas locales con Mailpit

1. Levantar el servicio: `docker compose up mailpit`
2. En `.env`, comentar el bloque de Gmail y descomentar el bloque de Mailpit
3. Abrir la bandeja en `http://localhost:8025`
4. Los correos enviados desde el entorno local aparecen allí sin llegar a destinos reales

---

## Tipos de documento soportados

El endpoint funciona para cualquier tipo de `MovementDocument`:

| `document_type` | Etiqueta en el correo |
|---|---|
| `entry` | Entrada |
| `exit` | Salida |
| `transfer` | Traslado |
| `adjustment` | Ajuste |
| `return` | Devolución |
| `expiration_write_off` | Baja por Vencimiento |
| `loss` | Baja de Inventario |
