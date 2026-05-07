<?php

namespace App\Http\Controllers;

use App\Models\Competicion;
use App\Models\Inscripcion;
use App\Models\LicenciaValidacion;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Notifications\InscripcionActualizadaNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ArbitroController — Controlador del panel del árbitro.
 *
 * Gestiona todo lo relacionado con la labor del árbitro en competiciones:
 *   - Ver competiciones asignadas
 *   - Revisar inscripciones por categoría
 *   - Validar documentos (licencia federativa y justificante de pago)
 *   - Cambiar categorías de competidores
 *   - Servir documentos subidos por los competidores
 *
 * Un árbitro también puede actuar como entrenador y deportista (jerarquía de roles),
 * por lo que tiene paneles adicionales para esas funciones.
 *
 * Solo accesible por usuarios con rol 'arbitro' o 'admin' (middleware 'rol:arbitro').
 * Además, cada acción verifica que el árbitro esté asignado a la competición
 * correspondiente mediante checkArbitro()/checkArbitroPorCompeticion().
 *
 * Rutas: bajo el prefijo 'arbitro/' y nombre 'arbitro.*'
 * Vistas: resources/views/arbitro/
 */
class ArbitroController extends Controller
{
    /**
     * Panel principal del árbitro: lista de competiciones asignadas.
     *
     * Muestra las competiciones donde este usuario es el arbitro_id asignado.
     * Desde aquí el árbitro puede acceder a gestionar las inscripciones de cada una.
     *
     * Ruta: GET /arbitro/ → arbitro.panel
     * Vista: arbitro/panel/arbitro.blade.php
     * Relación: usa User::competicionesArbitradas() (hasMany por arbitro_id)
     */
    public function panel()
    {
        // Cargar competiciones asignadas con sus relaciones para mostrar en la tabla
        $competicionesArbitradas = auth()->user()
            ->competicionesArbitradas()
            ->with('copa', 'ubicacion')
            ->get();

        return view('arbitro.panel.arbitro', compact('competicionesArbitradas'));
    }

    /**
     * Panel de entrenador (accesible porque el árbitro hereda permisos de entrenador).
     *
     * Funcionalidad idéntica al dashboard del entrenador: gestionar equipo,
     * buscar competidores por DNI, enviar solicitudes, inscribir en competiciones.
     *
     * Ruta: GET /arbitro/entrenador → arbitro.panel.entrenador
     * Vista: arbitro/panel/entrenador.blade.php
     * Relacionado: EntrenadorController maneja las acciones POST de este panel
     */
    public function panelEntrenador()
    {
        $user = auth()->user();
        // Competidores vinculados y aceptados del equipo
        $competidores = $user->competidoresAceptados()->get();
        // Solicitudes de vínculo pendientes de respuesta
        $pendientes   = $user->competidoresPendientes()->get();

        // Búsqueda de competidor por DNI (formulario GET en la vista)
        $userBuscado = null;
        if (request('dni')) {
            $userBuscado = User::where('dni', request('dni'))
                ->where('rol', 'competidor')
                ->first();
        }

        // Competiciones futuras disponibles para inscribir al equipo
        $competiciones = Competicion::where('fecha_realizacion', '>=', now())
            ->orderBy('fecha_realizacion')
            ->get();

        return view('arbitro.panel.entrenador', compact('competidores', 'pendientes', 'userBuscado', 'competiciones'));
    }

    /**
     * Panel de deportista (el árbitro como competidor).
     *
     * Muestra las competiciones en las que el árbitro está inscrito como participante
     * (a través de la tabla pivot competicions_users, NO inscripciones).
     *
     * Ruta: GET /arbitro/deportista → arbitro.panel.deportista
     * Vista: arbitro/panel/deportista.blade.php
     */
    public function panelDeportista()
    {
        // Inscripciones del árbitro como participante (relación legacy competicions_users)
        $misInscripciones = auth()->user()->competiciones()->with('copa')->get();

        return view('arbitro.panel.deportista', compact('misInscripciones'));
    }

