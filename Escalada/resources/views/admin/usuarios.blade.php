{{--
    Admin — Gestión de Usuarios.

    Panel de administración para gestionar todos los usuarios del sistema.
    Accesible solo para admins vía middleware 'rol:admin'.

    Recibe datos de AdminController@usuarios:
      - $usuarios → colección de usuarios filtrados por rol y/o búsqueda
      - $rolFiltro → filtro de rol activo: 'todos', 'competidor', 'entrenador', 'arbitro', 'admin'
      - $buscar → texto de búsqueda activo (nombre o DNI)

    Funcionalidades:
      1. Filtro por rol (select que recarga la página)
      2. Búsqueda en tiempo real por nombre o DNI (debounce 400ms vía JS)
      3. Tabla con datos del usuario: nombre, DNI, email, rol (badge con color), provincia
      4. Botón "Editar" → abre modal Alpine.js con todos los campos del usuario
      5. Botón "Eliminar" → formulario DELETE con confirmación

    Interactividad Alpine.js:
      - x-data en el contenedor principal: almacena el usuario seleccionado
      - @click="abrir(...)" en cada botón Editar: carga datos vía Js::from()
      - El modal de edición usa :value y :selected con x-data para rellenar campos

    Rutas usadas:
      - /admin/usuarios/{id} → AdminController (PATCH, actualizar usuario)
      - admin.usuarios.destroy → AdminController (DELETE, eliminar usuario)

    Extiende: layouts/app.blade.php
    Incluye: admin/partials/sidebar.blade.php

    Relacionado con:
      - dashboard/admin.blade.php → versión simplificada (solo cambio de rol)
      - ProfileController → los usuarios editan su propio perfil
      - User (modelo) → campos: name, email, dni, fecha_nacimiento, provincia, talla, genero, rol
--}}
@extends('layouts.app')
@section('title', 'Admin — Usuarios')

@section('content')
{{-- Contenedor Alpine.js: almacena el usuario seleccionado para el modal de edición --}}
<div class="row g-4" x-data="{
        usuario: {},
        abrir(u) { this.usuario = u; }
     }">

{{-- Sidebar admin --}}
<div class="col-auto">
    @include('admin.partials.sidebar')
</div>

<div class="col">

<h4 class="mb-3">Usuarios</h4>

{{-- Filtros: selector de rol + campo de búsqueda por nombre/DNI --}}
<form method="GET" class="d-flex flex-wrap gap-2 mb-3" id="formFiltroUsuarios">
    {{-- Filtro por rol: recarga la página al cambiar --}}
    <select name="rol" class="form-select" style="width:auto" onchange="this.form.submit()">
        <option value="todos"       @selected($rolFiltro==='todos')>Todos los roles</option>
        <option value="competidor"  @selected($rolFiltro==='competidor')>Competidor</option>
        <option value="entrenador"  @selected($rolFiltro==='entrenador')>Entrenador</option>
        <option value="arbitro"     @selected($rolFiltro==='arbitro')>Árbitro</option>
        <option value="admin"       @selected($rolFiltro==='admin')>Admin</option>
    </select>
    {{-- Campo de búsqueda: envía el formulario con debounce de 400ms --}}
    <input type="text" name="buscar" class="form-control" style="width:220px"
           placeholder="Buscar por nombre o DNI..." value="{{ $buscar }}"
           id="campoBuscar">
    {{-- Contador de resultados --}}
    <span class="text-muted small align-self-center">{{ $usuarios->count() }} usuarios</span>
</form>

