<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla pivot 'competicions_users' — Inscripción LEGACY de usuarios en competiciones.
 *
 * Tabla pivot many-to-many entre users y competicions.
 * Es el sistema de inscripción ORIGINAL/RÁPIDO, usado por los entrenadores
 * para inscribir a su equipo directamente (EntrenadorController::inscribir).
 *
 * Para el flujo COMPLETO de inscripción con verificación de documentos,
 * se usa la tabla 'inscripciones' (migración posterior).
 *
 * Columnas pivot:
 *   - tipoDato: tipo de dato adicional (ej: 'resultado', 'posicion') — actualmente no usado
 *   - dato: valor del dato — actualmente no usado
 *   Estos campos se pensaron para almacenar resultados de competición en el futuro.
 *
 * Restricción: un usuario solo puede estar inscrito una vez por competición (unique).
 * Cascade: si se borra el usuario o la competición, se borran las inscripciones.
 *
 * Modelos: User::competiciones(), Competicion::usuarios()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competicions_users', function (Blueprint $table) {
            $table->id();

            // FK al usuario inscrito — cascadeOnDelete
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // FK a la competición — cascadeOnDelete
            $table->foreignId('competicion_id')
                  ->constrained('competicions')
                  ->cascadeOnDelete();

            $table->string('tipoDato')->nullable();  // Tipo de dato extra (para uso futuro)
            $table->string('dato')->nullable();       // Valor del dato extra (para uso futuro)
            $table->timestamps();

            // Un usuario no puede inscribirse dos veces en la misma competición
            $table->unique(['user_id', 'competicion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competicions_users');
    }
};
