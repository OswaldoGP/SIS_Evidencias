document.addEventListener('DOMContentLoaded', function () {
    inicializarToggleMaterias();
    inicializarControlesGrupos();
    inicializarPdfSession();
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

function inicializarToggleMaterias() {
    document.addEventListener('change', async function (e) {
        const chk = e.target;

        if (!chk.classList.contains('chkToggle')) return;

        const id = chk.dataset.id;
        const estado = chk.checked ? 1 : 0;

        if (estado === 1) {
            chk.checked = false;

            const { value: grupos } = await Swal.fire({
                title: '¿Cuántos grupos se van a aperturar?',
                text: 'Especifica cuántos grupos se abrirán para esta materia.',
                input: 'number',
                inputAttributes: {
                    min: 1,
                    max: 20,
                    step: 1,
                    placeholder: 'Ej: 3',
                    autocomplete: 'off',
                },
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Activar',
                cancelButtonText: '<i class="bi bi-arrow-left me-1"></i> Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                inputValidator: (value) => {
                    if (!value) return 'Debes ingresar un número';
                    const n = parseInt(value, 10);
                    if (isNaN(n) || n < 1) return 'El mínimo es 1 grupo';
                    if (n > 20) return 'El máximo es 20 grupos';
                },
            });

            if (!grupos) {
                chk.checked = false;
                return;
            }

            chk.checked = true;
            cambiarEstado(id, 1, chk, parseInt(grupos, 10));
            return;
        }

        const confirmacion = await Swal.fire({
            title: '¿Desactivar materia?',
            text: 'Al desactivarla, los grupos se pondrán en 0.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-x-circle me-1"></i> Desactivar',
            cancelButtonText: '<i class="bi bi-arrow-left me-1"></i> Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
        });

        if (!confirmacion.isConfirmed) {
            chk.checked = true;
            return;
        }

        cambiarEstado(id, 0, chk, 0);
    });
}

function cambiarEstado(id, estado, chk, grupos) {
    const url = document.body.dataset.materiasEstadoUrl;
    if (!url) return;

    chk.disabled = true;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ id, estado, grupos }),
    })
        .then(res => res.json())
        .then(data => {
            chk.disabled = false;

            if (data.success) {
                actualizarGrupoEnFila(id, data.grupos ?? grupos);
                Swal.fire({
                    icon: 'success',
                    title: '¡Listo!',
                    text: data.mensaje,
                    timer: 1500,
                    showConfirmButton: false,
                });
            } else {
                chk.checked = !chk.checked;
                Swal.fire({
                    icon: 'error',
                    title: 'No se puede cambiar',
                    text: data.mensaje || 'Operación no permitida',
                });
            }
        })
        .catch(() => {
            chk.disabled = false;
            chk.checked = !chk.checked;
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo contactar al servidor.',
            });
        });
}

function actualizarGrupoEnFila(id, grupos) {
    const fila = document.getElementById(`chk${id}`)?.closest('tr');
    if (!fila) return;

    const valor = fila.querySelector('.grupos-control .grupos-valor');
    if (valor) valor.textContent = grupos;

    const asignacionesActivas = parseInt(fila.dataset.asignacionesActivas || '0', 10);

    const controles = fila.querySelectorAll('.grupos-control .btn-grupo');
    if (controles.length >= 2) {
        controles[0].disabled = !grupos || grupos <= 1 || grupos <= asignacionesActivas;
        controles[1].disabled = !grupos || grupos >= 20;
    }
}

function inicializarControlesGrupos() {
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-grupo');
        if (!btn) return;

        const id = btn.dataset.id;
        const accion = btn.dataset.accion;
        const fila = btn.closest('tr');
        const valorEl = fila?.querySelector('.grupos-control .grupos-valor');
        if (!valorEl) return;

        const chkToggle = fila.querySelector('.chkToggle');
        if (!chkToggle || !chkToggle.checked) {
            Swal.fire({
                icon: 'info',
                title: 'Materia inactiva',
                text: 'Activa la materia para poder cambiar el número de grupos.',
                timer: 1800,
                showConfirmButton: false,
            });
            return;
        }

        const actual = parseInt(valorEl.textContent, 10) || 0;
        const nuevo = accion === 'up' ? actual + 1 : actual - 1;
        const asignacionesActivas = parseInt(fila.dataset.asignacionesActivas || '0', 10);

        if (nuevo < 1) {
            Swal.fire({
                icon: 'info',
                title: 'Mínimo 1 grupo',
                text: 'Una materia activa debe tener al menos 1 grupo.',
                timer: 1500,
                showConfirmButton: false,
            });
            return;
        }

        if (accion === 'down' && nuevo < asignacionesActivas) {
            Swal.fire({
                icon: 'info',
                title: 'No puedes reducir',
                text: `Ya hay ${asignacionesActivas} asignaciones activas. No puedes dejar menos grupos que asignaciones.`,
                timer: 2200,
                showConfirmButton: false,
            });
            return;
        }

        if (nuevo > 20) {
            Swal.fire({
                icon: 'info',
                title: 'Máximo 20 grupos',
                text: 'No puedes asignar más de 20 grupos.',
                timer: 1500,
                showConfirmButton: false,
            });
            return;
        }

        guardarGrupos(id, nuevo, btn, valorEl);
    });
}

function guardarGrupos(id, grupos, btn, valorEl) {
    const url = document.body.dataset.materiasGruposUrl;
    if (!url) return;

    btn.disabled = true;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ id, grupos }),
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                valorEl.textContent = data.grupos ?? grupos;

                const fila = btn.closest('tr');
                const controles = fila.querySelectorAll('.grupos-control .btn-grupo');
                const asignacionesActivas = parseInt(fila.dataset.asignacionesActivas || '0', 10);

                if (controles.length >= 2) {
                    controles[0].disabled = grupos <= 1 || grupos <= asignacionesActivas;
                    controles[1].disabled = grupos >= 20;
                }

                Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: data.mensaje || `Grupos actualizados a ${grupos}`,
                    timer: 1200,
                    showConfirmButton: false,
                });
            } else {
                btn.disabled = false;
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.mensaje || 'No se pudo actualizar el número de grupos',
                });
            }
        })
        .catch(() => {
            btn.disabled = false;
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo contactar al servidor.',
            });
        });
}

function inicializarPdfSession() {
    const pdfUrl = document.body.dataset.pdfSession;
    if (!pdfUrl) return;

    setTimeout(() => {
        window.open(pdfUrl, '_blank');
    }, 500);
}