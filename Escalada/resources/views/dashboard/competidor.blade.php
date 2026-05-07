{{--
    Dashboard del competidor — Vista principal para usuarios con rol 'competidor'.

    Recibe datos del controlador (ruta 'dashboard' en web.php, lógica en closure):
      - $competiciones → colección paginada de competiciones futuras
      - $inscripciones → array indexado por competicion_id con la inscripción del usuario
                          (permite saber el estado de inscripción en cada competición)
      - $notificaciones → notificaciones no leídas del usuario (database channel):
                           - tipo 'inscripcion_actualizada' → árbitro aprobó/rechazó inscripción
                           - tipo 'solicitud_entrenador' → entrenador quiere vincularle
      - $entrenador → el entrenador actual (relación aceptada) o null

    Secciones de la vista:
      1. Notificaciones de inscripción (aprobada/rechazada por el árbitro)
      2. Solicitudes de entrenador pendientes (aceptar/rechazar)
      3. Entrenador actual con opción de desvincularse
      4. Grid de competiciones con estado de inscripción visual

    Estados de inscripción mostrados:
      - sin_inscripcion → botón "Inscribirme"
      - borrador → (no visible, se trata como sin_inscripcion)
      - pendiente → badge amarillo "Pendiente de revisión"
      - aprobada → badge verde "Inscripción aprobada" + borde verde en tarjeta
      - rechazada → badge rojo "Inscripción rechazada" + botón "Reinscribirme"

    Extiende: layouts/app.blade.php

    Relacionado con:
      - competidor/competicion-show.blade.php → vista detallada donde se sube documentación
      - InscripcionController → gestión de uploads y confirmación de inscripción
      - NotificacionController → aceptar/rechazar solicitudes de entrenador
      - InscripcionActualizadaNotification → notificación del árbitro
      - SolicitudEntrenadorNotification → notificación del entrenador
--}}
@extends('layouts.app')

@section('title', 'Dashboard — Competidor')

@section('content')

{{-- ── NOTIFICACIONES DE INSCRIPCIÓN ──────────────────────────────────────
     Muestra alertas cuando el árbitro ha aprobado o rechazado una inscripción.
     Las notificaciones vienen del canal database (InscripcionActualizadaNotification).
     Se marcan como leídas automáticamente al cargar el dashboard.
──────────────────────────────────────────────────────────────────────── --}}
@foreach($notificaciones as $notif)
    @if($notif->data['tipo'] === 'inscripcion_actualizada')
        @if($notif->data['estado'] === 'aprobada')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>¡Inscripción aprobada!</strong>
                Tu inscripción en <strong>{{ $notif->data['competicion'] }}</strong> ha sido verificada y aprobada.
                <a href="{{ route('competiciones.show', $notif->data['competicion_id']) }}" class="alert-link ms-2">Ver competición</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @else
            {{-- Inscripción rechazada — incluye motivo si el árbitro lo proporcionó --}}
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Inscripción rechazada.</strong>
                <strong>{{ $notif->data['competicion'] }}</strong>:
                {{ $notif->data['motivo'] ?? 'Documentación no válida.' }}
                <a href="{{ route('competiciones.show', $notif->data['competicion_id']) }}" class="alert-link ms-2">Volver a intentarlo</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
    @endif
@endforeach

{{-- ── SOLICITUDES DE ENTRENADOR PENDIENTES ───────────────────────────────
     Si hay notificaciones de tipo 'solicitud_entrenador', muestra una tarjeta
     con botones Aceptar/Rechazar para cada solicitud.
     - Aceptar: POST a notificaciones.aceptar (NotificacionController@aceptar)
     - Rechazar: DELETE a notificaciones.rechazar (NotificacionController@rechazar)
     Un competidor solo puede tener UN entrenador a la vez.
──────────────────────────────────────────────────────────────────────── --}}
@if($notificaciones->where('data.tipo', 'solicitud_entrenador')->isNotEmpty())
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark fw-semibold">
            Solicitudes de entrenador
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach($notificaciones as $notif)
                    @if($notif->data['tipo'] === 'solicitud_entrenador')
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{-- Mensaje de la solicitud (incluye nombre del entrenador) --}}
                            <span>{{ $notif->data['mensaje'] }}</span>
                            <div class="d-flex gap-2">
                                {{-- Aceptar solicitud → crea relación accepted en entrenador_competidor --}}
                                <form method="POST" action="{{ route('notificaciones.aceptar', $notif->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success">Aceptar</button>
                                </form>
                                {{-- Rechazar solicitud → elimina la relación pending --}}
                                <form method="POST" action="{{ route('notificaciones.rechazar', $notif->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Rechazar</button>
                                </form>
                            </div>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- ── ENTRENADOR ACTUAL ──────────────────────────────────────────────────
     Si el competidor tiene un entrenador vinculado (estado 'accepted'),
     muestra su nombre y un botón para desvincularse.
     La desvinculación es bidireccional: tanto entrenador como competidor pueden hacerla.