    /**
     * Dashboard de una competición: resumen de inscripciones agrupadas por categoría.
     *
     * Muestra tarjetas con totales generales y una tabla con el desglose por categoría
     * (cuántas inscripciones hay pendientes, aprobadas y rechazadas en cada categoría).
     * Solo se muestran inscripciones que ya fueron enviadas (no borradores).
     *
     * Verifica que el usuario sea el árbitro asignado a esta competición.
     *
     * Ruta: GET /arbitro/competicion/{competicion} → arbitro.competicion
     * Vista: arbitro/competicion.blade.php
     */
    public function competicion(Competicion $competicion)
    {
        // Verificar autorización: solo el árbitro asignado puede acceder
        $this->checkArbitro($competicion);

        // Cargar inscripciones enviadas (excluir borradores) con datos del competidor
        $inscripciones = Inscripcion::where('competicion_id', $competicion->id)
            ->whereIn('estado', ['pendiente', 'aprobada', 'rechazada'])
            ->with('user')
            ->get();

        // Agrupar por categoría y calcular conteos por estado para cada una
        $categorias = $inscripciones
            ->groupBy('categoria')
            ->map(fn($grupo) => [
                'total'     => $grupo->count(),
                'pendiente' => $grupo->where('estado', 'pendiente')->count(),
                'aprobada'  => $grupo->where('estado', 'aprobada')->count(),
                'rechazada' => $grupo->where('estado', 'rechazada')->count(),
            ])
            ->sortKeys(); // Ordenar categorías alfabéticamente

        // Totales generales para las tarjetas de resumen
        $totales = [
            'total'     => $inscripciones->count(),
            'pendiente' => $inscripciones->where('estado', 'pendiente')->count(),
            'aprobada'  => $inscripciones->where('estado', 'aprobada')->count(),
            'rechazada' => $inscripciones->where('estado', 'rechazada')->count(),
        ];

        return view('arbitro.competicion', compact('competicion', 'categorias', 'totales'));
    }

    /**
     * Lista detallada de competidores de una categoría específica.
     *
     * Muestra una tabla con cada inscripción de la categoría, permitiendo:
     *   - Ver y validar documentos (licencia y pago)
     *   - Cambiar la categoría del competidor
     *   - Ver si el competidor tiene licencia anual vigente
     *
     * Ruta: GET /arbitro/competicion/{competicion}/categoria/{categoria} → arbitro.categoria
     * Vista: arbitro/categoria.blade.php
     */
    public function categoria(Competicion $competicion, string $categoria)
    {
        $this->checkArbitro($competicion);

        // Inscripciones de esta categoría, ordenadas por antigüedad (primera en llegar primero)
        $inscripciones = Inscripcion::where('competicion_id', $competicion->id)
            ->where('categoria', $categoria)
            ->whereIn('estado', ['pendiente', 'aprobada', 'rechazada'])
            ->with('user')
            ->orderBy('created_at')
            ->get();

        // Consultar licencias anuales vigentes de los competidores de esta categoría.
        // Se usa para mostrar un indicador visual si la licencia ya fue validada anualmente,
        // lo que significa que no necesita re-validación en esta competición.
        $licenciasAnuales = LicenciaValidacion::whereIn('user_id', $inscripciones->pluck('user_id'))
            ->where('tipo', 'valida')
            ->where('valida_hasta', '>=', now()->toDateString())
            ->get()
            ->keyBy('user_id'); // Indexar por user_id para acceso rápido en la vista

        return view('arbitro.categoria', compact('competicion', 'categoria', 'inscripciones', 'licenciasAnuales'));
    }

