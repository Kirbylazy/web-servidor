<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla 'ubicacions' — Rocódromos/instalaciones de escalada.
 *
 * Almacena los lugares físicos donde se celebran las competiciones.
 * Incluye las dimensiones del muro de escalada para planificación.
 *
 * Modelo: App\Models\Ubicacion
 * Controlador: UbicacionController (CRUD), AdminController::rocodromos() (listado)
 * Vista: admin/rocodromos.blade.php
 *
 * Relación con competicions: Ubicacion hasMany Competicion (FK ubicacion_id)
 * Al eliminar una ubicación, se eliminan EN CASCADA las competiciones (cascadeOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicacions', function (Blueprint $table) {
            $table->id();                     // PK autoincremental
            $table->string('name');            // Nombre del rocódromo (ej: "Rocódromo El Muro")
            $table->string('provincia');       // Provincia donde está (Andalucía)
            $table->string('direccion');        // Dirección completa
            $table->float('alto');              // Altura del muro en metros
            $table->float('ancho');             // Anchura del muro en metros
            $table->integer('n_lineas');        // Número de líneas/vías de escalada disponibles
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicacions');
    }
};
