# Monitoreo ambiental — almacén/zonas, certificados de calibración, último registro y exportación

Implementado 2026-11-16. Pantalla: `/monitoring` (front `features/monitoring`).

## 1. Crear equipo: almacén → zonas en tarjetas

- Se elige el **almacén** (`warehouses`) y sus **zonas** (`zones`) se muestran como tarjetas con la
  condición que deben cumplir según el tipo (`ambient` → Temperatura ambiente, `cold` → Cadena de frío,
  `frozen` → Congelación, `controlled` → Condiciones controladas) y sus rangos de temperatura/humedad.
- El modal mide 980 px (máx. 95vw). Al elegir la variable (temperatura/humedad) se sugiere la unidad.
- Se quitó la opción «Presión»: el backend solo acepta `temperature` y `humidity`.
- Editar ahora **sí guarda `is_active`** (antes se ignoraba). Si el equipo cambia a una zona de otro
  almacén, sus usuarios (`user_sensor`) se recalculan a partir del nuevo almacén (+ super_administrador).

`GET /sensors` y `GET /sensors/{id}` devuelven además:

```json
{
  "zone": { "id": 3, "name": "Zona principal", "code": "ZP", "type": "ambient",
            "temp_min": 15, "temp_max": 30, "humidity_min": null, "humidity_max": 65,
            "warehouse_id": 1, "warehouse_name": "Almacén Norte" },
  "calibration": { "status": "expiring", "status_label": "Por vencer", "id": 7,
                   "expires_at": "2026-12-01", "days_left": 15 },
  "last_reading": { "id": 912, "value": 4.5, "recorded_at": "2026-11-16 08:00:00",
                    "created_at": "2026-11-16 08:02:11", "reading_source": "manual", "user_name": "Ana" }
}
```

`calibration.status`: `missing` | `valid` | `expiring` (≤ 30 días, incluye «vence hoy») | `expired`.

## 2. Certificados de calibración

Tabla nueva `sensor_calibration_certificates` (migración `2026_11_16_000001`). Archivos en el disco
privado `monitoreo` (`storage/app/monitoreo/sensors/{id}`). Un solo certificado vigente por equipo
(`is_current`); al cargar uno nuevo el anterior queda en el historial (`replaced_at`).

| Método | Ruta | Permiso | Notas |
|---|---|---|---|
| POST | `/sensors` | `sensores.crear` | Opcional multipart con `certificate_file`, `certificate_expires_at` (ambos o ninguno), `certificate_calibrated_at`, `certificate_certificate_number`, `certificate_laboratory`, `certificate_notes` |
| GET | `/sensors/{id}/calibrations` | `sensores.ver` | Vigente primero; los reemplazados traen `status: "replaced"` |
| POST | `/sensors/{id}/calibrations` | `sensores.editar` | multipart: `file` (PDF/JPG/PNG ≤ 10 MB), `expires_at`, `calibrated_at?`, `certificate_number?`, `laboratory?`, `notes?` |
| GET | `/calibrations/{id}/download` | `sensores.ver` | Descarga el archivo |
| DELETE | `/calibrations/{id}` | `sensores.editar` | Borrado lógico (para cargas por error). Si era el vigente, el más reciente del historial vuelve a ser vigente |

Todas validan el acceso al equipo (`user_sensor`, derivado del almacén).

### Alertas

Comando `sga:check-sensor-calibrations`, diario 06:20. Usa los canales de siempre (`database` + `mail`).
Avisa en hitos — 30, 15, 7, 3, 1 y 0 días antes — y, ya vencido, cada 7 días hasta cargar el reemplazo.
Destinatarios: roles de `sga.notification_recipients.sensor_calibration_alert` (administrador,
jefe_almacen + super_administrador) **y** los usuarios asignados a cada equipo. Una notificación por
usuario: `type: sensor_calibration_alert`, `expiring[]`, `expired[]`, `severity` (`critical` si hay vencidos).

## 3. Último registro al registrar una lectura

`GET /sensors/{id}/readings/latest` (`lecturas.ver`) → `data` igual a `last_reading`, o `null`.
El diálogo «Registrar lectura» lo consulta al abrirse y muestra fecha/hora, valor y quién registró,
además del rango permitido de la zona. El registro sigue limitado a los usuarios del almacén.

## 4. Exportar a Excel (control estadístico)

`GET /sensors/{id}/readings/export?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD` (`lecturas.ver`),
máximo 366 días. El xlsx trae el encabezado del equipo (almacén, zona, rango, periodo), un resumen
(total, fuera de rango, mín/máx/promedio, σ, LCS/LCI, Cp/Cpk, puntos fuera de control) y una fila por
lectura (fecha, hora, valor, dentro/fuera de rango, estado SPC, origen, registrado por, fecha de registro).
En el front: botón «Exportar Excel» en la pestaña Control Estadístico, con el periodo Desde/Hasta.
