{{--
    Admin — Gestión de Rocódromos/Ubicaciones.

    Panel de administración para gestionar las ubicaciones donde se celebran competiciones.
    Accesible solo para admins vía middleware 'rol:admin'.

    Recibe datos de AdminController@rocodromos:
      - $ubicaciones → colección de ubicaciones con $u->competiciones_count (withCount)

    Funcionalidades:
      1. Tabla con nombre, provincia, dirección, dimensiones (alto/ancho), nº líneas y nº pruebas
      2. Botón "Editar" → abre modal Alpine.js con datos precargados (Js::from)
      3. Botón "Eliminar" → formulario DELETE con confirmación
      4. Modal "Crear Rocódromo" → formulario con campos de dimensiones

    Interactividad Alpine.js:
      - x-data en el contenedor: almacena el rocódromo seleccionado en 'roco'
      - @click="abrir({...})" carga los datos del rocódromo para el modal de edición
      - El modal de edición usa x-model para binding bidireccional de campos
      - :action dinámico construye la URL del PATCH con roco.id

    Rutas usadas:
      - admin.rocodromos.store → UbicacionController@store (POST)
      - /admin/rocodromos/{id} → UbicacionController@update (PATCH)
      - admin.rocodromos.destroy → UbicacionController@destroy (DELETE)

    Extiende: layouts/app.blade.php
    Incluye: admin/partials/sidebar.blade.php

    Relacionado con:
      - admin/pruebas.blade.php → las pruebas se asocian a un rocódromo
      - Ubicacion (modelo) → name, provincia, direccion, alto, ancho, n_lineas
      - UbicacionController → CRUD de ubicaciones
--}}
@extends('layouts.app')
@section('title', 'Admin — Rocódromos')

@section('content')
{{-- Contenedor Alpine.js para el modal de edición --}}
<div class="row g-4" x-data="{ roco: {}, abrir(r) { this.roco = r; } }">

{{-- Sidebar admin --}}
<div class="col-auto">
    @include('admin.partials.sidebar')
</div>

<div class="col">

{{-- Cabecera: título + botón crear --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Rocódromos</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearRoco">+ Crear Rocódromo</button>
</div>

{{-- Tabla de rocódromos --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Provincia</th>
                    <th>Dirección</th>
                    <th class="text-center">Alto (m)</th>
                    <th class="text-center">Ancho (m)</th>
                    <th class="text-center">Líneas</th>
                    <th class="text-center">Pruebas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ubicaciones as $u)
                    <tr>
                        <td class="fw-semibold">{{ $u->name }}</td>
                        <td>{{ $u->provincia }}</td>
                        <td class="text-muted small">{{ $u->direccion ?? '—' }}</td>
                        <td class="text-center">{{ $u->alto ?? '—' }}</td>
                        <td class="text-center">{{ $u->ancho ?? '—' }}</td>
                        <td class="text-center">{{ $u->n_lineas ?? '—' }}</td>
                        {{-- Nº de competiciones asociadas a este rocódromo --}}
                        <td class="text-center">{{ $u->competiciones_count }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                {{-- Botón Editar: carga datos del rocódromo vía Js::from
                                     y abre el modal de edición --}}
                                <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarRoco"
                                        @click="abrir({{ Js::from([
                                            'id'       => $u->id,
                                            'name'     => $u->name,
                                            'provincia'=> $u->provincia,
                                            'direccion'=> $u->direccion ?? '',
                                            'alto'     => $u->alto ?? '',
                                            'ancho'    => $u->ancho ?? '',
                                            'n_lineas' => $u->n_lineas ?? '',
                                        ]) }})">
                                    Editar
                                </button>
                                {{-- Botón Eliminar con confirmación --}}
                                <form method="POST" action="{{ route('admin.rocodromos.destroy', $u->id) }}"
                                      onsubmit="return confirm('¿Eliminar «{{ addslashes($u->name) }}»?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay rocódromos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>

</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL CREAR ROCÓDROMO
     ══════════════════════════════════════════════════════════════════════
     Formulario para crear un nuevo rocódromo/ubicación.
     Campos: nombre, provincia (8 andaluzas), dirección, alto, ancho, nº líneas.
     POST a admin.rocodromos.store (UbicacionController@store)
──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCrearRoco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear nuevo Rocódromo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.rocodromos.store') }}">
                @csrf
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name') }}" placeholder="Ej: Rocódromo Municipal de Sevilla" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Provincia</label>
                            <select name="provincia" class="form-select" required>
                                <option value="">Selecciona...</option>
                                @foreach(['Almería','Cádiz','Córdoba','Granada','Huelva','Jaén','Málaga','Sevilla'] as $prov)
                                    <option value="{{ $prov }}" @selected(old('provincia')===$prov)>{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección</label>
                            <input type="text" name="direccion" class="form-control"
                                   value="{{ old('direccion') }}" placeholder="Calle, número...">
                        </div>
                        {{-- Dimensiones del muro de escalada --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Alto (m)</label>
                            <input type="number" name="alto" class="form-control"
                                   value="{{ old('alto') }}" step="0.1" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ancho (m)</label>
                            <input type="number" name="ancho" class="form-control"
                                   value="{{ old('ancho') }}" step="0.1" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nº líneas</label>
                            <input type="number" name="n_lineas" class="form-control"
                                   value="{{ old('n_lineas') }}" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Rocódromo</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL EDITAR ROCÓDROMO
     ══════════════════════════════════════════════════════════════════════
     Formulario para editar un rocódromo existente.
     Usa Alpine.js con x-model para binding bidireccional.
     :action dinámico con roco.id para construir la URL del PATCH.
     PATCH a /admin/rocodromos/{id} (UbicacionController@update)
──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarRoco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                {{-- Nombre dinámico en el título del modal --}}
                <h5 class="modal-title">Editar rocódromo — <span x-text="roco.name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" :action="'/admin/rocodromos/' + roco.id">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="name" class="form-control" x-model="roco.name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Provincia</label>
                            <select name="provincia" class="form-select" required>
                                @foreach(['Almería','Cádiz','Córdoba','Granada','Huelva','Jaén','Málaga','Sevilla'] as $prov)
                                    <option value="{{ $prov }}" :selected="roco.provincia === '{{ $prov }}'">{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección</label>
                            <input type="text" name="direccion" class="form-control" x-model="roco.direccion">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Alto (m)</label>
                            <input type="number" name="alto" class="form-control" x-model="roco.alto" step="0.1" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ancho (m)</label>
                            <input type="number" name="ancho" class="form-control" x-model="roco.ancho" step="0.1" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nº líneas</label>
                            <input type="number" name="n_lineas" class="form-control" x-model="roco.n_lineas" min="0">
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

@endsection
