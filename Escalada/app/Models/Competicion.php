<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;

/**
 * Modelo Competicion — Representa una prueba/evento de escalada.
 *
 * Cada competición es un evento concreto (ej: "1ª Prueba de Bloque, Sevilla")
 * que pertenece opcionalmente a una Copa (torneo/serie) y se celebra en
 * una Ubicación (rocódromo) específica.
 *
 * Estructura jerárquica: Copa (1) → tiene muchas → Competiciones (N) → en → Ubicación (1)
 *
 * Tipos de competición: 'bloque', 'dificultad', 'velocidad'
 * Una competición puede ser marcada como 'campeonato' (la final de la copa).
 *
 * Relaciones:
 *   - copa()          → Copa a la que pertenece (nullable: puede ser independiente)
 *   - ubicacion()     → Rocódromo donde se celebra
 *   - arbitro()       → Usuario (rol árbitro) asignado para gestionar inscripciones
 *   - usuarios()      → Usuarios inscritos (pivot legacy: competicions_users)
 *   - inscripciones() → Inscripciones formales con verificación de documentos
 *
 * Tabla: 'competicions' (nombre forzado con $table porque Laravel pluraliza mal el español)
 *
 * Gestionada por: CompeticionController (CRUD), AdminController (asignar árbitro),
 *                 ArbitroController (gestión de inscripciones por categoría)
 */
class Competicion extends Model
{
    /** @use HasFactory<\Database\Factories\CompeticionFactory> */
    use HasFactory;

    /**
     * Nombre de la tabla en la BD.
     * Se especifica manualmente porque Laravel pluralizaría 'competicions' de forma incorrecta.
     */
    protected $table = 'competicions';

    /**
     * Campos asignables masivamente.
     *
     * - copa_id:           FK a la copa/torneo al que pertenece (nullable)
     * - arbitro_id:        FK al usuario árbitro asignado (nullable, asignado por admin)
     * - ubicacion_id:      FK al rocódromo donde se celebra
     * - name:              Nombre descriptivo de la prueba
     * - provincia:         Provincia donde se celebra (Andalucía)
     * - fecha_realizacion: Fecha y hora de inicio
     * - fecha_fin:         Fecha y hora de fin (nullable, puede ser el mismo día)
     * - tipo:              Tipo de escalada: 'bloque', 'dificultad', 'velocidad'
     * - campeonato:        Boolean — si es la prueba final/campeonato de la copa
     * - categorias:        JSON array de categorías habilitadas (ej: ['U15','U17','Absoluta'])
     */
    protected $fillable = [
        'copa_id',
        'arbitro_id',
        'ubicacion_id',
        'name',
        'provincia',
        'fecha_realizacion',
        'fecha_fin',
        'tipo',
        'campeonato',
        'categorias',
    ];

    /**
     * Casts — Transformaciones automáticas de tipos.
     *
     * - fecha_realizacion/fecha_fin → Carbon para formateo y comparaciones de fecha
     * - campeonato → boolean (en BD es tinyint)
     * - categorias → array (en BD es JSON) — permite usar como array PHP directamente
     */
    protected function casts(): array
    {
        return [
            'fecha_realizacion' => 'datetime',
            'fecha_fin'         => 'datetime',
            'campeonato'        => 'boolean',
            'categorias'        => 'array',
        ];
    }

    /**
     * Lista estática de categorías base disponibles para asignar a una competición.
     *
     * Se usa en las vistas de admin (admin/pruebas.blade.php) para mostrar checkboxes
     * al crear/editar una competición. Cada categoría se combina con género
     * (Masculino/Femenino) en el modelo Inscripcion.
     *
     * Las categorías son por edad: U9(7-8), U11(9-10), U13(11-12), U15(13-14),
     * U17(15-16), U19(17-18), Absoluta(19-34), Veterana(35+), Promoción(manual).
     */
    public static function categoriasDisponibles(): array
    {
        return ['U9', 'U11', 'U13', 'U15', 'U17', 'U19', 'Absoluta', 'Veterana', 'Promoción'];
    }

    /**
     * Copa/torneo al que pertenece esta competición.
     *
     * Nullable: una competición puede existir sin copa (ej: prueba de velocidad suelta).
     * Si se elimina la copa, este campo se pone a null (nullOnDelete en migración).
     *
     * Usado por: vistas para mostrar "Copa de Bloque 2026", filtros en admin
     */
    public function copa(): BelongsTo
    {
        return $this->belongsTo(Copa::class, 'copa_id');
    }

    /**
     * Rocódromo/ubicación donde se celebra la competición.
     *
     * Relación obligatoria (no nullable). Si se elimina la ubicación,
     * se eliminan en cascada las competiciones asociadas (cascadeOnDelete).
     *
     * Usado por: vistas para mostrar nombre y dirección del rocódromo
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    /**
     * Árbitro asignado a esta competición.
     *
     * Es un User con rol 'arbitro' (o superior). Nullable hasta que el admin lo asigne.
     * El árbitro asignado puede ver y gestionar las inscripciones de esta competición.
     *
     * Asignado por: AdminController::asignarArbitro()
     * Usado por: ArbitroController::competicion(), ArbitroController::categoria()
     */
    public function arbitro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbitro_id');
    }

    /**
     * Usuarios inscritos en esta competición (relación pivot LEGACY).
     *
     * Usa la tabla pivot 'competicions_users'. Esta es la inscripción básica
     * que se usa cuando un entrenador inscribe a su equipo rápidamente.
     * Para el flujo completo con verificación de documentos, ver inscripciones().
     *
     * Columnas pivot:
     *   - tipoDato: tipo de dato adicional (ej: 'resultado')
     *   - dato: valor del dato
     *
     * Inversa de: User::competiciones()
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'competicions_users', 'competicion_id', 'user_id')
            ->withPivot('tipoDato', 'dato')
            ->withTimestamps();
    }

    /**
     * Inscripciones formales en esta competición (con verificación de documentos).
     *
     * Relación uno-a-muchos con la tabla 'inscripciones'.
     * Cada inscripción pasa por el flujo: borrador → pendiente → aprobada/rechazada.
     *
     * Usado por: ArbitroController (gestión por categoría),
     *            InscripcionController (flujo de inscripción del competidor),
     *            CompeticionController::destroy() (se eliminan en cascada)
     */
    public function inscripciones()
    {
        return $this->hasMany(\App\Models\Inscripcion::class);
    }
}
