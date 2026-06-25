<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * GuestLayout — Componente de layout para visitantes (no autenticados).
 *
 * Este componente renderiza la vista layouts/guest.blade.php, que es un layout
 * minimalista centrado usado para las páginas de autenticación:
 *   - Login (auth/login.blade.php)
 *   - Registro (auth/register.blade.php)
 *   - Recuperación de contraseña
 *   - Verificación de email
 *
 * Se usa con: <x-guest-layout> ... </x-guest-layout>
 * Usa Tailwind CSS (heredado de Laravel Breeze).
 */
class GuestLayout extends Component
{
    /**
     * Renderizar el layout de invitado.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
