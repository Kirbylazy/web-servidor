<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * AppLayout — Componente de layout para usuarios autenticados.
 *
 * Este componente renderiza la vista layouts/app.blade.php, que es el layout
 * principal de la aplicación. Incluye:
 *   - Navbar con navegación, nombre del usuario, badge de rol y botones de perfil/logout
 *   - Bootstrap 5 CDN y Alpine.js CDN
 *   - Mensajes flash (status, error, success)
 *   - Slot para el contenido de cada página
 *
 * Se usa en las vistas con: <x-app-layout> ... </x-app-layout>
 * Todas las páginas autenticadas (dashboards, admin, árbitro, perfil, etc.) lo usan.
 */
class AppLayout extends Component
{
    /**
     * Renderizar el layout principal de la aplicación.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
