# Plan de implementación — Módulo de Citas (Call Center)

> Estado: **borrador para revisión**. No se ha escrito código de producción.
> Base del análisis: `backup_medsys_20260919_1134.sql.gz` (226 tablas, 430.561 citas).
> Fecha del análisis: 2026-09-19.

---

## 1. Resumen ejecutivo

Se construirá un módulo de citas dentro de SGA para que los consultores del call center
agenden, reagenden, cancelen y hagan seguimiento de citas **operando directamente sobre la
base de datos de MedSys**, sin base de datos espejo.

El principio rector, definido por el cliente, es **paridad total y bidireccional**: una cita
gestionada desde SGA debe verse y comportarse en MedSys exactamente igual que una gestionada
desde MedSys, y viceversa. La base de datos de MedSys es la única fuente de verdad; SGA es
una herramienta alterna sobre esa misma base.

---

## 2. Mapa de MedSys (hallazgos del análisis)

### 2.1 Las tablas que importan

| Tabla | Filas | Rol real | Uso en SGA |
|---|---|---|---|
| `controles` | 430.561 | **La tabla de citas.** Comentario MySQL: *"Control de Citas"* | Lectura + escritura |
| `agendas` | 50 (24 activas) | Recurso agendable + granularidad + ventana laboral | Lectura |
| `horario` | 86.439 | **Bloqueos** por rango horario y fecha (`nl` = no laboral) | Lectura |
| `anotaciones_citas` | 249.275 | **Bitácora de gestión del call center** | Lectura + escritura |
| `pacientes` | 86.425 | Pacientes | Lectura + escritura |
| `tiposproc` | 192 (~110 activos) | Servicios / tipos de procedimiento | Lectura |
| `estadoscita` | 9 | Catálogo de estados | Lectura |
| `razonescan` | 2 | Motivos de cancelación | Lectura |
| `sedes` | 1 (`C94`) | Sedes | Lectura |
| `usuarios` | 154 | Validación del mapeo de login | Lectura |
| `calendario` | 14 | Días no laborables — casi abandonada | Lectura (complemento) |

### 2.2 Tablas descartadas y por qué

- **`agendarecursos`** (102.602) — degradada. `codrecurso` vacío en 98.521 filas y `hora`
  contiene literales basura (`DATEHOUR3`, `datehour10`). Es un bug de plantilla del sistema legado.
- **`agendausuario`** — 0 filas. No se usa para restringir visibilidad.
- **`controlseg`, `encuestas`** — 0 filas.
- **`tareasseguimiento`, `oportunidadventa`, `campañas`, `enviosemail`, `seguimientocontactos`,
  `logcrm`, `llamadas`, `contactos`** — el módulo CRM de MedSys está muerto. Última actividad
  relevante entre 2011 y 2025. No replicar.
- **`historiaclinica`, `usuarios` (escritura)** — fuera de alcance, nunca tocar.

### 2.3 Estructura de una cita (`controles`)

| Campo | Significado |
|---|---|
| `codcontrol` varchar(10) PK | ID de cita, formato propietario `_7KB0LZCSX` |
| `idpaciente` varchar(20) | → `pacientes.codigo` |
| `agenda` varchar(10) | → `agendas.codigo` — el recurso agendable |
| `codtipocontrol` varchar(10) | → `tiposproc.codigo` — **el servicio** |
| `fecha` date + `hora` varchar(10) | Inicio. `hora` es texto `'HH:MM:SS'` |
| `duracion` int DEFAULT 15 | **Minutos**, no bloques |
| `estado` char(3) | → `estadoscita` |
| `sede` varchar(10) | → `sedes.codigo` |
| `usuarioreg`, `fechareg`, `horaregistro` | **Auditoría: quién agendó** |
| `fechasolicitada` | Fecha que pidió el paciente (≠ asignada). 86% poblado |
| `razonnoaten`, `obsnoaten` | Motivo y observación de no atención |
| `codtraslado` | Al reagendar, apunta a la cita **destino** |
| `horallegada`, `horaatencion` | Marcas de recepción |
| `primeravez` | 0/1 |

