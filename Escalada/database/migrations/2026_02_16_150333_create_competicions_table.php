<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla 'competicions' — Competiciones/pruebas de escalada.
 *
 * Cada competición es un evento concreto: tiene lugar en un rocódromo,
 * puede pertenecer a una copa, y tiene un árbitro asignado opcionalmente.
 *
 * Modelo: App\Models\Competicion (con $table = 'competicions' explícito)
 * Controlador: CompeticionController (CRUD), AdminController (asignar árbitro)
 *
 * FKs y comportamiento al borrar:
 *   - copa_id     → nullOnDelete (si se borra la copa, la competición queda independiente)
 *   - arbitro_id  → nullOnDelete (si se borra el árbitro, la competición queda sin árbitro)
 *   - ubicacion_id → cascadeOnDelete (si se borra el rocódromo, se borran sus competiciones)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competicions', function (Blueprint $table) {
            $table->id();

            // FK a la copa/torneo (opcional — una competición puede ser independiente)
            $table->foreignId('copa_id')
                  ->nullable()
                  ->constrained('copas')
                  ->nullOnDelete();          // Si se borra la copa → copa_id = null

            // FK al usuario árbitro asignado (opcional — se asigna después por el admin)
            $table->foreignId('arbitro_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();          // Si se borra el árbitro → arbitro_id = null

            // FK al rocódromo donde se celebra (obligatorio)
            $table->foreignId('ubicacion_id')
                  ->constrained('ubicacions')
                  ->cascadeOnDelete();       // Si se borra el rocódromo → se borra la competición

            $table->string('name');                              // Nombre de la prueba
            $table->string('provincia');                          // Provincia (redundante con ubicación, para filtros rápidos)
            $table->dateTime('fecha_realizacion');                // Fecha y hora de inicio
            $table->dateTime('fecha_fin')->nullable();            // Fecha y hora de fin (puede ser mismo día)
            $table->string('tipo');                               // Tipo: 'bloque', 'dificultad', 'velocidad'
            $table->boolean('campeonato')->default(false);        // Si es el campeonato/final de la copa
            $table->json('categorias')->nullable();               // JSON array de categorías habilitadas
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competicions');
    }
};
