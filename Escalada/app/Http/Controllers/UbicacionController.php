<?php

namespace App\Http\Controllers;

use App\Models\Ubicacion;
use Illuminate\Http\Request;

/**
 * UbicacionController — CRUD de rocódromos/ubicaciones de escalada.
 *
 * Gestiona los rocódromos donde se celebran las competiciones.
 * Cada ubicación tiene nombre, provincia, dirección y medidas del muro
 * (alto, ancho, número de líneas).
 *
 * Solo accesible por admins (middleware 'rol:admin').
 * El listado se gestiona en AdminController::rocodromos().
 *
 * Rutas: bajo admin/ con nombre admin.rocodromos.*
 * Vistas: modales en admin/rocodromos.blade.php
 */
class UbicacionController extends Controller
{
    /**
     * Crear un nuevo rocódromo.
     *
     * Valida los datos del formulario y crea la ubicación.
     * Las medidas son opcionales (puede que no se conozcan al crear).
     *
     * Ruta: POST /admin/rocodromos → admin.rocodromos.store
     * Formulario: modal "Crear rocódromo" en admin/rocodromos.blade.php
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:150',
            'provincia' => 'required|string',
            'direccion' => 'nullable|string|max:255',
            'alto'      => 'nullable|numeric|min:0',    // Metros de altura del muro
            'ancho'     => 'nullable|numeric|min:0',     // Metros de anchura del muro
            'n_lineas'  => 'nullable|integer|min:0',     // Número de líneas/vías disponibles
        ]);

        $ubicacion = Ubicacion::create($request->only(['name', 'provincia', 'direccion', 'alto', 'ancho', 'n_lineas']));

        return back()->with('status', "Rocódromo «{$ubicacion->name}» creado correctamente.");
    }

    /**
     * Actualizar un rocódromo existente.
     *
     * Ruta: PATCH /admin/rocodromos/{ubicacion} → admin.rocodromos.update
     * Formulario: modal "Editar" en admin/rocodromos.blade.php
     */
    public function update(Request $request, Ubicacion $ubicacion)
    {
        $request->validate([
            'name'      => 'required|string|max:150',
            'provincia' => 'required|string',
            'direccion' => 'nullable|string|max:255',
            'alto'      => 'nullable|numeric|min:0',
            'ancho'     => 'nullable|numeric|min:0',
            'n_lineas'  => 'nullable|integer|min:0',
        ]);

        $ubicacion->update($request->only(['name', 'provincia', 'direccion', 'alto', 'ancho', 'n_lineas']));

        return back()->with('status', "Rocódromo «{$ubicacion->name}» actualizado.");
    }

    /**
     * Eliminar un rocódromo.
     *
     * Protección: no se puede eliminar si tiene competiciones asociadas.
     * Aunque la migración tiene cascadeOnDelete, se protege desde el controlador
     * para evitar borrar accidentalmente competiciones e inscripciones.
     *
     * Ruta: DELETE /admin/rocodromos/{ubicacion} → admin.rocodromos.destroy
     */
    public function destroy(Ubicacion $ubicacion)
    {
        // Verificar que no haya competiciones asociadas antes de eliminar
        if ($ubicacion->competiciones()->exists()) {
            return back()->with('error',
                "No se puede eliminar «{$ubicacion->name}» porque tiene competiciones asociadas."
            );
        }

        $nombre = $ubicacion->name;
        $ubicacion->delete();

        return back()->with('status', "Rocódromo «{$nombre}» eliminado.");
    }
}
