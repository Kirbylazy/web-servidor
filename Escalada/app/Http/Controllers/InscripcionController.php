<?php

namespace App\Http\Controllers;

use App\Models\Competicion;
use App\Models\Inscripcion;
use App\Models\LicenciaValidacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * InscripcionController — Flujo de inscripción del competidor en competiciones.
 *
 * Gestiona el proceso completo de inscripción con verificación de documentos:
 *   1. El competidor accede al detalle de la competición (show)
 *   2. Sube su licencia federativa (uploadLicencia)
 *   3. Sube su justificante de pago (uploadPago)
 *   4. Envía la inscripción a revisión del árbitro (store)
 *
 * Si el competidor tiene una licencia anual vigente (validada previamente
 * por un árbitro como 'valida'), no necesita volver a subir la licencia.
 *
 * Estados de la inscripción:
 *   - borrador:  se crea automáticamente al subir el primer documento
 *   - pendiente: el competidor ha enviado la inscripción, espera revisión
 *   - aprobada:  el árbitro ha validado ambos documentos
 *   - rechazada: algún documento no es válido
 *
 * Accesible por cualquier usuario autenticado (middleware 'auth'), pero
 * las acciones de upload y envío están restringidas a rol 'competidor'
 * mediante soloCompetidor().
 *
 * Rutas: /competiciones/{competicion} y /inscripciones/{competicion}/*
 * Vista: competidor/competicion-show.blade.php
 */
class InscripcionController extends Controller
{
    /**
     * Mostrar el detalle de una competición con el formulario de inscripción.
     *
     * Carga la inscripción existente del usuario (si la tiene), comprueba
     * si tiene licencia anual vigente, y marca como leída cualquier
     * notificación pendiente sobre esta competición.
     *
     * Ruta: GET /competiciones/{competicion} → competiciones.show
     * Vista: competidor/competicion-show.blade.php
     */
    public function show(Competicion $competicion)
    {
        $user = auth()->user();

        // Buscar inscripción existente del usuario en esta competición
        $inscripcion = Inscripcion::where('user_id', $user->id)
            ->where('competicion_id', $competicion->id)
            ->first();

        // Comprobar si el competidor tiene licencia anual vigente.
        // Si la tiene, la vista no muestra el formulario de subida de licencia
        // y marca automáticamente la licencia como válida al enviar.
        $licenciaAnual = $user->rol === 'competidor'
            ? LicenciaValidacion::validezAnual($user->id)
            : null;

        // Buscar y marcar como leída la notificación de esta competición (si existe).
        // Las notificaciones se generan cuando el árbitro aprueba/rechaza la inscripción
        // (InscripcionActualizadaNotification).
        $notifInscripcion = null;
        if ($user->rol === 'competidor') {
            $notifInscripcion = $user->unreadNotifications
                ->where('data.tipo', 'inscripcion_actualizada')
                ->where('data.competicion_id', $competicion->id)
                ->first();

            // Auto-marcar como leída al ver el detalle de la competición
            if ($notifInscripcion) {
                $notifInscripcion->markAsRead();
            }
        }

        return view('competidor.competicion-show', compact(
            'competicion', 'inscripcion', 'notifInscripcion', 'licenciaAnual'
        ));
    }

    /**
     * Subir el archivo de licencia federativa.
     *
     * Crea la inscripción en estado 'borrador' si no existe (firstOrCreate).
     * Si ya había un archivo previo, lo elimina del storage antes de guardar el nuevo.
     * Archivos permitidos: jpg, jpeg, png, pdf (máx 5MB).
     * Se guardan en: storage/app/public/inscripciones/{competicion_id}/{user_id}/
     *
     * Ruta: POST /inscripciones/{competicion}/licencia → inscripciones.upload_licencia
     * Formulario: input file en competidor/competicion-show.blade.php
     */
    public function uploadLicencia(Request $request, Competicion $competicion)
    {
        // Solo competidores pueden subir documentos
        $this->soloCompetidor();

        $request->validate([
            'licencia' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB máx
        ]);

        $user = auth()->user();

        // Crear inscripción borrador si es la primera vez, calculando categoría automáticamente
        $inscripcion = Inscripcion::firstOrCreate(
            ['user_id' => $user->id, 'competicion_id' => $competicion->id],
            ['categoria' => Inscripcion::calcularCategoria($user)]
        );

        // Si ya tenía un archivo de licencia, eliminarlo antes de reemplazar
        if ($inscripcion->licencia_path) {
            Storage::disk('public')->delete($inscripcion->licencia_path);
        }

        // Guardar el nuevo archivo en storage/app/public/inscripciones/{comp_id}/{user_id}/
        $path = $request->file('licencia')->store(
            "inscripciones/{$competicion->id}/{$user->id}",
            'public'
        );

        $inscripcion->update(['licencia_path' => $path]);

        return back()->with('success', 'Licencia subida correctamente.');
    }

