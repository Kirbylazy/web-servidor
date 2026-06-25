<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * CopaFactory — Genera copas/torneos de escalada aleatorios.
 *
 * Crea copas con nombre fijo "Copa de Andalucía", tipo aleatorio
 * y temporada entre 2023-2026.
 *
 * En DatabaseSeeder, las copas se crean manualmente con datos reales
 * (ej: "Copa de Bloque 2026"). Esta factory se usa para testing rápido.
 *
 * Modelo: App\Models\Copa
 *
 * @extends Factory<\App\Models\Copa>
 */
class CopaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Copa de Andalucía',                          // Nombre genérico fijo
            'tipo' => fake()->randomElement([
                'Bloque',
                'Cuerda',
                'Velocidad'
            ]),                                                      // Tipo de escalada aleatorio
            'temporada' => fake()->numberBetween(2023, 2026),        // Temporada aleatoria reciente
        ];
    }
}
