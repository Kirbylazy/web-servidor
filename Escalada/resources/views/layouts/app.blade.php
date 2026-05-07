{{--
    Layout principal de la aplicación (autenticado).

    Este layout lo extienden TODAS las vistas que requieren autenticación:
      - Dashboard (dashboard.blade.php y dashboard/*.blade.php)
      - Admin (admin/*.blade.php)
      - Árbitro (arbitro/*.blade.php)
      - Competidor (competidor/*.blade.php)
      - Perfil (profile/*.blade.php)

    Proporciona:
      - Navbar superior con enlaces de navegación y usuario actual
      - Zona de contenido principal (@yield('content') o {{ $slot }})
      - Mensajes flash globales (session('status') y session('error'))
      - Sección @yield('scripts') para JS adicional por vista

    Dependencias externas (CDN, sin compilación):
      - Bootstrap 5.3.3 (CSS + JS bundle)
      - Alpine.js 3.x (interactividad reactiva sin build)

    La directiva [x-cloak] oculta elementos Alpine hasta que se inicialicen,
    evitando parpadeos de contenido no procesado.

    Relacionado con:
      - layouts/guest.blade.php → layout para vistas de autenticación (login, register)
      - layouts/navigation.blade.php → componente Breeze original (Tailwind, NO se usa aquí)
      - bootstrap/app.php → configuración de middleware y alias de rutas
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Título dinámico: cada vista puede definir @section('title', 'Mi título') --}}
    <title>@yield('title', 'Escalada')</title>

    {{-- Token CSRF — necesario para peticiones AJAX (fetch con X-CSRF-TOKEN header) --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Bootstrap 5 vía CDN — se usa en TODA la app en vez de Tailwind --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Oculta elementos con x-cloak hasta que Alpine.js los procese --}}
    <style>[x-cloak] { display: none !important; }</style>
</head>

<body class="bg-light">

{{-- ── NAVBAR SUPERIOR ────────────────────────────────────────────────────
     Barra de navegación oscura con:
     - Logo/marca "Escalada" que enlaza a la página principal
     - Enlaces de navegación: Dashboard, Competiciones, Copas, Ubicaciones
     - Info del usuario autenticado (nombre + badge de rol)
     - Botones: Admin (solo admins), Perfil, Salir
     - Si no está autenticado: botón Login
──────────────────────────────────────────────────────────────────────── --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/">Escalada</a>

        {{-- Botón hamburguesa para móvil (Bootstrap collapse) --}}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            {{-- Enlaces de navegación principales --}}
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                </li>
                {{-- Enlaces condicionales: solo se muestran si la ruta existe
                     (las rutas resource pueden no estar registradas en todos los entornos) --}}
                @php use Illuminate\Support\Facades\Route; @endphp

                @if (Route::has('competiciones.index'))
                    <a class="nav-link" href="{{ route('competiciones.index') }}">Competiciones</a>
                @endif

                @if (Route::has('copas.index'))
                    <a class="nav-link" href="{{ route('copas.index') }}">Copas</a>
                @endif

                @if (Route::has('ubicaciones.index'))
                    <a class="nav-link" href="{{ route('ubicaciones.index') }}">Ubicaciones</a>
                @endif
            </ul>

            {{-- Zona derecha: info de usuario y acciones --}}
            <div class="d-flex align-items-center gap-3">
                {{-- Nombre y rol del usuario autenticado --}}
                <span class="text-white-50 small">
                    @auth
                        {{ auth()->user()->name }}
                        {{-- Badge con el rol actual (competidor, entrenador, arbitro, admin) --}}
                        <span class="badge bg-secondary ms-1">{{ auth()->user()->rol }}</span>
                    @else
                        Invitado
                    @endauth
                </span>

                @auth
                    {{-- Botón Admin: solo visible para usuarios con rol admin
                         Enlaza a la ruta admin.usuarios (AdminController@usuarios) --}}
                    @if(auth()->user()->isAdmin())
                        <a class="btn btn-outline-warning btn-sm" href="{{ route('admin.usuarios') }}">
                            Admin
                        </a>
                    @endif

                    {{-- Enlace al perfil del usuario (ProfileController@edit) --}}
                    <a class="btn btn-outline-light btn-sm" href="{{ route('profile.edit') }}">
                        Perfil
                    </a>

                    {{-- Formulario de cierre de sesión (POST a /logout) --}}
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button class="btn btn-warning btn-sm" type="submit">
                            Salir
                        </button>
                    </form>
                @else
                    {{-- Si no está autenticado, mostrar botón de login --}}
                    <a class="btn btn-outline-light btn-sm" href="{{ route('login') }}">Login</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

{{-- ── CONTENIDO PRINCIPAL ────────────────────────────────────────────────
     Zona donde se inyecta el contenido de cada vista hija.
     Soporta dos mecanismos de Blade:
       1. {{ $slot }} → para componentes Blade (x-app-layout)
       2. @yield('content') → para vistas que usan @extends('layouts.app')
──────────────────────────────────────────────────────────────────────── --}}
<main class="container py-4">

    {{-- Mensajes flash globales — los controladores pueden hacer
         redirect()->with('status', '...') o redirect()->with('error', '...') --}}
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Contenido de la vista --}}
    {{ $slot ?? '' }}

    {{-- Soporte para vistas que usan @section('content') con @extends --}}
    @yield('content')

</main>

{{-- Bootstrap JS bundle (incluye Popper.js para dropdowns/modales/tooltips) --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
{{-- Alpine.js vía CDN con defer — se inicializa después de parsear el DOM --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
{{-- Sección para scripts adicionales por vista (ej: arbitro/categoria usa esto para reload al cerrar modal) --}}
@yield('scripts')
</body>
</html>
