{{--
    Sidebar de navegación del panel del árbitro.

    Se incluye con @include('arbitro.partials.sidebar') en las vistas del panel
    del árbitro (separado del dashboard):
      - arbitro/panel/arbitro.blade.php → competiciones asignadas como árbitro
      - arbitro/panel/entrenador.blade.php → funcionalidades heredadas de entrenador
      - arbitro/panel/deportista.blade.php → inscripciones propias como competidor

    El árbitro hereda funcionalidades de entrenador y competidor gracias al sistema
    de roles jerárquico (admin > arbitro > entrenador > competidor), por eso el
    sidebar tiene tres secciones.

    Cada enlace cambia de estilo según la ruta actual (request()->routeIs()).
    Es sticky para permanecer visible al hacer scroll.

    Las rutas corresponden a ArbitroController:
      - arbitro.panel → panelArbitro() → competiciones asignadas
      - arbitro.panel.entrenador → panelEntrenador() → gestión de equipo
      - arbitro.panel.deportista → panelDeportista() → inscripciones propias

    Relacionado con:
      - admin/partials/sidebar.blade.php → sidebar análogo del panel admin
--}}
<div class="d-flex flex-column gap-2" style="position:sticky; top:80px; min-width:150px">
    {{-- Título del sidebar --}}
    <p class="text-muted small fw-bold mb-1 text-uppercase px-1">Panel árbitro</p>

    {{-- Pestaña Árbitro: ver competiciones asignadas para arbitrar --}}
    <a href="{{ route('arbitro.panel') }}"
       class="btn text-start {{ request()->routeIs('arbitro.panel') ? 'btn-dark' : 'btn-outline-dark' }}">
        Árbitro
    </a>

    {{-- Pestaña Entrenador: gestionar equipo (funcionalidad heredada de entrenador) --}}
    <a href="{{ route('arbitro.panel.entrenador') }}"
       class="btn text-start {{ request()->routeIs('arbitro.panel.entrenador') ? 'btn-dark' : 'btn-outline-dark' }}">
        Entrenador
    </a>

    {{-- Pestaña Deportista: ver inscripciones propias como competidor --}}
    <a href="{{ route('arbitro.panel.deportista') }}"
       class="btn text-start {{ request()->routeIs('arbitro.panel.deportista') ? 'btn-dark' : 'btn-outline-dark' }}">
        Deportista
    </a>
</div>
