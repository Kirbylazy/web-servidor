<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Competicion;

/**
 * CompeticionSeeder — Seeder individual para crear una competición de prueba.
 *
 * Crea UNA sola competición usando CompeticionFactory con datos aleatorios.
 * No se usa directamente en DatabaseSeeder (las competiciones se crean manualmente allí).
 * Útil para testing individual: php artisan db:seed --class=CompeticionSeeder
 *
 * Nota: la CompeticionFactory NO asigna copa_id ni ubicacion_id automáticamente,
 * por lo que la competición creada puede fallar si no existen esos registros.
 */
class CompeticionSeeder extends Seeder
{
    public function run(): void
    {
        Competicion::factory()->create();
    }
}
