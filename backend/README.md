# Match Deportivo · API (backend)

API REST de Match Deportivo: reserva de canchas por hora y partidos abiertos (matchmaking) en Bolivia.
Laravel 13 (PHP 8.3+), Sanctum (tokens Bearer) y PostgreSQL 16.

El panel de administración (`/admin`) y la app Flutter (`/frontend`) usan esta misma base de datos y esta API.
Las reglas de negocio completas están en [`.github/copilot-instructions.md`](../.github/copilot-instructions.md).

## Desarrollo con Docker

Desde esta carpeta, inicia la API, el panel y PostgreSQL con:

```bash
docker compose up -d --build
```

- API: `http://localhost:8000` · Panel admin: `http://localhost:8001` · PostgreSQL: puerto `5432`.
- Las migraciones se ejecutan automáticamente al iniciar el contenedor `app`; luego el contenedor `admin` corre sus migraciones y seeders.
- El contenedor ejecuta `composer install` en cada inicio para sincronizar el volumen de dependencias con `composer.lock`. Después de añadir o actualizar un paquete, reinicia con `docker compose up -d --build`; no elimines el volumen de PostgreSQL para resolver cambios de dependencias.

```bash
docker compose logs -f app            # logs
docker compose exec app php artisan … # comandos artisan
docker compose down                   # detener
```

Los datos de PostgreSQL se conservan en el volumen `backend_postgres_data`. Para eliminarlos también, usa `docker compose down -v`.

## Tests

```bash
docker compose exec app php vendor/bin/phpunit
```

Los tests usan SQLite en memoria: `phpunit.xml` fuerza las variables `DB_*` (con `force="true"`) porque el contenedor las define como variables reales; sin eso `RefreshDatabase` borraría la base de desarrollo.

## Módulos principales

| Módulo | Dónde |
|---|---|
| Autenticación y perfil | `AuthController`, `UserController` |
| Partidos (crear, unirse, lista de espera, terminar y calificar) | `MatchController` |
| Centros deportivos y canchas físicas | `CourtController`, `CourtFieldController` |
| Reservas de canchas (una o varias canchas y horas, pago QR simulado) | `CourtBookingController`, `CourtFieldController`, `App\Services\CourtBookingService` |

### Reservas

- La disponibilidad se calcula por cancha física en bloques de 1 hora (`CourtField::slotsForDate`): cada hora es `available`, `reserved` (con el deporte reservado) o `past`.
- Un **booking** agrupa todos los rangos que el jugador reserva juntos (varias canchas y/o varias horas), comparten `booking_code` y se pagan con un solo QR. Se reserva todo o nada.
- Estados: `pending_payment` (aparta el horario 15 minutos), `confirmed` (registrada en el panel, paga en el local), `paid` y `cancelled`.
- El pago QR es **simulado** (`POST /api/court-bookings/{code}/pay`); falta conectar el webhook del banco.

## Endpoints

Públicos: `POST /api/register`, `POST /api/login`, `GET /api/matches`, `GET /api/matches/{id}`, `GET /api/sports`, `GET /api/match-levels`, `GET /api/cities`, `GET /api/courts`, `GET /api/court-fields`, `GET /api/court-fields/{id}/availability?date=`.

Con token (`Authorization: Bearer …`):

- Sesión: `GET /api/me`, `POST /api/logout`.
- Partidos: `GET /api/matches/mine`, `/organized`, `/organized/past`, `POST /api/matches`, `POST /api/matches/{id}/join`, `DELETE /api/matches/{id}/leave`, `DELETE /api/matches/{id}`, `POST /api/matches/{id}/finish`, gestión de jugadores.
- Usuarios: `GET /api/users/search?query=`.
- Reservas:
  - `POST /api/court-bookings` con `items[]` (`court_field_id`, `sport_id`, `date`, `start_time`, `hours`).
  - `POST /api/court-bookings/{code}/pay` · `POST /api/court-bookings/{code}/cancel`.
  - `GET /api/court-reservations` (mis reservas).
  - Reserva individual (heredado): `POST /api/court-reservations`, `POST /api/court-reservations/{id}/pay`, `POST /api/court-reservations/{id}/cancel`.

El detalle de cada ruta está en `routes/api.php`.
