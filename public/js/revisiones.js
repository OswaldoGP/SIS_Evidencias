document.addEventListener('DOMContentLoaded', function () {
    inicializarToggleRevisiones();
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

function inicializarToggleRevisiones() {
    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('cambiarEstado')) return;

        const checkbox = e.target;
        const id = checkbox.dataset.id;
        const estadoOriginal = !checkbox.checked;
        const activando = checkbox.checked;

        fetch(`/revisiones/cambiar-estado/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        })
            .then(res => res.json().then(data => ({ ok: res.ok, status: res.status, data })))
            .then(({ ok, status, data }) => {
                if (!ok) throw { status, data };

                if (!data.confirmar) return;

                mostrarModalRevision(id, activando, checkbox, estadoOriginal, data);
            })
            .catch(err => {
                const mensaje = err?.data?.error || 'No se pudo procesar la solicitud';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: mensaje,
                    confirmButtonColor: '#dc3545',
                });
                checkbox.checked = estadoOriginal;
            });
    });
}

function mostrarModalRevision(id, activando, checkbox, estadoOriginal, res) {
    let html = `
        <div style="text-align:left;">
            <div class="rev-field">
                <label class="rev-label">
                    <i class="bi bi-lock-fill me-1"></i> Contraseña
                </label>
                <div class="rev-icon-wrap">
                    <i class="bi bi-shield-lock rev-icon"></i>
                    <input
                        type="password"
                        id="password"
                        class="rev-input"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password">
                </div>
            </div>
    `;

    if (activando) {
        const hoy = new Date().toISOString().split('T')[0];

        html += `
            <div class="rev-field">
                <label class="rev-label">
                    <i class="bi bi-calendar-event me-1"></i> Fecha límite de entrega
                </label>
                <div class="rev-icon-wrap">
                    <i class="bi bi-calendar3 rev-icon"></i>
                    <input
                        type="date"
                        id="fecha_limite"
                        class="rev-input"
                        min="${hoy}">
                </div>
                <small style="display:block;margin-top:0.5rem;color:#94a3b8;font-size:0.8rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    La fecha límite no puede ser anterior a hoy.
                </small>
            </div>
        `;
    }

    html += `</div>`;

    Swal.fire({
        title: activando
            ? '<span style="color:#0d6efd;">Activar revisión</span>'
            : '<span style="color:#dc3545;">Desactivar revisión</span>',
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
            const fechaLimite = document.getElementById('fecha_limite')
                ? document.getElementById('fecha_limite').value
                : null;

            if (!password) {
                Swal.showValidationMessage(
                    '<i class="bi bi-exclamation-circle me-1"></i> Debes ingresar tu contraseña'
                );
                return false;
            }

            if (activando && !fechaLimite) {
                Swal.showValidationMessage(
                    '<i class="bi bi-exclamation-circle me-1"></i> Debes seleccionar una fecha límite'
                );
                return false;
            }

            return fetch(`/revisiones/cambiar-estado-confirmar/${res.revision_id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    password: password,
                    fecha_limite: fechaLimite,
                }),
            })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Error al procesar');
                    }
                    return data;
                }))
                .catch(error => {
                    Swal.showValidationMessage(
                        '<i class="bi bi-exclamation-circle me-1"></i> ' + error.message
                    );
                });
        },
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: '¡Listo!',
                text: result.value.message || 'Estado actualizado correctamente',
                timer: 1500,
                showConfirmButton: false,
            }).then(() => location.reload());
        } else {
            checkbox.checked = estadoOriginal;
        }
    });
}