**No existe campo de "quién modificó".** Es el hallazgo que obliga a escribir en
`anotaciones_citas`: sin eso, cancelar o reagendar desde SGA no deja rastro de autoría en MedSys.

### 2.4 Ciclo de vida y estados

```
PEN (Agendada) → CNF (Confirmada) → CON (En Consultorio) → ATN (Atendida) → FAC (Facturado)
                      ↓
        CAN (Cancelada) · INC (Incumplida) · TRA (Re Agendada → nace otra cita)

NOT = anotación o cita no médica (bloqueo dentro de la agenda)
```

Uso real (histórico): `ATN` 231.560 · `TRA` 69.728 · `CAN` 51.290 · `INC` 37.296 ·
`PEN` 29.819 · `CON` 7.484 · `CNF` 2.392 · `FAC` 12.

**Estados sucios fuera del catálogo:** `NAT` (978), `ANU` (1), `atn` en minúscula (1).
SGA los tratará como ocupantes del bloque (criterio conservador) y los registrará como anomalía.

### 2.5 Bloques de tiempo — la premisa matizada

**No hay filas pre-generadas por bloque.** La cita guarda hora de inicio + duración en minutos.
Una cita que ocupa varios bloques es simplemente `duracion = 30 / 45 / 60`.

`agendas.citascada` define la granularidad **por agenda**: 35 de 50 usan 15 minutos; el resto
usa 10, 20, 30, 45 o 60. Duraciones reales observadas: 15 (118k), 60 (106k), 10 (59k), 30 (57k),
0 (40k), 5 (28k), y colas de 45, 90, 120, 180, 240.

`tiposproc.tiempo` da la duración **sugerida** por servicio, pero el operador la sobrescribe:
en una muestra de 8.000 citas de 2026, 3.167 coinciden y 4.760 difieren.

> **Decisión de diseño:** SGA respetará `agendas.citascada` como grilla (no un 15 fijo), y
> propondrá `tiposproc.tiempo` como duración por defecto, editable por el consultor en múltiplos
> de la grilla. Esto cumple el requisito de bloques de 15 minutos allí donde la agenda los usa,
> sin romper las agendas configuradas con otra granularidad.

### 2.6 Bloqueos de agenda

`horario` es el mecanismo real (86.439 filas). PK compuesta por
`(agenda, horanl_ini, minnl_ini, horanl_fin, minnl_fin, fecha)`. Define el rango **no laboral**
en una fecha concreta, con `anotacion` de texto libre sin catálogo:

`ALMUERZO` (14.731) · `BLOQUEO` (14.418) · `SALIDA` (13.000) · `BLOQUEO SALIDA` (10.213) ·
`BLOQUEO ENTRADA` (5.185) · `BLOQUEO AGENDA` (4.019) · `BLOQUEO ALMUERZO` (3.753) ·
`BLOQUEO VACACIONES` (984) · `FESTIVO` (611).

Un día completo se bloquea con un rango amplio (ej. `02:00 → 20:00`).
La ventana laboral base sale de `agendas.hr_inicio_c` / `hr_fin_c`.

SGA **solo lee** estos bloqueos, conforme al requisito. No los crea ni los modifica.

### 2.7 Reagendamiento — encadenamiento, nunca borrado

La cita original queda en `TRA` y su `codtraslado` apunta al `codcontrol` de la cita nueva.
El puntero es unidireccional. Reagendar varias veces forma cadenas recursivas:

```
C000114811 (TRA) → _5JF0OFNUI (TRA) → _5JF0V5XML (PEN)
```

**El 26% de las citas de 2026 se reagendan** (10.982 de 42.300). Es el segundo caso de uso más
pesado después de agendar, y el módulo debe optimizarlo explícitamente.

> **Trampa:** 4.732 citas en estado `ATN` también traen `codtraslado` (residuo histórico).
> Nunca inferir `codtraslado ⇒ TRA`.

### 2.8 Identidad de usuario y auditoría

