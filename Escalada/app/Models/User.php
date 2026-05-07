<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modelo User — Representa a cualquier usuario de la aplicación de escalada.
 *
 * Es el modelo central de la app. Cada usuario tiene un ROL que determina
 * qué puede hacer y a qué rutas puede acceder:
 *   - competidor (nivel 1): puede inscribirse en competiciones, subir documentos.
 *   - entrenador (nivel 2): hereda permisos de competidor + gestiona equipo de competidores.
 *   - arbitro   (nivel 3): hereda permisos anteriores + valida inscripciones y documentos.
 *   - admin     (nivel 4): acceso total — CRUD de copas, competiciones, usuarios, rocódromos.
 *
 * Relaciones principales:
 *   - competiciones()          → Competiciones en las que participa (pivot: competicions_users)
 *   - competidoresAceptados()  → (como entrenador) competidores vinculados y aceptados
 *   - competidoresPendientes() → (como entrenador) solicitudes de vínculo pendientes
 *   - entrenadores()           → (como competidor) su entrenador actual (0 o 1)
 *   - competicionesArbitradas()→ (como árbitro) competiciones que arbitra
 *   - inscripciones()          → Inscripciones formales del usuario (tabla inscripciones)
 *   - licenciaValidaciones()   → Validaciones de licencia federativa del usuario
 *
 * Usado por: casi todos los controladores, middleware CheckRol, seeders, factories.
 * Vista principal: dashboard.blade.php redirige según el rol del usuario.
 */
class User extends Authenticatable
{
    /**
     * HasFactory: permite crear usuarios de prueba con UserFactory (database/factories/UserFactory.php).
     * Notifiable: habilita el sistema de notificaciones de Laravel (canal 'database').
     *   Se usa para notificar solicitudes de entrenador y cambios en inscripciones.
     */
    use HasFactory, Notifiable;

