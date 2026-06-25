<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Ubicacion;

/**
 * UbicacionSeeder — Seeder individual para crear una ubicación de prueba.
 *
 * Crea UN solo rocódromo usando UbicacionFactory con datos aleatorios.
 * No se usa directamente en DatabaseSeeder (las ubicaciones se crean con factory allí).
 * Útil para testing individual: php artisan db:seed --class=UbicacionSeeder
 */
class UbicacionSeeder extends Seeder
{
    public function run(): void
    {
        Ubicacion::factory()->create();
    }
}
