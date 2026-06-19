# Bug — `required` rechaza arrays vacíos en asignación de almacenes/sensores

## Resumen

`AssignWarehousesRequest` y `AssignSensorsRequest` usan la regla `['required', 'array']`
para `warehouse_ids` / `sensor_ids`. En Laravel, `required` falla cuando el valor es un
array vacío `[]`, no solo cuando está ausente. Esto contradice la documentación de estos
mismos endpoints (`frontend-warehouse-access.md` y `frontend-sensor-access.md`), que dice
explícitamente que se puede enviar `[]` para quitarle al usuario todos los almacenes o
sensores asignados.

## Reproducción

```bash
curl -X PUT http://localhost:8000/api/v1/users/1/sensors \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"sensor_ids":[]}'
```

Respuesta actual:
```json
{"success":false,"message":"Error de validación.","errors":{"sensor_ids":["validation.required"]}}
```

Lo mismo ocurre con `PUT /api/v1/users/{id}/warehouses` y `{"warehouse_ids":[]}`.

Nota adicional: el mensaje de error muestra la clave cruda `validation.required` en vez
del mensaje traducido ("El campo sensor_ids es obligatorio.") — sugiere que falta el
archivo de idioma o el resolver de mensajes no está tomando la traducción para esta regla.

## Impacto

- Un usuario con permiso `sensores.asignar`/`almacenes.asignar` **no puede quitarle a
  otro usuario todo su acceso** a sensores/almacenes desde un único `PUT` con `[]`, que
  es justo el caso de uso que la documentación promete.
- Efecto colateral en el frontend: el formulario de edición de usuario llama a
  `assignWarehouses`/`assignSensors` en cada guardado cuando el usuario logueado tiene
  el permiso correspondiente, sin importar si hubo cambios. Si el usuario editado no
  tiene almacenes/sensores asignados y no se selecciona ninguno, el guardado completo
  del formulario falla con este 422 (bloqueando también los demás campos del usuario:
  nombre, roles, etc., porque la asignación va encadenada después del `PUT /users/{id}`).

## Archivos afectados

- `app/Modules/Auth/Infrastructure/Http/Requests/AssignWarehousesRequest.php:19`
- `app/Modules/Auth/Infrastructure/Http/Requests/AssignSensorsRequest.php:19`

## Fix sugerido

Cambiar `required` por `present` (o `nullable`) manteniendo `array`, para aceptar
`[]` sin dejar de exigir que el campo venga en el body:

```php
'warehouse_ids'   => ['present', 'array'],
'warehouse_ids.*' => ['integer', 'exists:warehouses,id'],
```

```php
'sensor_ids'   => ['present', 'array'],
'sensor_ids.*' => ['integer', 'exists:sensors,id'],
```

## Cómo se encontró

Detectado en pruebas end-to-end del frontend (sección "Equipos de monitoreo" en edición
de usuario + diálogo "Usuarios con acceso" en Monitoreo) el 2026-06-19, al verificar el
flujo de asignación de sensores contra el backend local. El guardado funciona
correctamente en todos los demás casos (con al menos un almacén/sensor seleccionado).
