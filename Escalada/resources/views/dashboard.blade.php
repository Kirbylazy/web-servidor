{{--
    Dashboard genérico — Vista por defecto para usuarios sin dashboard específico.

    Se muestra cuando el rol del usuario no tiene una vista de dashboard propia
    (actualmente todos los roles tienen su propia vista, pero esta sirve como fallback).

    Datos recibidos del controlador (ruta 'dashboard' en web.php):
      - $competiciones → colección paginada de competiciones futuras (fecha > ahora),
                          ordenadas por fecha_realizacion ASC, con relaciones copa y ubicacion

    Muestra:
      - Tarjetas con las próximas competiciones (fecha, nombre, provincia, tipo, copa, ubicación)
      - Enlace "Ver" a la vista detallada de cada competición (si la ruta existe)
      - Botón "Inscribirme" placeholder (deshabilitado, pendiente de implementar aquí)
      - Paginación automática de Laravel

    Extiende: layouts/app.blade.php

    Relacionado con:
      - dashboard/admin.blade.php → dashboard para rol admin
      - dashboard/arbitro.blade.php → dashboard para rol arbitro
      - dashboard/entrenador.blade.php → dashboard para rol entrenador
      - dashboard/competidor.blade.php → dashboard para rol competidor
      - routes/web.php → ruta GET /dashboard con lógica de redirección según rol
--}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<h3 class="mb-3">Próximas competiciones</h3>

{{-- Si no hay competiciones futuras, mostrar aviso informativo --}}
@if($competiciones->count() === 0)
    <div class="alert alert-info">
        No hay competiciones futuras ahora mismo.
    </div>
@else
    {{-- Grid de tarjetas Bootstrap: 2 columnas en md, 3 en lg --}}
    <div class="row g-3">
        @foreach($competiciones as $c)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        {{-- Fecha de la competición (formato español dd/mm/YYYY HH:mm) --}}
                        <div class="text-muted small">
                            {{ $c->fecha_realizacion?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                        </div>

                        <h5 class="card-title mb-2">{{ $c->name }}</h5>

                        {{-- Detalles: provincia, tipo de escalada, copa asociada, ubicación --}}
                        <div class="small">
                            <div><strong>Provincia:</strong> {{ $c->provincia }}</div>
                            <div><strong>Tipo:</strong> {{ $c->tipo }}</div>
                            <div><strong>Copa:</strong> {{ $c->copa?->name ?? '—' }}</div>
                            <div><strong>Ubicación:</strong> {{ $c->ubicacion?->name ?? '—' }}</div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            {{-- Enlace "Ver" solo si la ruta competiciones.show existe --}}
                            @if(\Illuminate\Support\Facades\Route::has('competiciones.show'))
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('competiciones.show', $c->id) }}">
                                    Ver
                                </a>
                            @endif

                            {{-- Botón placeholder — la inscripción real se hace desde
                                 competidor/competicion-show.blade.php --}}
                            <button class="btn btn-sm btn-primary" disabled>
                                Inscribirme (próximamente)
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Paginación automática de Laravel --}}
    <div class="mt-4">
        {{ $competiciones->links() }}
    </div>
@endif
@endsection
