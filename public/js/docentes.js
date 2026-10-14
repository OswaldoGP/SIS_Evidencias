document.addEventListener('DOMContentLoaded', function () {
    inicializarTablaDocentes();
    inicializarFormularioDocente();
    inicializarPdfSession();
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

function inicializarTablaDocentes() {
    const tabla = document.getElementById('tablaDocentes');
    if (!tabla) return;

    const dataUrl = document.body.dataset.docentesDataUrl;
    const estadoUrl = document.body.dataset.docentesEstadoUrl;
    const resetUrl = document.body.dataset.docentesResetUrl;
    const rolActual = (document.body.dataset.userRol || '').toLowerCase();

    const dt = new DataTable('#tablaDocentes', {
        processing: true,
        serverSide: true,
        ajax: dataUrl,
        columns: [
            { data: 'nombre', name: 'nombre' },
            { data: 'email', name: 'email' },
            { data: 'celular', name: 'celular' },
            { data: 'departamento', name: 'departamento' },
            { data: 'cargo', name: 'cargo' },
            { data: 'rol', name: 'rol' },
            { data: 'password_btn', orderable: false, searchable: false },
            { data: 'activo_switch', orderable: false, searchable: false },
            { data: 'editar_btn', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json'
        }
    });

    document.addEventListener('change', async function (e) {
        if (!e.target.classList.contains('cambiar-estado')) return;

        const checkbox = e.target;
        const id = checkbox.dataset.id;
        const rolObjetivo = (checkbox.dataset.rol || '').toLowerCase();
        const estado = checkbox.checked ? 1 : 0;

        if (rolObjetivo === 'admin' && rolActual !== 'admin') {
            checkbox.checked = !checkbox.checked;
            Swal.fire({
                icon: 'error',
                title: 'Acción no permitida',
                text: 'Solo un administrador puede activar o desactivar a otro administrador.'
            });
            return;
        }

        if (estado === 0) {
            const confirmacion = await Swal.fire({
                title: '¿Desactivar usuario?',
                text: 'El usuario dejará de tener acceso al sistema.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            });

            if (!confirmacion.isConfirmed) {
                checkbox.checked = true;
                return;
            }
        }

        cambiarEstadoUsuario(id, estado, checkbox, estadoUrl, dt);
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.reset-btn');
        if (!btn) return;

        const userId = btn.dataset.id;
        const rolObjetivo = (btn.dataset.rol || '').toLowerCase();

        if (rolObjetivo === 'admin' && rolActual !== 'admin') {
            Swal.fire({
                icon: 'error',
                title: 'Acción no permitida',
                text: 'Solo un administrador puede resetear la contraseña de otro administrador.'
            });
            return;
        }

        solicitarResetPassword(userId, resetUrl, dt);
    });
}

function cambiarEstadoUsuario(id, estado, checkbox, estadoUrl, dt) {
    fetch(`${estadoUrl}/${id}/${estado}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                dt.ajax.reload(null, false);
                Swal.fire({
                    icon: 'success',
                    title: 'Estado actualizado',
                    text: data.message || 'Estado actualizado correctamente',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                checkbox.checked = !checkbox.checked;
                Swal.fire({
                    icon: 'error',
                    title: 'No se puede',
                    text: data.message || 'No se pudo cambiar el estado'
                });
            }
        })
        .catch(() => {
            checkbox.checked = !checkbox.checked;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo procesar la solicitud'
            });
        });
}

function solicitarResetPassword(userId, resetUrl, dt) {
    Swal.fire({
        title: 'Confirmar Reset',
        text: 'Ingresa tu contraseña de administrador',
        input: 'password',
        inputAttributes: {
            placeholder: 'Contraseña',
            autocomplete: 'current-password'
        },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        showLoaderOnConfirm: true,

        preConfirm: (password) => {
            if (!password) {
                Swal.showValidationMessage('Debes ingresar tu contraseña');
                return false;
            }

            return fetch(`${resetUrl}/${userId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ password: password })
            })
                .then(response => response.json().then(data => {
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Error al restablecer');
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
                title: '¡Éxito!',
                text: result.value.message,
                timer: 1500,
                showConfirmButton: false
            });

            dt.ajax.reload(null, false);

            if (result.value.pdf) {
                window.open(result.value.pdf, '_blank');
            }
        }
    });
}

function inicializarFormularioDocente() {
    const rolSelect = document.getElementById('rol_select');
    if (!rolSelect) return;

    const perfilDocenteContainer = document.getElementById('perfil_docente_container');
    const perfilDocente = document.getElementById('perfil_docente');
    const datosDocenteContainer = document.getElementById('datos_docente_container');
    const cargoContainer = document.getElementById('cargo_container');
    const dpto = document.getElementById('dpto');
    const celular = document.getElementById('celular');
    const cargo = document.getElementById('cargo');

    function mostrarElemento(el) {
        if (!el) return;
        el.classList.remove('d-none');
        el.style.display = '';
    }

    function ocultarElemento(el) {
        if (!el) return;
        el.classList.add('d-none');
    }

    function aplicarReglas() {
        const rol = rolSelect.value.toLowerCase();
        const esDocente = rol === 'docente';
        const esAdmin = rol === 'admin';
        const esEncargado = rol === 'encargado';
        const adminTambienDocente = perfilDocente?.checked;

        if (esDocente) {
            if (perfilDocente) perfilDocente.checked = false;
            ocultarElemento(perfilDocenteContainer);

            mostrarElemento(datosDocenteContainer);
            if (cargoContainer) cargoContainer.style.display = 'none';

            dpto?.setAttribute('required', 'required');
            celular?.setAttribute('required', 'required');

            cargo?.removeAttribute('required');
            if (cargo) cargo.value = 'DOCENTE';
            return;
        }

        if (esEncargado) {
            if (perfilDocente) perfilDocente.checked = false;
            ocultarElemento(perfilDocenteContainer);
            ocultarElemento(datosDocenteContainer);
            if (cargoContainer) cargoContainer.style.display = 'none';

            dpto?.removeAttribute('required');
            celular?.removeAttribute('required');
            cargo?.removeAttribute('required');
            return;
        }

        if (esAdmin) {
            mostrarElemento(perfilDocenteContainer);

            if (cargoContainer) cargoContainer.style.display = 'block';
            cargo?.setAttribute('required', 'required');

            if (cargo && cargo.value === 'DOCENTE') cargo.value = '';

            if (adminTambienDocente) {
                mostrarElemento(datosDocenteContainer);
                dpto?.setAttribute('required', 'required');
                celular?.setAttribute('required', 'required');
            } else {
                ocultarElemento(datosDocenteContainer);
                dpto?.removeAttribute('required');
                celular?.removeAttribute('required');
            }
        }
    }

    rolSelect.addEventListener('change', aplicarReglas);
    perfilDocente?.addEventListener('change', aplicarReglas);

    celular?.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });

    cargo?.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });

    aplicarReglas();
}

function inicializarPdfSession() {
    const pdfUrl = document.body.dataset.pdfSession;
    if (!pdfUrl) return;

    setTimeout(() => {
        window.open(pdfUrl, '_blank');
    }, 500);
}