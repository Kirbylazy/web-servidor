<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * SolicitudEntrenadorNotification — Notifica al competidor que un entrenador quiere vincularse.
 *
 * Se envía cuando un entrenador busca un competidor por DNI y le envía una solicitud
 * de vínculo (ver EntrenadorController::solicitarVinculo).
 *
 * Canal: 'database' — se almacena en la tabla 'notifications'.
 * El competidor ve la notificación en su dashboard con opciones de "Aceptar" y "Rechazar"
 * (dashboard/competidor.blade.php → NotificacionController::aceptar/rechazar).
 *
 * Datos almacenados en la notificación (JSON):
 *   - tipo:            'solicitud_entrenador' (para filtrar por tipo en las vistas)
 *   - entrenador_id:   ID del entrenador (para actualizar la tabla pivot al aceptar/rechazar)
 *   - entrenador_name: Nombre del entrenador (para mostrar sin cargar el modelo)
 *   - mensaje:         Texto legible para mostrar al competidor
 */
class SolicitudEntrenadorNotification extends Notification
{
    /**
     * @param User $entrenador El entrenador que envía la solicitud de vínculo
     */
    public function __construct(public User $entrenador) {}

    /**
     * Canales de envío: solo 'database'.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Datos que se almacenan como JSON en la columna 'data' de la tabla notifications.
     * NotificacionController lee estos datos para saber qué entrenador aceptar/rechazar.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo'            => 'solicitud_entrenador',                                  // Identificador del tipo
            'entrenador_id'   => $this->entrenador->id,                                   // ID para buscar en entrenador_competidor
            'entrenador_name' => $this->entrenador->name,                                 // Nombre para mostrar en la UI
            'mensaje'         => "{$this->entrenador->name} quiere ser tu entrenador.",    // Texto legible
        ];
    }
}
