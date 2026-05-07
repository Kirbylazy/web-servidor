# Guia Completa del Proyecto — Aplicacion de Gestion de Competiciones de Escalada

## Indice

1. [Descripcion General](#1-descripcion-general)
2. [Stack Tecnologico](#2-stack-tecnologico)
3. [Arquitectura y Estructura de Carpetas](#3-arquitectura-y-estructura-de-carpetas)
4. [Base de Datos — Migraciones y Tablas](#4-base-de-datos--migraciones-y-tablas)
5. [Modelos Eloquent](#5-modelos-eloquent)
6. [Sistema de Roles y Permisos](#6-sistema-de-roles-y-permisos)
7. [Rutas (routes/web.php)](#7-rutas-routeswebphp)
8. [Controladores](#8-controladores)
9. [Vistas Blade](#9-vistas-blade)
10. [Notificaciones](#10-notificaciones)
11. [Seeders y Factories](#11-seeders-y-factories)
12. [Flujos Funcionales Completos](#12-flujos-funcionales-completos)
13. [Usuarios de Prueba](#13-usuarios-de-prueba)

---

## 1. Descripcion General

Esta aplicacion web gestiona **competiciones de escalada deportiva en Andalucia**. Permite a diferentes tipos de usuarios (administradores, arbitros, entrenadores y competidores) interactuar con el sistema segun su rol.

### Que hace la aplicacion:

- **Administradores**: Crean y gestionan copas (torneos), competiciones (pruebas), rocodromos (ubicaciones) y usuarios.
- **Arbitros**: Validan documentos (licencias federativas y justificantes de pago) de los competidores inscritos en las competiciones que tienen asignadas.
- **Entrenadores**: Forman equipos vinculandose con competidores y los inscriben en competiciones.
- **Competidores**: Se inscriben en competiciones subiendo su licencia federativa y justificante de pago, y reciben notificaciones sobre el estado de sus inscripciones.

### Jerarquia del dominio:

```
Copa (torneo/serie)
  └── tiene muchas Competiciones (pruebas/eventos)
        ├── se celebra en una Ubicacion (rocodromo)
        ├── tiene un Arbitro asignado (usuario con rol arbitro)
        └── tiene muchas Inscripciones (competidores inscritos)
              ├── licencia federativa (archivo subido)
              └── justificante de pago (archivo subido)
```

---

## 2. Stack Tecnologico

| Tecnologia | Version | Uso |
|---|---|---|
| **PHP** | 8.2+ | Lenguaje del backend |
| **Laravel** | 12 | Framework PHP principal |
| **Laravel Breeze** | 2.3 | Autenticacion (login, registro, reset password) |
| **MySQL** | (XAMPP) | Base de datos relacional |
| **Bootstrap 5** | CDN | Framework CSS para las vistas |
| **Alpine.js** | CDN | Interactividad ligera en el frontend |
| **Vite** | - | Bundler de assets (CSS/JS) |
| **Blade** | (Laravel) | Motor de plantillas para las vistas |

### Dependencias principales (composer.json):

- `laravel/framework` ^12.0
- `laravel/breeze` ^2.3 (dev) — genera las vistas y rutas de autenticacion
- `laravel/tinker` ^2.10.1 — consola interactiva para debug
- `fakerphp/faker` ^1.23 (dev) — generacion de datos falsos para seeders

---

## 3. Arquitectura y Estructura de Carpetas

```
Escalada/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AdminController.php          # Panel de administracion
│   │   │   ├── ArbitroController.php        # Panel del arbitro
│   │   │   ├── CompeticionController.php    # CRUD de competiciones
│   │   │   ├── CopaController.php           # CRUD de copas
│   │   │   ├── EntrenadorController.php     # Gestion de equipo del entrenador
│   │   │   ├── InscripcionController.php    # Flujo de inscripcion del competidor
│   │   │   ├── NotificacionController.php   # Aceptar/rechazar solicitudes
│   │   │   ├── ProfileController.php        # Perfil del usuario (Breeze)
│   │   │   ├── UbicacionController.php      # CRUD de rocodromos
│   │   │   └── Auth/                        # Controladores de autenticacion (Breeze)
│   │   ├── Middleware/
│   │   │   └── CheckRol.php                 # Middleware de control de acceso por rol
│   │   └── Requests/
│   │       ├── Auth/LoginRequest.php        # Validacion de login (Breeze)
│   │       └── ProfileUpdateRequest.php     # Validacion de perfil (Breeze)
│   ├── Models/
│   │   ├── User.php                         # Usuario (modelo central)
│   │   ├── Competicion.php                  # Competicion/prueba de escalada
│   │   ├── Copa.php                         # Copa/torneo
│   │   ├── Inscripcion.php                  # Inscripcion formal con documentos
│   │   ├── LicenciaValidacion.php           # Registro de validacion de licencia
│   │   └── Ubicacion.php                    # Rocodromo/ubicacion
│   ├── Notifications/
│   │   ├── SolicitudEntrenadorNotification.php     # "X quiere ser tu entrenador"
│   │   └── InscripcionActualizadaNotification.php  # "Tu inscripcion fue aprobada/rechazada"
│   └── View/Components/
│       ├── AppLayout.php                    # Layout principal
│       └── GuestLayout.php                  # Layout para invitados (login/register)
├── database/
│   ├── factories/
│   │   ├── UserFactory.php                  # Genera usuarios aleatorios
│   │   ├── CompeticionFactory.php           # Genera competiciones aleatorias
│   │   ├── CopaFactory.php                  # Genera copas aleatorias
│   │   └── UbicacionFactory.php             # Genera ubicaciones aleatorias
│   ├── migrations/                          # 10 migraciones (detalladas abajo)
│   └── seeders/
│       ├── DatabaseSeeder.php               # Seeder principal
│       ├── TestUsersSeeder.php              # Usuarios de prueba con credenciales conocidas
│       └── InscripcionesSeeder.php          # 150 inscripciones por competicion
├── resources/views/                         # Vistas Blade (detalladas abajo)
├── routes/
│   ├── web.php                              # TODAS las rutas de la aplicacion
│   └── auth.php                             # Rutas de autenticacion (Breeze)
├── bootstrap/
│   └── app.php                              # Registro del middleware 'rol'
└── public/
    └── index.php                            # Punto de entrada
```

---

## 4. Base de Datos — Migraciones y Tablas

### 4.1 Tabla `users` — Usuarios del sistema

**Migracion**: `0001_01_01_000000_create_users_table.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | bigint (PK) | Identificador unico |
| dni | string (unique) | DNI/NIE — usado para buscar competidores |
| fecha_nacimiento | date | Para calcular la categoria por edad |
| provincia | string | Provincia del usuario |
| talla | string | Talla de camiseta (XS/S/M/L/XL/XXL) |
| name | string | Nombre completo |
| email | string (unique) | Email para login |
| email_verified_at | timestamp? | Verificacion de email |
| password | string | Contrasena hasheada |
| rol | string (default: 'competidor') | competidor / entrenador / arbitro / admin |
| genero | string | M / F / otro — determina categoria Masculino/Femenino |
| remember_token | string? | Token "recordarme" |
| created_at / updated_at | timestamps | Fechas de creacion/modificacion |

Esta migracion tambien crea las tablas auxiliares `password_reset_tokens` y `sessions`.

### 4.2 Tabla `copas` — Torneos/series

**Migracion**: `2026_02_16_150245_create_copas_table.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | bigint (PK) | |
| name | string | Ej: "Copa de Bloque 2026" |
| tipo | string | Bloque / Dificultad / Velocidad |
| temporada | integer | Ano de la temporada (ej: 2026) |
| timestamps | | |

### 4.3 Tabla `ubicacions` — Rocodromos

**Migracion**: `2026_02_16_150312_create_ubicacions_table.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | bigint (PK) | |
| name | string | Nombre del rocodromo |
| provincia | string | Provincia |
| direccion | string | Direccion completa |
| alto | float | Altura del muro (metros) |
| ancho | float | Anchura del muro (metros) |
| n_lineas | integer | Numero de lineas/vias |
| timestamps | | |

### 4.4 Tabla `competicions` — Competiciones/Pruebas

**Migracion**: `2026_02_16_150333_create_competicions_table.php`

| Columna | Tipo | FK | Comportamiento al borrar |
|---|---|---|---|
| id | bigint (PK) | | |
| copa_id | bigint? | copas.id | nullOnDelete |
| arbitro_id | bigint? | users.id | nullOnDelete |
| ubicacion_id | bigint | ubicacions.id | **cascadeOnDelete** |
| name | string | | |
| provincia | string | | |
| fecha_realizacion | datetime | | |
| fecha_fin | datetime? | | |
| tipo | string | | bloque / dificultad / velocidad |
| campeonato | boolean (default: false) | | |
| categorias | json? | | Array de categorias habilitadas |
| timestamps | | | |

### 4.5 Tabla `competicions_users` — Inscripcion legacy (pivot)

**Migracion**: `2026_02_16_150407_competicion_user.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | bigint (PK) | |
| user_id | bigint (FK -> users, cascadeOnDelete) | |
| competicion_id | bigint (FK -> competicions, cascadeOnDelete) | |
| tipoDato | string? | Para uso futuro (resultados) |
| dato | string? | Para uso futuro |
| timestamps | | |
| **UNIQUE** | (user_id, competicion_id) | Un usuario solo se inscribe 1 vez |

**Nota**: Esta es la inscripcion **rapida/legacy** que usa el entrenador. El flujo completo con documentos usa la tabla `inscripciones`.

### 4.6 Tabla `entrenador_competidor` — Vinculo entrenador-competidor (pivot)

**Migracion**: `2026_03_09_000001_create_entrenador_competidor_table.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | bigint (PK) | |
| entrenador_id | bigint (FK -> users, cascadeOnDelete) | El entrenador |
| competidor_id | bigint (**UNIQUE**, FK -> users, cascadeOnDelete) | El competidor (solo 1 entrenador) |
| estado | enum: 'pending', 'accepted' | Estado del vinculo |
| timestamps | | |

**Clave**: `competidor_id` es **UNIQUE**, lo que garantiza que un competidor solo puede tener UN entrenador a la vez.

### 4.7 Tabla `notifications` — Notificaciones (canal database de Laravel)

**Migracion**: `2026_03_09_000002_create_notifications_table.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | uuid (PK) | UUID unico |
| type | string | Clase FQCN de la notificacion |
| notifiable_type | string | Tipo del modelo notificado (App\Models\User) |
| notifiable_id | bigint | ID del usuario notificado |
| data | text (JSON) | Datos de la notificacion |
| read_at | timestamp? | null = no leida |
| timestamps | | |

### 4.8 Tabla `inscripciones` — Inscripcion formal con documentos

**Migracion**: `2026_03_10_000001_create_inscripciones_table.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | bigint (PK) | |
| user_id | bigint (FK -> users, cascadeOnDelete) | Competidor |
| competicion_id | bigint (FK -> competicions, cascadeOnDelete) | Competicion |
| licencia_path | string? | Ruta al archivo de licencia federativa |
| pago_path | string? | Ruta al justificante de pago |
| estado | enum: borrador/pendiente/aprobada/rechazada | Estado global |
| licencia_estado | enum: valida/valida_dia/no_valida (nullable) | Validacion de la licencia |
| pago_estado | enum: valida/valida_dia/no_valida (nullable) | Validacion del pago |
| licencia_motivo | text? | Motivo si licencia rechazada |
| pago_motivo | text? | Motivo si pago rechazado |
| motivo_rechazo | text? | Motivo general (legacy) |
| categoria | string? | Ej: "Masculino U17" |
| timestamps | | |
| **UNIQUE** | (user_id, competicion_id) | 1 inscripcion por competicion |

### 4.9 Tabla `licencia_validaciones` — Validaciones de licencia

**Migracion**: `2026_03_10_000002_create_licencia_validaciones_table.php`

| Columna | Tipo | Descripcion |
|---|---|---|
| id | bigint (PK) | |
| user_id | bigint (FK -> users, cascadeOnDelete) | Competidor |
| validada_por | bigint (FK -> users, cascadeOnDelete) | Arbitro que valido |
| competicion_id | bigint? (FK -> competicions, nullOnDelete) | Competicion donde se valido |
| tipo | enum: 'valida', 'valida_dia' | Anual o solo para ese dia |
| valida_hasta | date | Fecha de expiracion |
| timestamps | | |

### Diagrama de relaciones:

```
                    ┌─────────┐
                    │  copas  │
                    └────┬────┘
                         │ 1:N (nullOnDelete)
                         ▼
┌───────────┐      ┌─────────────┐      ┌────────────┐
│ ubicacions │──1:N──│ competicions │──N:1──│   users    │ (arbitro)
└───────────┘      └──────┬──────┘      └─────┬──────┘
  (cascadeOnDelete)       │                    │
                          │ 1:N                │
              ┌───────────┴──────────┐         │
              ▼                      ▼         │
    ┌──────────────────┐  ┌──────────────┐     │
    │  inscripciones   │  │competicions_ │     │
    │                  │  │   users      │     │
    │ user_id (FK)─────│──│──user_id     │─────┘
    │ competicion_id   │  │  competicion_│
    └──────────────────┘  │  id          │
              │           └──────────────┘
              │ 1:1
              ▼
    ┌──────────────────────┐
    │ licencia_validaciones│
    └──────────────────────┘

    ┌──────────────────────────┐
    │ entrenador_competidor    │  (self-referencing users <-> users)
    │ entrenador_id -> users   │
    │ competidor_id -> users   │  (UNIQUE: solo 1 entrenador por competidor)
    └──────────────────────────┘

    ┌──────────────────┐
    │  notifications   │  (polimorfismo -> users)
    └──────────────────┘
```

---

## 5. Modelos Eloquent

### 5.1 User (`app/Models/User.php`)

**Modelo central** de la aplicacion. Cada usuario tiene un rol que determina sus permisos.

**Campos fillable**: name, email, password, dni, fecha_nacimiento, provincia, talla, genero, rol

**Casts**:
- `email_verified_at` -> datetime
- `password` -> hashed (se hashea automaticamente)
- `fecha_nacimiento` -> datetime (permite calcular edad)

**Relaciones**:

| Relacion | Tipo | Tabla/Modelo | Descripcion |
|---|---|---|---|
| `competiciones()` | BelongsToMany | competicions_users -> Competicion | Inscripcion legacy (entrenador) |
| `competidoresAceptados()` | BelongsToMany | entrenador_competidor -> User | (Como entrenador) Equipo aceptado |
| `competidoresPendientes()` | BelongsToMany | entrenador_competidor -> User | (Como entrenador) Solicitudes pendientes |
| `entrenadores()` | BelongsToMany | entrenador_competidor -> User | (Como competidor) Su entrenador |
| `competicionesArbitradas()` | HasMany | Competicion (arbitro_id) | (Como arbitro) Competiciones asignadas |
| `inscripciones()` | HasMany | Inscripcion | Inscripciones formales |
| `licenciaValidaciones()` | HasMany | LicenciaValidacion | Validaciones de licencia |

**Metodos de rol** (con herencia jerarquica):

```php
rolNivel(): admin=4, arbitro=3, entrenador=2, competidor=1
isAdmin():      true si nivel >= 4 (solo admin)
isArbitro():    true si nivel >= 3 (arbitro + admin)
isEntrenador(): true si nivel >= 2 (entrenador + arbitro + admin)
isCompetidor(): true si nivel >= 1 (todos)
```

### 5.2 Competicion (`app/Models/Competicion.php`)

**$table** = 'competicions' (forzado porque Laravel pluraliza mal el espanol)

**Campos fillable**: copa_id, arbitro_id, ubicacion_id, name, provincia, fecha_realizacion, fecha_fin, tipo, campeonato, categorias

**Casts**: fecha_realizacion/fecha_fin -> datetime, campeonato -> boolean, categorias -> array (JSON)

**Relaciones**:

| Relacion | Tipo | Descripcion |
|---|---|---|
| `copa()` | BelongsTo Copa | Copa a la que pertenece (nullable) |
| `ubicacion()` | BelongsTo Ubicacion | Rocodromo donde se celebra |
| `arbitro()` | BelongsTo User | Arbitro asignado (nullable) |
| `usuarios()` | BelongsToMany User | Inscritos via pivot legacy |
| `inscripciones()` | HasMany Inscripcion | Inscripciones formales |

**Metodo estatico**: `categoriasDisponibles()` -> devuelve ['U9','U11','U13','U15','U17','U19','Absoluta','Veterana','Promocion']

### 5.3 Copa (`app/Models/Copa.php`)

**Campos fillable**: name, tipo, temporada

**Relaciones**: `competiciones()` -> HasMany Competicion

### 5.4 Ubicacion (`app/Models/Ubicacion.php`)

**Campos fillable**: name, provincia, direccion, alto, ancho, n_lineas

**Relaciones**: `competiciones()` -> HasMany Competicion

### 5.5 Inscripcion (`app/Models/Inscripcion.php`)

**$table** = 'inscripciones' (forzado)

**Campos fillable**: user_id, competicion_id, licencia_path, pago_path, estado, motivo_rechazo, categoria, licencia_estado, pago_estado, licencia_motivo, pago_motivo

**Relaciones**: `user()` -> BelongsTo User, `competicion()` -> BelongsTo Competicion

**Metodos clave**:

| Metodo | Descripcion |
|---|---|
| `documentosCompletos()` | true si ambos archivos estan subidos |
| `recalcularEstado()` | Recalcula estado global segun licencia_estado y pago_estado |
| `etiquetaEstadoDoc($estado)` | Convierte 'valida' -> 'Valida', etc. (para vistas) |
| `calcularCategoria(User)` | Calcula categoria por edad+genero (ej: "Masculino U17") |
| `listaCategorias()` | Lista todas las categorias validas (19 combinaciones) |

**Logica de `recalcularEstado()`**:
```
Si CUALQUIER documento es 'no_valida' -> estado = 'rechazada'
Si AMBOS documentos son 'valida' o 'valida_dia' -> estado = 'aprobada'
En otro caso -> estado = 'pendiente'
```

**Categorias por edad**:
| Categoria | Rango de edad |
|---|---|
| U9 | 7-8 anos |
| U11 | 9-10 |
| U13 | 11-12 |
| U15 | 13-14 |
| U17 | 15-16 |
| U19 | 17-18 |
| Absoluta | 19-34 |
| Veterana | 35+ |
| Promocion | Solo manual (arbitro) |

Cada categoria se combina con genero: "Masculino U17", "Femenino Absoluta", "Mixta Promocion"

### 5.6 LicenciaValidacion (`app/Models/LicenciaValidacion.php`)

**$table** = 'licencia_validaciones'

**Campos fillable**: user_id, validada_por, competicion_id, tipo, valida_hasta

**Relaciones**: `user()`, `validador()`, `competicion()`

**Metodos clave**:
- `estaVigente()` -> true si valida_hasta >= hoy
- `tieneValidezAnual(userId)` -> static, true si tiene licencia anual vigente
- `validezAnual(userId)` -> static, devuelve el registro de validez anual o null

---

## 6. Sistema de Roles y Permisos

### 6.1 Jerarquia de Roles

```
admin (nivel 4)        -> Acceso total
  └── arbitro (nivel 3)    -> Todo lo de entrenador + validar inscripciones
      └── entrenador (nivel 2) -> Todo lo de competidor + gestionar equipo
          └── competidor (nivel 1) -> Inscribirse en competiciones
```

La jerarquia es **acumulativa**: un admin puede hacer todo lo que hace un arbitro, un entrenador y un competidor.

### 6.2 Middleware CheckRol (`app/Http/Middleware/CheckRol.php`)

Registrado en `bootstrap/app.php` con el alias `'rol'`.

**Funcionamiento**:
1. Recibe uno o mas roles como parametros (ej: `middleware('rol:admin')`)
2. Convierte cada rol a su nivel numerico
3. Toma el nivel MINIMO requerido
4. Compara con el nivel del usuario actual
5. Si el nivel del usuario es insuficiente, redirige al dashboard con error

**Uso en rutas**:
```php
Route::middleware(['auth', 'rol:admin'])       // Solo admin (nivel 4)
Route::middleware(['auth', 'rol:arbitro'])      // Arbitro + admin (nivel 3+)
Route::middleware(['auth', 'rol:entrenador'])   // Entrenador + arbitro + admin (nivel 2+)
```

---

## 7. Rutas (routes/web.php)

### 7.1 Ruta publica

| Metodo | URL | Accion | Nombre |
|---|---|---|---|
| GET | `/` | Closure -> welcome.blade.php | - |

### 7.2 Dashboard (auth)

| Metodo | URL | Accion | Nombre |
|---|---|---|---|
| GET | `/dashboard` | Closure (redirige segun rol) | dashboard |

**Logica del dashboard**:
- **Admin** -> redirige a `admin.pruebas`
- **Arbitro** -> redirige a `arbitro.panel`
- **Entrenador** -> muestra `dashboard/entrenador.blade.php`
- **Competidor** -> muestra `dashboard/competidor.blade.php`

### 7.3 Perfil (auth)

| Metodo | URL | Controlador::metodo | Nombre |
|---|---|---|---|
| GET | `/profile` | ProfileController::edit | profile.edit |
| PATCH | `/profile` | ProfileController::update | profile.update |
| DELETE | `/profile` | ProfileController::destroy | profile.destroy |

### 7.4 Panel de Administracion (auth + rol:admin)

Prefijo: `/admin/` — Nombre: `admin.*`

| Metodo | URL | Controlador::metodo | Nombre |
|---|---|---|---|
| GET | `/admin/pruebas` | AdminController::pruebas | admin.pruebas |
| GET | `/admin/copas` | AdminController::copas | admin.copas |
| GET | `/admin/usuarios` | AdminController::usuarios | admin.usuarios |
| GET | `/admin/rocodromos` | AdminController::rocodromos | admin.rocodromos |
| PATCH | `/admin/usuarios/{user}` | AdminController::actualizarUsuario | admin.usuarios.update |
| DELETE | `/admin/usuarios/{user}` | AdminController::destroyUsuario | admin.usuarios.destroy |
| PATCH | `/admin/usuarios/{user}/rol` | AdminController::updateRol | admin.usuarios.rol |
| PATCH | `/admin/competiciones/{competicion}/arbitro` | AdminController::asignarArbitro | admin.competiciones.arbitro |
| POST | `/admin/competiciones` | CompeticionController::store | admin.competiciones.store |
| PATCH | `/admin/competiciones/{competicion}` | CompeticionController::update | admin.competiciones.update |
| DELETE | `/admin/competiciones/{competicion}` | CompeticionController::destroy | admin.competiciones.destroy |
| PATCH | `/admin/competiciones/{competicion}/campeonato` | CompeticionController::toggleCampeonato | admin.competiciones.campeonato |
| POST | `/admin/copas` | CopaController::store | admin.copas.store |
| PATCH | `/admin/copas/{copa}` | CopaController::update | admin.copas.update |
| DELETE | `/admin/copas/{copa}` | CopaController::destroy | admin.copas.destroy |
| POST | `/admin/rocodromos` | UbicacionController::store | admin.rocodromos.store |
| PATCH | `/admin/rocodromos/{ubicacion}` | UbicacionController::update | admin.rocodromos.update |
| DELETE | `/admin/rocodromos/{ubicacion}` | UbicacionController::destroy | admin.rocodromos.destroy |

### 7.5 Acciones de Entrenador (auth + rol:entrenador)

Prefijo: `/entrenador/` — Nombre: `entrenador.*`

| Metodo | URL | Controlador::metodo | Nombre |
|---|---|---|---|
| POST | `/entrenador/solicitar` | EntrenadorController::solicitarVinculo | entrenador.solicitar |
| DELETE | `/entrenador/competidor/{competidor}` | EntrenadorController::eliminarCompetidor | entrenador.eliminar_competidor |
| POST | `/entrenador/inscribir` | EntrenadorController::inscribir | entrenador.inscribir |

### 7.6 Inscripcion en Competiciones (auth)

| Metodo | URL | Controlador::metodo | Nombre |
|---|---|---|---|
| GET | `/competiciones/{competicion}` | InscripcionController::show | competiciones.show |
| POST | `/inscripciones/{competicion}/licencia` | InscripcionController::uploadLicencia | inscripciones.upload_licencia |
| POST | `/inscripciones/{competicion}/pago` | InscripcionController::uploadPago | inscripciones.upload_pago |
| POST | `/inscripciones/{competicion}` | InscripcionController::store | inscripciones.store |

### 7.7 Panel de Arbitro (auth + rol:arbitro)

Prefijo: `/arbitro/` — Nombre: `arbitro.*`

| Metodo | URL | Controlador::metodo | Nombre |
|---|---|---|---|
| GET | `/arbitro/` | ArbitroController::panel | arbitro.panel |
| GET | `/arbitro/entrenador` | ArbitroController::panelEntrenador | arbitro.panel.entrenador |
| GET | `/arbitro/deportista` | ArbitroController::panelDeportista | arbitro.panel.deportista |
| GET | `/arbitro/competicion/{competicion}` | ArbitroController::competicion | arbitro.competicion |
| GET | `/arbitro/competicion/{comp}/categoria/{cat}` | ArbitroController::categoria | arbitro.categoria |
| GET | `/arbitro/inscripcion/{inscripcion}/documento/{tipo}` | ArbitroController::verDocumento | arbitro.ver_documento |
| PATCH | `/arbitro/inscripcion/{inscripcion}/validar` | ArbitroController::validarLicencia | arbitro.validar_licencia |
| PATCH | `/arbitro/inscripcion/{inscripcion}/categoria` | ArbitroController::cambiarCategoria | arbitro.cambiar_categoria |

### 7.8 Notificaciones (auth)

Prefijo: `/notificaciones/` — Nombre: `notificaciones.*`

| Metodo | URL | Controlador::metodo | Nombre |
|---|---|---|---|
| POST | `/notificaciones/{id}/aceptar` | NotificacionController::aceptar | notificaciones.aceptar |
| DELETE | `/notificaciones/{id}/rechazar` | NotificacionController::rechazar | notificaciones.rechazar |
| DELETE | `/notificaciones/desvincular` | NotificacionController::desvincular | notificaciones.desvincular |

### 7.9 Autenticacion (Breeze)

Definidas en `routes/auth.php`: login, register, logout, forgot-password, reset-password, verify-email, confirm-password.

---

## 8. Controladores

### 8.1 AdminController (`app/Http/Controllers/AdminController.php`)

Panel de administracion. Solo accesible por admins.

| Metodo | Ruta | Que hace |
|---|---|---|
| `pruebas()` | GET /admin/pruebas | Lista competiciones con filtros (proximas/este ano/todas, por copa). Carga copas, ubicaciones y arbitros para modales. |
| `copas()` | GET /admin/copas | Lista copas con conteo de competiciones. Filtro por temporada. |
| `usuarios()` | GET /admin/usuarios | Lista usuarios (excluye al admin actual). Filtro por rol, busqueda por nombre/DNI. |
| `rocodromos()` | GET /admin/rocodromos | Lista ubicaciones con conteo de competiciones. |
| `actualizarUsuario()` | PATCH /admin/usuarios/{user} | Edita perfil completo de un usuario (nombre, email, DNI, rol, etc.). |
| `destroyUsuario()` | DELETE /admin/usuarios/{user} | Elimina un usuario (impide auto-eliminacion). |
| `updateRol()` | PATCH /admin/usuarios/{user}/rol | Cambio rapido de rol (no permite asignar admin). |
| `asignarArbitro()` | PATCH /admin/competiciones/{comp}/arbitro | Asigna/desasigna arbitro a una competicion. Verifica que el usuario tenga rol de arbitro o superior. |

### 8.2 ArbitroController (`app/Http/Controllers/ArbitroController.php`)

Panel del arbitro. Solo accesible por arbitros y admins.

| Metodo | Ruta | Que hace |
|---|---|---|
| `panel()` | GET /arbitro/ | Lista competiciones asignadas al arbitro actual. |
| `panelEntrenador()` | GET /arbitro/entrenador | Panel de entrenador (herencia de rol): gestionar equipo. |
| `panelDeportista()` | GET /arbitro/deportista | Panel de deportista: ver inscripciones propias. |
| `competicion()` | GET /arbitro/competicion/{comp} | Dashboard de competicion: resumen por categorias (pendientes/aprobadas/rechazadas). |
| `categoria()` | GET /arbitro/competicion/{comp}/categoria/{cat} | Lista detallada de inscritos en una categoria. Permite validar documentos y cambiar categorias. |
| `verDocumento()` | GET /arbitro/inscripcion/{insc}/documento/{tipo} | Sirve un archivo (licencia o pago) subido por un competidor. |
| `validarLicencia()` | PATCH /arbitro/inscripcion/{insc}/validar | Valida un documento (licencia o pago). Opciones: valida, valida_dia, no_valida. Recalcula estado global. Envia notificacion si cambia a aprobada/rechazada. Soporta AJAX. |
| `cambiarCategoria()` | PATCH /arbitro/inscripcion/{insc}/categoria | Cambia la categoria de un competidor. Valida contra listaCategorias(). |

**Metodos privados de seguridad**:
- `checkArbitro($competicion)` -> verifica que el usuario es el arbitro asignado (abort 403)
- `checkArbitroPorCompeticion($id)` -> igual pero recibiendo el ID

### 8.3 InscripcionController (`app/Http/Controllers/InscripcionController.php`)

Flujo de inscripcion del competidor.

| Metodo | Ruta | Que hace |
|---|---|---|
| `show()` | GET /competiciones/{comp} | Muestra detalle de competicion + formulario de inscripcion. Carga inscripcion existente, verifica licencia anual, marca notificaciones como leidas. |
| `uploadLicencia()` | POST /inscripciones/{comp}/licencia | Sube archivo de licencia (jpg/png/pdf, max 5MB). Crea inscripcion borrador si no existe. Calcula categoria automaticamente. |
| `uploadPago()` | POST /inscripciones/{comp}/pago | Sube justificante de pago. Mismo flujo que uploadLicencia. |
| `store()` | POST /inscripciones/{comp} | Envia inscripcion a revision (borrador -> pendiente). Verifica que documentos estan subidos. Si tiene licencia anual, la marca como valida automaticamente. |

**Metodo privado**: `soloCompetidor()` -> abort 403 si el usuario no es competidor.

### 8.4 EntrenadorController (`app/Http/Controllers/EntrenadorController.php`)

Gestion de equipo e inscripciones del entrenador.

| Metodo | Ruta | Que hace |
|---|---|---|
| `solicitarVinculo()` | POST /entrenador/solicitar | Envia solicitud de vinculo a un competidor. Verifica que sea competidor, que no sea el propio entrenador, y que no tenga ya entrenador. Crea registro en entrenador_competidor y notifica al competidor. |
| `eliminarCompetidor()` | DELETE /entrenador/competidor/{comp} | Desvincula un competidor del equipo. |
| `inscribir()` | POST /entrenador/inscribir | Inscribe al entrenador y/o competidores en una competicion (pivot legacy competicions_users). Verifica que la competicion no ha pasado y que los IDs son de competidores permitidos. |

### 8.5 NotificacionController (`app/Http/Controllers/NotificacionController.php`)

Respuestas del competidor a solicitudes de entrenador.

| Metodo | Ruta | Que hace |
|---|---|---|
| `aceptar($id)` | POST /notificaciones/{id}/aceptar | Acepta solicitud: cambia estado a 'accepted' en pivot, marca notificacion como leida. |
| `rechazar($id)` | DELETE /notificaciones/{id}/rechazar | Rechaza solicitud: elimina registro de pivot y la notificacion. |
| `desvincular()` | DELETE /notificaciones/desvincular | Rompe vinculo con entrenador actual (elimina registro accepted). |

### 8.6 CompeticionController (`app/Http/Controllers/CompeticionController.php`)

CRUD de competiciones. Solo admin.

| Metodo | Que hace |
|---|---|
| `store()` | Crea competicion. Valida tipo, fechas, ubicacion, copa, categorias. |
| `update()` | Actualiza competicion. Misma validacion. |
| `toggleCampeonato()` | Alterna si es campeonato. Regla: solo 1 campeonato por tipo y ano. |
| `destroy()` | Elimina competicion (inscripciones se borran en cascada). |

### 8.7 CopaController (`app/Http/Controllers/CopaController.php`)

CRUD de copas. Solo admin.

| Metodo | Que hace |
|---|---|
| `store()` | Crea copa (tipo: bloque/dificultad/velocidad, temporada: ano). |
| `update()` | Actualiza copa. |
| `destroy()` | Elimina copa. **Proteccion**: no se puede eliminar si tiene competiciones. |

### 8.8 UbicacionController (`app/Http/Controllers/UbicacionController.php`)

CRUD de rocodromos. Solo admin.

| Metodo | Que hace |
|---|---|
| `store()` | Crea ubicacion (nombre, provincia, direccion, medidas del muro). |
| `update()` | Actualiza ubicacion. |
| `destroy()` | Elimina ubicacion. **Proteccion**: no se puede eliminar si tiene competiciones. |

---

## 9. Vistas Blade

### 9.1 Layouts

| Vista | Descripcion |
|---|---|
| `layouts/app.blade.php` | Layout principal con navegacion Bootstrap. Incluye navbar con enlaces segun rol. |
| `layouts/guest.blade.php` | Layout para paginas de invitado (login, registro). |
| `layouts/navigation.blade.php` | Componente de navegacion con dropdown de usuario. |

### 9.2 Paginas publicas

| Vista | Descripcion |
|---|---|
| `welcome.blade.php` | Landing page con botones de login/registro. |

### 9.3 Dashboards (segun rol)

| Vista | Rol | Contenido |
|---|---|---|
| `dashboard.blade.php` | (Wrapper) | Redirige al dashboard correspondiente segun rol |
| `dashboard/admin.blade.php` | Admin | (Redirige a admin.pruebas) |
| `dashboard/arbitro.blade.php` | Arbitro | (Redirige a arbitro.panel) |
| `dashboard/entrenador.blade.php` | Entrenador | Busqueda por DNI, equipo, solicitudes pendientes, inscripcion en competiciones, inscripciones del equipo |
| `dashboard/competidor.blade.php` | Competidor | Notificaciones de entrenador, entrenador actual, lista de competiciones con estado de inscripcion |

### 9.4 Panel de Administracion

| Vista | Descripcion |
|---|---|
| `admin/pruebas.blade.php` | Tabla de competiciones con filtros. Modales para crear/editar. Asignar arbitro. Toggle campeonato. |
| `admin/copas.blade.php` | Tabla de copas. Modales para crear/editar. Conteo de pruebas por copa. |
| `admin/usuarios.blade.php` | Tabla de usuarios. Filtro por rol, busqueda. Modales para editar perfil. Cambio rapido de rol. |
| `admin/rocodromos.blade.php` | Tabla de rocodromos. Modales para crear/editar. Medidas del muro. |
| `admin/partials/sidebar.blade.php` | Sidebar de navegacion del panel admin. |

### 9.5 Panel del Arbitro

| Vista | Descripcion |
|---|---|
| `arbitro/panel/arbitro.blade.php` | Panel principal: lista de competiciones asignadas. |
| `arbitro/panel/entrenador.blade.php` | Panel de entrenador (herencia de rol). |
| `arbitro/panel/deportista.blade.php` | Panel de deportista (inscripciones propias). |
| `arbitro/competicion.blade.php` | Dashboard de competicion: tarjetas resumen + tabla por categorias. |
| `arbitro/categoria.blade.php` | Detalle de categoria: tabla de inscritos, validacion de documentos (AJAX), cambio de categoria. |
| `arbitro/partials/sidebar.blade.php` | Sidebar de navegacion del arbitro. |

### 9.6 Vista del Competidor

| Vista | Descripcion |
|---|---|
| `competidor/competicion-show.blade.php` | Detalle de competicion con formulario de inscripcion. Upload de licencia y pago. Boton de envio. Estado actual. |

### 9.7 Perfil (Breeze)

| Vista | Descripcion |
|---|---|
| `profile/edit.blade.php` | Pagina de edicion de perfil (con partials). |
| `profile/partials/update-profile-information-form.blade.php` | Formulario de datos personales. |
| `profile/partials/update-password-form.blade.php` | Formulario de cambio de contrasena. |
| `profile/partials/delete-user-form.blade.php` | Formulario de eliminacion de cuenta. |

### 9.8 Autenticacion (Breeze)

| Vista | Descripcion |
|---|---|
| `auth/login.blade.php` | Formulario de login. |
| `auth/register.blade.php` | Formulario de registro. |
| `auth/forgot-password.blade.php` | Solicitar reset de contrasena. |
| `auth/reset-password.blade.php` | Establecer nueva contrasena. |
| `auth/verify-email.blade.php` | Verificacion de email. |
| `auth/confirm-password.blade.php` | Confirmar contrasena. |

### 9.9 Componentes Blade (Breeze)

`components/`: application-logo, auth-session-status, danger-button, dropdown, dropdown-link, input-error, input-label, modal, nav-link, primary-button, responsive-nav-link, secondary-button, text-input.

---

## 10. Notificaciones

La aplicacion usa el **canal database** de Laravel para las notificaciones. Se almacenan en la tabla `notifications` y se consultan con `$user->unreadNotifications`.

### 10.1 SolicitudEntrenadorNotification

**Se envia cuando**: Un entrenador solicita vincularse con un competidor.

**Datos JSON almacenados**:
```json
{
    "tipo": "solicitud_entrenador",
    "entrenador_id": 5,
    "entrenador_name": "Juan Garcia",
    "mensaje": "Juan Garcia quiere ser tu entrenador."
}
```

**Donde se muestra**: Dashboard del competidor (`dashboard/competidor.blade.php`) con botones "Aceptar" y "Rechazar".

### 10.2 InscripcionActualizadaNotification

**Se envia cuando**: El arbitro valida documentos y el estado de la inscripcion cambia a 'aprobada' o 'rechazada'.

**Datos JSON almacenados**:
```json
{
    "tipo": "inscripcion_actualizada",
    "competicion_id": 3,
    "competicion": "1a Prueba de Bloque, Sevilla",
    "estado": "aprobada",
    "motivo": null
}
```

**Donde se muestra**: Dashboard del competidor. Se marca como leida automaticamente al acceder al detalle de la competicion.

---

## 11. Seeders y Factories

### 11.1 DatabaseSeeder (seeder principal)

Orden de ejecucion:

1. **Usuario admin**: admin@escalada.com / admin (rol: admin)
2. **2 Copas**: Copa de Bloque 2026, Copa de Dificultad 2026
3. **8 Ubicaciones**: Una por provincia andaluza (Sevilla, Malaga, Granada, Cordoba, Cadiz, Almeria, Huelva, Jaen)
4. **7 Competiciones**:
   - 3 de Bloque (en Copa de Bloque) — la 3a es campeonato
   - 3 de Dificultad (en Copa de Dificultad) — la 3a es campeonato
   - 1 de Velocidad (sin copa, campeonato directo)
5. **300 usuarios aleatorios** (UserFactory, rol: competidor)
6. **TestUsersSeeder**: Usuarios de prueba con credenciales conocidas
7. **InscripcionesSeeder**: 150 inscripciones por competicion (~1050 total)

### 11.2 TestUsersSeeder

Crea usuarios con credenciales predecibles (contrasena: `password`):

| Email | Rol | DNI |
|---|---|---|
| arbitro@escalada.com | arbitro | 11111111B |
| entrenador@escalada.com | entrenador | 11111111A |
| competidor1@escalada.com | competidor | 22222221B |
| competidor2@escalada.com | competidor | 22222222B |
| competidor3@escalada.com | competidor | 22222223B |
| competidor4@escalada.com | competidor | 22222224B |
| competidor5@escalada.com | competidor | 22222225B |

### 11.3 InscripcionesSeeder

- Crea un archivo **placeholder** (GIF 1x1px) para simular documentos subidos
- Asigna el arbitro de prueba a todas las competiciones
- Crea **150 inscripciones por competicion** con esta distribucion:

| Porcentaje | Estado | Descripcion |
|---|---|---|
| 30% | Pendiente | Sin verificar, esperando arbitro |
| 30% | Aprobada | Ambos documentos validos |
| 20% | Rechazada (licencia) | Licencia no valida |
| 10% | Rechazada (pago) | Pago no valido |
| 10% | Pendiente parcial | Licencia verificada, pago pendiente |

Los 5 competidores de prueba se **excluyen** para que su estado este limpio.

### 11.4 Factories

- **UserFactory**: Genera usuarios con nombres espanoles, DNIs aleatorios, edades 8-45, provincias andaluzas, rol competidor.
- **CompeticionFactory**: Genera competiciones aleatorias.
- **CopaFactory**: Genera copas aleatorias.
- **UbicacionFactory**: Genera rocodromos con medidas aleatorias.

---

## 12. Flujos Funcionales Completos

### 12.1 Flujo de Inscripcion del Competidor

```
1. Competidor accede a /competiciones/{id} (InscripcionController::show)
   ├── Se comprueba si tiene licencia anual vigente
   └── Se carga inscripcion existente (si la tiene)

2. Sube licencia federativa (POST /inscripciones/{id}/licencia)
   ├── Se crea inscripcion en estado 'borrador' si no existia
   ├── Se calcula categoria automaticamente por edad+genero
   └── Archivo se guarda en storage/app/public/inscripciones/{comp_id}/{user_id}/
   (Si tiene licencia anual vigente, este paso se salta)

3. Sube justificante de pago (POST /inscripciones/{id}/pago)
   └── Mismo flujo que licencia

4. Envia inscripcion (POST /inscripciones/{id})
   ├── Verifica: competicion no pasada, documentos subidos
   ├── Estado pasa de 'borrador' a 'pendiente'
   ├── Si tiene licencia anual, licencia_estado = 'valida' automaticamente
   └── Recalcula categoria

5. Arbitro revisa (PATCH /arbitro/inscripcion/{id}/validar)
   ├── Valida cada documento: valida / valida_dia / no_valida
   ├── Recalcula estado global
   └── Si estado cambia a aprobada/rechazada → notificacion al competidor

6. Competidor recibe notificacion en su dashboard
   └── Al acceder al detalle de la competicion, se marca como leida
```

### 12.2 Flujo de Vinculacion Entrenador-Competidor

```
1. Entrenador busca competidor por DNI en su dashboard
   └── GET /dashboard?dni=22222221B (formulario en la vista)

2. Entrenador envia solicitud (POST /entrenador/solicitar)
   ├── Verifica: es competidor, no es el mismo, no tiene entrenador
   ├── Crea registro en entrenador_competidor (estado: 'pending')
   └── Envia SolicitudEntrenadorNotification al competidor

3. Competidor ve notificacion en su dashboard
   └── "Juan Garcia quiere ser tu entrenador" [Aceptar] [Rechazar]

4a. Acepta (POST /notificaciones/{id}/aceptar)
    ├── Estado cambia a 'accepted'
    └── Entrenador puede inscribirlo en competiciones

4b. Rechaza (DELETE /notificaciones/{id}/rechazar)
    └── Se elimina el registro y la notificacion

5. Desvinculacion (en cualquier momento)
   ├── Entrenador: DELETE /entrenador/competidor/{id}
   └── Competidor: DELETE /notificaciones/desvincular
```

### 12.3 Flujo de Inscripcion por Entrenador (legacy)

```
1. Entrenador selecciona competicion y marca participantes (checkboxes)
   └── Puede incluirse a si mismo + competidores aceptados

2. POST /entrenador/inscribir
   ├── Verifica: competicion no pasada, IDs en lista de permitidos
   └── Inserta en tabla pivot competicions_users (evita duplicados)
```

### 12.4 Flujo de Validacion del Arbitro

```
1. Arbitro accede a su panel (GET /arbitro/)
   └── Ve competiciones asignadas

2. Selecciona una competicion (GET /arbitro/competicion/{id})
   └── Ve resumen: tarjetas con totales y tabla por categorias

3. Accede a una categoria (GET /arbitro/competicion/{id}/categoria/{cat})
   └── Ve lista de inscritos con estado de cada documento

4. Valida un documento (PATCH /arbitro/inscripcion/{id}/validar)
   ├── Opciones: valida (anual), valida_dia (solo este evento), no_valida
   ├── Si valida/valida_dia licencia → crea registro en licencia_validaciones
   ├── Recalcula estado global de la inscripcion
   └── Si estado cambia → notifica al competidor

5. Puede cambiar categoria (PATCH /arbitro/inscripcion/{id}/categoria)
   └── Ej: mover a "Promocion" o corregir categoria calculada
```

---

## 13. Usuarios de Prueba

Para probar la aplicacion despues de ejecutar `php artisan migrate:fresh --seed`:

| Email | Contrasena | Rol | Acceso |
|---|---|---|---|
| admin@escalada.com | admin | admin | Panel admin completo |
| arbitro@escalada.com | password | arbitro | Panel arbitro + entrenador + deportista |
| entrenador@escalada.com | password | entrenador | Dashboard entrenador |
| competidor1@escalada.com | password | competidor | Dashboard competidor |
| competidor2@escalada.com | password | competidor | Dashboard competidor |
| competidor3@escalada.com | password | competidor | Dashboard competidor |
| competidor4@escalada.com | password | competidor | Dashboard competidor |
| competidor5@escalada.com | password | competidor | Dashboard competidor |

**DNIs de competidores de prueba**: 22222221B a 22222225B (utiles para buscar desde el panel del entrenador).

---

*Documento generado para la presentacion del proyecto de gestion de competiciones de escalada — DAW 3er ano.*
