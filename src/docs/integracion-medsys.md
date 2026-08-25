# Plan de Integración con MedSys (Sistema de Citas)

> **Fecha de análisis:** 2026-08-19  
> **Estado:** Pendiente de revisión y aprobación

---

## Contexto

MedSys es el sistema de citas médicas externo que maneja pacientes, agendas y procedimientos. SGA-Bojanini maneja el inventario, consumos y órdenes de compra. La integración busca:

1. Buscar pacientes y sus citas desde SGA (por número de documento **o por nombre**).
2. Asociar el procedimiento de la cita con el servicio médico del inventario.
3. Usar las citas futuras + el historial de consumos para proyectar órdenes de compra anticipadas.
4. Sincronizar automáticamente la evolución clínica registrada en SGA hacia MedSys (incluyendo medicamentos inyectables aplicados).

La conexión a MedSys es **mayormente de lectura**. La excepción es la sincronización de evoluciones clínicas (Fase 7), que requiere permisos de escritura acotados sobre las tablas `sga_evoluciones` y `sga_medicamentos_inyectables`.

---

## Estructura de MedSys relevante

### Tablas de lectura

| Tabla | Descripción |
|---|---|
| `pacientes` | Datos del paciente: `codigo`, `tipodoc`, `documento`, `nombre1/2`, `apellido1/2` |
| `controles` | Citas: `codcontrol`, `idpaciente`, `codtipocontrol`, `fecha`, `hora`, `estado`, `sede` |
| `tiposproc` | Tipos de procedimiento/servicio: `codigo`, `descripcion`, `activo` |
| `estadoscita` | Estados posibles de una cita (ver tabla abajo) |
| `vpacientes` | **Vista ya existente** que une las 4 tablas anteriores |

### Tabla destino en MedSys (escritura desde SGA — pendiente de definición por MedSys)

MedSys creará en su momento la tabla que recibirá las evoluciones. SGA escribirá en ella los siguientes campos mínimos:

| Campo esperado | Tipo | Descripción |
|---|---|---|
| `idpaciente` | `varchar(10)` | Código del paciente en MedSys |
| `codcontrol` | `varchar(10)` | Código de la cita asociada |
| `fecha` | `date` | Fecha de la evolución |
| `hora` | `varchar(20)` | Hora de la evolución |
| `evolucion` | `text` | Texto completo (evolución + párrafo de inyectables si aplica) |
| `usuario` | `varchar(50)` | Nombre del profesional que registró en SGA |
| `sga_evolucion_id` | `int unsigned` | ID del registro en SGA (para sincronizaciones idempotentes) |

### Estados de cita (`estadoscita`)

| Código | Descripción |
|---|---|
| `PEN` | Cita Agendada |
| `CNF` | Cita Confirmada |
| `CON` | En Consultorio |
| `ATN` | Cita Atendida |
| `INC` | Cita Incumplida |
| `CAN` | Cita Cancelada |
| `TRA` | Cita Re-Agendada |
| `FAC` | Facturado |

### Vista `vpacientes` (ya existe en MedSys)

```sql
SELECT
  pacientes.codigo        AS id,
  pacientes.tipodoc       AS tipodoc,
  pacientes.documento     AS documento,
  CONCAT(nombre1,' ',nombre2,' ',apellido1,' ',apellido2) AS nombre,
  controles.fecha         AS fecha,
  controles.hora          AS hora,
  tiposproc.descripcion   AS servicio,
  controles.codcontrol    AS idcita,
  estadoscita.descripcion AS estadocita
FROM controles
JOIN pacientes   ON controles.idpaciente     = pacientes.codigo
JOIN tiposproc   ON controles.codtipocontrol = tiposproc.codigo
JOIN estadoscita ON controles.estado         = estadoscita.codigo;
```

---

## Punto de enlace entre los dos sistemas

| Campo en MedSys | Campo en SGA |
|---|---|
| `pacientes.documento` | `patient_procedure_records.patient_document` |
| `pacientes.nombre1/2/apellido1/2` | Búsqueda por nombre (nuevo) |
| `tiposproc.codigo` | `medical_services.external_code` *(campo nuevo)* |
| `controles.codcontrol` | `patient_clinical_evolutions.appointment_code` |

---

## Fases de implementación

---

### Fase 1 — Conexión de base de datos (fundamento)

**Objetivo:** Que Laravel pueda consultar MedSys. Las fases 1–6 solo leen; la Fase 7 necesita escritura acotada.

**Tarea:** Agregar una segunda conexión en `config/database.php`:

