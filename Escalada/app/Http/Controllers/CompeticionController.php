<?php

namespace App\Http\Controllers;

use App\Models\Competicion;
use Illuminate\Http\Request;

/**
 * CompeticionController — CRUD de competiciones/pruebas de escalada.
 *
 * Gestiona la creación, edición, eliminación y marcado de campeonato
 * de competiciones. Solo accesible por admins (middleware 'rol:admin').
 *
 * Las competiciones son eventos de escalada que pertenecen a una Copa (opcional),
 * se celebran en una Ubicación (obligatorio) y pueden tener un árbitro asignado.
 *
 * Tipos de competición: 'bloque', 'dificultad', 'velocidad'.
 * Una competición puede ser marcada como "campeonato" (final de la copa del año).
 *
 * Rutas: bajo admin/ con nombre admin.competiciones.*
 * Vistas: los formularios están en modales dentro de admin/pruebas.blade.php
 */
class CompeticionController extends Controller
{
    /**
     * Crear una nueva competición.
     *
     * Valida y crea la competición con los datos del formulario modal.
     * Las categorías se almacenan como JSON array (ej: ['U15','U17','Absoluta']).
     * La competición se crea siempre con campeonato=false; para marcarla
     * como campeonato se usa toggleCampeonato().
     *
     * Ruta: POST /admin/competiciones → admin.competiciones.store
     * Formulario: modal "Crear prueba" en admin/pruebas.blade.php
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:150',
            'tipo'              => 'required|in:bloque,dificultad,velocidad',
            'fecha_realizacion' => 'required|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_realizacion', // Si existe, debe ser >= inicio
            'provincia'         => 'required|string',
            'ubicacion_id'      => 'required|exists:ubicacions,id',   // Rocódromo obligatorio
            'copa_id'           => 'nullable|exists:copas,id',        // Copa opcional
            'arbitro_id'        => 'nullable|exists:users,id',        // Árbitro opcional
            'categorias'        => 'nullable|array',                  // Checkboxes de categorías
            'categorias.*'      => 'in:U9,U11,U13,U15,U17,U19,Absoluta,Veterana,Promoción',
        ]);

        Competicion::create([
            'name'              => $request->name,
            'tipo'              => $request->tipo,
            'fecha_realizacion' => $request->fecha_realizacion,
            'fecha_fin'         => $request->fecha_fin ?: null,
            'provincia'         => $request->provincia,
            'ubicacion_id'      => $request->ubicacion_id,
            'copa_id'           => $request->copa_id ?: null,    // null si no pertenece a ninguna copa
            'arbitro_id'        => $request->arbitro_id ?: null, // null si no tiene árbitro aún
            'campeonato'        => false,                        // Siempre empieza como no-campeonato
            'categorias'        => $request->categorias ?? [],   // Array vacío si no se seleccionó ninguna
        ]);

        return back()->with('status', "Prueba «{$request->name}» creada correctamente.");
    }

    /**
     * Actualizar una competición existente.
     *
     * Misma validación que store(). No modifica el campo 'campeonato'
     * (eso se hace exclusivamente con toggleCampeonato).
     *
     * Ruta: PATCH /admin/competiciones/{competicion} → admin.competiciones.update
     * Formulario: modal "Editar" en admin/pruebas.blade.php
     */
    public function update(Request $request, Competicion $competicion)
    {
        $request->validate([
            'name'              => 'required|string|max:150',
            'tipo'              => 'required|in:bloque,dificultad,velocidad',
            'fecha_realizacion' => 'required|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_realizacion',
            'provincia'         => 'required|string',
            'ubicacion_id'      => 'required|exists:ubicacions,id',
            'copa_id'           => 'nullable|exists:copas,id',
            'arbitro_id'        => 'nullable|exists:users,id',
            'categorias'        => 'nullable|array',
            'categorias.*'      => 'in:U9,U11,U13,U15,U17,U19,Absoluta,Veterana,Promoción',
        ]);

        $competicion->update([
            'name'              => $request->name,
            'tipo'              => $request->tipo,
            'fecha_realizacion' => $request->fecha_realizacion,
            'fecha_fin'         => $request->fecha_fin ?: null,
            'provincia'         => $request->provincia,
            'ubicacion_id'      => $request->ubicacion_id,
            'copa_id'           => $request->copa_id ?: null,
            'arbitro_id'        => $request->arbitro_id ?: null,
            'categorias'        => $request->categorias ?? [],
        ]);

        return back()->with('status', "Prueba «{$competicion->name}» actualizada.");
    }

    /**
     * Alternar si una competición es campeonato o no.
     *
     * Regla de negocio: solo puede haber UN campeonato por tipo de escalada
     * y año. Por ejemplo, no puede haber dos campeonatos de bloque en 2026.
     * Si ya existe otro campeonato del mismo tipo en el mismo año, se rechaza.
     *
     * Ruta: PATCH /admin/competiciones/{competicion}/campeonato → admin.competiciones.campeonato
     * Botón: en la tabla de admin/pruebas.blade.php
     */
    public function toggleCampeonato(Competicion $competicion)
    {
        // Si ya es campeonato, simplemente quitar la marca
        if ($competicion->campeonato) {
            $competicion->update(['campeonato' => false]);
            return back()->with('status', "«{$competicion->name}» ya no es campeonato.");
        }

        // Si no es campeonato, verificar que no haya otro del mismo tipo en el mismo año
        $año = $competicion->fecha_realizacion->year;
        $existente = Competicion::where('tipo', $competicion->tipo)
            ->where('campeonato', true)
            ->whereYear('fecha_realizacion', $año)
            ->where('id', '!=', $competicion->id)
            ->first();

        if ($existente) {
            return back()->with('error',
                "Ya hay un campeonato de {$competicion->tipo} en {$año}: «{$existente->name}»."
            );
        }

        // Marcar como campeonato
        $competicion->update(['campeonato' => true]);
        return back()->with('status', "«{$competicion->name}» designada campeonato de {$competicion->tipo} {$año}.");
    }

    /**
     * Eliminar una competición.
     *
     * Al eliminar, se borran en cascada las inscripciones asociadas
     * (definido en la migración con cascadeOnDelete).
     *
     * Ruta: DELETE /admin/competiciones/{competicion} → admin.competiciones.destroy
     * Botón: en la tabla de admin/pruebas.blade.php
     */
    public function destroy(Competicion $competicion)
    {
        $nombre = $competicion->name;
        $competicion->delete(); // Las inscripciones se eliminan en cascada (FK)

        return back()->with('status', "Prueba «{$nombre}» eliminada.");
    }
}