    /**
     * Servir un documento subido por un competidor (licencia o pago).
     *
     * El árbitro necesita ver los documentos para poder validarlos.
     * Los documentos están almacenados en storage/app/public/inscripciones/.
     *
     * Ruta: GET /arbitro/inscripcion/{inscripcion}/documento/{tipo} → arbitro.ver_documento
     * Tipo: 'licencia' o 'pago'
     */
    public function verDocumento(Inscripcion $inscripcion, string $tipo)
    {
        // Verificar que el árbitro está asignado a la competición de esta inscripción
        $this->checkArbitroPorCompeticion($inscripcion->competicion_id);

        // Seleccionar la ruta del documento según el tipo solicitado
        $path = match ($tipo) {
            'licencia' => $inscripcion->licencia_path,
            'pago'     => $inscripcion->pago_path,
            default    => abort(404),
        };

        // Verificar que el archivo existe en el disco
        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404, 'Documento no encontrado.');
        }

        // Devolver el archivo como respuesta HTTP (se muestra en el navegador)
        return Storage::disk('public')->response($path);
    }

    /**
     * Validar un documento (licencia o pago) de una inscripción.
     *
     * Este es el método central del flujo de verificación del árbitro.
     * El árbitro puede decidir para cada documento:
     *   - 'valida':     Documento válido (si es licencia: válida todo el año)
     *   - 'valida_dia': Documento válido solo para esta competición
     *   - 'no_valida':  Documento no válido (requiere motivo obligatorio)
     *
     * Después de validar un documento, se recalcula el estado global de la inscripción
     * (Inscripcion::recalcularEstado). Si el estado cambia a aprobada o rechazada,
     * se envía una notificación al competidor.
     *
     * Para licencias validadas como 'valida' o 'valida_dia', se crea/actualiza un registro
     * en licencia_validaciones para rastrear la validez.
     *
     * Soporta respuestas JSON (para AJAX desde la vista) y redirección normal.
     *
     * Ruta: PATCH /arbitro/inscripcion/{inscripcion}/validar → arbitro.validar_licencia
     * Vista: modal de verificación en arbitro/categoria.blade.php (usa fetch/AJAX)
     */
    public function validarLicencia(Request $request, Inscripcion $inscripcion)
    {
        $this->checkArbitroPorCompeticion($inscripcion->competicion_id);

        // Validar datos del formulario
        $request->validate([
            'tipo'     => 'required|in:licencia,pago',           // Qué documento se valida
            'decision' => 'required|in:valida,valida_dia,no_valida', // Decisión del árbitro
            'motivo'   => 'required_if:decision,no_valida|nullable|string|max:500', // Motivo obligatorio si rechaza
        ]);

        $arbitro     = auth()->user();
        $competidor  = $inscripcion->user;     // El competidor dueño de la inscripción
        $competicion = $inscripcion->competicion;
        $tipo        = $request->tipo;

        if ($tipo === 'licencia') {
            // Actualizar estado de la licencia en la inscripción
            $inscripcion->licencia_estado = $request->decision;
            // Solo guardar motivo si es rechazada
            $inscripcion->licencia_motivo = $request->decision === 'no_valida' ? $request->motivo : null;

            // Si la licencia es válida, registrar en licencia_validaciones
            // Esto permite que el competidor no tenga que re-subir la licencia en futuras competiciones
            if ($request->decision !== 'no_valida') {
                // 'valida' → válida hasta fin de año (31 dic)
                // 'valida_dia' → válida solo hasta la fecha de esta competición
                $validaHasta = $request->decision === 'valida'
                    ? now()->endOfYear()->toDateString()
                    : $competicion->fecha_realizacion->toDateString();

                // updateOrCreate: actualiza si ya existe un registro para este usuario+competición
                LicenciaValidacion::updateOrCreate(
                    ['user_id' => $competidor->id, 'competicion_id' => $competicion->id],
                    [
                        'validada_por' => $arbitro->id,
                        'tipo'         => $request->decision,
                        'valida_hasta' => $validaHasta,
                    ]
                );
            }
        } else {
            // Actualizar estado del justificante de pago en la inscripción
            $inscripcion->pago_estado = $request->decision;
            $inscripcion->pago_motivo = $request->decision === 'no_valida' ? $request->motivo : null;
        }

        // Guardar estado anterior para comparar después del recálculo
        $estadoAnterior = $inscripcion->estado;
        // Recalcular estado global: si ambos docs son válidos → aprobada, si alguno no → rechazada
        $inscripcion->recalcularEstado();

        // Enviar notificación al competidor solo si el estado global cambió a un estado final
        if ($inscripcion->estado !== $estadoAnterior &&
            in_array($inscripcion->estado, ['aprobada', 'rechazada'])) {

            // Construir mensaje de motivo para la notificación
            $motivo = $inscripcion->estado === 'rechazada'
                ? ($inscripcion->licencia_estado === 'no_valida'
                    ? 'Licencia: ' . $inscripcion->licencia_motivo
                    : 'Justificante de pago: ' . $inscripcion->pago_motivo)
                : null;

            // Notificación via canal 'database' → aparece en el dashboard del competidor
            $competidor->notify(new InscripcionActualizadaNotification($inscripcion, $motivo));
        }

        $etiqueta = $tipo === 'licencia' ? 'Licencia' : 'Justificante de pago';

        // Respuesta JSON para peticiones AJAX (desde el modal de la vista)
        if ($request->expectsJson()) {
            return response()->json([
                'success'         => true,
                'message'         => "$etiqueta verificado correctamente.",
                'estado'          => $inscripcion->estado,
                'licencia_estado' => $inscripcion->licencia_estado,
                'pago_estado'     => $inscripcion->pago_estado,
            ]);
        }

        // Respuesta normal: redirigir de vuelta con mensaje flash
        return back()->with('success', "$etiqueta verificado correctamente.");
    }

    /**
     * Cambiar la categoría de un competidor manualmente.
     *
     * El árbitro puede reasignar la categoría de cualquier inscripción,
     * por ejemplo para mover a un competidor a "Promoción" o corregir
     * una categoría mal calculada.
     *
     * Valida que la categoría sea una de las opciones válidas definidas
     * en Inscripcion::listaCategorias() (ej: "Masculino U17", "Mixta Promoción").
     *
     * Ruta: PATCH /arbitro/inscripcion/{inscripcion}/categoria → arbitro.cambiar_categoria
     * Vista: dropdown en arbitro/categoria.blade.php
     */
    public function cambiarCategoria(Request $request, Inscripcion $inscripcion)
    {
        $this->checkArbitroPorCompeticion($inscripcion->competicion_id);

        // Validar contra la lista completa de categorías válidas del sistema
        $request->validate([
            'categoria' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\Inscripcion::listaCategorias())],
        ]);

        $inscripcion->update(['categoria' => $request->categoria]);

        return back()->with('success', "Categoría cambiada a «{$request->categoria}».");
    }

    /**
     * Verificar que el usuario actual es el árbitro asignado a la competición.
     *
     * Aborta con 403 si el árbitro no está asignado. Cada acción sobre
     * inscripciones/documentos llama a este método o a checkArbitroPorCompeticion().
     *
     * @param Competicion $competicion La competición a verificar
     */
    private function checkArbitro(Competicion $competicion): void
    {
        if ($competicion->arbitro_id !== auth()->id()) {
            abort(403, 'No eres el árbitro asignado a esta competición.');
        }
    }

    /**
     * Verificar autorización usando el ID de competición (busca la competición primero).
     *
     * Útil cuando se trabaja con inscripciones que tienen competicion_id pero
     * no el objeto Competicion cargado.
     *
     * @param int $competicionId ID de la competición a verificar
     */
    private function checkArbitroPorCompeticion(int $competicionId): void
    {
        $this->checkArbitro(Competicion::findOrFail($competicionId));
    }
}
