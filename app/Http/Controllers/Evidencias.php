<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Revision;
use App\Models\AsignacionMateria;
use App\Models\Docente;
use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Evidencias extends Controller
{
    public function index()
    {
        $titulo = "Gestión de evidencias";

        $user = Auth::user();
        $docente = Docente::where('user_id', $user->id)->first();

        $docenteId = $docente ? $docente->id : $user->id;

        $semestreActivo = \App\Models\Semestre::where('activo', 1)->first();

        $asignaciones = AsignacionMateria::with([
            'materia',
            'evidencias',
        ])
            ->where('docente_id', $docenteId)
            ->where('activo', 1)
            ->when($semestreActivo, function ($query) use ($semestreActivo) {
                $query->where('semestre_id', $semestreActivo->id);
            })
            ->whereHas('materia', function ($q) {
                $q->where('activo', 1);
            })
            ->get()
            ->sortBy([
                fn($a, $b) => strcmp($a->materia?->nombre ?? '', $b->materia?->nombre ?? ''),
                fn($a, $b) => strcmp($a->grupo ?? '', $b->grupo ?? ''),
            ])
            ->values();

        $revisiones = Revision::orderBy('numero', 'asc')->get();

        return view('modules.evidencias.index', compact('asignaciones', 'titulo', 'revisiones'));
    }

    public function create()
    {
        $docenteId = auth()->id();
        $semestreActivo = \App\Models\Semestre::where('activo', 1)->first();

        $asignaciones = AsignacionMateria::with('materia')
            ->where('docente_id', $docenteId)
            ->where('activo', 1)
            ->when($semestreActivo, function ($q) use ($semestreActivo) {
                $q->where('semestre_id', $semestreActivo->id);
            })
            ->get();

        $revisiones = Revision::where('activo', true)->orderBy('numero')->get();

        $evidenciasSubidas = Evidencia::whereHas('asignacionMateria', function ($query) use ($docenteId) {
            $query->where('docente_id', $docenteId);
        })->get();

        $subidasArray = [];
        $unidadesUtilizadasPorMateria = [];
        $unidadesEvaluadasPorMateria = [];
        $hashesExistentes = [];

        foreach ($evidenciasSubidas as $ev) {
            // Nota: usamos el ID de la asignación para diferenciar grupos
            $subidasArray[] = "{$ev->asignacion_materia_id}-{$ev->revision_id}";

            $docs = is_array($ev->documentos)
                ? $ev->documentos
                : json_decode($ev->documentos ?? '[]', true);

            if (!is_array($docs)) {
                $docs = [];
            }

            $unidades = $docs['unidades'] ?? [];

            // Asociamos el tracking a la asignación (o a la materia_id según tu lógica de BD)
            $key = $ev->asignacion_materia_id ?? $ev->materia_id;

            if (!isset($unidadesUtilizadasPorMateria[$key])) {
                $unidadesUtilizadasPorMateria[$key] = [];
            }

            $unidadesReales = array_filter($unidades, function ($unidad) {
                return (int) $unidad > 0;
            });

            $unidadesUtilizadasPorMateria[$key] = array_merge(
                $unidadesUtilizadasPorMateria[$key],
                $unidadesReales
            );

            $unidadesUtilizadasPorMateria[(string) $key] = array_values(
                array_unique($unidadesUtilizadasPorMateria[$key])
            );

            $numeroRevision = optional($ev->revision)->numero;

            if ($numeroRevision && (int) $numeroRevision <= 4) {
                if (!isset($unidadesEvaluadasPorMateria[$key])) {
                    $unidadesEvaluadasPorMateria[$key] = [];
                }

                $unidadesEvaluadasPorMateria[$key] = array_merge(
                    $unidadesEvaluadasPorMateria[$key],
                    array_map('intval', $unidadesReales)
                );

                $unidadesEvaluadasPorMateria[(string) $key] = array_values(
                    array_unique($unidadesEvaluadasPorMateria[$key])
                );
                sort($unidadesEvaluadasPorMateria[(string) $key]);
            }

            if (!empty($docs['hashes']) && is_array($docs['hashes'])) {
                foreach ($docs['hashes'] as $h) {
                    if (is_string($h) && $h !== '') {
                        $hashesExistentes[] = $h;
                    }
                }
            }
        }

        $hashesExistentes = array_values(array_unique($hashesExistentes));

        return view('modules.evidencias.create', compact(
            'asignaciones', // <-- Enviamos la colección de asignaciones a la vista
            'revisiones',
            'subidasArray',
            'unidadesUtilizadasPorMateria',
            'unidadesEvaluadasPorMateria',
            'hashesExistentes'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $docente = Docente::where('user_id', $user->id)->first();

        $idsDocentePermitidos = [$user->id];
        if ($docente) {
            $idsDocentePermitidos[] = $docente->id;
        }

        $materia  = Materia::findOrFail($request->materia_id);
        $revision = Revision::findOrFail($request->revision_id);

        $esRevision5 = (int) $revision->numero === 5;
        $esRevision4 = (int) $revision->numero === 4;
        $esPrimeraRevision = (int) $revision->numero === 1;

        $asignacion = AsignacionMateria::where('materia_id', $materia->id)
            ->whereIn('docente_id', array_unique($idsDocentePermitidos))
            ->firstOrFail();

        $unidadesInput = array_values(array_unique(array_map(
            'intval',
            $request->input('unidades', [])
        )));

        $otrasEvidencias = Evidencia::where('asignacion_materia_id', $asignacion->id)->get();
        $unidadesOcupadas = [];

        foreach ($otrasEvidencias as $otraEvidencia) {
            $datosOtra = is_array($otraEvidencia->documentos)
                ? $otraEvidencia->documentos
                : json_decode($otraEvidencia->documentos ?? '[]', true);

            if (!is_array($datosOtra)) continue;

            foreach (($datosOtra['unidades'] ?? []) as $unidadOtra) {
                $unidadOtra = (int) $unidadOtra;
                if ($unidadOtra !== 0) {
                    $unidadesOcupadas[] = $unidadOtra;
                }
            }
        }
        $unidadesOcupadas = array_values(array_unique($unidadesOcupadas));

        $totalUnidadesMateria = (int) ($materia->unidades ?? 0);

        if ($esRevision5) {
            return $this->guardarSegundasOportunidades(
                $request,
                $materia,
                $revision,
                $asignacion,
                $unidadesInput,
                $otrasEvidencias
            );
        }

        $unidadesDisponibles = [];
        for ($i = 1; $i <= $totalUnidadesMateria; $i++) {
            if (!in_array($i, $unidadesOcupadas, true)) {
                $unidadesDisponibles[] = $i;
            }
        }
        $sinUnidadesDisponibles = count($unidadesDisponibles) === 0;

        if (empty($unidadesInput) && $sinUnidadesDisponibles) {
            $unidadesInput = [0];
        }

        $esNingunaUnidad = in_array(0, $unidadesInput, true);

        if (empty($unidadesInput)) {
            return back()
                ->withErrors(['unidades' => 'Debes seleccionar al menos una unidad o marcar Ninguna Unidad cuando aplique.'])
                ->withInput();
        }

        if ($esNingunaUnidad && count($unidadesInput) > 1) {
            return back()
                ->withErrors(['unidades' => 'No puedes seleccionar Ninguna Unidad junto con otras unidades.'])
                ->withInput();
        }

        if ($esRevision4) {
            $reglaCuarta = 'required|file|mimes:pdf|max:2048';

            $request->validate([
                'actas' => $reglaCuarta,
                'calificaciones_finales' => $reglaCuarta,
            ], [
                'actas.required' => 'El archivo de Actas es obligatorio en la Revisión 4.',
                'actas.mimes'    => 'El archivo de Actas debe estar en formato PDF.',
                'actas.max'      => 'El archivo de Actas no debe pesar más de 2 MB.',

                'calificaciones_finales.required' => 'La lista de calificaciones finales es obligatoria en la Revisión 4.',
                'calificaciones_finales.mimes'    => 'La lista de calificaciones finales debe estar en formato PDF.',
                'calificaciones_finales.max'      => 'La lista de calificaciones finales no debe pesar más de 2 MB.',
            ]);
        }

        foreach ($unidadesInput as $unidadSeleccionada) {
            if ($unidadSeleccionada === 0) continue;

            if ($unidadSeleccionada < 1 || $unidadSeleccionada > $totalUnidadesMateria) {
                return back()->withErrors(['unidades' => "La Unidad {$unidadSeleccionada} no pertenece a esta materia."])->withInput();
            }
            if (in_array($unidadSeleccionada, $unidadesOcupadas, true)) {
                return back()->withErrors(['unidades' => "La Unidad {$unidadSeleccionada} ya fue evaluada en otra revisión."])->withInput();
            }
        }

        $reglaArchivoGeneral = $esPrimeraRevision
            ? 'required|file|mimes:pdf|max:2048'
            : 'nullable|file|mimes:pdf|max:2048';

        $request->validate([
            'materia_id'    => 'required|exists:materias,id',
            'revision_id'   => 'required|exists:revisiones,id',
            'unidades'      => 'required|array',
            'unidades.*'    => 'integer',

            'motivo_no_evaluo' => $esNingunaUnidad
                ? 'required|string|min:5|max:1000'
                : 'nullable|string|max:1000',

            'instrumentacion'      => $reglaArchivoGeneral,
            'reporte_inicio'       => $reglaArchivoGeneral,
            'acuerdos'             => $reglaArchivoGeneral,
            'examen_diagnostico'   => $reglaArchivoGeneral,
            'analisis_diagnostico' => $reglaArchivoGeneral,

            'calificaciones' => $esNingunaUnidad ? 'nullable|array' : 'required|array',
            'calificaciones.*' => 'file|mimes:pdf|max:2048',

            'rac'    => 'nullable|array',
            'rac.*'  => 'nullable|file|mimes:pdf|max:2048',
            'rac_na' => 'nullable|array',
            'rac_na.*' => 'nullable|boolean',

            'rubricas'     => $esNingunaUnidad ? 'nullable|array' : 'required|array',
            'rubricas.*'   => 'array|max:3',
            'rubricas.*.*' => 'file|mimes:pdf|max:2048',

            'instrumentos'    => 'nullable|array',
            'instrumentos.*'  => 'array',
            'instrumentos.*.*' => 'file|mimes:pdf|max:2048',
        ], [
            '*.mimes' => 'Todos los archivos deben ser documentos PDF válidos.',
            '*.max'   => 'Cada archivo PDF no debe pesar más de 2 MB.',
            'rubricas.*.max'      => 'Cada unidad admite máximo 3 PDFs de rúbricas.',
            'rubricas.*.*.mimes'  => 'Todos los archivos deben ser documentos PDF válidos.',
            'rubricas.*.*.max'    => 'Cada archivo PDF no debe pesar más de 2 MB.',
            'motivo_no_evaluo.required' => 'Debes escribir el motivo.',
            'motivo_no_evaluo.min'      => 'El motivo debe tener al menos 5 caracteres.',
            'motivo_no_evaluo.max'      => 'El motivo no debe superar los 1000 caracteres.',
            'instrumentacion.required'      => 'La instrumentación didáctica es obligatoria en la primera revisión.',
            'reporte_inicio.required'       => 'El reporte de inicio de curso es obligatorio en la primera revisión.',
            'acuerdos.required'             => 'Los acuerdos de clase son obligatorios en la primera revisión.',
            'examen_diagnostico.required'   => 'El examen diagnóstico es obligatorio en la primera revisión.',
            'analisis_diagnostico.required' => 'El análisis del diagnóstico es obligatorio en la primera revisión.',
            'calificaciones.required' => 'Debes subir la lista de calificaciones.',
            'rubricas.required'       => 'Debes subir las rúbricas.',
        ]);

        $motivoNoEvaluo = $esNingunaUnidad
            ? trim((string) $request->input('motivo_no_evaluo'))
            : null;

        if (!$esNingunaUnidad) {
            $racNaUnidades = $request->input('rac_na', []);
            $errores = [];

            foreach ($unidadesInput as $numUnidad) {
                if (!$request->hasFile("calificaciones.{$numUnidad}")) {
                    $errores["calificaciones.{$numUnidad}"] = "La lista de calificaciones de la Unidad {$numUnidad} es obligatoria.";
                }
                if (!$request->hasFile("rubricas.{$numUnidad}")) {
                    $errores["rubricas.{$numUnidad}"] = "La rúbrica de la Unidad {$numUnidad} es obligatoria.";
                }
                $racEsNoAplica = array_key_exists($numUnidad, $racNaUnidades)
                    || array_key_exists((string) $numUnidad, $racNaUnidades);

                if (!$racEsNoAplica && !$request->hasFile("rac.{$numUnidad}")) {
                    $errores["rac.{$numUnidad}"] = "Debes subir el RAC de la Unidad {$numUnidad} o marcar No aplica.";
                }
            }

            if (!empty($errores)) {
                return back()->withErrors($errores)->withInput();
            }
        }

        $semestreNombre = $this->limpiarNombre($asignacion->semestre->nombre ?? 'SIN_SEMESTRE');
        $materiaNombre  = $this->limpiarNombre($materia->nombre);
        $revisionNombre = $this->limpiarNombre($revision->nombre);

        $basePath = "evidencias_pdf/{$semestreNombre}/{$materiaNombre}/{$revisionNombre}";

        $documentos = [];
        $evidencias = [];
        $instrumentosGrupales = [];
        $instrumentosNa = false;
        $hashesNuevos = [];

        if ($esRevision4) {
            $fileActa = $request->file('actas');
            $documentos['acta'] = $fileActa->storeAs(
                $basePath . '/documentos',
                'acta_revision_4.pdf',
                'public'
            );
            $hashesNuevos[] = md5_file($fileActa->getRealPath());

            $fileCalFin = $request->file('calificaciones_finales');
            $documentos['calificaciones_finales'] = $fileCalFin->storeAs(
                $basePath . '/documentos',
                'calificaciones_finales_revision_4.pdf',
                'public'
            );
            $hashesNuevos[] = md5_file($fileCalFin->getRealPath());
        }

        if ($esPrimeraRevision) {
            foreach (['instrumentacion', 'reporte_inicio', 'acuerdos'] as $field) {
                $file = $request->file($field);
                $documentos[$field] = $file->storeAs(
                    $basePath . '/documentos',
                    $field . '.pdf',
                    'public'
                );
                $hashesNuevos[] = md5_file($file->getRealPath());
            }
            foreach (['examen_diagnostico', 'analisis_diagnostico'] as $field) {
                $file = $request->file($field);
                $evidencias[$field] = $file->storeAs(
                    $basePath . '/evidencias',
                    $field . '.pdf',
                    'public'
                );
                $hashesNuevos[] = md5_file($file->getRealPath());
            }
        } else {
            foreach (['instrumentacion', 'reporte_inicio', 'acuerdos'] as $campo) {
                $documentos[$campo] = ['na' => true, 'archivo' => null];
            }
            foreach (['examen_diagnostico', 'analisis_diagnostico'] as $campo) {
                $evidencias[$campo] = ['na' => true, 'archivo' => null];
            }
        }

        if ($esNingunaUnidad) {
            $unidades = [0];
            $documentos['calificaciones'] = ['na' => true, 'archivo' => null, 'motivo' => $motivoNoEvaluo];
            $documentos['calificaciones_detalladas'] = ['u0' => ['na' => true, 'archivo' => null, 'motivo' => $motivoNoEvaluo]];
            $documentos['rac'] = ['na' => true, 'archivo' => null];
            $documentos['rac_detallado'] = ['u0' => ['na' => true, 'archivo' => null]];
            $evidencias['rubricas'] = ['na' => true, 'archivo' => null];
            $evidencias['rubricas_detalladas'] = ['u0' => ['na' => true, 'archivo' => null]];
            $instrumentosGrupales = [];
            $instrumentosNa = true;
        } else {
            $unidades = array_values(array_filter($unidadesInput, fn($u) => (int) $u !== 0));
            $racNaUnidades = $request->input('rac_na', []);

            foreach ($unidades as $index => $numUnidad) {
                if ($request->hasFile("calificaciones.{$numUnidad}")) {
                    $file = $request->file("calificaciones.{$numUnidad}");
                    $pathCal = $file->storeAs(
                        $basePath . '/documentos',
                        "calificaciones_u{$numUnidad}.pdf",
                        'public'
                    );
                    $documentos['calificaciones_detalladas']["u{$numUnidad}"] = ['na' => false, 'archivo' => $pathCal];
                    if ($index === 0) $documentos['calificaciones'] = ['na' => false, 'archivo' => $pathCal];
                    $hashesNuevos[] = md5_file($file->getRealPath());
                }

                $racEsNoAplica = array_key_exists($numUnidad, $racNaUnidades)
                    || array_key_exists((string) $numUnidad, $racNaUnidades);

                if ($racEsNoAplica) {
                    $documentos['rac_detallado']["u{$numUnidad}"] = ['na' => true, 'archivo' => null];
                    if ($index === 0) $documentos['rac'] = ['na' => true, 'archivo' => null];
                } else {
                    $file = $request->file("rac.{$numUnidad}");
                    $pathRac = $file->storeAs(
                        $basePath . '/documentos',
                        "RAC_u{$numUnidad}.pdf",
                        'public'
                    );
                    $documentos['rac_detallado']["u{$numUnidad}"] = ['na' => false, 'archivo' => $pathRac];
                    if ($index === 0) $documentos['rac'] = ['na' => false, 'archivo' => $pathRac];
                    $hashesNuevos[] = md5_file($file->getRealPath());
                }

                if ($request->hasFile("rubricas.{$numUnidad}")) {
                    $archivosRubrica = $request->file("rubricas.{$numUnidad}");
                    if (!is_array($archivosRubrica)) $archivosRubrica = [$archivosRubrica];

                    $rutasRubrica = [];
                    foreach (array_values(array_filter($archivosRubrica)) as $fileIndex => $file) {
                        if ($fileIndex >= 3) break;
                        $rutasRubrica[] = $file->storeAs(
                            $basePath . '/evidencias',
                            "rubricas_u{$numUnidad}_" . ($fileIndex + 1) . ".pdf",
                            'public'
                        );
                        $hashesNuevos[] = md5_file($file->getRealPath());
                    }

                    // 'archivo' = primer PDF (compatibilidad con vistas viejas), 'archivos' = todos
                    $datosRubrica = [
                        'na'       => false,
                        'archivo'  => $rutasRubrica[0] ?? null,
                        'archivos' => $rutasRubrica,
                    ];
                    $evidencias['rubricas_detalladas']["u{$numUnidad}"] = $datosRubrica;
                    if ($index === 0) $evidencias['rubricas'] = $datosRubrica;
                }

                if ($request->hasFile("instrumentos.{$numUnidad}")) {
                    foreach ($request->file("instrumentos.{$numUnidad}") as $fileIndex => $file) {
                        if ($fileIndex >= 3) break;
                        $nombreInstrumento = "instrumento_u{$numUnidad}_" . ($fileIndex + 1) . ".pdf";
                        $instrumentosGrupales[] = $file->storeAs(
                            $basePath . '/instrumentos',
                            $nombreInstrumento,
                            'public'
                        );
                        $hashesNuevos[] = md5_file($file->getRealPath());
                    }
                }
            }
        }

        $json = [
            'unidades'          => $unidades,
            'motivo_no_evaluo'  => $motivoNoEvaluo,
            'documentos'        => $documentos,
            'evidencias'        => $evidencias,
            'instrumentos'      => $instrumentosGrupales,
            'instrumentos_na'   => $instrumentosNa,
            'hashes'            => array_values(array_unique($hashesNuevos)),
        ];

        Evidencia::updateOrCreate(
            [
                'asignacion_materia_id' => $asignacion->id,
                'revision_id'           => $revision->id,
            ],
            [
                'materia_id' => $materia->id,
                'documentos' => $json,
                'estado'     => 3,
            ]
        );

        return redirect()
            ->route('evidencias')
            ->with('success', 'Evidencia guardada correctamente');
    }

    private function guardarSegundasOportunidades(
        Request $request,
        Materia $materia,
        Revision $revision,
        AsignacionMateria $asignacion,
        array $unidadesInput,
        $otrasEvidencias
    ) {
        $unidadesEvaluadas = [];

        foreach ($otrasEvidencias as $otraEvidencia) {
            $numeroRev = optional($otraEvidencia->revision)->numero;
            if (!$numeroRev || (int) $numeroRev > 4) continue;

            $datosOtra = is_array($otraEvidencia->documentos)
                ? $otraEvidencia->documentos
                : json_decode($otraEvidencia->documentos ?? '[]', true);

            if (!is_array($datosOtra)) continue;

            foreach (($datosOtra['unidades'] ?? []) as $u) {
                $u = (int) $u;
                if ($u > 0) $unidadesEvaluadas[] = $u;
            }
        }
        $unidadesEvaluadas = array_values(array_unique($unidadesEvaluadas));
        sort($unidadesEvaluadas);

        if (empty($unidadesEvaluadas)) {
            return back()
                ->withErrors(['unidades' => 'No hay unidades evaluadas aún. Realiza primero tus revisiones y sube tus evidencias.'])
                ->withInput();
        }

        $unidadesSeleccionadas = array_values(array_filter($unidadesInput, fn($u) => (int) $u > 0));

        if (empty($unidadesSeleccionadas)) {
            return back()
                ->withErrors(['unidades' => 'Debes seleccionar al menos una unidad evaluada.'])
                ->withInput();
        }

        foreach ($unidadesSeleccionadas as $u) {
            if (!in_array($u, $unidadesEvaluadas, true)) {
                return back()
                    ->withErrors(['unidades' => "La Unidad {$u} no está entre las que ya evaluaste."])
                    ->withInput();
            }
        }

        $request->validate([
            'materia_id'                => 'required|exists:materias,id',
            'revision_id'               => 'required|exists:revisiones,id',
            'unidades'                  => 'required|array',
            'unidades.*'                => 'integer',

            'rac_segundas'              => 'nullable|array',
            'rac_segundas.*'            => 'file|mimes:pdf|max:2048',
            'rubricas_segundas'         => 'nullable|array',
            'rubricas_segundas.*'       => 'file|mimes:pdf|max:2048',
            'calificaciones_segundas'   => 'nullable|array',
            'calificaciones_segundas.*' => 'file|mimes:pdf|max:2048',
        ], [
            '*.mimes' => 'Todos los archivos deben ser PDF.',
            '*.max'   => 'Cada PDF no debe pesar más de 2 MB.',
        ]);

        $hash = function ($file) {
            return md5_file($file->getRealPath());
        };

        $evidenciaActual = Evidencia::where('asignacion_materia_id', $asignacion->id)
            ->where('revision_id', $revision->id)
            ->first();

        $documentos = [];
        $hashesExistentes = [];

        if ($evidenciaActual) {
            $documentos = is_array($evidenciaActual->documentos)
                ? $evidenciaActual->documentos
                : json_decode($evidenciaActual->documentos ?? '[]', true);

            if (!is_array($documentos)) $documentos = [];

            $hashesExistentes = $documentos['hashes'] ?? [];
            if (!is_array($hashesExistentes)) $hashesExistentes = [];
        }

        $hashesSubidos = [];
        $hashesNuevos = [];

        foreach (['rac_segundas', 'rubricas_segundas', 'calificaciones_segundas'] as $campo) {
            $files = $request->file($campo) ?? [];
            if (!is_array($files)) $files = [$files];

            foreach ($files as $file) {
                if (!$file) continue;

                $h = $hash($file);

                if (in_array($h, $hashesExistentes, true) || in_array($h, $hashesSubidos, true)) {
                    return back()
                        ->withErrors(['archivos' => "El archivo '{$file->getClientOriginalName()}' ya fue subido antes. No se permiten duplicados."])
                        ->withInput();
                }

                $hashesSubidos[] = $h;
                $hashesNuevos[] = $h;
            }
        }

        $semestreNombre = $this->limpiarNombre($asignacion->semestre->nombre ?? 'SIN_SEMESTRE');
        $materiaNombre  = $this->limpiarNombre($materia->nombre);

        $basePath = "evidencias_pdf/{$semestreNombre}/{$materiaNombre}/Segundas_Oportunidades";

        $documentos['hashes'] = $hashesExistentes;

        foreach (['rac_segundas', 'rubricas_segundas', 'calificaciones_segundas'] as $campo) {
            $documentos[$campo] = $documentos[$campo] ?? [];

            if (!is_array($documentos[$campo])) {
                $documentos[$campo] = $documentos[$campo] ? [$documentos[$campo]] : [];
            }

            $files = $request->file($campo) ?? [];
            if (!is_array($files)) $files = [$files];

            foreach ($files as $file) {
                if (!$file) continue;

                $nombreBase = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $nombreBase = $this->limpiarNombre($nombreBase);
                $nombreFinal = $nombreBase . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6) . '.pdf';

                $path = $file->storeAs($basePath, $nombreFinal, 'public');

                $documentos[$campo][] = $path;
            }
        }

        foreach ($hashesNuevos as $h) {
            $documentos['hashes'][] = $h;
        }

        $documentos['hashes'] = array_values(array_unique($documentos['hashes']));

        $unidadesGuardadas = $documentos['unidades'] ?? [];
        if (!is_array($unidadesGuardadas)) $unidadesGuardadas = [];
        $unidadesGuardadas = array_values(array_unique(array_merge(
            array_map('intval', $unidadesGuardadas),
            $unidadesSeleccionadas
        )));
        sort($unidadesGuardadas);

        $documentos['unidades'] = $unidadesGuardadas;

        Evidencia::updateOrCreate(
            [
                'asignacion_materia_id' => $asignacion->id,
                'revision_id'           => $revision->id,
            ],
            [
                'materia_id' => $materia->id,
                'documentos' => $documentos,
                'estado'     => 3,
            ]
        );

        return redirect()
            ->route('evidencias')
            ->with('success', 'Evidencias de Segundas Oportunidades guardadas correctamente');
    }

    public function edit($id)
    {
        $evidencia = Evidencia::with([
            'materia',
            'revision',
            'admin'
        ])->findOrFail($id);

        $user = Auth::user();
        $docente = Docente::where('user_id', $user->id)->first();

        $idsDocentePermitidos = [$user->id];
        if ($docente) {
            $idsDocentePermitidos[] = $docente->id;
        }

        $item = $evidencia;

        $asignacion = AsignacionMateria::where('id', $evidencia->asignacion_materia_id)
            ->whereIn('docente_id', array_unique($idsDocentePermitidos))
            ->firstOrFail();

        $datos = is_array($evidencia->documentos)
            ? $evidencia->documentos
            : json_decode($evidencia->documentos ?? '[]', true);

        if (!is_array($datos)) {
            $datos = [];
        }

        $esRevision5 = (int) ($evidencia->revision->numero ?? 0) === 5;

        if ($esRevision5) {
            return $this->editSegundasOportunidades($evidencia, $asignacion, $datos);
        }

        $unidades = $datos['unidades'] ?? [];
        $documentos = $datos['documentos'] ?? [];
        $evidencias = $datos['evidencias'] ?? [];
        $instrumentos = $datos['instrumentos'] ?? []; // Recuperamos los instrumentos

        $motivoNoEvaluo = $datos['motivo_no_evaluo']
            ?? ($documentos['calificaciones']['motivo'] ?? null)
            ?? ($documentos['calificaciones_detalladas']['u0']['motivo'] ?? null)
            ?? '';

        $unidades = array_values(array_map('intval', $unidades));

        $otrasEvidencias = Evidencia::where('asignacion_materia_id', $evidencia->asignacion_materia_id)
            ->where('id', '!=', $evidencia->id)
            ->get();

        $unidadesOcupadas = [];
        $hashesExistentes = [];

        // Hashes de la evidencia actual
        if (!empty($datos['hashes']) && is_array($datos['hashes'])) {
            foreach ($datos['hashes'] as $h) {
                if (is_string($h) && $h !== '') {
                    $hashesExistentes[] = $h;
                }
            }
        }

        foreach ($otrasEvidencias as $otraEvidencia) {
            $datosOtra = is_array($otraEvidencia->documentos)
                ? $otraEvidencia->documentos
                : json_decode($otraEvidencia->documentos ?? '[]', true);

            if (!is_array($datosOtra)) continue;

            foreach (($datosOtra['unidades'] ?? []) as $unidad) {
                $unidad = (int) $unidad;
                if ($unidad !== 0) $unidadesOcupadas[] = $unidad;
            }

            // Hashes de otras evidencias del mismo docente+materia
            if (!empty($datosOtra['hashes']) && is_array($datosOtra['hashes'])) {
                foreach ($datosOtra['hashes'] as $h) {
                    if (is_string($h) && $h !== '') {
                        $hashesExistentes[] = $h;
                    }
                }
            }
        }

        $unidadesOcupadas = array_values(array_unique($unidadesOcupadas));
        $hashesExistentes = array_values(array_unique($hashesExistentes));

        $totalUnidadesMateria = (int) ($evidencia->materia->unidades ?? 0);

        $unidadesActualesSinCero = array_values(array_filter($unidades, function ($unidad) {
            return (int) $unidad !== 0;
        }));

        $unidadesDisponiblesParaEditar = [];

        for ($i = 1; $i <= $totalUnidadesMateria; $i++) {
            $unidadEstaOcupada = in_array($i, $unidadesOcupadas, true);
            $unidadEsActual = in_array($i, $unidadesActualesSinCero, true);

            if (!$unidadEstaOcupada || $unidadEsActual) {
                $unidadesDisponiblesParaEditar[] = $i;
            }
        }

        if (count($unidadesDisponiblesParaEditar) === 0 && count($unidadesActualesSinCero) === 0) {
            $unidades = [0];
        }

        // --- PREPARAR ITEMS PARA EL VISOR DE ARCHIVOS EN LA VISTA DE EDICIÓN ---
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
                'key'                => 'calificaciones',
                'nombre'             => 'Lista de calificaciones',
                'archivo'            => $archivosCalificaciones[0] ?? null,
                'archivos_multiples' => $archivosCalificaciones,
                'documento_na'       => empty($archivosCalificaciones) && !empty($documentos['calificaciones']['na']),
                'motivo_no_evaluo'   => $motivoNoEvaluo,
            ],
            [
                'key'                => 'rac',
                'nombre'             => 'Actividades de Regularización (RAC)',
                'archivo'            => $archivosRac[0] ?? null,
                'archivos_multiples' => $archivosRac,
                'documento_na'       => empty($archivosRac) && !empty($documentos['rac']['na']),
                'motivo_no_evaluo'   => $motivoNoEvaluo,
            ],
            [
                'key'                => 'rubricas',
                'nombre'             => 'Rúbricas del semestre',
                'archivo'            => $archivosRubricas[0] ?? null,
                'archivos_multiples' => $archivosRubricas,
                'documento_na'       => empty($archivosRubricas) && !empty($evidencias['rubricas']['na']),
                'motivo_no_evaluo'   => $motivoNoEvaluo,
            ],
            [
                'key'                => 'instrumentos',
                'nombre'             => 'Instrumentos de evaluación individuales',
                'archivo'            => $archivosInstrumentos[0] ?? null,
                'archivos_multiples' => $archivosInstrumentos,
                'documento_na'       => !empty($datos['instrumentos_na']),
                'motivo_no_evaluo'   => $motivoNoEvaluo,
            ],
        ];

        return view('modules.evidencias.edit', compact(
            'evidencia',
            'unidades',
            'documentos',
            'evidencias',
            'instrumentos',
            'items',
            'unidadesOcupadas',
            'item',
            'motivoNoEvaluo',
            'hashesExistentes'
        ));
    }

    private function editSegundasOportunidades($evidencia, $asignacion, $datos)
    {
        $unidadesGuardadas = array_values(array_map('intval', $datos['unidades'] ?? []));

        $otrasEvidencias = Evidencia::where('asignacion_materia_id', $evidencia->asignacion_materia_id)
            ->where('id', '!=', $evidencia->id)
            ->get();

        $unidadesEvaluadas = [];

        foreach ($otrasEvidencias as $otraEvidencia) {
            $numeroRev = optional($otraEvidencia->revision)->numero;
            if (!$numeroRev || (int) $numeroRev > 4) continue;

            $datosOtra = is_array($otraEvidencia->documentos)
                ? $otraEvidencia->documentos
                : json_decode($otraEvidencia->documentos ?? '[]', true);

            if (!is_array($datosOtra)) continue;

            foreach (($datosOtra['unidades'] ?? []) as $u) {
                $u = (int) $u;
                if ($u > 0) $unidadesEvaluadas[] = $u;
            }
        }

        $unidadesEvaluadas = array_values(array_unique($unidadesEvaluadas));
        sort($unidadesEvaluadas);

        $normalizarArray = function ($valor) {
            if (is_array($valor)) {
                return array_values(array_filter($valor, fn($v) => is_string($v) && $v !== ''));
            }

            if (is_string($valor) && trim($valor) !== '') {
                return [$valor];
            }

            return [];
        };

        $racSegundas = $normalizarArray($datos['rac_segundas'] ?? []);
        $rubricasSegundas = $normalizarArray($datos['rubricas_segundas'] ?? []);
        $calificacionesSegundas = $normalizarArray($datos['calificaciones_segundas'] ?? []);

        $hashesExistentes = [];
        if (!empty($datos['hashes']) && is_array($datos['hashes'])) {
            foreach ($datos['hashes'] as $h) {
                if (is_string($h) && $h !== '') {
                    $hashesExistentes[] = $h;
                }
            }
        }

        foreach ($otrasEvidencias as $otraEvidencia) {
            $datosOtra = is_array($otraEvidencia->documentos)
                ? $otraEvidencia->documentos
                : json_decode($otraEvidencia->documentos ?? '[]', true);

            if (!is_array($datosOtra)) continue;

            if (!empty($datosOtra['hashes']) && is_array($datosOtra['hashes'])) {
                foreach ($datosOtra['hashes'] as $h) {
                    if (is_string($h) && $h !== '') {
                        $hashesExistentes[] = $h;
                    }
                }
            }
        }

        $hashesExistentes = array_values(array_unique($hashesExistentes));

        return view('modules.evidencias.edit-segundas', compact(
            'evidencia',
            'unidadesGuardadas',
            'unidadesEvaluadas',
            'racSegundas',
            'rubricasSegundas',
            'calificacionesSegundas',
            'hashesExistentes'
        ));
    }

    public function update(Request $request, $id)
    {
        $evidencia = Evidencia::with([
            'materia',
            'revision'
        ])->findOrFail($id);

        $esRevision5 = (int) ($evidencia->revision->numero ?? 0) === 5;

        if ($esRevision5) {
            return $this->actualizarSegundasOportunidades($request, $evidencia);
        }

        $estadoActual = strtolower((string) ($evidencia->estado ?? ''));

        if (in_array($estadoActual, ['2', 'aprobado', 'aprobada'], true)) {
            return back()
                ->withErrors([
                    'estado' => 'Esta evidencia ya fue aprobada y no puede modificarse.',
                ])
                ->withInput();
        }

        $user = Auth::user();
        $docente = Docente::where('user_id', $user->id)->first();

        $idsDocentePermitidos = [$user->id];
        if ($docente) {
            $idsDocentePermitidos[] = $docente->id;
        }

        $asignacion = AsignacionMateria::where('id', $evidencia->asignacion_materia_id)
            ->whereIn('docente_id', array_unique($idsDocentePermitidos))
            ->firstOrFail();

        $materia = $evidencia->materia;
        $revision = $evidencia->revision;

        $datosActuales = is_array($evidencia->documentos)
            ? $evidencia->documentos
            : json_decode($evidencia->documentos ?? '[]', true);

        if (!is_array($datosActuales)) {
            $datosActuales = [];
        }

        $datosActuales['documentos'] = $datosActuales['documentos'] ?? [];
        $datosActuales['evidencias'] = $datosActuales['evidencias'] ?? [];
        $datosActuales['instrumentos'] = $datosActuales['instrumentos'] ?? [];
        $datosActuales['instrumentos_na'] = $datosActuales['instrumentos_na'] ?? false;

        $unidadesSeleccionadas = array_values(array_unique(array_map(
            'intval',
            $request->input('unidades', [])
        )));

        $esPrimeraRevision = (int) $revision->numero === 1;

        $otrasEvidencias = Evidencia::where('asignacion_materia_id', $evidencia->asignacion_materia_id)
            ->where('id', '!=', $evidencia->id)
            ->get();

        $unidadesOcupadas = [];

        foreach ($otrasEvidencias as $otraEvidencia) {
            $datosOtra = is_array($otraEvidencia->documentos)
                ? $otraEvidencia->documentos
                : json_decode($otraEvidencia->documentos ?? '[]', true);

            if (!is_array($datosOtra)) continue;

            foreach (($datosOtra['unidades'] ?? []) as $unidadOtra) {
                $unidadOtra = (int) $unidadOtra;
                if ($unidadOtra !== 0) $unidadesOcupadas[] = $unidadOtra;
            }
        }

        $unidadesOcupadas = array_values(array_unique($unidadesOcupadas));

        $totalUnidadesMateria = (int) ($materia->unidades ?? 0);

        $unidadesActuales = array_values(array_map(
            'intval',
            $datosActuales['unidades'] ?? []
        ));

        $unidadesActualesSinCero = array_values(array_filter($unidadesActuales, function ($unidad) {
            return (int) $unidad !== 0;
        }));

        $unidadesDisponiblesParaEditar = [];

        for ($i = 1; $i <= $totalUnidadesMateria; $i++) {
            $unidadEstaOcupada = in_array($i, $unidadesOcupadas, true);
            $unidadEsActual = in_array($i, $unidadesActualesSinCero, true);

            if (!$unidadEstaOcupada || $unidadEsActual) {
                $unidadesDisponiblesParaEditar[] = $i;
            }
        }

        $sinUnidadesDisponibles = count($unidadesDisponiblesParaEditar) === 0;

        if (empty($unidadesSeleccionadas) && $sinUnidadesDisponibles) {
            $unidadesSeleccionadas = [0];
        }

        $esNingunaUnidad = in_array(0, $unidadesSeleccionadas, true);

        if (empty($unidadesSeleccionadas)) {
            return back()
                ->withErrors([
                    'unidades' => 'Debes seleccionar al menos una unidad o marcar Ninguna Unidad cuando aplique.',
                ])
                ->withInput();
        }

        if ($esNingunaUnidad && count($unidadesSeleccionadas) > 1) {
            return back()
                ->withErrors([
                    'unidades' => 'No puedes seleccionar Ninguna Unidad junto con otras unidades.',
                ])
                ->withInput();
        }

        if ($esNingunaUnidad && !$esPrimeraRevision && !$sinUnidadesDisponibles) {
            return back()
                ->withErrors([
                    'unidades' => 'La opción Ninguna Unidad solo está permitida en la primera revisión o cuando ya no hay unidades disponibles.',
                ])
                ->withInput();
        }

        foreach ($unidadesSeleccionadas as $unidadSeleccionada) {
            if ($unidadSeleccionada === 0) continue;

            if ($unidadSeleccionada < 1 || $unidadSeleccionada > $totalUnidadesMateria) {
                return back()->withErrors([
                    'unidades' => "La Unidad {$unidadSeleccionada} no pertenece a esta materia.",
                ])->withInput();
            }

            if (in_array($unidadSeleccionada, $unidadesOcupadas, true)) {
                return back()->withErrors([
                    'unidades' => "La Unidad {$unidadSeleccionada} ya fue evaluada en otra revisión.",
                ])->withInput();
            }
        }

        $request->validate([
            'instrumentacion' => 'nullable|file|mimes:pdf|max:2048',
            'reporte_inicio' => 'nullable|file|mimes:pdf|max:2048',
            'acuerdos' => 'nullable|file|mimes:pdf|max:2048',

            'calificaciones' => 'nullable|array',
            'calificaciones.*' => 'nullable|file|mimes:pdf|max:2048',

            'rac' => 'nullable|array',
            'rac.*' => 'nullable|file|mimes:pdf|max:2048',

            'rac_na' => 'nullable|array',
            'rac_na.*' => 'nullable|boolean',

            'examen_diagnostico' => 'nullable|file|mimes:pdf|max:2048',
            'analisis_diagnostico' => 'nullable|file|mimes:pdf|max:2048',

            'rubricas' => 'nullable|array',
            'rubricas.*' => 'nullable|array|max:3',
            'rubricas.*.*' => 'file|mimes:pdf|max:2048',

            'eliminar_rubricas' => 'nullable|array',
            'eliminar_rubricas.*' => 'nullable|array',
            'eliminar_rubricas.*.*' => 'string',

            'instrumentos' => 'nullable|array',
            'instrumentos.*' => 'array',
            'instrumentos.*.*' => 'file|mimes:pdf|max:2048',

            'eliminar_instrumentos' => 'nullable|array',
        ], [
            '*.mimes' => 'Todos los archivos deben estar en formato PDF.',
            '*.max' => 'Cada archivo PDF no debe pesar más de 2 MB.',
            'rubricas.*.max' => 'Cada unidad admite máximo 3 PDFs de rúbricas.',
            'rubricas.*.*.mimes' => 'Todos los archivos deben estar en formato PDF.',
            'rubricas.*.*.max' => 'Cada archivo PDF no debe pesar más de 2 MB.',
        ]);

        $obtenerArchivo = function ($valor) {
            if (is_array($valor)) return $valor['archivo'] ?? null;
            if (is_string($valor) && trim($valor) !== '') return $valor;
            return null;
        };

        $eliminarArchivo = function ($valor) use ($obtenerArchivo) {
            $archivo = $obtenerArchivo($valor);
            if (!empty($archivo)) Storage::disk('public')->delete($archivo);
        };

        // Rúbricas: soporta el formato nuevo ('archivos' => [..]) y el viejo ('archivo' => '..')
        $obtenerArchivosRubrica = function ($valor) {
            if (is_array($valor)) {
                if (!empty($valor['archivos']) && is_array($valor['archivos'])) {
                    return array_values(array_filter(
                        $valor['archivos'],
                        fn($p) => is_string($p) && trim($p) !== ''
                    ));
                }
                $a = $valor['archivo'] ?? null;
                return (is_string($a) && trim($a) !== '') ? [$a] : [];
            }
            return (is_string($valor) && trim($valor) !== '') ? [$valor] : [];
        };

        $eliminarArchivosRubrica = function ($valor) use ($obtenerArchivosRubrica) {
            foreach ($obtenerArchivosRubrica($valor) as $ruta) {
                Storage::disk('public')->delete($ruta);
            }
        };

        $semestreNombre = $this->limpiarNombre($asignacion->semestre->nombre ?? 'SIN_SEMESTRE');
        $materiaNombre = $this->limpiarNombre($materia->nombre);
        $revisionNombre = $this->limpiarNombre($revision->nombre);

        $basePath = "evidencias_pdf/{$semestreNombre}/{$materiaNombre}/{$revisionNombre}";

        $actualizarArchivo = function ($campo, $subcarpeta, $nombreFijo) use (
            $request,
            $basePath,
            &$datosActuales,
            $obtenerArchivo,
            $eliminarArchivo
        ) {
            $archivoActual = $datosActuales[$subcarpeta][$campo] ?? null;

            if ($request->hasFile($campo)) {
                $eliminarArchivo($archivoActual);
                return $request->file($campo)->storeAs(
                    $basePath . '/' . $subcarpeta,
                    $nombreFijo,
                    'public'
                );
            }

            return $obtenerArchivo($archivoActual);
        };

        if ($esPrimeraRevision) {
            $datosActuales['documentos']['instrumentacion'] = $actualizarArchivo('instrumentacion', 'documentos', 'instrumentacion.pdf');
            $datosActuales['documentos']['reporte_inicio'] = $actualizarArchivo('reporte_inicio', 'documentos', 'reporte_inicio.pdf');
            $datosActuales['documentos']['acuerdos'] = $actualizarArchivo('acuerdos', 'documentos', 'acuerdos.pdf');
            $datosActuales['evidencias']['examen_diagnostico'] = $actualizarArchivo('examen_diagnostico', 'evidencias', 'examen_diagnostico.pdf');
            $datosActuales['evidencias']['analisis_diagnostico'] = $actualizarArchivo('analisis_diagnostico', 'evidencias', 'analisis_diagnostico.pdf');
        } else {
            foreach (['instrumentacion', 'reporte_inicio', 'acuerdos'] as $campo) {
                if (isset($datosActuales['documentos'][$campo])) $eliminarArchivo($datosActuales['documentos'][$campo]);
                $datosActuales['documentos'][$campo] = ['na' => true, 'archivo' => null];
            }

            foreach (['examen_diagnostico', 'analisis_diagnostico'] as $campo) {
                if (isset($datosActuales['evidencias'][$campo])) $eliminarArchivo($datosActuales['evidencias'][$campo]);
                $datosActuales['evidencias'][$campo] = ['na' => true, 'archivo' => null];
            }
        }

        if ($esNingunaUnidad) {
            foreach ($datosActuales['documentos']['calificaciones_detalladas'] ?? [] as $archivo) $eliminarArchivo($archivo);
            foreach ($datosActuales['documentos']['rac_detallado'] ?? [] as $archivo) $eliminarArchivo($archivo);
            foreach ($datosActuales['evidencias']['rubricas_detalladas'] ?? [] as $archivo) $eliminarArchivosRubrica($archivo);
            foreach ($datosActuales['instrumentos'] ?? [] as $archivo) $eliminarArchivo($archivo);

            $datosActuales['unidades'] = [0];
            $datosActuales['documentos']['calificaciones'] = ['na' => true, 'archivo' => null];
            $datosActuales['documentos']['calificaciones_detalladas'] = ['u0' => ['na' => true, 'archivo' => null]];
            $datosActuales['documentos']['rac'] = ['na' => true, 'archivo' => null];
            $datosActuales['documentos']['rac_detallado'] = ['u0' => ['na' => true, 'archivo' => null]];
            $datosActuales['evidencias']['rubricas'] = ['na' => true, 'archivo' => null];
            $datosActuales['evidencias']['rubricas_detalladas'] = ['u0' => ['na' => true, 'archivo' => null]];
            $datosActuales['instrumentos'] = [];
            $datosActuales['instrumentos_na'] = true;
        } else {
            $unidadesReales = array_values(array_filter($unidadesSeleccionadas, function ($unidad) {
                return (int) $unidad !== 0;
            }));

            $datosActuales['unidades'] = $unidadesReales;
            $datosActuales['instrumentos_na'] = false;

            $datosActuales['documentos']['calificaciones_detalladas'] =
                $datosActuales['documentos']['calificaciones_detalladas'] ?? [];
            $datosActuales['documentos']['rac_detallado'] =
                $datosActuales['documentos']['rac_detallado'] ?? [];
            $datosActuales['evidencias']['rubricas_detalladas'] =
                $datosActuales['evidencias']['rubricas_detalladas'] ?? [];

            $keysSeleccionadas = array_map(function ($unidad) {
                return "u{$unidad}";
            }, $unidadesReales);

            foreach ($datosActuales['documentos']['calificaciones_detalladas'] as $key => $valor) {
                if ($key !== 'u0' && !in_array($key, $keysSeleccionadas, true)) {
                    $eliminarArchivo($valor);
                    unset($datosActuales['documentos']['calificaciones_detalladas'][$key]);
                }
            }

            foreach ($datosActuales['documentos']['rac_detallado'] as $key => $valor) {
                if ($key !== 'u0' && !in_array($key, $keysSeleccionadas, true)) {
                    $eliminarArchivo($valor);
                    unset($datosActuales['documentos']['rac_detallado'][$key]);
                }
            }

            foreach ($datosActuales['evidencias']['rubricas_detalladas'] as $key => $valor) {
                if ($key !== 'u0' && !in_array($key, $keysSeleccionadas, true)) {
                    $eliminarArchivosRubrica($valor);
                    unset($datosActuales['evidencias']['rubricas_detalladas'][$key]);
                }
            }

            unset($datosActuales['documentos']['calificaciones_detalladas']['u0']);
            unset($datosActuales['documentos']['rac_detallado']['u0']);
            unset($datosActuales['evidencias']['rubricas_detalladas']['u0']);

            $datosActuales['instrumentos'] = array_values(array_filter(
                $datosActuales['instrumentos'] ?? [],
                function ($path) use ($unidadesReales) {
                    if (!is_string($path)) return false;

                    if (preg_match('/instrumento_u(\d+)_/', $path, $matches)) {
                        $unidadArchivo = (int) $matches[1];
                        if (!in_array($unidadArchivo, $unidadesReales, true)) {
                            Storage::disk('public')->delete($path);
                            return false;
                        }
                    }
                    return true;
                }
            ));

            $racNaUnidades = $request->input('rac_na', []);
            $errores = [];

            foreach ($unidadesReales as $unidad) {
                $keyUnidad = "u{$unidad}";

                $calificacionActual = $datosActuales['documentos']['calificaciones_detalladas'][$keyUnidad] ?? null;
                $calificacionActualArchivo = $obtenerArchivo($calificacionActual);

                if (!$request->hasFile("calificaciones.{$unidad}") && empty($calificacionActualArchivo)) {
                    $errores["calificaciones.{$unidad}"] = "La lista de calificaciones de la Unidad {$unidad} es obligatoria.";
                }

                $rutasRubricaActuales = $obtenerArchivosRubrica(
                    $datosActuales['evidencias']['rubricas_detalladas'][$keyUnidad] ?? null
                );
                $rutasRubricaEliminar = array_intersect(
                    (array) data_get($request->input('eliminar_rubricas', []), $unidad, []),
                    $rutasRubricaActuales
                );
                $totalRubricasRestantes = count($rutasRubricaActuales) - count($rutasRubricaEliminar);

                $nuevasRubricas = $request->file("rubricas.{$unidad}");
                if (!is_array($nuevasRubricas)) $nuevasRubricas = $nuevasRubricas ? [$nuevasRubricas] : [];
                $totalRubricasUnidad = $totalRubricasRestantes + count($nuevasRubricas);

                if ($totalRubricasUnidad === 0) {
                    $errores["rubricas.{$unidad}"] = "La rúbrica de la Unidad {$unidad} es obligatoria.";
                } elseif ($totalRubricasUnidad > 3) {
                    $errores["rubricas.{$unidad}"] = "La Unidad {$unidad} admite máximo 3 PDFs de rúbricas.";
                }

                $racActual = $datosActuales['documentos']['rac_detallado'][$keyUnidad] ?? null;
                $racActualArchivo = $obtenerArchivo($racActual);

                $racEsNoAplica = array_key_exists($unidad, $racNaUnidades)
                    || array_key_exists((string) $unidad, $racNaUnidades);

                if (!$racEsNoAplica && !$request->hasFile("rac.{$unidad}") && empty($racActualArchivo)) {
                    $errores["rac.{$unidad}"] = "Debes subir el RAC de la Unidad {$unidad} o marcar No aplica.";
                }
            }

            if (!empty($errores)) {
                return back()->withErrors($errores)->withInput();
            }

            foreach ($unidadesReales as $index => $unidad) {
                $keyUnidad = "u{$unidad}";

                $calificacionActual = $datosActuales['documentos']['calificaciones_detalladas'][$keyUnidad] ?? null;
                $calificacionActualArchivo = $obtenerArchivo($calificacionActual);

                if ($request->hasFile("calificaciones.{$unidad}")) {
                    $eliminarArchivo($calificacionActual);
                    $calificacionActualArchivo = $request->file("calificaciones.{$unidad}")->storeAs(
                        $basePath . '/documentos',
                        "calificaciones_u{$unidad}.pdf",
                        'public'
                    );
                }

                $datosActuales['documentos']['calificaciones_detalladas'][$keyUnidad] = [
                    'na' => false,
                    'archivo' => $calificacionActualArchivo,
                ];

                if ($index === 0) {
                    $datosActuales['documentos']['calificaciones'] = [
                        'na' => false,
                        'archivo' => $calificacionActualArchivo,
                    ];
                }

                $racActual = $datosActuales['documentos']['rac_detallado'][$keyUnidad] ?? null;
                $racActualArchivo = $obtenerArchivo($racActual);

                $racEsNoAplica = array_key_exists($unidad, $racNaUnidades)
                    || array_key_exists((string) $unidad, $racNaUnidades);

                if ($racEsNoAplica) {
                    $eliminarArchivo($racActual);
                    $datosActuales['documentos']['rac_detallado'][$keyUnidad] = ['na' => true, 'archivo' => null];
                    if ($index === 0) $datosActuales['documentos']['rac'] = ['na' => true, 'archivo' => null];
                } else {
                    if ($request->hasFile("rac.{$unidad}")) {
                        $eliminarArchivo($racActual);
                        $racActualArchivo = $request->file("rac.{$unidad}")->storeAs(
                            $basePath . '/documentos',
                            "RAC_u{$unidad}.pdf",
                            'public'
                        );
                    }

                    $datosActuales['documentos']['rac_detallado'][$keyUnidad] = [
                        'na' => false,
                        'archivo' => $racActualArchivo,
                    ];

                    if ($index === 0) {
                        $datosActuales['documentos']['rac'] = [
                            'na' => false,
                            'archivo' => $racActualArchivo,
                        ];
                    }
                }

                $rutasRubrica = $obtenerArchivosRubrica(
                    $datosActuales['evidencias']['rubricas_detalladas'][$keyUnidad] ?? null
                );

                // 1) Quitar los PDFs que el usuario eliminó (solo si realmente pertenecen a esta unidad)
                $rutasRubricaEliminar = array_values(array_intersect(
                    (array) data_get($request->input('eliminar_rubricas', []), $unidad, []),
                    $rutasRubrica
                ));
                foreach ($rutasRubricaEliminar as $rutaEliminar) {
                    Storage::disk('public')->delete($rutaEliminar);
                }
                $rutasRubrica = array_values(array_diff($rutasRubrica, $rutasRubricaEliminar));

                // 2) Agregar los nuevos en los huecos libres (_1, _2, _3), tope de 3
                $nuevasRubricas = $request->file("rubricas.{$unidad}");
                if (!is_array($nuevasRubricas)) $nuevasRubricas = $nuevasRubricas ? [$nuevasRubricas] : [];

                foreach ($nuevasRubricas as $fileRubrica) {
                    if (!$fileRubrica || count($rutasRubrica) >= 3) break;

                    for ($n = 1; $n <= 3; $n++) {
                        $rutaCandidata = $basePath . "/evidencias/rubricas_u{$unidad}_{$n}.pdf";
                        if (!in_array($rutaCandidata, $rutasRubrica, true)) {
                            $rutasRubrica[] = $fileRubrica->storeAs(
                                $basePath . '/evidencias',
                                "rubricas_u{$unidad}_{$n}.pdf",
                                'public'
                            );
                            break;
                        }
                    }
                }

                $datosRubrica = [
                    'na'       => false,
                    'archivo'  => $rutasRubrica[0] ?? null,
                    'archivos' => $rutasRubrica,
                ];

                $datosActuales['evidencias']['rubricas_detalladas'][$keyUnidad] = $datosRubrica;

                if ($index === 0) {
                    $datosActuales['evidencias']['rubricas'] = $datosRubrica;
                }
            }

            if ($request->has('eliminar_instrumentos')) {
                foreach ($request->eliminar_instrumentos as $unidad => $rutas) {
                    foreach ($rutas as $ruta) {
                        Storage::disk('public')->delete($ruta);
                        $datosActuales['instrumentos'] = array_values(array_filter(
                            $datosActuales['instrumentos'] ?? [],
                            function ($item) use ($ruta) {
                                return $item !== $ruta;
                            }
                        ));
                    }
                }
            }

            // 🔥 CORRECCIÓN: Iteramos dinámicamente cada unidad y verificamos sus instrumentos individuales
            foreach ($unidadesReales as $unidad) {
                if ($request->hasFile("instrumentos.{$unidad}")) {
                    $archivos = $request->file("instrumentos.{$unidad}");

                    // Aseguramos de tener un arreglo aunque solo se haya enviado 1 PDF
                    if (!is_array($archivos)) {
                        $archivos = [$archivos];
                    }

                    $existentesUnidad = array_filter(
                        $datosActuales['instrumentos'] ?? [],
                        function ($path) use ($unidad) {
                            return is_string($path) && strpos($path, "instrumento_u{$unidad}_") !== false;
                        }
                    );

                    $numerosUsados = [];

                    foreach ($existentesUnidad as $pathExistente) {
                        if (preg_match("/instrumento_u{$unidad}_(\d+)\.pdf$/", $pathExistente, $matches)) {
                            $numerosUsados[] = (int) $matches[1];
                        }
                    }

                    foreach ($archivos as $file) {
                        if (!$file) continue;

                        $numeroDisponible = null;

                        for ($n = 1; $n <= 3; $n++) {
                            if (!in_array($n, $numerosUsados, true)) {
                                $numeroDisponible = $n;
                                break;
                            }
                        }

                        if ($numeroDisponible === null) break;

                        $nombre = "instrumento_u{$unidad}_{$numeroDisponible}.pdf";

                        $path = $file->storeAs(
                            $basePath . '/instrumentos',
                            $nombre,
                            'public'
                        );

                        $datosActuales['instrumentos'][] = $path;
                        $numerosUsados[] = $numeroDisponible;
                    }
                }
            }
            // 🔥 FIN CORRECCIÓN INSTRUMENTOS
        }

        $hashesFinales = [];

        $recolectarHashes = function ($item) use (&$recolectarHashes, &$hashesFinales) {
            if (is_string($item) && !empty($item)) {
                if (strpos($item, '/') !== false && Storage::disk('public')->exists($item)) {
                    $fullPath = Storage::disk('public')->path($item);
                    if (is_file($fullPath)) {
                        $hashesFinales[] = md5_file($fullPath);
                    }
                }
                return;
            }

            if (is_array($item)) {
                foreach ($item as $valor) {
                    $recolectarHashes($valor);
                }
            }
        };

        $recolectarHashes($datosActuales);

        $datosActuales['hashes'] = array_values(array_unique(array_filter($hashesFinales)));

        $evidencia->documentos = $datosActuales;
        $evidencia->save();

        return redirect()
            ->route('evidencias')
            ->with('success', 'Evidencia actualizada correctamente');
    }

    private function actualizarSegundasOportunidades(Request $request, Evidencia $evidencia)
    {
        $user = Auth::user();
        $docente = Docente::where('user_id', $user->id)->first();

        $idsDocentePermitidos = [$user->id];
        if ($docente) $idsDocentePermitidos[] = $docente->id;

        $asignacion = AsignacionMateria::where('id', $evidencia->asignacion_materia_id)
            ->whereIn('docente_id', array_unique($idsDocentePermitidos))
            ->firstOrFail();

        $materia  = $evidencia->materia;
        $revision = $evidencia->revision;

        $datos = is_array($evidencia->documentos)
            ? $evidencia->documentos
            : json_decode($evidencia->documentos ?? '[]', true);

        if (!is_array($datos)) $datos = [];

        $otrasEvidencias = Evidencia::where('asignacion_materia_id', $asignacion->id)
            ->where('id', '!=', $evidencia->id)
            ->get();

        $unidadesEvaluadas = [];

        foreach ($otrasEvidencias as $otraEvidencia) {
            $numeroRev = optional($otraEvidencia->revision)->numero;
            if (!$numeroRev || (int) $numeroRev > 4) continue;

            $datosOtra = is_array($otraEvidencia->documentos)
                ? $otraEvidencia->documentos
                : json_decode($otraEvidencia->documentos ?? '[]', true);

            if (!is_array($datosOtra)) continue;

            foreach (($datosOtra['unidades'] ?? []) as $u) {
                $u = (int) $u;
                if ($u > 0) $unidadesEvaluadas[] = $u;
            }
        }
        $unidadesEvaluadas = array_values(array_unique($unidadesEvaluadas));
        sort($unidadesEvaluadas);

        $unidadesSeleccionadas = array_values(array_filter(array_map(
            'intval',
            $request->input('unidades', [])
        ), fn($u) => $u > 0));

        if (empty($unidadesSeleccionadas)) {
            return back()
                ->withErrors(['unidades' => 'Debes seleccionar al menos una unidad.'])
                ->withInput();
        }

        foreach ($unidadesSeleccionadas as $u) {
            if (!in_array($u, $unidadesEvaluadas, true)) {
                return back()
                    ->withErrors(['unidades' => "La Unidad {$u} no está entre las que ya evaluaste."])
                    ->withInput();
            }
        }

        $request->validate([
            'rac_segundas'              => 'nullable|array',
            'rac_segundas.*'            => 'file|mimes:pdf|max:2048',
            'rubricas_segundas'         => 'nullable|array',
            'rubricas_segundas.*'       => 'file|mimes:pdf|max:2048',
            'calificaciones_segundas'   => 'nullable|array',
            'calificaciones_segundas.*' => 'file|mimes:pdf|max:2048',

            'eliminar_rac_segundas'            => 'nullable|array',
            'eliminar_rac_segundas.*'          => 'string',
            'eliminar_rubricas_segundas'       => 'nullable|array',
            'eliminar_rubricas_segundas.*'     => 'string',
            'eliminar_calificaciones_segundas' => 'nullable|array',
            'eliminar_calificaciones_segundas.*' => 'string',
        ], [
            '*.mimes' => 'Todos los archivos deben ser PDF.',
            '*.max'   => 'Cada PDF no debe pesar más de 2 MB.',
        ]);

        foreach (['eliminar_rac_segundas', 'eliminar_rubricas_segundas', 'eliminar_calificaciones_segundas'] as $campoEliminar) {
            $rutas = $request->input($campoEliminar, []);
            $campoTarget = str_replace('eliminar_', '', $campoEliminar);

            foreach ($rutas as $ruta) {
                if (is_string($ruta) && $ruta !== '' && Storage::disk('public')->exists($ruta)) {
                    Storage::disk('public')->delete($ruta);
                }
            }

            if (!empty($datos[$campoTarget])) {
                if (!is_array($datos[$campoTarget])) {
                    $datos[$campoTarget] = [$datos[$campoTarget]];
                }

                $datos[$campoTarget] = array_values(array_filter(
                    $datos[$campoTarget],
                    fn($p) => !in_array($p, $rutas, true)
                ));
            }
        }

        $hashesExistentes = $datos['hashes'] ?? [];
        if (!is_array($hashesExistentes)) $hashesExistentes = [];

        $hashesSubidos = [];
        $hashesNuevos = [];

        foreach (['rac_segundas', 'rubricas_segundas', 'calificaciones_segundas'] as $campo) {
            $files = $request->file($campo) ?? [];
            if (!is_array($files)) $files = [$files];

            foreach ($files as $file) {
                if (!$file) continue;

                $h = md5_file($file->getRealPath());

                if (in_array($h, $hashesExistentes, true) || in_array($h, $hashesSubidos, true)) {
                    return back()
                        ->withErrors(['archivos' => "El archivo '{$file->getClientOriginalName()}' ya fue subido antes. No se permiten duplicados."])
                        ->withInput();
                }

                $hashesSubidos[] = $h;
                $hashesNuevos[] = $h;
            }
        }

        $semestreNombre = $this->limpiarNombre($asignacion->semestre->nombre ?? 'SIN_SEMESTRE');
        $materiaNombre  = $this->limpiarNombre($materia->nombre);

        $basePath = "evidencias_pdf/{$semestreNombre}/{$materiaNombre}/Segundas_Oportunidades";

        foreach (['rac_segundas', 'rubricas_segundas', 'calificaciones_segundas'] as $campo) {
            $datos[$campo] = $datos[$campo] ?? [];
            if (!is_array($datos[$campo])) {
                $datos[$campo] = $datos[$campo] ? [$datos[$campo]] : [];
            }

            $files = $request->file($campo) ?? [];
            if (!is_array($files)) $files = [$files];

            foreach ($files as $file) {
                if (!$file) continue;

                $nombreBase = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $nombreBase = $this->limpiarNombre($nombreBase);
                $nombreFinal = $nombreBase . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6) . '.pdf';

                $path = $file->storeAs($basePath, $nombreFinal, 'public');

                $datos[$campo][] = $path;
            }
        }

        foreach ($hashesNuevos as $h) {
            $hashesExistentes[] = $h;
        }

        $datos['hashes'] = array_values(array_unique($hashesExistentes));

        $datos['unidades'] = $unidadesSeleccionadas;
        sort($datos['unidades']);

        $evidencia->documentos = $datos;
        $evidencia->save();

        return redirect()
            ->route('evidencias')
            ->with('success', 'Segundas Oportunidades actualizadas correctamente');
    }

    private function limpiarNombre($texto)
    {
        $texto = trim($texto);
        $texto = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $texto);
        return str_replace(' ', '_', $texto);
    }

    private function tieneCalificacionAprobatoria(Evidencia $evidencia): bool
    {
        $evaluaciones = $evidencia->evaluacion ?? [];

        foreach ($evaluaciones as $evaluacion) {
            if (
                !($evaluacion['na'] ?? false) &&
                isset($evaluacion['calificacion']) &&
                (float)$evaluacion['calificacion'] >= 70
            ) {
                return true;
            }
        }

        return false;
    }

    public function destroy($id, $force = false)
    {
        $evidencia = Evidencia::findOrFail($id);

        $estadoActual = strtolower((string) ($evidencia->estado ?? ''));

        if (!$force && in_array($estadoActual, ['2', 'aprobado', 'aprobada'], true)) {
            return redirect()
                ->route('evidencias')
                ->with('error', 'La evidencia ya fue aprobada y no puede eliminarse.');
        }

        $esRechazada = in_array($estadoActual, ['4', 'rechazado', 'rechazada'], true);

        $datos = is_array($evidencia->documentos)
            ? $evidencia->documentos
            : json_decode($evidencia->documentos ?? '{}', true);

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

        if ($force) {
            return redirect()
                ->route('seguimiento-academico')
                ->with('success', 'La evidencia (incluyendo aprobadas) fue eliminada permanentemente.');
        }

        $mensaje = $esRechazada
            ? 'La evidencia rechazada fue eliminada correctamente. Ya puedes subir una nueva versión corregida.'
            : 'La evidencia y todos sus archivos fueron eliminados correctamente.';

        return redirect()
            ->route('evidencias')
            ->with('success', $mensaje);
    }

    public function rechazarSinEvidencia(Request $request)
    {
        $asignacionMateriaId = $request->asignacion_materia_id;
        $materiaId = $request->materia_id;
        $revisionId = $request->revision_id;

        $revision = Revision::findOrFail($revisionId);

        $avanceProgramatico = $request->input('evaluaciones.avance_programatico', []);
        $asisteSeguimiento = $request->input('evaluaciones.asiste_seguimiento', []);

        $evaluacion = [];

        if ((int)$revision->numero === 1) {
            $evaluacion = [
                'instrumentacion' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'reporte_inicio' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'examen_diagnostico' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'analisis_diagnostico' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'acuerdos' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'calificaciones' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'rac' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'rubricas' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
                'instrumentos' => ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'],
            ];
        } else {
            $revision1 = Revision::where('numero', 1)->first();
            $evaluacionRevision1 = [];

            if ($revision1) {
                $evidenciaRev1 = Evidencia::where('asignacion_materia_id', $asignacionMateriaId)
                    ->where('revision_id', $revision1->id)
                    ->first();
                $evaluacionRevision1 = $evidenciaRev1?->evaluacion ?? [];
            }

            $camposAuto = ['instrumentacion', 'reporte_inicio', 'acuerdos', 'examen_diagnostico', 'analisis_diagnostico'];

            foreach ($camposAuto as $campo) {
                $evaluacion[$campo] = $evaluacionRevision1[$campo] ?? [
                    'calificacion' => 0,
                    'observaciones' => 'No existe evaluación en revisión 1'
                ];
            }

            $evaluacion['calificaciones'] = ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'];
            $evaluacion['rac'] = ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'];
            $evaluacion['rubricas'] = ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'];
            $evaluacion['instrumentos'] = ['calificacion' => 0, 'observaciones' => 'No se entregó evidencia'];
        }

        $evaluacion['avance_programatico'] = [
            'calificacion' => $avanceProgramatico['calificacion'] ?? '',
            'observaciones' => $avanceProgramatico['observaciones'] ?? '',
            'na' => $avanceProgramatico['na'] ?? 0,
        ];

        $evaluacion['asiste_seguimiento'] = [
            'calificacion' => $asisteSeguimiento['calificacion'] ?? '',
            'observaciones' => $asisteSeguimiento['observaciones'] ?? '',
            'na' => $asisteSeguimiento['na'] ?? 0,
        ];

        Evidencia::create([
            'asignacion_materia_id' => $asignacionMateriaId,
            'materia_id' => $materiaId,
            'revision_id' => $revisionId,
            'estado' => 4,
            'documentos' => [],
            'evaluacion' => $evaluacion,
            'observaciones' => 'No se entregó evidencia',
            'admin_id' => Auth::id(),
            'fecha_revision' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}