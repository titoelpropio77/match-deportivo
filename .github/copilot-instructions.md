# Contexto y Reglas de Negocio: Match Deportivo / Wally Pass SCZ (Reserva de Canchas & Matchmaking)

App multideporte para Santa Cruz (Bolivia): reservar canchas por hora y organizar/unirse a partidos abiertos.
Deportes sembrados: Fútbol 5, Fútbol 7, Pádel, Baloncesto, Tenis, Voleibol, Wally, Frontón.

## 1. Arquitectura General y Entorno (WSL)
- **Monorepo**:
  - `/backend`: API REST con **Laravel 13** (PHP 8.3+) y **Sanctum** (Bearer tokens). Base de datos **PostgreSQL 16** vía `docker compose` (`backend/docker-compose.yml`, API en `http://localhost:8000`, TZ `America/La_Paz`). Telescope y Laravel Boost en dev.
  - `/frontend`: App Flutter (móvil/web). La URL del backend se lee de `frontend/.env` (`backend_url`) mediante `flutter_dotenv` (`lib/config/app_config.dart`).
  - `/admin`: Panel de administración web (Laravel 13, sesión web) en `http://localhost:8001`. **Comparte la misma BD PostgreSQL** que el backend; ver sección 8.
- **Entorno de ejecución**: Ubuntu bajo WSL (Linux POSIX). Rutas y scripts compatibles con Unix.
- Archivos subidos (avatares, QR de pago) se guardan en el disco `public` (`storage/app/public`).

## 2. Usuarios y Perfil
- `users`: name, nickname, email, phone, gender, preferred_position, avatar_path, password.
- Registro (`multipart`, con foto opcional) y login devuelven `{ user, token }`. `GET /api/me` valida la sesión guardada.
- **Roles** (spatie/laravel-permission, gestionados solo desde `/admin`): `superadmin` (todo), `admin` (staff de la plataforma: todas las canchas), `partner` (dueño de canchas: solo ve y administra las canchas con `courts.owner_id` = su usuario), `cliente` (jugador, sin acceso al panel). La API del backend todavía no los usa.
- **Pendiente (aún no implementado)**: métricas deportivas (PJ, PG, PP, puntos, ranking regional).

## 3. Canchas y Reservas (sin pasarela de pago)
- **`cities`**: ciudades de Bolivia (key, name, department, lat/lng, is_active) para segmentar canchas. Los datos (27 ciudades: capitales de los 9 departamentos + principales) se insertan en la propia migración `2026_09_29_000001_create_cities_table`; las canchas existentes se asignaron a Santa Cruz de la Sierra.
- **`courts`** (complejo/sede): city_id, name, address, lat/lng, opening_time, closing_time, owner_id; fotos (`court_photos`), reseñas (`court_reviews`), deportes (`court_sport`).
- **`court_fields`** (cancha física dentro de un complejo): name, `price_per_hour`, deportes que ofrece (`court_field_sport`).
- **`court_reservations`**: court_field_id, user_id, sport_id, reserved_on, starts_at, ends_at, hours (1 o 2), amount, status (`pending_payment` por defecto; `cancelled` libera el horario).
- **Reglas**:
  - Disponibilidad en bloques de 1 hora entre apertura y cierre (`CourtField::slotsForDate`), con `free_ranges` agrupados.
  - Una reserva ocupa la cancha física para **todos** los deportes; se valida solapamiento dentro de `DB::transaction` con `lockForUpdate`.
  - Inicio en hora exacta, dentro del horario del complejo, fecha >= hoy.
  - El pago por QR está solo maquetado en el frontend (`court_payment_screen.dart`); no hay integración real.

## 4. Partidos (Matchmaking)
- **`matches`** (modelo `MatchModel`, soft deletes): organizer_id, sport_id, level_id (`match_levels`: Básico, Básico/Intermedio, Intermedio, Intermedio Avanzado, Avanzado, Élite), court_id, gender (`mixed`, `male`, `female`), payment_qr_path, start_time, end_time, max_players, missing_players, status.
- **Estados de partido** (`MatchStatus`): `open`, `full`, `cancelled`, `finished`.
- **`match_players`**: match_id, user_id, quantity_slots, status (`MatchPlayerStatus`): `pending`, `confirmed`, `reserved` (lista de espera).
- **`trusted_players`**: jugadores que un organizador acepta "siempre"; se confirman automáticamente al unirse.
- **Reglas**:
  - Crear: el organizador puede unirse como jugador y añadir jugadores (quedan `confirmed`); no pueden exceder `max_players`. QR de pago opcional.
  - Unirse (`POST /matches/{id}/join`): con cupo → `confirmed` si es de confianza, si no `pending` (el cupo se descuenta igual); sin cupo o partido lleno → `reserved`. Al llegar `missing_players` a 0 el partido pasa a `full`.
  - El organizador revisa solicitudes: `accept_once`, `accept_always` (lo agrega a `trusted_players`) o `reject`.
  - Al salir/quitar/rechazar a un jugador activo se libera el cupo y se promueven los `reserved` por orden de llegada.
  - No se puede salir ni eliminar un partido ya iniciado/concluido. Solo el organizador elimina, añade o quita jugadores.
  - **Terminar partido** (`POST /matches/{id}/finish`, solo organizador y tras `end_time`): marca `finished` y opcionalmente califica a los confirmados con estrellas (1-5), "no asistió" y etiquetas (`rating_tags` por deporte, con polaridad `positive` si estrellas > 3, `negative` si < 3; ninguna para 3). Se guardan en `player_ratings` / `player_rating_tag`.
  - Toda operación que toca cupos usa `DB::transaction` + `lockForUpdate` sobre el partido.

