<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Inscripcion — Representa la inscripción formal de un competidor en una competición.
 *
 * Este es el modelo central del flujo de inscripción con verificación de documentos.
 * Cada inscripción pasa por un ciclo de vida:
 *
 *   1. BORRADOR: el competidor accede a la competición → se crea la inscripción vacía
 *   2. El competidor sube la licencia federativa (licencia_path)
 *   3. El competidor sube el justificante de pago (pago_path)
 *   4. PENDIENTE: el competidor envía la inscripción → pasa a revisión del árbitro
 *   5. El árbitro valida cada documento por separado (licencia_estado, pago_estado)
 *   6. APROBADA: ambos documentos son válidos → el competidor puede participar
 *      RECHAZADA: algún documento no es válido → el competidor recibe notificación
 *
 * La categoría (ej: "Masculino U17") se calcula automáticamente por edad y género,
 * pero el árbitro puede cambiarla manualmente.
 *
 * Restricción: un usuario solo puede tener UNA inscripción por competición
 * (unique en [user_id, competicion_id]).
 *
 * Relaciones:
 *   - user()        → El competidor inscrito
 *   - competicion() → La competición a la que se inscribe
 *
 * Tabla: 'inscripciones' (forzada con $table, Laravel pluralizaría mal)
 *
 * Gestionada por: InscripcionController (flujo del competidor),
 *                 ArbitroController (validación de documentos)
 * Notificaciones: InscripcionActualizadaNotification al cambiar estado
 */
class Inscripcion extends Model
{
    /**
     * Nombre de la tabla en BD — forzado porque Laravel no pluraliza bien en español.
     */
    protected $table = 'inscripciones';

    /**
     * Campos asignables masivamente.
     *
     * - user_id:         FK al competidor (tabla users)
     * - competicion_id:  FK a la competición (tabla competicions)
     * - licencia_path:   Ruta al archivo de licencia federativa subido (storage)
     * - pago_path:       Ruta al archivo de justificante de pago subido (storage)
     * - estado:          Estado global: 'borrador', 'pendiente', 'aprobada', 'rechazada'
     * - motivo_rechazo:  Texto explicativo si se rechaza (legacy, se usan licencia_motivo/pago_motivo)
     * - categoria:       Categoría asignada (ej: "Masculino U17", "Femenino Absoluta")
     * - licencia_estado: Estado de validación de la licencia: 'valida', 'valida_dia', 'no_valida', null
     * - pago_estado:     Estado de validación del pago: 'valida', 'valida_dia', 'no_valida', null
     * - licencia_motivo: Motivo si la licencia no es válida (texto libre del árbitro)
     * - pago_motivo:     Motivo si el pago no es válido (texto libre del árbitro)
     */
    protected $fillable = [
        'user_id',
        'competicion_id',
        'licencia_path',
        'pago_path',
        'estado',
        'motivo_rechazo',
        'categoria',
        'licencia_estado',
        'pago_estado',
        'licencia_motivo',
        'pago_motivo',
    ];

    /**
     * Competidor que realiza esta inscripción.
     *
     * Usado por: ArbitroController (ver datos del competidor),
     *            InscripcionesSeeder (calcular categoría)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Competición a la que corresponde esta inscripción.
     *
     * Usado por: InscripcionController::show() (mostrar detalles de la competición),
     *            InscripcionActualizadaNotification (nombre de la competición en la notificación)
     */
    public function competicion(): BelongsTo
    {
        return $this->belongsTo(Competicion::class);
    }

    /**
     * Comprueba si ambos documentos (licencia y pago) han sido subidos.
     *
     * El competidor no puede enviar la inscripción a revisión hasta que
     * ambos documentos estén subidos. Se comprueba en InscripcionController::store().
     *
     * @return bool true si ambos paths tienen valor (archivos subidos)
     */
    public function documentosCompletos(): bool
    {
        return !empty($this->licencia_path) && !empty($this->pago_path);
    }