    /**
     * Subir el archivo de justificante de pago.
     *
     * Mismo funcionamiento que uploadLicencia() pero para el pago.
     * Crea la inscripción borrador si no existe.
     *
     * Ruta: POST /inscripciones/{competicion}/pago → inscripciones.upload_pago
     * Formulario: input file en competidor/competicion-show.blade.php
     */
    public function uploadPago(Request $request, Competicion $competicion)
    {
        $this->soloCompetidor();

        $request->validate([
            'pago' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $user = auth()->user();

        // Crear inscripción borrador si no existe
        $inscripcion = Inscripcion::firstOrCreate(
            ['user_id' => $user->id, 'competicion_id' => $competicion->id],
            ['categoria' => Inscripcion::calcularCategoria($user)]
        );

        // Reemplazar archivo previo si existía
        if ($inscripcion->pago_path) {
            Storage::disk('public')->delete($inscripcion->pago_path);
        }

        $path = $request->file('pago')->store(
            "inscripciones/{$competicion->id}/{$user->id}",
            'public'
        );

        $inscripcion->update(['pago_path' => $path]);

        return back()->with('success', 'Justificante de pago subido correctamente.');
    }

    /**
     * Enviar la inscripción a revisión del árbitro (pasar de borrador a pendiente).
     *
     * Verificaciones antes de enviar:
     *   - La competición no debe haber pasado
     *   - La licencia debe estar subida (o tener licencia anual vigente)
     *   - El justificante de pago debe estar subido
     *   - La inscripción no debe estar ya pendiente o aprobada
     *
     * Si el competidor tiene licencia anual vigente, se marca automáticamente
     * como 'valida' sin necesidad de revisión del árbitro para ese documento.
     *
     * Ruta: POST /inscripciones/{competicion} → inscripciones.store
     * Botón: "Confirmar inscripción" en competidor/competicion-show.blade.php
     */
    public function store(Request $request, Competicion $competicion)
    {
        $this->soloCompetidor();

        // No permitir inscripción en competiciones pasadas
        if ($competicion->fecha_realizacion->isPast()) {
            return back()->with('error', 'No puedes inscribirte en una competición que ya ha pasado.');
        }

        $user          = auth()->user();
        $licenciaAnual = LicenciaValidacion::validezAnual($user->id);

        $inscripcion = Inscripcion::where('user_id', $user->id)
            ->where('competicion_id', $competicion->id)
            ->first();

        // Verificar que los documentos necesarios están subidos.
        // Con licencia anual vigente, solo hace falta el justificante de pago.
        $licenciaOk = $licenciaAnual || ($inscripcion && $inscripcion->licencia_path);
        $pagoOk     = $inscripcion && $inscripcion->pago_path;

        if (!$licenciaOk || !$pagoOk) {
            $msg = !$pagoOk
                ? 'Debes subir el justificante de pago antes de inscribirte.'
                : 'Debes subir la licencia federativa antes de inscribirte.';
            return back()->with('error', $msg);
        }

        // No reenviar si ya está en revisión o aprobada
        if ($inscripcion->estado === 'pendiente') {
            return back()->with('error', 'Tu inscripción ya está pendiente de revisión por el árbitro.');
        }

        if ($inscripcion->estado === 'aprobada') {
            return back()->with('error', 'Tu inscripción ya ha sido aprobada.');
        }

        // Preparar datos de actualización: pasar a estado 'pendiente'
        $updateData = [
            'estado'         => 'pendiente',
            'motivo_rechazo' => null, // Limpiar motivo de rechazo anterior (si fue rechazada antes)
            'categoria'      => Inscripcion::calcularCategoria($user), // Recalcular categoría
        ];

        // Si tiene licencia anual válida, marcar la licencia como verificada automáticamente.
        // Así el árbitro solo necesita revisar el justificante de pago.
        if ($licenciaAnual) {
            $updateData['licencia_estado'] = 'valida';
            $updateData['licencia_motivo'] = null;
        }

        $inscripcion->update($updateData);

        return back()->with('success', 'Inscripción enviada. El árbitro revisará tu documentación en breve.');
    }

    /**
     * Middleware manual: aborta con 403 si el usuario no es competidor.
     *
     * Las rutas de inscripción son accesibles por cualquier usuario autenticado
     * (para ver el detalle), pero las acciones de upload y envío son exclusivas
     * para competidores. Los entrenadores inscriben desde su propio panel.
     */
    private function soloCompetidor(): void
    {
        if (auth()->user()->rol !== 'competidor') {
            abort(403, 'Solo los competidores pueden usar este formulario.');
        }
    }
}
