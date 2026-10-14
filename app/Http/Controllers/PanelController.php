<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function cambiar($panel)
    {
        $panel = strtolower($panel);

        if (!in_array($panel, ['admin', 'docente'], true)) {
            return back()->with('error', 'Panel inválido.');
        }

        $user = auth()->user();
        $rolUsuario = strtolower($user->rol ?? '');

        if ($panel === 'admin') {
            if (!in_array($rolUsuario, ['admin', 'encargado'], true)) {
                return back()->with('error', 'No puedes acceder al panel administrativo.');
            }

            session(['panel_activo' => 'admin']);

            return redirect()->route('home');
        }

        // Panel docente
        if (!$user->docente || !$user->docente->activo) {
            return back()->with('error', 'No tienes perfil de docente activo.');
        }

        session(['panel_activo' => 'docente']);

        return redirect()->route('dashboard');
    }
}
