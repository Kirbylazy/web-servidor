<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Copa;

/**
 * CopaSeeder — Seeder individual para crear una copa de prueba.
 *
 * Crea UNA sola copa usando CopaFactory con datos aleatorios.
 * No se usa directamente en DatabaseSeeder (las copas se crean manualmente allí).
 * Útil para testing individual: php artisan db:seed --class=CopaSeeder
 */
class CopaSeeder extends Seeder
{
    public function run(): void
    {
        Copa::factory()->create();
    }
}
