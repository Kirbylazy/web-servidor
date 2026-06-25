<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * ProfileController — Gestión del perfil del usuario autenticado.
 *
 * Permite a cualquier usuario ver/editar su perfil personal y eliminar su cuenta.
 * Generado por Laravel Breeze con personalización para los campos extra del proyecto
 * (DNI, fecha_nacimiento, provincia, talla, genero).
 *
 * La validación de los campos del perfil está en ProfileUpdateRequest
 * (app/Http/Requests/ProfileUpdateRequest.php).
 *
 * Rutas: /profile (GET, PATCH, DELETE) — protegidas por middleware 'auth'
 * Vista: profile/edit.blade.php (incluye partials para información, contraseña y eliminación)
 */
class ProfileController extends Controller
{
    /**
     * Mostrar el formulario de edición de perfil.
     *
     * Pasa el usuario autenticado a la vista, que muestra:
     *   - Formulario de información personal (nombre, email, DNI, etc.)
     *   - Formulario de cambio de contraseña (PasswordController)
     *   - Botón de eliminación de cuenta
     *
     * Ruta: GET /profile → profile.edit
     * Vista: profile/edit.blade.php
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Actualizar la información del perfil del usuario.
     *
     * Usa ProfileUpdateRequest para validar los campos (name, email, dni,
     * fecha_nacimiento, provincia, talla, genero). Si el email cambia,
     * se resetea la verificación de email (email_verified_at = null).
     *
     * Ruta: PATCH /profile → profile.update
     * Request: ProfileUpdateRequest (app/Http/Requests/ProfileUpdateRequest.php)
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Rellenar el modelo con los datos validados del formulario
        $request->user()->fill($request->validated());

        // Si el email cambió, invalidar la verificación anterior
        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Eliminar la cuenta del usuario.
     *
     * Requiere confirmar la contraseña actual antes de proceder.
     * Cierra la sesión, elimina el usuario de la BD y regenera el token CSRF.
     * Al eliminar, se borran en cascada: inscripciones, vínculos entrenador-competidor,
     * notificaciones, etc. (según las FKs con cascadeOnDelete en las migraciones).
     *
     * Ruta: DELETE /profile → profile.destroy
     * Vista: modal de confirmación en profile/partials/delete-user-form.blade.php
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Validar contraseña actual (bag 'userDeletion' para errores específicos del modal)
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Cerrar sesión antes de eliminar
        Auth::logout();

        // Eliminar usuario (cascadea inscripciones, vínculos, notificaciones, etc.)
        $user->delete();

        // Invalidar la sesión y regenerar token CSRF por seguridad
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
