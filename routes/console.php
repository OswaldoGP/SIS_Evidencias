<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Semestre;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    if (Semestre::where('activo', 1)->exists()) {
        return;
    }

    $hoy = now()->toDateString();

    $candidato = Semestre::where('activo', 0)
        ->whereDate('fecha_inicio', '<=', $hoy)
        ->whereDate('fecha_fin', '>=', $hoy)
        ->first();

    if ($candidato) {
        $candidato->update(['activo' => 1]);
    }
})->daily();
