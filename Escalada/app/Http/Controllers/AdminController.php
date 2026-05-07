<?php

namespace App\Http\Controllers;

use App\Models\Competicion;
use App\Models\Copa;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AdminController — Controlador del panel de administración.
 *
 * Gestiona las secciones del panel admin: pruebas (competiciones), copas,
 * usuarios y rocódromos (ubicaciones). Solo accesible por usuarios con
 * rol 'admin' gracias al middleware 'rol:admin' definido en routes/web.php.
 *
 * El admin tiene acceso total a la app: puede crear/editar/eliminar
 * competiciones, copas, ubicaciones y usuarios, así como asignar árbitros
 * a competiciones y cambiar roles de usuarios.
 *
 * Rutas: todas bajo el prefijo 'admin/' y nombre 'admin.*'
 * Vistas: resources/views/admin/ (pruebas, copas, usuarios, rocodromos)
 */
class AdminController extends Controller
{
    /**
     * Listado de pruebas/competiciones con filtros.
     *
     * Muestra todas las competiciones con sus relaciones (árbitro, copa, ubicación).
     * Permite filtrar por:
     *   - 'proximas': solo competiciones futuras (por defecto)
     *   - 'este_año': competiciones del año actual
     *   - 'todas': sin filtro de fecha
     *   - copa_id: filtrar por copa específica o 'sin_copa' para las que no pertenecen a ninguna
     *
     * También carga las copas, ubicaciones y árbitros disponibles para los modales
     * de creación/edición de competiciones.
     *
     * Ruta: GET /admin/pruebas → admin.pruebas
     * Vista: admin/pruebas.blade.php
     */
    public function pruebas(Request $request)
    {
        // Obtener filtros de la query string (por defecto: solo próximas)
        $filtro  = $request->get('filtro', 'proximas');
        $copaId  = $request->get('copa_id', '');

        // Query base: cargar relaciones y ordenar por fecha
        $query = Competicion::with('arbitro', 'copa', 'ubicacion')
            ->orderBy('fecha_realizacion');

        // Aplicar filtro de fecha según selección del usuario
        match ($filtro) {
            'proximas' => $query->where('fecha_realizacion', '>=', now()),  // Solo futuras
            'este_año' => $query->whereYear('fecha_realizacion', now()->year), // Este año
            default    => null, // 'todas': no aplica filtro de fecha
        };

        // Filtro adicional por copa
        if ($copaId === 'sin_copa') {
            $query->whereNull('copa_id'); // Competiciones independientes (sin copa)
        } elseif ($copaId) {
            $query->where('copa_id', $copaId); // Competiciones de una copa específica
        }

        $competiciones = $query->get();

        // Datos para los selects de los modales de crear/editar competición
        $copas         = Copa::orderBy('temporada', 'desc')->orderBy('name')->get();
        $ubicaciones   = Ubicacion::orderBy('name')->get();
        // Solo usuarios con rol árbitro o admin pueden ser asignados como árbitros
        $arbitros      = User::whereIn('rol', ['arbitro', 'admin'])->orderBy('name')->get();

        return view('admin.pruebas', compact('competiciones', 'copas', 'ubicaciones', 'arbitros', 'filtro', 'copaId'));
    }

    /**
     * Listado de copas/torneos con conteo de competiciones.
     *
     * Muestra las copas ordenadas por temporada (desc) con la cantidad de
     * competiciones asociadas a cada una (withCount).
     * Permite filtrar por 'este_año' o ver 'todas'.
     *
     * Ruta: GET /admin/copas → admin.copas
     * Vista: admin/copas.blade.php
     */
    public function copas(Request $request)
    {
        $filtro = $request->get('filtro', 'todas');

        // withCount('competiciones') añade el atributo competiciones_count a cada copa
        $query = Copa::withCount('competiciones')
            ->orderBy('temporada', 'desc')
            ->orderBy('name');

        // Filtrar solo copas de la temporada actual si se solicita
        if ($filtro === 'este_año') {
            $query->where('temporada', now()->year);
        }

        $copas = $query->get();

        return view('admin.copas', compact('copas', 'filtro'));
    }

