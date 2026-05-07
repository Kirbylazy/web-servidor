{{--
    Panel Árbitro — Pestaña "Árbitro" (competiciones asignadas).

    Vista del panel con sidebar que muestra las competiciones que este usuario
    tiene asignadas como árbitro. Es la pestaña principal del panel del árbitro.

    Recibe datos de ArbitroController@panelArbitro:
      - $competicionesArbitradas → competiciones donde arbitro_id = auth()->id()

    Muestra una tabla con: nombre (+ badge campeonato), fecha, tipo, provincia,
    copa asociada y enlace "Gestionar inscripciones" a arbitro.competicion.

    Diferencia con dashboard/arbitro.blade.php:
      - Esta vista SOLO muestra la sección de competiciones arbitradas
      - Usa sidebar (arbitro/partials/sidebar) para navegar entre pestañas
      - El dashboard combina todo en una sola página sin sidebar

    Extiende: layouts/app.blade.php
    Incluye: arbitro/partials/sidebar.blade.php

    Relacionado con:
      - arbitro/panel/entrenador.blade.php → pestaña de funciones de entrenador
      - arbitro/panel/deportista.blade.php → pestaña de inscripciones propias
      - arbitro/competicion.blade.php → gestión detallada de inscripciones
      - ArbitroController@panelArbitro → prepara los datos
--}}
@extends('layouts.app')
@section('title', 'Panel Árbitro')

@section('content')
<div class="row g-4">

{{-- Sidebar con pestañas: Árbitro | Entrenador | Deportista --}}
<div class="col-auto">
    @include('arbitro.partials.sidebar')
</div>

<div class="col">

<h4 class="mb-4">Mis competiciones asignadas</h4>

{{-- Tabla de competiciones asignadas como árbitro --}}
<div class="card">
    <div class="card-body p-0">
        @if($competicionesArbitradas->isEmpty())
            <p class="text-muted p-3 mb-0">No tienes ninguna competición asignada aún.</p>
        @else
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Competición</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Provincia</th>
                        <th>Copa</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($competicionesArbitradas as $c)
                        <tr>
                            <td>
                                {{ $c->name }}
                                @if($c->campeonato)
                                    <span class="badge bg-danger ms-1">Campeonato</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $c->fecha_realizacion?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $c->tipo }}</span></td>
                            <td>{{ $c->provincia }}</td>
                            <td>{{ $c->copa?->name ?? '—' }}</td>
                            <td class="text-end">
                                {{-- Enlace a la vista de gestión de inscripciones por categoría --}}
                                <a href="{{ route('arbitro.competicion', $c->id) }}" class="btn btn-sm btn-primary">
                                    Gestionar inscripciones
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

</div>
</div>
@endsection
