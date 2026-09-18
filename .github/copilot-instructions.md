# Contexto y Reglas de Negocio: App Armador de Partidos Deportivos

## 1. Arquitectura del Proyecto
- **Monorepo**:
  - `/backend`: API REST construida con Laravel 11 y Sanctum para autenticación. Base de Datos PostgreSQL/MySQL.
  - `/frontend`: App Móvil/Web construida en Flutter.

## 2. Autenticación y Usuarios
- Registro de usuario (Nombre, Email, Teléfono, Password).
- Login mediante API devolviendo un Bearer Token con Laravel Sanctum.
- Gestión de sesión persistente en Flutter.

## 3. Reglas de Negocio de Equipos y Partidos
- **Creación de Partido / Cancha**:
  - Un usuario autenticado puede crear una "Cancha/Partido".
  - **Campos**: Nombre de Cancha, Lugar/Ubicación, Fecha y Hora, Límite máximo de jugadores/equipos.
- **Gestión de Plantilla / Jugadores del Equipo**:
  - El creador del partido puede agregar otros usuarios registrados al equipo.
  - Debe haber un **buscador de usuarios** por nombre/email para seleccionarlos e incorporarlos.
- **Unirse a un Partido / Equipo**:
  - Un usuario puede explorar y buscar partidas/canchas disponibles mediante un buscador.
  - Un usuario puede solicitar unirse o inscribirse a una cancha que tenga cupos disponibles.

## 4. Convenciones de Código
- **Laravel**: Controladores en `app/Http/Controllers/Api`, Form Requests para validación, API Resources para respuestas JSON uniformes, Transacciones DB para inscripciones.
- **Flutter**: Arquitectura por capas (models, services, providers/controllers, screens, widgets). Uso de `http` o `dio` para peticiones HTTP.