```php
'medsys' => [
    'driver'    => 'mysql',
    'host'      => env('MEDSYS_DB_HOST', '127.0.0.1'),
    'port'      => env('MEDSYS_DB_PORT', '3306'),
    'database'  => env('MEDSYS_DB_DATABASE', 'medsys'),
    'username'  => env('MEDSYS_DB_USERNAME'),
    'password'  => env('MEDSYS_DB_PASSWORD'),
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
    'strict'    => false,
    'options'   => [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
    ],
],
```

Variables a agregar en `.env` (y en `.env.example`):

```
MEDSYS_DB_HOST=
MEDSYS_DB_PORT=3306
MEDSYS_DB_DATABASE=medsys
MEDSYS_DB_USERNAME=
MEDSYS_DB_PASSWORD=
```

> **Permisos del usuario de BD:** `SELECT` en todo el esquema + `INSERT/UPDATE/DELETE` únicamente en `sga_evoluciones` y `sga_medicamentos_inyectables`.

---

### Fase 2 — Migración: `external_code` en `medical_services`

**Objetivo:** Permitir mapear cada servicio médico de SGA con su equivalente en MedSys.

```php
Schema::table('medical_services', function (Blueprint $table) {
    $table->string('external_code', 20)->nullable()->unique()->after('code');
});
```

**Archivos a crear/modificar:**
- Nueva migración `2026_08_XX_000001_add_external_code_to_medical_services_table.php`
- Modelo `MedicalService`: agregar `external_code` al `$fillable`
- `MedicalServiceRequest`: agregar validación del campo (nullable, unique)
- `MedicalServiceResource`: exponer el campo en la respuesta

---

### Fase 3 — Capa de servicios para MedSys *(actualizado)*

**Objetivo:** Encapsular todas las consultas a MedSys en servicios dedicados.

#### `MedsysPatientService`

Soporta búsqueda por documento **o** por nombre del paciente.

```php
// app/Services/Medsys/MedsysPatientService.php

public function findByDocument(string $document): ?array
{
    return DB::connection('medsys')
        ->table('pacientes')
        ->where('documento', $document)
        ->select('codigo', 'tipodoc', 'documento',
            DB::raw("CONCAT(TRIM(nombre1),' ',TRIM(nombre2),' ',TRIM(apellido1),' ',TRIM(apellido2)) as nombre"))
        ->first();
}

public function findByName(string $name): Collection
{
    return DB::connection('medsys')
        ->table('pacientes')
        ->whereRaw(
            "CONCAT(TRIM(nombre1),' ',TRIM(nombre2),' ',TRIM(apellido1),' ',TRIM(apellido2)) LIKE ?",
            ['%' . $name . '%']
        )
        ->select('codigo', 'tipodoc', 'documento',
            DB::raw("CONCAT(TRIM(nombre1),' ',TRIM(nombre2),' ',TRIM(apellido1),' ',TRIM(apellido2)) as nombre"))
        ->limit(20)
        ->get();
}
```

#### `MedsysAppointmentService`

```php
// app/Services/Medsys/MedsysAppointmentService.php

public function getActiveAppointments(string $patientCode, string $date = null): Collection
{
    return DB::connection('medsys')
        ->table('controles as c')
        ->join('tiposproc as t', 'c.codtipocontrol', '=', 't.codigo')
        ->join('estadoscita as e', 'c.estado', '=', 'e.codigo')
        ->where('c.idpaciente', $patientCode)
        ->whereIn('c.estado', ['PEN', 'CNF', 'CON'])
        ->when($date, fn($q) => $q->whereDate('c.fecha', $date))
        ->select('c.codcontrol', 'c.fecha', 'c.hora',
                 't.codigo as codtipocontrol', 't.descripcion as servicio',
                 'e.descripcion as estado')
        ->orderBy('c.fecha')
        ->orderBy('c.hora')
        ->get();
}

public function getFutureAppointmentsGrouped(int $days = 30): Collection
{
    return DB::connection('medsys')
        ->table('controles as c')
        ->join('tiposproc as t', 'c.codtipocontrol', '=', 't.codigo')
        ->whereIn('c.estado', ['PEN', 'CNF'])
        ->whereBetween('c.fecha', [now()->toDateString(), now()->addDays($days)->toDateString()])
        ->groupBy('c.codtipocontrol', 't.descripcion')
        ->select('c.codtipocontrol', 't.descripcion as servicio', DB::raw('COUNT(*) as total_citas'))
        ->get();
}
```

---

