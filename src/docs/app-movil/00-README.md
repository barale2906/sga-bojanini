# SGA Bojanini — Guía de integración para la App Móvil

Documento para el desarrollador móvil. Describe cómo consumir la API REST del **Sistema de Gestión de Almacén (SGA) Bojanini**: autenticación, convenciones, flujos de negocio y la referencia de **todos los endpoints (342)** con permisos, payloads y reglas de validación.

> Generado el 2026-11-24 a partir del código fuente del backend (rutas, FormRequests y Resources). Ante cualquier discrepancia, **el código del backend manda**.

## Contenido

| Archivo | Contenido |
|---|---|
| [01-convenciones.md](01-convenciones.md) | **Leer primero.** Auth, envoltorio de respuestas, errores, paginación, fechas, archivos, permisos y acceso a almacenes |
| [02-auth.md](02-auth.md) | Login, sesión, menú, firma personal/PIN, usuarios y roles (25 endpoints) |
| [03-warehouse.md](03-warehouse.md) | Almacenes, zonas, ubicaciones, capacidad (21) |
| [04-catalog.md](04-catalog.md) | Productos genéricos/variantes, presentaciones, kits, proveedores, códigos de barras (68) |
| [05-inventory.md](05-inventory.md) | Lotes, stock, movimientos y comprobantes con firma (27) |
| [06-purchasing.md](06-purchasing.md) | Órdenes de compra, consolidadas, órdenes de gasto, pagos, flujos de aprobación (40) |
| [07-monitoring.md](07-monitoring.md) | Sensores, lecturas, calibraciones, reglas de alerta, reportes de condiciones (23) |
| [08-audit.md](08-audit.md) | Bitácora de auditoría (4) |
| [09-integration.md](09-integration.md) | Integraciones, agenda, consumos (12) |
| [10-medsys.md](10-medsys.md) | Pacientes/citas MedSys, proyección de consumo (4) |
| [11-reports.md](11-reports.md) | Notificaciones y reportes/exportaciones (14) |
| [12-dashboard.md](12-dashboard.md) | Tablero (5) |
| [13-cost-center.md](13-cost-center.md) | Centros de costo, servicios médicos, procedimientos, registros y evoluciones clínicas (37) |
| [14-service-orders.md](14-service-orders.md) | Órdenes de servicio, listas de precios (9) |
| [15-assets.md](15-assets.md) | Activos: ubicaciones, traslados, mantenimientos, documentos, hoja de vida (53) |
| [16-flujos.md](16-flujos.md) | Máquinas de estado y flujos multi-paso (movimientos con firma, compras, activos) |
| [17-permisos.md](17-permisos.md) | Catálogo completo de permisos |

## Resumen técnico

| Tema | Valor |
|---|---|
| Backend | Laravel 13 / PHP 8.3 |
| Base URL | `{BASE_URL}/api/v1` (el host lo define cada ambiente; en local `http://localhost:8000`) |
| Formato | JSON (`Content-Type: application/json`, `Accept: application/json`). Subidas de archivos: `multipart/form-data` |
| Autenticación | Bearer token (Laravel Sanctum): `Authorization: Bearer <token>` |
| Autorización | Permisos por rol (`spatie/laravel-permission`) + acceso por almacén y por sensor |
| Idioma | Mensajes de la API en español |
| Salud | `GET {BASE_URL}/up` (sin auth, fuera de `/api`) |
| Documentación viva | El backend incluye Scramble (`dedoc/scramble`); con la app corriendo, la documentación interactiva suele servirse en `{BASE_URL}/docs/api` (por defecto Scramble solo la habilita en ambientes no productivos). El `docs/openapi/api.json` del repo está **desactualizado** (mayo 2026): se regenera con `php artisan scramble:export`. |

## Cómo está organizada cada entrada de endpoint

Cada endpoint de los archivos 02–15 muestra:

- **Método y ruta** (siempre con prefijo `/api/v1`).
- **Permiso requerido** — string que debe estar en `user.permissions` (si no, la API responde 403).
- **Parámetros de ruta**, **query params** y/o **body** con tabla `Campo | Requerido | Reglas`.
- **Campos de respuesta** — claves del recurso JSON devuelto (dentro de `data`).

Lectura de la columna *Reglas* (sintaxis de validación de Laravel):

| Regla | Significado |
|---|---|
| `integer`, `numeric`, `string`, `boolean`, `array`, `date`, `email` | Tipo. `date` acepta `YYYY-MM-DD` (o ISO 8601) |
| `nullable` | Puede enviarse `null` |
| `min:n` / `max:n` | En strings = longitud; en números = valor; en arrays = cantidad de elementos; en archivos = KB |
| `in:a,b,c` | Solo uno de los valores listados (enums) |
| `exists:tabla,col` | El ID debe existir en la tabla indicada |
| `unique:tabla,col` | Valor único |
| `required_if:campo,valor`, `required_with:campo`, `required_without:...` | Requerido condicionalmente. La columna *Requerido* indica `condicional` |
| `confirmed` | Debe enviarse además `<campo>_confirmation` con el mismo valor |
| `items.*.campo` | Campo de cada elemento del arreglo `items` |
| `file`, `mimes:pdf,jpg` | Archivo subido en multipart |

Los campos de **query** detectados (sin FormRequest) se extrajeron del código del controlador; si un filtro no aparece, revisar el recurso listado en el endpoint o consultar a backend.

## Primeros pasos (checklist)

1. `POST /api/v1/auth/login` → guardar `token` y `user.permissions` de forma segura (Keychain / Keystore).
2. Enviar `Authorization: Bearer <token>` y `Accept: application/json` en toda petición.
3. `GET /api/v1/auth/menu` para construir la navegación según permisos.
4. Cargar catálogos base con `GET /api/v1/warehouses` (ya filtrado a los almacenes del usuario).
5. Manejar `401` (cerrar sesión → login), `403` (sin permiso), `409` (regla de negocio), `422` (validación) — ver [01-convenciones](01-convenciones.md).

## Observaciones para el desarrollo de la app

- **Guías previas del frontend web**: en `docs/` existen guías `frontend-*.md` y `guia-*.md` con detalle de negocio por módulo (p. ej. `guia-frontend-firma-movimientos.md`, `frontend-activos.md`, `frontend-ordenes-v2.md`). Son útiles para entender el *porqué* de cada flujo, pero algunas contienen nombres de campos anteriores a refactors (por ejemplo `product_id` → hoy es `product_variant_id`). **Los nombres de campos de esta referencia son los vigentes.**
- **Escáner de códigos de barras**: los productos genéricos exponen un código de 6 dígitos (`GET /generic-products/barcode/{value}`); ideal para el lector de la cámara.
- **Firma manuscrita**: los comprobantes de inventario y los traslados de activos requieren firma (imagen PNG en base64, o PIN de 3 dígitos si el firmante tiene firma registrada). Ver [16-flujos](16-flujos.md).
- **Sensores IoT** usan un token distinto (`X-IoT-Token`) y no corresponden a la app de usuarios; solo se documenta por completitud.
