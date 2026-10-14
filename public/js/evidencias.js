document.addEventListener('DOMContentLoaded', function () {
    const tieneFormularioEdit = document.querySelector('[data-evidencia-mode="edit"]');
    const tieneFormularioCreate = document.querySelector('[data-evidencia-mode="create"]');

    if (tieneFormularioEdit) {
        inicializarModoEdit();
    }

    if (tieneFormularioCreate) {
        inicializarModoCreate();
    }
});

async function hashArchivo(file) {
    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    const prime = 0x100000001b3n;
    const mask = (1n << 64n) - 1n;

    let h1 = 0xcbf29ce484222325n;
    for (let i = 0; i < bytes.length; i++) {
        h1 ^= BigInt(bytes[i]);
        h1 = (h1 * prime) & mask;
    }
    let g1 = 0x84222325cbf29ce4n;
    for (let i = 0; i < bytes.length; i++) {
        g1 ^= BigInt(bytes[bytes.length - 1 - i]);
        g1 = (g1 * prime) & mask;
    }

    return h1.toString(16) + g1.toString(16) + ':' + file.size;
}

function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function obtenerArchivo(valor) {
    if (valor && typeof valor === 'object' && !Array.isArray(valor)) return valor.archivo ?? null;
    if (typeof valor === 'string' && valor.trim() !== '') return valor;
    return null;
}

function obtenerNa(valor) {
    return !!(valor && typeof valor === 'object' && valor.na);
}

function assetStorage(path) {
    const base = document.body.dataset.storageUrl || '/storage';
    return `${base}/${path}`;
}

function limpiarNombreArchivo(path) {
    return path ? path.split('/').pop() : '';
}

window.toggleMinimizarDropzone = function (unidad, boton) {
    const cuerpo = document.getElementById(`body_dropzone_u_${unidad}`);
    if (!cuerpo) return;
    cuerpo.classList.toggle('collapsed');
    boton.classList.toggle('rotated');
};

async function validarArchivoPdf(input, maxMb) {
    const maxSize = maxMb * 1024 * 1024;
    if (!input.files || input.files.length === 0) return true;

    for (const file of input.files) {
        if (file.type !== 'application/pdf') {
            await Swal.fire({
                icon: 'warning',
                title: 'Archivo no permitido',
                text: 'Solo se permiten archivos en formato PDF.',
                allowOutsideClick: false,
                allowEscapeKey: false,
            });
            input.value = '';
            return false;
        }
        if (file.size > maxSize) {
            await Swal.fire({
                icon: 'warning',
                title: 'Archivo demasiado pesado',
                text: `El archivo "${escaparHtml(file.name)}" supera el límite permitido de ${maxMb} MB. Selecciona un archivo más ligero.`,
                allowOutsideClick: false,
                allowEscapeKey: false,
            });
            input.value = '';
            return false;
        }
    }
    return true;
}

async function validarDuplicado(input, hashesSeleccionados, hashesExistentes) {
    if (!input.files || input.files.length === 0) return true;

    for (const file of input.files) {
        const h = await hashArchivo(file);
        if (hashesSeleccionados.has(h)) {
            await Swal.fire({
                icon: 'error',
                title: 'Archivo duplicado',
                html: `El archivo <b>${escaparHtml(file.name)}</b> ya lo seleccionaste antes.`,
                allowOutsideClick: false,
                allowEscapeKey: false,
            });
            input.value = '';
            return false;
        }

        if (hashesExistentes.has(h)) {
            await Swal.fire({
                icon: 'error',
                title: 'Archivo ya subido',
                html: `El archivo <b>${escaparHtml(file.name)}</b> ya fue subido en una versión o módulo anterior.`,
                allowOutsideClick: false,
                allowEscapeKey: false,
            });
            input.value = '';
            return false;
        }
    }
    return true;
}

async function registrarHashes(input, hashesSeleccionados) {
    if (!input.files || input.files.length === 0) return;
    for (const file of input.files) {
        const h = await hashArchivo(file);
        hashesSeleccionados.add(h);
    }
}

/* ==========================================================================
   MODO EDIT
   ========================================================================== */
