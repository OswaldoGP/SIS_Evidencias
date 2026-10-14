<?php

namespace App\Http\Controllers;

use App\Models\AsignacionMateria;
use App\Models\Evidencia;
use App\Models\Revision;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Reportes extends Controller
{
    public function index()
    {
        $titulo = "Reportes";

        $semestreActivo = \App\Models\Semestre::where('activo', 1)->first();

        $evidencias = Evidencia::query()
            ->with([
                'materia',
                'revision',
                'asignacionMateria'
            ])
            ->when($semestreActivo, function ($query) use ($semestreActivo) {
                $query->whereHas('asignacionMateria', function ($q) use ($semestreActivo) {
                    $q->where('semestre_id', $semestreActivo->id)
                        ->where('activo', 1);
                });
            })
            ->get();

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

        return view(
            'modules.reportes.index',
            compact(
                'titulo',
                'asignaciones',
                'revisiones',
                'evidencias'
            )
        );
    }

    private function obtenerEvaluacionCompleta($evidencia)
    {
        $evaluacionActual = $evidencia->evaluacion ?? [];
        $esRevision1 = (int) $evidencia->revision->id === 1;

        if ($esRevision1) {
            return $evaluacionActual;
        }

        $revision1 = Revision::where('numero', 1)->first();
        if (!$revision1) {
            return $evaluacionActual;
        }

        $evidenciaRev1 = Evidencia::where('materia_id', $evidencia->materia_id)
            ->where('revision_id', $revision1->id)
            ->where('asignacion_materia_id', $evidencia->asignacion_materia_id)
            ->first();

        if (!$evidenciaRev1) {
            return $evaluacionActual;
        }

        $evaluacionRev1 = $evidenciaRev1->evaluacion ?? [];

        $camposAuto = ['instrumentacion', 'reporte_inicio', 'acuerdos', 'examen_diagnostico', 'analisis_diagnostico'];

        foreach ($camposAuto as $campo) {
            if (isset($evaluacionRev1[$campo])) {
                $evaluacionActual[$campo] = $evaluacionRev1[$campo];
            } else {
                $evaluacionActual[$campo] = [
                    'na' => false,
                    'calificacion' => 0,
                    'observaciones' => 'Sin evaluación en Revisión 1'
                ];
            }
        }

        return $evaluacionActual;
    }

    public function reportePdf($id)
    {
        $evidencia = Evidencia::with([
            'materia',
            'revision',
            'asignacion.docente',
            'asignacionMateria.semestre',
            'evaluador.docente'
        ])->findOrFail($id);

        $evaluacion = $this->obtenerEvaluacionCompleta($evidencia);
        $esRevision4 = (int) $evidencia->revision->id === 4;

        $criterios = [
            'instrumentacion'      => 'Instrumentación didáctica',
            'reporte_inicio'       => 'Reporte inicio de curso',
            'examen_diagnostico'   => 'Examen diagnóstico',
            'analisis_diagnostico' => 'Análisis del diagnóstico',
            'acuerdos'             => 'Acuerdos de clase',
            'avance_programatico'  => 'Avance programático',
            'instrumentos'         => 'Evidencia de instrumentos de evaluación (3 muestras)',
            'rubricas'             => 'Rúbricas del semestre',
            'calificaciones'       => 'Lista de calificaciones con índice de aprobación',
            'rac'                  => 'Actividades de regularización',
            'asiste_seguimiento'   => 'Asiste al seguimiento',
        ];

        if ($esRevision4) {
            $criterios['calificaciones_finales'] = 'Lista de calificaciones finales';
            $criterios['acta'] = 'Acta de revisión';
            $criterios['segunda_oportunidad'] = 'Evidencias de segunda oportunidad';
        }

        $promedio = 0;
        $contador = 0;

        foreach ($criterios as $key => $nombre) {
            $item = $evaluacion[$key] ?? [];
            $na = !empty($item['na']);
            $calificacion = $item['calificacion'] ?? null;

            if (!$na && $calificacion !== null && $calificacion !== '') {
                $promedio += floatval($calificacion);
                $contador++;
            }
        }

        $promedioFinal = $contador > 0 ? round($promedio / $contador, 2) : 0;

        $pdf = Pdf::loadView(
            'modules.reportes.pdf',
            [
                'evidencia'      => $evidencia,
                'evaluacion'     => $evaluacion,
                'criterios'      => $criterios,
                'promedioFinal'  => $promedioFinal,
                'admin'          => $evidencia->evaluador ?? Auth::user(),
            ]
        );

        $pdf->setPaper('letter');
        return $pdf->stream('reporte-seguimiento.pdf');
    }

    public function reportePdfDocente($id)
    {
        $evidencia = Evidencia::with([
            'materia',
            'revision',
            'asignacion.docente',
            'asignacionMateria.semestre',
            'admin'
        ])->findOrFail($id);

        if ($evidencia->asignacionMateria->docente_id != Auth::id()) {
            abort(403);
        }

        $evaluacion = $this->obtenerEvaluacionCompleta($evidencia);
        $esRevision4 = (int) $evidencia->revision->id === 4;

        $criterios = [
            'instrumentacion'      => 'Instrumentación didáctica',
            'reporte_inicio'       => 'Reporte inicio de curso',
            'examen_diagnostico'   => 'Examen diagnóstico',
            'analisis_diagnostico' => 'Análisis del diagnóstico',
            'acuerdos'             => 'Acuerdos de clase',
            'avance_programatico'  => 'Avance programático',
            'instrumentos'         => 'Evidencia de instrumentos de evaluación (3 muestras)',
            'rubricas'             => 'Rúbricas del semestre',
            'calificaciones'       => 'Lista de calificaciones',
            'rac'                  => 'Actividades de regularización',
            'asiste_seguimiento'   => 'Asiste al seguimiento',
        ];

        if ($esRevision4) {
            $criterios['calificaciones_finales'] = 'Lista de calificaciones finales';
            $criterios['acta'] = 'Acta de revisión';
            $criterios['segunda_oportunidad'] = 'Evidencias de segunda oportunidad';
        }

        $promedio = 0;
        $contador = 0;

        foreach ($criterios as $key => $nombre) {
            $item = $evaluacion[$key] ?? [];
            $na = !empty($item['na']);
            $calificacion = $item['calificacion'] ?? null;

            if (!$na && $calificacion !== null && $calificacion !== '') {
                $promedio += floatval($calificacion);
                $contador++;
            }
        }

        $promedioFinal = $contador > 0 ? round($promedio / $contador, 2) : 0;

        $pdf = Pdf::loadView(
            'modules.reportes.pdf',
            [
                'evidencia' => $evidencia,
                'evaluacion' => $evaluacion,
                'criterios' => $criterios,
                'promedioFinal' => $promedioFinal,
                'admin' => $evidencia->admin,
            ]
        );

        return $pdf->stream('reporte-seguimiento.pdf');
    }
}