──────────────────────────────────────────────────────────────────────── --}}
@if($entrenador)
    <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
        <span>Tu entrenador es <strong>{{ $entrenador->name }}</strong>.</span>
        {{-- DELETE a notificaciones.desvincular (NotificacionController@desvincular) --}}
        <form method="POST" action="{{ route('notificaciones.desvincular') }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">Desvincularme</button>
        </form>
    </div>
@endif

{{-- ── GRID DE COMPETICIONES ──────────────────────────────────────────────
     Muestra todas las competiciones futuras en tarjetas con:
     - Fecha, nombre, tipo, copa, ubicación
     - Estado visual de inscripción (badge + color de borde de tarjeta)
     - Botón contextual según estado (Inscribirme, Ver estado, Reinscribirme, etc.)
     El estado se obtiene del array $inscripciones indexado por competicion_id.
──────────────────────────────────────────────────────────────────────── --}}
<h3 class="mb-3">Competiciones</h3>

@if($competiciones->count() === 0)
    <div class="alert alert-info">No hay competiciones disponibles.</div>
@else
    <div class="row g-3">
        @foreach($competiciones as $c)
            @php
                // Buscar inscripción del usuario para esta competición
                $ins    = $inscripciones[$c->id] ?? null;
                $estado = $ins?->estado ?? 'sin_inscripcion';
                $esPasada = $c->fecha_realizacion?->isPast() ?? false;
            @endphp
            <div class="col-md-6 col-lg-4">
                {{-- Borde de la tarjeta cambia según estado: verde=aprobada, rojo=rechazada --}}
                <div class="card h-100 {{ $estado === 'aprobada' ? 'border-success' : ($estado === 'rechazada' ? 'border-danger' : '') }}">
                    <div class="card-body d-flex flex-column">

                        {{-- Cabecera: fecha + badge "Pasada" si ya ocurrió + badge "Campeonato" --}}
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div class="text-muted small">
                                {{ $c->fecha_realizacion?->format('d/m/Y') ?? 'Sin fecha' }}
                                @if($esPasada)
                                    <span class="badge bg-secondary ms-1">Pasada</span>
                                @endif
                            </div>
                            @if($c->campeonato)
                                <span class="badge bg-danger">Campeonato</span>
                            @endif
                        </div>

                        <h5 class="card-title mb-2">{{ $c->name }}</h5>

                        {{-- Detalles de la competición --}}
                        <div class="small mb-3">
                            <div><strong>Tipo:</strong> {{ ucfirst($c->tipo) }}</div>
                            <div><strong>Copa:</strong> {{ $c->copa?->name ?? '—' }}</div>
                            <div><strong>Ubicación:</strong> {{ $c->ubicacion?->name ?? '—' }}</div>
                        </div>

                        {{-- Estado de inscripción — se empuja al fondo con mt-auto --}}
                        <div class="mt-auto">
                            {{-- Badge de estado (aprobada/pendiente/rechazada) --}}
                            @if($estado === 'aprobada')
                                <span class="badge bg-success fs-6 d-block text-center py-2 mb-2">
                                    Inscripción aprobada
                                </span>
                            @elseif($estado === 'pendiente')
                                <span class="badge bg-warning text-dark fs-6 d-block text-center py-2 mb-2">
                                    Pendiente de revisión
                                </span>
                            @elseif($estado === 'rechazada')
                                <span class="badge bg-danger fs-6 d-block text-center py-2 mb-2">
                                    Inscripción rechazada
                                </span>
                            @endif

                            {{-- Botón contextual: enlaza a competiciones.show donde
                                 el competidor puede ver detalles y subir documentación --}}
                            <a href="{{ route('competiciones.show', $c->id) }}"
                               class="btn btn-sm w-100 {{ $estado === 'aprobada' ? 'btn-success' : ($estado === 'rechazada' ? 'btn-danger' : 'btn-outline-primary') }}">
                                @if($estado === 'aprobada')
                                    Ver mi inscripción
                                @elseif($estado === 'pendiente')
                                    Ver estado
                                @elseif($estado === 'rechazada')
                                    Reinscribirme
                                @elseif(!$esPasada)
                                    Inscribirme
                                @else
                                    Ver detalles
                                @endif
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $competiciones->links() }}
    </div>
@endif

@endsection
