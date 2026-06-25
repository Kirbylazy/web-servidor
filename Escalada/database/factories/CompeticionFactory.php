<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Copa;
use App\Models\Ubicacion;

/**
 * CompeticionFactory — Genera competiciones de escalada aleatorias.
 *
 * Crea competiciones con datos aleatorios para testing.
 *
 * Nota: esta factory NO asigna copa_id ni ubicacion_id automáticamente.
 * En DatabaseSeeder, las competiciones se crean manualmente con datos reales.
 * Esta factory se usa solo para CompeticionSeeder (testing individual)
 * o cuando se necesita una competición rápida en tests.
 *
 * Para usarla correctamente, hay que pasar copa_id y ubicacion_id:
 *   Competicion::factory()->create(['copa_id' => $copa->id, 'ubicacion_id' => $ubi->id])
 *
 * Modelo: App\Models\Competicion
 *
 * @extends Factory<\App\Models\Competicion>
 */
class CompeticionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city() . ' Copa',                    // Nombre genérico basado en ciudad
            'provincia' => fake()->randomElement(['Huelva','Sevilla','Cádiz','Málaga','Granada','Córdoba','Jaén','Almería']),
            'fecha_realizacion' => fake()->dateTimeBetween('+1 week', '+1 year'), // Siempre en el futuro
            'tipo' => fake()->randomElement(['bloque','cuerda','velocidad']),     // Tipo de escalada aleatorio
            'campeonato' => fake()->boolean(30),                   // 30% de probabilidad de ser campeonato
        ];
    }
}
