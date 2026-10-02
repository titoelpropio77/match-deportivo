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

## Módulos

- **Dashboard**: centros, canchas, partidos abiertos, reservas para hoy, ingresos del mes (cobrado y no reembolsado), próximas reservas y aviso de devoluciones pendientes. Todo limitado a los centros que el usuario puede ver.
- **Centros deportivos**: CRUD con galería de fotos, ubicación en mapa, canchas físicas y managers. Desde el detalle hay accesos a su agenda y sus reservas.
- **Reservas**:
  - *Lista de reservas*: filtros por centro, estado, origen y fechas; búsqueda por referencia (`MD-000123`), cliente o cancha; ordenada por fecha y hora descendente; exportable.
  - *Detalle*: reserva, cliente (con enlace a WhatsApp), pago, historial y las otras reservas pagadas con el mismo QR (`booking_code`).
  - *Acciones*: **Anular** (motivo obligatorio, el jugador lo ve en la app), **Registrar pago** (efectivo, transferencia o QR) y **Marcar como reembolsada** para las pagadas que se anularon.
  - *Agenda del día*: canchas × horas de un centro, con colores por estado; un clic en una hora libre abre el registro.
  - *Registrar reserva*: reservas por teléfono o presenciales (cliente por nombre/teléfono o su cuenta de la app por email), "paga en el local" o ya pagada. Valida choques de horario.
- **Torneos**: crear/editar torneos de los centros visibles (costo por equipo, cupos, jugadores por equipo, fechas, premios, reglamento, portada), cambiar su estado (borrador → inscripciones abiertas → cerradas → en curso → finalizado / cancelado), gestionar las inscripciones de equipos (pago en el local, anular con motivo, reembolso), armar el fixture (manual o "todos contra todos" automático), cargar resultados y ver la tabla de posiciones.
- **Usuarios**: CRUD y asignación de roles.
- **Configuración → Roles y permisos**: matriz permiso × rol con toggles AJAX y CRUD de permisos y roles.

### Estados de una reserva

| Estado | Significado |
|---|---|
| `pending_payment` | Reservada desde la app; aparta el horario 15 minutos mientras se paga el QR, luego expira sola. |
| `confirmed` | Registrada en el panel para pagar en el local; bloquea el horario sin expirar. |
| `paid` | Pagada (QR en la app o cobro registrado en el panel). |
| `cancelled` | Anulada por el jugador o por el centro. Si estaba pagada queda como *devolución pendiente* hasta marcarla reembolsada. |

## Tablas (DataTables)

Todas las tablas usan [Yajra Laravel DataTables](https://yajrabox.com/docs/laravel-datatables) server-side: cada listado tiene un endpoint `.../data` con el mismo permiso que su `index`.

- En el controlador: `DataTables::eloquent($query)` con `addColumn` / `editColumn` (el HTML de acciones y badges va en partials `*/partials/*.blade.php` y se declara en `rawColumns`), `filterColumn` para buscar en columnas calculadas y `orderColumn` para ordenarlas.
- En la vista: `AdminTable.init('#tabla', { serverSide: true, ajax: ..., columns: [...] })` (`public/js/admin.js`) monta la barra Exportar / Refrescar / Imprimir / Reiniciar / Columnas.
- Eliminar: botón con `data-delete-url` y `data-table` (confirmación SweetAlert + DELETE por AJAX que responde JSON y recarga la tabla).

## Roles y permisos

- Cada ruta usa el middleware `permission:<modulo>.<accion>` y cada botón/ítem de menú `@can`.
- Permisos base (ver `database/seeders/RolesAndPermissionsSeeder.php`): `dashboard.index`, `courts.*` (incluye `courts.view_all` y `courts.managers`), `court_fields.*`, `reservations.*` (`index`, `show`, `store`, `cancel`, `payments`), `tournaments.*` (`index`, `store`, `update`, `destroy`, `registrations`, `games`), `users.*`, `roles.*`, `permissions.*` (incluye `permissions.assign` para la matriz).
- `superadmin`: todos los permisos vía `Gate::before`; no se puede editar ni eliminar.
- `admin`: staff de la plataforma. Todos los centros y reservas; crea usuarios y les asigna roles.
- `partner`: dueño de centros. Solo ve y administra los centros asignados a él (`courts.owner_id`) y sus reservas, y les asigna managers.
- `manager`: encargado asignado por un partner. Gestiona las canchas y reservas de ese centro; no elimina centros ni asigna managers.
- `cliente`: jugador de la app, sin acceso al panel.
- Solo quien tenga `dashboard.index` puede iniciar sesión en el panel.
- Al asignar roles, quien no es superadmin solo ve los roles cuyos permisos también tiene (nadie puede dar más acceso del que tiene), y no puede editar a usuarios con roles fuera de su alcance.

El seeder corre en cada arranque de Docker: a un rol nuevo le da todos sus permisos por defecto y a uno existente solo los permisos recién creados, para no pisar lo cambiado desde el panel. Para dar a un rol existente un permiso que ya existía, usa una migración de datos en `database/migrations` (ej. `2026_10_01_000001_grant_user_management_to_admin_role.php`).

Si agregas una pantalla nueva: crea el permiso en el seeder (o desde *Configuración → Crear permiso*), protégelo en `routes/web.php` y en la vista con `@can`, y limita los datos con `visibleTo($user)` / `CourtPolicy::manage` si están ligados a un centro.
