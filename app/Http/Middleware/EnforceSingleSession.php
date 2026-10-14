<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnforceSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $sessionId = $request->session()->getId();
            $userId    = Auth::id();

            $isActive = DB::table('sessions')
                ->where('id', $sessionId)
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->exists();

            if (!$isActive) {
                Auth::logout();

                $request->session()->put('sesion_cerrada_por_dispositivo', true);

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Sesión cerrada: iniciaste sesión en otro dispositivo.'
                    ], 401);
                }

                return redirect()->route('login');
            }

            DB::table('sessions')
                ->where('id', $sessionId)
                ->update(['last_activity' => now()->timestamp]);
        }

        return $next($request);
    }
}
