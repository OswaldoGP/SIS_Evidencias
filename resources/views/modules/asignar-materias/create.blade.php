@extends('layouts.main')

@section('titulo', $titulo ?? 'Asignar Materia')

@section('contenido')
    <main id="main" class="main" data-semestre-activo-id="{{ $semestreActivo->id ?? '' }}"
        data-session-error="{{ session('error') }}">

        <div class="pagetitle mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="fw-bold text-primary mb-1">
                        <i class="bi bi-diagram-3-fill me-2"></i>
                        Asignar Materia
                    </h1>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('home') }}" class="text-decoration-none text-secondary">Home</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('asignar-materias') }}" class="text-decoration-none text-secondary">
                                    Asignaciones
                                </a>
                            </li>
                            <li class="breadcrumb-item active text-primary fw-semibold">Nueva Asignación</li>
                        </ol>
                    </nav>
                </div>

                <a href="{{ route('asignar-materias') }}" class="btn btn-light border rounded-pill px-4 shadow-sm">
                    <i class="bi bi-arrow-left me-1"></i> Regresar
                </a>
            </div>
        </div>

        <section class="section">
            <div class="row justify-content-center">
                <div class="col-lg-11 col-xl-10">

                    <div class="card border-0 shadow-lg asig-hero-card">

                        <div class="asig-hero-header">
                            <div class="asig-hero-glow"></div>

                            <div class="d-flex align-items-center position-relative">
                                <div class="asig-hero-icon">
                                    <i class="bi bi-journal-plus"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="fw-bold mb-1 text-white">
                                        Nueva Asignación Académica
                                    </h3>
                                    <p class="mb-0 text-white-50 small">
                                        Registra un nuevo grupo de materia para el semestre activo
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-lg-5">

                            @if (!$semestreActivo)
                                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                                    <div>No hay un semestre activo configurado actualmente.</div>
                                </div>
                            @elseif($materias->isEmpty())
                                <div class="asig-empty">
                                    <div class="asig-empty-icon">
                                        <i class="bi bi-inbox-fill"></i>
                                    </div>
                                    <h5 class="fw-bold mb-2">No hay materias disponibles</h5>
                                    <p class="text-muted mb-4">
                                        Todas las materias activas del semestre
                                        <strong>{{ $semestreActivo->nombre }}</strong>
                                        ya tienen sus grupos asignados.
                                    </p>
                                    <a href="{{ route('asignar-materias') }}" class="btn btn-primary rounded-pill px-4">
                                        <i class="bi bi-arrow-left me-2"></i> Volver a la lista
                                    </a>
                                </div>
                            @else
                                <form action="{{ route('asignar-materias.store') }}" method="POST" id="formAsignacion">
                                    @csrf
                                    <input type="hidden" name="semestre_id" value="{{ $semestreActivo->id }}">
                                    <input type="hidden" name="grupo" id="grupo_input" value="">
                                    <input type="hidden" name="letra" id="letra_input" value="">

                                    <div class="row g-4">

                                        <div class="col-12">
                                            <div class="asig-section-title">
                                                <span class="asig-step">1</span>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Contexto Académico</h6>
                                                    <small class="text-muted">Semestre activo y grupo a asignar</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="asig-label">
                                                <i class="bi bi-calendar-check-fill text-primary"></i>
                                                Semestre Activo
                                            </label>
                                            <div class="asig-input-wrap">
                                                <input type="text" class="asig-input bg-soft" readonly
                                                    value="{{ $semestreActivo->nombre }}">
                                                <i class="bi bi-lock-fill asig-input-lock"></i>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="asig-label">
                                                <i class="bi bi-hash text-primary"></i>
                                                Grupo Generado
                                            </label>
                                            <div class="asig-input-wrap">
                                                <input type="text" id="grupo_preview"
                                                    class="asig-input bg-soft fw-bold text-primary" readonly
                                                    placeholder="Selecciona una materia">
                                                <i class="bi bi-diagram-2-fill asig-input-lock"></i>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-2">
                                            <div class="asig-section-title">
                                                <span class="asig-step">2</span>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Selección de Materia</h6>
                                                    <small class="text-muted">Elige la materia y el grupo a asignar</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="asig-label">
                                                <i class="bi bi-book-half text-primary"></i>
                                                Materia
                                            </label>
                                            <select name="materia_id" id="materia_select" class="asig-select" required>
                                                <option value="">Seleccione una materia...</option>
                                                @foreach ($materias as $materia)
                                                    <option value="{{ $materia->id }}"
                                                        data-carrera="{{ $materia->carrera }}"
                                                        data-semestre="{{ $materia->semestre }}"
                                                        data-num-grupos="{{ $materia->num_grupos ?? 1 }}">
                                                        {{ $materia->nombre }} — {{ $materia->carrera }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-6 d-none" id="letra_container">
                                            <label class="asig-label">
                                                <i class="bi bi-tag-fill text-primary"></i>
                                                Letra del Grupo
                                            </label>
                                            <select id="letra_select" class="asig-select">
                                                <option value="">Seleccione una letra...</option>
                                            </select>
                                            <small class="asig-hint">
                                                <i class="bi bi-info-circle me-1"></i>
                                                La materia permite varios grupos.
                                            </small>
                                        </div>

                                        <div class="col-12 mt-2">
                                            <div class="asig-section-title">
                                                <span class="asig-step">3</span>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">Docente y Alumnos</h6>
                                                    <small class="text-muted">Asigna al responsable y la cantidad</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-7">
                                            <label class="asig-label">
                                                <i class="bi bi-person-badge-fill text-primary"></i>
                                                Docente
                                            </label>
                                            <select name="docente_id" id="docente_id" class="asig-select" required>
                                                <option value="">Seleccione un docente...</option>
                                                @foreach ($docentes as $docente)
                                                    <option value="{{ $docente->id }}">
                                                        {{ $docente->user->name }}
                                                        @if ($docente->user->rol === 'admin')
                                                            — Administrador
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-5">
                                            <label class="asig-label">
                                                <i class="bi bi-people-fill text-primary"></i>
                                                Número de Alumnos
                                            </label>
                                            <div class="asig-number-wrap">
                                                <input name="alumnos" id="alumnos" type="number"
                                                    class="asig-input asig-number-input" min="1" max="50"
                                                    value="1" required>
                                                <span class="asig-number-suffix">alumnos</span>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="asig-actions">
                                                <a href="{{ route('asignar-materias') }}"
                                                    class="btn btn-light border rounded-pill px-4">
                                                    <i class="bi bi-x-circle me-2"></i> Cancelar
                                                </a>

                                                <button type="submit" class="btn asig-btn-primary rounded-pill px-5">
                                                    <i class="bi bi-check2-circle me-2"></i>
                                                    Registrar Asignación
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            @endif
                        </div>

                    </div>

                </div>
            </div>
        </section>
    </main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/asignar-materias.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/asignar-materias.js') }}"></script>
@endpush