### Fase 4 — Endpoints de búsqueda de pacientes/citas *(actualizado)*

**Objetivo:** Que el frontend pueda buscar un paciente en MedSys por documento **o por nombre** y ver sus citas.

#### Rutas nuevas (archivo `routes/api/medsys.php`):

```
GET /medsys/patients?document={doc}         → buscar por documento (exacto)
GET /medsys/patients?name={texto}           → buscar por nombre (parcial, devuelve lista)
GET /medsys/patients/{codigo}/appointments  → citas activas del paciente
```

#### Controlador `MedsysPatientController`:

```php
public function search(Request $request)
{
    $request->validate([
        'document' => 'required_without:name|string',
        'name'     => 'required_without:document|string|min:3',
    ]);

    if ($request->filled('document')) {
        $patient = $this->patientService->findByDocument($request->document);
        if (!$patient) {
            return response()->json(['message' => 'Paciente no encontrado'], 404);
        }

        $appointments = $this->appointmentService
            ->getActiveAppointments($patient->codigo, today()->toDateString())
            ->map($this->enrichAppointment(...));

        return response()->json([
            'patient'      => $patient,
            'appointments' => $appointments,
        ]);
    }

    // Búsqueda por nombre: devuelve lista de pacientes, sin citas
    $patients = $this->patientService->findByName($request->name);
    return response()->json(['patients' => $patients]);
}

private function enrichAppointment(object $appt): object
{
    $service = MedicalService::where('external_code', $appt->codtipocontrol)->first();
    $appt->medical_service_id   = $service?->id;
    $appt->medical_service_name = $service?->name;
    return $appt;
}
```

---

### Fase 5 — Pantalla de mapeo MedSys ↔ Medical Services (Admin)

**Objetivo:** Permitir que el administrador vincule cada `tiposproc` de MedSys con un `medical_service` de SGA.

**Flujo:**
1. El frontend lista todos los `tiposproc` activos de MedSys (nuevo endpoint `GET /medsys/procedure-types`).
2. Junto a cada uno muestra un selector para elegir el `medical_service` de SGA.
3. Al guardar, el backend actualiza `medical_services.external_code` con el código de MedSys.

---

### Fase 6 — Proyección de consumos para órdenes de compra

**Objetivo:** En el módulo de órdenes de compra, mostrar una proyección basada en citas futuras + historial.

```
proyección_insumo = (citas_futuras_por_servicio) × (promedio_histórico_unidades_por_cita)
```

El **promedio histórico** se calcula desde los movimientos de salida de SGA agrupados por `medical_service_id`.

---

### Fase 7 — Sincronización de evolución clínica *(nuevo)*

**Objetivo:** Cuando se registra una evolución clínica en SGA, enviarla a la tabla que MedSys habilitará para tal fin. Los medicamentos inyectables aplicados se incluyen como un párrafo adicional al final del texto de evolución (no en una tabla separada).

> **Nota:** La tabla destino en MedSys será creada y definida por el equipo de MedSys en su momento. El servicio de SGA concentra toda la lógica de escritura en un único método (`writeToMedsys`) para que, cuando MedSys confirme la estructura final (nombre de tabla, campos exactos), el ajuste sea mínimo y localizado.

#### Paso 1 — Servicio de sincronización

```php
// app/Services/Medsys/MedsysEvolutionService.php

public function syncEvolution(PatientClinicalEvolution $evolution): void
{
    $patient = $this->patientService->findByDocument($evolution->patient_document);
    if (!$patient) return;

    $this->writeToMedsys(
        patientCode: $patient->codigo,
        controlCode: $evolution->appointment_code ?? '',
        date:        $evolution->created_at->toDateString(),
        time:        $evolution->created_at->format('H:i:s'),
        text:        $this->buildEvolutionText($evolution),
        user:        auth()->user()?->name ?? 'sga',
        evolutionId: $evolution->id,
    );
}

private function buildEvolutionText(PatientClinicalEvolution $evolution): string
{
    $text = $evolution->content;

    // Productos marcados como inyectables en SGA
    $injectables = $evolution->medicationRecords()
        ->whereHas('productVariant.productGeneric',
            fn($q) => $q->where('administration_route', 'inyectable'))
        ->get();

    if ($injectables->isNotEmpty()) {
        $text .= "\n\nMedicamentos inyectables aplicados:";
        foreach ($injectables as $med) {
            $text .= "\n- {$med->productVariant->productGeneric->name}"
                   . " | Lote: {$med->productVariant->lot}"
                   . " | Vence: {$med->productVariant->expiration_date}"
                   . " | Cant: {$med->quantity}";
        }
    }

    return $text;
}

// ─── Punto de adaptación ───────────────────────────────────────────────────
// Cuando MedSys defina la tabla y los campos exactos, solo este método cambia.
private function writeToMedsys(
    string $patientCode,
    string $controlCode,
    string $date,
    string $time,
    string $text,
    string $user,
    int    $evolutionId,
): void {
    DB::connection('medsys')->table('sga_evoluciones')->updateOrInsert(
        ['sga_evolucion_id' => $evolutionId],
        [
            'idpaciente' => $patientCode,
            'codcontrol' => $controlCode,
            'fecha'      => $date,
            'hora'       => $time,
            'evolucion'  => $text,
            'usuario'    => $user,
            'creado_en'  => now(),
        ]
    );
}
```

