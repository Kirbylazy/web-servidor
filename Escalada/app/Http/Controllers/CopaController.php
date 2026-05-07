<?php

namespace App\Http\Controllers;

use App\Models\Copa;
use Illuminate\Http\Request;

/**
 * CopaController — CRUD de copas/torneos de escalada.
 *
 * Una copa es una serie de competiciones del mismo tipo agrupadas
 * en una temporada. Ejemplo: "Copa de Bloque 2026" contiene las 3 pruebas
 * de bloque de Andalucía, siendo la última el campeonato.
 *
 * Solo accesible por admins (middleware 'rol:admin').
 * El listado de copas se gestiona en AdminController::copas().
 *
 * Rutas: bajo admin/ con nombre admin.copas.*
 * Vistas: modales en admin/copas.blade.php
 */
class CopaController extends Controller
{
    /**
     * Crear una nueva copa.
     *
     * Tipos válidos: 'bloque', 'dificultad', 'velocidad'.
     * La temporada es un año (ej: 2026) con rango 2000-2100.
     * El nombre se genera automáticamente en la vista con Alpine.js
     * (ej: "Copa de Bloque de Andalucía 2026"), pero puede editarse.
     *
     * Ruta: POST /admin/copas → admin.copas.store
     * Formulario: modal "Crear copa" en admin/copas.blade.php
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipo'      => 'required|in:bloque,dificultad,velocidad',
            'temporada' => 'required|integer|min:2000|max:2100',
            'name'      => 'required|string|max:150',
        ]);

        Copa::create([
            'name'      => $request->name,
            'tipo'      => $request->tipo,
            'temporada' => (int) $request->temporada,
        ]);

        return back()->with('status', "Copa «{$request->name}» creada correctamente.");
    }

    /**
     * Actualizar una copa existente.
     *
     * Ruta: PATCH /admin/copas/{copa} → admin.copas.update
     * Formulario: modal "Editar" en admin/copas.blade.php
     */
    public function update(Request $request, Copa $copa)
    {
        $request->validate([
            'tipo'      => 'required|in:bloque,dificultad,velocidad',
            'temporada' => 'required|integer|min:2000|max:2100',
            'name'      => 'required|string|max:150',
        ]);

        $copa->update([
            'name'      => $request->name,
            'tipo'      => $request->tipo,
            'temporada' => (int) $request->temporada,
        ]);

        return back()->with('status', "Copa «{$copa->name}» actualizada.");
    }

    /**
     * Eliminar una copa.
     *
     * Protección: no se puede eliminar si tiene competiciones asociadas.
     * El admin debe desasociar las competiciones primero (cambiar su copa_id).
     * Nota: la FK en la migración es nullOnDelete, pero se protege igualmente
     * desde el controlador para dar un mensaje más claro al usuario.
     *
     * Ruta: DELETE /admin/copas/{copa} → admin.copas.destroy
     */
    public function destroy(Copa $copa)
    {
        // Impedir eliminación si la copa tiene pruebas asociadas
        if ($copa->competiciones()->exists()) {
            return back()->with('error',
                "No se puede eliminar «{$copa->name}» porque tiene pruebas asociadas. Desasócialas primero."
            );
        }

        $nombre = $copa->name;
        $copa->delete();

        return back()->with('status', "Copa «{$nombre}» eliminada.");
    }
}
