{{--
    Dashboard del entrenador — Vista principal para usuarios con rol 'entrenador'.

    Recibe datos del controlador (ruta 'dashboard' en web.php, lógica en closure):
      - $competidores → competidores aceptados en el equipo del entrenador
                          (relación entrenador_competidor con estado 'accepted')
      - $pendientes → competidores con solicitud pendiente (estado 'pending')
      - $userBuscado → resultado de búsqueda por DNI (si se pasó ?dni= en la URL) o null
      - $competiciones → competiciones futuras disponibles para inscribir
      - $misInscripciones → competiciones donde el entrenador está inscrito (pivot legacy)
      - $inscripcionesEquipo → array con competiciones donde están inscritos los competidores del equipo

    Secciones de la vista:
      1. Mi equipo → tabla de competidores aceptados con opción de desvincular
      2. Solicitudes pendientes → lista de solicitudes enviadas esperando aceptación
      3. Añadir competidor por DNI → buscador + resultado + botón enviar solicitud
      4. Inscribir en competición → seleccionar competición + checkboxes de participantes
      5. Inscripciones activas → tabla combinada de inscripciones propias + del equipo

    Extiende: layouts/app.blade.php

    Relacionado con:
      - EntrenadorController → solicitar(), inscribir(), eliminarCompetidor()
      - SolicitudEntrenadorNotification → notificación al competidor
      - dashboard/arbitro.blade.php → dashboard del árbitro que hereda estas funcionalidades
      - arbitro/panel/entrenador.blade.php → versión panel del árbitro con sidebar
--}}
@extends('layouts.app')

@section('title', 'Dashboard — Entrenador')

@section('content')
<h3 class="mb-4">Panel de entrenador</h3>

{{-- ── 1. MI EQUIPO ───────────────────────────────────────────────────────
     Tabla de competidores que han aceptado la solicitud del entrenador.
     Cada fila tiene nombre, DNI, provincia y botón para desvincular.
     La desvinculación usa DELETE a entrenador.eliminar_competidor.
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">Mi equipo</div>
    <div class="card-body p-0">
        @if($competidores->isEmpty())
            <p class="text-muted p-3 mb-0">Aún no tienes competidores aceptados.</p>
        @else
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>DNI</th>
                        <th>Provincia</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($competidores as $comp)
                        <tr>
                            <td>{{ $comp->name }}</td>
                            <td>{{ $comp->dni }}</td>
                            <td>{{ $comp->provincia }}</td>
                            <td class="text-end">
                                {{-- Desvincular competidor del equipo
                                     EntrenadorController@eliminarCompetidor --}}
                                <form method="POST"
                                      action="{{ route('entrenador.eliminar_competidor', $comp->id) }}"
                                      onsubmit="return confirm('¿Eliminar a {{ $comp->name }} de tu equipo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Desvincular</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- ── 2. SOLICITUDES PENDIENTES ──────────────────────────────────────────
     Lista de solicitudes enviadas a competidores que aún no han respondido.
     El entrenador puede cancelar la solicitud (mismo endpoint que desvincular).
     Solo se muestra si hay solicitudes pendientes.
──────────────────────────────────────────────────────────────────────── --}}
@if($pendientes->isNotEmpty())
    <div class="card mb-4 border-secondary">
        <div class="card-header fw-semibold text-muted">Solicitudes enviadas (pendientes)</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach($pendientes as $p)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $p->name }} <span class="text-muted small">({{ $p->dni }})</span></span>
                        <form method="POST"
                              action="{{ route('entrenador.eliminar_competidor', $p->id) }}"
                              onsubmit="return confirm('¿Cancelar la solicitud a {{ $p->name }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-secondary">Cancelar solicitud</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- ── 3. AÑADIR COMPETIDOR POR DNI ───────────────────────────────────────
     Buscador: el entrenador introduce un DNI y se busca el competidor.
     Si se encuentra ($userBuscado), muestra nombre y botón "Enviar solicitud".
     POST a entrenador.solicitar (EntrenadorController@solicitar) →
     crea relación pending + envía SolicitudEntrenadorNotification al competidor.
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">Añadir competidor por DNI</div>
    <div class="card-body">
        {{-- Formulario GET: recarga la misma página con ?dni=valor --}}
        <form method="GET" action="{{ route('dashboard') }}" class="d-flex gap-2 mb-0">
            <input type="text" name="dni" class="form-control" style="max-width:220px"
                   placeholder="DNI del competidor"
                   value="{{ request('dni') }}">
            <button class="btn btn-outline-primary">Buscar</button>
        </form>

        {{-- Resultado: no encontrado --}}
        @if(request('dni') && !$userBuscado)
            <div class="alert alert-warning mt-3 mb-0">
                No se encontró ningún competidor con ese DNI.
            </div>
        @endif

        {{-- Resultado: competidor encontrado → mostrar datos y botón solicitud --}}
        @if($userBuscado)
            <div class="alert alert-light border mt-3 d-flex justify-content-between align-items-center mb-0">
                <div>
                    <strong>{{ $userBuscado->name }}</strong>
                    <span class="text-muted small ms-2">{{ $userBuscado->dni }} · {{ $userBuscado->provincia }}</span>
                </div>
                <form method="POST" action="{{ route('entrenador.solicitar') }}">
                    @csrf
                    <input type="hidden" name="competidor_id" value="{{ $userBuscado->id }}">
                    <button class="btn btn-sm btn-success">Enviar solicitud</button>
                </form>
            </div>
        @endif
    </div>
