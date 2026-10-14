@extends('layouts.main')

@section('titulo', 'Editar Evidencia')

@section('contenido')

    @php
        $datosEvidencia = is_array($evidencia->documentos)
            ? $evidencia->documentos
            : json_decode($evidencia->documentos ?? '[]', true);
        if (!is_array($datosEvidencia)) {
            $datosEvidencia = [];
        }

        $unidadesSeleccionadas = $unidades ?? ($datosEvidencia['unidades'] ?? []);
        $documentosData = $documentos ?? ($datosEvidencia['documentos'] ?? []);
        $evidenciasData = $evidencias ?? ($datosEvidencia['evidencias'] ?? []);
        $instrumentosExistentes = $datosEvidencia['instrumentos'] ?? [];

        $motivoNoEvaluo = old(
            'motivo_no_evaluo',
            $motivoNoEvaluo ??
                ($datosEvidencia['motivo_no_evaluo'] ??
                    ($documentosData['calificaciones']['motivo'] ??
                        ($documentosData['calificaciones_detalladas']['u0']['motivo'] ?? ''))),
        );

        $totalUnidadesMateria = (int) ($evidencia->materia->unidades ?? 0);
        $primeraRevisionId = 1;
        $esPrimeraRevision = (int) ($evidencia->revision->id ?? 0) === $primeraRevisionId;
        $esCuartaRevision = (int) ($evidencia->revision->id ?? 0) === 4;

        $estadoActual = strtolower((string) ($evidencia->estado ?? ''));
        $evidenciaAprobada = in_array($estadoActual, ['2', 'aprobado', 'aprobada'], true);
        $evidenciaRechazada = in_array($estadoActual, ['4', 'rechazado', 'rechazada'], true);
        $evidenciaEvaluada = $evidenciaAprobada || $evidenciaRechazada;

        $unidadesOcupadas = array_values(array_unique(array_map('intval', $unidadesOcupadas ?? [])));
        $unidadesSeleccionadas = array_values(array_map('intval', $unidadesSeleccionadas ?? []));
        $unidadesActualesSinCero = array_values(array_filter($unidadesSeleccionadas, fn($u) => (int) $u !== 0));

        $unidadesDisponiblesParaEditar = [];
        for ($i = 1; $i <= $totalUnidadesMateria; $i++) {
            if (!in_array($i, $unidadesOcupadas, true) || in_array($i, $unidadesActualesSinCero, true)) {
                $unidadesDisponiblesParaEditar[] = $i;
            }
        }
        $sinUnidadesDisponibles = count($unidadesDisponiblesParaEditar) === 0;
        if ($sinUnidadesDisponibles && count($unidadesActualesSinCero) === 0) {
            $unidadesSeleccionadas = [0];
        }
        $permitirNingunaUnidad =
            $esPrimeraRevision || $sinUnidadesDisponibles || in_array(0, $unidadesSeleccionadas, true);

        $obtenerArchivo = function ($valor) {
            if (is_array($valor)) {
                return $valor['archivo'] ?? null;
            }
            if (is_string($valor) && trim($valor) !== '') {
                return $valor;
            }
            return null;
        };

        $instrumentacionActual = $obtenerArchivo($documentosData['instrumentacion'] ?? null);
        $reporteInicioActual = $obtenerArchivo($documentosData['reporte_inicio'] ?? null);
        $acuerdosActual = $obtenerArchivo($documentosData['acuerdos'] ?? null);
        $examenDiagnosticoActual = $obtenerArchivo($evidenciasData['examen_diagnostico'] ?? null);
        $analisisDiagnosticoActual = $obtenerArchivo($evidenciasData['analisis_diagnostico'] ?? null);
        $actaActual = $obtenerArchivo($documentosData['acta'] ?? null);
        $evidenciasSegundaOportunidad = $evidenciasData['segunda_oportunidad'] ?? [];

        $evaluaciones = $evidencia->evaluacion ?? [];
        $documentoAprobado = function ($key) use ($evaluaciones) {
            if (!isset($evaluaciones[$key])) {
                return false;
            }
            $item = $evaluaciones[$key];
            if (!empty($item['na'])) {
                return false;
            }
            $calif = $item['calificacion'] ?? null;
            return is_numeric($calif) && (float) $calif >= 70;
        };

        $instrumentacionAprobada = $documentoAprobado('instrumentacion');
        $reporteInicioAprobado = $documentoAprobado('reporte_inicio');
        $acuerdosAprobado = $documentoAprobado('acuerdos');
        $examenDiagnosticoAprobado = $documentoAprobado('examen_diagnostico');
        $analisisDiagnosticoAprobado = $documentoAprobado('analisis_diagnostico');
        $calificacionesAprobadas = $documentoAprobado('calificaciones');
        $racAprobado = $documentoAprobado('rac');
        $rubricasAprobadas = $documentoAprobado('rubricas');
        $instrumentosAprobados = $documentoAprobado('instrumentos');
        $actaAprobada = $documentoAprobado('acta');
        $segundaOportunidadAprobada = $documentoAprobado('segunda_oportunidad');

        $configEdit = [
            'evidenciaAprobada' => $evidenciaAprobada,
            'esPrimeraRevision' => $esPrimeraRevision,
            'esCuartaRevision' => $esCuartaRevision,
            'totalUnidadesMateria' => $totalUnidadesMateria,
            'unidadesSeleccionadas' => array_values($unidadesSeleccionadas),
            'hashesExistentes' => $hashesExistentes ?? [],
            'calificacionesAprobadas' => $calificacionesAprobadas,
            'racAprobado' => $racAprobado,
            'rubricasAprobadas' => $rubricasAprobadas,
            'instrumentosAprobados' => $instrumentosAprobados,
            'documentosData' => $documentosData,
            'evidenciasData' => $evidenciasData,
            'instrumentosExistentes' => array_values($instrumentosExistentes),
            'motivoNoEvaluo' => $motivoNoEvaluo,
        ];
    @endphp

    <main id="main" class="main" data-evidencia-mode="edit"
        data-config="{{ json_encode($configEdit, JSON_HEX_APOS | JSON_HEX_QUOT) }}">

        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary px-3 py-2">
                                <i class="bi bi-journal-bookmark-fill me-1"></i>
                                Evidencia
                            </span>
                            <span
                                class="badge bg-light text-dark border px-3 py-2">{{ $evidencia->revision->nombre }}</span>

                            @if ($evidenciaAprobada)
                                <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>
                                    Aprobada</span>
                            @elseif ($evidenciaRechazada)
                                <span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle-fill me-1"></i>
                                    Rechazada</span>
                            @else
                                <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-clock-history me-1"></i>
                                    Pendiente</span>
                            @endif
                        </div>

                        <h2 class="fw-bold text-primary mb-1">{{ $evidencia->materia->nombre }}</h2>
                        <div class="text-muted">
                            <i class="bi bi-person-fill me-1"></i>
                            {{ $evidencia->asignacionMateria->docente->name ?? 'Sin docente' }}
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        @if ($evidenciaEvaluada)
                            <a href="{{ route('mis-reportes', $evidencia->id) }}" target="_blank"
                                class="btn btn-outline-success shadow-sm px-4">
                                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Imprimir Comprobante
                            </a>
                        @else
                            <button type="button" class="btn btn-outline-secondary shadow-sm px-4" disabled>
                                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Imprimir Comprobante
                            </button>
                        @endif

                        @if (!$evidenciaAprobada)
                            <form action="{{ route('evidencias.destroy', $evidencia->id) }}" method="POST"
                                id="form-eliminar-evidencia" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-outline-danger shadow-sm px-4"
                                    onclick="confirmarEliminarEvidencia(event)">
                                    <i class="bi bi-trash-fill me-2"></i> Eliminar Evidencia
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-outline-secondary shadow-sm px-4" disabled>
                                <i class="bi bi-trash-fill me-2"></i> Eliminar Evidencia
                            </button>
                        @endif
                    </div>
                </div>

                @if ($evidenciaEvaluada)
                    <div class="alert alert-{{ $evidenciaAprobada ? 'success' : 'danger' }} mt-3 mb-0">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-{{ $evidenciaAprobada ? 'check-circle-fill' : 'x-circle-fill' }} fs-4"></i>
                            <div>
                                <strong
                                    class="d-block">{{ $evidenciaAprobada ? 'EVIDENCIA APROBADA' : 'EVIDENCIA RECHAZADA' }}</strong>
                                <small>
                                    Fecha:
                                    {{ $evidencia->fecha_revision ? \Carbon\Carbon::parse($evidencia->fecha_revision)->format('d/m/Y H:i') : 'No disponible' }}
                                    @if ($evidencia->admin_id)
                                        | Revisado por: {{ $evidencia->admin->name ?? 'Administrador' }}
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <section class="section">
            <div class="card p-4 shadow-lg border-0" style="border-radius: 18px;">

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Corrige los errores:</h6>
                        <ul class="mb-0 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if ($evidenciaAprobada)
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body text-center py-4">
                            <i class="bi bi-patch-check-fill text-success" style="font-size: 4rem;"></i>
                            <h3 class="fw-bold text-success mt-2 mb-2">DOCUMENTO VALIDADO</h3>
                            <p class="text-muted mb-0">Esta evidencia ya fue aprobada. No se permiten modificaciones.</p>
                        </div>
                    </div>
                @endif

                <form action="{{ route('evidencias.update', $evidencia->id) }}" method="POST"
                    enctype="multipart/form-data" id="form-evidencias">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="fw-bold small text-uppercase text-secondary mb-2 block">Materia</label>
                            <select class="form-select form-select-lg fs-6" disabled>
                                <option>{{ $evidencia->materia->nombre }}</option>
                            </select>
                            <input type="hidden" name="materia_id" value="{{ $evidencia->materia->id }}">
                        </div>

                        <div class="col-md-6">
                            <label class="fw-bold small text-uppercase text-secondary mb-2 block">Revisión</label>
                            <select class="form-select form-select-lg fs-6" disabled>
                                <option>{{ $evidencia->revision->nombre }}</option>
                            </select>
                            <input type="hidden" name="revision_id" value="{{ $evidencia->revision->id }}">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3">¿QUÉ UNIDADES EVALUASTE?</h5>

                    <div class="row g-3 mb-4" id="contenedor_tarjetas_unidades">
                        @if ($permitirNingunaUnidad)
                            <div class="col-md-3">
                                <div class="card card-unidad-check p-3 text-center shadow-sm h-100 d-flex flex-column align-items-center justify-content-center {{ in_array(0, $unidadesSeleccionadas) ? 'active' : '' }} {{ $evidenciaAprobada ? 'unidad-bloqueada' : '' }}"
                                    id="card_unidad_0"
                                    @if (!$evidenciaAprobada) onclick="toggleUnidadTarjeta(0)" @endif>
                                    <input type="checkbox" id="chk_unidad_0" name="unidades[]" value="0" class="d-none"
                                        {{ in_array(0, $unidadesSeleccionadas) ? 'checked' : '' }}>
                                    <i class="bi bi-dash-circle fs-3 text-secondary mb-2"></i>
                                    <span class="fw-bold text-dark">Ninguna Unidad</span>
                                </div>
                            </div>
                        @endif

                        @for ($i = 1; $i <= $totalUnidadesMateria; $i++)
                            @php
                                $ocupada = in_array($i, $unidadesOcupadas, true);
                                $actual = in_array($i, $unidadesActualesSinCero, true);
                                $bloqueada = $ocupada && !$actual;
                                $activa = in_array($i, $unidadesSeleccionadas, true);
                            @endphp
                            <div class="col-md-3">
                                <div class="card card-unidad-check p-3 text-center shadow-sm h-100 d-flex flex-column align-items-center justify-content-center {{ $activa ? 'active' : '' }} {{ $bloqueada || $evidenciaAprobada ? 'unidad-bloqueada' : '' }}"
                                    id="card_unidad_{{ $i }}"
                                    @if (!$bloqueada && !$evidenciaAprobada) onclick="toggleUnidadTarjeta({{ $i }})" @endif>
                                    <input type="checkbox" id="chk_unidad_{{ $i }}" name="unidades[]"
                                        value="{{ $i }}" class="d-none" {{ $activa ? 'checked' : '' }}
                                        {{ $bloqueada ? 'disabled' : '' }}>
                                    <i
                                        class="bi bi-bookmark-check fs-3 {{ $bloqueada ? 'text-muted' : 'text-primary' }} mb-2"></i>
                                    <span class="fw-bold text-dark">Unidad {{ $i }}</span>
                                    @if ($bloqueada)
                                        <span class="badge bg-danger-subtle text-danger mt-2">Ya evaluada</span>
                                    @elseif($actual)
                                        <span class="badge bg-success-subtle text-success mt-2">Actual</span>
                                    @endif
                                </div>
                            </div>
                        @endfor
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3">DOCUMENTOS</h5>

                    <div class="row g-4">
                        @if ($esPrimeraRevision)
                            <div class="col-md-6">
                                <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="p-2.5 rounded-3 bg-primary-subtle text-primary me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                            style="width: 45px; height: 45px;">
                                            <i class="bi bi-book-half"></i>
                                        </div>
                                        <label class="form-label fw-bold text-dark fs-5 mb-0">Instrumentación
                                            didáctica</label>
                                    </div>
                                    @if ($instrumentacionActual)
                                        <div class="border rounded-3 p-3 bg-light mb-2">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i>
                                                <a href="{{ asset('storage/' . $instrumentacionActual) }}"
                                                    target="_blank" class="text-decoration-none fw-semibold">Ver documento
                                                    actual</a>
                                            </div>
                                        </div>
                                    @endif
                                    @if (!$evidenciaAprobada && !$instrumentacionAprobada)
                                        <input type="file" name="instrumentacion"
                                            class="form-control form-control-lg fs-6 archivo-pdf-2mb"
                                            accept="application/pdf">
                                    @else
                                        <div class="alert alert-secondary mt-3 mb-0"><i
                                                class="bi bi-lock-fill me-2"></i>Bloqueado</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="p-2.5 rounded-3 bg-info-subtle text-info me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                            style="width: 45px; height: 45px;">
                                            <i class="bi bi-calendar-check"></i>
                                        </div>
                                        <label class="form-label fw-bold text-dark fs-5 mb-0">Reporte de inicio de
                                            curso</label>
                                    </div>
                                    @if ($reporteInicioActual)
                                        <div class="border rounded-3 p-3 bg-light mb-2">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i>
                                                <a href="{{ asset('storage/' . $reporteInicioActual) }}" target="_blank"
                                                    class="text-decoration-none fw-semibold">Ver documento actual</a>
                                            </div>
                                        </div>
                                    @endif
                                    @if (!$evidenciaAprobada && !$reporteInicioAprobado)
                                        <input type="file" name="reporte_inicio"
                                            class="form-control form-control-lg fs-6 archivo-pdf-2mb"
                                            accept="application/pdf">
                                    @else
                                        <div class="alert alert-secondary mt-3 mb-0"><i
                                                class="bi bi-lock-fill me-2"></i>Bloqueado</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="p-2.5 rounded-3 bg-warning-subtle text-warning me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                            style="width: 45px; height: 45px;">
                                            <i class="bi bi-person-workspace"></i>
                                        </div>
                                        <label class="form-label fw-bold text-dark fs-5 mb-0">Acuerdos de clase</label>
                                    </div>
                                    @if ($acuerdosActual)
                                        <div class="border rounded-3 p-3 bg-light mb-2">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i>
                                                <a href="{{ asset('storage/' . $acuerdosActual) }}" target="_blank"
                                                    class="text-decoration-none fw-semibold">Ver documento actual</a>
                                            </div>
                                        </div>
                                    @endif
                                    @if (!$evidenciaAprobada && !$acuerdosAprobado)
                                        <input type="file" name="acuerdos"
                                            class="form-control form-control-lg fs-6 archivo-pdf-2mb"
                                            accept="application/pdf">
                                    @else
                                        <div class="alert alert-secondary mt-3 mb-0"><i
                                                class="bi bi-lock-fill me-2"></i>Bloqueado</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="col-md-6" id="contenedor_calificaciones">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-2.5 rounded-3 bg-success-subtle text-success me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bi bi-card-checklist"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Lista de calificaciones</label>
                                </div>
                                <div class="wrapper-inputs d-flex flex-column gap-2" id="wrapper_calificaciones"></div>
                            </div>
                        </div>

                        <div class="col-md-6" id="contenedor_rac">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white"
                                id="rac_card">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-2.5 rounded-3 bg-secondary-subtle text-secondary me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Actividades de Regularización
                                        (RAC)</label>
                                </div>
                                <div class="wrapper-inputs d-flex flex-column gap-3" id="wrapper_rac"></div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-success mb-3">EVIDENCIAS</h5>

                    <div class="row g-4">
                        @if ($esPrimeraRevision)
                            <div class="col-md-6">
                                <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="p-2.5 rounded-3 bg-primary-subtle text-primary me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                            style="width: 45px; height: 45px;">
                                            <i class="bi bi-file-earmark-medical"></i>
                                        </div>
                                        <label class="form-label fw-bold text-dark fs-5 mb-0">Examen diagnóstico</label>
                                    </div>
                                    @if ($examenDiagnosticoActual)
                                        <div class="border rounded-3 p-3 bg-light mb-2">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i>
                                                <a href="{{ asset('storage/' . $examenDiagnosticoActual) }}"
                                                    target="_blank" class="text-decoration-none fw-semibold">Ver documento
                                                    actual</a>
                                            </div>
                                        </div>
                                    @endif
                                    @if (!$evidenciaAprobada && !$examenDiagnosticoAprobado)
                                        <input type="file" name="examen_diagnostico"
                                            class="form-control form-control-lg fs-6 archivo-pdf-2mb"
                                            accept="application/pdf">
                                    @else
                                        <div class="alert alert-secondary mt-3 mb-0"><i
                                                class="bi bi-lock-fill me-2"></i>Bloqueado</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="p-2.5 rounded-3 bg-info-subtle text-info me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                            style="width: 45px; height: 45px;">
                                            <i class="bi bi-bar-chart-line"></i>
                                        </div>
                                        <label class="form-label fw-bold text-dark fs-5 mb-0">Análisis del
                                            diagnóstico</label>
                                    </div>
                                    @if ($analisisDiagnosticoActual)
                                        <div class="border rounded-3 p-3 bg-light mb-2">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i>
                                                <a href="{{ asset('storage/' . $analisisDiagnosticoActual) }}"
                                                    target="_blank" class="text-decoration-none fw-semibold">Ver documento
                                                    actual</a>
                                            </div>
                                        </div>
                                    @endif
                                    @if (!$evidenciaAprobada && !$analisisDiagnosticoAprobado)
                                        <input type="file" name="analisis_diagnostico"
                                            class="form-control form-control-lg fs-6 archivo-pdf-2mb"
                                            accept="application/pdf">
                                    @else
                                        <div class="alert alert-secondary mt-3 mb-0"><i
                                                class="bi bi-lock-fill me-2"></i>Bloqueado</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="col-md-6" id="contenedor_rubricas">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-2.5 rounded-3 bg-warning-subtle text-warning me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bi bi-table"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Rúbricas del semestre</label>
                                </div>
                                <div class="wrapper-inputs d-flex flex-column gap-2" id="wrapper_rubricas"></div>
                            </div>
                        </div>

                        <div class="col-md-6" id="contenedor_instrumentos">
                            <div id="seccion_dropzones_dinamicos"></div>
                        </div>
                    </div>

                    @if ($esCuartaRevision)
                        <div id="campos_revision_4">
                            <hr class="my-4">
                            <h5 class="fw-bold text-warning mb-3"><i class="bi bi-star-fill me-2"></i>DOCUMENTOS DE
                                REVISIÓN 4</h5>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="p-2.5 rounded-3 bg-danger-subtle text-danger me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                                style="width: 45px; height: 45px;">
                                                <i class="bi bi-file-text-fill"></i>
                                            </div>
                                            <label class="form-label fw-bold text-dark fs-5 mb-0">Adjuntar Actas</label>
                                        </div>
                                        @if ($actaActual)
                                            <div class="border rounded-3 p-3 bg-light mb-2">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i>
                                                    <a href="{{ asset('storage/' . $actaActual) }}" target="_blank"
                                                        class="text-decoration-none fw-semibold">Ver documento actual</a>
                                                </div>
                                            </div>
                                        @endif
                                        @if (!$evidenciaAprobada && !$actaAprobada)
                                            <input type="file" name="actas"
                                                class="form-control form-control-lg fs-6 archivo-pdf-2mb input-revision-4"
                                                accept="application/pdf">
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="p-2.5 rounded-3 bg-warning-subtle text-warning me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                                style="width: 45px; height: 45px;">
                                                <i class="bi bi-clock-history"></i>
                                            </div>
                                            <label class="form-label fw-bold text-dark fs-5 mb-0">Evidencias de Segunda
                                                Oportunidad</label>
                                        </div>
                                        @if (!$evidenciaAprobada && !$segundaOportunidadAprobada)
                                            <div id="contenedor_evidencias_segunda_oportunidad_edit">
                                                @foreach ($evidenciasSegundaOportunidad as $ruta)
                                                    <div class="evidencia-existente mb-3"
                                                        data-ruta="{{ $ruta }}">
                                                        <div class="border rounded-3 p-3 bg-light">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div class="d-flex align-items-center">
                                                                    <i
                                                                        class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i>
                                                                    <a href="{{ asset('storage/' . $ruta) }}"
                                                                        target="_blank"
                                                                        class="text-decoration-none fw-semibold">Ver
                                                                        documento actual</a>
                                                                </div>
                                                                <button type="button"
                                                                    class="btn-eliminar-archivo btn border-0 p-2 text-muted rounded-2"
                                                                    onclick="marcarEliminarEvidenciaSegundaOportunidad(this, '{{ $ruta }}')">
                                                                    <i class="bi bi-trash3 fs-5"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                                <div id="nuevas_evidencias_container_edit"></div>
                                                <button type="button" class="btn btn-outline-success w-100 mt-2"
                                                    onclick="agregarCampoEvidenciaSegundaOportunidadEdit()">
                                                    <i class="bi bi-plus-circle"></i> Agregar nueva evidencia
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <hr class="my-4">

                    <div class="mt-4 d-flex gap-2">
                        <a href="{{ route('evidencias') }}" class="btn btn-light border px-4 rounded-pill">
                            <i class="bi bi-arrow-left me-1"></i> Regresar
                        </a>
                        <button type="submit" class="btn btn-primary px-4 rounded-pill"
                            {{ $evidenciaAprobada ? 'disabled' : '' }}>
                            <i class="bi bi-floppy me-1"></i> Actualizar Evidencia
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/evidencias.css') }}">
@endpush

