<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla pivot 'entrenador_competidor' — Vínculos entre entrenadores y competidores.
 *
 * Tabla pivot self-referencing many-to-many en la tabla users:
 * un user (entrenador) se vincula con otro user (competidor).
 *
 * Flujo de vinculación:
 *   1. Entrenador busca competidor por DNI → envía solicitud
 *   2. Se crea registro con estado='pending'
 *   3. Competidor recibe notificación (SolicitudEntrenadorNotification)
 *   4. Competidor acepta → estado='accepted' (o rechaza → se borra el registro)
 *
 * Restricción clave: competidor_id es UNIQUE — un competidor solo puede
 * tener UN entrenador a la vez (ni siquiera puede tener dos solicitudes pendientes).
 *
 * Cascade: si se borra cualquiera de los dos usuarios, se borra el vínculo.
 *
 * Modelos: User::competidoresAceptados(), User::competidoresPendientes(), User::entrenadores()
 * Controladores: EntrenadorController (crear/eliminar), NotificacionController (aceptar/rechazar/desvincular)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrenador_competidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrenador_id')->constrained('users')->cascadeOnDelete();  // FK al entrenador
            $table->foreignId('competidor_id')->unique()->constrained('users')->cascadeOnDelete(); // FK al competidor (UNIQUE: solo 1 entrenador)
            $table->enum('estado', ['pending', 'accepted'])->default('pending'); // Estado del vínculo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrenador_competidor');
    }
};
