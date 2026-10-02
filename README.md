# Match Deportivo · Wally Pass SCZ

App multideporte para Santa Cruz (Bolivia): reservar canchas por hora, organizar o unirse a partidos abiertos, armar equipos, inscribirse en torneos, alquilar espacios para eventos y comprar artículos deportivos en las tiendas de cada centro.

Deportes incluidos: Fútbol 5, Fútbol 7, Pádel, Baloncesto, Tenis, Voleibol, Wally y Frontón.

## Funcionalidades

| Módulo | App (jugador) | Panel (centro / staff) |
| --- | --- | --- |
| **Reservas de canchas** | Varias canchas y horas en un solo booking, pago QR (simulado), mis reservas | Agenda del día, reservas presenciales, anular, registrar pago y reembolsos |
| **Partidos** | Crear partido (sobre una reserva o no), buscar y unirse, calificar jugadores | — |
| **Equipos** | Crear equipos con escudo, plantel y capitán | — |
| **Torneos** | Inscribir mi equipo, pagar, ver fixture y tabla | Crear torneos, generar fixture, cargar resultados, cobrar inscripciones |
| **Espacios para eventos** | Parrilleros, quinchos y salones con capacidad y normas | CRUD por centro y reservas |
| **Alquiler de artículos** | Pelotas, raquetas, pecheras… al reservar | CRUD por centro con control de stock |
| **Tiendas** | Catálogo con ofertas, carrito y retiro en tienda | Productos, movimientos de stock, ventas de mostrador |
| **Usuarios y roles** | Registro, perfil de jugador, deportes favoritos | Roles `superadmin`, `admin`, `partner`, `manager` y matriz de permisos |

> El pago QR está **simulado**: la app muestra un QR ficticio y un botón "Simular pago". Falta integrar el webhook del banco.

## Arquitectura

Monorepo con tres proyectos que comparten una misma base de datos PostgreSQL:

```
match-deportivo/
├── backend/   API REST · Laravel 13 + Sanctum      → http://localhost:8000
├── admin/     Panel web · Laravel 13 + AdminLTE     → http://localhost:8001
└── frontend/  App móvil/web · Flutter
```

- **backend**: define el esquema (todas las migraciones de la app) y expone la API (`backend/routes/api.php`) con tokens Bearer de Sanctum.
- **admin**: usa las mismas tablas; solo agrega las de permisos (`spatie/laravel-permission`). Tablas server-side con Yajra DataTables y validación con FormRequests.
- **frontend**: lee la URL de la API de `frontend/.env` (`backend_url`).

Las reglas de negocio y convenciones detalladas están en [`.github/copilot-instructions.md`](.github/copilot-instructions.md).

## Requisitos

- Docker y Docker Compose
- Flutter (SDK de Dart `^3.13`)
- Para Android: Android SDK y JDK 17+ (ver [Correr en Android](#correr-en-android-usb))

El entorno de desarrollo es Ubuntu sobre WSL.

## Puesta en marcha

### 1. Backend, panel y base de datos

```bash
cd backend
docker compose up -d --build
```

Levanta tres contenedores:

| Servicio | Puerto | Qué hace al iniciar |
| --- | --- | --- |
| `db` | 5432 | PostgreSQL 16 (`match_deportivo` / `match_deportivo`) |
| `app` | 8000 | `composer install`, migraciones, `storage:link` y la API |
| `admin` | 8001 | Espera a `app`, migra permisos, corre los seeders y sirve el panel |

Para cargar los datos de ejemplo (deportes, centros, canchas, espacios, tiendas y jugadores) y luego volver a correr los seeders del panel, que asignan el partner y el manager a "Complejo Wally Sur":

```bash
docker exec backend-app-1 php artisan db:seed
docker exec backend-admin-1 php artisan db:seed
```

### 2. Variables de entorno

- `backend/.env` y `admin/.env`: se crean desde su `.env.example`. En el panel, `GOOGLE_MAPS_API_KEY` habilita el mapa para ubicar los centros (sin key, lat/lng se cargan a mano). Para enviar correos con Mailgun: `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN` y `MAILGUN_SECRET`.
- `frontend/.env`: `backend_url=http://localhost:8000`.

No subas claves reales a los `.env.example`.

### 3. App Flutter

```bash
cd frontend
flutter pub get
flutter run -d linux     # o -d chrome
```

### Correr en Android (USB)

En WSL el teléfono no se ve por USB, pero sí desde el `adb` de Windows. Se compila en WSL y se instala con el `adb.exe` de Windows:

```bash
# Una sola vez: JDK y Android SDK en WSL
flutter config --android-sdk ~/Android/Sdk --jdk-dir ~/opt/jdk21

cd frontend
flutter build apk --debug

ADB=/mnt/c/Users/<usuario>/AppData/Local/Android/Sdk/platform-tools/adb.exe
$ADB install -r "$(wslpath -w build/app/outputs/flutter-apk/app-debug.apk)"
$ADB reverse tcp:8000 tcp:8000   # el localhost:8000 del teléfono llega al backend
```

`adb reverse` se pierde al desconectar el cable: vuelve a ejecutarlo. Los builds debug permiten HTTP (`android/app/src/debug/AndroidManifest.xml`) para hablar con el backend local.

## Usuarios de prueba (panel)

| Rol | Email | Contraseña |
| --- | --- | --- |
| superadmin | `admin@matchdeportivo.test` | `password` (o `ADMIN_EMAIL` / `ADMIN_PASSWORD`) |
| admin | `admin.norte@matchdeportivo.test`, `admin.sur@matchdeportivo.test` | `12345678` |
| partner | `partner.wallysur@matchdeportivo.test` (dueño de "Complejo Wally Sur") | `12345678` |
| manager | `manager.wallysur@matchdeportivo.test` (mismo centro) | `12345678` |

Solo pueden entrar al panel usuarios con el permiso `dashboard.index`. Los jugadores (`cliente`) usan la app.

## Tests

```bash
# Backend (SQLite en memoria; no toca la BD de desarrollo)
docker exec backend-app-1 php vendor/bin/phpunit

# Frontend
cd frontend && flutter test
```

`frontend/test/widget_test.dart` falla desde antes y no está relacionado con los módulos actuales.

## Pendiente

- Webhook real de pago QR (hoy simulado).
- Resultados de partidos (PG, PP, puntos) y ranking regional.
- Pestaña "Explorar" de la app.
