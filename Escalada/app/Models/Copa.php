<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Competicion;

/**
 * Modelo Copa — Representa un torneo o serie de competiciones.
 *
 * Una copa agrupa varias competiciones del mismo tipo en una temporada.
 * Ejemplo: "Copa de Bloque 2026" contiene 3 pruebas de bloque, siendo
 * la última el campeonato de Andalucía.
 *
 * Tipos de copa: 'Bloque', 'Dificultad', 'Velocidad' (coinciden con tipos de competición).
 * Temporada: año (ej: 2026).
 *
 * Relaciones:
 *   - competiciones() → Las competiciones/pruebas que forman esta copa
 *
 * Tabla: 'copas' (Laravel pluraliza correctamente)
 *
 * Gestionada por: CopaController (CRUD), AdminController::copas() (listado)
 * Vistas: admin/copas.blade.php (gestión), admin/pruebas.blade.php (filtro por copa)
 */
class Copa extends Model
{
    /** @use HasFactory<\Database\Factories\CopaFactory> */
    use HasFactory;

    /**
     * Campos asignables masivamente.
     *
     * - name:      Nombre de la copa (ej: "Copa de Bloque 2026")
     * - temporada: Año de la temporada (ej: 2026) — se castea a integer
     * - tipo:      Tipo de escalada: 'Bloque', 'Dificultad', 'Velocidad'
     */
    protected $fillable = [
        'name',
        'temporada',
        'tipo',
    ];

    /**
     * Casts — temporada se convierte a integer para comparaciones numéricas.
     * Se usa en filtros de admin (ej: mostrar solo copas del año actual).
     */
    protected function casts(): array
    {
        return [
            'temporada' => 'integer',
        ];
    }

    /**
     * Competiciones que pertenecen a esta copa.
     *
     * Relación uno-a-muchos. Si se elimina la copa, las competiciones
     * no se borran: su copa_id se pone a null (nullOnDelete en migración).
     *
     * Usado por: CopaController::destroy() (impide borrar si tiene competiciones),
     *            AdminController::copas() (cuenta competiciones por copa),
     *            vistas de admin para mostrar pruebas de cada copa
     */
    public function competiciones(): HasMany
    {
        return $this->hasMany(Competicion::class, 'copa_id');
    }
}
