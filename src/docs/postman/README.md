# Postman — SGA Bojanini

## Importar colección (desde OpenAPI)

1. Levantar el entorno: `docker compose up -d`
2. Exportar o usar la spec versionada: `docs/openapi/api.json`
3. Postman → **Import** → **File** → seleccionar `docs/openapi/api.json`  
   Alternativa: **Link** → `http://localhost:8080/docs/api.json`

## Importar environment

1. Postman → **Environments** → **Import**
2. Archivo: `docs/postman/SGA-Bojanini.postman_environment.json`
3. Activar el environment **SGA Bojanini — Local**

## Autenticación

1. Ejecutar **POST** `{{base_url}}/auth/login` con credenciales del seeder (`admin@sga.bojanini.com` / contraseña del `.env`)
2. En la pestaña **Tests** del request de login:

```javascript
const json = pm.response.json();
if (json.data && json.data.token) {
  pm.environment.set('token', json.data.token);
}
```

3. En la colección importada: **Authorization** → Type **Bearer Token** → `{{token}}`

## Actualizar tras cambios en la API

```bash
docker compose exec app php artisan scramble:export
```

Re-importar `docs/openapi/api.json` en Postman (o volver a importar por URL).

## Insomnia / Bruno

Mismo flujo: importar `docs/openapi/api.json` y configurar variable `base_url` + Bearer `token`.