```sql
CREATE TABLE `usuarios` (
  `login`    varchar(20) NOT NULL DEFAULT '',   -- PK, no hay id numérico
  `password` varchar(20) NOT NULL DEFAULT '',   -- cifrado reversible propietario
  `nombre`   varchar(100) NOT NULL DEFAULT '',
  `perfil`   char(2) NOT NULL DEFAULT '',
  PRIMARY KEY (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

Todas las tablas estampan **el string `login`**, no un identificador numérico.
Perfil `10` = "Call" con 6 usuarios activos. Perfil `07` = "Inactivo" (97 de 154 usuarios).

> **SGA nunca autentica contra MedSys ni toca la tabla `usuarios`.** La contraseña usa cifrado
> reversible propietario, no un hash. SGA autentica con su propia seguridad y solo mantiene el
> mapeo `usuario SGA → login MedSys`.

---

## 3. Decisiones tomadas

| # | Decisión | Definida por |
|---|---|---|
| D1 | Usuarios de la v1: consultores de call center + rol coordinador/supervisor | Cliente |
| D2 | **Siempre en vivo** contra MedSys. Sin base espejo | Cliente |
| D3 | Sin notificaciones al paciente en la v1 | Cliente |
| D4 | SGA **nunca** permite sobrecupo ni agendar sobre bloqueo | Cliente |
| D5 | Replicar el algoritmo de IDs + verificar inexistencia antes de insertar, con reintento | Cliente |
| D6 | Probar contra **réplica restaurada** antes de apuntar a producción | Cliente |
| D7 | Cada gestión escribe una fila en `anotaciones_citas` con el login del consultor | Cliente |
| D8 | Marcar el origen SGA de cada escritura | Cliente |
| D9 | **Paridad total bidireccional**: sin campos que solo SGA entienda, sin filtros ni restricciones al leer lo que MedSys creó | Cliente |
| D10 | La grilla la define `agendas.citascada`, no un 15 fijo | Análisis |
| D11 | El registro de origen vive en la base de **SGA**, no en MedSys — cero DDL sobre MedSys | Análisis |

### 3.1 Decisiones deliberadamente NO tomadas

Se excluyeron de la v1 y quedan disponibles para revertir:

- **No se escribe en `logusuarios`.** MedSys registra ahí cada alta/modificación/cancelación de
  cita en texto libre. Al no escribirlo, el log nativo tendrá vacíos para lo hecho desde SGA.
  *Motivo adicional:* su PK es únicamente `fecha` (datetime, resolución de 1 segundo), lo que
  introduce riesgo de colisión. La autoría queda igualmente cubierta por `anotaciones_citas`.
- **No se corrige la semántica de `CNF`.** Hoy solo 304 citas están confirmadas frente a 25.909
  anotaciones que dicen "CONFIRMA": los consultores anotan pero no cambian el estado. SGA
  replicará el comportamiento actual para no alterar los reportes de MedSys. Es la mejora de
  mayor impacto disponible para una fase posterior.

---

## 4. Arquitectura

Módulo nuevo `src/app/Modules/Appointments/`, siguiendo Clean Architecture como `CostCenter`.

```
Appointments/
├── Domain/
│   ├── Entities/          Appointment · Agenda · Slot · ScheduleBlock
│   │                      Patient · AppointmentNote · RescheduleChain
│   ├── Enums/             AppointmentStatus · CancellationReason
│   ├── ValueObjects/      MedsysId · MedsysLogin · TimeRange
│   ├── Repositories/      AppointmentRepositoryInterface · AgendaRepositoryInterface
│   │                      PatientRepositoryInterface · ScheduleBlockRepositoryInterface
│   │                      AppointmentNoteRepositoryInterface
│   └── Services/          SlotAvailabilityService · BookingValidator
│                          RescheduleChainResolver
├── Application/
│   ├── DTOs/              BookAppointmentData · RescheduleData · CancelData
│   │                      PatientData · NoteData
│   └── UseCases/          BookAppointment · RescheduleAppointment · CancelAppointment
│                          RegisterNote · CreatePatient · UpdatePatient
│                          GetDayAgenda · FindAvailability · SearchPatient
└── Infrastructure/
    ├── Http/Controllers/  AppointmentController · PatientController
    │                      AgendaController · CatalogController · MetricsController
    ├── Http/Requests/     BookAppointmentRequest · Reschedule… · Cancel… · StorePatient…
    ├── Http/Resources/    AppointmentResource · DayAgendaResource · SlotResource
    ├── Persistence/
    │   ├── Medsys/        MedsysAppointmentRepository · MedsysAgendaRepository
    │   │                  MedsysPatientRepository · MedsysScheduleBlockRepository
    │   │                  MedsysNoteRepository
    │   └── Models/        MedsysWriteLogModel   (tabla propia de SGA)
    └── Support/           MedsysIdGenerator · MedsysTextCodec
                           MedsysSlotLock · MedsysWriteLogger
