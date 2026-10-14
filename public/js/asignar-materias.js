document.addEventListener('DOMContentLoaded', function () {
    inicializarIndex();
    inicializarCreate();
    inicializarShow();
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

function normalizarCarrera(carrera) {
    if (!carrera) return '';
    return carrera
        .toString()
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function recargarTbody() {
    const tbodyUrl = document.body.dataset.tbodyUrl;
    if (!tbodyUrl) return;

    fetch(tbodyUrl, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
    })
        .then(res => res.text())
        .then(html => {
            const tbody = document.getElementById('tbody_asignaciones');
            if (tbody) tbody.innerHTML = html;
        })
        .catch(err => console.error('Error recargando tbody:', err));
}

function inicializarIndex() {
    const tabla = document.getElementById('tablaAsignaciones');
    if (!tabla) return;

    document.addEventListener('change', async function (e) {
        if (!e.target.classList.contains('chkToggle')) return;

        const checkbox = e.target;
        const id = checkbox.dataset.id;
        const activar = checkbox.checked;

        const confirmacion = await Swal.fire({
            title: activar ? '¿Activar asignación?' : '¿Desactivar asignación?',
            text: activar
                ? 'La asignación volverá a estar activa en el sistema.'
                : 'La asignación dejará de estar activa en el sistema.',
            icon: activar ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: activar
                ? '<i class="bi bi-check-circle me-1"></i> Activar'
                : '<i class="bi bi-x-circle me-1"></i> Desactivar',
            cancelButtonText: '<i class="bi bi-arrow-left me-1"></i> Cancelar',
            confirmButtonColor: activar ? '#0d6efd' : '#dc3545',
            cancelButtonColor: '#6c757d',
        });

        if (!confirmacion.isConfirmed) {
            checkbox.checked = !activar;
            return;
        }

        cambiarEstado(id, activar ? 1 : 0, checkbox);
    });
}

function cambiarEstado(id, estado, checkbox) {
    const url = document.body.dataset.estadoUrl;
    if (!url) return;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ id: id, estado: estado }),
    })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || data.mensaje || 'Error en la petición');
            return data;
        })
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Logrado!',
                    text: data.mensaje,
                    confirmButtonText: 'Entendido',
                    timer: 1500,
                    showConfirmButton: false,
                });
                recargarTbody();
            } else {
                checkbox.checked = !checkbox.checked;
                Swal.fire({
                    icon: 'error',
                    title: 'No se puede',
                    text: data.mensaje || 'No se pudo cambiar el estado',
                });
            }
        })
        .catch(error => {
            checkbox.checked = !checkbox.checked;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message,
            });
        });
}

function inicializarCreate() {
    const selectMateria = document.getElementById('materia_select');
    if (!selectMateria) return;

    const grupoPreview = document.getElementById('grupo_preview');
    const grupoInput = document.getElementById('grupo_input');
    const letraContainer = document.getElementById('letra_container');
    const letraSelect = document.getElementById('letra_select');
    const letraInput = document.getElementById('letra_input');

    const SIGLAS = {
        'INGENIERIA EN SISTEMAS COMPUTACIONALES': 'SIS',
        'INGENIERIA INDUSTRIAL': 'IG',
        'INGENIERIA EN GESTION EMPRESARIAL': 'IGE',
        'LICENCIATURA EN TURISMO': 'LT',
    };

    const abecedario = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

    function actualizarGrupoPreview() {
        const base = grupoInput.value;
        const letra = letraSelect.value;

        if (!base) {
            grupoPreview.value = '';
            return;
        }

        if (letra) {
            grupoPreview.value = base + '-' + letra;
        } else {
            grupoPreview.value = base;
        }
    }

    function calcularGrupo() {
        const option = selectMateria.options[selectMateria.selectedIndex];
        const materiaId = selectMateria.value;
        const carreraNom = option?.dataset?.carrera;
        const semestre = option?.dataset?.semestre;
        const numGrupos = parseInt(option?.dataset?.numGrupos || '1', 10);

        letraSelect.innerHTML = '<option value="">Seleccione una letra...</option>';
        letraSelect.value = '';
        letraInput.value = '';

        if (!materiaId) {
            grupoInput.value = '';
            grupoPreview.value = '';
            letraContainer.classList.add('d-none');
            return;
        }

        const carreraNorm = normalizarCarrera(carreraNom);
        const sigla = SIGLAS[carreraNorm] || 'GEN';
        const baseGrupo = sigla + '-' + semestre;

        grupoInput.value = baseGrupo;

        if (numGrupos > 1) {
            letraContainer.classList.remove('d-none');

            for (let i = 0; i < numGrupos; i++) {
                const letra = abecedario[i];
                const opt = document.createElement('option');
                opt.value = letra;
                opt.textContent = letra;
                letraSelect.appendChild(opt);
            }
        } else {
            letraContainer.classList.add('d-none');
        }

        actualizarGrupoPreview();
    }

    selectMateria.addEventListener('change', calcularGrupo);
    letraSelect.addEventListener('change', function () {
        letraInput.value = this.value;
        actualizarGrupoPreview();
    });

    if (selectMateria.value) {
        calcularGrupo();
    }

    const sessionError = document.body.dataset.sessionError;
    if (sessionError) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: sessionError,
            confirmButtonColor: '#d33',
        });
    }
}

function inicializarShow() {
    const btnEliminar = document.getElementById('btnEliminar');
    if (!btnEliminar) return;

    const url = btnEliminar.dataset.url;
    const redirectUrl = btnEliminar.dataset.redirect;

    btnEliminar.addEventListener('click', function () {
        Swal.fire({
            title: 'Confirmar eliminación',
            text: 'Ingresa tu contraseña para eliminar esta asignación',
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
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ password: password }),
                })
                    .then(response => response.json().then(data => {
                        if (!response.ok) throw new Error(data.error || 'Contraseña incorrecta');
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
                    title: '¡Eliminado!',
                    text: 'La asignación ha sido eliminada correctamente.',
                    timer: 1500,
                    showConfirmButton: false,
                }).then(() => {
                    window.location.href = redirectUrl;
                });
            }
        });
    });
}