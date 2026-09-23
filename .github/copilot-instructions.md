# Contexto y Reglas de Negocio: Wally Pass SCZ (Gestión de Canchas & Matchmaking)

## 1. Arquitectura General y Entorno (WSL)
- **Monorepo**:
  - `/backend`: API RESTful con Laravel 11 y Sanctum (Bearer Tokens). Base de Datos PostgreSQL / MySQL.
  - `/frontend`: Aplicación Móvil/Web en Flutter (con arquitectura limpia por capas/features).
- **Entorno Execution**: Ubuntu bajo WSL (Linux POSIX). Todas las rutas y scripts deben ser compatibles con Unix.

## 2. Roles de Usuario y Perfil Deportivo (Gamificación)
- **Roles**: `cliente` (Jugador), `admin` (Administrador de Cancha), `superadmin`.
- **Perfil de Jugador**:
  - Datos básicos: Nombre completo, teléfono, email, foto, posición en cancha (Ej: Rematador, Colocador, Servidor).
  - Métricas Deportivas: Partidos Jugados (PJ), Victorias (PG), Derrotas (PP), Puntos Totales, Ranking Regional (Santa Cruz).

## 3. Módulo de Canchas y Reservas (Sin Pasarela de Pagos por ahora)
- **Canchas**: Nombre, tipo de superficie, precio por hora diurna/nocturna, estado (`activa`, `mantenimiento`).
- **Reserva de Horarios**:
  - Grilla de disponibilidad horaria por fecha.
  - Estados de bloque: `disponible`, `ocupado`, `bloqueado`.

## 4. Módulo de Matchmaking, Creación de Equipos y Partidos
- **Creación de Partido / Cancha**:
  - Un usuario autenticado puede publicar/crear una partida en una cancha.
  - **Campos**: Cancha/Lugar, Fecha, Hora de inicio, Hora de término, Nivel del equipo (`Básico`, `Básico/Intermedio`, `Intermedio`, `Intermedio Avanzado`, `Avanzado`, `Élite`), Límite máximo de jugadores.
- **Buscador y Adición de Jugadores (Por el Creador)**:
  - Buscador autocompletable de usuarios registrados por nombre/email para añadirlos directamente a la plantilla antes o después de crear el partido.
- **Unirse a un Equipo / Partido Activo (Matchmaking de Jugadores)**:
  - **Explorador de Partidos**: Listado y buscador de partidos/canchas con cupos abiertos.
  - **Solicitud de Ingreso**: Cualquier usuario registrado puede ver los detalles de un partido abierto (lugar, horario, nivel, jugadores confirmados) y presionar "Unirse al Partido".
  - **Validación de Cupos**: Al unirse, el sistema valida atómicamente que queden cupos disponibles, incrementa la lista de participantes (`match_players`) y actualiza el estado a `lleno` si se completa el cupo máximo.

## 5. Esquema Simplificado de Base de Datos (Eloquent / SQL)
- `users`: id, nombre_completo, telefono, email, password, rol.
- `perfil_jugadores`: id, user_id, posicion, partidos_jugados, victorias, derrotas, puntos, ranking.
- `canchas`: id, nombre, superficie, precio_diurno, precio_nocturno, estado.
- `matches` (Partidos/Canchas): id, creator_id, cancha_id, lugar, fecha, hora_inicio, hora_fin, nivel, max_jugadores, status (`abierto`, `lleno`, `cancelado`).
- `match_players` (Inscripciones/Match): id, match_id, user_id, status (`confirmado`, `pendiente`).

## 6. Convenciones de Código para Copilot
- **Backend (Laravel)**: Controladores API en `app/Http/Controllers/Api`, validaciones vía `FormRequest`, transformación JSON vía `JsonResource`, uso de transacciones DB (`DB::transaction`) para inscripción/unión a partidos.
- **Frontend (Flutter)**: Consumo HTTP vía `http` o `dio`, manejo de estado modular, componentes reusables de tarjetas de partidos y modales de búsqueda.