</div>

{{-- ── 4. INSCRIBIR EN COMPETICIÓN ────────────────────────────────────────
     El entrenador puede inscribir a sí mismo y/o a sus competidores aceptados
     en una competición futura. Usa el sistema de inscripción legacy (pivot).
     POST a entrenador.inscribir (EntrenadorController@inscribir).
     Solo se muestra si hay competiciones disponibles.
──────────────────────────────────────────────────────────────────────── --}}
@if($competiciones->isNotEmpty() && ($competidores->isNotEmpty() || true))
    <div class="card mb-4">
        <div class="card-header fw-semibold">Inscribir en competición</div>
        <div class="card-body">
            <form method="POST" action="{{ route('entrenador.inscribir') }}">
                @csrf

                {{-- Selector de competición --}}
                <div class="mb-3">
                    <label class="form-label">Competición</label>
                    <select name="competicion_id" class="form-select" required>
                        <option value="" disabled selected>Selecciona una competición...</option>
                        @foreach($competiciones as $c)
                            <option value="{{ $c->id }}">
                                {{ $c->name }} — {{ $c->fecha_realizacion?->format('d/m/Y') ?? 'Sin fecha' }}
                                ({{ $c->provincia }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Checkboxes de participantes: el propio entrenador + competidores del equipo --}}
                <div class="mb-3">
                    <label class="form-label">Participantes</label>
                    <div class="border rounded p-3">
                        {{-- Checkbox para inscribirse a sí mismo --}}
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="participantes[]" value="{{ auth()->id() }}" id="self">
                            <label class="form-check-label" for="self">
                                <strong>Yo mismo</strong>
                                <span class="badge bg-success ms-1">Entrenador</span>
                            </label>
                        </div>

                        {{-- Checkboxes para cada competidor del equipo --}}
                        @foreach($competidores as $comp)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="participantes[]" value="{{ $comp->id }}"
                                       id="comp-{{ $comp->id }}">
                                <label class="form-check-label" for="comp-{{ $comp->id }}">
                                    {{ $comp->name }}
                                    <span class="text-muted small">({{ $comp->dni }})</span>
                                </label>
                            </div>
                        @endforeach

                        @if($competidores->isEmpty())
                            <p class="text-muted small mb-0 mt-1">Aún no tienes competidores en tu equipo.</p>
                        @endif
                    </div>
                </div>

                <button class="btn btn-primary">Inscribir seleccionados</button>
            </form>
        </div>
    </div>
@endif

{{-- ── 5. INSCRIPCIONES ACTIVAS ───────────────────────────────────────────
     Tabla combinada que muestra:
     - Inscripciones del propio entrenador ($misInscripciones) con badge "Yo"
     - Inscripciones de los competidores del equipo ($inscripcionesEquipo)
     Ambas listas se fusionan en $todasInscripciones para mostrar en una sola tabla.
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">Inscripciones activas</div>
    <div class="card-body p-0">
        {{-- Combinar inscripciones propias y del equipo en una sola colección --}}
        @php
            $todasInscripciones = collect();

            // Inscripciones propias del entrenador
            foreach($misInscripciones as $c) {
                $todasInscripciones->push([
                    'competicion' => $c,
                    'participante' => auth()->user()->name,
                    'tipo' => 'Yo',
                ]);
            }
            // Inscripciones de competidores del equipo
            foreach($inscripcionesEquipo as $item) {
                $todasInscripciones->push([
                    'competicion' => $item['competicion'],
                    'participante' => $item['competidor']->name,
                    'tipo' => 'Competidor',
                ]);
            }
        @endphp

        @if($todasInscripciones->isEmpty())
            <p class="text-muted p-3 mb-0">Ninguna inscripción registrada aún.</p>
        @else
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Competición</th>
                        <th>Fecha</th>
                        <th>Provincia</th>
                        <th>Participante</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($todasInscripciones as $ins)
                        <tr>
                            <td>{{ $ins['competicion']->name }}</td>
                            <td>{{ $ins['competicion']->fecha_realizacion?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $ins['competicion']->provincia }}</td>
                            <td>
                                {{ $ins['participante'] }}
                                {{-- Badge "Yo" para distinguir inscripciones propias --}}
                                @if($ins['tipo'] === 'Yo')
                                    <span class="badge bg-success ms-1">Yo</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

@endsection
