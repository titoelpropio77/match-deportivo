# Match Deportivo · App (Flutter)

App móvil/web de Match Deportivo: reservar canchas por hora y organizar o unirse a partidos.
Consume la API de [`/backend`](../backend/README.md). Las reglas de negocio completas están en [`.github/copilot-instructions.md`](../.github/copilot-instructions.md).

## Puesta en marcha

1. Levanta la API: `cd ../backend && docker compose up -d --build`.
2. Configura la URL del backend en `frontend/.env`:

   ```
   backend_url=http://localhost:8000
   ```

   En un teléfono físico usa la IP de tu PC en la red local (por ejemplo `http://192.168.1.100:8000`).
3. Instala dependencias y ejecuta:

   ```bash
   flutter pub get
   flutter run
   ```

## Funcionalidades

- **Cuenta**: registro (con foto opcional), login y perfil. La sesión se guarda con `shared_preferences`.
- **Partidos**:
  - Buscar partidos abiertos con filtros, unirse, salir y lista de espera.
  - Crear partido: centro deportivo (búsqueda en la API) y una o varias de sus canchas, deporte, nivel, género, jugadores y QR de cobro.
  - Horario del partido en 3 pasos (`MatchSchedulePicker`): día en chips, hora de inicio y de término en pasos de 30 minutos, dentro del horario del centro.
  - El organizador revisa solicitudes, termina el partido y califica a los jugadores.
- **Reservar cancha**:
  - Lista de centros deportivos con las etiquetas de los deportes que ofrecen, filtrable por deporte y fecha.
  - En el centro: deporte, cancha, día y grilla de horas (libre, reservada con su deporte o pasada). Se pueden elegir varias horas en varias canchas y fechas; las horas seguidas se juntan en rangos.
  - Detalle y pago con **QR simulado** (un solo QR para toda la reserva, con cuenta regresiva).
- **Mis reservas**: acceso desde el home cuando hay reservas. Cada reserva agrupa todas sus canchas y horarios; se puede completar el pago o cancelar una reserva pendiente, y se ven las anulaciones hechas por el centro con su motivo.

## Estructura

```
lib/
  config/      configuración (URL del backend desde .env)
  models/      modelos y parseo JSON (court_field_model.dart: reservas, bookings y agrupaciones)
  services/    un *ApiService por recurso, con el paquete http
  screens/     una carpeta por funcionalidad (home, reserve_court, my_reservations, search_teams, …)
  data/        datos de ejemplo (canchas destacadas)
```

Estado con `StatefulWidget` + `setState` (sin Provider/Bloc). Textos de la interfaz en español.

## Tests

```bash
flutter test
flutter analyze
```

Los tests de widgets están en `test/` y usan servicios falsos (subclases de los `*ApiService`). `test/widget_test.dart` falla desde antes y no está relacionado con estas funcionalidades.
