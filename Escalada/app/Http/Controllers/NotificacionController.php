<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/**
 * NotificacionController — Gestión de notificaciones del competidor.
 *
 * Maneja las respuestas del competidor a las solicitudes de entrenador:
 *   - Aceptar: el entrenador se vincula al competidor
 *   - Rechazar: se elimina la solicitud
 *   - Desvincular: el competidor rompe el vínculo con su entrenador actual
 *
 * Las notificaciones se almacenan en la tabla 'notifications' (canal database de Laravel)
 * y se muestran en el dashboard del competidor (dashboard/competidor.blade.php).
 *
 * Flujo:
 *   1. EntrenadorController::solicitarVinculo() crea registro en entrenador_competidor
 *      y envía SolicitudEntrenadorNotification al competidor
 *   2. El competidor ve la notificación en su dashboard
 *   3. Acepta → aceptar(): actualiza estado a 'accepted' en entrenador_competidor
 *      Rechaza → rechazar(): elimina el registro de entrenador_competidor
 *   4. Desvincular → desvincular(): rompe el vínculo aceptado en cualquier momento
 *
 * Rutas: bajo notificaciones/ con nombre notificaciones.*
 * Vista: botones en dashboard/competidor.blade.php
 */
class NotificacionController extends Controller
{
    /**
     * Aceptar una solicitud de entrenador.
     *
     * Actualiza el registro en la tabla pivot entrenador_competidor de 'pending'
     * a 'accepted', y marca la notificación como leída.
     * A partir de este momento, el entrenador puede inscribir al competidor.
     *
     * Ruta: POST /notificaciones/{id}/aceptar → notificaciones.aceptar
     * Botón: "Aceptar" en la tarjeta de notificación del dashboard del competidor
     *
     * @param string $id UUID de la notificación (tabla notifications)
     */
    public function aceptar(string $id)
    {
        $user         = auth()->user();
        // Buscar la notificación del usuario (findOrFail protege contra acceso a notifs ajenas)
        $notification = $user->notifications()->findOrFail($id);
        $data         = $notification->data; // Contiene entrenador_id y entrenador_name

        // Cambiar estado del vínculo a 'accepted' en la tabla pivot
        DB::table('entrenador_competidor')
            ->where('entrenador_id', $data['entrenador_id'])
            ->where('competidor_id', $user->id)
            ->update(['estado' => 'accepted', 'updated_at' => now()]);

        // Marcar la notificación como leída (no se volverá a mostrar como pendiente)
        $notification->markAsRead();

        return back()->with('status', "Has aceptado a {$data['entrenador_name']} como tu entrenador.");
    }

    /**
     * Rechazar una solicitud de entrenador.
     *
     * Elimina el registro de la tabla pivot entrenador_competidor y también
     * elimina la notificación (no solo la marca como leída).
     *
     * Ruta: DELETE /notificaciones/{id}/rechazar → notificaciones.rechazar
     * Botón: "Rechazar" en la tarjeta de notificación del dashboard del competidor
     *
     * @param string $id UUID de la notificación
     */
    public function rechazar(string $id)
    {
        $user         = auth()->user();
        $notification = $user->notifications()->findOrFail($id);
        $data         = $notification->data;

        // Eliminar completamente el vínculo pendiente
        DB::table('entrenador_competidor')
            ->where('entrenador_id', $data['entrenador_id'])
            ->where('competidor_id', $user->id)
            ->delete();

        // Eliminar la notificación de la BD (no solo marcar como leída)
        $notification->delete();

        return back()->with('status', 'Has rechazado la solicitud de entrenador.');
    }

    /**
     * Desvincular al competidor de su entrenador actual.
     *
     * Elimina el vínculo aceptado. El competidor queda libre para aceptar
     * solicitudes de otros entrenadores. El entrenador pierde la capacidad
     * de inscribir a este competidor.
     *
     * A diferencia de rechazar(), esta acción no necesita ID de notificación
     * porque actúa sobre el vínculo activo (estado 'accepted').
     *
     * Ruta: DELETE /notificaciones/desvincular → notificaciones.desvincular
     * Botón: "Desvincularme" junto al nombre del entrenador en el dashboard
     */
    public function desvincular()
    {
        $user = auth()->user();

        // Eliminar el vínculo aceptado con cualquier entrenador
        DB::table('entrenador_competidor')
            ->where('competidor_id', $user->id)
            ->where('estado', 'accepted')
            ->delete();

        return back()->with('status', 'Te has desvinculado de tu entrenador.');
    }
}