## 5. API (`backend/routes/api.php`)
- Públicas: `POST register`, `POST login`, `GET matches` (abiertos/llenos futuros, filtros `sport_id`, `court_id`, `city_id`, `date`, paginado 15), `GET matches/{id}`, `GET sports`, `GET match-levels`, `GET cities` (activas), `GET courts` (`city_id`), `GET court-fields` (`sport_id`, `city_id`, `date`), `GET court-fields/{id}/availability?date=`.
- Con `auth:sanctum`: `me`, `logout`, `matches/mine`, `matches/organized`, `matches/organized/past`, `POST matches`, `join`, `leave`, `DELETE matches/{id}`, `rating-tags`, `finish`, `players` (añadir), `players/{playerId}/review`, `DELETE players/{playerId}`, `GET users/search?query=` (nombre, nickname o email), `POST court-reservations`.

## 6. Frontend (Flutter)
- Estructura: `lib/models`, `lib/services` (un `*ApiService` por recurso con `http`), `lib/screens/<feature>/` y `widgets/` por feature, `lib/config`, `lib/data` (datos dummy de canchas destacadas).
- Estado: `StatefulWidget` + `setState` (sin Provider/Bloc). El token se guarda con `shared_preferences` (`TokenStorage` en `auth_service.dart`); `_AuthGate` en `main.dart` restaura la sesión.
- Navegación: `HomeShellScreen` con pestañas Inicio / Explorar (placeholder) / Perfil. Desde el dashboard: Reservar Cancha, Buscar Equipos, Mis Canchas (partidos que organizo); Torneos, Equipos, Ranking y Resultados son `ComingSoonScreen`.
- Paquetes: `http`, `shared_preferences`, `flutter_dotenv`, `url_launcher`, `share_plus`, `image_picker`, `file_picker`.
- Tests de widgets en `frontend/test/`.

## 7. Convenciones de Código
- **Backend**: controladores API en `app/Http/Controllers/Api` (nota: `MatchController` aún vive en `app/Http/Controllers`). Validación vía `FormRequest` (hoy solo en auth; el resto usa `$request->validate`) y respuestas vía `JsonResource`. Enums PHP en `app/Enums`. Mensajes de error al usuario en español. `DB::transaction` para inscripciones, cupos y reservas. Tests en `backend/tests/Feature` con SQLite en memoria: `phpunit.xml` fija `DB_*` con `force="true"` en `<env>` y `<server>` porque el contenedor Docker define `DB_*` como variables reales; sin eso `RefreshDatabase` borra la BD PostgreSQL de desarrollo.
- **Frontend**: consumo HTTP con `http`, componentes reutilizables (tarjetas de partido, hojas/modales de búsqueda y filtros), textos de UI en español.

## 8. Panel de Administración (`/admin`)
- Stack: Laravel 13 + `spatie/laravel-permission` + AdminLTE 3 / Bootstrap 4 por CDN + DataTables server-side (`yajra/laravel-datatables-oracle`), SweetAlert2 y Toastr. Sin build de Node.
- Docker: servicio `admin` en `backend/docker-compose.yml` (puerto 8001, `DB_HOST=db`, espera a que `app` esté healthy y luego corre `migrate` + `db:seed`). Se sirve con `php -S` desde `public/` porque `artisan serve` no propaga las variables de entorno del contenedor.
- BD compartida: el esquema de la app lo definen las migraciones de `/backend`; `/admin` solo tiene la migración de tablas de permisos. Los modelos del admin (`User`, `Court`, `CourtField`, `Sport`, `MatchModel`, `CourtReservation`) mapean esas mismas tablas. Sesión y caché en archivos.
- Módulos: Dashboard, Canchas (CRUD de complejos + canchas físicas en modal), Usuarios (CRUD + asignación de roles), Configuración → Roles y permisos (matriz permiso × rol con toggles AJAX, CRUD de permisos y roles).
- Autorización: cada ruta con middleware `permission:<modulo>.<accion>` y cada botón/menú con `@can`. `superadmin` pasa todo vía `Gate::before` y está protegido. Solo usuarios con `dashboard.index` pueden iniciar sesión.
- Alcance por dueño: el permiso `courts.view_all` permite ver/administrar todas las canchas y asignar el partner (`owner_id`). Sin él (rol `partner`), `Court::visibleTo($user)` filtra listas y dashboard, y `CourtPolicy::manage` (`Gate::authorize('manage', $court)`) protege detalle, edición, borrado y canchas físicas. Todo dato nuevo ligado a una cancha debe respetar este alcance.
- Permisos base y defaults por rol en `admin/database/seeders/RolesAndPermissionsSeeder.php` (`ROLE_DEFAULTS`): un rol nuevo recibe todos sus defaults; uno existente solo los permisos recién creados, así el seeder no pisa cambios hechos desde el panel. Al agregar una pantalla, crear su permiso y protegerla igual.
- Usuarios de ejemplo (contraseña `12345678`): `admin.norte@…`, `admin.sur@…` (rol admin) y `partner.wallysur@matchdeportivo.test` (partner de "Complejo Wally Sur").
- Tablas: `AdminTable.init()` (`admin/public/js/admin.js`) monta la barra Exportar / Refrescar / Imprimir / Reiniciar / Columnas; eliminar usa `data-delete-url` (confirmación + DELETE AJAX que responde JSON).
