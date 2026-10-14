@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')

    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="fw-bold text-primary mb-1">
                        <i class="bi bi-journal-plus me-2"></i>
                        Gestión de Asignaturas
                    </h1>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('home') }}" class="text-decoration-none text-secondary">Home</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('materias') }}" class="text-decoration-none text-secondary">Materias</a>
                            </li>
                            <li class="breadcrumb-item active text-primary fw-semibold">Nueva Materia</li>
                        </ol>
                    </nav>
                </div>

                <a href="{{ route('materias') }}" class="btn btn-light border rounded-pill px-4 shadow-sm">
                    <i class="bi bi-arrow-left me-1"></i> Regresar
                </a>
            </div>
        </div>

        <section class="section">
            <div class="row justify-content-center">
                <div class="col-lg-11 col-xl-10">

                    <div class="card border-0 shadow-lg materia-hero-card">

                        <div class="materia-hero-header">
                            <div class="materia-hero-glow"></div>
                            <div class="materia-hero-glow-2"></div>

                            <div class="d-flex align-items-center position-relative">
                                <div class="materia-hero-icon">
                                    <i class="bi bi-book-half"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="fw-bold mb-1 text-white">
                                        Nueva Materia
                                    </h3>
                                    <p class="mb-0 text-white-50 small">
                                        Registra una nueva asignatura para el sistema académico
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-lg-5">

                            <form action="{{ route('materias.store') }}" id="formMateria" method="POST"
                                class="needs-validation">
                                @csrf

                                <div class="row g-4">

                                    <div class="col-12">
                                        <div class="materia-section-title">
                                            <span class="materia-step">1</span>
                                            <div>
                                                <h6 class="mb-0 fw-bold">Identificación de la Materia</h6>
                                                <small class="text-muted">Nombre y clave oficial</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-7">
                                        <label class="materia-label" for="nombre">
                                            <i class="bi bi-fonts text-primary"></i>
                                            Nombre de la Materia
                                        </label>
                                        <div class="materia-input-wrap">
                                            <input oninput="this.value = this.value.toUpperCase();" name="nombre"
                                                id="nombre" type="text" class="materia-input"
                                                placeholder="EJ. ESTRUCTURA DE DATOS" required>
                                            <i class="bi bi-journal-text materia-input-icon"></i>
                                        </div>
                                    </div>

                                    <div class="col-md-5">
                                        <label class="materia-label" for="clave">
                                            <i class="bi bi-key-fill text-primary"></i>
                                            Clave Materia
                                        </label>
                                        <div class="materia-input-wrap">
                                            <input oninput="this.value = this.value.toUpperCase();" name="clave"
                                                id="clave" type="text" class="materia-input"
                                                placeholder="EJ. AED-128" required>
                                            <i class="bi bi-hash materia-input-icon"></i>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <div class="materia-section-title">
                                            <span class="materia-step">2</span>
                                            <div>
                                                <h6 class="mb-0 fw-bold">Estructura Académica</h6>
                                                <small class="text-muted">Unidades, semestre y carrera</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="materia-label" for="unidades">
                                            <i class="bi bi-list-check text-primary"></i>
                                            Unidades
                                        </label>
                                        <div class="materia-number-wrap">
                                            <input name="unidades" id="unidades" type="number"
                                                class="materia-input materia-number-input" min="1" max="10"
                                                value="1" required>
                                            <span class="materia-number-suffix">Unid.</span>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="materia-label" for="semestre">
                                            <i class="bi bi-calendar3 text-primary"></i>
                                            Semestre
                                        </label>
                                        <select id="semestre" name="semestre" class="materia-select" required>
                                            <option value="" selected disabled>Seleccione...</option>
                                            @for ($i = 1; $i <= 9; $i++)
                                                <option value="{{ $i }}">{{ $i }}° Semestre</option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="materia-label" for="carrera">
                                            <i class="bi bi-mortarboard-fill text-primary"></i>
                                            Carrera
                                        </label>
                                        <select id="carrera" name="carrera" class="materia-select" required>
                                            <option value="" selected disabled>Seleccione...</option>
                                            <option value="INGENIERIA EN SISTEMAS COMPUTACIONALES">
                                                Ing. en Sistemas Computacionales
                                            </option>
                                            <option value="INGENIERIA INDUSTRIAL">
                                                Ing. Industrial
                                            </option>
                                            <option value="INGENIERIA EN GESTION EMPRESARIAL">
                                                Ing. en Gestión Empresarial
                                            </option>
                                            <option value="LICENCIATURA EN TURISMO">
                                                Lic. en Turismo
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <div class="materia-actions">
                                            <a href="{{ route('materias') }}"
                                                class="btn btn-light border rounded-pill px-4">
                                                <i class="bi bi-x-circle me-2"></i> Cancelar
                                            </a>

                                            <button type="submit" class="btn materia-btn-primary rounded-pill px-5">
                                                <i class="bi bi-check2-circle me-2"></i>
                                                Guardar Materia
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </form>

                        </div>

                    </div>
                </div>
            </div>
        </section>

    </main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/materias.css') }}">
@endpush