#### Paso 2 — Observer para sincronización automática

```php
// app/Observers/PatientClinicalEvolutionObserver.php

public function saved(PatientClinicalEvolution $evolution): void
{
    dispatch(fn() => app(MedsysEvolutionService::class)->syncEvolution($evolution))
        ->afterResponse();
}

// Registrar en AppServiceProvider::boot()
PatientClinicalEvolution::observe(PatientClinicalEvolutionObserver::class);
```

> Los medicamentos inyectables son los productos con `administration_route = 'inyectable'` en SGA. Cuando no hay inyectables en la evolución, el texto se envía sin el párrafo adicional.

---

## Resumen de archivos a crear/modificar

| Archivo | Acción |
|---|---|
| `config/database.php` | Modificar — agregar conexión `medsys` |
| `.env` / `.env.example` | Modificar — variables `MEDSYS_DB_*` |
| `database/migrations/..._add_external_code_to_medical_services.php` | Crear |
| `app/Models/MedicalService.php` | Modificar — agregar `external_code` al fillable |
| `app/Services/Medsys/MedsysPatientService.php` | Crear |
| `app/Services/Medsys/MedsysAppointmentService.php` | Crear |
| `app/Services/Medsys/ConsumptionProjectionService.php` | Crear |
| `app/Services/Medsys/MedsysEvolutionService.php` | Crear (nuevo) |
| `app/Observers/PatientClinicalEvolutionObserver.php` | Crear (nuevo) |
| `app/Http/Controllers/Medsys/MedsysPatientController.php` | Crear |
| `app/Providers/AppServiceProvider.php` | Modificar — registrar observer |
| `routes/api/medsys.php` | Crear |
| `app/Http/Requests/MedicalServiceRequest.php` | Modificar — validar `external_code` |
| `app/Http/Resources/MedicalServiceResource.php` | Modificar — exponer `external_code` |
| MedSys BD — tabla destino | **Responsabilidad de MedSys** — SGA solo necesita el nombre de la tabla y los campos al momento de implementar |

---

## Orden de ejecución recomendado

```
Fase 1 → Fase 2 → Fase 3 → Fase 4 → Fase 5 → Fase 6 → Fase 7
  BD       Campo    Servicios  Búsqueda  Mapeo  Proyección  Evolución
```

Las fases 1–3 son prerrequisitos de todas las demás. Las fases 4–7 son independientes entre sí una vez completadas las primeras tres.

---

## Consideraciones adicionales

- **Codificación:** MedSys usa `latin1` (MySQL 5.5). Usar `PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"` para evitar problemas con tildes y ñ. Las tablas nuevas se crean en `latin1` siguiendo la convención del esquema.
- **Acceso de escritura acotado:** El usuario de BD de MedSys debe tener `SELECT` en todo el esquema y `INSERT/UPDATE/DELETE` únicamente en la tabla destino que MedSys defina para las evoluciones clínicas.
- **Sin transacciones entre BDs:** No usar transacciones que crucen las dos conexiones — cada BD es independiente.
- **Fallos de conexión:** Si MedSys no está disponible, los flujos de SGA deben seguir funcionando. El observer usa `afterResponse()` para no bloquear la respuesta. Capturar excepciones dentro de `syncEvolution()` y registrarlas en el log.
- **Citas sin mapeo:** Cuando una cita tiene un `codtipocontrol` sin `external_code` en SGA, mostrarlo con advertencia y enlace a la pantalla de mapeo.
- **Búsqueda por nombre:** Usa `LIKE '%texto%'` sobre los cuatro campos concatenados. Si hay problemas de rendimiento, agregar índice `FULLTEXT` o aumentar el mínimo de 3 caracteres.
