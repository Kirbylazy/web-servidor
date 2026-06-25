<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla 'inscripciones' — Inscripciones formales con verificación de documentos.
 *
 * Esta es la tabla principal del sistema de inscripción COMPLETO (a diferencia de
 * competicions_users que es la inscripción legacy/rápida).
 *
 * Ciclo de vida de una inscripción:
 *   1. BORRADOR: se crea al subir el primer documento
 *   2. PENDIENTE: el competidor envía la inscripción a revisión
 *   3. APROBADA: el árbitro valida ambos documentos como válidos
 *   4. RECHAZADA: algún documento no es válido
 *
 * Cada documento (licencia y pago) tiene su propio estado de validación independiente.
 * El estado global se recalcula automáticamente (Inscripcion::recalcularEstado).
 *
 * Modelo: App\Models\Inscripcion (con $table = 'inscripciones' explícito)
 * Controladores: InscripcionController (competidor), ArbitroController (árbitro)
 *
 * Restricción: un usuario solo puede tener UNA inscripción por competición (unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();

            // FK al competidor — cascadeOnDelete (si se borra el usuario, se borran sus inscripciones)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // FK a la competición — cascadeOnDelete (si se borra la competición, se borran las inscripciones)
            $table->foreignId('competicion_id')->constrained('competicions')->cascadeOnDelete();

            // ── Documentos subidos por el competidor ──
            $table->string('licencia_path')->nullable();   // Ruta al archivo de licencia federativa en storage
            $table->string('pago_path')->nullable();        // Ruta al archivo de justificante de pago en storage

            // ── Estado global de la inscripción ──
            $table->enum('estado', ['borrador', 'pendiente', 'aprobada', 'rechazada'])->default('borrador');

            // ── Estados de validación individuales por documento (asignados por el árbitro) ──
            // null = pendiente de revisión
            $table->enum('licencia_estado', ['valida', 'valida_dia', 'no_valida'])->nullable();
            $table->enum('pago_estado',     ['valida', 'valida_dia', 'no_valida'])->nullable();

            // ── Motivos de rechazo por documento (texto del árbitro) ──
            $table->text('licencia_motivo')->nullable();   // Motivo si licencia_estado = 'no_valida'
            $table->text('pago_motivo')->nullable();        // Motivo si pago_estado = 'no_valida'
            $table->text('motivo_rechazo')->nullable();     // Motivo general (legacy, se usan los de arriba)

            // ── Categoría del competidor ──
            $table->string('categoria')->nullable();        // Ej: "Masculino U17", "Femenino Absoluta"
                                                            // Calculada automáticamente, modificable por árbitro

            $table->timestamps();

            // Un usuario solo puede inscribirse una vez por competición
            $table->unique(['user_id', 'competicion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
