<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo LicenciaValidacion — Registro de validación de licencia federativa.
 *
 * Cada vez que un árbitro valida la licencia de un competidor, se crea un registro
 * en esta tabla. Hay dos tipos de validación:
 *
 *   - 'valida' (anual):     La licencia es válida hasta fin de año.
 *                            El competidor NO necesita volver a subir la licencia
 *                            en futuras competiciones del mismo año.
 *   - 'valida_dia' (diaria): La licencia solo vale para esa competición concreta.
 *                            Debe volver a subir/validar en la siguiente competición.
 *
 * Este modelo permite optimizar el flujo: si un competidor ya tiene licencia anual
 * vigente, la vista de inscripción lo detecta y le ahorra subir el documento de nuevo.
 *
 * Relaciones:
 *   - user()        → El competidor cuya licencia se validó
 *   - validador()   → El árbitro que realizó la validación
 *   - competicion() → La competición en la que se validó (nullable para validaciones anuales)
 *
 * Tabla: 'licencia_validaciones' (forzada con $table)
 *
 * Gestionada por: ArbitroController::validarLicencia() (crea registros),
 *                 InscripcionController::show() (consulta si hay validez anual)
 */
class LicenciaValidacion extends Model
{
    protected $table = 'licencia_validaciones';

    /**
     * Campos asignables masivamente.
     *
     * - user_id:        FK al competidor cuya licencia se validó
     * - validada_por:   FK al árbitro que hizo la validación (users.id)
     * - competicion_id: FK a la competición donde se validó (nullable)
     * - tipo:           Tipo de validación: 'valida' (anual) o 'valida_dia' (solo esa competición)
     * - valida_hasta:   Fecha límite de validez (31 dic del año actual para anual,
     *                   fecha de la competición para diaria)
     */
    protected $fillable = [
        'user_id',
        'validada_por',
        'competicion_id',
        'tipo',
        'valida_hasta',
    ];

    /**
     * Casts — valida_hasta se convierte a Carbon (solo fecha, sin hora).
     * Permite comparaciones como ->gte(now()) para comprobar vigencia.
     */
    protected function casts(): array
    {
        return [
            'valida_hasta' => 'date',
        ];
    }

    /**
     * El competidor cuya licencia fue validada.
     *
     * Usado por: consultas de validez anual (tieneValidezAnual, validezAnual)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El árbitro que realizó la validación.
     *
     * Usa la columna 'validada_por' como FK (no sigue convención de Laravel).
     * Permite rastrear quién validó cada licencia.
     */
    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validada_por');
    }

    /**
     * Competición en la que se realizó la validación.
     *
     * Nullable: para validaciones anuales, puede no estar asociada a una competición específica.
     * Para 'valida_dia', siempre tiene la competición donde se validó.
     */
    public function competicion(): BelongsTo
    {
        return $this->belongsTo(Competicion::class);
    }

    /**
     * Comprueba si esta validación de licencia aún está vigente (no ha caducado).
     *
     * Compara la fecha de validez con el inicio del día actual.
     *
     * @return bool true si valida_hasta >= hoy
     */
    public function estaVigente(): bool
    {
        return $this->valida_hasta->gte(now()->startOfDay());
    }

    /**
     * Comprueba si un usuario tiene licencia ANUAL vigente (tipo='valida').
     *
     * Método estático para consultar rápidamente sin cargar el modelo completo.
     * Una licencia anual vigente significa que el competidor no necesita
     * volver a subir el documento de licencia en nuevas inscripciones.
     *
     * Usado por: ArbitroController::categoria() (mostrar indicador en tabla),
     *            InscripcionController::show() (decidir si mostrar upload de licencia)
     *
     * @param int $userId ID del usuario a comprobar
     * @return bool true si tiene licencia anual vigente
     */
    public static function tieneValidezAnual(int $userId): bool
    {
        return self::where('user_id', $userId)
            ->where('tipo', 'valida')
            ->where('valida_hasta', '>=', now()->toDateString())
            ->exists();
    }

    /**
     * Devuelve el registro de validez anual vigente de un usuario, o null si no tiene.
     *
     * Similar a tieneValidezAnual() pero devuelve el objeto completo para poder
     * acceder a la fecha de expiración, quién lo validó, etc.
     *
     * Usado por: InscripcionController::show() (pasar info de validez a la vista)
     *
     * @param int $userId ID del usuario
     * @return LicenciaValidacion|null Registro de validez o null
     */
    public static function validezAnual(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('tipo', 'valida')
            ->where('valida_hasta', '>=', now()->toDateString())
            ->first();
    }
}
