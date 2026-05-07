<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * UbicacionFactory — Genera rocódromos/ubicaciones de escalada aleatorios.
 *
 * Crea ubicaciones con datos realistas:
 *   - Nombre: "Rocódromo" + ciudad aleatoria
 *   - Provincia: una de las 8 provincias andaluzas
 *   - Dirección: dirección aleatoria
 *   - Dimensiones del muro: medidas realistas para un rocódromo
 *
 * Usado por: DatabaseSeeder para crear 8 ubicaciones (una por provincia andaluza),
 *            UbicacionSeeder para testing individual.
 *
 * En DatabaseSeeder se sobreescribe la provincia con la correcta:
 *   Ubicacion::factory()->create(['provincia' => 'Sevilla'])
 *
 * Modelo: App\Models\Ubicacion
 *
 * @extends Factory<\App\Models\Ubicacion>
 */
class UbicacionFactory extends Factory
{
    public function definition(): array
    {
        $provincias = ['Sevilla', 'Cádiz', 'Málaga', 'Granada', 'Córdoba', 'Huelva', 'Jaén', 'Almería'];

        return [
            'name' => 'Rocódromo ' . fake()->city(),                     // Nombre del rocódromo
            'provincia' => fake()->randomElement($provincias),            // Provincia andaluza
            'direccion' => fake()->streetAddress(),                       // Dirección aleatoria

            // Medidas razonables para un muro de escalada
            'alto' => fake()->randomFloat(2, 3.0, 18.0),                 // Altura: 3m - 18m (2 decimales)
            'ancho' => fake()->randomFloat(2, 4.0, 35.0),                // Anchura: 4m - 35m (2 decimales)

            'n_lineas' => fake()->numberBetween(5, 60),                   // Líneas/vías: 5 - 60
        ];
    }
}
