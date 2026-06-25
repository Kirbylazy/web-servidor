<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla 'notifications' — Notificaciones del sistema (canal database).
 *
 * Tabla estándar de Laravel para el sistema de notificaciones con canal 'database'.
 * Almacena las notificaciones enviadas a los usuarios.
 *
 * Tipos de notificación usados en la app:
 *   - SolicitudEntrenadorNotification: un entrenador quiere vincularse con un competidor
 *   - InscripcionActualizadaNotification: el árbitro aprobó/rechazó una inscripción
 *
 * La columna 'data' contiene JSON con los datos de la notificación (tipo, IDs, mensajes).
 * Se consultan con: $user->unreadNotifications, $user->notifications
 *
 * morphs('notifiable') crea: notifiable_type (string) + notifiable_id (bigint) + índice.
 * Esto permite que cualquier modelo pueda recibir notificaciones (polimorfismo).
 * En esta app, solo los Users reciben notificaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();           // UUID como PK (no autoincremental)
            $table->string('type');                    // Clase de la notificación (FQCN)
            $table->morphs('notifiable');              // notifiable_type + notifiable_id (polimorfismo)
            $table->text('data');                      // JSON con datos de la notificación
            $table->timestamp('read_at')->nullable();  // null = no leída, timestamp = leída
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
