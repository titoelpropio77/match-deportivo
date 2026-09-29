# Contexto y Reglas de Negocio: Match Deportivo / Wally Pass SCZ (Reserva de Canchas & Matchmaking)

App multideporte para Santa Cruz (Bolivia): reservar canchas por hora y organizar/unirse a partidos abiertos.
Deportes sembrados: Fútbol 5, Fútbol 7, Pádel, Baloncesto, Tenis, Voleibol, Wally, Frontón.

## 1. Arquitectura General y Entorno (WSL)
- **Monorepo**:
  - `/backend`: API REST con **Laravel 13** (PHP 8.3+) y **Sanctum** (Bearer tokens). Base de datos **PostgreSQL 16** vía `docker compose` (`backend/docker-compose.yml`, API en `http://localhost:8000`, TZ `America/La_Paz`). Telescope y Laravel Boost en dev.
  - `/frontend`: App Flutter (móvil/web). La URL del backend se lee de `frontend/.env` (`backend_url`) mediante `flutter_dotenv` (`lib/config/app_config.dart`).
- **Entorno de ejecución**: Ubuntu bajo WSL (Linux POSIX). Rutas y scripts compatibles con Unix.
- Archivos subidos (avatares, QR de pago) se guardan en el disco `public` (`storage/app/public`).

## 2. Usuarios y Perfil
- `users`: name, nickname, email, phone, gender, preferred_position, avatar_path, password.
- Registro (`multipart`, con foto opcional) y login devuelven `{ user, token }`. `GET /api/me` valida la sesión guardada.
- **Pendiente (aún no implementado)**: roles (`cliente`, `admin` de cancha, `superadmin`) y métricas deportivas (PJ, PG, PP, puntos, ranking regional). Hoy la única noción de "dueño" es `courts.owner_id`.

## 3. Canchas y Reservas (sin pasarela de pago)
- **`courts`** (complejo/sede): name, address, lat/lng, opening_time, closing_time, owner_id; fotos (`court_photos`), reseñas (`court_reviews`), deportes (`court_sport`).
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
- Públicas: `POST register`, `POST login`, `GET matches` (abiertos/llenos futuros, filtros `sport_id`, `court_id`, `date`, paginado 15), `GET matches/{id}`, `GET sports`, `GET match-levels`, `GET courts`, `GET court-fields` (`sport_id`, `date`), `GET court-fields/{id}/availability?date=`.
- Con `auth:sanctum`: `me`, `logout`, `matches/mine`, `matches/organized`, `matches/organized/past`, `POST matches`, `join`, `leave`, `DELETE matches/{id}`, `rating-tags`, `finish`, `players` (añadir), `players/{playerId}/review`, `DELETE players/{playerId}`, `GET users/search?query=` (nombre, nickname o email), `POST court-reservations`.

## 6. Frontend (Flutter)
- Estructura: `lib/models`, `lib/services` (un `*ApiService` por recurso con `http`), `lib/screens/<feature>/` y `widgets/` por feature, `lib/config`, `lib/data` (datos dummy de canchas destacadas).
- Estado: `StatefulWidget` + `setState` (sin Provider/Bloc). El token se guarda con `shared_preferences` (`TokenStorage` en `auth_service.dart`); `_AuthGate` en `main.dart` restaura la sesión.
- Navegación: `HomeShellScreen` con pestañas Inicio / Explorar (placeholder) / Perfil. Desde el dashboard: Reservar Cancha, Buscar Equipos, Mis Canchas (partidos que organizo); Torneos, Equipos, Ranking y Resultados son `ComingSoonScreen`.
- Paquetes: `http`, `shared_preferences`, `flutter_dotenv`, `url_launcher`, `share_plus`, `image_picker`, `file_picker`.
- Tests de widgets en `frontend/test/`.

## 7. Convenciones de Código
- **Backend**: controladores API en `app/Http/Controllers/Api` (nota: `MatchController` aún vive en `app/Http/Controllers`). Validación vía `FormRequest` (hoy solo en auth; el resto usa `$request->validate`) y respuestas vía `JsonResource`. Enums PHP en `app/Enums`. Mensajes de error al usuario en español. `DB::transaction` para inscripciones, cupos y reservas. Tests en `backend/tests/Feature`.
- **Frontend**: consumo HTTP con `http`, componentes reutilizables (tarjetas de partido, hojas/modales de búsqueda y filtros), textos de UI en español.