{{-- Tabla de usuarios con scroll vertical limitado --}}
<div class="card">
    <div class="card-body p-0" style="max-height:75vh; overflow-y:auto">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Provincia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                    <tr>
                        <td class="fw-semibold">{{ $u->name }}</td>
                        <td class="text-muted small">{{ $u->dni ?? '—' }}</td>
                        <td class="text-muted small">{{ $u->email }}</td>
                        <td>
                            {{-- Badge con color según rol --}}
                            @php $bc = match($u->rol) { 'arbitro'=>'bg-warning text-dark','entrenador'=>'bg-success','admin'=>'bg-danger',default=>'bg-secondary' }; @endphp
                            <span class="badge {{ $bc }}">{{ $u->rol }}</span>
                        </td>
                        <td class="small">{{ $u->provincia ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                            {{-- Botón Editar: carga datos del usuario en Alpine.js
                                 Js::from() convierte el modelo a JSON seguro --}}
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditarUsuario"
                                    @click="abrir({{ Js::from([
                                        'id'               => $u->id,
                                        'name'             => $u->name,
                                        'email'            => $u->email,
                                        'dni'              => $u->dni ?? '',
                                        'fecha_nacimiento' => $u->fecha_nacimiento?->format('Y-m-d') ?? '',
                                        'provincia'        => $u->provincia ?? '',
                                        'talla'            => $u->talla ?? '',
                                        'genero'           => $u->genero ?? '',
                                        'rol'              => $u->rol,
                                    ]) }})">
                                Editar
                            </button>
                            {{-- Botón Eliminar: formulario DELETE con confirmación --}}
                            <form method="POST" action="{{ route('admin.usuarios.destroy', $u->id) }}"
                                  onsubmit="return confirm('¿Eliminar a {{ addslashes($u->name) }}? Esta acción no se puede deshacer.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay usuarios con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL EDITAR USUARIO
     ══════════════════════════════════════════════════════════════════════
     Formulario para editar todos los datos de un usuario.
     Los campos se rellenan con Alpine.js (:value="usuario.campo").
     La action del form se construye dinámicamente con el ID del usuario.
     PATCH a /admin/usuarios/{id} (AdminController)

     Campos editables: nombre, email, DNI, fecha nacimiento, provincia,
     talla, género y rol. Provincias incluyen las 8 andaluzas + otras.
──────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar usuario — <span x-text="usuario.name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" :action="'/admin/usuarios/' + usuario.id">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre completo</label>
                            <input type="text" name="name" class="form-control" :value="usuario.name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" :value="usuario.email" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">DNI</label>
                            <input type="text" name="dni" class="form-control" :value="usuario.dni">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-control" :value="usuario.fecha_nacimiento">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Provincia</label>
                            <select name="provincia" class="form-select">
                                <option value="">—</option>
                                @foreach(['Almería','Cádiz','Córdoba','Granada','Huelva','Jaén','Málaga','Sevilla','Madrid','Barcelona','Valencia','Otros'] as $prov)
                                    <option :selected="usuario.provincia === '{{ $prov }}'">{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Talla</label>
                            <select name="talla" class="form-select">
                                <option value="">—</option>
                                @foreach(['XS','S','M','L','XL','XXL'] as $t)
                                    <option :selected="usuario.talla === '{{ $t }}'">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Género</label>
                            <select name="genero" class="form-select">
                                <option value="">—</option>
                                <option value="M" :selected="usuario.genero === 'M'">Masculino</option>
                                <option value="F" :selected="usuario.genero === 'F'">Femenino</option>
                                <option value="otro" :selected="usuario.genero === 'otro'">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rol</label>
                            <select name="rol" class="form-select" required>
                                <option value="competidor" :selected="usuario.rol === 'competidor'">Competidor</option>
                                <option value="entrenador" :selected="usuario.rol === 'entrenador'">Entrenador</option>
                                <option value="arbitro"    :selected="usuario.rol === 'arbitro'">Árbitro</option>
                                <option value="admin"      :selected="usuario.rol === 'admin'">Admin</option>
                            </select>
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

</div>

</div>

{{-- Script: búsqueda con debounce — envía el formulario 400ms después de dejar de escribir --}}
<script>
document.getElementById('campoBuscar').addEventListener('input', function() {
    clearTimeout(this._t);
    this._t = setTimeout(() => document.getElementById('formFiltroUsuarios').submit(), 400);
});
</script>
@endsection
