{{--
    Admin — Gestión de Copas/Torneos.

    Panel de administración para gestionar copas (torneos) de escalada.
    Accesible solo para admins vía middleware 'rol:admin'.

    Recibe datos de AdminController@copas:
      - $copas → colección de copas con $copa->competiciones_count (withCount)
      - $filtro → filtro activo: 'todas' o 'este_año' (por defecto 'todas')

    Funcionalidades:
      1. Tabla de copas con nombre, tipo (badge), temporada y nº de pruebas asociadas
      2. Filtro por año (select que recarga la página)
      3. Botón "Editar" → abre modal con datos precargados vía JS vanilla
      4. Botón "Eliminar" → formulario DELETE con confirmación
      5. Modal "Crear Copa" → formulario con Alpine.js para nombre auto-generado
      6. Modal "Editar Copa" → formulario PATCH con datos rellenados por show.bs.modal

    Interactividad:
      - Alpine.js en el modal de creación: genera nombre automático
        "Copa Andaluza de [Tipo] [Temporada]" al cambiar tipo o temporada
      - JS vanilla en el modal de edición: usa evento show.bs.modal para
        leer data-attributes del botón y rellenar el formulario

    Rutas usadas:
      - admin.copas.store → CopaController@store (POST, crear copa)
      - /admin/copas/{id} → CopaController@update (PATCH, editar copa)
      - admin.copas.destroy → CopaController@destroy (DELETE, eliminar copa)

    Extiende: layouts/app.blade.php
    Incluye: admin/partials/sidebar.blade.php

    Relacionado con:
      - admin/pruebas.blade.php → gestión de competiciones (usan copas como FK)
      - dashboard/admin.blade.php → versión resumida con modales similares
      - CopaController → CRUD de copas
      - Copa (modelo) → name, tipo, temporada, relación hasMany competiciones
--}}
@extends('layouts.app')
@section('title', 'Admin — Copas')

@section('content')
{{-- Contenedor Alpine.js para gestionar el modal de edición --}}
<div x-data="{
    copa: {},
    abrir(c) { this.copa = c; this.$refs.editarCopaForm.action = '/admin/copas/' + c.id; },
    get nombreAuto() { return 'Copa Andaluza de ' + ({bloque:'Bloque',dificultad:'Dificultad',velocidad:'Velocidad'}[this.copa.tipo]||this.copa.tipo) + ' ' + this.copa.temporada; }
}">
<div class="row g-4">

{{-- Sidebar de navegación del panel admin --}}
<div class="col-auto">
    @include('admin.partials.sidebar')
</div>

<div class="col">

{{-- Cabecera: título + botón crear --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Copas</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearCopa">+ Crear Copa</button>
</div>

{{-- Filtro: todas las copas o solo las de este año --}}
<form method="GET" class="mb-3">
    <select name="filtro" class="form-select" style="width:auto" onchange="this.form.submit()">
        <option value="todas"    @selected($filtro==='todas')>Todas las copas</option>
        <option value="este_año" @selected($filtro==='este_año')>Copas de este año ({{ now()->year }})</option>
    </select>
</form>

{{-- Tabla de copas --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Temporada</th>
                    <th class="text-center">Pruebas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($copas as $copa)
                    <tr>
                        <td class="fw-semibold">{{ $copa->name }}</td>
                        <td><span class="badge bg-secondary">{{ $copa->tipo }}</span></td>
                        <td>{{ $copa->temporada }}</td>
                        {{-- Nº de competiciones asociadas (withCount en el controlador) --}}
                        <td class="text-center">{{ $copa->competiciones_count }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                {{-- Botón Editar: pasa datos al modal vía data-attributes
                                     El script JS los lee con el evento show.bs.modal --}}
                                <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarCopa"
                                        data-id="{{ $copa->id }}"
                                        data-name="{{ $copa->name }}"
                                        data-tipo="{{ $copa->tipo }}"
                                        data-temporada="{{ $copa->temporada }}">
                                    Editar
                                </button>
                                {{-- Botón Eliminar: DELETE con confirmación JS --}}
                                <form method="POST" action="{{ route('admin.copas.destroy', $copa->id) }}"
                                      onsubmit="return confirm('¿Eliminar «{{ addslashes($copa->name) }}»?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay copas con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>

</div>
</div>{{-- /row --}}

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL EDITAR COPA
     ══════════════════════════════════════════════════════════════════════
     Formulario para editar una copa existente.
     Los campos se rellenan con JS vanilla al abrir el modal (show.bs.modal).
     La action del formulario se actualiza dinámicamente con el ID de la copa.
     PATCH a /admin/copas/{id} (CopaController@update)
──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarCopa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar copa — <span id="editarCopaNombreTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editarCopaForm">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo</label>
                        <select name="tipo" id="editarCopaTipo" class="form-select" required>
                            <option value="bloque">Bloque</option>
                            <option value="dificultad">Dificultad</option>
                            <option value="velocidad">Velocidad</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Temporada (año)</label>
                        <input type="number" name="temporada" id="editarCopaTemporada" class="form-control"
                               min="2000" max="2100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="name" id="editarCopaName" class="form-control"
                               maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Script: al abrir el modal de edición, lee data-attributes del botón
     que lo abrió y rellena los campos del formulario --}}
<script>
document.getElementById('modalEditarCopa').addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    document.getElementById('editarCopaForm').action = '/admin/copas/' + btn.dataset.id;
    document.getElementById('editarCopaNombreTitle').textContent = btn.dataset.name;
    document.getElementById('editarCopaTipo').value = btn.dataset.tipo;
    document.getElementById('editarCopaTemporada').value = btn.dataset.temporada;
    document.getElementById('editarCopaName').value = btn.dataset.name;
});
</script>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL CREAR COPA
     ══════════════════════════════════════════════════════════════════════
     Formulario para crear nueva copa con nombre auto-generado vía Alpine.js.
     Mismo modal que en dashboard/admin.blade.php.
     POST a admin.copas.store (CopaController@store)
──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCrearCopa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"
         x-data="{
            tipo: 'bloque',
            temporada: '{{ now()->year }}',
            nombre: 'Copa Andaluza de Bloque {{ now()->year }}',
            nombreManual: false,
            tipoLabel(t) { return {bloque:'Bloque',dificultad:'Dificultad',velocidad:'Velocidad'}[t] ?? t; },
            actualizarNombre() { if (!this.nombreManual) this.nombre = 'Copa Andaluza de ' + this.tipoLabel(this.tipo) + ' ' + this.temporada; }
         }">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear nueva Copa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.copas.store') }}">
                @csrf
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    {{-- Tipo de copa --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo</label>
                        <select name="tipo" class="form-select" x-model="tipo" @change="actualizarNombre()" required>
                            <option value="bloque">Bloque</option>
                            <option value="dificultad">Dificultad</option>
                            <option value="velocidad">Velocidad</option>
                        </select>
                    </div>
                    {{-- Temporada --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Temporada (año)</label>
                        <input type="number" name="temporada" class="form-control"
                               x-model="temporada" @input="actualizarNombre()"
                               min="2000" max="2100" required>
                    </div>
                    {{-- Nombre auto-generado --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Nombre <span class="fw-normal text-muted small">(se genera automáticamente)</span>
                        </label>
                        <input type="text" name="name" class="form-control"
                               x-model="nombre" @input="nombreManual=true" maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Copa</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>{{-- /x-data --}}

@endsection
