<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckRol — Control de acceso basado en jerarquía de roles.
 *
 * Implementa un sistema de permisos JERÁRQUICO para la app de escalada.
 * Los roles tienen niveles numéricos:
 *   - competidor:  1 (nivel base)
 *   - entrenador:  2 (puede hacer todo lo de competidor + lo de entrenador)
 *   - arbitro:     3 (puede hacer todo lo anterior + lo de árbitro)
 *   - admin:       4 (acceso total a toda la aplicación)
 *
 * Uso en rutas: middleware('rol:entrenador') permite acceso a entrenadores,
 * árbitros y admins (nivel 2+). middleware('rol:admin') solo permite admins (nivel 4).
 *
 * Si se pasan múltiples roles, se toma el MÍNIMO. Ejemplo: 'rol:arbitro,entrenador'
 * requiere al menos nivel 2 (entrenador).
 *
 * Registrado en bootstrap/app.php como alias 'rol'.
 * Usado en: routes/web.php para proteger los grupos de rutas admin, arbitro y entrenador.
 */
class CheckRol
{
    /**
     * Verificar que el usuario tiene nivel suficiente para acceder a la ruta.
     *
     * @param Request $request   La petición HTTP actual
     * @param Closure $next      Siguiente middleware/controlador en la cadena
     * @param string  ...$roles  Roles requeridos (se toma el nivel mínimo)
     * @return Response          Continúa la petición o redirige al dashboard con error
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Mapa de roles a niveles numéricos (misma lógica que User::rolNivel())
        $niveles = ['competidor' => 1, 'entrenador' => 2, 'arbitro' => 3, 'admin' => 4];

        // Obtener el nivel del usuario actual (0 si no tiene rol válido o no está autenticado)
        $userNivel   = $niveles[$request->user()?->rol] ?? 0;

        // Calcular el nivel mínimo requerido a partir de los roles especificados.
        // Si se pasan varios roles (ej: 'arbitro,entrenador'), se toma el menor (2).
        $minRequerido = collect($roles)->map(fn($r) => $niveles[$r] ?? 0)->min();

        // Denegar acceso si: no hay usuario autenticado, o su nivel es insuficiente
        if (!$request->user() || $userNivel < $minRequerido) {
            return redirect()->route('dashboard')
                ->with('error', 'No tienes permiso para acceder a esa sección.');
        }

        // Nivel suficiente: continuar con la petición
        return $next($request);
    }
}