    /**
     * Recalcula el estado global de la inscripción según los estados individuales de cada documento.
     *
     * Lógica:
     *   - Si CUALQUIER documento es 'no_valida' → estado = 'rechazada'
     *   - Si AMBOS documentos son válidos ('valida' o 'valida_dia') → estado = 'aprobada'
     *   - En cualquier otro caso (alguno sin validar) → estado = 'pendiente'
     *
     * Se llama desde ArbitroController::validarLicencia() cada vez que el árbitro
     * valida un documento. Guarda automáticamente en BD.
     */
    public function recalcularEstado(): void
    {
        // Si cualquier documento es rechazado, la inscripción completa se rechaza
        if ($this->licencia_estado === 'no_valida' || $this->pago_estado === 'no_valida') {
            $this->estado = 'rechazada';
        // Si ambos documentos están validados (ya sea anual o por un día), se aprueba
        } elseif (
            in_array($this->licencia_estado, ['valida', 'valida_dia']) &&
            in_array($this->pago_estado, ['valida', 'valida_dia'])
        ) {
            $this->estado = 'aprobada';
        // Si falta algún documento por validar, sigue pendiente
        } else {
            $this->estado = 'pendiente';
        }
        $this->save();
    }

    /**
     * Convierte el estado interno de un documento a una etiqueta legible para las vistas.
     *
     * Usado por: arbitro/categoria.blade.php para mostrar el estado de cada documento
     * en la tabla de inscripciones.
     *
     * @param string|null $estado Estado interno ('valida', 'valida_dia', 'no_valida', null)
     * @return string Etiqueta en español para mostrar al usuario
     */
    public static function etiquetaEstadoDoc(?string $estado): string
    {
        return match ($estado) {
            'valida'     => 'Válida',
            'valida_dia' => 'Válida por un día',
            'no_valida'  => 'No válida',
            default      => 'Pendiente',
        };
    }

    /**
     * Calcula la categoría por defecto de un usuario según su edad y género.
     *
     * Solo se usa el AÑO de nacimiento (mes y día son irrelevantes para la categoría).
     * La categoría combina género + rango de edad:
     *   - U9: 7-8 años    |  U11: 9-10   |  U13: 11-12  |  U15: 13-14
     *   - U17: 15-16       |  U19: 17-18  |  Absoluta: 19-34  |  Veterana: 35+
     *   - Promoción: NUNCA se asigna automáticamente (solo manual por el árbitro)
     *
     * Resultado ejemplo: "Masculino U17", "Femenino Absoluta"
     *
     * Usado por: InscripcionController::show() (al crear inscripción borrador),
     *            InscripcionesSeeder (datos de prueba),
     *            ArbitroController (referencia, aunque el árbitro puede cambiarla)
     *
     * @param User $user Usuario del que calcular la categoría
     * @return string Categoría formateada (ej: "Femenino U15")
     */
    public static function calcularCategoria(User $user): string
    {
        // Obtener año de nacimiento; si no tiene fecha, usar año actual (edad = 0)
        $añoNac = (int) ($user->fecha_nacimiento?->format('Y') ?? now()->year);
        $edad   = now()->year - $añoNac;

        // Determinar categoría por rango de edad
        $cat = match (true) {
            $edad <= 8  => 'U9',
            $edad <= 10 => 'U11',
            $edad <= 12 => 'U13',
            $edad <= 14 => 'U15',
            $edad <= 16 => 'U17',
            $edad <= 18 => 'U19',
            $edad <= 34 => 'Absoluta',
            default     => 'Veterana',
        };

        // Convertir código de género (M/F) a texto completo
        $genero = match ($user->genero) {
            'M'     => 'Masculino',
            'F'     => 'Femenino',
            default => 'Masculino', // Por defecto si no se especifica
        };

        return "$genero $cat";
    }

    /**
     * Genera la lista completa de todas las categorías válidas para selección manual.
     *
     * Incluye todas las combinaciones de género + categoría de edad, más las
     * tres variantes de Promoción (Masculino, Femenino, Mixta).
     * Promoción es la única categoría que puede ser "Mixta".
     *
     * Usado por: ArbitroController::cambiarCategoria() (validación),
     *            arbitro/categoria.blade.php (dropdown de cambio de categoría)
     *
     * @return array Lista de strings con todas las categorías válidas
     */
    public static function listaCategorias(): array
    {
        $result = [];
        // Generar combinaciones género + categoría para cada categoría de edad
        foreach (['U9', 'U11', 'U13', 'U15', 'U17', 'U19', 'Absoluta', 'Veterana'] as $cat) {
            $result[] = "Masculino $cat";
            $result[] = "Femenino $cat";
        }
        // Promoción es especial: puede ser Masculino, Femenino o Mixta
        $result[] = 'Masculino Promoción';
        $result[] = 'Femenino Promoción';
        $result[] = 'Mixta Promoción';
        return $result;
    }
}
