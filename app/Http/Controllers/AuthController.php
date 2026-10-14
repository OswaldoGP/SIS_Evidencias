<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function index(Request $request)
    {
        $titulo = "Login de usuarios";

        $sesionCerrada = false;
        if ($request->session()->pull('sesion_cerrada_por_dispositivo')) {
            $sesionCerrada = true;
        }

        return view("modules.auth.login", compact("titulo", "sesionCerrada"));
    }

    public function logear(Request $request)
    {
        $credenciales = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'tab_id'   => 'nullable|string|max:64',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['email' => 'Credencial Incorrecta'])
                ->withInput();
        }

        $rolUsuario = strtolower(trim($user->rol));

        if ($rolUsuario === 'docente' && (!$user->docente || !$user->docente->activo)) {
            return back()->withErrors(['email' => 'Tu cuenta está inactiva!']);
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        Auth::login($user);

        $request->session()->regenerate();

        $request->session()->save();

        DB::table('sessions')
            ->where('id', $request->session()->getId())
            ->update([
                'is_active' => true,
                'tab_id'    => $request->input('tab_id'),
            ]);

        $panelPorDefecto = in_array($rolUsuario, ['admin', 'encargado'], true) ? 'admin' : 'docente';
        $request->session()->put('panel_activo', $panelPorDefecto);

        if ($rolUsuario === 'admin') {
            return redirect()->route('home');
        }

        if ($rolUsuario === 'encargado') {
            return redirect()->route('encargado.dashboard');
        }

        if ($rolUsuario === 'docente') {
            return redirect()->route('dashboard');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['email' => 'El usuario no tiene un rol válido.']);
    }

    public function logout(Request $request)
    {
        $sessionId = $request->session()->getId();

        DB::table('sessions')
            ->where('id', $sessionId)
            ->update(['is_active' => false]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ], [
            'password.confirmed' => 'Las nuevas contraseñas no coinciden.',
            'password.min'       => 'La nueva contraseña debe tener al menos 8 caracteres.'
        ]);

        $user = User::findOrFail(Auth::id());

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'Tu contraseña actual no coincide con nuestros registros.');
        }

        if (Hash::check($request->password, $user->password)) {
            return back()->with('error', 'La nueva contraseña debe ser diferente a la anterior.');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', '¡Contraseña actualizada con éxito!');
    }
}
