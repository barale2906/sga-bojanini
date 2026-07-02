.PHONY: up down start stop restart ps logs app db artisan composer migrate migrate-fresh seed migrate-seed migrate-fresh-seed key env init show-urls permissions

up:
	@echo "=> Levantando contenedores (build incluido)..."
	docker compose up -d --build
	@$(MAKE) show-urls

init:
	@echo "=> Inicializando proyecto por primera vez..."
	@$(MAKE) env
	@$(MAKE) up
	@echo "=> Esperando que la base de datos este lista..."
	@sleep 12
	@echo "=> Instalando dependencias de Composer..."
	docker compose exec app composer install
	@echo "=> Generando APP_KEY..."
	docker compose exec app php artisan key:generate
	@$(MAKE) permissions
	@echo "=> Ejecutando migraciones..."
	docker compose exec app php artisan migrate --force
	@echo "=> Inicializacion completada."
	@$(MAKE) show-urls

down:
	@echo "=> Deteniendo y eliminando contenedores/red..."
	docker compose down

stop:
	@echo "=> Deteniendo contenedores (sin borrar datos)..."
	docker compose stop

start:
	@echo "=> Iniciando contenedores existentes..."
	docker compose start
	@$(MAKE) show-urls

restart:
	@echo "=> Reiniciando contenedores..."
	docker compose restart
	@$(MAKE) show-urls

ps:
	@echo "=> Estado de servicios:"
	docker compose ps

logs:
	docker compose logs -f $(filter-out $@,$(MAKECMDGOALS))

app:
	docker compose exec app bash

db:
	docker compose exec db mysql -u sga_user -pSgaBojanini2026! sga_bojanini

artisan:
	docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

composer:
	docker compose exec app composer $(filter-out $@,$(MAKECMDGOALS))

migrate:
	docker compose exec app php artisan migrate

migrate-fresh:
	docker compose exec app php artisan migrate:fresh

seed:
	docker compose exec app php artisan db:seed

migrate-seed:
	docker compose exec app php artisan migrate --seed

migrate-fresh-seed:
	docker compose exec app php artisan migrate:fresh --seed

key:
	docker compose exec app php artisan key:generate

env:
	@if [ ! -f src/.env ]; then \
		if [ -f .env.docker ]; then \
			cp .env.docker src/.env && echo "=> src/.env creado desde .env.docker"; \
		else \
			cp src/.env.example src/.env && echo "=> src/.env creado desde src/.env.example"; \
		fi; \
	else \
		echo "=> src/.env ya existe"; \
	fi

permissions:
	@echo "=> Ajustando permisos de storage y bootstrap/cache..."
	docker compose exec app chown -R www-data:www-data storage bootstrap/cache
	docker compose exec app chmod -R 775 storage bootstrap/cache

show-urls:
	@echo ""
	@echo "=> Accesos:"
	@echo "   App (Nginx): http://localhost:8000"
	@echo "   MySQL:       localhost:3307 (sga_user/SgaBojanini2026!, db: sga_bojanini)"
	@echo ""

%:
	@:
