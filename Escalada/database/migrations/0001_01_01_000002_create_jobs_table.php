<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tablas de jobs/colas — Infraestructura estándar de Laravel.
 *
 * Crea las tablas para el sistema de colas (queues) de Laravel:
 *   - jobs: trabajos pendientes de ejecutar en background
 *   - job_batches: lotes de trabajos agrupados
 *   - failed_jobs: trabajos que fallaron (para reintentar o inspeccionar)
 *
 * Estas tablas son parte del scaffolding de Laravel. En este proyecto las
 * notificaciones se procesan de forma síncrona (canal 'database'), por lo
 * que estas tablas no se usan activamente, pero están disponibles por si
 * se necesita procesar algo en background en el futuro.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabla de trabajos pendientes en cola
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();              // Nombre de la cola (ej: 'default')
            $table->longText('payload');                    // Datos del job serializado
            $table->unsignedTinyInteger('attempts');        // Número de intentos realizados
            $table->unsignedInteger('reserved_at')->nullable(); // Cuándo fue reservado por un worker
            $table->unsignedInteger('available_at');        // Cuándo estará disponible para ejecutar
            $table->unsignedInteger('created_at');          // Cuándo se creó el job
        });

        // Tabla de lotes de jobs (para procesar grupos de trabajos)
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');                        // Nombre del lote
            $table->integer('total_jobs');                  // Total de jobs en el lote
            $table->integer('pending_jobs');                // Jobs pendientes
            $table->integer('failed_jobs');                 // Jobs fallidos
            $table->longText('failed_job_ids');             // IDs de jobs fallidos
            $table->mediumText('options')->nullable();      // Opciones del lote
            $table->integer('cancelled_at')->nullable();    // Si fue cancelado
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();     // Cuándo terminó el lote
        });

        // Tabla de jobs fallidos (para inspección y reintento)
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();              // UUID único del job
            $table->text('connection');                     // Conexión de cola usada
            $table->text('queue');                          // Cola donde estaba
            $table->longText('payload');                    // Datos del job
            $table->longText('exception');                  // Excepción que causó el fallo
            $table->timestamp('failed_at')->useCurrent();  // Cuándo falló
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
