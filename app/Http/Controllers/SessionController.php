<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function check(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['active' => false], 200);
        }

        $sessionId = $request->session()->getId();
        $userId    = Auth::id();

        $isActive = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        return response()->json([
            'active' => $isActive,
        ], 200);
    }
}
