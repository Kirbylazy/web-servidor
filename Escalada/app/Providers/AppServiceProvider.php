<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider — Proveedor de servicios principal de la aplicación.
 *
 * Este es el lugar para registrar bindings en el contenedor de servicios
 * y configurar servicios durante el arranque de la aplicación.
 *
 * Actualmente está vacío — toda la configuración personalizada del proyecto
 * se hace en otros lugares:
 *   - Middleware: bootstrap/app.php (alias 'rol' para CheckRol)
 *   - Rutas: routes/web.php y routes/auth.php
 *   - Casts y relaciones: en cada modelo directamente
 *
 * Registrado automáticamente en bootstrap/providers.php.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registrar servicios en el contenedor (bindings, singletons, etc.).
     * Vacío — no hay servicios personalizados que registrar.
     */
    public function register(): void
    {
        //
    }

    /**
     * Configurar servicios después del arranque (observers, macros, etc.).
     * Vacío — no hay configuración de arranque personalizada.
     */
    public function boot(): void
    {
        //
    }
}
