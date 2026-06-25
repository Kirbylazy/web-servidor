{{--
    Perfil del usuario — Edición de datos personales.

    Vista unificada que combina las tres secciones del perfil en una sola página
    con tarjetas Bootstrap (reemplaza los partials de Breeze que usan Tailwind).

    Recibe datos de ProfileController@edit:
      - $user → el usuario autenticado (auth()->user())

    Secciones:
      1. Datos personales → formulario PATCH a profile.update
         Campos: nombre, email, DNI, fecha nacimiento, provincia, talla, género, rol (solo lectura)
      2. Cambiar contraseña → formulario PUT a password.update
         Campos: contraseña actual, nueva contraseña, confirmación
      3. Zona de peligro → botón que abre modal de eliminación de cuenta
         Modal: pide contraseña de confirmación, DELETE a profile.destroy

    Error bags separados:
      - $errors → errores del formulario de datos personales
      - $errors->updatePassword → errores del formulario de contraseña
      - $errors->userDeletion → errores del modal de eliminación

    Session status:
      - 'profile-updated' → éxito al actualizar datos
      - 'password-updated' → éxito al cambiar contraseña

    Extiende: layouts/app.blade.php

    NOTA: Los partials originales de Breeze en profile/partials/ (update-profile-information-form,
    update-password-form, delete-user-form) se mantienen como referencia pero NO se usan.
    Esta vista los reemplaza con Bootstrap 5.

    Relacionado con:
      - ProfileController → edit(), update(), destroy()
      - routes/web.php → rutas profile.edit, profile.update, profile.destroy
      - admin/usuarios.blade.php → el admin puede editar cualquier usuario
--}}
@extends('layouts.app')
@section('title', 'Mi perfil')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">

        <h3 class="mb-4">Mi perfil</h3>

        {{-- ══════════════════════════════════════════════════════════════
             1. DATOS PERSONALES
             ══════════════════════════════════════════════════════════════
             Formulario PATCH a profile.update (ProfileController@update).
             Incluye campos adicionales sobre los de Breeze estándar:
             DNI, fecha nacimiento, provincia, talla, género.
             El rol se muestra como campo deshabilitado (solo el admin puede cambiarlo).
        ──────────────────────────────────────────────────────────────── --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">Datos personales</div>
            <div class="card-body">

                {{-- Mensaje de éxito --}}
                @if(session('status') === 'profile-updated')
                    <div class="alert alert-success">Perfil actualizado correctamente.</div>
                @endif
                {{-- Errores de validación --}}
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf @method('PATCH')
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre completo</label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email', $user->email) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">DNI</label>
                            <input type="text" name="dni" class="form-control"
                                   value="{{ old('dni', $user->dni) }}" placeholder="12345678A">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-control"
                                   value="{{ old('fecha_nacimiento', $user->fecha_nacimiento?->format('Y-m-d')) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Provincia</label>
                            <select name="provincia" class="form-select">
                                <option value="">—</option>
                                @foreach(['Almería','Cádiz','Córdoba','Granada','Huelva','Jaén','Málaga','Sevilla','Madrid','Barcelona','Valencia','Otros'] as $prov)
                                    <option value="{{ $prov }}" @selected(old('provincia', $user->provincia) === $prov)>{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Talla</label>
                            <select name="talla" class="form-select">
                                <option value="">—</option>
                                @foreach(['XS','S','M','L','XL','XXL'] as $t)
                                    <option value="{{ $t }}" @selected(old('talla', $user->talla) === $t)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Género</label>
                            <select name="genero" class="form-select">
                                <option value="">—</option>
                                <option value="M"    @selected(old('genero', $user->genero) === 'M')>Masculino</option>
                                <option value="F"    @selected(old('genero', $user->genero) === 'F')>Femenino</option>
                                <option value="otro" @selected(old('genero', $user->genero) === 'otro')>Otro</option>
                            </select>
                        </div>

                        {{-- Rol: solo lectura, solo el admin puede cambiarlo --}}
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="w-100">
                                <label class="form-label fw-semibold">Rol</label>
                                <input type="text" class="form-control" value="{{ $user->rol }}" disabled>
                            </div>
                        </div>

                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             2. CAMBIAR CONTRASEÑA
             ══════════════════════════════════════════════════════════════
             Formulario PUT a password.update (PasswordController de Breeze).
             Usa error bag 'updatePassword' separado del formulario principal.
        ──────────────────────────────────────────────────────────────── --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">Cambiar contraseña</div>
            <div class="card-body">

                @if(session('status') === 'password-updated')
                    <div class="alert alert-success">Contraseña actualizada.</div>
                @endif
                @if($errors->updatePassword->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->updatePassword->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contraseña actual</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nueva contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Confirmar contraseña</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-outline-primary">Actualizar contraseña</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             3. ZONA DE PELIGRO — ELIMINAR CUENTA
             ══════════════════════════════════════════════════════════════
             Tarjeta con borde rojo + botón que abre modal de confirmación.
             Requiere contraseña para confirmar la eliminación.
             DELETE a profile.destroy (ProfileController@destroy).
        ──────────────────────────────────────────────────────────────── --}}
        <div class="card border-danger">
            <div class="card-header fw-semibold text-danger">Zona de peligro</div>
            <div class="card-body">
                <p class="text-muted small">Una vez eliminada, tu cuenta no se podrá recuperar.</p>
                <button type="button" class="btn btn-outline-danger btn-sm"
                        data-bs-toggle="modal" data-bs-target="#modalEliminarCuenta">
                    Eliminar mi cuenta
                </button>
            </div>
        </div>

    </div>
</div>

{{-- Modal de confirmación de eliminación de cuenta --}}
<div class="modal fade" id="modalEliminarCuenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Eliminar cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('profile.destroy') }}">
                @csrf @method('DELETE')
                <div class="modal-body">
                    <p>Introduce tu contraseña para confirmar la eliminación de tu cuenta.</p>
                    {{-- Errores del error bag 'userDeletion' --}}
                    @if($errors->userDeletion->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach($errors->userDeletion->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar cuenta</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
