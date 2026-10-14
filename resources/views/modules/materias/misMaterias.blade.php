@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')
    <main id="main" class="main">

        <div class="pagetitle">
            <h1 class="fw-bold text-primary">Mis Materias</h1>

            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">
                            Home
                        </a>
                    </li>
                    <li class="breadcrumb-item active text-primary fw-semibold">
                        Ver mis Materias
                    </li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card border-0 shadow-lg materia-card">

                        <div
                            class="card-header materia-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1 fw-bold">Gestión de Materias</h4>
                                <p class="text-muted mb-0">
                                    Visualiza las asignaturas registradas y administra sus evidencias correspondientes.
                                </p>
                            </div>

                            <div class="header-icon">
                                <i class="bi bi-journal-richtext"></i>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            @if (!$semestreActivo)
                                <div class="alert alert-warning border-0 shadow-sm rounded-3">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    No hay un semestre activo configurado.
                                </div>
                            @elseif($asignaciones->isEmpty())
                                <div class="alert alert-info border-0 shadow-sm rounded-3 text-center">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    No tienes materias asignadas en el semestre
                                    <strong>{{ $semestreActivo->nombre }}</strong>.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table
                                        class="table table-hover align-middle text-center materia-table datatable t_materias">
                                        <thead>
                                            <tr>
                                                <th>NOMBRE</th>
                                                <th>CARRERA</th>
                                                <th>SEMESTRE</th>
                                                <th>CLAVE</th>
                                                <th>GRUPO</th>
                                                <th>N° UNIDADES</th>
                                                <th>ACCIONES</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($asignaciones as $asig)
                                                <tr>
                                                    <td class="fw-semibold text-dark">
                                                        {{ $asig->materia->nombre ?? 'Sin materia' }}
                                                    </td>

                                                    <td>{{ $asig->materia->carrera ?? '—' }}</td>

                                                    <td>
                                                        <span class="badge rounded-pill badge-semestre">
                                                            {{ $asig->materia->semestre ?? '—' }}
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <span class="badge rounded-pill badge-clave">
                                                            {{ $asig->materia->clave ?? '—' }}
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <span
                                                            class="badge bg-primary-subtle text-primary border border-primary px-3 py-2">
                                                            {{ $asig->grupo }}
                                                        </span>
                                                    </td>

                                                    <td class="text-center">
                                                        <span
                                                            class="text-muted">{{ $asig->materia->unidades ?? '—' }}</span>
                                                    </td>

                                                    <td>
                                                        <a href="{{ route('evidencias') }}"
                                                            class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm">
                                                            <i class="bi bi-folder2-open me-2"></i>
                                                            Ver Evidencias
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        </section>

    </main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/mis-materias.css') }}">
@endpush
