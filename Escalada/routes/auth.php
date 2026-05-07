<?php

/**
 * auth.php — Rutas de autenticación generadas por Laravel Breeze.
 *
 * Este archivo define todas las rutas de registro, login, recuperación de contraseña,
 * verificación de email y logout. Se incluye desde routes/web.php.
 *
 * Dos grupos de middleware:
 *   - 'guest': solo accesible si NO estás autenticado (login, registro, reset password)
 *   - 'auth':  solo accesible si estás autenticado (verificación email, logout, cambio password)
 *
 * Los controladores están en app/Http/Controllers/Auth/ y son generados por Breeze.
 * Las vistas están en resources/views/auth/ (usan Tailwind CSS del scaffolding de Breeze).
 */

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas para invitados (usuarios NO autenticados)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    // ── Registro ──
    Route::get('register', [RegisteredUserController::class, 'create'])  // Formulario de registro
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']); // Procesar registro

    // ── Login ──
    Route::get('login', [AuthenticatedSessionController::class, 'create'])  // Formulario de login
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']); // Procesar login (ver LoginRequest)

    // ── Recuperación de contraseña ──
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])  // Formulario "Olvidé mi contraseña"
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])  // Enviar email con link de reset
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create']) // Formulario de nueva contraseña (desde email)
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])         // Guardar nueva contraseña
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Rutas para usuarios autenticados
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // ── Verificación de email ──
    Route::get('verify-email', EmailVerificationPromptController::class)            // Página "Verifica tu email"
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)            // Link de verificación del email
        ->middleware(['signed', 'throttle:6,1'])                                     // signed: URL firmada; throttle: máx 6 intentos/min
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store']) // Reenviar email de verificación
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // ── Confirmación de contraseña (para acciones sensibles) ──
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])  // Formulario "Confirma tu contraseña"
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']); // Verificar contraseña

    // ── Cambio de contraseña ──
    Route::put('password', [PasswordController::class, 'update'])->name('password.update'); // Desde perfil

    // ── Logout ──
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