    /**
     * Campos asignables masivamente (se pueden rellenar con create() o fill()).
     *
     * - name:             Nombre completo del usuario
     * - email:            Email único, usado para login
     * - password:         Contraseña (se hashea automáticamente por el cast 'hashed')
     * - dni:              DNI/NIE único — usado por entrenadores para buscar competidores
     * - fecha_nacimiento: Fecha de nacimiento — se usa para calcular la categoría de competición
     * - provincia:        Provincia del usuario (Andalucía)
     * - talla:            Talla de camiseta (XS, S, M, L, XL, XXL)
     * - genero:           Género (M/F/otro) — determina la categoría (Masculino/Femenino)
     * - rol:              Rol del usuario (competidor, entrenador, arbitro, admin)
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'dni',
        'fecha_nacimiento',
        'provincia',
        'talla',
        'genero',
        'rol',
    ];

    /**
     * Campos ocultos en serialización JSON (no se envían al frontend).
     * Protege la contraseña y el token de "remember me".
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts — Transformaciones automáticas de tipos al leer/escribir en BD.
     *
     * - email_verified_at → Carbon (objeto fecha) para comparaciones fáciles
     * - password          → 'hashed': Laravel hashea automáticamente al asignar un valor
     * - fecha_nacimiento  → Carbon: permite ->format('Y'), ->year, etc.
     *                        Se usa en Inscripcion::calcularCategoria() para determinar la edad
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_nacimiento' => 'datetime',
        ];
    }

    /**
     * Relación muchos-a-muchos: competiciones en las que participa el usuario.
     *
     * Usa la tabla pivot 'competicions_users' (migración: competicion_user.php).
     * Columnas pivot:
     *   - tipoDato: tipo de dato adicional almacenado (ej: 'resultado', 'posicion')
     *   - dato:     valor del dato
     *
     * Esta es la relación LEGACY de inscripción básica. El flujo completo de
     * inscripción con verificación de documentos usa la tabla 'inscripciones'
     * (ver relación inscripciones() más abajo).
     *
     * Usado por: EntrenadorController::inscribir(), dashboard del entrenador
     */
    public function competiciones(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Competicion::class, 'competicions_users', 'user_id', 'competicion_id')
            ->withPivot('tipoDato', 'dato')
            ->withTimestamps();
    }

    /**
     * (Como ENTRENADOR) Devuelve los competidores que han ACEPTADO el vínculo.
     *
     * Usa la tabla pivot 'entrenador_competidor' filtrando estado = 'accepted'.
     * Es una relación User-a-User (self-referencing many-to-many).
     *
     * Usado por: dashboard del entrenador, EntrenadorController::inscribir(),
     *            EntrenadorController::eliminarCompetidor()
     */
    public function competidoresAceptados(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'entrenador_competidor', 'entrenador_id', 'competidor_id')
            ->wherePivot('estado', 'accepted')
            ->withPivot('estado', 'created_at');
    }

    /**
     * (Como ENTRENADOR) Devuelve solicitudes de vínculo aún PENDIENTES de respuesta.
     *
     * El entrenador busca competidores por DNI y envía una solicitud.
     * El competidor la ve como notificación en su dashboard y puede aceptar/rechazar.
     *
     * Usado por: dashboard del entrenador (muestra solicitudes pendientes)
     */
    public function competidoresPendientes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'entrenador_competidor', 'entrenador_id', 'competidor_id')
            ->wherePivot('estado', 'pending')
            ->withPivot('estado', 'created_at');
    }

    /**
     * (Como COMPETIDOR) Devuelve el entrenador aceptado de este competidor.
     *
     * Un competidor solo puede tener UN entrenador a la vez (restricción lógica).
     * Devuelve una colección con 0 o 1 elemento.
     *
     * Usado por: dashboard del competidor (muestra "Tu entrenador: X"),
     *            NotificacionController::desvincular()
     */
    public function entrenadores(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'entrenador_competidor', 'competidor_id', 'entrenador_id')
            ->wherePivot('estado', 'accepted');
    }

    /**
     * Convierte el rol del usuario a un nivel numérico para la jerarquía de permisos.
     *
     * La jerarquía es ACUMULATIVA: un admin (nivel 4) puede hacer todo lo que
     * hace un árbitro (3), entrenador (2) y competidor (1).
     * Esto se usa tanto aquí (métodos isX()) como en el middleware CheckRol.
     *
     * @return int Nivel numérico del rol (0 si no reconocido)
     */
    protected function rolNivel(): int
    {
        return match($this->rol) {
            'admin'      => 4,
            'arbitro'    => 3,
            'entrenador' => 2,
            'competidor' => 1,
            default      => 0,
        };
    }

    /**
     * Métodos de comprobación de rol con herencia jerárquica.
     *
     * isAdmin():      true solo para admin
     * isArbitro():    true para arbitro Y admin
     * isEntrenador(): true para entrenador, arbitro Y admin
     * isCompetidor(): true para TODOS los roles
     *
     * Usado por: web.php (dashboard routing), vistas (mostrar/ocultar elementos),
     *            layouts/app.blade.php (mostrar botón admin), ArbitroController
     */
    public function isAdmin(): bool      { return $this->rolNivel() >= 4; }
    public function isArbitro(): bool    { return $this->rolNivel() >= 3; }
    public function isEntrenador(): bool { return $this->rolNivel() >= 2; }
    public function isCompetidor(): bool { return $this->rolNivel() >= 1; }

    /**
     * (Como ÁRBITRO) Competiciones asignadas a este usuario como árbitro.
     *
     * Relación uno-a-muchos: un árbitro puede tener varias competiciones asignadas.
     * El admin asigna árbitros desde AdminController::asignarArbitro().
     *
     * Usado por: ArbitroController::panel() (lista competiciones del árbitro)
     */
    public function competicionesArbitradas()
    {
        return $this->hasMany(\App\Models\Competicion::class, 'arbitro_id');
    }

    /**
     * Inscripciones formales del usuario en competiciones.
     *
     * Relación uno-a-muchos con la tabla 'inscripciones'.
     * Cada inscripción tiene un flujo: subir licencia → subir pago → enviar → árbitro valida.
     *
     * Usado por: dashboard del competidor (estado de inscripciones),
     *            InscripcionController, ArbitroController
     */
    public function inscripciones()
    {
        return $this->hasMany(\App\Models\Inscripcion::class);
    }

    /**
     * Validaciones de licencia federativa de este usuario.
     *
     * Cada vez que un árbitro valida la licencia de un competidor, se crea un registro.
     * Si la validación es 'valida' (anual), el competidor no necesita volver a subir
     * la licencia hasta fin de año. Si es 'valida_dia', solo vale para esa competición.
     *
     * Usado por: ArbitroController::validarLicencia(), InscripcionController::show()
     */
    public function licenciaValidaciones()
    {
        return $this->hasMany(\App\Models\LicenciaValidacion::class);
    }
}
