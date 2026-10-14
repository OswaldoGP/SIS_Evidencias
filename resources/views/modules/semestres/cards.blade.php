@forelse ($semestres as $semestre)
    <div class="col-12 col-md-6 col-lg-4">

        <div class="card semestre-card border-0 shadow-lg h-100">
            <div class="card-body d-flex flex-column p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="card-title mb-1 semestre-title">
                            {{ $semestre->nombre }}
                        </h4>
                        <p class="semestre-carrera mb-0">
                            {{ $semestre->carrera }}
                        </p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input custom-switch toggle-status" type="checkbox"
                            id="estado_{{ $semestre->id }}" data-id="{{ $semestre->id }}" data-base-url="/semestres"
                            {{ $semestre->activo ? 'checked' : '' }} data-inicio="{{ $semestre->fecha_inicio }}"
                            data-fin="{{ $semestre->fecha_fin }}">
                    </div>
                </div>

                <div class="info-box">
                    <div class="info-item">
                        <i class="bi bi-journal-text text-primary"></i>
                        <span>
                            <strong>Año:</strong>
                            {{ $semestre->anio }}
                        </span>
                    </div>

                    <div class="info-item">
                        <i class="bi bi-calendar-check text-primary"></i>
                        <span>
                            <strong>Estatus:</strong>
                        </span>
                        <span
                            class="badge rounded-pill status-badge
                            {{ $semestre->activo ? 'status-active' : 'status-inactive' }}">
                            {{ $semestre->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>

                    <div class="info-item">
                        <i class="bi bi-book-fill text-primary"></i>
                        <span>
                            <strong>Materias Activas:</strong>
                            {{ $semestre->materias_activas_count }}
                        </span>
                    </div>

                    <div class="info-item">
                        <i class="bi bi-check2-square text-success"></i>
                        <span>
                            <strong>Materias Asignadas:</strong>
                            {{ $semestre->materias_asignadas_count }}
                        </span>
                    </div>

                    <div class="info-item">
                        <i class="bi bi-hourglass-split text-warning"></i>
                        <span>
                            <strong>Por Asignar:</strong>
                            {{ $semestre->materias_por_asignar_count }}
                        </span>
                    </div>
                </div>

                <div class="acciones-box mt-4">
                    <h6 class="acciones-title">
                        <i class="fa-solid fa-hexagon-nodes me-2"></i>
                        Acciones
                    </h6>
                    <div class="d-flex gap-2">
                        <a href="{{ route('semestres.edit', $semestre->id) }}"
                            class="btn btn-warning btn-sm flex-fill rounded-pill fw-semibold
                            {{ $semestre->activo ? 'disabled' : '' }}"
                            {{ $semestre->activo ? 'aria-disabled="true" title=Semestre activo, no se puede editar' : '' }}>
                            <i class="fa-solid fa-file-pen me-1"></i>
                            Editar
                        </a>
                        <a href="{{ route('semestres.show', $semestre->id) }}"
                            class="btn btn-outline-danger btn-sm flex-fill rounded-pill fw-semibold
                            {{ $semestre->activo ? 'disabled' : '' }}"
                            {{ $semestre->activo ? 'aria-disabled="true" title=Semestre activo, no se puede eliminar' : '' }}>
                            <i class="fa-solid fa-trash me-1"></i>
                            Eliminar
                        </a>
                    </div>
                </div>

                {{-- DROPDOWN --}}
                <div class="mt-auto pt-4">
                    <div class="dropdown w-100">
                        <button type="button"
                            class="btn btn-primary dropdown-toggle w-100 rounded-pill py-2 fw-semibold"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-folder2-open me-2"></i>
                            Ver Materias
                        </button>
                        <ul class="dropdown-menu w-100 shadow border-0 rounded-4">
                            @forelse ($semestre->materias as $materia)
                                <li>
                                    <span class="dropdown-item py-2">
                                        {{ $materia->nombre }}
                                    </span>
                                </li>
                            @empty
                                <li>
                                    <span class="dropdown-item text-muted py-2">
                                        No hay materias asignadas
                                    </span>
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@empty

    <div class="col-12 text-center mt-4">
        <div class="alert alert-warning rounded-4 shadow-sm fw-semibold">
            No hay semestres registrados.
        </div>
    </div>

@endforelse