```

Las interfaces de repositorio en `Domain` permiten implementaciones falsas en memoria para las
pruebas, que es lo que resuelve el problema de que la conexión `medsys` no exista en el entorno
de test.

Bindings en `app/Providers/ModuleServiceProvider.php` (array `$bindings`).

### 4.1 Relación con el módulo `Integration` existente

`Integration/Infrastructure/ExternalServices/MedsysAppointmentService.php` ya lee `controles`,
`tiposproc` y `estadoscita`, y define `ACTIVE_STATES = ['PEN','CNF','CON']`.

> **Decisión pendiente de tu criterio** (ver §11): el módulo nuevo puede *reutilizar* esa clase
> o *reemplazarla*, migrando sus tres consumidores actuales a los repositorios nuevos. La segunda
> opción evita dos caminos de acceso a la misma tabla, a costa de tocar código que hoy funciona.

---

## 5. Componentes críticos

### 5.1 Generación de IDs (`MedsysIdGenerator`)

Algoritmo descifrado contra las 430.561 filas de `controles`:

```
_ + base36((año - 2000) * 367 + día_del_año)   → 3 chars, mayúsculas   [99,3% verificado]
  + base36(aleatorio en [0, 86.400.000))       → 6 chars, mayúsculas
```

El contador de días trata **todo año como 367 días**, por eso derivaba dos días por año
(uno en año bisiesto). El componente de tiempo **no** codifica la hora de creación: correlación
0,28 contra `horaregistro`, y de 45.223 registros creados en el mismo segundo exacto, ninguno
comparte valor. Es aleatorio, y no necesita ser reproducible — solo no colisionar.

**Riesgo de colisión: ~15% acumulado anual** (mediana 139 IDs/día, p99 340).
La verificación previa es obligatoria:

```
generar():
    para intento en 1..5:
        id = construir()
        si no existe en la tabla destino: devolver id
    lanzar excepción y alertar          # nunca degradar silenciosamente
```

Mismo algoritmo para `pacientes.codigo` (99,96% de consistencia validada contra la fecha de la
primera cita de cada paciente).

**Formatos legados que hay que leer pero nunca generar:** `C#########` secuencial
(46.183 citas entre 2013 y 2019) y ~14.600 códigos numéricos en `pacientes`.

### 5.2 Normalización (`MedsysTextCodec`)

MedSys no tiene constraints y sus datos están sucios de forma sistemática. Esta capa es
obligatoria en **toda** lectura y escritura:

| Problema | Regla |
|---|---|
| Padding con espacios (`'C94       '`, `'01  '`, `'65633406      '`) | `TRIM()` en toda comparación y lectura |
| Login con espacios finales (18 casos: `'spgarzon  '`) | `TRIM()` antes de estampar y comparar |
| Login case-inconsistente (`khatem` vs `Khatem`) | Comparar en minúsculas; MySQL latin1 es insensible, PHP no |
| Truncamiento a varchar(10) (`anotaciones_citas.usuario`) | Truncar a 10 al escribir, igual que MedSys. **No hacer join directo por login** |
| Mojibake latin1/UTF-8 (`APLICACIÃN` = `APLICACIÓN`) | Resolver en Fase 0 contra la réplica |
| Estados fuera del catálogo (`NAT`, `ANU`, `atn`) | Tratar como ocupantes; registrar anomalía |
| Registros de atención mezclados con citas (`codtipocontrol` vacío, `duracion=0`) | Filtrar al listar agenda; mostrar al consultar historial |

### 5.3 Motor de disponibilidad (`SlotAvailabilityService`)

Un bloque está libre si cumple **todas** las condiciones:

