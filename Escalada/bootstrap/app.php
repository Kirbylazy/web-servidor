<?php

/**
 * bootstrap/app.php — Configuración de arranque de la aplicación Laravel.
 *
 * Este archivo configura la aplicación: rutas, middleware y excepciones.
 * Es el punto de entrada que Laravel usa para construir la instancia de Application.
 *
 * Configuraciones importantes para este proyecto:
 *   - Rutas: carga routes/web.php (rutas web) y routes/console.php (comandos artisan)
 *   - Health check: endpoint /up para monitoreo de disponibilidad
 *   - Middleware: registra el alias 'rol' para el middleware CheckRol,
 *     que se usa en routes/web.php para proteger rutas por rol de usuario
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',          // Archivo de rutas web principal
        commands: __DIR__.'/../routes/console.php',  // Comandos artisan personalizados
        health: '/up',                               // Endpoint de health check
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registrar el middleware CheckRol con el alias 'rol'.
        // Esto permite usar middleware('rol:admin') en las rutas (routes/web.php).
        // CheckRol implementa jerarquía de roles: admin > arbitro > entrenador > competidor.
        $middleware->alias(['rol' => \App\Http\Middleware\CheckRol::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sin personalización de excepciones (usa los handlers por defecto de Laravel)
    })->create();
