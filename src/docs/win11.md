# SGA Bojanini — Desarrollo y pruebas en Windows 11 con Laragon

Guía para levantar el backend Laravel en **Windows 11** usando **Laragon**, sin Docker.  
La **Fase 11** (despliegue en Windows Server, NSSM, optimización) queda fuera de alcance por ahora; aquí solo se cubre un entorno local de pruebas.

---

## 1. Qué necesitas entender del repositorio

El repositorio tiene dos capas:

| Ubicación | Contenido | ¿Usar en Laragon? |
|-----------|-----------|-------------------|
| Raíz `sga-bojanini/` | `docker/`, `docker-compose.yml`, `Makefile` | No (solo Docker/Linux) |
| **`src/`** | Aplicación Laravel (`app/`, `routes/`, `composer.json`, `.env`) | **Sí — esta es la raíz de Laravel** |

Todas las órdenes `php`, `composer` y `artisan` se ejecutan **dentro de `src/`**:

```text
C:\laragon\www\sga-bojanini\          ← clon del repo
└── src\                              ← cd aquí siempre
    ├── app\
    ├── public\                       ← DocumentRoot del sitio
    ├── .env
    └── composer.json
```

---

## 2. Requisitos en Windows 11

### 2.1 Laragon

- Descargar e instalar [Laragon](https://laragon.org/) (Full preferible: incluye Apache/Nginx, MySQL, HeidiSQL).
- En **Menu → PHP → Version** elegir **PHP 8.3** o superior (el proyecto exige `^8.3` en `composer.json`).
- En **Menu → MySQL** usar **MySQL 8.0** (incluido en Laragon).

### 2.2 Herramientas adicionales

| Herramienta | Uso |
|-------------|-----|
| **Git** | Clonar el repositorio |
| **Composer** | Laragon suele traerlo; si no, [getcomposer.org](https://getcomposer.org/) |
| **Terminal** | PowerShell, CMD o la terminal integrada de Laragon |

### 2.3 Extensiones PHP (obligatorias)

En Laragon: **Menu → PHP → php.ini** y verificar que estén **habilitadas** (sin `;` al inicio):

```ini
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
extension=bcmath
extension=exif
```

Opcional (no bloquea el arranque básico): `redis`. El proyecto usa por defecto caché/colas en **base de datos**, no Redis.

> **Nota:** La extensión `pcntl` **no existe en Windows**. En Docker sí se instala; en Laragon no hace falta para desarrollo normal (`artisan serve`, `queue:work`, tests).

Reinicia Apache/Nginx después de cambiar `php.ini` (**Menu → Apache/Nginx → Reload**).

---

## 3. Clonar el proyecto

```powershell
cd C:\laragon\www
git clone https://github.com/barale2906/sga-bojanini.git
cd sga-bojanini\src
```

Si ya tienes el repo en otra ruta, copia o clona donde Laragon sirva sitios (por defecto `C:\laragon\www`).

---

## 4. Base de datos MySQL

### 4.1 Crear base y usuario (HeidiSQL o consola)

Abre **HeidiSQL** (Laragon → Database) o la consola MySQL de Laragon y ejecuta:

```sql
CREATE DATABASE IF NOT EXISTS sga_bojanini
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'sga_user'@'localhost' IDENTIFIED BY 'SgaBojanini2026!';
GRANT ALL PRIVILEGES ON sga_bojanini.* TO 'sga_user'@'localhost';
FLUSH PRIVILEGES;
```

Para pruebas rápidas puedes usar el usuario `root` de Laragon (a menudo sin contraseña); en producción no hagas eso.

### 4.2 Credenciales de referencia (Docker / documentación)

| Campo | Valor |
|-------|--------|
| Base de datos | `sga_bojanini` |
| Usuario | `sga_user` |
| Contraseña | `SgaBojanini2026!` |
| Host | `127.0.0.1` |
| Puerto | `3306` |

---

## 5. Configurar el sitio en Laragon

### 5.1 Virtual host (recomendado)

1. **Menu → Apache → sites-enabled → Manual** (o Nginx equivalente).
2. DocumentRoot debe apuntar a la carpeta **`public`** de Laravel:

   ```text
   C:/laragon/www/sga-bojanini/src/public
   ```

3. Laragon puede crear automáticamente `http://sga-bojanini.test` si detecta la carpeta; si el auto-host apunta a la raíz del repo, **corrige** el DocumentRoot a `...\src\public`.

4. **Menu → Apache/Nginx → Reload**.

URL típica: `http://sga-bojanini.test` (o el nombre que asigne Laragon).

### 5.2 Alternativa sin virtual host

Desde `src\`:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

API en `http://127.0.0.1:8000` (sin pretty URLs de Laragon; suficiente para pruebas rápidas).

---

## 6. Archivo `.env`

Desde `src\`:

```powershell
copy .env.example .env
```

Edita `src\.env` con valores para **Laragon** (no uses `DB_HOST=db`; eso es solo Docker):

```dotenv
APP_NAME="SGA Bojanini"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://sga-bojanini.test
APP_TIMEZONE=America/Bogota

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sga_bojanini
DB_USERNAME=sga_user
DB_PASSWORD=SgaBojanini2026!

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# Desarrollo: integraciones mock (agenda / HC simuladas)
SGA_INTEGRATIONS_USE_MOCK=true

# Token IoT para POST /api/v1/sensors/readings/bulk
IOT_TOKEN=dev-iot-token-win11
```

Ajusta `APP_URL` si usas `artisan serve` (`http://127.0.0.1:8000`) o otro dominio `.test`.

---

## 7. Instalación y arranque

Todos los comandos en **`C:\laragon\www\sga-bojanini\src`**:

```powershell
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### 7.1 Permisos de `storage` y `bootstrap/cache`

En Windows, si aparecen errores al escribir logs o caché:

```powershell
# PowerShell (como administrador si hace falta)
icacls storage /grant Users:(OI)(CI)F /T
icacls bootstrap\cache /grant Users:(OI)(CI)F /T
```

O desde el Explorador: clic derecho en `storage` y `bootstrap\cache` → Propiedades → Seguridad → permitir modificación al usuario con el que corre Apache.

### 7.2 Cola de trabajos (recomendado)

El proyecto usa `QUEUE_CONNECTION=database`. Los consumos y sync a HC encolan jobs; sin worker, las tareas quedan pendientes.

Abre una **segunda terminal** en `src\`:

```powershell
php artisan queue:work --sleep=3 --tries=3
```

Déjala abierta mientras pruebas integraciones y notificaciones asíncronas.

### 7.3 Scheduler (opcional en local)

Para comandos programados (`sga:analyze-conditions`, alertas FEFO, etc.):

```powershell
php artisan schedule:work
```

O una entrada en el **Programador de tareas de Windows** que ejecute cada minuto:

```text
php C:\laragon\www\sga-bojanini\src\artisan schedule:run
```

---

## 8. Comprobar que funciona

| Prueba | URL / comando |
|--------|----------------|
| Health Laravel | `http://sga-bojanini.test` (puede responder 404 en `/`; lo normal es usar `/api/...`) |
| Documentación API (Scramble) | `http://sga-bojanini.test/docs/api` |
| OpenAPI JSON | `http://sga-bojanini.test/docs/api.json` |
| Login API | `POST http://sga-bojanini.test/api/v1/auth/login` |

### Usuario demo (tras `db:seed`)

| Campo | Valor |
|-------|--------|
| Email | `admin@sga.bojanini.com` |
| Contraseña | `Admin2026!` |

Ejemplo con curl (PowerShell):

```powershell
curl -X POST http://sga-bojanini.test/api/v1/auth/login `
  -H "Content-Type: application/json" `
  -d "{\"email\":\"admin@sga.bojanini.com\",\"password\":\"Admin2026!\",\"device_name\":\"win11\"}"
```

Guarda el `token` de la respuesta para llamadas con `Authorization: Bearer ...`.

### Postman

Importa el environment desde `src/docs/postman/SGA-Bojanini.postman_environment.json` y cambia `base_url` a:

```text
http://sga-bojanini.test/api/v1
```

La colección se genera importando `src/docs/openapi/api.json` (ver `src/docs/postman/README.md`).

---

## 9. Ejecutar tests en Windows

Desde `src\` (PHPUnit usa SQLite en memoria; no necesitas MySQL para tests):

```powershell
php artisan test
```

Si `php` no está en el PATH global, usa la ruta de Laragon, por ejemplo:

```powershell
C:\laragon\bin\php\php-8.3.x-Win32-vs16-x64\php.exe artisan test
```

---

## 10. Docker (Linux) vs Laragon (Windows 11)

| Aspecto | Docker (equipo Linux) | Laragon (Windows 11) |
|---------|------------------------|----------------------|
| Raíz Laravel | `src/` montado en contenedor | `src/` en disco local |
| `DB_HOST` | `db` | `127.0.0.1` |
| Puerto HTTP | `http://localhost:8000` | `http://sga-bojanini.test` o `:8000` con `serve` |
| Puerto MySQL | `localhost:3307` | `localhost:3306` |
| Cola / scheduler | Contenedores `queue` y `scheduler` | Terminales locales o Programador de tareas |
| Comandos | `make artisan migrate` | `php artisan migrate` en `src\` |

**Mismo código**, distinto `.env`. No subas `.env` a git.

---

## 11. Flujo de trabajo recomendado

1. **Desarrollo principal:** Docker en Linux (como en el plan de fases).
2. **Pruebas en Windows 11:** Laragon con esta guía, mismo branch, `composer install` y `migrate` en `src\`.
3. Antes de probar un cambio en Win11: `git pull` y, si hay migraciones nuevas, `php artisan migrate`.
4. Si cambian rutas o controllers: `php artisan scramble:export` y reimportar OpenAPI en Postman.

---

## 12. Problemas frecuentes

### `could not find driver` (PDO)

- Activa `extension=pdo_mysql` en `php.ini` y recarga Apache.

### `Class "Intl" not found` / errores de `intl`

- Activa `extension=intl` y reinicia el servidor.

### `SQLSTATE[HY000] [2002] Connection refused`

- MySQL de Laragon debe estar **Started** (Menu → MySQL).
- Revisa `DB_HOST=127.0.0.1` y `DB_PORT=3306`.

### `Access denied for user 'sga_user'@'localhost'`

- Vuelve a ejecutar el script SQL de la sección 4.1 o usa `root` temporalmente en `.env` solo para diagnosticar.

### 404 en todas las rutas `/api/...`

- DocumentRoot incorrecto: debe ser **`src\public`**, no `src` ni la raíz del repo.
- Apache: verifica que `mod_rewrite` esté activo; Laravel trae `.htaccess` en `public`.

### Login 401 con credenciales correctas

- ¿Ejecutaste `php artisan db:seed`?
- ¿`APP_KEY` generado? (`php artisan key:generate`).

### Jobs que no se procesan

- Falta `php artisan queue:work` en otra terminal.

### CORS / frontend en otro puerto

- Configura el middleware CORS de Laravel (`config/cors.php`) según la URL del frontend; en local suele bastar `APP_URL` coherente con el dominio `.test`.

### Composer muy lento o falla por memoria

```powershell
php -d memory_limit=-1 C:\laragon\bin\composer\composer.phar install
```

---

## 13. Checklist rápido

- [ ] Laragon instalado con **PHP 8.3+** y **MySQL 8.0**
- [ ] Extensiones PHP habilitadas (pdo_mysql, intl, zip, gd, bcmath, mbstring)
- [ ] Repo clonado; trabajo en carpeta **`src`**
- [ ] Virtual host → `src\public`
- [ ] Base `sga_bojanini` y usuario creados
- [ ] `.env` con `DB_HOST=127.0.0.1` y `APP_URL` correcto
- [ ] `composer install` → `key:generate` → `migrate` → `db:seed`
- [ ] `storage` y `bootstrap/cache` escribibles
- [ ] `queue:work` en segundo plano (si pruebas colas)
- [ ] Login y `/docs/api` responden

---

## 14. Relación con la Fase 11 (futuro)

La **Fase 11** del plan backend prevé **producción en Windows Server** (PHP + Apache/IIS + MySQL + NSSM para cola y scheduler), no Laragon.  
Laragon en Windows 11 es solo para **desarrollo y pruebas locales**; el despliegue formal se documentará cuando se retome esa fase.

---

## Referencias en el repo

| Recurso | Ruta |
|---------|------|
| Variables de entorno ejemplo | `src/.env.example` |
| OpenAPI exportado | `src/docs/openapi/api.json` |
| Postman environment | `src/docs/postman/SGA-Bojanini.postman_environment.json` |
| Docker (otro SO) | `Makefile`, `docker-compose.yml` en la raíz del repo |
