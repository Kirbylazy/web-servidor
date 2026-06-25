<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

/**
 * TestUsersSeeder — Crea usuarios de prueba con credenciales conocidas.
 *
 * Estos usuarios tienen emails y contraseñas predecibles para facilitar
 * el testing manual de la aplicación con diferentes roles.
 *
 * Usuarios creados (contraseña: 'password' para todos):
 *   - arbitro@escalada.com    → rol: arbitro    (DNI: 11111111B)
 *   - entrenador@escalada.com → rol: entrenador (DNI: 11111111A)
 *   - competidor1-5@escalada.com → rol: competidor (DNIs: 22222221B - 22222225B)
 *
 * Usa firstOrCreate para no duplicar si ya existen (idempotente).
 *
 * Nota: el admin se crea directamente en DatabaseSeeder (admin@escalada.com / admin).
 * Estos competidores de prueba se EXCLUYEN del InscripcionesSeeder para que
 * su estado de inscripciones esté limpio al probar manualmente.
 *
 * Llamado desde: DatabaseSeeder
 */
class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // ── Árbitro de prueba ──
        // Se asigna como árbitro a todas las competiciones en InscripcionesSeeder
        User::firstOrCreate(['email' => 'arbitro@escalada.com'], [
            'name'             => 'arbitro',
            'password'         => bcrypt('password'),
            'rol'              => 'arbitro',
            'dni'              => '11111111B',
            'fecha_nacimiento' => '1988-03-20',
            'provincia'        => 'Sevilla',
            'talla'            => 'M',
            'genero'           => 'otro',
        ]);

        // ── Entrenador de prueba ──
        // Puede buscar competidores por DNI y enviar solicitudes de vínculo
        User::firstOrCreate(['email' => 'entrenador@escalada.com'], [
            'name'             => 'entrenador',
            'password'         => bcrypt('password'),
            'rol'              => 'entrenador',
            'dni'              => '11111111A',
            'fecha_nacimiento' => '1985-05-15',
            'provincia'        => 'Madrid',
            'talla'            => 'L',
            'genero'           => 'otro',
        ]);

        // ── 5 Competidores de prueba ──
        // DNIs predecibles (22222221B - 22222225B) para buscar fácilmente desde el panel de entrenador
        // Nacidos en 2000 → categoría Absoluta (26 años)
        foreach (range(1, 5) as $i) {
            User::firstOrCreate(['email' => "competidor{$i}@escalada.com"], [
                'name'             => "competidor{$i}",
                'password'         => bcrypt('password'),
                'rol'              => 'competidor',
                'dni'              => "2222222{$i}B",
                'fecha_nacimiento' => '2000-01-0' . $i,
                'provincia'        => 'Madrid',
                'talla'            => 'M',
                'genero'           => 'otro',
            ]);
        }
    }
}
