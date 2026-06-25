{{--
    Árbitro — Gestión de inscripciones de una competición.

    Vista donde el árbitro ve el resumen de inscripciones de una competición
    que tiene asignada, organizadas por categoría.

    Recibe datos de ArbitroController@competicion:
      - $competicion → modelo Competicion con relaciones copa y ubicacion
      - $totales → array con contadores: total, pendiente, aprobada, rechazada
      - $categorias → colección indexada por nombre de categoría, cada una con:
                       total, pendiente, aprobada, rechazada (contadores por estado)

    Secciones de la vista:
      1. Cabecera: botón volver + nombre/fecha/tipo/provincia de la competición
      2. Resumen en 4 tarjetas: total, pendientes (amarillo), aprobadas (verde), rechazadas (rojo)
      3. Tabla de categorías: nombre + contadores por estado + enlace "Ver competidores"

    El enlace "Ver competidores" lleva a arbitro/categoria.blade.php donde el árbitro
    puede verificar documentación de cada inscripción individualmente.

    Rutas usadas:
      - dashboard → volver al dashboard del árbitro
      - arbitro.categoria → ArbitroController@categoria (ver inscripciones de una categoría)

    Extiende: layouts/app.blade.php

    Relacionado con:
      - arbitro/categoria.blade.php → vista detallada con verificación de documentos
      - dashboard/arbitro.blade.php → dashboard con lista de competiciones asignadas
      - ArbitroController@competicion → prepara los datos de resumen
      - Inscripcion (modelo) → estados: borrador, pendiente, aprobada, rechazada
--}}
@extends('layouts.app')

@section('title', 'Gestión — ' . $competicion->name)

@section('content')

{{-- Mensaje flash de éxito (ej: tras aprobar/rechazar inscripciones) --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Cabecera: botón volver + datos de la competición --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">&larr; Volver al dashboard</a>
    <div>
        <h3 class="mb-0">{{ $competicion->name }}</h3>
        <div class="text-muted small">
            {{ $competicion->fecha_realizacion?->format('d/m/Y H:i') ?? '—' }}
            &nbsp;·&nbsp; {{ ucfirst($competicion->tipo) }}
            &nbsp;·&nbsp; {{ $competicion->provincia }}
            @if($competicion->campeonato)
                &nbsp;·&nbsp; <span class="badge bg-danger">Campeonato</span>
            @endif
        </div>
    </div>
</div>

{{-- ── RESUMEN TOTAL ──────────────────────────────────────────────────────
     4 tarjetas con los contadores globales de inscripciones.
     Colores: neutro (total), amarillo (pendientes), verde (aprobadas), rojo (rechazadas).
──────────────────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold">{{ $totales['total'] }}</div>
                <div class="text-muted small">Total solicitudes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-warning">{{ $totales['pendiente'] }}</div>
                <div class="text-muted small">Pendientes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-success">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success">{{ $totales['aprobada'] }}</div>
                <div class="text-muted small">Aprobadas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-danger">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-danger">{{ $totales['rechazada'] }}</div>
                <div class="text-muted small">Rechazadas</div>
            </div>
        </div>
    </div>
</div>

{{-- ── TABLA DE CATEGORÍAS ────────────────────────────────────────────────
     Muestra cada categoría (ej: "U13 Masculino", "Absoluta Femenino") con:
     - Contadores por estado (badges de colores si > 0)
     - Enlace "Ver competidores" → arbitro.categoria para verificar documentos
     Las categorías se generan agrupando inscripciones por su campo 'categoria'.
──────────────────────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header fw-semibold">Categorías</div>
    @if($categorias->isEmpty())
        <div class="card-body">
            <p class="text-muted mb-0">No hay inscripciones recibidas aún.</p>
        </div>
    @else
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Categoría</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Pendientes</th>
                        <th class="text-center">Aprobadas</th>
                        <th class="text-center">Rechazadas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categorias as $nombre => $datos)
                        <tr>
                            <td class="fw-semibold">{{ $nombre }}</td>
                            <td class="text-center">{{ $datos['total'] }}</td>
                            <td class="text-center">
                                @if($datos['pendiente'] > 0)
                                    <span class="badge bg-warning text-dark">{{ $datos['pendiente'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($datos['aprobada'] > 0)
                                    <span class="badge bg-success">{{ $datos['aprobada'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($datos['rechazada'] > 0)
                                    <span class="badge bg-danger">{{ $datos['rechazada'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{-- Enlace a la vista de detalle por categoría
                                     Parámetros: competicion_id y nombre de categoría --}}
                                <a href="{{ route('arbitro.categoria', [$competicion->id, $nombre]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Ver competidores
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
