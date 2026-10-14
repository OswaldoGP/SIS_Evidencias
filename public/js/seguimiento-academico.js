document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const body = document.body;
    const urlRechazar = body?.dataset?.rechazarUrl;

    if (!csrfToken || !urlRechazar) {
        // No estamos en la vista de seguimiento académico
        return;
    }

    document.querySelectorAll('.btn-rechazar-sin-evidencia').forEach(btn => {
        btn.addEventListener('click', async function () {
            const asignacionId = this.dataset.asignacion;
            const materiaId = this.dataset.materia;
            const revisionId = this.dataset.revision;

            const { value: datos } = await Swal.fire({
                title: 'Rechazar evidencia',
                text: 'El docente no ha adjuntado ninguna evidencia. A continuación, registra la evaluación de seguimiento:',
                width: 900,
                showCancelButton: true,
                confirmButtonText: 'Rechazar y guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                background: '#f8f9fa',
                customClass: {
                    popup: 'rounded-4 shadow-lg',
                    title: 'fw-bold text-danger',
                    confirmButton: 'btn btn-danger rounded-pill px-4',
                    cancelButton: 'btn btn-outline-secondary rounded-pill px-4'
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
                                <input type="number" id="avance_calificacion" class="fachero-input mb-3"
                                    placeholder="Ej: 85" min="0" max="100">
                                <label class="fw-semibold mb-1">Observaciones</label>
                                <textarea id="avance_observaciones" class="fachero-textarea" rows="2"
                                    placeholder="Comentarios sobre el avance..."></textarea>
                                <label class="label-na">
                                    <span class="fw-semibold">No aplica (N/A)</span>
                                    <label class="switch">
                                        <input type="checkbox" id="avance_na">
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
                                <input type="number" id="seguimiento_calificacion" class="fachero-input mb-3"
                                    placeholder="Ej: 90" min="0" max="100">
                                <label class="fw-semibold mb-1">Observaciones</label>
                                <textarea id="seguimiento_observaciones" class="fachero-textarea" rows="2"
                                    placeholder="Comentarios sobre asistencia..."></textarea>
                                <label class="label-na">
                                    <span class="fw-semibold">No aplica (N/A)</span>
                                    <label class="switch">
                                        <input type="checkbox" id="seguimiento_na">
                                        <span class="slider"></span>
                                    </label>
                                </label>
                            </div>
                        </div>
                    </div>
                `,
                didOpen: () => {
                    const avanceNa = document.getElementById('avance_na');
                    const avanceCalif = document.getElementById('avance_calificacion');
                    const seguimientoNa = document.getElementById('seguimiento_na');
                    const seguimientoCalif = document.getElementById('seguimiento_calificacion');

                    const toggleAvance = () => {
                        if (avanceNa.checked) {
                            avanceCalif.disabled = true;
                            avanceCalif.value = '';
                            avanceCalif.classList.add('bg-light', 'opacity-50');
                        } else {
                            avanceCalif.disabled = false;
                            avanceCalif.classList.remove('bg-light', 'opacity-50');
                        }
                    };

                    const toggleSeguimiento = () => {
                        if (seguimientoNa.checked) {
                            seguimientoCalif.disabled = true;
                            seguimientoCalif.value = '';
                            seguimientoCalif.classList.add('bg-light', 'opacity-50');
                        } else {
                            seguimientoCalif.disabled = false;
                            seguimientoCalif.classList.remove('bg-light', 'opacity-50');
                        }
                    };

                    avanceNa.addEventListener('change', toggleAvance);
                    seguimientoNa.addEventListener('change', toggleSeguimiento);
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
                            na: document.getElementById('avance_na').checked ? 1 : 0
                        },
                        asiste_seguimiento: {
                            calificacion: seguimientoCalif.disabled ? '' : seguimientoCalif.value,
                            observaciones: document.getElementById('seguimiento_observaciones').value,
                            na: document.getElementById('seguimiento_na').checked ? 1 : 0
                        }
                    };
                }
            });

            if (!datos) return;

            fetch(urlRechazar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    asignacion_materia_id: asignacionId,
                    materia_id: materiaId,
                    revision_id: revisionId,
                    evaluaciones: {
                        avance_programatico: datos.avance_programatico,
                        asiste_seguimiento: datos.asiste_seguimiento
                    }
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Evidencia rechazada',
                            text: 'La revisión fue rechazada y la evaluación de seguimiento se ha guardado.'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Ocurrió un error al guardar.', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo completar la operación.', 'error');
                    console.error(error);
                });
        });
    });
});