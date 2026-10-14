@extends('layouts.main')

@section('titulo', 'Editar Segundas Oportunidades')

@section('contenido')
    <main id="main" class="main">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <span class="badge bg-warning text-dark px-3 py-2 mb-2">
                            <i class="bi bi-clock-history me-1"></i> Segundas Oportunidades
                        </span>

                        <h2 class="fw-bold text-primary mb-1">
                            {{ $evidencia->materia->nombre }}
                        </h2>

                        <div class="text-muted">
                            <i class="bi bi-person-fill me-1"></i>
                            {{ $evidencia->asignacionMateria->docente->name ?? 'Sin docente' }}
                        </div>
                    </div>

                    <a href="{{ route('evidencias') }}" class="btn btn-light border px-4 rounded-pill">
                        <i class="bi bi-arrow-left me-1"></i> Regresar
                    </a>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card p-4 shadow-lg border-0" style="border-radius: 18px;">

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <h6 class="fw-bold">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Corrige los errores:
                        </h6>
                        <ul class="mb-0 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('evidencias.update', $evidencia->id) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="materia_id" value="{{ $evidencia->materia->id }}">
                    <input type="hidden" name="revision_id" value="{{ $evidencia->revision->id }}">

                    <h5 class="fw-bold text-primary mb-3">¿QUÉ UNIDADES EVALUASTE?</h5>

                    <div class="row g-3 mb-4" id="contenedor_tarjetas_unidades">
                        @foreach ($unidadesEvaluadas as $u)
                            @php $estaGuardada = in_array($u, $unidadesGuardadas, true); @endphp
                            <div class="col-md-3">
                                <div class="card card-unidad-check p-3 text-center shadow-sm h-100 d-flex flex-column align-items-center justify-content-center {{ $estaGuardada ? 'active' : '' }}"
                                    id="card_unidad_{{ $u }}"
                                    onclick="toggleUnidadTarjetaEditSegundas({{ $u }})">
                                    <input type="checkbox" id="chk_unidad_{{ $u }}" name="unidades[]"
                                        value="{{ $u }}" class="d-none" {{ $estaGuardada ? 'checked' : '' }}>
                                    <i class="bi bi-bookmark-check fs-3 text-primary mb-2"></i>
                                    <span class="fw-bold text-dark">Unidad {{ $u }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3">DOCUMENTOS ACTUALES</h5>

                    <div class="row g-4">

                        <div class="col-md-6">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-2.5 rounded-3 bg-secondary-subtle text-secondary me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Actividades de Regularización
                                        (RAC)</label>
                                </div>

                                <div class="lista-pdfs mb-3">
                                    @forelse ($racSegundas as $ruta)
                                        <div
                                            class="pdf-item border rounded-3 p-2 bg-light mb-2 d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i>
                                                <a href="{{ asset('storage/' . $ruta) }}" target="_blank"
                                                    class="text-decoration-none fw-semibold small">
                                                    {{ basename($ruta) }}
                                                </a>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle"
                                                onclick="marcarEliminarPdfSegundas(this, '{{ $ruta }}', 'eliminar_rac_segundas[]')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    @empty
                                        <div class="alert alert-secondary py-2 px-3 small">No hay PDFs cargados.</div>
                                    @endforelse
                                </div>

                                <label class="form-label fw-semibold small">Agregar más PDFs</label>
                                <input type="file" name="rac_segundas[]"
                                    class="form-control form-control-sm archivo-pdf-2mb" accept="application/pdf" multiple>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-2.5 rounded-3 bg-warning-subtle text-warning me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bi bi-table"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Rúbricas</label>
                                </div>

                                <div class="lista-pdfs mb-3">
                                    @forelse ($rubricasSegundas as $ruta)
                                        <div
                                            class="pdf-item border rounded-3 p-2 bg-light mb-2 d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i>
                                                <a href="{{ asset('storage/' . $ruta) }}" target="_blank"
                                                    class="text-decoration-none fw-semibold small">
                                                    {{ basename($ruta) }}
                                                </a>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle"
                                                onclick="marcarEliminarPdfSegundas(this, '{{ $ruta }}', 'eliminar_rubricas_segundas[]')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    @empty
                                        <div class="alert alert-secondary py-2 px-3 small">No hay PDFs cargados.</div>
                                    @endforelse
                                </div>

                                <label class="form-label fw-semibold small">Agregar más PDFs</label>
                                <input type="file" name="rubricas_segundas[]"
                                    class="form-control form-control-sm archivo-pdf-2mb" accept="application/pdf" multiple>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100 border border-light-subtle rounded-3 shadow-sm p-4 bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-2.5 rounded-3 bg-success-subtle text-success me-3 fs-4 d-inline-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bi bi-card-checklist"></i>
                                    </div>
                                    <label class="form-label fw-bold text-dark fs-5 mb-0">Lista de calificaciones</label>
                                </div>

                                <div class="lista-pdfs mb-3">
                                    @forelse ($calificacionesSegundas as $ruta)
                                        <div
                                            class="pdf-item border rounded-3 p-2 bg-light mb-2 d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i>
                                                <a href="{{ asset('storage/' . $ruta) }}" target="_blank"
                                                    class="text-decoration-none fw-semibold small">
                                                    {{ basename($ruta) }}
                                                </a>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle"
                                                onclick="marcarEliminarPdfSegundas(this, '{{ $ruta }}', 'eliminar_calificaciones_segundas[]')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    @empty
                                        <div class="alert alert-secondary py-2 px-3 small">No hay PDFs cargados.</div>
                                    @endforelse
                                </div>

                                <label class="form-label fw-semibold small">Agregar más PDFs</label>
                                <input type="file" name="calificaciones_segundas[]"
                                    class="form-control form-control-sm archivo-pdf-2mb" accept="application/pdf"
                                    multiple>
                            </div>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex gap-3 justify-content-end">
                        <a href="{{ route('evidencias') }}" class="btn btn-light border px-4 rounded-pill">
                            <i class="bi bi-arrow-left me-1"></i> Regresar
                        </a>

                        <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm fw-bold">
                            <i class="bi bi-floppy me-1"></i> Actualizar
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
        function toggleUnidadTarjetaEditSegundas(num) {
            const chk = document.getElementById('chk_unidad_' + num);
            const card = document.getElementById('card_unidad_' + num);
            if (!chk || !card) return;
            chk.checked = !chk.checked;
            card.classList.toggle('active', chk.checked);
        }

        function marcarEliminarPdfSegundas(btn, ruta, inputName) {
            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar archivo?',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                btn.closest('.pdf-item').remove();
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = inputName;
                hidden.value = ruta;
                btn.closest('form').appendChild(hidden);
            });
        }
    </script>
    <script src="{{ asset('js/evidencias.js') }}"></script>
@endpush
