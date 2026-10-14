@extends('layouts.main')

@section('titulo', 'Registrar Usuario')

@section('contenido')

    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <h1 class="fw-bold text-primary">Gestión de Usuarios</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home') }}" class="text-decoration-none text-secondary">Home</a>
                    </li>
                    <li class="breadcrumb-item">Usuarios</li>
                    <li class="breadcrumb-item active text-primary fw-semibold">Nuevo Registro</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    <div class="card border-0 shadow-lg usuario-card">

                        <div class="card-header usuario-header">
                            <div class="d-flex align-items-center">
                                <div class="header-icon me-3">
                                    <i class="bi bi-person-plus-fill"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1 text-dark">Datos del Nuevo Usuario</h4>
                                    <p class="text-muted mb-0">Complete la información para registrar un nuevo docente o
                                        administrador</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-lg-5">

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <strong>Revisa los siguientes campos:</strong>
                                    <ul class="mb-0 mt-2">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <form action="{{ route('docente.store') }}" method="POST">
                                @csrf

                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Nombre(s)</label>
                                        <input type="text" name="name" class="form-control custom-input"
                                            placeholder="NOMBRE" value="{{ old('name') }}"
                                            style="text-transform: uppercase;" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Apellido Paterno</label>
                                        <input type="text" name="apellido_p" class="form-control custom-input"
                                            placeholder="APELLIDO PATERNO" value="{{ old('apellido_p') }}"
                                            style="text-transform: uppercase;" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Apellido Materno</label>
                                        <input type="text" name="apellido_m" class="form-control custom-input"
                                            placeholder="APELLIDO MATERNO" value="{{ old('apellido_m') }}"
                                            style="text-transform: uppercase;">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Rol de Usuario</label>
                                        <select name="rol" id="rol_select" class="form-select custom-input" required>
                                            <option value="docente"
                                                {{ old('rol', 'docente') == 'docente' ? 'selected' : '' }}>DOCENTE</option>
                                            <option value="admin" {{ old('rol') == 'admin' ? 'selected' : '' }}>
                                                ADMINISTRADOR</option>
                                            <option value="encargado" {{ old('rol') == 'encargado' ? 'selected' : '' }}>
                                                ENCARGADO</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 d-none" id="perfil_docente_container">
                                        <label class="form-label fw-bold">Perfil Docente</label>
                                        <div class="border rounded-3 px-3 py-2 bg-light perfil-docente-box">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="perfil_docente"
                                                    id="perfil_docente" value="1"
                                                    {{ old('perfil_docente') ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="perfil_docente">
                                                    Este administrador también es docente
                                                </label>
                                            </div>
                                            <small class="text-muted">Activa esta opción si el administrador también
                                                impartirá materias.</small>
                                        </div>
                                    </div>

                                    <div class="col-md-6" id="cargo_container" style="display: none;">
                                        <label class="form-label fw-bold">Cargo del Administrador</label>
                                        <input type="text" name="cargo" id="cargo"
                                            class="form-control custom-input"
                                            placeholder="JEFE DEL DEPARTAMENTO DE CIENCIAS BÁSICAS"
                                            value="{{ old('cargo') }}" style="text-transform: uppercase;">
                                        <small class="text-muted">Este cargo se guardará en el perfil del
                                            administrador.</small>
                                    </div>

                                    <div class="col-md-6" id="correo_container">
                                        <label class="form-label fw-bold">Correo Electrónico (Usuario)</label>
                                        <input type="email" name="email" id="email_username"
                                            class="form-control custom-input" placeholder="usuario@itma.edu.mx"
                                            value="{{ old('email') }}" required>
                                    </div>

                                    <div class="col-12" id="datos_docente_container">
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Departamento</label>
                                                <select name="dpto" id="dpto" class="form-select custom-input">
                                                    <option value="" selected disabled>SELECCIONA UN DEPARTAMENTO
                                                    </option>
                                                    <option value="Ciencias Económico-Administrativas"
                                                        {{ old('dpto') == 'Ciencias Económico-Administrativas' ? 'selected' : '' }}>
                                                        CIENCIAS ECONÓMICO-ADMINISTRATIVAS
                                                    </option>
                                                    <option value="Ciencias Básicas y Sistemas"
                                                        {{ old('dpto') == 'Ciencias Básicas y Sistemas' ? 'selected' : '' }}>
                                                        CIENCIAS BÁSICAS Y SISTEMAS
                                                    </option>
                                                    <option value="Departamento de Ingenierías"
                                                        {{ old('dpto') == 'Departamento de Ingenierías' ? 'selected' : '' }}>
                                                        DEPARTAMENTO DE INGENIERÍAS
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Número de Celular</label>
                                                <div class="input-group">
                                                    <span
                                                        class="input-group-text bg-white text-success fw-bold phone-prefix">
                                                        <i class="bi bi-whatsapp me-2"></i>
                                                        +52
                                                    </span>
                                                    <input type="text" name="celular" id="celular"
                                                        class="form-control custom-input phone-input"
                                                        placeholder="5512345678" maxlength="10" autocomplete="off"
                                                        value="{{ old('celular') }}">
                                                </div>
                                                <small class="text-muted">Ingresa únicamente 10 dígitos.</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-5">
                                        <div class="d-flex justify-content-end gap-3 flex-wrap">
                                            <a href="{{ route('docentes') }}"
                                                class="btn btn-outline-secondary px-4 rounded-pill">
                                                <i class="bi bi-x-circle me-1"></i> Cancelar
                                            </a>
                                            <button type="submit"
                                                class="btn btn-primary px-5 rounded-pill shadow-sm fw-bold">
                                                <i class="bi bi-save me-1"></i> Registrar Usuario
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
