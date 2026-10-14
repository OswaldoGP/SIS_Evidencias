@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')
    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <h1>Eliminar Asignación</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('asignar-materias') }}">Asignar Materias</a></li>
                    <li class="breadcrumb-item active">Eliminar</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="card shadow-sm border-0" style="border-radius: 18px; overflow: hidden;">

                <div class="card-header bg-white py-4 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="p-3 rounded-3 me-3 bg-danger-light">
                            <i class="bi bi-trash3-fill text-danger fs-4"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 p-0 fw-bold text-danger">Confirmar Eliminación</h5>
                            <small class="text-muted">Esta acción eliminará permanentemente la asignación
                                seleccionada</small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">

                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 mt-1"></i>
                        <div>
                            <h6 class="fw-bold mb-2">Advertencia importante</h6>
                            <p class="mb-2">
                                Vas a eliminar la asignación de la materia
                                <strong>{{ $item->materia->nombre }}</strong>
                                del semestre
                                <strong>{{ $item->semestre->nombre }}</strong>.
                            </p>
                            <p class="mb-0">Esta acción no se puede deshacer.</p>
                        </div>
                    </div>

                    <div class="table-responsive rounded-3 border shadow-sm mt-4">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>MATERIA</th>
                                    <th class="text-center">SEMESTRE</th>
                                    <th class="text-center">DOCENTE</th>
                                    <th class="text-center">GRUPO</th>
                                    <th class="text-center">ALUMNOS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold">{{ $item->materia->nombre }}</td>
                                    <td class="text-center">{{ $item->semestre->nombre }}</td>
                                    <td class="text-center">{{ $item->docente->name }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 py-2">{{ $item->grupo }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-primary">{{ $item->alumnos }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-3 border-top pt-4 mt-5">
                        <a href="{{ route('asignar-materias') }}" class="btn btn-outline-info px-4"
                            style="border-radius: 10px;">
                            <i class="bi bi-x-circle me-2"></i> Cancelar
                        </a>

                        <button id="btnEliminar" class="btn btn-outline-danger px-4 shadow-sm" style="border-radius: 10px;"
                            data-url="{{ route('asignar-materias.destroy', $item->id) }}"
                            data-redirect="{{ route('asignar-materias') }}">
                            <i class="bi bi-trash me-2"></i> Eliminar Asignación
                        </button>
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
