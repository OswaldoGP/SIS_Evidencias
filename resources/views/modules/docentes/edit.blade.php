@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')

    @php
        $usuarioActual = Auth::user();
        $rolActual = strtolower($usuarioActual->rol ?? '');
        $esAdmin = $rolActual === 'admin';
        $itemEsEncargado = strtolower($item->rol ?? '') === 'encargado';
        $itemEsAdmin = strtolower($item->rol ?? '') === 'admin';
    @endphp

    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <h1 class="fw-bold text-primary">Modificar Usuario</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home') }}" class="text-decoration-none text-secondary">Home</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('docentes') }}" class="text-decoration-none text-secondary">Docentes</a>
                    </li>
                    <li class="breadcrumb-item active text-primary fw-semibold">Editar Registro</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card border-0 shadow-lg usuario-card">

                        <div class="card-header usuario-header warning">
                            <div class="d-flex align-items-center">
                                <div class="header-icon warning me-3">
                                    <i class="bi bi-pencil-square"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1 text-dark">Editor de Usuario</h4>
                                    <p class="text-muted mb-0">
                                        Editando a: <strong>{{ $item->name }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-lg-5">
                            <form action="{{ route('docentes.update', $item->id) }}" id="formUsuario" method="POST"
                                class="needs-validation">
                                @csrf
                                @method('PUT')

                                <div class="col-md-12">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-person me-1"></i> Nombre(s)
                                    </label>
                                    <input name="name" type="text" class="form-control custom-input"
                                        oninput="this.value = this.value.toUpperCase();" value="{{ $item->name }}"
                                        required>
                                </div>

                                <div class="row g-4 mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-shield-lock me-1"></i> Rol de Sistema
                                        </label>
                                        <select name="rol" id="rol_select" class="form-select custom-input" required>
                                            <option value="docente" {{ $item->rol == 'docente' ? 'selected' : '' }}>
                                                DOCENTE
                                            </option>
                                            <option value="admin" {{ $item->rol == 'admin' ? 'selected' : '' }}>
                                                ADMINISTRADOR
                                            </option>
                                            @if ($esAdmin)
                                                <option value="encargado" {{ $item->rol == 'encargado' ? 'selected' : '' }}>
                                                    ENCARGADO
                                                </option>
                                            @endif
                                        </select>
                                    </div>

                                    <div class="col-md-6" id="cargo_container"
                                        style="{{ $itemEsAdmin ? '' : 'display: none;' }}">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-briefcase me-1"></i> Cargo del Administrador
                                        </label>
                                        <input type="text" name="cargo" id="cargo"
                                            class="form-control custom-input"
                                            placeholder="JEFE DEL DEPARTAMENTO DE CIENCIAS BÁSICAS"
                                            value="{{ $itemEsAdmin ? $item->cargo : '' }}"
                                            style="text-transform: uppercase;">
                                    </div>

                                    <div class="col-md-6" id="dpto_container"
                                        style="{{ $itemEsEncargado ? 'display: none;' : '' }}">
                                        <label class="form-label fw-bold">Departamento</label>
                                        <select name="dpto" id="dpto" class="form-select custom-input"
                                            {{ $itemEsEncargado ? '' : 'required' }}>
                                            <option value="" disabled>SELECCIONA UN DEPARTAMENTO</option>
                                            <option value="Ciencias Económico-Administrativas"
                                                {{ $item->departamento == 'Ciencias Económico-Administrativas' ? 'selected' : '' }}>
                                                CIENCIAS ECONÓMICO-ADMINISTRATIVAS
                                            </option>
                                            <option value="Ciencias Básicas y Sistemas"
                                                {{ $item->departamento == 'Ciencias Básicas y Sistemas' ? 'selected' : '' }}>
                                                CIENCIAS BÁSICAS Y SISTEMAS
                                            </option>
                                            <option value="Departamento de Ingenierías"
                                                {{ $item->departamento == 'Departamento de Ingenierías' ? 'selected' : '' }}>
                                                DEPARTAMENTO DE INGENIERÍAS
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Correo Electrónico (Usuario)</label>
                                        <input type="email" name="email" id="email_username"
                                            class="form-control custom-input" placeholder="usuario@itma.edu.mx"
                                            value="{{ $item->email }}" required>
                                    </div>

                                    <div class="col-md-6" id="celular_container"
                                        style="{{ $itemEsEncargado ? 'display: none;' : '' }}">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-phone me-1"></i> Celular
                                        </label>
                                        <input type="text" name="celular" id="celular"
                                            class="form-control custom-input" placeholder="5512345678" maxlength="10"
                                            autocomplete="off" value="{{ old('celular', $item->docente->celular ?? '') }}"
                                            {{ $itemEsEncargado ? '' : 'required' }}>
                                    </div>

                                    <div class="col-12 mt-5">
                                        <div class="d-flex justify-content-end gap-3">
                                            <a href="{{ route('docentes') }}"
                                                class="btn btn-outline-secondary px-4 rounded-pill">
                                                <i class="bi bi-x-circle me-1"></i> Cancelar
                                            </a>
                                            <button type="submit"
                                                class="btn btn-warning px-5 rounded-pill shadow-sm fw-bold text-dark">
                                                <i class="bi bi-arrow-clockwise me-1"></i> Actualizar Registro
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
    <link rel="stylesheet" href="{{ asset('css/docentes.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/docentes.js') }}"></script>
@endpush
