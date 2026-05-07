<?php

/**
 * web.php — Archivo principal de rutas de la aplicación de escalada.
 *
 * Define TODAS las rutas web de la app, organizadas por rol/funcionalidad:
 *   1. Ruta pública (welcome)
 *   2. Dashboard dinámico (redirige según el rol del usuario)
 *   3. Perfil de usuario (cualquier autenticado)
 *   4. Panel de administración (solo admin)
 *   5. Acciones de entrenador (entrenador+)
 *   6. Inscripción en competiciones (cualquier autenticado, upload solo competidor)
 *   7. Panel de árbitro (arbitro+)
 *   8. Notificaciones del competidor (cualquier autenticado)
 *
 * Middleware de roles: 'rol:X' usa CheckRol (app/Http/Middleware/CheckRol.php)
 * que implementa jerarquía: admin(4) > arbitro(3) > entrenador(2) > competidor(1).
 * Un admin puede acceder a TODAS las rutas protegidas por rol.
 */

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EntrenadorController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\ArbitroController;
use App\Http\Controllers\CopaController;
use App\Http\Controllers\CompeticionController;
use App\Http\Controllers\UbicacionController;
use Illuminate\Support\Facades\Route;
use App\Models\Competicion;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| 1. Ruta pública — Página de bienvenida
|--------------------------------------------------------------------------
| Vista: welcome.blade.php (landing page con botones de login/registro)
*/
Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| 2. Dashboard dinámico — Redirige según el rol del usuario autenticado
|--------------------------------------------------------------------------
| Este closure actúa como "router de dashboards":
|   - Admin      → redirige a admin.pruebas (panel de administración)
|   - Árbitro    → redirige a arbitro.panel (panel del árbitro)
|   - Entrenador → carga dashboard/entrenador.blade.php con datos de equipo
|   - Competidor → carga dashboard/competidor.blade.php con competiciones e inscripciones
|
| Middleware: 'auth' — requiere usuario autenticado
*/
Route::get('/dashboard', function () {
    // Query inicial de competiciones futuras (usado por entrenador si no redirige antes)
    $competiciones = Competicion::query()
        ->where('fecha_realizacion', '>=', now())
        ->orderBy('fecha_realizacion')
        ->paginate(10);

    $user = auth()->user();

    // ── ADMIN: redirigir directamente al panel de administración ──
    if ($user->isAdmin()) {
        return redirect()->route('admin.pruebas');
    }

    // ── ÁRBITRO (no admin): redirigir al panel del árbitro ──
    if ($user->isArbitro() && !$user->isAdmin()) {
        return redirect()->route('arbitro.panel');
    }

    // ── ENTRENADOR: mostrar dashboard con gestión de equipo ──
    if ($user->isEntrenador()) {
        // Competidores vinculados y aceptados
        $competidores = $user->competidoresAceptados()->get();
        // Solicitudes de vínculo pendientes de respuesta
        $pendientes   = $user->competidoresPendientes()->get();

        // Búsqueda de competidor por DNI (formulario GET en la vista)
        $userBuscado = null;
        if (request('dni')) {
            $userBuscado = User::where('dni', request('dni'))
                ->where('rol', 'competidor')
                ->first();
        }

        // Inscripciones propias del entrenador (tabla pivot legacy competicions_users)
        $misInscripciones = $user->competiciones()->with('copa', 'ubicacion')->get();

        // Recopilar inscripciones de todos los competidores del equipo
        $inscripcionesEquipo = collect();
        foreach ($competidores as $comp) {
            foreach ($comp->competiciones()->with('copa', 'ubicacion')->get() as $c) {
                $inscripcionesEquipo->push(['competicion' => $c, 'competidor' => $comp]);
            }
        }

        return view('dashboard.entrenador', compact(
            'competiciones', 'competidores', 'pendientes',
            'userBuscado', 'misInscripciones', 'inscripcionesEquipo'
        ));
    }

    // ── COMPETIDOR (por defecto): mostrar competiciones con estado de inscripción ──
    // Cargar el entrenador actual del competidor (0 o 1)
    $entrenador     = $user->entrenadores()->first();
    // Notificaciones sin leer (solicitudes de entrenador, cambios de inscripción)
    $notificaciones = $user->unreadNotifications;

    // Cargar TODAS las competiciones (no solo futuras) para que vea las pasadas también
    $competiciones = Competicion::with('copa', 'ubicacion')
        ->orderBy('fecha_realizacion', 'desc')
        ->paginate(12);

    // Cargar inscripciones del usuario para las competiciones de la página actual.
    // keyBy('competicion_id') permite acceso rápido por ID en la vista:
    // $inscripciones[$competicion->id] → la inscripción del usuario en esa competición
    $inscripciones = $user->inscripciones()
        ->whereIn('competicion_id', $competiciones->pluck('id'))
        ->get()
        ->keyBy('competicion_id');

    return view('dashboard.competidor', compact('competiciones', 'entrenador', 'notificaciones', 'inscripciones'));
})->middleware(['auth'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| 3. Perfil de usuario — Accesible por cualquier usuario autenticado
|--------------------------------------------------------------------------
| Controlador: ProfileController
| Vista: profile/edit.blade.php (con partials para info, contraseña y eliminación)
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| 4. Panel de administración — Solo admin (middleware rol:admin, nivel 4)
|--------------------------------------------------------------------------
| Prefijo URL: /admin/...
| Nombre: admin.*
| Controladores: AdminController, CompeticionController, CopaController, UbicacionController
| Vistas: resources/views/admin/
|
| Gestión completa de: pruebas (competiciones), copas, usuarios y rocódromos
*/
Route::middleware(['auth', 'rol:admin'])->prefix('admin')->name('admin.')->group(function () {
    // ── Páginas de listado (GET) ──
    Route::get('/pruebas',    [AdminController::class, 'pruebas'])->name('pruebas');       // Lista de competiciones con filtros
    Route::get('/copas',      [AdminController::class, 'copas'])->name('copas');           // Lista de copas
    Route::get('/usuarios',   [AdminController::class, 'usuarios'])->name('usuarios');     // Lista de usuarios con búsqueda
    Route::get('/rocodromos', [AdminController::class, 'rocodromos'])->name('rocodromos'); // Lista de rocódromos

    // ── CRUD de usuarios ──
    Route::patch('/usuarios/{user}',     [AdminController::class, 'actualizarUsuario'])->name('usuarios.update');  // Editar perfil completo
    Route::delete('/usuarios/{user}',    [AdminController::class, 'destroyUsuario'])->name('usuarios.destroy');    // Eliminar usuario
    Route::patch('/usuarios/{user}/rol', [AdminController::class, 'updateRol'])->name('usuarios.rol');             // Cambio rápido de rol

    // ── CRUD de competiciones + asignación de árbitro ──
    Route::patch('/competiciones/{competicion}/arbitro',    [AdminController::class, 'asignarArbitro'])->name('competiciones.arbitro');
    Route::post('/competiciones',                            [CompeticionController::class, 'store'])->name('competiciones.store');
    Route::patch('/competiciones/{competicion}',             [CompeticionController::class, 'update'])->name('competiciones.update');
    Route::delete('/competiciones/{competicion}',            [CompeticionController::class, 'destroy'])->name('competiciones.destroy');
    Route::patch('/competiciones/{competicion}/campeonato',  [CompeticionController::class, 'toggleCampeonato'])->name('competiciones.campeonato');

    // ── CRUD de copas ──
    Route::post('/copas',           [CopaController::class, 'store'])->name('copas.store');
    Route::patch('/copas/{copa}',   [CopaController::class, 'update'])->name('copas.update');
    Route::delete('/copas/{copa}',  [CopaController::class, 'destroy'])->name('copas.destroy');

    // ── CRUD de rocódromos/ubicaciones ──
    Route::post('/rocodromos',               [UbicacionController::class, 'store'])->name('rocodromos.store');
    Route::patch('/rocodromos/{ubicacion}',  [UbicacionController::class, 'update'])->name('rocodromos.update');
    Route::delete('/rocodromos/{ubicacion}', [UbicacionController::class, 'destroy'])->name('rocodromos.destroy');
});

/*
|--------------------------------------------------------------------------
| 5. Acciones de entrenador — Entrenador+ (middleware rol:entrenador, nivel 2+)
|--------------------------------------------------------------------------
| Prefijo URL: /entrenador/...
| Nombre: entrenador.*
| Controlador: EntrenadorController
|
| Acciones POST/DELETE para gestionar equipo e inscribir participantes.
| Las vistas están en dashboard/entrenador.blade.php y arbitro/panel/entrenador.blade.php
*/
Route::middleware(['auth', 'rol:entrenador'])->prefix('entrenador')->name('entrenador.')->group(function () {
    Route::post('/solicitar', [EntrenadorController::class, 'solicitarVinculo'])->name('solicitar');                // Enviar solicitud de vínculo
    Route::delete('/competidor/{competidor}', [EntrenadorController::class, 'eliminarCompetidor'])->name('eliminar_competidor'); // Desvincular competidor
    Route::post('/inscribir', [EntrenadorController::class, 'inscribir'])->name('inscribir');                       // Inscribir equipo en competición
});

/*
|--------------------------------------------------------------------------
| 6. Inscripción en competiciones — Cualquier usuario autenticado
|--------------------------------------------------------------------------
| Controlador: InscripcionController
| Vista: competidor/competicion-show.blade.php
|
| El GET es accesible por todos (ver detalle), pero las acciones POST
| de subida de documentos y envío están restringidas internamente a
| rol 'competidor' mediante InscripcionController::soloCompetidor().
*/
Route::middleware('auth')->group(function () {
    Route::get('/competiciones/{competicion}', [InscripcionController::class, 'show'])->name('competiciones.show');                       // Ver detalle de competición
    Route::post('/inscripciones/{competicion}/licencia', [InscripcionController::class, 'uploadLicencia'])->name('inscripciones.upload_licencia'); // Subir licencia federativa
    Route::post('/inscripciones/{competicion}/pago', [InscripcionController::class, 'uploadPago'])->name('inscripciones.upload_pago');             // Subir justificante de pago
    Route::post('/inscripciones/{competicion}', [InscripcionController::class, 'store'])->name('inscripciones.store');                             // Enviar inscripción a revisión
});

/*
|--------------------------------------------------------------------------
| 7. Panel de árbitro — Árbitro+ (middleware rol:arbitro, nivel 3+)
|--------------------------------------------------------------------------
| Prefijo URL: /arbitro/...
| Nombre: arbitro.*
| Controlador: ArbitroController
| Vistas: resources/views/arbitro/
|
| Gestión de inscripciones: ver competiciones asignadas, revisar por categoría,
| validar documentos (licencia/pago), cambiar categorías, servir documentos.
*/
Route::middleware(['auth', 'rol:arbitro'])->prefix('arbitro')->name('arbitro.')->group(function () {
    Route::get('/',                  [ArbitroController::class, 'panel'])->name('panel');                     // Panel principal: competiciones asignadas
    Route::get('/entrenador',        [ArbitroController::class, 'panelEntrenador'])->name('panel.entrenador'); // Panel de entrenador (herencia de rol)
    Route::get('/deportista',        [ArbitroController::class, 'panelDeportista'])->name('panel.deportista'); // Panel de deportista (herencia de rol)
    Route::get('/competicion/{competicion}', [ArbitroController::class, 'competicion'])->name('competicion'); // Dashboard de competición: resumen por categorías
    Route::get('/competicion/{competicion}/categoria/{categoria}', [ArbitroController::class, 'categoria'])->name('categoria'); // Detalle de categoría: lista de inscritos
    Route::get('/inscripcion/{inscripcion}/documento/{tipo}', [ArbitroController::class, 'verDocumento'])->name('ver_documento');  // Servir documento (licencia/pago)
    Route::patch('/inscripcion/{inscripcion}/validar', [ArbitroController::class, 'validarLicencia'])->name('validar_licencia');   // Validar documento
    Route::patch('/inscripcion/{inscripcion}/categoria', [ArbitroController::class, 'cambiarCategoria'])->name('cambiar_categoria'); // Cambiar categoría
});

/*
|--------------------------------------------------------------------------
| 8. Notificaciones — Respuestas del competidor a solicitudes de entrenador
|--------------------------------------------------------------------------
| Prefijo URL: /notificaciones/...
| Nombre: notificaciones.*
| Controlador: NotificacionController
| Vista: botones en dashboard/competidor.blade.php
|
| Accesible por cualquier autenticado (el competidor gestiona sus notificaciones).
*/
Route::middleware('auth')->prefix('notificaciones')->name('notificaciones.')->group(function () {
    Route::post('/{id}/aceptar', [NotificacionController::class, 'aceptar'])->name('aceptar');     // Aceptar solicitud de entrenador
    Route::delete('/{id}/rechazar', [NotificacionController::class, 'rechazar'])->name('rechazar'); // Rechazar solicitud de entrenador
    Route::delete('/desvincular', [NotificacionController::class, 'desvincular'])->name('desvincular'); // Romper vínculo con entrenador
});

/*
|--------------------------------------------------------------------------
| Rutas de autenticación (Laravel Breeze)
|--------------------------------------------------------------------------
| Incluye: login, register, forgot-password, reset-password, verify-email, logout.
| Definidas en routes/auth.php (generado por Breeze).
*/
require __DIR__.'/auth.php';
