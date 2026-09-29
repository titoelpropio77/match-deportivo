# Match Deportivo · Panel de administración

Laravel 13 + [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) + AdminLTE 3 (Bootstrap 4, por CDN) + DataTables server-side ([yajra/laravel-datatables](https://yajrabox.com/docs/laravel-datatables)).

Comparte la base de datos PostgreSQL del backend (`/backend`): mismos `users`, `courts`, `court_fields`, etc.
El esquema de la app lo gobiernan las migraciones del backend; aquí solo vive la migración de las tablas de permisos.

## Puesta en marcha

### Con Docker (recomendado)

El servicio `admin` está en `backend/docker-compose.yml` junto a `app` (API) y `db`:

```bash
cd ../backend
docker compose up -d --build
```

- API: http://localhost:8000 · Admin: http://localhost:8001 · PostgreSQL: localhost:5432
- El contenedor `admin` espera a que la API esté sana (sus migraciones ya corrieron), luego ejecuta `migrate` (tablas de permisos) y `db:seed` (idempotente: no pisa los cambios hechos desde el panel).
- Dentro de Docker se conecta a la BD por el host `db` (variables en el compose, que tienen prioridad sobre `.env`).
- Logs: `docker compose logs -f admin`. Comandos: `docker compose exec admin php artisan ...`

### Sin Docker (PHP local)

1. Levanta al menos la BD: `cd ../backend && docker compose up -d db`.
2. En esta carpeta:

```bash
composer install
cp .env.example .env && php artisan key:generate   # DB_* apuntan a 127.0.0.1:5432 / match_deportivo
php artisan migrate        # crea permissions, roles, model_has_roles, ...
php artisan db:seed        # permisos, roles y el usuario superadmin
php artisan serve --port=8001
```

Entra en http://localhost:8001 con `ADMIN_EMAIL` / `ADMIN_PASSWORD` del `.env` (por defecto `admin@matchdeportivo.test` / `password`; cámbialo).

> Ojo: `php artisan migrate:fresh` en el backend borra también las tablas de permisos. Después vuelve a correr `php artisan migrate --seed` aquí.

## Google Maps (ubicación de canchas)

Al crear/editar una cancha se elige el punto exacto en un mapa (clic o arrastrando el marcador); latitud y longitud se llenan solas.

1. En Google Cloud Console habilita **Maps JavaScript API** y **Geocoding API** (para "Buscar dirección").
2. Crea una API key y restríngela por *HTTP referrer* (`http://localhost:8001/*`).
3. Pon la key en `admin/.env`: `GOOGLE_MAPS_API_KEY=...` (opcional `GOOGLE_MAPS_MAP_ID`; `DEMO_MAP_ID` sirve en desarrollo).

Sin key, el formulario muestra un aviso y deja escribir latitud/longitud a mano.

## Roles y permisos

- Cada ruta usa el middleware `permission:<modulo>.<accion>` y cada botón/ítem de menú `@can`.
- Permisos base (ver `database/seeders/RolesAndPermissionsSeeder.php`): `dashboard.index`, `courts.*`, `court_fields.*`, `users.*`, `roles.*`, `permissions.*` (incluye `permissions.assign` para la matriz).
- `superadmin`: todos los permisos vía `Gate::before`; no se puede editar ni eliminar.
- `admin`: staff de la plataforma (todas las canchas, lectura de usuarios).
- `partner`: dueño de canchas. Solo ve y administra las canchas asignadas a él (`courts.owner_id`); la asignación la hace alguien con `courts.view_all`.
- `cliente`: jugador de la app, sin acceso al panel.
- Solo quien tenga `dashboard.index` puede iniciar sesión en el panel.

Si agregas una pantalla nueva: crea el permiso en el seeder (o desde *Configuración → Crear permiso*), protégelo en `routes/web.php` y en la vista con `@can`.
