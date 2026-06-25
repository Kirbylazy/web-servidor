<?php

namespace App\Http\Controllers;

use App\Models\Competicion;
use App\Models\User;
use App\Notifications\SolicitudEntrenadorNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EntrenadorController — Gestión del equipo y inscripciones del entrenador.
 *
 * Maneja tres funcionalidades principales:
 *   1. Solicitar vínculo con un competidor (buscar por DNI → enviar solicitud)
 *   2. Eliminar un competidor del equipo (desvincular)
 *   3. Inscribir al equipo (entrenador + competidores aceptados) en competiciones
 *
 * Accesible por entrenadores, árbitros y admins (middleware 'rol:entrenador').
 * Las vistas de este controlador están en dashboard/entrenador.blade.php
 * y arbitro/panel/entrenador.blade.php (el árbitro hereda funcionalidad de entrenador).
 *
 * Flujo del vínculo entrenador-competidor:
 *   1. Entrenador busca competidor por DNI en su dashboard
 *   2. Envía solicitud → se crea registro en entrenador_competidor (estado: 'pending')
 *   3. Competidor recibe notificación (SolicitudEntrenadorNotification, canal database)
 *   4. Competidor acepta/rechaza desde su dashboard (NotificacionController)
 *   5. Si acepta → estado cambia a 'accepted', el entrenador puede inscribirlo
 *
 * Un competidor solo puede tener UN entrenador a la vez (unique en competidor_id).
 *
 * Rutas: bajo entrenador/ con nombre entrenador.*
 */
class EntrenadorController extends Controller
{
    /**
     * Enviar solicitud de vínculo a un competidor.
     *
     * Busca al competidor por ID (previamente buscado por DNI en la vista),
     * verifica que sea competidor, que no sea el propio entrenador, y que
     * no tenga ya un entrenador (aceptado o con solicitud pendiente).
     *
     * Si todo es válido, crea el registro en la tabla pivot y envía
     * una notificación al competidor.
     *
     * Ruta: POST /entrenador/solicitar → entrenador.solicitar
     * Formulario: botón "Solicitar" en la búsqueda por DNI del dashboard
     * Notificación: SolicitudEntrenadorNotification → aparece en dashboard del competidor
     */
    public function solicitarVinculo(Request $request)
    {
        $request->validate(['competidor_id' => 'required|exists:users,id']);

        $entrenador  = auth()->user();
        $competidor  = User::findOrFail($request->competidor_id);

        // Validación: solo se pueden vincular competidores
        if ($competidor->rol !== 'competidor') {
            return back()->with('error', 'El usuario seleccionado no es un competidor.');
        }

        // Validación: no enviarse solicitud a uno mismo
        if ($competidor->id === $entrenador->id) {
            return back()->with('error', 'No puedes enviarte una solicitud a ti mismo.');
        }

        // Verificar que el competidor no tenga ya un entrenador (aceptado o pendiente).
        // Restricción: un competidor solo puede tener UN entrenador a la vez.
        $yaVinculado = DB::table('entrenador_competidor')
            ->where('competidor_id', $competidor->id)
            ->exists();

        if ($yaVinculado) {
            return back()->with('error', 'Este competidor ya tiene un entrenador o una solicitud pendiente.');
        }

        // Crear la solicitud de vínculo en estado 'pending'
        DB::table('entrenador_competidor')->insert([
            'entrenador_id'  => $entrenador->id,
            'competidor_id'  => $competidor->id,
            'estado'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Enviar notificación al competidor (canal database → tabla notifications)
        // El competidor verá "X quiere ser tu entrenador" en su dashboard
        $competidor->notify(new SolicitudEntrenadorNotification($entrenador));

        return back()->with('status', "Solicitud enviada a {$competidor->name}.");
    }

    /**
     * Desvincular un competidor del equipo del entrenador.
     *
     * Elimina el registro de la tabla pivot entrenador_competidor.
     * Tanto el entrenador como el competidor pueden desvincularse en cualquier momento
     * (el competidor lo hace desde NotificacionController::desvincular).
     *
     * Ruta: DELETE /entrenador/competidor/{competidor} → entrenador.eliminar_competidor
     * Botón: en la tabla de equipo del dashboard del entrenador
     */
    public function eliminarCompetidor(User $competidor)
    {
        $entrenador = auth()->user();

        // Eliminar el vínculo (independientemente del estado: pending o accepted)
        DB::table('entrenador_competidor')
            ->where('entrenador_id', $entrenador->id)
            ->where('competidor_id', $competidor->id)
            ->delete();

        return back()->with('status', "{$competidor->name} ha sido eliminado de tu equipo.");
    }

    /**
     * Inscribir al entrenador y/o sus competidores aceptados en una competición.
     *
     * El entrenador selecciona una competición y marca checkboxes con los
     * participantes que quiere inscribir (puede incluirse a sí mismo).
     * Solo se permiten IDs de competidores aceptados + el propio entrenador.
     *
     * Esta inscripción usa la tabla pivot LEGACY 'competicions_users' (no la tabla
     * 'inscripciones' que requiere documentos). Es una inscripción rápida/directa.
     *
     * Ruta: POST /entrenador/inscribir → entrenador.inscribir
     * Formulario: sección "Inscribir en competición" del dashboard
     */
    public function inscribir(Request $request)
    {
        $request->validate([
            'competicion_id' => 'required|exists:competicions,id',
            'participantes'  => 'required|array|min:1',         // Al menos 1 participante
            'participantes.*'=> 'exists:users,id',               // Cada ID debe existir
        ]);

        $entrenador  = auth()->user();
        $competicion = Competicion::findOrFail($request->competicion_id);

        // No permitir inscripción en competiciones pasadas
        if ($competicion->fecha_realizacion < now()) {
            return back()->with('error', 'Esta competición ya ha tenido lugar.');
        }

        // Construir lista de IDs permitidos: competidores aceptados + el propio entrenador
        $permitidos = $entrenador->competidoresAceptados()
            ->pluck('users.id')
            ->push($entrenador->id)
            ->toArray();

        $inscritos = 0;
        foreach ($request->participantes as $userId) {
            // Verificar que el ID está en la lista de permitidos (seguridad)
            if (!in_array((int) $userId, $permitidos)) {
                continue;
            }

            // Evitar inscripciones duplicadas (unique constraint en la tabla)
            $yaInscrito = DB::table('competicions_users')
                ->where('user_id', $userId)
                ->where('competicion_id', $competicion->id)
                ->exists();

            if (!$yaInscrito) {
                DB::table('competicions_users')->insert([
                    'user_id'       => $userId,
                    'competicion_id'=> $competicion->id,
                    'tipoDato'      => null,  // Columnas legacy, no se usan en este flujo
                    'dato'          => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $inscritos++;
            }
        }

        return back()->with('status', "{$inscritos} inscripción(es) realizadas correctamente.");
    }
}
