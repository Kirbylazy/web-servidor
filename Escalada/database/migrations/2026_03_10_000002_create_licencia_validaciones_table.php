<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla 'licencia_validaciones' — Registro de validaciones de licencia federativa.
 *
 * Cada vez que un árbitro valida la licencia de un competidor, se crea un registro aquí.
 * Esto permite rastrear quién validó, cuándo, y hasta cuándo es válida.
 *
 * Tipos de validación:
 *   - 'valida':     Licencia anual — válida hasta el 31 de diciembre del año actual.
 *                   El competidor no necesita re-subir la licencia en futuras competiciones.
 *   - 'valida_dia': Licencia diaria — válida solo para la fecha de la competición.
 *                   Debe volver a subir y validar en la siguiente competición.
 *
 * Modelo: App\Models\LicenciaValidacion
 * Controlador: ArbitroController::validarLicencia() (crea registros)
 * Consulta: LicenciaValidacion::tieneValidezAnual() y validezAnual()
 *           usados por InscripcionController::show() para decidir si mostrar upload de licencia
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencia_validaciones', function (Blueprint $table) {
            $table->id();

            // FK al competidor cuya licencia se validó
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // FK al árbitro que realizó la validación (no sigue convención: validada_por en vez de user_id)
            $table->foreignId('validada_por')->references('id')->on('users')->cascadeOnDelete();

            // FK a la competición donde se validó (nullable para validaciones genéricas)
            $table->foreignId('competicion_id')->nullable()->constrained('competicions')->nullOnDelete();

            // Tipo de validación: 'valida' (anual) o 'valida_dia' (solo esa competición)
            $table->enum('tipo', ['valida', 'valida_dia']);

            // Fecha hasta la que es válida la licencia
            // 'valida': 31 dic del año actual
            // 'valida_dia': fecha de la competición
            $table->date('valida_hasta');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_validaciones');
    }
};
