<?php

namespace App\Notifications;

use App\Models\Inscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * InscripcionActualizadaNotification — Notifica al competidor cuando su inscripción cambia de estado.
 *
 * Se envía cuando el árbitro valida los documentos de una inscripción y el estado
 * global cambia a 'aprobada' o 'rechazada' (ver ArbitroController::validarLicencia).
 *
 * Canal: 'database' — se almacena en la tabla 'notifications' (migración estándar de Laravel).
 * El competidor ve la notificación en su dashboard (dashboard/competidor.blade.php) y
 * al acceder al detalle de la competición se marca como leída automáticamente
 * (InscripcionController::show).
 *
 * Datos almacenados en la notificación (JSON):
 *   - tipo:           'inscripcion_actualizada' (para filtrar en las consultas)
 *   - competicion_id: ID de la competición (para buscar la notificación por competición)
 *   - competicion:    Nombre de la competición (para mostrar al usuario sin cargar el modelo)
 *   - estado:         Nuevo estado de la inscripción ('aprobada' o 'rechazada')
 *   - motivo:         Motivo del rechazo (null si fue aprobada)
 */
class InscripcionActualizadaNotification extends Notification
{
    use Queueable;

    /**
     * @param Inscripcion $inscripcion La inscripción que cambió de estado
     * @param string|null $motivo      Motivo del rechazo (solo si estado='rechazada')
     */
    public function __construct(
        public readonly Inscripcion $inscripcion,
        public readonly ?string $motivo = null
    ) {}

    /**
     * Canales de envío: solo 'database'.
     * La notificación se guarda en la tabla 'notifications' y se consulta con
     * $user->unreadNotifications en las vistas.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Datos que se almacenan como JSON en la columna 'data' de la tabla notifications.
     * Estos datos se leen directamente en las vistas sin necesidad de cargar modelos.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo'           => 'inscripcion_actualizada',              // Identificador del tipo de notificación
            'competicion_id' => $this->inscripcion->competicion_id,     // FK para buscar por competición
            'competicion'    => $this->inscripcion->competicion->name,  // Nombre para mostrar en la UI
            'estado'         => $this->inscripcion->estado,             // 'aprobada' o 'rechazada'
            'motivo'         => $this->motivo,                          // Motivo de rechazo (o null)
        ];
    }
}
