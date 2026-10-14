@extends('layouts.main')

@section('titulo', 'Crear Evidencia')

@section('contenido')

    @php
        $primeraRevision = $revisiones->firstWhere('numero', 1) ?? $revisiones->first();
        $primeraRevisionId = $primeraRevision->id ?? 1;

        $configCreate = [
            'combinacionesSubidas' => $subidasArray,
            'revisionesOriginales' => $revisiones,
            'unidadesUtilizadas' => $unidadesUtilizadasPorMateria,
            'unidadesEvaluadasData' => $unidadesEvaluadasPorMateria ?? [],
            'hashesExistentesBackend' => $hashesExistentes ?? [],
        ];
    @endphp

    <main id="main" class="main" data-evidencia-mode="create"
        data-config="{{ json_encode($configCreate, JSON_HEX_APOS | JSON_HEX_QUOT) }}">

        <div class="d-flex justify-content-between pagetitle mb-4">
            <h1 class="fw-bold text-primary">Crear Evidencia</h1>
            <div>
                <button type="button" class="btn btn-info text-white rounded-pill px-4 shadow-sm" data-bs-toggle="modal"
                    data-bs-target="#modalManualEvidenciasDocente">
                    <i class="bi bi-question-circle me-1"></i> Ayuda
                </button>
            </div>
        </div>

        <section class="section">
            <div class="card p-4 p-lg-5 shadow-lg border-0 usuario-card">

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <h6 class="fw-bold">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Por favor corrige los siguientes errores:
                        </h6>
                        <ul class="mb-0 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form action="{{ route('evidencias.store') }}" method="POST" enctype="multipart/form-data"
                    id="form-evidencias">
                    @csrf

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="fw-bold small text-uppercase text-secondary mb-2 block">Materia / Grupo</label>
                            <select id="asignacion_materia_id" name="asignacion_materia_id"
                                class="form-select form-select-lg fs-6 custom-input" required>
                                <option value="">Seleccione</option>
                                @foreach ($asignaciones as $asig)
                                    <option value="{{ $asig->id }}" data-materia-id="{{ $asig->materia_id }}"
                                        data-unidades="{{ $asig->materia->unidades }}"
                                        data-unidades-evaluadas='@json($unidadesEvaluadasPorMateria[$asig->id] ?? ($unidadesEvaluadasPorMateria[$asig->materia_id] ?? []))'>
                                        {{ $asig->materia->nombre }} - Grupo {{ $asig->grupo }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="materia_id" id="materia_id">
                        </div>

                        <div class="col-md-6">
                            <label class="fw-bold small text-uppercase text-secondary mb-2 block">Revisión</label>
                            <select id="revision_id" name="revision_id" class="form-select form-select-lg fs-6 custom-input"
                                required disabled>
                                <option value="">Seleccione</option>
                                @foreach ($revisiones as $revision)
                                    <option value="{{ $revision->id }}" data-numero="{{ $revision->numero }}"
                                        data-es-primera="{{ $revision->numero == 1 ? '1' : '0' }}"
                                        data-es-cuarta="{{ $revision->numero == 4 ? '1' : '0' }}"
                                        data-es-segundas="{{ $revision->numero == 5 ? '1' : '0' }}">
                                        {{ $revision->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3">¿QUÉ UNIDADES EVALUASTE?</h5>

                    <div class="row g-3 mb-4" id="contenedor_tarjetas_unidades">
                        <div class="col-12"><span class="text-muted small">Selecciona una materia y revisión para cargar las
                                unidades disponibles.</span></div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3">DOCUMENTOS</h5>

                    <div class="row g-4">
                        <div class="col-md-6 campo-solo-revision-1">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div
                                        class="p-2.5 rounded-3 bg-primary-subtle text-primary me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                        <i class="bi bi-book-half"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Instrumentación didáctica</label>
                                </div>
                                <input type="file" name="instrumentacion"
                                    class="form-control form-control-lg fs-6 input-solo-revision-1 archivo-pdf-2mb custom-input"
                                    accept="application/pdf" required>
                                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Solo PDF.
                                    Máximo 2 MB.</small>
                            </div>
                        </div>

                        <div class="col-md-6 campo-solo-revision-1">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div
                                        class="p-2.5 rounded-3 bg-info-subtle text-info me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Reporte de inicio de curso</label>
                                </div>
                                <input type="file" name="reporte_inicio"
                                    class="form-control form-control-lg fs-6 input-solo-revision-1 archivo-pdf-2mb custom-input"
                                    accept="application/pdf" required>
                                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Solo PDF.
                                    Máximo 2 MB.</small>
                            </div>
                        </div>

                        <div class="col-md-6 campo-solo-revision-1">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div
                                        class="p-2.5 rounded-3 bg-warning-subtle text-warning me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                        <i class="bi bi-person-workspace"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Acuerdos de clase</label>
                                </div>
                                <input type="file" name="acuerdos"
                                    class="form-control form-control-lg fs-6 input-solo-revision-1 archivo-pdf-2mb custom-input"
                                    accept="application/pdf" required>
                                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Solo PDF.
                                    Máximo 2 MB.</small>
                            </div>
                        </div>

                        <div class="col-md-6" id="contenedor_calificaciones">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div
                                        class="p-2.5 rounded-3 bg-success-subtle text-success me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                        <i class="bi bi-card-checklist"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Lista de calificaciones</label>
                                </div>
                                <div class="wrapper-inputs d-flex flex-column gap-2">
                                    <span class="text-muted small">Selecciona unidades primero</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6" id="contenedor_rac">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white"
                                id="rac_card" style="transition: all 0.3s ease;">
                                <div class="d-flex align-items-center mb-3">
                                    <div
                                        class="p-2.5 rounded-3 bg-secondary-subtle text-secondary me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Actividades de Regularización
                                        (RAC)</label>
                                </div>
                                <div class="wrapper-inputs d-flex flex-column gap-3" id="rac_inputs_wrapper">
                                    <span class="text-muted small">Selecciona unidades primero</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-success mb-3">EVIDENCIAS</h5>

                    <div class="row g-4">
                        <div class="col-md-6 campo-solo-revision-1">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div
                                        class="p-2.5 rounded-3 bg-primary-subtle text-primary me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                        <i class="bi bi-file-earmark-medical"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Examen diagnóstico</label>
                                </div>
                                <input type="file" name="examen_diagnostico"
                                    class="form-control form-control-lg fs-6 input-solo-revision-1 archivo-pdf-2mb custom-input"
                                    accept="application/pdf" required>
                                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Solo PDF.
                                    Máximo 2 MB.</small>
                            </div>
                        </div>

                        <div class="col-md-6 campo-solo-revision-1">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div
                                        class="p-2.5 rounded-3 bg-info-subtle text-info me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                        <i class="bi bi-bar-chart-line"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Análisis del diagnóstico</label>
                                </div>
                                <input type="file" name="analisis_diagnostico"
                                    class="form-control form-control-lg fs-6 input-solo-revision-1 archivo-pdf-2mb custom-input"
                                    accept="application/pdf" required>
                                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Solo PDF.
                                    Máximo 2 MB.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mt-1">
                        <div class="col-lg-6" id="contenedor_rubricas">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-2.5 rounded-3 bg-warning-subtle text-warning me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box"
                                        style="width: 45px; height: 45px;">
                                        <i class="bi bi-table"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Rúbricas del semestre</label>
                                </div>
                                <div class="wrapper-inputs d-flex flex-column gap-2">
                                    <span class="text-muted small">Selecciona unidades primero</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="h-100" id="seccion_dropzones_dinamicos"></div>
                        </div>
                    </div>

                    <div id="campos_revision_4" class="d-none">
                        <hr class="my-4">
                        <h5 class="fw-bold text-warning mb-3"><i class="bi bi-star-fill me-2"></i>DOCUMENTOS DE REVISIÓN 4
                        </h5>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center mb-3">
                                        <div
                                            class="p-2.5 rounded-3 bg-success-subtle text-success me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                            <i class="bi bi-list-check"></i>
                                        </div>
                                        <label class="form-label fw-bold text-dark fs-5 mb-0">Lista de calificaciones
                                            finales <span class="text-danger ms-1">*</span></label>
                                    </div>
                                    <input type="file" name="calificaciones_finales" id="calificaciones_finales_file"
                                        class="form-control form-control-lg fs-6 archivo-pdf-2mb input-revision-4 custom-input"
                                        accept="application/pdf">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center mb-3">
                                        <div
                                            class="p-2.5 rounded-3 bg-danger-subtle text-danger me-3 fs-4 d-inline-flex align-items-center justify-content-center header-icon-box">
                                            <i class="bi bi-file-text-fill"></i>
                                        </div>
                                        <label class="form-label fw-bold text-dark fs-5 mb-0">Adjuntar Actas <span
                                                class="text-danger ms-1">*</span></label>
                                    </div>
                                    <input type="file" name="actas" id="actas_file"
                                        class="form-control form-control-lg fs-6 archivo-pdf-2mb input-revision-4 custom-input"
                                        accept="application/pdf">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex gap-3 justify-content-end">
                        <a href="{{ route('evidencias') }}" class="btn btn-outline-secondary px-4 rounded-pill">
                            <i class="bi bi-arrow-left me-1"></i> Regresar
                        </a>
                        <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm fw-bold">
                            <i class="bi bi-floppy me-1"></i> Guardar Evidencia
                        </button>
                    </div>
                </form>
            </div>
        </section>

        @include('modules.evidencias.manual-crear')
    </main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/evidencias.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/evidencias.js') }}"></script>
@endpush
