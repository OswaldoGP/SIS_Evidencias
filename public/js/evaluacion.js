document.addEventListener('DOMContentLoaded', function () {
    inicializarEvaluacion();
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

function inicializarEvaluacion() {
    const formEvaluacion = document.getElementById('form-evaluacion');
    if (!formEvaluacion) return;

    const autosaveUrl = document.body.dataset.evaluacionAutosaveUrl;
    const mainEl = document.querySelector('main[data-avance-calificacion]') || document.querySelector('main');

    const datosExistentes = {
        avance: {
            calificacion: mainEl?.dataset.avanceCalificacion || '',
            observaciones: mainEl?.dataset.avanceObservaciones || '',
            na: mainEl?.dataset.avanceNa === '1',
        },
        seguimiento: {
            calificacion: mainEl?.dataset.seguimientoCalificacion || '',
            observaciones: mainEl?.dataset.seguimientoObservaciones || '',
            na: mainEl?.dataset.seguimientoNa === '1',
        },
    };

    document.querySelectorAll('.na-toggle').forEach(function (checkbox) {
        aplicarNoAplica(checkbox);
        checkbox.addEventListener('change', function () {
            aplicarNoAplica(this);
        });
    });

    document.querySelectorAll('.documento-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.documento-btn').forEach(b => {
                b.classList.remove('active', 'text-white', 'bg-primary');
            });

            document.querySelectorAll('.documento-panel').forEach(p => {
                p.classList.add('d-none');
            });

            this.classList.add('active', 'text-white', 'bg-primary');

            const panel = document.getElementById(this.getAttribute('data-target'));
            if (panel) panel.classList.remove('d-none');
        });
    });

    let debounceTimer;

    document.querySelectorAll('.auto-save-input').forEach(input => {
        input.addEventListener('input', triggerAutoSave);
        input.addEventListener('change', triggerAutoSave);
    });

    function triggerAutoSave() {
        clearTimeout(debounceTimer);

        const container = this.closest('.form-evaluacion');
        if (!container) return;

        const key = container.getAttribute('data-key');
        const statusBadge = document.getElementById(`status-${key}`);
        if (!statusBadge) return;

        statusBadge.classList.remove('d-none', 'bg-success', 'bg-danger');
        statusBadge.classList.add('bg-warning', 'text-dark');
        statusBadge.textContent = 'Guardando...';

        debounceTimer = setTimeout(() => {
            ejecutarGuardadoBackend(container, key, statusBadge);
        }, 1000);
    }

    function ejecutarGuardadoBackend(container, key, statusBadge) {
        const checkbox = container.querySelector('input[type="checkbox"]');
        const inputCalificacion = container.querySelector('input[type="number"]');
        const textareaObservaciones = container.querySelector('textarea');

        const calificacion = inputCalificacion ? inputCalificacion.value : '';
        const observaciones = textareaObservaciones ? textareaObservaciones.value : '';

        fetch(autosaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                key: key,
                na: checkbox && checkbox.checked ? 1 : null,
                calificacion: calificacion,
                observaciones: observaciones,
            }),
        })
            .then(response => {
                if (!response.ok) throw new Error('Error en el servidor');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    statusBadge.classList.remove('bg-warning', 'text-dark', 'bg-danger');
                    statusBadge.classList.add('bg-success', 'text-white');
                    statusBadge.textContent = 'Guardado ✓';

                    setTimeout(() => {
                        statusBadge.classList.add('d-none');
                    }, 3000);
                }
            })
            .catch(error => {
                statusBadge.classList.remove('bg-warning', 'text-dark', 'bg-success');
                statusBadge.classList.add('bg-danger', 'text-white');
                statusBadge.textContent = 'Error al guardar ⚠';
                console.error('Error Auto-guardado:', error);
            });
    }

    const btnFinalizar = document.getElementById('btnFinalizar');
    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', async function (e) {
            e.preventDefault();

            const { value: datos } = await Swal.fire({
                title: 'Evaluación Final',
                width: 900,
                showCancelButton: true,
                confirmButtonText: 'Guardar evaluación',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                background: '#f8f9fa',
                customClass: {
                    popup: 'rounded-4 shadow-lg',
                    title: 'fw-bold text-primary',
                    confirmButton: 'btn btn-primary rounded-pill px-4',
                    cancelButton: 'btn btn-outline-secondary rounded-pill px-4',
                },
                html: `
                    <div class="row-fachero">
                        <div class="col-fachero">
                            <div class="fachero-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="fw-bold mb-0">
                                        <i class="bi bi-graph-up"></i> Avance programático
                                    </h5>
                                    <span class="badge-fachero">Obligatorio</span>
                                </div>
                                <hr>
                                <label class="fw-semibold mb-1">Calificación (0-100)</label>
                                <input type="number" id="avance_calificacion" class="fachero-input mb-3" placeholder="Ej: 85" min="0" max="100" value="${datosExistentes.avance.calificacion}">

                                <label class="fw-semibold mb-1">Observaciones</label>
                                <textarea id="avance_observaciones" class="fachero-textarea" rows="2" placeholder="Comentarios sobre el avance...">${datosExistentes.avance.observaciones}</textarea>

                                <label class="label-na">
                                    <span class="fw-semibold">No aplica (N/A)</span>
                                    <label class="switch">
                                        <input type="checkbox" id="avance_na" ${datosExistentes.avance.na ? 'checked' : ''}>
                                        <span class="slider"></span>
                                    </label>
                                </label>
                            </div>
                        </div>

                        <div class="col-fachero">
                            <div class="fachero-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="fw-bold mb-0">
                                        <i class="bi bi-person-check"></i> Asiste al seguimiento
                                    </h5>
                                    <span class="badge-fachero">Obligatorio</span>
                                </div>
                                <hr>
                                <label class="fw-semibold mb-1">Calificación (0-100)</label>
                                <input type="number" id="seguimiento_calificacion" class="fachero-input mb-3" placeholder="Ej: 90" min="0" max="100" value="${datosExistentes.seguimiento.calificacion}">

                                <label class="fw-semibold mb-1">Observaciones</label>
                                <textarea id="seguimiento_observaciones" class="fachero-textarea" rows="2" placeholder="Comentarios sobre asistencia...">${datosExistentes.seguimiento.observaciones}</textarea>

                                <label class="label-na">
                                    <span class="fw-semibold">No aplica (N/A)</span>
                                    <label class="switch">
                                        <input type="checkbox" id="seguimiento_na" ${datosExistentes.seguimiento.na ? 'checked' : ''}>
                                        <span class="slider"></span>
                                    </label>
                                </label>
                            </div>
                        </div>
                    </div>
                `,
                didOpen: () => {
                    const avanceNaCheck = document.getElementById('avance_na');
                    const avanceCalif = document.getElementById('avance_calificacion');
                    const seguimientoNaCheck = document.getElementById('seguimiento_na');
                    const seguimientoCalif = document.getElementById('seguimiento_calificacion');

                    const toggleAvance = () => {
                        if (avanceNaCheck.checked) {
                            avanceCalif.disabled = true;
                            avanceCalif.value = '';
                            avanceCalif.classList.add('bg-light', 'opacity-50');
                        } else {
                            avanceCalif.disabled = false;
                            avanceCalif.classList.remove('bg-light', 'opacity-50');
                        }
                    };

                    const toggleSeguimiento = () => {
                        if (seguimientoNaCheck.checked) {
                            seguimientoCalif.disabled = true;
                            seguimientoCalif.value = '';
                            seguimientoCalif.classList.add('bg-light', 'opacity-50');
                        } else {
                            seguimientoCalif.disabled = false;
                            seguimientoCalif.classList.remove('bg-light', 'opacity-50');
                        }
                    };

                    avanceNaCheck.addEventListener('change', toggleAvance);
                    seguimientoNaCheck.addEventListener('change', toggleSeguimiento);

                    toggleAvance();
                    toggleSeguimiento();
                },
                preConfirm: () => {
                    const avanceCalif = document.getElementById('avance_calificacion');
                    const seguimientoCalif = document.getElementById('seguimiento_calificacion');

                    return {
                        avance_programatico: {
                            calificacion: avanceCalif.disabled ? '' : avanceCalif.value,
                            observaciones: document.getElementById('avance_observaciones').value,
                            na: document.getElementById('avance_na').checked ? 1 : 0,
                        },
                        asiste_seguimiento: {
                            calificacion: seguimientoCalif.disabled ? '' : seguimientoCalif.value,
                            observaciones: document.getElementById('seguimiento_observaciones').value,
                            na: document.getElementById('seguimiento_na').checked ? 1 : 0,
                        },
                    };
                },
            });

            if (!datos) return;

            const form = document.getElementById('form-evaluacion');
            const existingHidden = form.querySelectorAll(
                'input[name^="evaluaciones[avance_programatico]"], input[name^="evaluaciones[asiste_seguimiento]"]'
            );
            existingHidden.forEach(el => el.remove());

            form.insertAdjacentHTML('beforeend', `
                <input type="hidden" name="evaluaciones[avance_programatico][calificacion]" value="${datos.avance_programatico.calificacion}">
                <input type="hidden" name="evaluaciones[avance_programatico][observaciones]" value="${datos.avance_programatico.observaciones.replace(/"/g, '&quot;')}">
                <input type="hidden" name="evaluaciones[avance_programatico][na]" value="${datos.avance_programatico.na}">

                <input type="hidden" name="evaluaciones[asiste_seguimiento][calificacion]" value="${datos.asiste_seguimiento.calificacion}">
                <input type="hidden" name="evaluaciones[asiste_seguimiento][observaciones]" value="${datos.asiste_seguimiento.observaciones.replace(/"/g, '&quot;')}">
                <input type="hidden" name="evaluaciones[asiste_seguimiento][na]" value="${datos.asiste_seguimiento.na}">
            `);

            form.submit();
        });
    }

    window.cambiarPdfMultiple = function (pdfUrl, iframeId, botonActivo, prefijoBotones) {
        const iframe = document.getElementById(iframeId);
        if (iframe) iframe.src = pdfUrl;

        const key = iframeId.replace('iframe-', '');
        const linkExterior = document.getElementById('link-exterior-' + key);
        if (linkExterior) linkExterior.href = pdfUrl;

        const botones = document.querySelectorAll(`[id^="${prefijoBotones}"]`);

        botones.forEach(btn => {
            btn.classList.remove('active', 'text-white', 'bg-primary');
            btn.classList.add('bg-light', 'text-dark', 'border');
        });

        botonActivo.classList.add('active', 'text-white', 'bg-primary');
        botonActivo.classList.remove('bg-light', 'text-dark', 'border');
    };

    window.confirmarEliminarEvidencia = function (event) {
        event.preventDefault();

        Swal.fire({
            title: '¿Eliminar evidencia permanentemente?',
            text: 'Esta acción eliminará la evidencia y TODOS sus archivos adjuntos, sin importar su estado actual. No podrás recuperarlos después.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('form-eliminar-evaluacion').submit();
            }
        });
    };
}

function aplicarNoAplica(checkbox) {
    const key = checkbox.dataset.key;
    const inputCalificacion = document.getElementById('calificacion-' + key);

    if (!inputCalificacion) return;

    if (checkbox.checked) {
        inputCalificacion.value = '';
        inputCalificacion.disabled = true;
        inputCalificacion.classList.add('input-disabled-na');
    } else {
        inputCalificacion.disabled = false;
        inputCalificacion.classList.remove('input-disabled-na');
    }
}