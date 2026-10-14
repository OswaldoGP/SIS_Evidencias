@extends('layouts.main')

@section('titulo', $titulo)

@section('contenido')
    <main id="main" class="main">

        <div class="pagetitle mb-4">
            <h1 class="fw-bold text-primary">
                Panel del Encargado
            </h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('encargado.dashboard') }}" class="text-decoration-none text-secondary">Inicio</a>
                    </li>
                    <li class="breadcrumb-item active text-primary fw-semibold">Dashboard</li>
                </ol>
            </nav>
        </div>

        <section class="section dashboard">
            <div class="row g-4">

                <div class="col-lg-12">
                    <div class="card border-0 shadow-lg encargado-hero">
                        <div class="card-body p-4 p-lg-5 d-flex align-items-center justify-content-between flex-wrap gap-4">
                            <div>
                                <h2 class="fw-bold mb-2 text-dark">
                                    ¡Bienvenido, {{ auth()->user()->name }}!
                                </h2>
                                <p class="text-muted mb-0">
                                    Accede rápidamente a los módulos que tienes habilitados.
                                </p>
                            </div>

                            <div class="encargado-hero-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <h5 class="fw-bold text-secondary mb-3">Accesos rápidos</h5>
                </div>

                <div class="col-xl-4 col-md-6">
                    <a href="{{ route('semestres') }}" class="quick-card">
                        <div class="quick-card-icon quick-blue">
                            <i class="fa-solid fa-school-circle-check"></i>
                        </div>
                        <div class="quick-card-text">
                            <h6>Gestión de Semestres</h6>
                            <p>Administra periodos académicos</p>
                        </div>
                        <i class="bi bi-arrow-right quick-card-arrow"></i>
                    </a>
                </div>

                <div class="col-xl-4 col-md-6">
                    <a href="{{ route('docentes') }}" class="quick-card">
                        <div class="quick-card-icon quick-purple">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <div class="quick-card-text">
                            <h6>Control de Docentes</h6>
                            <p>Administra usuarios y credenciales</p>
                        </div>
                        <i class="bi bi-arrow-right quick-card-arrow"></i>
                    </a>
                </div>

                <div class="col-xl-4 col-md-6">
                    <a href="{{ route('revisiones') }}" class="quick-card">
                        <div class="quick-card-icon quick-orange">
                            <i class="fa-solid fa-person-chalkboard"></i>
                        </div>
                        <div class="quick-card-text">
                            <h6>Revisiones</h6>
                            <p>Gestiona las revisiones activas</p>
                        </div>
                        <i class="bi bi-arrow-right quick-card-arrow"></i>
                    </a>
                </div>

                <div class="col-xl-4 col-md-6">
                    <a href="{{ route('archivos') }}" class="quick-card">
                        <div class="quick-card-icon quick-yellow">
                            <i class="fa-solid fa-folder-closed"></i>
                        </div>
                        <div class="quick-card-text">
                            <h6>Archivos</h6>
                            <p>Consulta y descarga documentos</p>
                        </div>
                        <i class="bi bi-arrow-right quick-card-arrow"></i>
                    </a>
                </div>

                <div class="col-xl-4 col-md-6">
                    <a href="{{ route('imagenes') }}" class="quick-card">
                        <div class="quick-card-icon quick-green">
                            <i class="fa-solid fa-file-image"></i>
                        </div>
                        <div class="quick-card-text">
                            <h6>Imágenes de Oficios</h6>
                            <p>Configura encabezados y sellos</p>
                        </div>
                        <i class="bi bi-arrow-right quick-card-arrow"></i>
                    </a>
                </div>

            </div>
        </section>
    </main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/encargado.css') }}">
@endpush
