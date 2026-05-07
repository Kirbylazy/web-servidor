{{--
    Admin — Gestión de Pruebas/Competiciones.

    Panel de administración para gestionar las competiciones de escalada.
    Accesible solo para admins vía middleware 'rol:admin'.

    Recibe datos de AdminController@pruebas:
      - $competiciones → colección de competiciones filtradas, con relaciones copa y arbitro
      - $copas → todas las copas (para filtro y modales)
      - $ubicaciones → todos los rocódromos (para modales)
      - $arbitros → usuarios con rol 'arbitro' (para asignación)
      - $filtro → filtro de tiempo activo: 'proximas', 'este_año' o 'todas'
      - $copaId → filtro de copa activo (ID, 'sin_copa' o '')

    Funcionalidades:
      1. Filtros combinados: tiempo (próximas/este año/todas) + copa (select)
      2. Tabla completa: nombre + categorías, fecha inicio/fin, tipo, copa, campeonato toggle,
         árbitro actual + selector de asignación, editar + eliminar
      3. Modal "Editar Prueba" → formulario PATCH con todos los campos, precargado vía JS
      4. Modal "Crear Prueba" → formulario POST con auto-detección de copa vía Alpine.js

    Interactividad:
      - Modal Editar: JS vanilla con evento show.bs.modal, lee data-attributes del botón
      - Modal Crear: Alpine.js para auto-detectar copa por tipo+temporada (igual que dashboard/admin)
      - Toggle campeonato: PATCH inline con confirmación JS
      - Asignación de árbitro: formulario PATCH inline

    Rutas usadas:
      - admin.competiciones.store → CompeticionController@store (POST)
      - /admin/competiciones/{id} → CompeticionController@update (PATCH)
      - admin.competiciones.destroy → CompeticionController@destroy (DELETE)
      - admin.competiciones.campeonato → CompeticionController@toggleCampeonato (PATCH)
      - admin.competiciones.arbitro → CompeticionController@asignarArbitro (PATCH)

    Extiende: layouts/app.blade.php
    Incluye: admin/partials/sidebar.blade.php

    Relacionado con:
      - admin/copas.blade.php → las copas agrupan pruebas por tipo y temporada
      - admin/rocodromos.blade.php → los rocódromos son ubicaciones de las pruebas
      - dashboard/admin.blade.php → versión resumida sin sidebar ni filtros avanzados
      - Competicion (modelo) → campos, relaciones copa/ubicacion/arbitro, categoriasDisponibles()
      - CompeticionController → CRUD + toggleCampeonato + asignarArbitro
--}}
@extends('layouts.app')
@section('title', 'Admin — Pruebas')

@section('content')
<div class="row g-4">

{{-- Sidebar admin --}}
<div class="col-auto">
    @include('admin.partials.sidebar')
</div>

{{-- Contenido principal --}}
<div class="col">

