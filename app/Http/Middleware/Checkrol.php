<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Checkrol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $rolesAplanados = [];
        foreach ($roles as $rol) {
            foreach (explode(',', $rol) as $subRol) {
                foreach (explode('|', $subRol) as $r) {
                    $r = strtolower(trim($r));
                    if ($r !== '') {
                        $rolesAplanados[] = $r;
                    }
                }
            }
        }
        $rolesPermitidos = array_values(array_unique($rolesAplanados));

        $rolUsuario = strtolower(trim($user->rol));

        if (!$request->session()->has('panel_activo')) {
            $request->session()->put(
                'panel_activo',
                in_array($rolUsuario, ['admin', 'encargado'], true) ? 'admin' : 'docente'
            );
        }

        $panelActivo = $request->session()->get('panel_activo');

        if ($panelActivo === 'admin') {
            if ($rolUsuario === 'admin') {
                if (
                    in_array('admin', $rolesPermitidos, true) ||
                    in_array('encargado', $rolesPermitidos, true)
                ) {
                    return $next($request);
                }
                abort(403, 'Rol admin no autorizado para: ' . implode(',', $rolesPermitidos));
            }

            if ($rolUsuario === 'encargado') {
                if (in_array('encargado', $rolesPermitidos, true)) {
                    return $next($request);
                }
                abort(403, 'Rol encargado no autorizado para: ' . implode(',', $rolesPermitidos));
            }

            abort(403, 'Rol desconocido en panel admin: ' . $rolUsuario);
        }

        if ($panelActivo === 'docente') {
            $tienePerfilDocente = $user->docente && (int) $user->docente->activo === 1;

            if (!$tienePerfilDocente) {
                abort(403, 'Sin perfil docente activo');
            }

            if ($rolUsuario === 'admin') {
                if (
                    in_array('docente', $rolesPermitidos, true) ||
                    in_array('admin', $rolesPermitidos, true)
                ) {
                    return $next($request);
                }
                abort(403, 'Rol admin no autorizado en panel docente para: ' . implode(',', $rolesPermitidos));
            }

            if ($rolUsuario === 'docente') {
                if (in_array('docente', $rolesPermitidos, true)) {
                    return $next($request);
                }
                abort(403, 'Rol docente no autorizado para: ' . implode(',', $rolesPermitidos));
            }

            abort(403, 'Rol no permitido en panel docente: ' . $rolUsuario);
        }

        abort(403, 'Panel activo desconocido: ' . $panelActivo);
    }
}