function inicializarModoEdit() {
    const contenedor = document.querySelector('[data-evidencia-mode="edit"]');
    const config = JSON.parse(contenedor.dataset.config || '{}');

    const evidenciaAprobada = config.evidenciaAprobada;
    const totalUnidadesMateria = config.totalUnidadesMateria;
    const calificacionesAprobadas = config.calificacionesAprobadas;
    const racAprobado = config.racAprobado;
    const rubricasAprobadas = config.rubricasAprobadas;
    const instrumentosAprobados = config.instrumentosAprobados;
    const documentosData = config.documentosData;
    const evidenciasData = config.evidenciasData;
    const instrumentosExistentesOriginales = config.instrumentosExistentes || {};
    const motivoNoEvaluoActual = config.motivoNoEvaluo;

    let unidadesSeleccionadas = (config.unidadesSeleccionadas || []).map(Number);
    let nuevosArchivosPorUnidad = {};
    let nuevasRubricasPorUnidad = {};

    const wrapperCalificaciones = document.getElementById('wrapper_calificaciones');
    const wrapperRac = document.getElementById('wrapper_rac');
    const wrapperRubricas = document.getElementById('wrapper_rubricas');
    const seccionDropzones = document.getElementById('seccion_dropzones_dinamicos');

    window.__HASHES_SELECCIONADOS__ = new Set();
    window.__HASHES_EXISTENTES__ = new Set(config.hashesExistentes || []);

    document.addEventListener('change', async function (e) {
        if (e.target.classList.contains('archivo-pdf-2mb')) {
            if (!await validarArchivoPdf(e.target, 2)) return;
            if (!await validarDuplicado(e.target, window.__HASHES_SELECCIONADOS__, window.__HASHES_EXISTENTES__)) return;
            await registrarHashes(e.target, window.__HASHES_SELECCIONADOS__);
        }
    });

    function actualizarListaUnidades() {
        unidadesSeleccionadas = [];
        const chk0 = document.getElementById('chk_unidad_0');
        if (chk0 && chk0.checked) {
            unidadesSeleccionadas = [0];
            return;
        }
        for (let i = 1; i <= totalUnidadesMateria; i++) {
            const chk = document.getElementById(`chk_unidad_${i}`);
            if (chk && chk.checked && !chk.disabled) unidadesSeleccionadas.push(i);
        }
    }

    window.toggleUnidadTarjeta = async function (num) {
        if (evidenciaAprobada) return;
        const checkbox = document.getElementById(`chk_unidad_${num}`);
        const tarjeta = document.getElementById(`card_unidad_${num}`);
        if (!checkbox || !tarjeta || checkbox.disabled) return;

        if (num === 0) {
            for (let i = 1; i <= totalUnidadesMateria; i++) {
                const chk = document.getElementById(`chk_unidad_${i}`);
                const crd = document.getElementById(`card_unidad_${i}`);
                if (chk && chk.checked) {
                    chk.checked = false;
                    crd.classList.remove('active');
                }
            }
            checkbox.checked = !checkbox.checked;
            tarjeta.classList.toggle('active', checkbox.checked);
        } else {
            const chk0 = document.getElementById('chk_unidad_0');
            const crd0 = document.getElementById('card_unidad_0');
            if (chk0 && chk0.checked) {
                chk0.checked = false;
                crd0.classList.remove('active');
            }
            checkbox.checked = !checkbox.checked;
            tarjeta.classList.toggle('active', checkbox.checked);
        }

        actualizarListaUnidades();
        await procesarCambioUnidades();
    };

    function obtenerCalificacionUnidad(u) {
        return documentosData?.calificaciones_detalladas?.[`u${u}`] ?? null;
    }

    function obtenerRacUnidad(u) {
        return documentosData?.rac_detallado?.[`u${u}`] ?? null;
    }

    function obtenerRubricaUnidad(u) {
        return evidenciasData?.rubricas_detalladas?.[`u${u}`] ?? null;
    }

    function renderMotivoNoUnidad() {
        wrapperCalificaciones.innerHTML = `
            <input type="hidden" name="unidades[]" value="0">
            <div class="motivo-no-unidad-box">
                <label for="motivo_no_evaluo" class="form-label fw-bold text-dark mb-2">
                    Motivo por el que no se evaluó ninguna unidad
                </label>
                <textarea name="motivo_no_evaluo" id="motivo_no_evaluo" class="form-control fs-6" rows="4" maxlength="1000" required
                    ${evidenciaAprobada ? 'disabled' : ''}
                    placeholder="Escribe el motivo por el que no se evaluó ninguna unidad...">${escaparHtml(motivoNoEvaluoActual ?? '')}</textarea>
            </div>`;
    }

    async function procesarCambioUnidades() {
        actualizarListaUnidades();
        const esNinguna = unidadesSeleccionadas.includes(0);

        if (esNinguna) {
            renderMotivoNoUnidad();
            wrapperRac.innerHTML = `<div class="alert alert-secondary py-2 px-3 mb-0 small"><i class="bi bi-ban me-1"></i> No aplica.</div>`;
            wrapperRubricas.innerHTML = `<div class="alert alert-secondary py-2 px-3 mb-0 small"><i class="bi bi-ban me-1"></i> No aplica.</div>`;
            seccionDropzones.innerHTML = `<div class="alert alert-secondary"><i class="bi bi-ban me-1"></i> No aplica.</div>`;
            nuevosArchivosPorUnidad = {};
            nuevasRubricasPorUnidad = {};
            window.__HASHES_SELECCIONADOS__ = new Set(window.__HASHES_EXISTENTES__);
            return;
        }

        if (unidadesSeleccionadas.length === 0) {
            wrapperCalificaciones.innerHTML = `<span class="text-muted small">Selecciona unidades primero</span>`;
            wrapperRac.innerHTML = `<span class="text-muted small">Selecciona unidades primero</span>`;
            wrapperRubricas.innerHTML = `<span class="text-muted small">Selecciona unidades primero</span>`;
            seccionDropzones.innerHTML = '';
            nuevosArchivosPorUnidad = {};
            nuevasRubricasPorUnidad = {};
            window.__HASHES_SELECCIONADOS__ = new Set(window.__HASHES_EXISTENTES__);
            return;
        }

        if (wrapperCalificaciones.querySelector('span.text-muted') || wrapperCalificaciones.querySelector('.motivo-no-unidad-box')) {
            wrapperCalificaciones.innerHTML = '';
        }
        if (wrapperRac.querySelector('span.text-muted')) wrapperRac.innerHTML = '';
        if (wrapperRubricas.querySelector('span.text-muted')) wrapperRubricas.innerHTML = '';

        // 1. Eliminación incremental de unidades desmarcadas
        for (let i = 1; i <= totalUnidadesMateria; i++) {
            if (!unidadesSeleccionadas.includes(i)) {
                document.querySelectorAll(`.bloque-u-${i}`).forEach(el => el.remove());
                document.getElementById(`dropzone_u_${i}`)?.remove();
                delete nuevosArchivosPorUnidad[i];
                delete nuevasRubricasPorUnidad[i];
            }
        }

        // 2. Inserción incremental de unidades nuevas
        unidadesSeleccionadas.forEach(u => {
            // Calificaciones
            if (!calificacionesAprobadas && !wrapperCalificaciones.querySelector(`.bloque-u-${u}`)) {
                const ruta = obtenerArchivo(obtenerCalificacionUnidad(u));
                const div = document.createElement('div');
                div.className = `mb-3 bloque-u-${u}`;
                div.innerHTML = `
                    <span class="badge bg-secondary mb-2">U${u}</span>
                    ${ruta ? `<div class="border rounded-3 p-3 bg-light mb-2"><div class="d-flex align-items-center"><i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i><div><a href="${assetStorage(ruta)}" target="_blank" class="text-decoration-none fw-semibold">Ver documento actual</a></div></div></div>` : ''}
                    <input type="file" name="calificaciones[${u}]" class="form-control form-control-sm fs-6 archivo-pdf-2mb" accept="application/pdf" ${evidenciaAprobada ? 'disabled' : ''}>
                    <small class="text-muted">${ruta ? 'Dejar vacío para mantener.' : 'Archivo requerido.'}</small>`;
                wrapperCalificaciones.appendChild(div);
            }

            // RAC
            if (!racAprobado && !wrapperRac.querySelector(`.bloque-u-${u}`)) {
                const data = obtenerRacUnidad(u);
                const na = obtenerNa(data);
                const ruta = obtenerArchivo(data);
                const div = document.createElement('div');
                div.className = `rac-edit-row mb-2 bloque-u-${u}`;
                div.innerHTML = `
                    <span class="badge bg-secondary rac-edit-badge">U${u}</span>
                    <div class="rac-edit-file-box">
                        ${na ? `<div class="alert alert-secondary py-2 px-3 mb-2 small"><i class="bi bi-ban me-1"></i> No aplica.</div>` : ''}
                        ${(!na && ruta) ? `<div class="border rounded-3 p-3 bg-light mb-2"><div class="d-flex align-items-center"><i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-3"></i><div><a href="${assetStorage(ruta)}" target="_blank" class="text-decoration-none fw-semibold">Ver documento actual</a></div></div></div>` : ''}
                        <input type="file" name="rac[${u}]" id="rac_file_${u}" class="form-control form-control-sm fs-6 rac-edit-file archivo-pdf-2mb" accept="application/pdf" ${na || evidenciaAprobada ? 'disabled' : ''}>
                    </div>
                    <div class="form-check form-switch rac-edit-na-box">
                        <input class="form-check-input rac-edit-na-toggle" type="checkbox" name="rac_na[${u}]" value="1" id="rac_na_${u}" data-unidad="${u}" ${na ? 'checked' : ''} ${evidenciaAprobada ? 'disabled' : ''}>
                        <label class="form-check-label" for="rac_na_${u}">No aplica</label>
                    </div>`;
                wrapperRac.appendChild(div);
            }

            // Rúbricas
            if (!rubricasAprobadas && !wrapperRubricas.querySelector(`.bloque-u-${u}`)) {
                nuevasRubricasPorUnidad[u] = nuevasRubricasPorUnidad[u] || [];
                const rutasActuales = obtenerRubricasExistentes(u);
                const div = document.createElement('div');
                div.className = `mb-3 bloque-u-${u}`;
                div.innerHTML = `
                    <div class="rubrica-unidad">
                    <div class="rubrica-unidad-header">
                        <span class="badge-unidad-rubrica">U${u}</span>
                        <small class="text-muted">Hasta 3 PDFs (2 MB c/u).</small>
                    </div>
                    <div class="row align-items-center g-3 rubrica-unidad-body">
                        <div class="col-md-5">
                        <input type="file" id="helper_rubrica_u_${u}" class="helper-dropzone-pdf" accept="application/pdf" multiple onchange="window.agregarRubricasEdit(this, ${u})" ${evidenciaAprobada ? 'disabled' : ''}>
                        <button type="button" class="btn btn-rubrica rounded-pill fw-semibold small px-3 py-2 w-100" onclick="document.getElementById('helper_rubrica_u_${u}').click()" ${evidenciaAprobada ? 'disabled' : ''}>
                            <i class="bi bi-folder2-open"></i> Subir rúbricas de la Unidad ${u}
                        </button>
                        </div>
                        <div class="col-md-7">
                        <div id="lista_rubricas_u_${u}" class="d-flex flex-column gap-2 text-start">
                            ${rutasActuales.map(path => `
                                <div class="archivo-cargado-item rubrica-existente d-flex align-items-center justify-content-between p-2 bg-white border border-light-subtle rounded-3 shadow-sm">
                                    <div class="d-flex align-items-center">
                                        <div class="p-1 rounded-2 bg-danger-subtle text-danger me-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                            <i class="bi bi-file-earmark-pdf-fill fs-6"></i>
                                        </div>
                                        <a href="${assetStorage(path)}" target="_blank" class="text-secondary fw-medium small text-decoration-none">${escaparHtml(limpiarNombreArchivo(path))}</a>
                                    </div>
                                    ${!evidenciaAprobada ? `<button type="button" class="btn-eliminar-archivo btn border-0 p-1 text-muted rounded-2" data-ruta="${escaparHtml(path)}" onclick="window.eliminarRubricaExistente(${u}, this)"><i class="bi bi-trash3 fs-6"></i></button>` : ''}
                                </div>`).join('')}
                        </div>
                        </div>
                    </div>
                    </div>
                    <div id="hidden_rubricas_del_u_${u}"></div>
                    <div id="hidden_rubricas_u_${u}"></div>`;
                wrapperRubricas.appendChild(div);
                renderizarRubricasEdit(u);
            }

            // Dropzones (Extracción robusta de instrumentos existentes por unidad)
            if (!instrumentosAprobados && !seccionDropzones.querySelector(`#dropzone_u_${u}`)) {
                nuevosArchivosPorUnidad[u] = nuevosArchivosPorUnidad[u] || [];
                
                let instrumentosUnidad = [];
                if (Array.isArray(instrumentosExistentesOriginales)) {
                    instrumentosUnidad = instrumentosExistentesOriginales.filter(p => typeof p === 'string' && p.includes(`u${u}_`));
                } else if (typeof instrumentosExistentesOriginales === 'object' && instrumentosExistentesOriginales !== null) {
                    instrumentosUnidad = instrumentosExistentesOriginales[u] || instrumentosExistentesOriginales[`u${u}`] || [];
                }

                const card = document.createElement('div');
                card.className = 'card border border-light-subtle rounded-3 shadow-sm p-3 bg-white style-dropzone mb-3';
                card.id = `dropzone_u_${u}`;
                card.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <div class="p-2 rounded-3 bg-success-subtle text-success me-2 fs-5 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-file-earmark-arrow-up"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Instrumentos - Unidad ${u}</h6>
                                <p class="text-muted small mb-0">Máximo 3 PDFs (2 MB c/u)</p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-light border-0 p-2 rounded-circle btn-minimizar" onclick="toggleMinimizarDropzone(${u}, this)">
                            <i class="bi bi-chevron-down fs-5 text-secondary"></i>
                        </button>
                    </div>
                    <div class="dropzone-body-collapse mt-2" id="body_dropzone_u_${u}">
                        <div class="row align-items-center bg-light p-3 rounded-3 g-3">
                            <div class="col-md-4">
                                <input type="file" id="helper_file_u_${u}" class="helper-dropzone-pdf" accept="application/pdf" multiple onchange="window.agregarArchivosDropzoneEdit(this, ${u})" ${evidenciaAprobada ? 'disabled' : ''}>
                                <button type="button" class="btn btn-outline-success rounded-pill fw-semibold small px-3 py-2 w-100" onclick="document.getElementById('helper_file_u_${u}').click()" ${evidenciaAprobada ? 'disabled' : ''}>
                                    <i class="bi bi-folder2-open"></i> Seleccionar archivos
                                </button>
                            </div>
                            <div class="col-md-8">
                                <div id="lista_archivos_u_${u}" class="d-flex flex-column gap-2 text-start">
                                    ${instrumentosUnidad.map(path => `
                                        <div class="archivo-cargado-item d-flex align-items-center justify-content-between p-2 bg-white border border-light-subtle rounded-3 shadow-sm">
                                            <div class="d-flex align-items-center">
                                                <div class="p-1 rounded-2 bg-danger-subtle text-danger me-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                    <i class="bi bi-file-earmark-pdf-fill fs-6"></i>
                                                </div>
                                                <a href="${assetStorage(path)}" target="_blank" class="text-secondary fw-medium small text-decoration-none">${limpiarNombreArchivo(path)}</a>
                                            </div>
                                            ${!evidenciaAprobada ? `<button type="button" class="btn-eliminar-archivo btn border-0 p-1 text-muted rounded-2" onclick="window.eliminarArchivoExistente(${u}, '${path}', this)"><i class="bi bi-trash3 fs-6"></i></button>` : ''}
                                        </div>`).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="hidden_inputs_u_${u}"></div>`;
                seccionDropzones.appendChild(card);
                renderizarDropzoneUnidad(u);
            }
        });

        // Casos globales de aprobación
        if (calificacionesAprobadas) wrapperCalificaciones.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i> Ya aprobado.</div>`;
        if (racAprobado) wrapperRac.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i> Ya aprobado.</div>`;
        if (rubricasAprobadas) wrapperRubricas.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i> Ya aprobado.</div>`;
        if (instrumentosAprobados) seccionDropzones.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i> Ya aprobado.</div>`;

        // 3. Recálculo dinámico de Hashes activos
        await recalcularHashesEdit();

        aplicarListenersRac();
    }

    async function recalcularHashesEdit() {
        window.__HASHES_SELECCIONADOS__ = new Set(window.__HASHES_EXISTENTES__);
        for (const grupo of [nuevosArchivosPorUnidad, nuevasRubricasPorUnidad]) {
            for (const u in grupo) {
                if (!Array.isArray(grupo[u])) continue;
                for (const f of grupo[u]) {
                    window.__HASHES_SELECCIONADOS__.add(await hashArchivo(f));
                }
            }
        }
    }

    function obtenerRubricasExistentes(u) {
        const d = obtenerRubricaUnidad(u);
        if (!d) return [];
        if (typeof d === 'string') return d.trim() !== '' ? [d] : [];
        if (typeof d !== 'object' || d.na) return [];
        if (Array.isArray(d.archivos)) {
            return d.archivos.filter(p => typeof p === 'string' && p.trim() !== '');
        }
        return d.archivo ? [d.archivo] : [];
    }

    function totalRubricasUnidadEdit(u) {
        const existentes = document.querySelectorAll(`#lista_rubricas_u_${u} .rubrica-existente`).length;
        return existentes + (nuevasRubricasPorUnidad[u] || []).length;
    }

    window.agregarRubricasEdit = async function (input, u) {
        if (!await validarArchivoPdf(input, 2)) return;
        if (!nuevasRubricasPorUnidad[u]) nuevasRubricasPorUnidad[u] = [];

        for (const file of input.files) {
            if (totalRubricasUnidadEdit(u) >= 3) {
                await Swal.fire({ icon: 'info', title: 'Límite', text: 'Máx 3 rúbricas por unidad.' });
                break;
            }
            const h = await hashArchivo(file);
            if (window.__HASHES_SELECCIONADOS__.has(h) || window.__HASHES_EXISTENTES__.has(h)) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Archivo duplicado',
                    html: `El archivo <b>${escaparHtml(file.name)}</b> ya fue subido o seleccionado.`,
                });
                break;
            }
            nuevasRubricasPorUnidad[u].push(file);
            window.__HASHES_SELECCIONADOS__.add(h);
        }

        renderizarRubricasEdit(u);
        input.value = '';
    };

    window.eliminarRubricaExistente = function (u, boton) {
        if (evidenciaAprobada) return;
        const ruta = boton.dataset.ruta;
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar archivo?',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        }).then((r) => {
            if (!r.isConfirmed) return;
            boton.closest('.archivo-cargado-item')?.remove();
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `eliminar_rubricas[${u}][]`;
            inp.value = ruta;
            document.getElementById(`hidden_rubricas_del_u_${u}`)?.appendChild(inp);
        });
    };

    function renderizarRubricasEdit(u) {
        const lista = document.getElementById(`lista_rubricas_u_${u}`);
        const container = document.getElementById(`hidden_rubricas_u_${u}`);
        const files = nuevasRubricasPorUnidad[u] || [];
        if (!lista || !container) return;

        lista.querySelectorAll('.archivo-nuevo').forEach(el => el.remove());
        container.innerHTML = '';

        files.forEach((file, index) => {
            const div = document.createElement('div');
            div.className = 'archivo-cargado-item archivo-nuevo d-flex align-items-center justify-content-between p-2 bg-white border border-light-subtle rounded-3 shadow-sm';
            div.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="p-1 rounded-2 bg-danger-subtle text-danger me-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                        <i class="bi bi-file-earmark-pdf-fill fs-6"></i>
                    </div>
                    <span class="text-secondary fw-medium small">${escaparHtml(file.name)}</span>
                </div>
                <button type="button" class="btn-eliminar-archivo btn border-0 p-1 text-muted rounded-2" onclick="window.eliminarRubricaNuevaEdit(${u}, ${index})">
                    <i class="bi bi-trash3 fs-6"></i>
                </button>`;
            lista.appendChild(div);
        });

        if (files.length > 0) {
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            const realInput = document.createElement('input');
            realInput.type = 'file';
            realInput.name = `rubricas[${u}][]`;
            realInput.multiple = true;
            realInput.className = 'd-none';
            realInput.files = dt.files;
            container.appendChild(realInput);
        }
    }

    window.eliminarRubricaNuevaEdit = async function (u, index) {
        nuevasRubricasPorUnidad[u].splice(index, 1);
        await recalcularHashesEdit();
        renderizarRubricasEdit(u);
    };

    // Debe quedar al menos 1 PDF de rúbrica por unidad (existente o nuevo).
    const formEdit = wrapperRubricas.closest('form');
    if (formEdit) {
        formEdit.addEventListener('submit', async function (e) {
            if (evidenciaAprobada || rubricasAprobadas) return;
            if (unidadesSeleccionadas.includes(0)) return;

            const faltantes = unidadesSeleccionadas.filter(u => totalRubricasUnidadEdit(u) === 0);
            if (faltantes.length) {
                e.preventDefault();
                await Swal.fire({
                    icon: 'warning',
                    title: 'Faltan rúbricas',
                    text: `Debe quedar al menos un PDF de rúbrica en: ${faltantes.map(u => 'Unidad ' + u).join(', ')}.`
                });
            }
        });
    }

    window.agregarArchivosDropzoneEdit = async function (input, u) {
        if (!await validarArchivoPdf(input, 2)) return;
        if (!nuevosArchivosPorUnidad[u]) nuevosArchivosPorUnidad[u] = [];

        for (const file of input.files) {
            const h = await hashArchivo(file);
            if (window.__HASHES_SELECCIONADOS__.has(h) || window.__HASHES_EXISTENTES__.has(h)) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Archivo duplicado',
                    html: `El archivo <b>${escaparHtml(file.name)}</b> ya fue subido o seleccionado.`,
                });
                input.value = '';
                return;
            }
            if (nuevosArchivosPorUnidad[u].length >= 3) {
                await Swal.fire({ icon: 'info', title: 'Límite', text: 'Máx 3 archivos por unidad.' });
                break;
            }
            nuevosArchivosPorUnidad[u].push(file);
            window.__HASHES_SELECCIONADOS__.add(h);
        }

        renderizarDropzoneUnidad(u);
        input.value = '';
    };

    window.eliminarArchivoExistente = function (u, ruta, boton) {
        if (evidenciaAprobada) return;
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar archivo?',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        }).then((r) => {
            if (!r.isConfirmed) return;
            boton.closest('.archivo-cargado-item')?.remove();
            const container = document.getElementById(`hidden_inputs_u_${u}`);
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `eliminar_instrumentos[${u}][]`;
            inp.value = ruta;
            container.appendChild(inp);
        });
    };

    function renderizarDropzoneUnidad(u) {
        const lista = document.getElementById(`lista_archivos_u_${u}`);
        const container = document.getElementById(`hidden_inputs_u_${u}`);
        const files = nuevosArchivosPorUnidad[u] || [];
        if (!lista || !container) return;

        lista.querySelectorAll('.archivo-nuevo').forEach(el => el.remove());
        container.querySelectorAll('input[type="file"]').forEach(el => el.remove());

        files.forEach((file, index) => {
            const div = document.createElement('div');
            div.className = 'archivo-cargado-item archivo-nuevo d-flex align-items-center justify-content-between p-2 bg-white border border-light-subtle rounded-3 shadow-sm mb-1';
            div.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="p-1 rounded-2 bg-danger-subtle text-danger me-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                        <i class="bi bi-file-earmark-pdf-fill fs-6"></i>
                    </div>
                    <span class="text-secondary fw-medium small">${escaparHtml(file.name)}</span>
                </div>
                <button type="button" class="btn-eliminar-archivo btn border-0 p-1 text-muted rounded-2" onclick="window.eliminarArchivoNuevoEdit(${u}, ${index})">
                    <i class="bi bi-trash3 fs-6"></i>
                </button>`;
            lista.appendChild(div);
        });

        if (files.length > 0) {
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            const realInput = document.createElement('input');
            realInput.type = 'file';
            realInput.name = `instrumentos[${u}][]`;
            realInput.multiple = true;
            realInput.className = 'd-none';
            realInput.files = dt.files;
            container.appendChild(realInput);
        }
    }

    window.eliminarArchivoNuevoEdit = async function (u, index) {
        nuevosArchivosPorUnidad[u].splice(index, 1);
        await recalcularHashesEdit();
        renderizarDropzoneUnidad(u);
    };

    function aplicarListenersRac() {
        document.querySelectorAll('.rac-edit-na-toggle').forEach(chk => {
            aplicarRacNa(chk);
            chk.addEventListener('change', function () {
                aplicarRacNa(this);
            });
        });
    }

    function aplicarRacNa(chk) {
        const u = chk.dataset.unidad;
        const input = document.getElementById(`rac_file_${u}`);
        if (!input) return;
        input.disabled = chk.checked || evidenciaAprobada;
        if (chk.checked) input.value = '';
    }

    function init() {
        procesarCambioUnidades();
    }

    init();
}

