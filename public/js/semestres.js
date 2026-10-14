/**
 * semestres.js
 * Lógica de todas las vistas de semestres:
 *   - index  → toggle-status (activar/desactivar)
 *   - create → formulario (auto-nombre y restricciones de fechas)
 *   - edit   → formulario + confirmación con contraseña al guardar
 *   - show   → eliminar con contraseña
 *
 * Se auto-detecta qué inicializar según qué elementos existan en el DOM.
 * NO contiene Blade. Las URLs y tokens se leen de data-* y del meta csrf-token.
 */

document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    initToggleStatus(csrfToken);
    initEliminar(csrfToken);
    initFormularioSemestre();
    initEditarConPassword(csrfToken);
});


/* ============================================================
 *  1) TOGGLE STATUS (index)
 * ============================================================ */
function initToggleStatus(csrfToken) {
    document.querySelectorAll('.toggle-status').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const $checkbox = this;
            const id = $checkbox.dataset.id;
            const baseUrl = $checkbox.getAttribute('data-base-url');
            const estadoOriginal = !$checkbox.checked;
            const activando = $checkbox.checked;

            fetch(`${baseUrl}/cambiar-estado/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({})
            })
                .then(response => response.json().then(data => ({
                    ok: response.ok,
                    status: response.status,
                    data
                })))
                .then(({ ok, status, data }) => {
                    if (!ok) throw { status, data };

                    if (!data.confirmar) return;

                    mostrarModalPassword(baseUrl, id, activando, $checkbox, estadoOriginal, csrfToken);
                })
                .catch(err => {
                    const mensaje = err?.data?.error || 'No se pudo procesar la solicitud';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: mensaje,
                        confirmButtonColor: '#dc3545',
                    });
                    $checkbox.checked = estadoOriginal;
                });
        });
    });
}


function mostrarModalPassword(baseUrl, id, activando, checkbox, estadoOriginal, csrfToken) {
    const html = `
        <style>
            .sem-input {
                width: 100%;
                padding: 0.75rem 1rem;
                border: 1.5px solid #e2e8f0;
                border-radius: 12px;
                font-size: 1rem;
                transition: all 0.2s;
                background: #f8fafc;
            }
            .sem-input:focus {
                outline: none;
                border-color: #0d6efd;
                background: #fff;
                box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
            }
            .sem-label {
                display: block;
                text-align: left;
                font-weight: 600;
                font-size: 0.85rem;
                color: #475569;
                margin-bottom: 0.5rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .sem-icon-wrap {
                position: relative;
            }
            .sem-icon-wrap .sem-icon {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #94a3b8;
                pointer-events: none;
                font-size: 1rem;
            }
            .sem-icon-wrap .sem-input {
                padding-left: 2.75rem;
            }
        </style>

        <div style="text-align:left;">
            <div style="margin-bottom: 1rem;">
                <label class="sem-label">
                    <i class="bi bi-lock-fill me-1"></i> Contraseña
                </label>
                <div class="sem-icon-wrap">
                    <i class="bi bi-shield-lock sem-icon"></i>
                    <input
                        type="password"
                        id="password"
                        class="sem-input"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password">
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        title: activando
            ? '<span style="color:#0d6efd;">Activar semestre</span>'
            : '<span style="color:#dc3545;">Desactivar semestre</span>',
        icon: activando ? 'question' : 'warning',
        html: html,
        showCancelButton: true,
        confirmButtonText: activando
            ? '<i class="bi bi-check-circle me-1"></i> Activar'
            : '<i class="bi bi-x-circle me-1"></i> Desactivar',
        cancelButtonText: '<i class="bi bi-arrow-left me-1"></i> Cancelar',
        confirmButtonColor: activando ? '#0d6efd' : '#dc3545',
        cancelButtonColor: '#6c757d',
        showLoaderOnConfirm: true,
        customClass: {
            popup: 'rounded-4 shadow-lg p-4',
            title: 'fw-bold fs-4',
            confirmButton: 'btn rounded-pill px-4',
            cancelButton: 'btn rounded-pill px-4',
        },
        didOpen: () => {
            const passInput = document.getElementById('password');
            if (passInput) passInput.focus();
        },
        preConfirm: () => {
            const password = document.getElementById('password').value;

            if (!password) {
                Swal.showValidationMessage(
                    '<i class="bi bi-exclamation-circle me-1"></i> Debes ingresar tu contraseña'
                );
                return false;
            }

            return fetch(`${baseUrl}/cambiar-estado-confirmar/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ password: password })
            })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Contraseña incorrecta');
                    }
                    return data;
                }))
                .catch(error => {
                    Swal.showValidationMessage(
                        '<i class="bi bi-exclamation-circle me-1"></i> ' + error.message
                    );
                });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: '¡Listo!',
                text: result.value.message || 'Estado actualizado.',
                timer: 1500,
                showConfirmButton: false,
            }).then(() => location.reload());
        } else {
            checkbox.checked = estadoOriginal;
        }
    });
}


/* ============================================================
 *  2) ELIMINAR (show)
 * ============================================================ */
function initEliminar(csrfToken) {
    const btnEliminar = document.getElementById('btnEliminar');
    if (!btnEliminar) return;

    btnEliminar.addEventListener('click', function () {
        const url = btnEliminar.dataset.url;
        const redirectUrl = btnEliminar.dataset.redirect;

        Swal.fire({
            title: 'Confirmar eliminación',
            text: 'Ingresa tu contraseña para eliminar este semestre',
            input: 'password',
            inputAttributes: {
                placeholder: 'Contraseña',
                autocomplete: 'current-password',
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar',
            cancelButtonText: '<i class="bi bi-arrow-left me-1"></i> Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            showLoaderOnConfirm: true,

            preConfirm: (password) => {
                if (!password) {
                    Swal.showValidationMessage('Debes ingresar tu contraseña');
                    return false;
                }

                return fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ password: password })
                })
                    .then(response => response.json().then(data => {
                        if (!response.ok) {
                            throw new Error(data.error || 'Contraseña incorrecta');
                        }
                        return data;
                    }))
                    .catch(error => {
                        Swal.showValidationMessage(
                            '<i class="bi bi-exclamation-circle me-1"></i> ' + error.message
                        );
                    });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminado!',
                    text: 'El semestre ha sido eliminado correctamente.',
                    timer: 1500,
                    showConfirmButton: false,
                }).then(() => {
                    window.location.href = redirectUrl;
                });
            }
        });
    });
}


/* ============================================================
 *  3) FORMULARIO (create / edit)
 *     - Autogenera nombre según año+periodo (solo en CREATE)
 *     - Restringe fechas de inicio/fin
 *     - En EDIT respeta el año que trae del backend
 * ============================================================ */
function initFormularioSemestre() {
    const anioInput = document.getElementById('anio_manual');
    const periodoSelect = document.getElementById('periodo_select');

    if (!anioInput || !periodoSelect) return;

    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');
    const nombreInput = document.getElementById('nombre');

    const anioActual = new Date().getFullYear();

    // 🔑 CLAVE: solo forzamos el año si el input viene vacío (create).
    //    Si ya trae un valor (edit), lo respetamos.
    if (!anioInput.value) {
        anioInput.value = anioActual;
    }

    anioInput.min = anioActual;

    const esEdit = document.getElementById('formSemestreEdit') !== null;

    if (!esEdit) {
        actualizarTodo();
    } else {
        // En edit solo aplicamos restricciones a fechas, sin tocar nombre
        aplicarRestriccionesFechas(anioInput.value, periodoSelect.value);
    }

    anioInput.addEventListener('input', () => {
        if (parseInt(anioInput.value) < anioActual) {
            anioInput.value = anioActual;
        }

        if (esEdit) {
            aplicarRestriccionesFechas(anioInput.value, periodoSelect.value);
        } else {
            actualizarTodo();
        }
    });

    periodoSelect.addEventListener('change', () => {
        if (fechaInicio) fechaInicio.value = '';
        if (fechaFin) fechaFin.value = '';

        if (esEdit) {
            aplicarRestriccionesFechas(anioInput.value, periodoSelect.value);
        } else {
            actualizarTodo();
        }
    });

    function actualizarTodo() {
        const anio = anioInput.value;
        const periodo = periodoSelect.value;

        if (!anio || !periodo) return;

        let texto = '';

        if (periodo == '1') {
            texto = 'ENERO - JUNIO ' + anio;

            if (fechaInicio) {
                fechaInicio.min = `${anio}-01-01`;
                fechaInicio.max = `${anio}-01-31`;
                if (!fechaInicio.value) fechaInicio.value = `${anio}-01-01`;
            }

            if (fechaFin) {
                fechaFin.min = `${anio}-06-01`;
                fechaFin.max = `${anio}-06-30`;
                if (!fechaFin.value) fechaFin.value = `${anio}-06-30`;
            }
        }

        if (periodo == '2') {
            texto = 'JULIO - DICIEMBRE ' + anio;

            if (fechaInicio) {
                fechaInicio.min = `${anio}-07-01`;
                fechaInicio.max = `${anio}-07-31`;
                if (!fechaInicio.value) fechaInicio.value = `${anio}-07-01`;
            }

            if (fechaFin) {
                fechaFin.min = `${anio}-12-01`;
                fechaFin.max = `${anio}-12-31`;
                if (!fechaFin.value) fechaFin.value = `${anio}-12-31`;
            }
        }

        if (nombreInput) nombreInput.value = texto;
    }

    function aplicarRestriccionesFechas(anio, periodo) {
        if (!anio || !periodo) return;

        if (periodo == '1') {
            if (fechaInicio) {
                fechaInicio.min = `${anio}-01-01`;
                fechaInicio.max = `${anio}-01-31`;
            }
            if (fechaFin) {
                fechaFin.min = `${anio}-06-01`;
                fechaFin.max = `${anio}-06-30`;
            }
        }

        if (periodo == '2') {
            if (fechaInicio) {
                fechaInicio.min = `${anio}-07-01`;
                fechaInicio.max = `${anio}-07-31`;
            }
            if (fechaFin) {
                fechaFin.min = `${anio}-12-01`;
                fechaFin.max = `${anio}-12-31`;
            }
        }
    }
}


/* ============================================================
 *  4) EDITAR CON CONTRASEÑA (edit)
 * ============================================================ */
function initEditarConPassword(csrfToken) {
    const form = document.getElementById('formSemestreEdit');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const url = form.dataset.confirmUrl;
        const redirectUrl = form.dataset.redirect;

        // Recogemos los datos actuales del formulario
        const formData = new FormData(form);
        const payload = {};
        formData.forEach((value, key) => {
            payload[key] = value;
        });

        Swal.fire({
            title: 'Confirmar cambios',
            text: 'Ingresa tu contraseña para guardar los cambios del semestre',
            input: 'password',
            inputAttributes: {
                placeholder: 'Contraseña',
                autocomplete: 'current-password',
            },
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Guardar',
            cancelButtonText: '<i class="bi bi-arrow-left me-1"></i> Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            showLoaderOnConfirm: true,

            preConfirm: (password) => {
                if (!password) {
                    Swal.showValidationMessage('Debes ingresar tu contraseña');
                    return false;
                }

                payload.password = password;

                return fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload)
                })
                    .then(response => response.json().then(data => {
                        if (!response.ok) {
                            throw new Error(data.error || 'No se pudo guardar');
                        }
                        return data;
                    }))
                    .catch(error => {
                        Swal.showValidationMessage(
                            '<i class="bi bi-exclamation-circle me-1"></i> ' + error.message
                        );
                    });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: result.value.message || 'El semestre ha sido actualizado.',
                    timer: 1500,
                    showConfirmButton: false,
                }).then(() => {
                    window.location.href = redirectUrl;
                });
            }
        });
    });
}