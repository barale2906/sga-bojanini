# 17 · Catálogo de permisos

Strings que llegan en `user.permissions` (login / `auth/me`). Formato `modulo.accion`. El permiso exigido por cada endpoint está indicado en su entrada.

Permisos adicionales que la app puede ver en `permissions` y que gobiernan botones (según el menú): `ordenes_servicio.crear`, `ordenes_servicio.editar`, `ordenes_servicio.eliminar`, `activos.dar_baja` (baja definitiva de un activo en traslado `retirement`).

| Módulo | Acciones |
|---|---|
| `activos` | `comentar`, `crear`, `editar`, `eliminar`, `exportar`, `importar`, `ver` |
| `activos_catalogos` | `crear`, `editar`, `eliminar` |
| `activos_documentos` | `cargar`, `eliminar` |
| `activos_mantenimientos` | `cancelar`, `ejecutar`, `programar`, `ver` |
| `activos_traslados` | `confirmar`, `solicitar` |
| `almacenes` | `asignar`, `crear`, `editar`, `eliminar`, `ver` |
| `auditoria` | `exportar`, `ver` |
| `centros_costo` | `crear`, `editar`, `eliminar`, `ver` |
| `consumos` | `crear`, `ver` |
| `evoluciones_clinicas` | `crear`, `editar`, `eliminar`, `ver` |
| `generic-products` | `barcode`, `crear`, `editar`, `eliminar`, `importar`, `ver` |
| `integraciones` | `configurar`, `ver` |
| `lecturas` | `crear`, `ver` |
| `listas_precios` | `crear`, `ver` |
| `lotes` | `ver` |
| `medicamentos_procedimiento` | `ver` |
| `movimientos` | `ajuste`, `baja`, `cancelar`, `confirmar`, `devolucion`, `entrada`, `enviar_correo`, `importar`, `salida`, `transferir` |
| `notificaciones` | `ver` |
| `ordenes_compra` | `aprobar`, `consolidar`, `crear`, `enviar`, `recibir`, `ver` |
| `ordenes_gasto` | `aprobar`, `crear`, `enviar`, `factura`, `pagos`, `recibir`, `ver` |
| `ordenes_servicio` | `aprobar`, `crear`, `ver` |
| `plantillas_clinicas` | `crear`, `editar`, `eliminar`, `ver` |
| `procedimientos` | `crear`, `editar`, `eliminar`, `ver` |
| `product-variants` | `crear`, `editar`, `eliminar`, `ver` |
| `proveedores` | `crear`, `editar`, `eliminar`, `importar`, `ver` |
| `registros_procedimientos` | `crear`, `editar`, `eliminar`, `ver` |
| `reglas_alerta` | `crear`, `editar`, `eliminar`, `ver` |
| `reportes` | `ver` |
| `roles` | `crear`, `editar`, `eliminar`, `ver` |
| `sensores` | `asignar`, `crear`, `editar`, `eliminar`, `ver` |
| `servicios_medicos` | `crear`, `editar`, `eliminar`, `importar`, `ver` |
| `stock` | `ver` |
| `tablero` | `ver` |
| `ubicaciones` | `crear`, `editar`, `eliminar`, `ver` |
| `usuarios` | `crear`, `editar`, `eliminar`, `ver` |
| `zonas` | `crear`, `editar`, `eliminar`, `ver` |

Rol con acceso total a almacenes: `super_administrador`.

## Roles de ejemplo (semilla inicial)

`super_administrador` (todo, acceso a todos los almacenes), `administrador`, `jefe_almacen`, `operador_almacen`, `compras`, `auditor`, `personal_medico`. Los roles y sus permisos son configurables vía `GET/POST/PUT /roles`; la app **no** debe asumir permisos por nombre de rol, solo por `permissions[]`.
