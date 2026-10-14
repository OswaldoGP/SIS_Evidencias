<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Revision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Evaluaciones extends Controller
{
    public function show($id)
    {
        $evidencia = Evidencia::with([
            'materia',
            'revision',
            'asignacionMateria.semestre',
            'asignacionMateria.docente'
        ])->findOrFail($id);

        $data = is_array($evidencia->documentos)
            ? $evidencia->documentos
            : json_decode($evidencia->documentos ?? '[]', true);

        if (!is_array($data)) {
            $data = [];
        }

        $esRevision5 = (int) ($evidencia->revision->numero ?? 0) === 5;

        if ($esRevision5) {
            return view('modules.evaluacion.index', [
                'evidencia' => $evidencia,
                'items' => [],
            ]);
        }

        $documentos = $data['documentos'] ?? [];
        $evidencias = $data['evidencias'] ?? [];
        $instrumentos = $data['instrumentos'] ?? [];

        $unidadesSeleccionadas = $data['unidades'] ?? [];
        $ningunaUnidad = in_array(0, $unidadesSeleccionadas);

        $motivoNoEvaluo = $data['motivo_no_evaluo']
            ?? ($documentos['calificaciones']['motivo'] ?? null)
            ?? ($documentos['calificaciones_detalladas']['u0']['motivo'] ?? null)
            ?? null;

        $calificacionesArchivo = $documentos['calificaciones'] ?? null;
        $calificacionesNa = is_array($calificacionesArchivo) && !empty($calificacionesArchivo['na']);

        $esRevision1 = (int) $evidencia->revision->numero === 1;
        $esRevision4 = (int) $evidencia->revision->numero === 4;

        // --- EXTRACCIÓN ROBUSTA DE ARCHIVOS MÚLTIPLES ---
        $extraerRutasPdfs = function ($fuente) {
            $rutas = [];
            if (!is_array($fuente)) return $rutas;

            array_walk_recursive($fuente, function ($item, $key) use (&$rutas) {
                if ($key === 'archivo' && is_string($item) && trim($item) !== '') {
                    $rutas[] = $item;
                } elseif (is_int($key) && is_string($item) && preg_match('/\.pdf$/i', trim($item))) {
                    $rutas[] = $item;
                } elseif (is_string($item) && preg_match('/\.pdf$/i', trim($item)) && !in_array($item, $rutas)) {
                    $rutas[] = $item;
                }
            });

            return array_values(array_unique($rutas));
        };

        $calificacionesDetalladas = $documentos['calificaciones_detalladas'] ?? ($documentos['calificaciones'] ?? []);
        $racDetallado            = $documentos['rac_detallado'] ?? ($documentos['rac'] ?? []);
        $rubricasDetalladas      = $evidencias['rubricas_detalladas'] ?? ($evidencias['rubricas'] ?? []);

        $archivosCalificaciones = $extraerRutasPdfs($calificacionesDetalladas);
        $archivosRac            = $extraerRutasPdfs($racDetallado);
        $archivosRubricas       = $extraerRutasPdfs($rubricasDetalladas);
        $archivosInstrumentos   = $extraerRutasPdfs($instrumentos);

        $items = [
            [
                'key' => 'calificaciones',
                'nombre' => 'Lista de calificaciones',
                'archivo' => $archivosCalificaciones[0] ?? null,
                'archivos_multiples' => $archivosCalificaciones,
                'motivo_no_evaluo' => $ningunaUnidad ? $motivoNoEvaluo : null,
                'documento_na' => $ningunaUnidad || $calificacionesNa || (empty($archivosCalificaciones) && !empty($documentos['calificaciones']['na'])),
            ],
            [
                'key' => 'rac',
                'nombre' => 'Actividades de Regularización (RAC)',
                'archivo' => $archivosRac[0] ?? null,
                'archivos_multiples' => $archivosRac,
                'motivo_no_evaluo' => $ningunaUnidad ? $motivoNoEvaluo : null,
                'documento_na' => $ningunaUnidad || (empty($archivosRac) && !empty($documentos['rac']['na'])),
            ],
            [
                'key' => 'rubricas',
                'nombre' => 'Rúbricas del semestre',
                'archivo' => $archivosRubricas[0] ?? null,
                'archivos_multiples' => $archivosRubricas,
                'motivo_no_evaluo' => $ningunaUnidad ? $motivoNoEvaluo : null,
                'documento_na' => $ningunaUnidad || (empty($archivosRubricas) && !empty($evidencias['rubricas']['na'])),
            ],
            [
                'key' => 'instrumentos',
                'nombre' => 'Instrumentos de evaluación individuales',
                'archivo' => $archivosInstrumentos[0] ?? null,
                'archivos_multiples' => $archivosInstrumentos,
                'motivo_no_evaluo' => $ningunaUnidad ? $motivoNoEvaluo : null,
                'documento_na' => $ningunaUnidad || !empty($data['instrumentos_na']),
            ],
        ];

        if ($esRevision1) {
            array_unshift($items, ...[
                ['key' => 'instrumentacion', 'nombre' => 'Instrumentación didáctica', 'archivo' => $documentos['instrumentacion'] ?? null],
                ['key' => 'reporte_inicio', 'nombre' => 'Reporte inicio de curso', 'archivo' => $documentos['reporte_inicio'] ?? null],
                ['key' => 'examen_diagnostico', 'nombre' => 'Examen diagnóstico', 'archivo' => $evidencias['examen_diagnostico'] ?? null],
                ['key' => 'analisis_diagnostico', 'nombre' => 'Análisis del diagnóstico', 'archivo' => $evidencias['analisis_diagnostico'] ?? null],
                ['key' => 'acuerdos', 'nombre' => 'Acuerdos de clase', 'archivo' => $documentos['acuerdos'] ?? null],
            ]);
        }

        if ($esRevision4) {
            $items[] = [
                'key' => 'acta',
                'nombre' => 'Acta de revisión',
                'archivo' => $documentos['acta'] ?? null
            ];

            $items[] = [
                'key' => 'calificaciones_finales',
                'nombre' => 'Lista de calificaciones finales',
                'archivo' => $documentos['calificaciones_finales'] ?? null
            ];

            $items[] = [
                'key' => 'segunda_oportunidad',
                'nombre' => 'Evidencias de segunda oportunidad',
                'archivos_multiples' => $evidencias['segunda_oportunidad'] ?? [],
                'documento_na' => empty($evidencias['segunda_oportunidad'])
            ];
        }

        return view('modules.evaluacion.index', compact('evidencia', 'items'));
    }

    public function update(Request $request, $id)
    {
        $evidencia = Evidencia::findOrFail($id);
        $evaluacion = $request->evaluaciones ?? [];

        $esRevision5 = (int) ($evidencia->revision->numero ?? 0) === 5;
        $esRevision1 = (int) ($evidencia->revision->numero ?? 0) === 1;

        if ($esRevision5) {
            $todoAprobado = true;

            foreach ($evaluacion as $item) {
                $na = !empty($item['na']);
                $calificacion = $item['calificacion'] ?? null;

                if ($na) continue;

                if ($calificacion === null || $calificacion === '' || (float) $calificacion < 70) {
                    $todoAprobado = false;
                }
            }

            $estadoFinal = $todoAprobado ? 2 : 4;

            $evidencia->update([
                'evaluacion'     => $evaluacion,
                'estado'         => $estadoFinal,
                'admin_id'       => Auth::id(),
                'fecha_revision' => now(),
            ]);

            return redirect()
                ->route('seguimiento-academico')
                ->with(
                    'success',
                    $estadoFinal == 2
                        ? 'Evidencia aprobada correctamente'
                        : 'Evidencia rechazada por documentos con calificación menor a 70'
                );
        }

        if (!$esRevision1) {
            $revision1 = Revision::where('numero', 1)->first();

            if ($revision1) {
                $evidenciaRev1 = Evidencia::where('asignacion_materia_id', $evidencia->asignacion_materia_id)
                    ->where('revision_id', $revision1->id)
                    ->first();

                $camposAuto = [
                    'instrumentacion',
                    'reporte_inicio',
                    'acuerdos',
                    'examen_diagnostico',
                    'analisis_diagnostico'
                ];

                foreach ($camposAuto as $campo) {
                    $evaluacion[$campo] = $evidenciaRev1->evaluacion[$campo] ?? [
                        'na' => false,
                        'calificacion' => 0,
                        'observaciones' => null,
                    ];
                }
            }
        }

        $data = is_array($evidencia->documentos)
            ? $evidencia->documentos
            : json_decode($evidencia->documentos ?? '[]', true);

        $motivoNoEvaluo = $data['motivo_no_evaluo'] ?? null;

        if (
            isset($evaluacion['calificaciones']['na']) &&
            $evaluacion['calificaciones']['na'] &&
            $motivoNoEvaluo
        ) {
            $evaluacion['calificaciones']['motivo_no_evaluo'] = $motivoNoEvaluo;
        }

        foreach (['rac', 'rubricas', 'instrumentos'] as $itemKey) {
            if (
                isset($evaluacion[$itemKey]['na']) &&
                $evaluacion[$itemKey]['na'] &&
                $motivoNoEvaluo
            ) {
                $evaluacion[$itemKey]['motivo_no_evaluo'] = $motivoNoEvaluo;
            }
        }

        $todoAprobado = true;

        foreach ($evaluacion as $key => $item) {
            $na = !empty($item['na']);
            $calificacion = $item['calificacion'] ?? null;

            if ($na) continue;

            if ($calificacion === null || $calificacion === '' || (float) $calificacion < 70) {
                $todoAprobado = false;
            }
        }

        $estadoFinal = $todoAprobado ? 2 : 4;

        $evidencia->update([
            'evaluacion'     => $evaluacion,
            'estado'         => $estadoFinal,
            'admin_id'       => Auth::id(),
            'fecha_revision' => now(),
        ]);

        if ($esRevision1) {
            $camposAuto = [
                'instrumentacion',
                'reporte_inicio',
                'acuerdos',
                'examen_diagnostico',
                'analisis_diagnostico'
            ];

            $otrasRevisiones = Evidencia::where(
                'asignacion_materia_id',
                $evidencia->asignacion_materia_id
            )
                ->where('id', '!=', $evidencia->id)
                ->whereIn('estado', [2, 4])
                ->get();

            foreach ($otrasRevisiones as $revision) {
                $eval = $revision->evaluacion ?? [];

                foreach ($camposAuto as $campo) {
                    $eval[$campo] = $evaluacion[$campo] ?? [
                        'na' => false,
                        'calificacion' => 0,
                        'observaciones' => null,
                    ];
                }

                $todoAprobadoRevision = true;

                foreach ($eval as $item) {
                    $na = !empty($item['na']);
                    $calificacion = $item['calificacion'] ?? null;

                    if ($na) continue;

                    if ($calificacion === null || $calificacion === '' || (float) $calificacion < 70) {
                        $todoAprobadoRevision = false;
                    }
                }

                $revision->update([
                    'evaluacion'     => $eval,
                    'estado'         => $todoAprobadoRevision ? 2 : 4,
                    'admin_id'       => Auth::id(),
                    'fecha_revision' => now(),
                ]);
            }
        }

        return redirect()
            ->route('seguimiento-academico')
            ->with(
                'success',
                $estadoFinal == 2
                    ? 'Evidencia aprobada correctamente'
                    : 'Evidencia rechazada por documentos con calificación menor a 70'
            );
    }

    public function autoSave(Request $request, $id)
    {
        $evidencia = Evidencia::findOrFail($id);
        $key = $request->input('key');
        $evaluacionActual = $evidencia->evaluacion ?? [];

        $evaluacionActual[$key] = [
            'na' => $request->input('na'),
            'calificacion' => $request->input('calificacion'),
            'observaciones' => $request->input('observaciones')
        ];

        $evidencia->update(['evaluacion' => $evaluacionActual]);
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $evidencia = Evidencia::findOrFail($id);
        $datos = is_array($evidencia->documentos) ? $evidencia->documentos : json_decode($evidencia->documentos ?? '{}', true);

        $eliminarArchivos = function ($item) use (&$eliminarArchivos) {
            if (is_string($item) && !empty($item)) {
                Storage::disk('public')->delete($item);
                return;
            }
            if (is_array($item)) {
                if (isset($item['archivo']) && !empty($item['archivo'])) {
                    Storage::disk('public')->delete($item['archivo']);
                }
                foreach ($item as $valor) {
                    $eliminarArchivos($valor);
                }
            }
        };

        $eliminarArchivos($datos);
        $evidencia->delete();

        return redirect()->route('seguimiento-academico')->with('success', 'Evidencia eliminada permanentemente.');
    }
}