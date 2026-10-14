@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')
    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <h1>Editar Asignación</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('asignar-materias') }}">Asignar Materias</a></li>
                    <li class="breadcrumb-item active">Editar</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="card shadow-sm border-0" style="border-radius: 18px; overflow: hidden;">

                <div class="card-header bg-white py-4 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="p-3 rounded-3 me-3 bg-warning-light">
                            <i class="bi bi-pencil-square text-warning fs-4"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 p-0 fw-bold">Modificar Asignación</h5>
                            <small class="text-muted">Actualice la información de la asignación seleccionada</small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">

                    @if ($errors->any())
                        <div class="alert alert-danger border-0 shadow-sm">
                            <div class="fw-bold mb-2">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> Se encontraron errores:
                            </div>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('asignar-materias.update', $item->id) }}" method="POST" id="formAsignacionEdit">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Semestre</label>
                                <input type="text" class="form-control bg-light fw-semibold" readonly
                                    value="{{ $item->semestre->nombre }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Materia</label>
                                <input type="text" class="form-control bg-light fw-semibold" readonly
                                    value="{{ $item->materia->nombre }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Grupo</label>
                                <input type="text" class="form-control bg-light fw-semibold" readonly
                                    value="{{ $item->grupo }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Docente</label>
                                <select name="docente_id" class="form-select select2" required>
                                    <option value="" disabled>Seleccione docente...</option>
                                    @foreach ($docentes as $docente)
                                        <option value="{{ $docente->id }}"
                                            {{ $item->docente_id == $docente->id ? 'selected' : '' }}>
                                            {{ $docente->user->name }}
                                            @if ($docente->user->rol === 'admin')
                                                - ADMINISTRADOR
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold" for="alumnos">
                                    <i class="bi bi-people me-1"></i> Cantidad de Alumnos
                                </label>
                                <div class="input-group">
                                    <input name="alumnos" id="alumnos" type="number" class="form-control" min="1"
                                        max="100" value="{{ old('alumnos', $item->alumnos) }}" required>
                                    <span class="input-group-text">Alumnos</span>
                                </div>
                            </div>

                            <div class="col-12 mt-5">
                                <div class="d-flex justify-content-end gap-3 border-top pt-4">
                                    <a href="{{ route('asignar-materias') }}" class="btn btn-outline-secondary px-4"
                                        style="border-radius: 10px;">
                                        <i class="bi bi-x-circle me-2"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-outline-warning px-5 shadow-sm fw-bold"
                                        style="border-radius: 10px;">
                                        <i class="bi bi-check-circle me-2"></i> Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
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
