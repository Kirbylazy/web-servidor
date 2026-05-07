<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tablas de caché — Infraestructura estándar de Laravel.
 *
 * Crea las tablas para el driver de caché 'database' de Laravel:
 *   - cache: almacena valores cacheados con clave, valor y tiempo de expiración
 *   - cache_locks: locks atómicos para evitar race conditions en caché
 *
 * Estas tablas son parte del scaffolding de Laravel y no son específicas
 * de la lógica de escalada. Se usan internamente por el framework.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabla principal de caché
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();         // Clave única del valor cacheado
            $table->mediumText('value');                // Valor serializado
            $table->integer('expiration')->index();    // Timestamp de expiración (unix)
        });

        // Locks atómicos para operaciones de caché concurrentes
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();         // Clave del lock
            $table->string('owner');                   // Identificador del proceso que tiene el lock
            $table->integer('expiration')->index();    // Timestamp de expiración del lock
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
