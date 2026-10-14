@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')

    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <h1 class="fw-bold" style="color:#012970;">
                <i class="bi bi-pencil-square me-2"></i>Modificar Asignatura
            </h1>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('materias') }}">Materias</a>
                    </li>
                    <li class="breadcrumb-item active">Editar Registro</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row justify-content-center">
                <div class="col-lg-10">

                    <div class="card border-0 shadow-lg materia-card">

                        <div class="card-header materia-header edit">
                            <div class="d-flex align-items-center">
                                <div class="header-icon warning me-3">
                                    <i class="bi bi-journal-text"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1 fw-bold" style="color:#012970;">
                                        Editor de Materia
                                    </h4>
                                    <p class="mb-0 text-muted">
                                        Editando la asignatura:
                                        <strong>{{ $item->nombre }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-lg-5">

                            <form action="{{ route('materias.update', $item->id) }}" method="POST" id="formMateria">

                                @csrf
                                @method('PUT')

                                <div class="row g-4">

                                    <div class="col-12">
                                        <label class="form-label fw-bold text-dark">
                                            <i class="bi bi-fonts me-2 text-primary"></i>
                                            Nombre de la Materia
                                        </label>

                                        <input type="text" name="nombre" id="nombre"
                                            class="form-control form-control-lg custom-input" value="{{ $item->nombre }}"
                                            oninput="this.value = this.value.toUpperCase();" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark">
                                            <i class="bi bi-key me-2 text-primary"></i>
                                            Clave de la Materia
                                        </label>

                                        <input type="text" name="clave" id="clave"
                                            class="form-control custom-input" value="{{ $item->clave }}"
                                            oninput="this.value = this.value.toUpperCase();" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark">
                                            <i class="bi bi-list-check me-2 text-primary"></i>
                                            Número de Unidades
                                        </label>

                                        <div class="input-group">
                                            <input type="number" name="unidades" id="unidades"
                                                class="form-control custom-input" min="1" max="10"
                                                value="{{ $item->unidades }}" required>

                                            <span class="input-group-text bg-light border-0 fw-semibold">
                                                Unid.
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark">
                                            <i class="bi bi-mortarboard me-2 text-primary"></i>
                                            Carrera Correspondiente
                                        </label>

                                        <select name="carrera" id="carrera" class="form-select custom-input" required>

                                            @php
                                                $carreras = [
                                                    'INGENIERIA EN SISTEMAS COMPUTACIONALES',
                                                    'INGENIERIA INDUSTRIAL',
                                                    'INGENIERIA EN GESTIÓN EMPRESARIAL',
                                                    'LICENCIATURA EN TURISMO',
                                                ];
                                            @endphp

                                            @foreach ($carreras as $carrera)
                                                <option value="{{ $carrera }}"
                                                    {{ $item->carrera == $carrera ? 'selected' : '' }}>
                                                    {{ $carrera }}
                                                </option>
                                            @endforeach

                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark">
                                            <i class="bi bi-calendar3 me-2 text-primary"></i>
                                            Semestre
                                        </label>

                                        <select name="semestre" id="semestre" class="form-select custom-input" required>
                                            @for ($i = 1; $i <= 9; $i++)
                                                <option value="{{ $i }}"
                                                    {{ $item->semestre == $i ? 'selected' : '' }}>
                                                    {{ $i }}° Semestre
                                                </option>
                                            @endfor
                                        </select>
                                    </div>

                                </div>

                                <div class="mt-5 pt-4 border-top">
                                    <div class="d-flex justify-content-end flex-wrap gap-3">

                                        <a href="{{ route('materias') }}" class="btn btn-light px-4 py-2 shadow-sm">
                                            <i class="bi bi-arrow-left-circle me-2"></i>
                                            Cancelar
                                        </a>

                                        <button type="submit" class="btn px-5 py-2 fw-bold shadow-sm"
                                            style="background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%);
                                                   border:none;
                                                   color:#212529;
                                                   border-radius:10px;">
                                            <i class="bi bi-save me-2"></i>
                                            Guardar Cambios
                                        </button>

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
