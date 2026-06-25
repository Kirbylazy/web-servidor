{{--
    Dashboard del árbitro — Vista principal para usuarios con rol 'arbitro'.

    El árbitro hereda funcionalidades de entrenador y competidor (roles jerárquicos),
    por lo que este dashboard combina tres áreas:
      1. Competiciones asignadas como árbitro (función exclusiva del rol)
      2. Gestión de equipo como entrenador (función heredada)
      3. Inscripción en competiciones como competidor (función heredada)

    Recibe datos del controlador (ruta 'dashboard' en web.php, lógica en closure):
      - $competicionesArbitradas → competiciones donde este user es el árbitro asignado
      - $competidores → competidores aceptados en su equipo (entrenador_competidor, estado 'accepted')
      - $pendientes → solicitudes de vínculo pendientes (estado 'pending')
      - $userBuscado → resultado de búsqueda por DNI o null
      - $competiciones → competiciones futuras disponibles
      - $misInscripciones → competiciones donde está inscrito como participante
      - $inscripcionesEquipo → inscripciones de los competidores de su equipo

    NOTA: El árbitro también tiene un panel con sidebar separado en arbitro/panel/*.blade.php
    que organiza la misma información en pestañas (árbitro/entrenador/deportista).

    Extiende: layouts/app.blade.php

    Relacionado con:
      - arbitro/competicion.blade.php → vista detallada de inscripciones por categoría
      - arbitro/categoria.blade.php → verificación de documentación de cada competidor
      - arbitro/panel/*.blade.php → panel alternativo con sidebar
      - dashboard/entrenador.blade.php → las secciones 2-6 son prácticamente iguales
      - EntrenadorController → solicitar(), inscribir(), eliminarCompetidor()
--}}
@extends('layouts.app')

@section('title', 'Dashboard — Árbitro')

@section('content')
<h3 class="mb-4">Panel de árbitro</h3>

{{-- ── 1. MIS COMPETICIONES ASIGNADAS ─────────────────────────────────────
     Tabla con las competiciones donde este usuario es el árbitro designado.
     Desde aquí accede a gestionar inscripciones (verificar documentos).
     Enlace "Gestionar inscripciones" → arbitro.competicion (ArbitroController@competicion)
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">Mis competiciones asignadas</div>
    <div class="card-body p-0">
        @if($competicionesArbitradas->isEmpty())
            <p class="text-muted p-3 mb-0">No tienes ninguna competición asignada aún.</p>
        @else
            <table class="table table-hover mb-0">
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
                            <td>{{ $c->fecha_realizacion?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $c->tipo }}</span></td>
                            <td>{{ $c->provincia }}</td>
                            <td>{{ $c->copa?->name ?? '—' }}</td>
                            <td class="text-end">
                                {{-- Enlace a la vista de gestión de inscripciones por categoría --}}
                                <a href="{{ route('arbitro.competicion', $c->id) }}"
                                   class="btn btn-sm btn-primary">
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

{{-- ── 2. MI EQUIPO (hereda derechos de entrenador) ───────────────────────
     Igual que en dashboard/entrenador.blade.php:
     tabla de competidores aceptados con botón de desvincular.
     DELETE a entrenador.eliminar_competidor (EntrenadorController@eliminarCompetidor)
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">
        Mi equipo <span class="badge bg-success ms-1">Entrenador</span>
    </div>
    <div class="card-body p-0">
        @if($competidores->isEmpty())
            <p class="text-muted p-3 mb-0">Aún no tienes competidores en tu equipo.</p>
        @else
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>DNI</th><th>Provincia</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($competidores as $comp)
                        <tr>
                            <td>{{ $comp->name }}</td>
                            <td>{{ $comp->dni }}</td>
                            <td>{{ $comp->provincia }}</td>
                            <td class="text-end">
                                <form method="POST"
                                      action="{{ route('entrenador.eliminar_competidor', $comp->id) }}"
                                      onsubmit="return confirm('¿Desvincular a {{ $comp->name }}?')">
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

{{-- ── 3. SOLICITUDES PENDIENTES ──────────────────────────────────────────
     Solicitudes de vínculo enviadas a competidores que aún no han respondido.
     El árbitro puede cancelar la solicitud.
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
                              onsubmit="return confirm('¿Cancelar la solicitud?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-secondary">Cancelar</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- ── 4. AÑADIR COMPETIDOR POR DNI ───────────────────────────────────────
     Buscador de competidores por DNI + botón de enviar solicitud.
     Igual que en dashboard/entrenador.blade.php.
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">Añadir competidor por DNI</div>
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard') }}" class="d-flex gap-2 mb-0">
            <input type="text" name="dni" class="form-control" style="max-width:220px"
                   placeholder="DNI del competidor" value="{{ request('dni') }}">
            <button class="btn btn-outline-primary">Buscar</button>
        </form>

        @if(request('dni') && !$userBuscado)
            <div class="alert alert-warning mt-3 mb-0">No se encontró ningún competidor con ese DNI.</div>
        @endif

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

{{-- ── 5. INSCRIBIR EN COMPETICIÓN ────────────────────────────────────────
     El árbitro puede inscribirse a sí mismo y a sus competidores.
     Igual que en dashboard/entrenador.blade.php, pero con badge "Árbitro".
     POST a entrenador.inscribir (EntrenadorController@inscribir)
──────────────────────────────────────────────────────────────────────── --}}
@if($competiciones->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header fw-semibold">Inscribir en competición</div>
        <div class="card-body">
            <form method="POST" action="{{ route('entrenador.inscribir') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Competición</label>
                    <select name="competicion_id" class="form-select" required>
                        <option value="" disabled selected>Selecciona una competición...</option>
                        @foreach($competiciones as $c)
                            <option value="{{ $c->id }}">
                                {{ $c->name }} — {{ $c->fecha_realizacion?->format('d/m/Y') ?? 'Sin fecha' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Participantes</label>
                    <div class="border rounded p-3">
                        {{-- Checkbox para inscribirse a sí mismo (como árbitro) --}}
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="participantes[]" value="{{ auth()->id() }}" id="self">
                            <label class="form-check-label" for="self">
                                <strong>Yo mismo</strong>
                                <span class="badge bg-warning text-dark ms-1">Árbitro</span>
                            </label>
                        </div>
                        {{-- Checkboxes de competidores del equipo --}}
                        @foreach($competidores as $comp)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="participantes[]" value="{{ $comp->id }}" id="comp-{{ $comp->id }}">
                                <label class="form-check-label" for="comp-{{ $comp->id }}">
                                    {{ $comp->name }} <span class="text-muted small">({{ $comp->dni }})</span>
                                </label>
                            </div>
                        @endforeach
                        @if($competidores->isEmpty())
                            <p class="text-muted small mb-0 mt-1">No tienes competidores en tu equipo.</p>
                        @endif
                    </div>
                </div>
                <button class="btn btn-primary">Inscribir seleccionados</button>
            </form>
        </div>
    </div>
@endif

{{-- ── 6. INSCRIPCIONES ACTIVAS ───────────────────────────────────────────
     Tabla combinada de inscripciones propias + del equipo.
     Igual que en dashboard/entrenador.blade.php.
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">Inscripciones activas</div>
    <div class="card-body p-0">
        @php
            $todasInscripciones = collect();
            foreach($misInscripciones as $c) {
                $todasInscripciones->push(['competicion' => $c, 'participante' => auth()->user()->name, 'tipo' => 'Yo']);
            }
            foreach($inscripcionesEquipo as $item) {
                $todasInscripciones->push(['competicion' => $item['competicion'], 'participante' => $item['competidor']->name, 'tipo' => 'Competidor']);
            }
        @endphp
        @if($todasInscripciones->isEmpty())
            <p class="text-muted p-3 mb-0">Ninguna inscripción registrada aún.</p>
        @else
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Competición</th><th>Fecha</th><th>Participante</th></tr>
                </thead>
                <tbody>
                    @foreach($todasInscripciones as $ins)
                        <tr>
                            <td>{{ $ins['competicion']->name }}</td>
                            <td>{{ $ins['competicion']->fecha_realizacion?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                {{ $ins['participante'] }}
                                @if($ins['tipo'] === 'Yo')
                                    <span class="badge bg-warning text-dark ms-1">Yo</span>
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
