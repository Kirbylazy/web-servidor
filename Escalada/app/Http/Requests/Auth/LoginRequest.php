<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * LoginRequest — Validación y autenticación del formulario de login.
 *
 * Generado por Laravel Breeze. Maneja:
 *   1. Validación de campos (email y password obligatorios)
 *   2. Intento de autenticación contra la BD
 *   3. Rate limiting (máximo 5 intentos fallidos, luego bloqueo temporal)
 *
 * Usado por: AuthenticatedSessionController::store() (routes/auth.php POST /login)
 * Vista: auth/login.blade.php
 */
class LoginRequest extends FormRequest
{
    /**
     * Autorización — Cualquier visitante puede intentar hacer login.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación del formulario de login.
     * Solo requiere email válido y contraseña.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Intentar autenticar al usuario con las credenciales proporcionadas.
     *
     * Primero verifica que no se haya superado el límite de intentos (rate limiting).
     * Si las credenciales son incorrectas, incrementa el contador de intentos.
     * Si son correctas, limpia el contador.
     *
     * El checkbox "remember" habilita la sesión persistente (remember_token en BD).
     *
     * @throws ValidationException Si las credenciales son incorrectas
     */
    public function authenticate(): void
    {
        // Verificar rate limiting antes de intentar autenticar
        $this->ensureIsNotRateLimited();

        // Intentar login con email + password; $this->boolean('remember') para "Recuérdame"
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            // Login fallido: incrementar contador de intentos
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'), // "These credentials do not match our records."
            ]);
        }

        // Login exitoso: limpiar contador de intentos fallidos
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Verificar que no se ha superado el límite de intentos de login.
     *
     * Máximo 5 intentos. Después se bloquea temporalmente y se muestra
     * cuántos segundos faltan para poder reintentar.
     *
     * @throws ValidationException Si se superó el límite de intentos
     */
    public function ensureIsNotRateLimited(): void
    {
        // Máximo 5 intentos por clave (email + IP)
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        // Disparar evento Lockout (para logging/auditoría)
        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Generar clave única para el rate limiter.
     * Combina email (en minúsculas, sin acentos) + IP del cliente.
     * Así cada combinación email+IP tiene su propio contador de intentos.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