1. Cae dentro de `agendas.hr_inicio_c` … `hr_fin_c`
2. Está alineado a la grilla `agendas.citascada`
3. No solapa ningún rango de `horario` para esa agenda y fecha
4. La fecha no está en `calendario` para esa agenda
5. No solapa ninguna cita en estado `PEN`, `CNF`, `CON`, `NOT` ni estado sucio desconocido
6. La duración solicitada cabe completa sin violar 1–5

### 5.4 Concurrencia (`MedsysSlotLock`)

No se puede añadir un índice único a `controles`: rompería MedSys y los datos actuales ya lo
violarían (2.226 de 20.093 combinaciones agenda/fecha/hora de 2026 tienen más de una cita activa).

Estrategia: bloqueo nombrado en el servidor MySQL de MedSys, que coordina a todas las instancias
de SGA:

```
GET_LOCK('sga:slot:{agenda}:{fecha}:{hora}', 5)
  → revalidar disponibilidad dentro del lock
  → insertar
RELEASE_LOCK(...)
```

**Riesgo residual aceptado y documentado:** la aplicación nativa de MedSys no toma este bloqueo.
Existe una ventana de milisegundos en que MedSys podría ocupar el mismo bloque. El peor caso es
exactamente lo que MedSys ya permite hoy de forma rutinaria.

### 5.5 Registro de origen y auditoría propia (`MedsysWriteLogger`)

Tabla nueva **en la base de SGA** (no en MedSys):

```
medsys_write_log
  id · entidad (cita|paciente|anotacion) · medsys_id · accion (crear|reagendar|cancelar|anotar|actualizar)
  sga_user_id · medsys_login_usado · payload_json · resultado · created_at
```

Cubre el requisito D8 (marcar origen SGA), da trazabilidad completa del lado de SGA y permite
reconstruir o revertir lo escrito. Al vivir en SGA, **no requiere ningún DDL sobre la base de
MedSys**, que es una reducción de riesgo sustancial.

### 5.6 Mapeo de usuarios

Migración que añade a `users`:

```
medsys_login  varchar(20) nullable unique
```

Regla dura: **un usuario sin `medsys_login` válido no puede ejecutar ninguna operación de
escritura.** Sin eso la auditoría en MedSys quedaría anónima, incumpliendo el requisito 6.
El login se valida contra `usuarios` de MedSys al asignarlo.

---

## 6. Roles y permisos

Siguiendo el patrón de `database/seeders/RolesAndPermissionsSeeder.php`
(spatie/laravel-permission, formato `recurso.accion` en español).

**Permisos nuevos:**

```
citas.ver · citas.crear · citas.reagendar · citas.cancelar · citas.anotar
citas.agenda.ver · citas.metricas.ver
citas.pacientes.ver · citas.pacientes.crear · citas.pacientes.editar
```

**Roles nuevos:**

| Rol | Permisos |
|---|---|
| `call_center` | `citas.ver`, `citas.crear`, `citas.reagendar`, `citas.cancelar`, `citas.anotar`, `citas.agenda.ver`, `citas.pacientes.*` |
| `coordinador_citas` | Todo lo de `call_center` + `citas.metricas.ver` |

El rol `call_center` se modela sobre `personal_medico` (lista explícita y restringida), de modo
que **no puede acceder a ninguna otra funcionalidad de SGA** salvo asignación expresa, conforme
al requisito 8.

**Menú:** nueva sección de primer nivel en
`app/Modules/Shared/Application/Services/MenuBuilder.php` vía `buildAppointmentsSection($has)`.
Como grupo propio y no anidado bajo Inventario o Gestión, para que el consultor no vea ramas
vacías. Actualizar `tests/Feature/MenuTest.php`.

---

## 7. API

Prefijo `v1`, middleware `['auth:sanctum', 'user.is_active']`, permiso por ruta.
Respuestas con el trait `ApiResponse` (`{success, message, data}`).

**Catálogos**
```
GET  /v1/citas/agendas                    recursos agendables activos
GET  /v1/citas/servicios                  tiposproc activos
GET  /v1/citas/sedes
GET  /v1/citas/estados
GET  /v1/citas/motivos-cancelacion
```

