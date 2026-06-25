<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ProfileUpdateRequest — Reglas de validación para la actualización del perfil del usuario.
 *
 * Valida los campos que el usuario puede editar desde su perfil personal
 * (profile/edit.blade.php → ProfileController::update).
 *
 * Campos validados:
 *   - name:             Nombre completo (obligatorio)
 *   - email:            Email único (excluyendo al propio usuario), en minúsculas
 *   - dni:              DNI/NIE único (excluyendo al propio usuario), opcional
 *   - fecha_nacimiento: Fecha válida, opcional
 *   - provincia:        Texto libre, opcional
 *   - talla:            Una de: XS, S, M, L, XL, XXL, opcional
 *   - genero:           M (Masculino), F (Femenino), otro, opcional
 *
 * Nota: el campo 'rol' NO se incluye aquí — el usuario no puede cambiar
 * su propio rol. Solo el admin puede hacerlo desde AdminController::updateRol().
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * Reglas de validación del perfil.
     *
     * Rule::unique()->ignore() permite que el email/DNI del usuario actual
     * no genere error de duplicado al editar su propio perfil.
     */
    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'dni'              => ['nullable', 'string', 'max:20', Rule::unique(User::class)->ignore($this->user()->id)],
            'fecha_nacimiento' => ['nullable', 'date'],
            'provincia'        => ['nullable', 'string', 'max:100'],
            'talla'            => ['nullable', 'in:XS,S,M,L,XL,XXL'],
            'genero'           => ['nullable', 'in:M,F,otro'],
        ];
    }
}
