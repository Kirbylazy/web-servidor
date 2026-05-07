<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Ubicacion — Representa un rocódromo (instalación de escalada).
 *
 * Cada ubicación es un lugar físico donde se pueden celebrar competiciones.
 * Almacena las características del muro de escalada (dimensiones y líneas).
 *
 * Relaciones:
 *   - competiciones() → Competiciones que se celebran en este rocódromo
 *
 * Tabla: 'ubicacions' (Laravel pluraliza automáticamente)
 *
 * Gestionada por: UbicacionController (CRUD), AdminController::rocodromos() (listado)
 * Vistas: admin/rocodromos.blade.php (gestión CRUD)
 */
class Ubicacion extends Model
{
    /** @use HasFactory<\Database\Factories\UbicacionFactory> */
    use HasFactory;

    /**
     * Campos asignables masivamente.
     *
     * - name:      Nombre del rocódromo (ej: "Rocódromo El Muro")
     * - provincia:  Provincia donde está ubicado (Andalucía)
     * - direccion:  Dirección completa del rocódromo
     * - alto:       Altura del muro en metros (float, ej: 12.5)
     * - ancho:      Anchura del muro en metros (float, ej: 25.0)
     * - n_lineas:   Número de líneas/vías de escalada disponibles (integer)
     */
    protected $fillable = [
        'name',
        'provincia',
        'direccion',
        'alto',
        'ancho',
        'n_lineas',
    ];

    /**
     * Casts — Transformaciones de tipos para cálculos y comparaciones.
     *
     * - n_lineas → integer (número entero de líneas)
     * - alto/ancho → float (medidas con decimales en metros)
     */
    protected function casts(): array
    {
        return [
            'n_lineas' => 'integer',
            'alto' => 'float',
            'ancho' => 'float',
        ];
    }

    /**
     * Competiciones que se celebran en este rocódromo.
     *
     * Relación uno-a-muchos. Si se elimina la ubicación, se eliminan
     * en cascada las competiciones asociadas (cascadeOnDelete en migración).
     *
     * Usado por: UbicacionController::destroy() (impide borrar si tiene competiciones,
     *            aunque la BD lo haría en cascada, se valida por seguridad)
     */
    public function competiciones(): HasMany
    {
        return $this->hasMany(Competicion::class, 'ubicacion_id');
    }
}