**Agenda y disponibilidad**
```
GET  /v1/citas/agenda/{agenda}/dia?fecha=          citas + bloqueos del día
GET  /v1/citas/agenda/{agenda}/semana?desde=
GET  /v1/citas/disponibilidad?agenda=&fecha=&duracion=
GET  /v1/citas/proximo-disponible?servicio=&desde=  primer hueco entre todas las agendas
```

**Pacientes**
```
GET  /v1/citas/pacientes?documento=|nombre=
GET  /v1/citas/pacientes/{codigo}
POST /v1/citas/pacientes
PUT  /v1/citas/pacientes/{codigo}
```

**Citas**
```
GET  /v1/citas/{codcontrol}
GET  /v1/citas/{codcontrol}/cadena              cadena de reagendamientos
GET  /v1/citas/{codcontrol}/anotaciones
GET  /v1/citas/paciente/{codigo}                historial del paciente
POST /v1/citas                                  agendar
POST /v1/citas/{codcontrol}/reagendar
POST /v1/citas/{codcontrol}/cancelar
POST /v1/citas/{codcontrol}/anotaciones         registrar seguimiento
```

**Coordinador**
```
GET  /v1/citas/metricas/ocupacion
GET  /v1/citas/metricas/gestion
```

---

## 8. Flujo del consultor (requisito 7: curva de aprendizaje corta)

El diseño parte de que el 26% del trabajo es reagendar y el grueso restante es confirmar.

**Agendar** — una sola pantalla, cuatro pasos sin navegación intermedia:
1. Buscar paciente por documento. Si no existe, crearlo en el mismo formulario sin salir del flujo.
2. Elegir servicio → el sistema propone duración y filtra las agendas que lo atienden.
3. Elegir fecha → se muestra la rejilla del día con libres, ocupados y bloqueados diferenciados.
4. Confirmar → se crea la cita y su anotación inicial en un solo paso.

**Reagendar** — desde la cita existente, un botón que reabre el paso 3 conservando paciente y
servicio. El encadenamiento `TRA` + `codtraslado` es automático e invisible para el consultor.

**Cancelar** — motivo obligatorio desde `razonescan` (paciente / médico) + observación libre.

**Seguimiento** — campo de anotación siempre visible en la ficha de la cita, con el historial
cronológico debajo. **La anotación vacía se rechaza**, corrigiendo el defecto de MedSys donde el
20,6% de las anotaciones de 2026 se guardaron en blanco.

> **Nota sobre paridad (D9):** la vista de agenda muestra sin filtrar todo lo que exista en
> MedSys, incluidos los solapamientos que MedSys permitió y SGA nunca crearía. Se señalan
> visualmente como conflicto, pero jamás se ocultan.

---

## 9. Fases de implementación

### Fase 0 — Réplica y preparación *(prerrequisito, sin código de aplicación)*
- Restaurar el backup en un MySQL local como `medsys_replica`
- Resolver la decisión de charset/mojibake con datos reales
- **Verificar los índices de `controles`** para la consulta `agenda + fecha` (430k filas)
- Crear en MedSys un usuario de base de datos restringido: solo las tablas de §2.1 y solo los
  verbos necesarios
- Configurar el flag de habilitación (patrón de `config/sga.php:31-34`, por defecto apagado)

### Fase 1 — Núcleo de lectura *(riesgo cero, ya entrega valor)*
- Entidades de dominio e interfaces de repositorio
- `MedsysTextCodec` + repositorios de lectura
- `SlotAvailabilityService`
- Endpoints de catálogos, agenda y disponibilidad
- Roles, permisos y menú
- Búsqueda y consulta de pacientes

### Fase 2 — Pacientes *(primera escritura)*
- `MedsysIdGenerator` con verificación y reintento
- Alta y edición en `medsys.pacientes`
- Detección de duplicados por documento antes de crear
- `MedsysWriteLogger`

### Fase 3 — Agendar
- `BookingValidator` con las seis reglas de §5.3
- `MedsysSlotLock`
- Alta en `controles` + anotación inicial automática

### Fase 4 — Gestión
- Reagendar (`TRA` + `codtraslado`) y resolución de cadenas
- Cancelar (`CAN` + `razonnoaten` + `obsnoaten`)
- Registro de seguimientos en `anotaciones_citas`