@push('scripts')
    <script>
        function confirmarEliminarEvidencia(event) {
            event.preventDefault();
            Swal.fire({
                title: '¿Eliminar evidencia?',
                text: 'Esta acción eliminará la evidencia y TODOS sus archivos adjuntos.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((r) => {
                if (r.isConfirmed) document.getElementById('form-eliminar-evidencia').submit();
            });
        }

        window.agregarCampoEvidenciaSegundaOportunidadEdit = function() {
            const container = document.getElementById('nuevas_evidencias_container_edit');
            if (!container) return;
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2';
            row.innerHTML = `
                <div class="col-8">
                    <input type="file" name="evidencias_segunda_oportunidad_nuevas[]" class="form-control archivo-pdf-2mb" accept="application/pdf">
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-outline-danger w-100 btn-sm" onclick="this.closest('.row').remove()">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </div>`;
            container.appendChild(row);
        };

        window.marcarEliminarEvidenciaSegundaOportunidad = function(btn, ruta) {
            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar evidencia?',
                showCancelButton: true,
                confirmButtonText: 'Sí',
                cancelButtonText: 'Cancelar'
            }).then((r) => {
                if (!r.isConfirmed) return;
                btn.closest('.evidencia-existente').classList.add('evidencia-eliminada');
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'eliminar_evidencias_segunda_oportunidad[]';
                hidden.value = ruta;
                document.getElementById('nuevas_evidencias_container_edit').appendChild(hidden);
            });
        };
    </script>
    <script src="{{ asset('js/evidencias.js') }}"></script>
@endpush