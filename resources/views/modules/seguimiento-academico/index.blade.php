@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')

    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="fw-bold text-primary mb-1">
                        Administración de Evidencias
                    </h1>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="" class="text-decoration-none text-secondary">Home</a>
                            </li>
                            <li class="breadcrumb-item active text-primary fw-semibold">
                                Evidencias
                            </li>
                        </ol>
                    </nav>
                </div>

                <div>
                    <button type="button" class="btn btn-info text-white rounded-pill px-4 shadow-sm"
                        data-bs-toggle="modal" data-bs-target="#modalManualEvidencias">
                        <i class="bi bi-question-circle me-1"></i> Ayuda
                    </button>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card border-0 shadow-lg evidencia-card">
                        <div class="card-header evidencia-header">
                            <div>
                                <h4 class="mb-1 fw-bold">Evidencias de Docentes</h4>
                                <p class="text-muted mb-0">
                                    Consulta el estado de evidencias registradas por cada docente.
                                </p>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="estadoLegend mb-4">
                                <div class="estadoCard">
                                    <div class="estadoIcon aprobado">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h6>Aprobada</h6>
                                        <small>Validada por administración</small>
                                    </div>
                                </div>
                                <div class="estadoCard">
                                    <div class="estadoIcon pendiente" style="background-color: #ffedd5 !important; color: #ea580c !important;">
                                        <i class="bi bi-exclamation-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h6>Pendiente</h6>
                                        <small>En espera de revisión</small>
                                    </div>
                                </div>
                                <div class="estadoCard">
                                    <div class="estadoIcon rechazada">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h6>Rechazada</h6>
                                        <small>Requiere corrección</small>
                                    </div>
                                </div>
                                <div class="estadoCard">
                                    <div class="estadoIcon vacio">
                                        <i class="bi bi-dash-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h6>Sin evidencia</h6>
                                        <small>No entregada</small>
                                    </div>
                                </div>
                                <div class="estadoCard">
                                    <div class="estadoIcon asignada">
                                        <i class="bi bi-pause-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h6>Revisión inactiva</h6>
                                        <small>Actualmente deshabilitada</small>
                                    </div>
                                </div>
                            </div>

                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4"
                                    role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i><strong>¡Éxito!</strong>
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                        aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4"
                                    role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Atención:</strong>
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                        aria-label="Close"></button>
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 22%;">Docente</th>
                                            <th class="text-center" style="width: 22%;">Asignatura</th>
                                            <th class="text-center" style="width: 8%;">Grupo</th>
                                            @foreach ($revisiones as $revision)
                                                <th class="text-center" style="width: 8%;">
                                                    <div class="fw-bold">{{ $revision->nombre }}</div>
                                                    @if ($revision->fecha_limite)
                                                        <small class="d-block mt-1 text-white-50">
                                                            Límite:
                                                            {{ \Carbon\Carbon::parse($revision->fecha_limite)->format('d/m/Y') }}
                                                        </small>
                                                    @else
                                                        <small class="d-block mt-1 text-white-50">No activa</small>
                                                    @endif
                                                </th>
                                            @endforeach
                                            <th class="text-center" style="width: 15%; min-width: 160px;">Avance Real</th>
                                            <th class="text-center" style="width: 100px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-center">
                                        @forelse ($asignaciones as $asig)
                                            @php
                                                $evidenciasSubidas = $asig->evidencias;
                                                $totalRevisiones = $revisiones->where('activo', 1)->count();
                                                $cantidadAprobadas = $evidenciasSubidas->where('estado', 2)->count();
                                                $tieneRechazadas = $evidenciasSubidas->where('estado', 4)->count() > 0;

                                                $porcentaje =
                                                    $totalRevisiones > 0
                                                        ? ($cantidadAprobadas / $totalRevisiones) * 100
                                                        : 0;
                                                $porcentaje = (int) round($porcentaje);
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold text-center">
                                                    {{ $asig->docente->user->name ?? 'Sin docente' }}
                                                </td>
                                                <td class="fw-semibold text-center">
                                                    {{ $asig->materia->nombre ?? 'Sin materia' }}
                                                </td>
                                                <td class="text-center">
                                                    <span
                                                        class="badge bg-primary-subtle text-primary border border-primary px-3 py-2">
                                                        {{ $asig->grupo }}
                                                    </span>
                                                </td>

                                                @foreach ($revisiones as $revision)
                                                    @php
                                                        $evidenciaActual = $evidenciasSubidas
                                                            ->where('revision_id', $revision->id)
                                                            ->first();

                                                        if (!$revision->activo) {
                                                            $estado = 0;
                                                        } elseif (!$evidenciaActual) {
                                                            $estado = 1;
                                                        } else {
                                                            $estado = $evidenciaActual->estado;
                                                        }
                                                    @endphp
                                                    <td>
                                                        @switch($estado)
                                                            @case(0)
                                                                <button class="estadoBtn asignada" title="Revisión inactiva"
                                                                    style="cursor: not-allowed; opacity: 0.6;">
                                                                    <i class="bi bi-pause-circle-fill"></i>
                                                                </button>
                                                            @break

                                                            @case(1)
                                                                <button type="button"
                                                                    class="estadoBtn vacio btn-rechazar-sin-evidencia"
                                                                    data-asignacion="{{ $asig->id }}"
                                                                    data-materia="{{ $asig->materia_id }}"
                                                                    data-revision="{{ $revision->id }}" title="Sin evidencia">
                                                                    <i class="bi bi-dash-circle-fill"></i>
                                                                </button>
                                                            @break

                                                            @case(2)
                                                                <a href="{{ route('evaluaciones.show', $evidenciaActual->id) }}"
                                                                    class="d-inline-block"
                                                                    title="Evidencia Aprobada. Clic para modificar dictamen.">
                                                                    <button class="estadoBtn aprobado">
                                                                        <i class="bi bi-check-circle-fill"></i>
                                                                    </button>
                                                                </a>
                                                            @break

                                                            @case(3)
                                                                <a href="{{ route('evaluaciones.show', $evidenciaActual->id) }}"
                                                                    class="d-inline-block"
                                                                    title="Evidencia Pendiente. Clic para evaluar de inmediato.">
                                                                    <button class="estadoBtn pendiente" style="background-color: #ffedd5 !important; color: #ea580c !important;">
                                                                        <i class="bi bi-exclamation-circle-fill"></i>
                                                                    </button>
                                                                </a>
                                                            @break

                                                            @case(4)
                                                                <a href="{{ route('evaluaciones.show', $evidenciaActual->id) }}"
                                                                    class="d-inline-block"
                                                                    title="Evidencia Rechazada. Clic para modificar observaciones.">
                                                                    <button class="estadoBtn rechazada">
                                                                        <i class="bi bi-x-circle-fill"></i>
                                                                    </button>
                                                                </a>
                                                            @break
                                                        @endswitch
                                                    </td>
                                                @endforeach

                                                <td class="text-center align-middle">
                                                    @php
                                                        if ($tieneRechazadas) {
                                                            $colorBarra = 'bg-danger';
                                                        } elseif ($porcentaje >= 100) {
                                                            $colorBarra = 'bg-success';
                                                        } else {
                                                            $colorBarra = 'bg-primary';
                                                        }
                                                    @endphp

                                                    <div
                                                        class="d-flex align-items-center justify-content-center gap-2 px-2">
                                                        <div class="progress progress-custom w-100">
                                                            <div class="progress-bar {{ $colorBarra }}"
                                                                role="progressbar" style="width: {{ $porcentaje }}%;"
                                                                aria-valuenow="{{ $porcentaje }}" aria-valuemin="0"
                                                                aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="fw-bold @if ($tieneRechazadas) text-danger @else text-dark @endif"
                                                            style="min-width: 42px; text-align: right;">
                                                            {{ $porcentaje }}%
                                                        </span>
                                                    </div>
                                                </td>

                                                <td class="text-center align-middle">
                                                    @php
                                                        $docente = $asig->docente;

                                                        $totalRechazadas = $evidenciasSubidas
                                                            ->where('estado', 4)
                                                            ->count();

                                                        if ($totalRechazadas > 0) {
                                                            $mensaje = "Hola {$docente?->user?->name}, tienes {$totalRechazadas} evidencia(s) rechazada(s) en {$asig->materia->nombre} ({$asig->grupo}). Favor de revisarlas y corregirlas.";
                                                            $btnClass = 'btn-danger';
                                                            $icono = 'bi-exclamation-triangle-fill';
                                                            $tituloBtn = 'Notificar correcciones';
                                                        } elseif ($porcentaje >= 100) {
                                                            $mensaje = "Hola {$docente?->user?->name}, felicidades. Todas las evidencias de {$asig->materia->nombre} ({$asig->grupo}) han sido aprobadas correctamente.";
                                                            $btnClass = 'btn-success';
                                                            $icono = 'bi-trophy-fill';
                                                            $tituloBtn = 'Felicitar docente';
                                                        } else {
                                                            $mensaje = "Hola {$docente?->user?->name}, llevas {$cantidadAprobadas} de {$totalRevisiones} evidencias aprobadas en {$asig->materia->nombre} ({$asig->grupo}). No olvides completar las evidencias restantes.";
                                                            $btnClass = 'btn-warning';
                                                            $icono = 'bi-bell-fill';
                                                            $tituloBtn = 'Recordar evidencias';
                                                        }

                                                        $telefono = preg_replace(
                                                            '/[^0-9]/',
                                                            '',
                                                            $docente?->celular ?? '',
                                                        );
                                                    @endphp

                                                    @if ($telefono)
                                                        <a href="https://wa.me/52{{ $telefono }}?text={{ urlencode($mensaje) }}"
                                                            target="_blank"
                                                            class="btn {{ $btnClass }} rounded-circle"
                                                            title="{{ $tituloBtn }}">
                                                            <i class="bi {{ $icono }}"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $revisiones->count() + 5 }}"
                                                    class="text-center py-5 text-muted">
                                                    <i class="bi bi-folder-x display-4 d-block mb-3"></i>
                                                    No existen evidencias registradas.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('modules.seguimiento-academico.manual')

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/custom-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/seguimiento-academico.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/seguimiento-academico.js') }}"></script>
@endpush