### Fase 5 — Coordinador
- Métricas de ocupación, canceladas, reagendadas y actividad por consultor
- Todo de solo lectura sobre datos de MedSys

### Fase 6 — Producción
- Validación en modo simulación contra producción
- Piloto acotado
- Despliegue completo

---

## 10. Estrategia de pruebas

El problema: `phpunit.xml` fuerza SQLite en memoria y **la conexión `medsys` no existe en el
entorno de test**. Hoy ningún servicio de MedSys tiene cobertura.

Tres niveles:

1. **Unitarias puras** — dominio y casos de uso contra repositorios falsos en memoria.
   Cubren toda la lógica de disponibilidad, validación y encadenamiento. Sin base de datos.
2. **De integración** — segunda conexión SQLite llamada `medsys` en `phpunit.xml`, con un helper
   que crea el subconjunto de esquema de §2.1. Los fixtures deben incluir **datos sucios reales**:
   padding, mojibake, estados `NAT`, IDs legados `C#########` y bloques con sobrecupo preexistente.
3. **De contrato** — suite aparte contra `medsys_replica`, ejecutada manualmente o en CI opcional.
   Valida que las columnas y tipos esperados sigan existiendo. Es la red de seguridad frente a un
   cambio de esquema de MedSys, que es software de terceros y puede cambiar sin aviso.

Convención existente: `use RefreshDatabase`, `setUp()` siembra `RolesAndPermissionsSeeder`,
métodos en español, helper `$this->bearerTokenFor($user)`.

---

## 11. Riesgos

| # | Riesgo | Severidad | Mitigación |
|---|---|---|---|
| R1 | Escritura sobre la producción de un sistema de terceros | Alta | Réplica primero (D6), flag, usuario de BD restringido, `medsys_write_log` |
| R2 | Colisión de IDs (~15% anual) | Alta | Verificación previa + reintento; fallo ruidoso, nunca silencioso |
| R3 | Carrera con la app nativa de MedSys | Media | `GET_LOCK` + revalidación. Residual aceptado: es lo que MedSys ya hace hoy |
| R4 | Mojibake latin1/UTF-8 al escribir nombres con tildes | Media | Resolver en Fase 0 contra réplica antes de cualquier escritura |
| R5 | MedSys cambia su esquema sin aviso | Media | Tests de contrato + validación de columnas al arrancar |
| R6 | Rendimiento sobre `controles` (430k filas) | Media | Verificar índices en Fase 0. Si faltan, el DDL sobre MedSys requiere aprobación expresa |
| R7 | Caída de MedSys deja el módulo inoperante | Media | Aceptado por D2. Mensaje de error explícito, sin degradación silenciosa |
| R8 | Ausencia total de FKs en MedSys | Baja | Toda integridad se valida en la aplicación |

---

## 12. Puntos abiertos

1. **`MedsysAppointmentService` existente** — ¿lo reutilizamos o migramos sus tres consumidores
   actuales a los repositorios nuevos? (§4.1)
2. **Índices sobre `controles`** — si la consulta por `agenda + fecha` resulta lenta y hace falta
   crear un índice, es DDL sobre producción de terceros y necesita aprobación explícita. (R6)
3. **Restricción profesional ↔ servicio** — no existe en MedSys: una agenda registra hasta 36
   servicios distintos y un servicio lo atienden hasta 24 agendas. ¿El call center debe poder
   agendar cualquier servicio en cualquier agenda, o SGA define su propia tabla de compatibilidad?
4. **Agendas que son equipos** — `Tesla`, `Indiba`, `LASER: CO2`, `ULTHERAPY`, `PRESOTERAPIA`,
   `Implante Capilar` son recursos, no personas. ¿El call center los agenda también?
5. **Restricción por sede** — hoy solo existe `C94`, y `sedeusuario` asigna sede a los 6 usuarios
   Call. El diseño no la hardcodea, pero ¿se aplica ya como filtro o se deja preparada?
6. **Anotaciones duplicadas** — cuando un paciente tiene varias citas, MedSys replica la misma
   nota en todos sus `codcontrol` con idéntico timestamp. ¿SGA lo replica o escribe solo en la
   cita gestionada?