/* ==========================================================================
   MODO CREATE
   ========================================================================== */
function inicializarModoCreate() {
    const contenedor = document.querySelector('[data-evidencia-mode="create"]');
    const config = JSON.parse(contenedor.dataset.config || '{}');
    const combinacionesSubidas = config.combinacionesSubidas || [];
    const revisionesOriginales = config.revisionesOriginales || [];
    const unidadesUtilizadas = config.unidadesUtilizadas || {};
    const hashesExistentesBackend = config.hashesExistentesBackend || [];

    const selectMateria = document.getElementById('asignacion_materia_id') || document.getElementById('materia_id');
    const selectRevision = document.getElementById('revision_id');
    const contenedorUnidades = document.getElementById('contenedor_tarjetas_unidades');
    const seccionDropzones = document.getElementById('seccion_dropzones_dinamicos');
    const wrapperCalificaciones = document.querySelector('#contenedor_calificaciones .wrapper-inputs');
    const wrapperRac = document.querySelector('#contenedor_rac .wrapper-inputs');
    const wrapperRubricas = document.querySelector('#contenedor_rubricas .wrapper-inputs');

    let archivosPorUnidad = {};
    let rubricasPorUnidad = {};
    let totalUnidadesMateria = 0;
    let unidadesEvaluadasActuales = [];

    window.__HASHES_SELECCIONADOS__ = new Set();
    window.__HASHES_EXISTENTES__ = new Set(hashesExistentesBackend || []);

    document.addEventListener('change', async function (e) {
        if (e.target.classList.contains('rac-na-toggle')) {
            aplicarEstadoRac(e.target);
        }
        if (e.target.classList.contains('archivo-pdf-2mb')) {
            if (!await validarArchivoPdf(e.target, 2)) return;
            if (!await validarDuplicado(e.target, window.__HASHES_SELECCIONADOS__, window.__HASHES_EXISTENTES__)) return;
            await registrarHashes(e.target, window.__HASHES_SELECCIONADOS__);
        }
    });

    function aplicarEstadoRac(chk) {
        const u = chk.dataset.unidad;
        const input = document.getElementById(`rac_file_${u}`);
        if (!input) return;
        if (chk.checked) {
            input.value = '';
            input.disabled = true;
            input.required = false;
        } else {
            input.disabled = false;
            input.required = true;
        }
    }

    function numeroRevision() {
        if (!selectRevision.value) return null;
        const opt = selectRevision.options[selectRevision.selectedIndex];
        return opt ? parseInt(opt.dataset.numero) : null;
    }

    function esRevision1() { return numeroRevision() === 1; }
    function esRevision4() { return numeroRevision() === 4; }
    function esRevision5() { return numeroRevision() === 5; }

    function actualizarCamposRevision1() {
        const r1 = esRevision1();
        const r4 = esRevision4();
        const r5 = esRevision5();
        const mostrar = r1 && !r4 && !r5;

        document.querySelectorAll('.campo-solo-revision-1').forEach(el => {
            if (mostrar) el.classList.remove('d-none');
            else el.classList.add('d-none');
        });
        document.querySelectorAll('.input-solo-revision-1').forEach(el => {
            if (mostrar) { el.disabled = false; el.required = true; }
            else { el.value = ''; el.disabled = true; el.required = false; }
        });
    }

    function actualizarCamposRevision4() {
        const campo = document.getElementById('campos_revision_4');
        if (!campo) return;
        const inputs = document.querySelectorAll('.input-revision-4');
        if (esRevision4()) {
            campo.classList.remove('d-none');
            inputs.forEach(i => { if (i.type === 'file') i.required = true; });
        } else {
            campo.classList.add('d-none');
            inputs.forEach(i => { i.required = false; i.value = ''; });
        }
    }

    selectMateria.addEventListener('change', function () {
        const asignacionId = this.value;
        const opt = this.options[this.selectedIndex];

        const inputOculto = document.getElementById('materia_id');
        if (inputOculto && opt) {
            inputOculto.value = opt.dataset.materiaId || opt.value;
        }

        totalUnidadesMateria = opt ? parseInt(opt.dataset.unidades) : 0;

        try {
            unidadesEvaluadasActuales = JSON.parse(opt?.dataset?.unidadesEvaluadas || '[]').map(Number);
        } catch (e) {
            unidadesEvaluadasActuales = [];
        }

        selectRevision.innerHTML = `<option value="">Seleccione</option>`;
        limpiarBase();

        if (!asignacionId) {
            selectRevision.disabled = true;
            return;
        }

        let disponibles = 0;
        revisionesOriginales.forEach(r => {
            const key = `${asignacionId}-${r.id}`;
            const keyMateriaLegada = `${opt?.dataset?.materiaId}-${r.id}`;

            if (!combinacionesSubidas.includes(key) && !combinacionesSubidas.includes(keyMateriaLegada)) {
                const o = document.createElement('option');
                o.value = r.id;
                o.text = r.nombre;
                o.dataset.numero = r.numero;
                selectRevision.appendChild(o);
                disponibles++;
            }
        });

        selectRevision.disabled = disponibles === 0;
        actualizarCamposRevision1();
        actualizarCamposRevision4();
    });

    selectRevision.addEventListener('change', function () {
        limpiarBase();
        actualizarCamposRevision1();
        actualizarCamposRevision4();
        if (!this.value) return;

        if (esRevision5()) {
            document.querySelectorAll('.campo-solo-revision-1').forEach(el => el.classList.add('d-none'));
            document.getElementById('campos_revision_4')?.classList.add('d-none');
            document.getElementById('contenedor_calificaciones')?.classList.remove('d-none');
            document.getElementById('contenedor_rac')?.classList.remove('d-none');
            document.getElementById('contenedor_rubricas')?.classList.remove('d-none');

            seccionDropzones.innerHTML = '';
            seccionDropzones.style.display = 'none';

            if (!unidadesEvaluadasActuales.length) {
                contenedorUnidades.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>No hay unidades evaluadas aún.</strong>
                        </div>
                    </div>`;
                return;
            }

            contenedorUnidades.innerHTML = '';
            unidadesEvaluadasActuales.forEach(u => {
                contenedorUnidades.innerHTML += `
                    <div class="col-md-3">
                        <div class="card card-unidad-check p-3 text-center shadow-sm h-100 d-flex flex-column align-items-center justify-content-center" id="card_unidad_${u}" onclick="window.toggleUnidadTarjetaCreate(${u})">
                            <input type="checkbox" id="chk_unidad_${u}" name="unidades[]" value="${u}" class="d-none">
                            <i class="bi bi-bookmark-check fs-3 text-primary mb-2"></i>
                            <span class="fw-bold text-dark">Unidad ${u}</span>
                        </div>
                    </div>`;
            });

            wrapperCalificaciones.innerHTML = `<div class="mb-2"><input type="file" name="calificaciones_segundas[]" class="form-control form-control-sm fs-6 archivo-pdf-2mb" accept="application/pdf" multiple></div>`;
            wrapperRac.innerHTML = `<div class="mb-2"><input type="file" name="rac_segundas[]" class="form-control form-control-sm fs-6 archivo-pdf-2mb" accept="application/pdf" multiple></div>`;
            wrapperRubricas.innerHTML = `<div class="mb-2"><input type="file" name="rubricas_segundas[]" class="form-control form-control-sm fs-6 archivo-pdf-2mb" accept="application/pdf" multiple></div>`;
            return;
        }

        seccionDropzones.style.display = '';

        const materiaId = String(selectMateria.value);
        const quemadas = (unidadesUtilizadas[materiaId] || []).map(Number);

        contenedorUnidades.innerHTML = `
            <div class="col-md-3">
                <div class="card card-unidad-check p-3 text-center shadow-sm h-100 d-flex flex-column align-items-center justify-content-center" id="card_unidad_0" onclick="window.toggleUnidadTarjetaCreate(0)">
                    <input type="checkbox" id="chk_unidad_0" name="unidades[]" value="0" class="d-none">
                    <i class="bi bi-dash-circle fs-3 text-secondary mb-2"></i>
                    <span class="fw-bold text-dark">Ninguna Unidad</span>
                </div>
            </div>`;

        let disponibles = 0;
        for (let i = 1; i <= totalUnidadesMateria; i++) {
            if (quemadas.includes(i)) continue;
            disponibles++;
            contenedorUnidades.innerHTML += `
                <div class="col-md-3">
                    <div class="card card-unidad-check p-3 text-center shadow-sm h-100 d-flex flex-column align-items-center justify-content-center" id="card_unidad_${i}" onclick="window.toggleUnidadTarjetaCreate(${i})">
                        <input type="checkbox" id="chk_unidad_${i}" name="unidades[]" value="${i}" class="d-none">
                        <i class="bi bi-bookmark-check fs-3 text-primary mb-2"></i>
                        <span class="fw-bold text-dark">Unidad ${i}</span>
                    </div>
                </div>`;
        }

        if (disponibles === 0) {
            contenedorUnidades.innerHTML += `<div class="col-12"><div class="alert alert-warning">Todas las unidades ya fueron evaluadas.</div></div>`;
        }
    });

    window.toggleUnidadTarjetaCreate = async function (num) {
        const chk = document.getElementById(`chk_unidad_${num}`);
        const card = document.getElementById(`card_unidad_${num}`);
        if (!chk || !card) return;

        if (num === 0) {
            for (let i = 1; i <= totalUnidadesMateria; i++) {
                const c = document.getElementById(`chk_unidad_${i}`);
                const t = document.getElementById(`card_unidad_${i}`);
                if (c && c.checked) {
                    c.checked = false;
                    t.classList.remove('active');
                }
            }
            chk.checked = !chk.checked;
        } else {
            const c0 = document.getElementById('chk_unidad_0');
            const t0 = document.getElementById('card_unidad_0');
            if (c0 && c0.checked) {
                c0.checked = false;
                t0.classList.remove('active');
            }
            chk.checked = !chk.checked;
        }

        card.classList.toggle('active', chk.checked);
        await procesarCreate();
    };

    async function procesarCreate() {
        if (esRevision5()) return;

        const seleccionadas = [];
        for (let i = 1; i <= totalUnidadesMateria; i++) {
            const chk = document.getElementById(`chk_unidad_${i}`);
            if (chk && chk.checked) seleccionadas.push(i);
        }

        const chk0 = document.getElementById('chk_unidad_0');

        if (chk0 && chk0.checked) {
            wrapperCalificaciones.innerHTML = `
            <input type="hidden" name="unidades[]" value="0">
            <div class="motivo-no-unidad-box">
                <textarea name="motivo_no_evaluo" class="form-control fs-6" rows="4" maxlength="1000" required placeholder="Motivo..."></textarea>
            </div>`;
            wrapperRac.innerHTML = `<span class="text-muted small">No aplica</span>`;
            wrapperRubricas.innerHTML = `<span class="text-muted small">No aplica</span>`;
            seccionDropzones.innerHTML = '';
            archivosPorUnidad = {};
            rubricasPorUnidad = {};
            window.__HASHES_SELECCIONADOS__ = new Set(window.__HASHES_EXISTENTES__);
            return;
        }

        if (seleccionadas.length === 0) {
            wrapperCalificaciones.innerHTML = `<span class="text-muted small">Selecciona unidades primero</span>`;
            wrapperRac.innerHTML = `<span class="text-muted small">Selecciona unidades primero</span>`;
            wrapperRubricas.innerHTML = `<span class="text-muted small">Selecciona unidades primero</span>`;
            seccionDropzones.innerHTML = '';
            archivosPorUnidad = {};
            rubricasPorUnidad = {};
            window.__HASHES_SELECCIONADOS__ = new Set(window.__HASHES_EXISTENTES__);
            return;
        }

        if (wrapperCalificaciones.querySelector('span.text-muted') || wrapperCalificaciones.querySelector('.motivo-no-unidad-box')) {
            wrapperCalificaciones.innerHTML = '';
        }
        if (wrapperRac.querySelector('span.text-muted')) wrapperRac.innerHTML = '';
        if (wrapperRubricas.querySelector('span.text-muted')) wrapperRubricas.innerHTML = '';

        // 1. Eliminación incremental de unidades desmarcadas
        for (let i = 1; i <= totalUnidadesMateria; i++) {
            if (!seleccionadas.includes(i)) {
                document.querySelectorAll(`.bloque-u-${i}`).forEach(el => el.remove());
                document.getElementById(`dropzone_u_${i}`)?.remove();
                delete archivosPorUnidad[i];
                delete rubricasPorUnidad[i];
            }
        }

        // 2. Inserción incremental de unidades nuevas
        seleccionadas.forEach(u => {
            if (!wrapperCalificaciones.querySelector(`.bloque-u-${u}`)) {
                const div = document.createElement('div');
                div.className = `mb-2 bloque-u-${u}`;
                div.innerHTML = `
                <span class="badge bg-secondary mb-1">U${u}</span>
                <input type="file" name="calificaciones[${u}]" class="form-control form-control-sm fs-6 archivo-pdf-2mb" accept="application/pdf" required>`;
                wrapperCalificaciones.appendChild(div);
            }

            if (!wrapperRac.querySelector(`.bloque-u-${u}`)) {
                const div = document.createElement('div');
                div.className = `rac-row mb-2 bloque-u-${u}`;
                div.innerHTML = `
                <span class="badge bg-secondary unidad-badge">U${u}</span>
                <div class="rac-file-wrapper">
                    <input type="file" name="rac[${u}]" id="rac_file_${u}" class="form-control form-control-sm fs-6 rac-file archivo-pdf-2mb" accept="application/pdf" required>
                </div>
                <div class="form-check form-switch rac-na-box">
                    <input class="form-check-input rac-na-toggle" type="checkbox" name="rac_na[${u}]" value="1" id="rac_na_${u}" data-unidad="${u}">
                    <label class="form-check-label" for="rac_na_${u}">No aplica</label>
                </div>`;
                wrapperRac.appendChild(div);
            }

            if (!wrapperRubricas.querySelector(`.bloque-u-${u}`)) {
                const div = document.createElement('div');
                div.className = `mb-3 bloque-u-${u}`;
                div.innerHTML = `
                <div class="rubrica-unidad">
                    <div class="rubrica-unidad-header">
                        <span class="badge-unidad-rubrica">U${u}</span>
                        <small class="text-muted">Hasta 3 PDFs. Máx 2 MB c/u.</small>
                    </div>
                    <div class="row align-items-center g-3 rubrica-unidad-body">
                        <div class="col-md-5">
                            <input type="file" id="helper_rubrica_u_${u}" class="helper-dropzone-pdf" accept="application/pdf" multiple onchange="window.agregarRubricasCreate(this, ${u})">
                            <button type="button" class="btn btn-rubrica rounded-pill fw-semibold small px-3 py-2 w-100" onclick="document.getElementById('helper_rubrica_u_${u}').click()">
                                <i class="bi bi-folder2-open"></i> Subir rúbricas de la Unidad ${u}
                            </button>
                        </div>
                        <div class="col-md-7">
                            <div id="lista_rubricas_u_${u}" class="d-flex flex-column gap-2 text-start"></div>
                        </div>
                    </div>
                </div>
                <div id="hidden_rubricas_u_${u}"></div>`;
                wrapperRubricas.appendChild(div);

                rubricasPorUnidad[u] = rubricasPorUnidad[u] || [];
                renderizarRubricasCreate(u);
            }

            if (!seccionDropzones.querySelector(`#dropzone_u_${u}`)) {
                if (!seccionDropzones.querySelector('h5')) {
                    seccionDropzones.innerHTML = '<h5 class="fw-bold text-success mb-3">INSTRUMENTOS DE EVALUACIÓN</h5>';
                }

                const card = document.createElement('div');
                card.className = 'card border border-light-subtle rounded-3 shadow-sm p-4 bg-white style-dropzone mb-3';
                card.id = `dropzone_u_${u}`;
                card.innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center">
                        <div class="p-2.5 rounded-3 bg-success-subtle text-success me-3 fs-4 d-inline-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                            <i class="bi bi-file-earmark-arrow-up"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark fs-5 mb-0">Instrumentos - Unidad ${u}</h5>
                            <p class="text-muted small mb-0">Hasta 3 PDFs. Máx 2 MB c/u.</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-light border-0 p-2 rounded-circle btn-minimizar" onclick="toggleMinimizarDropzone(${u}, this)">
                        <i class="bi bi-chevron-down fs-5 text-secondary"></i>
                    </button>
                </div>
                <div class="dropzone-body-collapse mt-3" id="body_dropzone_u_${u}">
                    <div class="row align-items-center bg-light p-3 rounded-3 g-3">
                        <div class="col-md-4">
                            <input type="file" id="helper_file_u_${u}" class="helper-dropzone-pdf" accept="application/pdf" multiple onchange="window.agregarArchivosDropzoneCreate(this, ${u})">
                            <button type="button" class="btn btn-outline-success rounded-pill fw-semibold small px-4 py-2.5 w-100" onclick="document.getElementById('helper_file_u_${u}').click()">
                                <i class="bi bi-folder2-open"></i> Archivos Unidad ${u}
                            </button>
                        </div>
                        <div class="col-md-8">
                            <div id="lista_archivos_u_${u}" class="d-flex flex-column gap-2 text-start"></div>
                        </div>
                    </div>
                </div>
                <div id="hidden_inputs_u_${u}"></div>`;
                seccionDropzones.appendChild(card);

                archivosPorUnidad[u] = archivosPorUnidad[u] || [];
                renderizarDropzoneCreate(u);
            }
        });

        await recalcularHashesCreate();
    }

    async function recalcularHashesCreate() {
        window.__HASHES_SELECCIONADOS__ = new Set(window.__HASHES_EXISTENTES__);
        for (const grupo of [archivosPorUnidad, rubricasPorUnidad]) {
            for (const u in grupo) {
                if (!Array.isArray(grupo[u])) continue;
                for (const f of grupo[u]) {
                    window.__HASHES_SELECCIONADOS__.add(await hashArchivo(f));
                }
            }
        }
    }

    window.agregarArchivosDropzoneCreate = async function (input, u) {
        if (!archivosPorUnidad[u]) archivosPorUnidad[u] = [];

        if (!await validarArchivoPdf(input, 2)) return;

        for (const file of input.files) {
            const h = await hashArchivo(file);
            if (window.__HASHES_SELECCIONADOS__.has(h) || window.__HASHES_EXISTENTES__.has(h)) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Archivo duplicado',
                    html: `El archivo <b>${escaparHtml(file.name)}</b> ya fue subido o seleccionado.`
                });
                input.value = '';
                return;
            }
            if (archivosPorUnidad[u].length >= 3) {
                await Swal.fire({ icon: 'info', title: 'Límite', text: 'Máx 3 por unidad.' });
                break;
            }
            archivosPorUnidad[u].push(file);
            window.__HASHES_SELECCIONADOS__.add(h);
        }

        renderizarDropzoneCreate(u);
        input.value = '';
    };

    function renderizarDropzoneCreate(u) {
        const lista = document.getElementById(`lista_archivos_u_${u}`);
        const container = document.getElementById(`hidden_inputs_u_${u}`);
        const files = archivosPorUnidad[u] || [];
        if (!lista || !container) return;

        lista.innerHTML = '';
        container.innerHTML = '';

        if (files.length === 0) {
            lista.innerHTML = `<span class="text-muted small">Ningún archivo seleccionado.</span>`;
            return;
        }

        files.forEach((file, index) => {
            lista.innerHTML += `
                <div class="archivo-cargado-item d-flex align-items-center justify-content-between p-2.5 bg-white border rounded-3 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="p-2 rounded-2 bg-danger-subtle text-danger me-2.5 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-file-earmark-pdf-fill fs-5"></i>
                        </div>
                        <span class="text-secondary fw-medium small">${escaparHtml(file.name)}</span>
                    </div>
                    <button type="button" class="btn-eliminar-archivo btn border-0 p-2 text-muted rounded-2" onclick="window.eliminarArchivoDropzoneCreate(${u}, ${index})">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </div>`;
        });

        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        const realInput = document.createElement('input');
        realInput.type = 'file';
        realInput.name = `instrumentos[${u}][]`;
        realInput.multiple = true;
        realInput.className = 'd-none';
        realInput.files = dt.files;
        container.appendChild(realInput);
    }

    window.eliminarArchivoDropzoneCreate = async function (u, index) {
        archivosPorUnidad[u].splice(index, 1);
        await recalcularHashesCreate();
        renderizarDropzoneCreate(u);
    };

    /* ---------- Rúbricas por unidad (hasta 3 PDFs, igual que instrumentos) ---------- */
    window.agregarRubricasCreate = async function (input, u) {
        if (!rubricasPorUnidad[u]) rubricasPorUnidad[u] = [];

        if (!await validarArchivoPdf(input, 2)) return;

        for (const file of input.files) {
            if (rubricasPorUnidad[u].length >= 3) {
                await Swal.fire({ icon: 'info', title: 'Límite', text: 'Máx 3 rúbricas por unidad.' });
                break;
            }
            const h = await hashArchivo(file);
            if (window.__HASHES_SELECCIONADOS__.has(h) || window.__HASHES_EXISTENTES__.has(h)) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Archivo duplicado',
                    html: `El archivo <b>${escaparHtml(file.name)}</b> ya fue subido o seleccionado.`
                });
                break;
            }
            rubricasPorUnidad[u].push(file);
            window.__HASHES_SELECCIONADOS__.add(h);
        }

        renderizarRubricasCreate(u);
        input.value = '';
    };

    function renderizarRubricasCreate(u) {
        const lista = document.getElementById(`lista_rubricas_u_${u}`);
        const container = document.getElementById(`hidden_rubricas_u_${u}`);
        const files = rubricasPorUnidad[u] || [];
        if (!lista || !container) return;

        lista.innerHTML = '';
        container.innerHTML = '';

        if (files.length === 0) {
            lista.innerHTML = `<span class="text-muted small">Ningún archivo seleccionado.</span>`;
            return;
        }

        files.forEach((file, index) => {
            lista.innerHTML += `
                <div class="archivo-cargado-item d-flex align-items-center justify-content-between p-2 bg-white border rounded-3 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="p-1 rounded-2 bg-danger-subtle text-danger me-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                            <i class="bi bi-file-earmark-pdf-fill fs-6"></i>
                        </div>
                        <span class="text-secondary fw-medium small">${escaparHtml(file.name)}</span>
                    </div>
                    <button type="button" class="btn-eliminar-archivo btn border-0 p-1 text-muted rounded-2" onclick="window.eliminarRubricaCreate(${u}, ${index})">
                        <i class="bi bi-trash3 fs-6"></i>
                    </button>
                </div>`;
        });

        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        const realInput = document.createElement('input');
        realInput.type = 'file';
        realInput.name = `rubricas[${u}][]`;
        realInput.multiple = true;
        realInput.className = 'd-none';
        realInput.files = dt.files;
        container.appendChild(realInput);
    }

    window.eliminarRubricaCreate = async function (u, index) {
        rubricasPorUnidad[u].splice(index, 1);
        await recalcularHashesCreate();
        renderizarRubricasCreate(u);
    };

    // El input real de rúbricas va oculto (no puede ser `required`), así que se valida aquí.
    const formCreate = wrapperRubricas.closest('form');
    if (formCreate) {
        formCreate.addEventListener('submit', async function (e) {
            if (esRevision5()) return;
            const chk0 = document.getElementById('chk_unidad_0');
            if (chk0 && chk0.checked) return;

            const faltantes = [];
            for (let i = 1; i <= totalUnidadesMateria; i++) {
                const chk = document.getElementById(`chk_unidad_${i}`);
                if (chk && chk.checked && !(rubricasPorUnidad[i] && rubricasPorUnidad[i].length)) {
                    faltantes.push(i);
                }
            }
            if (faltantes.length) {
                e.preventDefault();
                await Swal.fire({
                    icon: 'warning',
                    title: 'Faltan rúbricas',
                    text: `Sube al menos un PDF de rúbrica en: ${faltantes.map(u => 'Unidad ' + u).join(', ')}.`
                });
            }
        });
    }

    function limpiarBase() {
        contenedorUnidades.innerHTML = '<div class="col-12"><span class="text-muted small">Selecciona materia y revisión.</span></div>';
        seccionDropzones.innerHTML = '';
        seccionDropzones.style.display = '';
        wrapperCalificaciones.innerHTML = '<span class="text-muted small">Selecciona unidades primero</span>';
        wrapperRac.innerHTML = '<span class="text-muted small">Selecciona unidades primero</span>';
        wrapperRubricas.innerHTML = '<span class="text-muted small">Selecciona unidades primero</span>';
        archivosPorUnidad = {};
        rubricasPorUnidad = {};
        window.__HASHES_SELECCIONADOS__ = new Set(window.__HASHES_EXISTENTES__);
        actualizarCamposRevision1();
        actualizarCamposRevision4();
    }

    actualizarCamposRevision1();
    actualizarCamposRevision4();
}