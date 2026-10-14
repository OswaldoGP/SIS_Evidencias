<?php

namespace App\Http\Controllers;

use App\Models\AsignacionMateria;
use App\Models\Revision;
use App\Models\Semestre;

class SeguimientoAcademico extends Controller
{
    public function index()
    {
        $titulo = "Administración de Evidencias";

        $semestreActivo = Semestre::where('activo', 1)->first();

        $asignaciones = AsignacionMateria::with([
            'materia',
            'docente.user',
            'evidencias',
        ])
            ->where('activo', 1)
            ->when($semestreActivo, function ($query) use ($semestreActivo) {
                $query->where('semestre_id', $semestreActivo->id);
            })
            ->get()
            ->sortBy([
                fn($a, $b) => strcmp($a->docente?->user?->name ?? '', $b->docente?->user?->name ?? ''),
                fn($a, $b) => strcmp($a->materia?->nombre ?? '', $b->materia?->nombre ?? ''),
                fn($a, $b) => strcmp($a->grupo ?? '', $b->grupo ?? ''),
            ])
            ->values();

        $revisiones = Revision::orderBy('numero', 'asc')->get();

        return view('modules.seguimiento-academico.index', compact(
            'asignaciones',
            'revisiones',
            'titulo'
        ));
    }
}
