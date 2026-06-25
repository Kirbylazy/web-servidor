<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Competicion;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * InscripcionesSeeder — Genera inscripciones masivas de prueba.
 *
 * Crea 150 inscripciones por cada competición (7 competiciones = ~1050 total)
 * con una distribución realista de estados para simular datos reales
 * en el panel del árbitro.
 *
 * Distribución de estados:
 *   - 30% pendiente (sin verificar) — el árbitro aún no ha revisado
 *   - 30% aprobada (ambos docs válidos) — el competidor puede participar
 *   - 20% rechazada por licencia — licencia caducada o no corresponde
 *   - 10% rechazada por pago — justificante no válido
 *   - 10% pendiente parcial — licencia ya verificada, pago pendiente
 *
 * También:
 *   - Crea un archivo placeholder (GIF 1x1px) en storage para simular documentos
 *   - Asigna el árbitro de prueba a todas las competiciones sin árbitro
 *   - Excluye los 5 competidores de prueba (competidor1-5@escalada.com)
 *     para que su estado esté limpio al probar manualmente
 *
 * Llamado desde: DatabaseSeeder (después de TestUsersSeeder)
 */
class InscripcionesSeeder extends Seeder
{
    public function run(): void
    {
        // ── Crear archivo placeholder para simular documentos subidos ──
        // En producción, cada competidor sube su propia licencia y pago.
        // Para el seeder, todos los documentos apuntan al mismo placeholder.
        $placeholderDir = storage_path('app/public/inscripciones');
        if (!is_dir($placeholderDir)) {
            mkdir($placeholderDir, 0755, true);
        }

        $placeholderPath = $placeholderDir . '/placeholder.jpg';
        if (!file_exists($placeholderPath)) {
            // GIF 1x1 pixel transparente — el archivo más pequeño válido posible
            file_put_contents(
                $placeholderPath,
                base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
            );
        }

        // Rutas relativas al disco 'public' (storage/app/public/)
        $licenciaPlaceholder = 'inscripciones/placeholder.jpg';
        $pagoPlaceholder     = 'inscripciones/placeholder.jpg';

        // ── Asignar árbitro de prueba a TODAS las competiciones sin árbitro ──
        // Esto permite al usuario de prueba (arbitro@escalada.com) ver todas
        // las competiciones en su panel y gestionar inscripciones
        $arbitro = User::where('email', 'arbitro@escalada.com')->first();
        if ($arbitro) {
            Competicion::whereNull('arbitro_id')->each(function ($c) use ($arbitro) {
                $c->update(['arbitro_id' => $arbitro->id]);
            });
        }

        $competiciones = Competicion::all();

        // ── Seleccionar competidores para inscribir ──
        // Excluir los 5 competidores de prueba para que puedan probar
        // el flujo de inscripción manualmente con estado limpio
        $competidores  = User::where('rol', 'competidor')
            ->whereNotIn('email', array_map(fn($i) => "competidor{$i}@escalada.com", range(1, 5)))
            ->pluck('id')->shuffle();

        if ($competidores->count() < 150) {
            $this->command->warn('Hay menos de 150 competidores. Se usarán todos los disponibles.');
        }

        // ── Crear inscripciones para cada competición ──
        foreach ($competiciones as $competicion) {
            // Tomar los primeros 150 competidores (mezclados aleatoriamente)
            $seleccionados = $competidores->take(150);

            foreach ($seleccionados as $userId) {
                $user = User::find($userId);
                if (!$user) continue;

                // Evitar duplicados si se ejecuta el seeder varias veces
                if (Inscripcion::where('user_id', $userId)->where('competicion_id', $competicion->id)->exists()) {
                    continue;
                }

                // ── Distribución realista de estados ──
                // Genera un número 1-10 para distribuir los estados proporcionalmente
                $rand = rand(1, 10);
                if ($rand <= 3) {
                    // 30% — PENDIENTE: inscripción enviada, ningún doc verificado aún
                    $estado = 'pendiente';
                    $licenciaEstado = null;
                    $pagoEstado     = null;
                    $licenciaMotivo = null;
                    $pagoMotivo     = null;
                } elseif ($rand <= 6) {
                    // 30% — APROBADA: ambos documentos validados como válidos
                    $estado = 'aprobada';
                    $licenciaEstado = rand(0,1) ? 'valida' : 'valida_dia'; // Anual o diaria aleatoriamente
                    $pagoEstado     = rand(0,1) ? 'valida' : 'valida_dia';
                    $licenciaMotivo = null;
                    $pagoMotivo     = null;
                } elseif ($rand <= 8) {
                    // 20% — RECHAZADA POR LICENCIA: licencia no válida
                    $estado = 'rechazada';
                    $licenciaEstado = 'no_valida';
                    $pagoEstado     = rand(0,1) ? 'valida' : null; // Pago puede o no estar verificado
                    $licenciaMotivo = 'La licencia federativa está caducada o no corresponde al titular.';
                    $pagoMotivo     = null;
                } elseif ($rand === 9) {
                    // 10% — RECHAZADA POR PAGO: justificante no válido
                    $estado = 'rechazada';
                    $licenciaEstado = rand(0,1) ? 'valida' : null;
                    $pagoEstado     = 'no_valida';
                    $licenciaMotivo = null;
                    $pagoMotivo     = 'El justificante de pago no es válido o el importe no corresponde.';
                } else {
                    // 10% — PENDIENTE PARCIAL: licencia verificada, pago aún pendiente
                    // Simula el caso real donde el árbitro va verificando documento a documento
                    $estado = 'pendiente';
                    $licenciaEstado = 'valida';
                    $pagoEstado     = null;
                    $licenciaMotivo = null;
                    $pagoMotivo     = null;
                }

                // Crear la inscripción con la categoría calculada automáticamente
                Inscripcion::create([
                    'user_id'         => $userId,
                    'competicion_id'  => $competicion->id,
                    'licencia_path'   => $licenciaPlaceholder,   // Todos apuntan al placeholder
                    'pago_path'       => $pagoPlaceholder,
                    'estado'          => $estado,
                    'motivo_rechazo'  => null,
                    'categoria'       => Inscripcion::calcularCategoria($user), // Calculada por edad+género
                    'licencia_estado' => $licenciaEstado,
                    'pago_estado'     => $pagoEstado,
                    'licencia_motivo' => $licenciaMotivo,
                    'pago_motivo'     => $pagoMotivo,
                    'created_at'      => now()->subDays(rand(1, 30)),   // Fechas aleatorias recientes
                    'updated_at'      => now()->subDays(rand(0, 5)),
                ]);
            }
        }

        $this->command->info('Inscripciones de prueba creadas correctamente.');
    }
}
