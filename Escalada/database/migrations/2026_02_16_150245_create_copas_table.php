<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla 'copas' — Torneos/series de competiciones.
 *
 * Una copa agrupa varias competiciones del mismo tipo en una temporada.
 * Ejemplo: "Copa de Bloque de Andalucía 2026" → 3 pruebas de bloque.
 *
 * Modelo: App\Models\Copa
 * Controlador: CopaController (CRUD), AdminController::copas() (listado)
 * Vista: admin/copas.blade.php
 *
 * Relación con competicions: Copa hasMany Competicion (FK copa_id en competicions)
 * Al eliminar una copa, las competiciones NO se borran: su copa_id se pone a null (nullOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copas', function (Blueprint $table) {
            $table->id();                     // PK autoincremental
            $table->string('name');            // Nombre de la copa (ej: "Copa de Bloque 2026")
            $table->string('tipo');            // Tipo de escalada: 'Bloque', 'Dificultad', 'Velocidad'
            $table->integer('temporada');      // Año de la temporada (ej: 2026)
            $table->timestamps();              // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copas');
    }
};