{{-- Cabecera: título + botón crear --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Pruebas</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearPrueba">+ Crear Prueba</button>
</div>

{{-- Filtros: temporalidad + copa asociada --}}
<form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    {{-- Filtro temporal --}}
    <select name="filtro" class="form-select" style="width:auto" onchange="this.form.submit()">
        <option value="proximas" @selected($filtro==='proximas')>Próximas pruebas</option>
        <option value="este_año" @selected($filtro==='este_año')>Este año</option>
        <option value="todas"    @selected($filtro==='todas')>Todas</option>
    </select>
    {{-- Filtro por copa --}}
    <select name="copa_id" class="form-select" style="width:auto" onchange="this.form.submit()">
        <option value="">Todas las copas</option>
        <option value="sin_copa" @selected($copaId==='sin_copa')>Sin copa</option>
        @foreach($copas as $copa)
            <option value="{{ $copa->id }}" @selected($copaId == $copa->id)>{{ $copa->name }}</option>
        @endforeach
    </select>
</form>

{{-- Tabla de pruebas/competiciones --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Prueba</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Copa</th>
                    <th>Campeonato</th>
                    <th>Árbitro</th>
                    <th>Asignar árbitro</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($competiciones as $c)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $c->name }}</div>
                            {{-- Categorías participantes (ej: U11, U13, Absoluta) --}}
                            @if($c->categorias)
                                <div class="text-muted small">{{ implode(', ', $c->categorias) }}</div>
                            @endif
                        </td>
                        <td class="small text-muted text-nowrap">
                            {{ $c->fecha_realizacion?->format('d/m/Y') ?? '—' }}
                            {{-- Si tiene fecha fin (competición de varios días) --}}
                            @if($c->fecha_fin)
                                <span class="text-muted">→ {{ $c->fecha_fin->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td><span class="badge bg-secondary">{{ $c->tipo }}</span></td>
                        <td class="small">{{ $c->copa?->name ?? '—' }}</td>
                        <td>
                            {{-- Badge + botón toggle campeonato --}}
                            @if($c->campeonato)
                                <span class="badge bg-danger me-1">Campeonato</span>
                            @endif
                            <form method="POST" action="{{ route('admin.competiciones.campeonato', $c->id) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm {{ $c->campeonato ? 'btn-outline-danger' : 'btn-outline-secondary' }}"
                                        onclick="return confirm('{{ $c->campeonato ? '¿Quitar campeonato?' : '¿Designar como campeonato?' }}')">
                                    {{ $c->campeonato ? 'Quitar' : 'Designar' }}
                                </button>
                            </form>
                        </td>
                        <td class="small">{{ $c->arbitro?->name ?? '—' }}</td>
                        <td>
                            {{-- Selector inline de árbitro --}}
                            <form method="POST" action="{{ route('admin.competiciones.arbitro', $c->id) }}" class="d-flex gap-1">
                                @csrf @method('PATCH')
                                <select name="arbitro_id" class="form-select form-select-sm" style="width:auto">
                                    <option value="">— Sin árbitro —</option>
                                    @foreach($arbitros as $a)
                                        <option value="{{ $a->id }}" @selected($c->arbitro_id === $a->id)>{{ $a->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-primary">Guardar</button>
                            </form>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                {{-- Botón Editar: pasa TODOS los datos de la competición
                                     como data-attributes para el modal JS --}}
                                <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarPrueba"
                                        data-id="{{ $c->id }}"
                                        data-name="{{ $c->name }}"
                                        data-tipo="{{ $c->tipo }}"
                                        data-fecha="{{ $c->fecha_realizacion?->format('Y-m-d\TH:i') }}"
                                        data-fecha-fin="{{ $c->fecha_fin?->format('Y-m-d\TH:i') }}"
                                        data-provincia="{{ $c->provincia }}"
                                        data-ubicacion-id="{{ $c->ubicacion_id }}"
                                        data-copa-id="{{ $c->copa_id ?? '' }}"
                                        data-arbitro-id="{{ $c->arbitro_id ?? '' }}"
                                        data-categorias="{{ json_encode($c->categorias ?? []) }}">
                                    Editar
                                </button>
                                {{-- Botón Eliminar con aviso de cascada (inscripciones) --}}
                                <form method="POST" action="{{ route('admin.competiciones.destroy', $c->id) }}"
                                      onsubmit="return confirm('¿Eliminar «{{ addslashes($c->name) }}»? Se eliminarán también todas sus inscripciones.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay pruebas con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>

</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL EDITAR PRUEBA
     ══════════════════════════════════════════════════════════════════════
     Formulario para editar una competición existente.
     Los campos se rellenan con JS vanilla al abrir el modal (show.bs.modal).
     Incluye: nombre, tipo, fecha inicio/fin, provincia, ubicación, copa,
     árbitro y categorías (checkboxes).
     PATCH a /admin/competiciones/{id} (CompeticionController@update)
──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarPrueba" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar prueba — <span id="editarPruebaNombreTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editarPruebaForm">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="name" id="editarPruebaName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipo</label>
                            <select name="tipo" id="editarPruebaTipo" class="form-select" required>
                                <option value="bloque">Bloque</option>
                                <option value="dificultad">Dificultad</option>
                                <option value="velocidad">Velocidad</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha inicio</label>
                            <input type="datetime-local" name="fecha_realizacion" id="editarPruebaFecha" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha fin <span class="fw-normal text-muted">(opcional)</span></label>
                            <input type="datetime-local" name="fecha_fin" id="editarPruebaFechaFin" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Provincia</label>
                            <select name="provincia" id="editarPruebaProvincia" class="form-select" required>
                                <option value="">Selecciona...</option>
                                @foreach(['Almería','Cádiz','Córdoba','Granada','Huelva','Jaén','Málaga','Sevilla'] as $prov)
                                    <option value="{{ $prov }}">{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rocódromo</label>
                            <select name="ubicacion_id" id="editarPruebaUbicacion" class="form-select" required>
                                <option value="">Selecciona...</option>
                                @foreach($ubicaciones as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->provincia }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Copa asociada</label>
                            <select name="copa_id" id="editarPruebaCopa" class="form-select">
                                <option value="">— Sin copa —</option>
                                @foreach($copas as $copa)
                                    <option value="{{ $copa->id }}">{{ $copa->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Árbitro</label>
                            <select name="arbitro_id" id="editarPruebaArbitro" class="form-select">
                                <option value="">— Sin árbitro —</option>
                                @foreach($arbitros as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Checkboxes de categorías: se marcan/desmarcan vía JS --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Categorías <span class="fw-normal text-muted small">(masculino y femenino)</span></label>
                            <div class="d-flex flex-wrap gap-2" id="editarPruebaCategorias">
                                @foreach(\App\Models\Competicion::categoriasDisponibles() as $cat)
                                    <div class="form-check form-check-inline border rounded px-3 py-2 m-0">
                                        <input class="form-check-input" type="checkbox"
                                               name="categorias[]" value="{{ $cat }}"
                                               id="ecat_{{ $cat }}">
                                        <label class="form-check-label" for="ecat_{{ $cat }}">{{ $cat }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Script: al abrir el modal de edición, lee data-attributes del botón
     y rellena todos los campos del formulario, incluyendo las categorías --}}
<script>
document.getElementById('modalEditarPrueba').addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const categorias = JSON.parse(btn.dataset.categorias || '[]');

    // Actualizar action del formulario con el ID de la competición
    document.getElementById('editarPruebaForm').action = '/admin/competiciones/' + btn.dataset.id;
    document.getElementById('editarPruebaNombreTitle').textContent = btn.dataset.name;
    document.getElementById('editarPruebaName').value = btn.dataset.name;
    document.getElementById('editarPruebaTipo').value = btn.dataset.tipo;
    document.getElementById('editarPruebaFecha').value = btn.dataset.fecha;
    document.getElementById('editarPruebaFechaFin').value = btn.dataset.fechaFin || '';
    document.getElementById('editarPruebaProvincia').value = btn.dataset.provincia;
    document.getElementById('editarPruebaUbicacion').value = btn.dataset.ubicacionId;
    document.getElementById('editarPruebaCopa').value = btn.dataset.copaId;
    document.getElementById('editarPruebaArbitro').value = btn.dataset.arbitroId;

    // Marcar/desmarcar checkboxes de categorías según los datos de la competición
    document.querySelectorAll('#editarPruebaCategorias input[type=checkbox]').forEach(function(cb) {
        cb.checked = categorias.includes(cb.value);
    });
});
</script>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL CREAR PRUEBA
     ══════════════════════════════════════════════════════════════════════
     Formulario para crear nueva competición con auto-detección de copa.
     Usa Alpine.js: al cambiar tipo/fecha, busca copa con mismo tipo+temporada.
     Igual que el modal en dashboard/admin.blade.php pero con campo fecha_fin.
     POST a admin.competiciones.store (CompeticionController@store)
──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCrearPrueba" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"
         x-data="{
            tipo: 'bloque', fecha: '', copaId: '', copaManual: false,
            copas: {{ Js::from($copas) }},
            get añoFecha() { return this.fecha ? new Date(this.fecha).getFullYear() : null; },
            get copaDetectada() {
                if (!this.añoFecha) return null;
                return this.copas.find(c => c.tipo === this.tipo && c.temporada == this.añoFecha) || null;
            },
            get copaDetectadaLabel() {
                if (this.copaDetectada) return 'Asociada automáticamente a: ' + this.copaDetectada.name;
                return this.añoFecha ? 'No hay copa de ' + this.tipo + ' para ' + this.añoFecha + '. Se creará sin copa.' : '';
            },
            sincronizarCopa() { if (!this.copaManual) this.copaId = this.copaDetectada ? String(this.copaDetectada.id) : ''; }
         }">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear nueva Prueba</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.competiciones.store') }}">
                @csrf
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    <div class="row g-3">
                        {{-- Nombre --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                   placeholder="Ej: 1ª Prueba de Bloque de Andalucía, Sevilla" required>
                        </div>
                        {{-- Tipo (sincroniza copa al cambiar) --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipo</label>
                            <select name="tipo" class="form-select" x-model="tipo" @change="copaManual=false; sincronizarCopa()" required>
                                <option value="bloque">Bloque</option>
                                <option value="dificultad">Dificultad</option>
                                <option value="velocidad">Velocidad</option>
                            </select>
                        </div>
                        {{-- Fecha inicio (sincroniza copa al cambiar) --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha inicio</label>
                            <input type="datetime-local" name="fecha_realizacion" class="form-control"
                                   x-model="fecha" @change="copaManual=false; sincronizarCopa()"
                                   value="{{ old('fecha_realizacion') }}" required>
                        </div>
                        {{-- Fecha fin (opcional, para competiciones de varios días) --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha fin <span class="fw-normal text-muted">(opcional)</span></label>
                            <input type="datetime-local" name="fecha_fin" class="form-control"
                                   value="{{ old('fecha_fin') }}">
                        </div>
                        {{-- Provincia --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Provincia</label>
                            <select name="provincia" class="form-select" required>
                                <option value="">Selecciona...</option>
                                @foreach(['Almería','Cádiz','Córdoba','Granada','Huelva','Jaén','Málaga','Sevilla'] as $prov)
                                    <option value="{{ $prov }}" @selected(old('provincia')===$prov)>{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Rocódromo --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rocódromo</label>
                            <select name="ubicacion_id" class="form-select" required>
                                <option value="">Selecciona...</option>
                                @foreach($ubicaciones as $u)
                                    <option value="{{ $u->id }}" @selected(old('ubicacion_id')==$u->id)>{{ $u->name }} — {{ $u->provincia }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Copa (auto-detectada o manual) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Copa asociada</label>
                            <select name="copa_id" class="form-select" x-model="copaId" @change="copaManual=true">
                                <option value="">— Sin copa —</option>
                                @foreach($copas as $copa)
                                    <option value="{{ $copa->id }}" @selected(old('copa_id')==$copa->id)>{{ $copa->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" :class="copaDetectada ? 'text-success' : 'text-muted'" x-text="copaDetectadaLabel"></div>
                        </div>
                        {{-- Árbitro (opcional) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Árbitro <span class="fw-normal text-muted">(opcional)</span></label>
                            <select name="arbitro_id" class="form-select">
                                <option value="">— Sin árbitro —</option>
                                @foreach($arbitros as $a)
                                    <option value="{{ $a->id }}" @selected(old('arbitro_id')==$a->id)>{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Categorías (checkboxes) --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Categorías <span class="fw-normal text-muted small">(masculino y femenino)</span></label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach(\App\Models\Competicion::categoriasDisponibles() as $cat)
                                    <div class="form-check form-check-inline border rounded px-3 py-2 m-0">
                                        <input class="form-check-input" type="checkbox" name="categorias[]"
                                               value="{{ $cat }}" id="pcat_{{ $cat }}"
                                               @checked(in_array($cat, old('categorias', [])))>
                                        <label class="form-check-label" for="pcat_{{ $cat }}">{{ $cat }}</label>
                                    </div>
                                @endforeach
                            </div>
                            {{-- Botones para seleccionar/deseleccionar todas --}}
                            <div class="mt-2 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="document.querySelectorAll('#modalCrearPrueba [name=\'categorias[]\']').forEach(c=>c.checked=true)">Todas</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="document.querySelectorAll('#modalCrearPrueba [name=\'categorias[]\']').forEach(c=>c.checked=false)">Ninguna</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Prueba</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
