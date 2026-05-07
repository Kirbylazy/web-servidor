<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Copa;
use App\Models\Competicion;
use App\Models\Ubicacion;
use App\Models\User;

/**
 * DatabaseSeeder — Seeder principal que puebla la BD con datos iniciales y de prueba.
 *
 * Se ejecuta con: php artisan db:seed (o php artisan migrate:fresh --seed)
 *
 * Crea en este orden:
 *   1. Usuario admin (admin@escalada.com / admin)
 *   2. 2 Copas: Bloque 2026 y Dificultad 2026
 *   3. 8 Ubicaciones (una por provincia andaluza) usando UbicacionFactory
 *   4. 7 Competiciones reales de Andalucía:
 *      - 3 de Bloque (la 3ª es campeonato)
 *      - 3 de Dificultad (la 3ª es campeonato)
 *      - 1 de Velocidad (campeonato independiente, sin copa)
 *   5. 300 usuarios aleatorios con UserFactory (todos rol competidor)
 *   6. TestUsersSeeder: usuarios de prueba con credenciales conocidas
 *   7. InscripcionesSeeder: 150 inscripciones por competición con estados variados
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Admin — Usuario administrador con acceso total ───────────────
        // Credenciales: admin@escalada.com / admin
        User::create([
            'name'             => 'admin',
            'email'            => 'admin@escalada.com',
            'password'         => bcrypt('admin'),
            'rol'              => 'admin',
            'dni'              => '00000000A',
            'fecha_nacimiento' => '1990-01-01',
            'provincia'        => 'Sevilla',
            'talla'            => 'M',
            'genero'           => 'otro',
        ]);

        // ── 2. Copas — Series de competiciones por tipo de escalada ─────────
        // Cada copa agrupa 3 pruebas del mismo tipo durante la temporada 2026
        $copaBloque = Copa::create([
            'name'      => 'Copa de Bloque 2026',
            'tipo'      => 'Bloque',
            'temporada' => 2026,
        ]);

        $copaDificultad = Copa::create([
            'name'      => 'Copa de Dificultad 2026',
            'tipo'      => 'Dificultad',
            'temporada' => 2026,
        ]);

        // ── 3. Ubicaciones — Un rocódromo por cada provincia andaluza ───────
        // Usa UbicacionFactory para generar datos aleatorios (nombre, dirección, medidas)
        // pero forzando la provincia correcta
        $provincias = ['Sevilla', 'Málaga', 'Granada', 'Córdoba', 'Cádiz', 'Almería', 'Huelva', 'Jaén'];

        $ubicaciones = [];
        foreach ($provincias as $prov) {
            $ubicaciones[$prov] = Ubicacion::factory()->create(['provincia' => $prov]);
        }

        // ── 4a. Competiciones de Bloque — 3 pruebas en la Copa de Bloque ────
        // La 3ª prueba es además el Campeonato de Andalucía de Bloque
        Competicion::create([
            'name'              => '1ª Prueba de Bloque de Andalucía, Sevilla',
            'provincia'         => 'Sevilla',
            'tipo'              => 'bloque',
            'campeonato'        => false,
            'fecha_realizacion' => '2026-02-14 10:00:00',
            'copa_id'           => $copaBloque->id,
            'ubicacion_id'      => $ubicaciones['Sevilla']->id,
        ]);

        Competicion::create([
            'name'              => '2ª Prueba de Bloque de Andalucía, Málaga',
            'provincia'         => 'Málaga',
            'tipo'              => 'bloque',
            'campeonato'        => false,
            'fecha_realizacion' => '2026-03-21 10:00:00',
            'copa_id'           => $copaBloque->id,
            'ubicacion_id'      => $ubicaciones['Málaga']->id,
        ]);

        Competicion::create([
            'name'              => '3ª Prueba de Bloque y Campeonato de Andalucía, Granada',
            'provincia'         => 'Granada',
            'tipo'              => 'bloque',
            'campeonato'        => true,  // Esta prueba es el campeonato de la copa
            'fecha_realizacion' => '2026-05-09 10:00:00',
            'copa_id'           => $copaBloque->id,
            'ubicacion_id'      => $ubicaciones['Granada']->id,
        ]);

        // ── 4b. Competiciones de Dificultad — 3 pruebas en la Copa de Dificultad ──
        Competicion::create([
            'name'              => '1ª Prueba de Dificultad de Andalucía, Córdoba',
            'provincia'         => 'Córdoba',
            'tipo'              => 'dificultad',
            'campeonato'        => false,
            'fecha_realizacion' => '2026-10-10 10:00:00',
            'copa_id'           => $copaDificultad->id,
            'ubicacion_id'      => $ubicaciones['Córdoba']->id,
        ]);

        Competicion::create([
            'name'              => '2ª Prueba de Dificultad de Andalucía, Cádiz',
            'provincia'         => 'Cádiz',
            'tipo'              => 'dificultad',
            'campeonato'        => false,
            'fecha_realizacion' => '2026-11-14 10:00:00',
            'copa_id'           => $copaDificultad->id,
            'ubicacion_id'      => $ubicaciones['Cádiz']->id,
        ]);

        Competicion::create([
            'name'              => '3ª Prueba de Dificultad y Campeonato de Andalucía, Almería',
            'provincia'         => 'Almería',
            'tipo'              => 'dificultad',
            'campeonato'        => true,
            'fecha_realizacion' => '2026-12-05 10:00:00',
            'copa_id'           => $copaDificultad->id,
            'ubicacion_id'      => $ubicaciones['Almería']->id,
        ]);

        // ── 4c. Competición de Velocidad — Prueba independiente (sin copa) ──
        // La velocidad solo tiene una prueba que es directamente el campeonato
        Competicion::create([
            'name'              => 'Prueba de Velocidad y Campeonato de Andalucía, Huelva',
            'provincia'         => 'Huelva',
            'tipo'              => 'velocidad',
            'campeonato'        => true,
            'fecha_realizacion' => '2026-06-20 10:00:00',
            'copa_id'           => null,  // No pertenece a ninguna copa
            'ubicacion_id'      => $ubicaciones['Huelva']->id,
        ]);

        // ── 5. Usuarios masivos — 300 competidores generados con UserFactory ──
        // Todos con rol 'competidor', nombres/apellidos españoles realistas,
        // edades entre 8-45 años, provincias andaluzas
        User::factory()->count(300)->create();

        // ── 6. Usuarios de prueba con credenciales conocidas ────────────────
        // Crea: árbitro, entrenador y 5 competidores con emails y passwords predecibles
        $this->call(TestUsersSeeder::class);

        // ── 7. Inscripciones masivas — 150 por competición ──────────────────
        // Crea inscripciones con distribución realista de estados:
        // 30% pendiente, 30% aprobada, 20% rechazada por licencia, etc.
        $this->call(InscripcionesSeeder::class);
    }
}
