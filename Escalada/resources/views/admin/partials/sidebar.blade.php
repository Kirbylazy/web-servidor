{{--
    Sidebar de navegación del panel de administración.

    Se incluye con @include('admin.partials.sidebar') en todas las vistas admin:
      - admin/pruebas.blade.php → gestión de competiciones/pruebas
      - admin/copas.blade.php → gestión de copas/torneos
      - admin/usuarios.blade.php → gestión de usuarios y roles
      - admin/rocodromos.blade.php → gestión de ubicaciones/rocódromos

    Cada enlace cambia de estilo (btn-dark vs btn-outline-dark) según la ruta
    actual, usando request()->routeIs() para resaltar la sección activa.

    Es sticky (position:sticky top:80px) para que permanezca visible al hacer
    scroll en tablas largas.

    Las rutas corresponden a AdminController:
      - admin.pruebas → pruebas()
      - admin.copas → copas()
      - admin.usuarios → usuarios()
      - admin.rocodromos → rocodromos()

    Relacionado con:
      - arbitro/partials/sidebar.blade.php → sidebar análogo para el panel del árbitro
--}}
<div class="d-flex flex-column gap-2" style="position:sticky; top:80px; min-width:150px">
    {{-- Título del sidebar --}}
    <p class="text-muted small fw-bold mb-1 text-uppercase px-1">Administración</p>

    {{-- Enlace a gestión de pruebas/competiciones --}}
    <a href="{{ route('admin.pruebas') }}"
       class="btn text-start {{ request()->routeIs('admin.pruebas') ? 'btn-dark' : 'btn-outline-dark' }}">
        Pruebas
    </a>

    {{-- Enlace a gestión de copas/torneos --}}
    <a href="{{ route('admin.copas') }}"
       class="btn text-start {{ request()->routeIs('admin.copas') ? 'btn-dark' : 'btn-outline-dark' }}">
        Copas
    </a>

    {{-- Enlace a gestión de usuarios (cambio de roles, edición, eliminación) --}}
    <a href="{{ route('admin.usuarios') }}"
       class="btn text-start {{ request()->routeIs('admin.usuarios') ? 'btn-dark' : 'btn-outline-dark' }}">
        Usuarios
    </a>

    {{-- Enlace a gestión de rocódromos/ubicaciones --}}
    <a href="{{ route('admin.rocodromos') }}"
       class="btn text-start {{ request()->routeIs('admin.rocodromos') ? 'btn-dark' : 'btn-outline-dark' }}">
        Rocódromos
    </a>
</div>
