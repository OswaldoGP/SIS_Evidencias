@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')
    <main id="main" class="main">

        <div class="row justify-content-center">
            <div class="col-lg-9 semestre-show">

                <div class="card border-0 shadow-lg semestre-card static mt-4 mb-4">

                    <div class="card-header semestre-header danger">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                            <div>
                                <h2 class="fw-bold text-danger mb-1">Eliminar Semestre</h2>
                                <p class="text-muted mb-0">
                                    Confirma la eliminación definitiva del periodo académico
                                </p>
                            </div>

                            <div class="header-icon danger">
                                <i class="bi bi-trash-fill"></i>
                            </div>

                        </div>

                        <nav class="mt-3">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('home') }}" class="text-decoration-none text-secondary">Home</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="{{ route('semestres') }}"
                                        class="text-decoration-none text-secondary">Semestres</a>
                                </li>
                                <li class="breadcrumb-item active text-danger fw-semibold">Eliminar</li>
                            </ol>
                        </nav>

                    </div>

                    <div class="card-body p-4 p-lg-5">

                        {{-- ALERTA --}}
                        <div class="delete-alert">
                            <div class="d-flex align-items-start gap-3">
                                <div class="alert-icon">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold text-danger mb-2">Advertencia importante</h4>
                                    <p class="mb-2">
                                        Vas a eliminar el semestre
                                        <strong class="text-dark">{{ $item->nombre }}</strong>.
                                        Esta acción no se puede deshacer.
                                    </p>
                                    <p class="mb-0">
                                        Si este semestre tiene materias asociadas,
                                        también dejarán de estar vinculadas al mismo.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- RESUMEN --}}
                        <div class="info-box mt-4">
                            <h5 class="fw-bold mb-3 text-dark">Información del semestre</h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="label">Nombre:</span>
                                        <span class="value">{{ $item->nombre }}</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="label">Año:</span>
                                        <span class="value">{{ $item->anio }}</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="label">Periodo:</span>
                                        <span class="value">{{ $item->periodo }}</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="label">Estado:</span>
                                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                                            {{ $item->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- BOTONES --}}
                        <div class="text-center mt-5">
                            <a href="{{ route('semestres') }}" class="btn btn-outline-secondary px-4 rounded-pill me-2">
                                <i class="bi bi-x-circle me-2"></i> Cancelar
                            </a>

                            <button id="btnEliminar" class="btn btn-danger px-5 rounded-pill shadow-sm"
                                data-url="{{ route('semestres.destroy', $item->id) }}"
                                data-redirect="{{ route('semestres') }}">
                                <i class="bi bi-trash me-2"></i> Eliminar Semestre
                            </button>
                        </div>

                    </div>

                </div>

            </div>
        </div>

    </main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/semestres.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/semestres.js') }}"></script>
@endpush