    /**
     * Listado de usuarios con filtros por rol y búsqueda.
     *
     * Muestra todos los usuarios excepto el admin actual (no puede editarse a sí mismo).
     * Permite filtrar por rol y buscar por nombre o DNI.
     *
     * Ruta: GET /admin/usuarios → admin.usuarios
     * Vista: admin/usuarios.blade.php
     */
    public function usuarios(Request $request)
    {
        $rolFiltro = $request->get('rol', 'todos');
        $buscar    = $request->get('buscar', '');

        // Excluir al usuario actual para que no pueda editarse/eliminarse a sí mismo
        $query = User::where('id', '!=', auth()->id())->orderBy('name');

        // Filtrar por rol si se seleccionó uno específico
        if ($rolFiltro !== 'todos') {
            $query->where('rol', $rolFiltro);
        }

        // Búsqueda por nombre O DNI (coincidencia parcial con LIKE)
        if ($buscar) {
            $query->where(function ($q) use ($buscar) {
                $q->where('name', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        $usuarios = $query->get();

        return view('admin.usuarios', compact('usuarios', 'rolFiltro', 'buscar'));
    }

    /**
     * Listado de rocódromos/ubicaciones con conteo de competiciones.
     *
     * Muestra todas las ubicaciones con cuántas competiciones tiene cada una.
     * El conteo se usa en la vista para impedir eliminar ubicaciones con competiciones.
     *
     * Ruta: GET /admin/rocodromos → admin.rocodromos
     * Vista: admin/rocodromos.blade.php
     */
    public function rocodromos()
    {
        // withCount('competiciones') añade competiciones_count a cada ubicación
        $ubicaciones = Ubicacion::withCount('competiciones')->orderBy('name')->get();
        return view('admin.rocodromos', compact('ubicaciones'));
    }

    /**
     * Actualizar los datos de un usuario desde el panel admin.
     *
     * Valida todos los campos editables del perfil, incluyendo el rol.
     * El email y DNI deben ser únicos (excluyendo al propio usuario).
     *
     * Ruta: PATCH /admin/usuarios/{user} → admin.usuarios.update
     * Vista: modal de edición en admin/usuarios.blade.php
     */
    public function actualizarUsuario(Request $request, User $user)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'dni'              => ['nullable', 'string', 'max:20', Rule::unique(User::class)->ignore($user->id)],
            'fecha_nacimiento' => 'nullable|date',
            'provincia'        => 'nullable|string|max:100',
            'talla'            => 'nullable|in:XS,S,M,L,XL,XXL',
            'genero'           => 'nullable|in:M,F,otro',
            'rol'              => 'required|in:competidor,entrenador,arbitro,admin',
        ]);

        // Actualizar solo los campos enviados del formulario
        $user->update($request->only([
            'name', 'email', 'dni', 'fecha_nacimiento', 'provincia', 'talla', 'genero', 'rol',
        ]));

        return back()->with('status', "Usuario «{$user->name}» actualizado correctamente.");
    }

    /**
     * Eliminar la cuenta de un usuario.
     *
     * Impide que el admin se elimine a sí mismo (debe hacerlo desde su perfil).
     * Al eliminar un usuario, se eliminan en cascada sus inscripciones,
     * vínculos entrenador-competidor, etc. (definido en las migraciones).
     *
     * Ruta: DELETE /admin/usuarios/{user} → admin.usuarios.destroy
     */
    public function destroyUsuario(User $user)
    {
        // Protección: no permitir auto-eliminación desde el panel admin
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta desde aquí.');
        }

        $nombre = $user->name;
        $user->delete();

        return back()->with('status', "Usuario «{$nombre}» eliminado.");
    }

    /**
     * Cambiar el rol de un usuario (versión simplificada).
     *
     * Nota: no permite asignar rol 'admin' (solo competidor, arbitro, entrenador).
     * Para cambios completos de perfil incluyendo rol admin, usar actualizarUsuario().
     *
     * Ruta: PATCH /admin/usuarios/{user}/rol → admin.usuarios.rol
     */
    public function updateRol(Request $request, User $user)
    {
        $request->validate(['rol' => ['required', 'in:competidor,arbitro,entrenador']]);
        $user->update(['rol' => $request->rol]);
        return back()->with('status', "Rol de {$user->name} actualizado a {$request->rol}.");
    }

    /**
     * Asignar o desasignar un árbitro a una competición.
     *
     * Verifica que el usuario seleccionado tenga rol de árbitro o superior
     * (un admin también puede arbitrar gracias a la jerarquía de roles).
     * Si se envía arbitro_id vacío, se desasigna el árbitro actual.
     *
     * Ruta: PATCH /admin/competiciones/{competicion}/arbitro → admin.competiciones.arbitro
     * Relacionado: ArbitroController usa competicionesArbitradas() para listar
     *              las competiciones asignadas al árbitro logueado.
     */
    public function asignarArbitro(Request $request, Competicion $competicion)
    {
        $request->validate(['arbitro_id' => 'nullable|exists:users,id']);

        // Si se seleccionó un árbitro, verificar que tiene el rol adecuado
        if ($request->arbitro_id) {
            $arbitro = User::findOrFail($request->arbitro_id);
            // isArbitro() devuelve true para roles arbitro(3) y admin(4)
            if (!$arbitro->isArbitro()) {
                return back()->with('error', "'{$arbitro->name}' no tiene rol de árbitro o superior.");
            }
        }

        // Asignar o quitar el árbitro (null si no se seleccionó ninguno)
        $competicion->update(['arbitro_id' => $request->arbitro_id ?: null]);
        return back()->with('status', "Árbitro de '{$competicion->name}' actualizado.");
    }
}
