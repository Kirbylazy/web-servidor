<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla 'users' — Usuarios de la aplicación de escalada.
 *
 * Tabla central del sistema. Almacena todos los usuarios independientemente
 * de su rol (competidor, entrenador, arbitro, admin).
 *
 * También crea las tablas auxiliares de Laravel:
 *   - password_reset_tokens: tokens para recuperación de contraseña
 *   - sessions: sesiones de usuario (driver 'database')
 *
 * Modelo: App\Models\User
 * Factory: Database\Factories\UserFactory
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Tabla 'users' — Datos de todos los usuarios del sistema.
         *
         * Campos específicos del proyecto de escalada:
         *   - dni:              DNI/NIE único, usado por entrenadores para buscar competidores
         *   - fecha_nacimiento: Se usa para calcular la categoría de competición (solo el año importa)
         *   - provincia:        Provincia del usuario (Andalucía)
         *   - talla:            Talla de camiseta (XS/S/M/L/XL/XXL)
         *   - rol:              Rol del usuario, por defecto 'competidor'
         *                       Valores posibles: competidor, entrenador, arbitro, admin
         *   - genero:           M (Masculino), F (Femenino), otro
         *                       Determina la categoría de competición (Masculino/Femenino)
         */
        Schema::create('users', function (Blueprint $table) {
            $table->id();                                        // PK autoincremental
            $table->string('dni')->unique();                     // DNI/NIE único — búsqueda de competidores
            $table->date('fecha_nacimiento');                     // Para calcular categoría por edad
            $table->string('provincia');                          // Provincia del usuario
            $table->string('talla');                              // Talla de camiseta
            $table->string('name');                               // Nombre completo
            $table->string('email')->unique();                    // Email único — login
            $table->timestamp('email_verified_at')->nullable();   // Verificación de email (Breeze)
            $table->string('password');                            // Contraseña hasheada
            $table->string('rol')->default('competidor');          // Rol del usuario (default: competidor)
            $table->string('genero');                              // M/F/otro — para categoría de competición
            $table->rememberToken();                               // Token "Recuérdame" para login persistente
            $table->timestamps();                                  // created_at, updated_at
        });

        /**
         * Tabla 'password_reset_tokens' — Tokens de recuperación de contraseña.
         * Tabla estándar de Laravel para el flujo "Olvidé mi contraseña".
         */
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();     // Email del usuario que pidió el reset
            $table->string('token');                 // Token único enviado por email
            $table->timestamp('created_at')->nullable(); // Cuándo se creó (para expiración)
        });

        /**
         * Tabla 'sessions' — Sesiones de usuario (driver database).
         * Laravel almacena aquí las sesiones activas de los usuarios.
         */
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();                 // ID de sesión
            $table->foreignId('user_id')->nullable()->index(); // Usuario al que pertenece (null si guest)
            $table->string('ip_address', 45)->nullable();    // IP del cliente (IPv4 o IPv6)
            $table->text('user_agent')->nullable();           // Navegador/dispositivo
            $table->longText('payload');                       // Datos de la sesión serialized
            $table->integer('last_activity')->index();         // Timestamp de última actividad
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
