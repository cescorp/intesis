<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl    = rtrim($configuracion->obtener('APP_URL', ''), '/');
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><strong>Archivos</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars((($usuario['bodega'] ?? null) ? $usuario['bodega'] . ' - ' : '') . $usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">

        <div class="gestor-barra">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPanel" title="Ocultar carpetas" aria-expanded="true" aria-controls="panelCarpetas">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
            <nav class="gestor-ruta" id="rutaActual" aria-label="Ubicación"></nav>
            <div class="gestor-acciones">
                <input type="search" class="form-control form-control-sm" id="buscarArchivo" placeholder="Buscar en esta carpeta" aria-label="Buscar en esta carpeta">
                <?php if ($permisos['carpetas']): ?>
                <button type="button" class="btn btn-sm btn-intesis d-none" id="btnNuevaCarpeta"><i class="bi bi-folder-plus"></i> Nueva carpeta</button>
                <?php endif; ?>
                <?php if ($permisos['compartir']): ?>
                <button type="button" class="btn btn-sm btn-secundario d-none" id="btnCompartirCarpeta"><i class="bi bi-people"></i> Compartir carpeta</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="gestor" id="gestor">
            <aside class="gestor-carpetas" id="panelCarpetas" aria-label="Carpetas">
                <ul class="arbol" id="arbolCarpetas" role="tree"></ul>
            </aside>

            <div class="gestor-centro">
                <?php if ($permisos['subir']): ?>
                <label class="zona-subida d-none" id="zonaSubida">
                    <input type="file" id="inpArchivos" multiple hidden>
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span class="fw-semibold">Arrastre archivos aquí</span>
                    <span class="small">o <u>seleccione desde su equipo</u></span>
                    <span class="small text-muted">Cualquier tipo excepto ejecutables, hasta <?= (int) $maxMb ?> MB cada uno. Un archivo con el mismo nombre se guarda como versión nueva.</span>
                </label>
                <?php endif; ?>
                <div id="colaSubida"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 tabla-archivos">
                        <thead><tr>
                            <th>Nombre</th>
                            <th>Subido por</th>
                            <th class="text-end">Peso</th>
                            <th>Fecha</th>
                            <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                        </tr></thead>
                        <tbody id="listaArchivos"></tbody>
                    </table>
                </div>
            </div>

            <div class="gestor-visor" id="visorArchivo" aria-live="polite"></div>
        </div>

    </section></div></div></main>
</div>

<!-- Modal Compartir -->
<div class="modal fade modal-intesis" id="modalCompartir" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <p class="modal-etiqueta mb-0">Archivos</p>
                    <h2 class="modal-title h6 mb-0">Compartir <span id="lblCompartirNombre"></span></h2>
                </div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Las personas marcadas podrán ver y descargar<span id="lblCompartirAlcance"></span>.</p>
                <input type="search" class="form-control form-control-sm mb-2" id="buscarUsuario" placeholder="Buscar usuario" aria-label="Buscar usuario">
                <div id="listaUsuarios"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-intesis" id="btnGuardarCompartir">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Informacion -->
<div class="modal fade modal-intesis" id="modalInfo" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <p class="modal-etiqueta mb-0">Información</p>
                    <h2 class="modal-title h6 mb-0" id="lblInfoNombre"></h2>
                </div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoInfo"></div>
        </div>
    </div>
</div>

<style>
/* Gestor de archivos: tres zonas (carpetas | lista | visor). El panel de carpetas se
   colapsa y la lista ocupa su espacio. Acento unico: color corporativo en la seleccion. */
.gestor-barra { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .75rem; }
.gestor-ruta { display: flex; align-items: center; gap: .25rem; flex-wrap: wrap; font-size: .9rem; min-width: 0; }
.gestor-ruta button { border: 0; background: none; padding: .1rem .25rem; color: #1f6f68; border-radius: 4px; }
.gestor-ruta button:hover { background: #e6f1f0; }
.gestor-ruta .actual { font-weight: 600; color: inherit; }
.gestor-acciones { display: flex; gap: .4rem; margin-left: auto; flex-wrap: wrap; }
.gestor-acciones input { width: 200px; }
.gestor { display: grid; grid-template-columns: 230px minmax(0, 1fr) minmax(0, 36%); gap: 0;
          border: 1px solid #dee2e6; border-radius: 6px; min-height: 68vh; background: #fff; }
.gestor.panel-oculto { grid-template-columns: 0 minmax(0, 1fr) minmax(0, 40%); }
.gestor.panel-oculto .gestor-carpetas { visibility: hidden; padding: 0; border: 0; }
.gestor-carpetas { border-right: 1px solid #dee2e6; padding: .5rem .25rem; overflow: auto; max-height: 75vh; }
.gestor-centro { padding: .75rem; overflow: auto; max-height: 75vh; }
.gestor-visor { border-left: 1px solid #dee2e6; background: #eef1f1; display: flex; align-items: center;
                justify-content: center; padding: .75rem; min-height: 40vh; }
.gestor-visor iframe { width: 100%; height: 70vh; border: 0; background: #fff; }
.gestor-visor img { max-width: 100%; max-height: 70vh; object-fit: contain; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
.visor-ficha { text-align: center; }
.visor-ficha .bi { font-size: 3rem; color: #6c757d; }
#colaSubida { margin: .25rem 0 .5rem; }
.arbol, .arbol ul { list-style: none; margin: 0; padding: 0; }
.arbol ul { padding-left: .9rem; }
.arbol-nodo { display: flex; align-items: center; gap: .25rem; width: 100%; border: 0; background: none;
              text-align: left; padding: .2rem .35rem; border-radius: 4px; font-size: .86rem; color: inherit; }
.arbol-nodo:hover { background: #f4f6f6; }
.arbol-nodo.activa { background: #e6f1f0; color: #1f6f68; font-weight: 600; }
.arbol-nodo .flecha { width: 1rem; flex: none; color: #6c757d; }
.arbol-nodo .dueno { color: #6c757d; font-weight: 400; font-size: .75rem; }
.arbol-raiz { margin-top: .35rem; }
.tabla-archivos td, .tabla-archivos th { font-size: .84rem; }
.tabla-archivos tbody tr { cursor: pointer; }
.tabla-archivos tbody tr.activa { box-shadow: inset 3px 0 0 #1f6f68; background: #e6f1f0; }
.tabla-archivos .nombre { display: flex; align-items: center; gap: .4rem; min-width: 180px; }
.tabla-archivos .nombre input { font-size: .84rem; }
.tabla-archivos .version { font-size: .68rem; padding: 0 .35rem; border-radius: 8px; background: #1f6f68; color: #fff; }
.tabla-archivos .acciones { white-space: nowrap; }
.usuario-compartir { display: flex; align-items: center; gap: .5rem; padding: .3rem 0; border-bottom: 1px solid #f0f0f0; }
.usuario-compartir small { margin-left: auto; color: #6c757d; }
.info-seccion { margin-bottom: 1rem; }
.info-seccion h3 { font-size: .9rem; font-weight: 600; margin-bottom: .4rem; }
.info-datos { display: grid; grid-template-columns: max-content 1fr; gap: .2rem .9rem; font-size: .85rem; margin: 0; }
.info-datos dt { color: #6c757d; font-weight: 400; }
@media (max-width: 991.98px) {
    .gestor, .gestor.panel-oculto { grid-template-columns: 1fr; }
    .gestor-carpetas { border-right: 0; border-bottom: 1px solid #dee2e6; max-height: 30vh; }
    .gestor.panel-oculto .gestor-carpetas { display: none; }
    .gestor-visor { border-left: 0; border-top: 1px solid #dee2e6; }
    .gestor-acciones { margin-left: 0; width: 100%; }
    .gestor-acciones input { flex: 1; width: auto; }
}
</style>

<script src="<?= $appUrl ?>/publico/js/subida-archivos.js?v=<?= filemtime($configuracion->raiz() . '/publico/js/subida-archivos.js') ?>"></script>
<script>
(function () {
    'use strict';
    const appUrl   = <?= json_encode($appUrl) ?>;
    const permisos = <?= json_encode($permisos) ?>;
    const maxMb    = <?= (int) $maxMb ?>;
    const extensionesBloqueadas = <?= json_encode(\Intesis\Servicios\AlmacenArchivosServicio::EXTENSIONES_PELIGROSAS) ?>;
    const q   = (sel) => document.querySelector(sel);
    const esc = IntesisSubida.esc;

    // ubicacion: { tipo: 'mios' } (raiz propia), { tipo: 'compartidos' } o { tipo: 'carpeta', id }
    const estado = { carpetas: [], abiertas: new Set(), ubicacion: { tipo: 'mios' }, archivos: [], activoId: 0 };

    // ── Datos ────────────────────────────────────────────────────────────────
    async function getJson(url) {
        const json = await (await fetch(appUrl + url)).json();
        if (!json.ok) throw new Error(json.mensaje || 'No se pudo completar la acción.');
        return json.data;
    }
    async function postForm(url, datos) {
        const form = new FormData();
        Object.entries(datos).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => form.append(k + '[]', x)) : form.append(k, v));
        const json = await (await fetch(appUrl + url, { method: 'POST', body: form })).json();
        if (!json.ok) throw new Error(json.mensaje || 'No se pudo completar la acción.');
        return json;
    }
    const avisarError = (error) => Swal.fire({ icon: 'error', title: 'No se pudo completar', text: error.message });

    const carpeta   = (id) => estado.carpetas.find(c => c.sis_carpeta_id === id);
    const hijas     = (padreId) => estado.carpetas.filter(c => c.sis_carpeta_padre_id === padreId);
    // Raices de "Compartido conmigo": carpetas ajenas cuya carpeta padre no me es visible
    const raicesCompartidas = () => estado.carpetas.filter(c => !c.propia && (c.sis_carpeta_padre_id === null || !carpeta(c.sis_carpeta_padre_id)));

    function ubicacionEsPropia() {
        const u = estado.ubicacion;
        return u.tipo === 'mios' || (u.tipo === 'carpeta' && carpeta(u.id)?.propia);
    }

    async function cargarArbol() {
        const data = await getJson('/archivos/arbol');
        estado.carpetas = data.carpetas.map(c => ({
            ...c,
            sis_carpeta_id: Number(c.sis_carpeta_id),
            sis_carpeta_padre_id: c.sis_carpeta_padre_id === null ? null : Number(c.sis_carpeta_padre_id),
            propia: c.propia === true || c.propia === 't',
            compartida: c.compartida === true || c.compartida === 't',
        }));
        renderArbol();
    }

    async function abrir(ubicacion) {
        estado.ubicacion = ubicacion;
        estado.activoId = 0;
        if (ubicacion.tipo === 'carpeta') {
            // Abrir una carpeta despliega su rama en el arbol
            for (let c = carpeta(ubicacion.id); c; c = carpeta(c.sis_carpeta_padre_id)) estado.abiertas.add(c.sis_carpeta_id);
        }
        renderArbol();
        renderRuta();
        renderBarra();
        q('#listaArchivos').innerHTML = '<tr><td colspan="5" class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';
        try {
            const url = ubicacion.tipo === 'compartidos' ? '/archivos/contenido?compartidos=1'
                : '/archivos/contenido?carpeta_id=' + (ubicacion.tipo === 'carpeta' ? ubicacion.id : 0);
            const data = await getJson(url);
            estado.archivos = data.archivos.map(a => ({ ...a, sis_archivos_id: Number(a.sis_archivos_id), propio: !!a.propio }));
            renderLista();
            mostrarEnVisor(0);
        } catch (error) {
            q('#listaArchivos').innerHTML = `<tr><td colspan="5" class="text-danger small py-3">${esc(error.message)}</td></tr>`;
        }
    }
    const recargar = () => abrir(estado.ubicacion);

    // ── Arbol ────────────────────────────────────────────────────────────────
    function nodoHtml(c) {
        const tieneHijas = hijas(c.sis_carpeta_id).length > 0;
        const abierta = estado.abiertas.has(c.sis_carpeta_id);
        const activa = estado.ubicacion.tipo === 'carpeta' && estado.ubicacion.id === c.sis_carpeta_id;
        return `<li role="treeitem" aria-expanded="${tieneHijas ? abierta : 'false'}">
            <button type="button" class="arbol-nodo${activa ? ' activa' : ''}" data-carpeta="${c.sis_carpeta_id}">
                <span class="flecha" data-alternar="${c.sis_carpeta_id}">${tieneHijas ? `<i class="bi bi-chevron-${abierta ? 'down' : 'right'}"></i>` : ''}</span>
                <i class="bi bi-folder${activa ? '2-open' : ''}"></i>
                <span class="text-truncate">${esc(c.sis_carpeta_nombre)}</span>
                ${c.propia && c.compartida ? '<i class="bi bi-people text-muted" title="Compartida"></i>' : ''}
                ${!c.propia && (c.sis_carpeta_padre_id === null || !carpeta(c.sis_carpeta_padre_id)) ? `<span class="dueno">${esc(c.dueno_nombre)}</span>` : ''}
            </button>
            ${tieneHijas && abierta ? `<ul role="group">${hijas(c.sis_carpeta_id).map(nodoHtml).join('')}</ul>` : ''}
        </li>`;
    }

    function renderArbol() {
        const u = estado.ubicacion;
        const raizPropia = estado.carpetas.filter(c => c.propia && c.sis_carpeta_padre_id === null);
        q('#arbolCarpetas').innerHTML = `
            <li role="treeitem"><button type="button" class="arbol-nodo${u.tipo === 'mios' ? ' activa' : ''}" data-raiz="mios">
                <span class="flecha"></span><i class="bi bi-person"></i> Mis archivos</button>
                <ul role="group">${raizPropia.map(nodoHtml).join('')}</ul></li>
            <li role="treeitem" class="arbol-raiz"><button type="button" class="arbol-nodo${u.tipo === 'compartidos' ? ' activa' : ''}" data-raiz="compartidos">
                <span class="flecha"></span><i class="bi bi-people"></i> Compartido conmigo</button>
                <ul role="group">${raicesCompartidas().map(nodoHtml).join('')}</ul></li>`;
    }

    q('#arbolCarpetas').addEventListener('click', (e) => {
        const alternar = e.target.closest('[data-alternar]');
        if (alternar && alternar.dataset.alternar) {
            const id = Number(alternar.dataset.alternar);
            estado.abiertas.has(id) ? estado.abiertas.delete(id) : estado.abiertas.add(id);
            renderArbol();
            return;
        }
        const nodo = e.target.closest('.arbol-nodo');
        if (!nodo) return;
        if (nodo.dataset.raiz) abrir({ tipo: nodo.dataset.raiz });
        else abrir({ tipo: 'carpeta', id: Number(nodo.dataset.carpeta) });
    });

    // ── Ruta y barra ─────────────────────────────────────────────────────────
    function renderRuta() {
        const u = estado.ubicacion;
        const tramos = [];
        if (u.tipo === 'carpeta') {
            for (let c = carpeta(u.id); c; c = carpeta(c.sis_carpeta_padre_id)) tramos.unshift(c);
        }
        const propia = u.tipo === 'mios' || (u.tipo === 'carpeta' && carpeta(u.id)?.propia);
        const raiz = propia ? { tipo: 'mios', texto: 'Mis archivos', icono: 'bi-person' } : { tipo: 'compartidos', texto: 'Compartido conmigo', icono: 'bi-people' };
        const partes = [`<button type="button" data-ir="${raiz.tipo}" class="${tramos.length ? '' : 'actual'}"><i class="bi ${raiz.icono}"></i> ${raiz.texto}</button>`];
        tramos.forEach((c, i) => partes.push(`<i class="bi bi-chevron-right small text-muted"></i>
            <button type="button" data-ir-carpeta="${c.sis_carpeta_id}" class="${i === tramos.length - 1 ? 'actual' : ''}">${esc(c.sis_carpeta_nombre)}</button>`));
        q('#rutaActual').innerHTML = partes.join('');
    }
    q('#rutaActual').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.ir) abrir({ tipo: b.dataset.ir });
        else abrir({ tipo: 'carpeta', id: Number(b.dataset.irCarpeta) });
    });

    function renderBarra() {
        const propia = ubicacionEsPropia();
        q('#btnNuevaCarpeta')?.classList.toggle('d-none', !propia);
        q('#zonaSubida')?.classList.toggle('d-none', !propia);
        q('#btnCompartirCarpeta')?.classList.toggle('d-none', !(propia && estado.ubicacion.tipo === 'carpeta'));
        q('#buscarArchivo').value = '';
    }

    // ── Lista ────────────────────────────────────────────────────────────────
    function iconoArchivo(a) {
        const ext = a.extension || '';
        if (a.sis_archivos_mime === 'application/pdf') return 'bi-file-earmark-pdf text-danger';
        if ((a.sis_archivos_mime || '').startsWith('image/')) return 'bi-file-earmark-image text-primary';
        if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) return 'bi-file-earmark-spreadsheet text-success';
        if (['doc', 'docx', 'odt', 'rtf', 'txt'].includes(ext)) return 'bi-file-earmark-text text-primary';
        if (['zip', 'rar', '7z'].includes(ext)) return 'bi-file-earmark-zip text-warning';
        return 'bi-file-earmark text-secondary';
    }

    function fecha(valor) {
        if (!valor) return '';
        const d = new Date(String(valor).replace(' ', 'T'));
        return isNaN(d) ? valor : d.toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function subcarpetasActuales() {
        const u = estado.ubicacion;
        if (u.tipo === 'mios') return estado.carpetas.filter(c => c.propia && c.sis_carpeta_padre_id === null);
        if (u.tipo === 'compartidos') return raicesCompartidas();
        return hijas(u.id);
    }

    function renderLista() {
        const filtro = q('#buscarArchivo').value.trim().toLowerCase();
        const coincide = (nombre) => !filtro || String(nombre).toLowerCase().includes(filtro);
        const filasCarpetas = subcarpetasActuales().filter(c => coincide(c.sis_carpeta_nombre)).map(c => `
            <tr data-carpeta="${c.sis_carpeta_id}">
                <td><div class="nombre"><i class="bi bi-folder-fill text-warning"></i> ${esc(c.sis_carpeta_nombre)}</div></td>
                <td>${esc(c.dueno_nombre)}</td><td class="text-end">—</td><td></td>
                <td class="text-end acciones">${c.propia ? `
                    ${permisos.carpetas ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-accion="renombrar-carpeta" title="Renombrar carpeta"><i class="bi bi-pencil"></i></button>' : ''}
                    ${permisos.compartir ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-accion="compartir-carpeta" title="Compartir carpeta"><i class="bi bi-people"></i></button>' : ''}
                    ${permisos.eliminar ? '<button type="button" class="btn btn-sm btn-outline-danger" data-accion="eliminar-carpeta" title="Eliminar carpeta"><i class="bi bi-trash"></i></button>' : ''}` : ''}
                </td>
            </tr>`);
        const filasArchivos = estado.archivos.filter(a => coincide(a.sis_archivos_nombre)).map(a => `
            <tr data-archivo="${a.sis_archivos_id}" class="${a.sis_archivos_id === estado.activoId ? 'activa' : ''}">
                <td><div class="nombre">
                    <i class="bi ${iconoArchivo(a)}"></i>
                    ${a.propio && permisos.subir
                        ? `<input type="text" class="form-control form-control-sm nombre-archivo" maxlength="150" value="${esc(a.sis_archivos_nombre)}" aria-label="Nombre del archivo">`
                        : `<span>${esc(a.sis_archivos_nombre)}</span>`}
                    ${a.extension ? `<span class="text-muted small">.${esc(a.extension)}</span>` : ''}
                    ${Number(a.sis_archivos_version) > 1 ? `<span class="version" title="Tiene versiones anteriores">v${Number(a.sis_archivos_version)}</span>` : ''}
                    ${a.propio && (a.compartido === true || a.compartido === 't') ? '<i class="bi bi-people text-muted" title="Compartido"></i>' : ''}
                </div></td>
                <td>${esc(a.subido_por)}</td>
                <td class="text-end text-nowrap">${IntesisSubida.tamanoLegible(a.sis_archivos_tamano)}</td>
                <td class="text-nowrap">${fecha(a.fecha_crea)}</td>
                <td class="text-end acciones">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-accion="info" title="Información"><i class="bi bi-info-circle"></i></button>
                    <a class="btn btn-sm btn-outline-secondary" href="${appUrl}/archivos/descargar?archivo_id=${a.sis_archivos_id}" title="Descargar"><i class="bi bi-download"></i></a>
                    ${a.propio && permisos.compartir ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-accion="compartir-archivo" title="Compartir"><i class="bi bi-people"></i></button>' : ''}
                    ${a.propio && permisos.eliminar ? '<button type="button" class="btn btn-sm btn-outline-danger" data-accion="eliminar-archivo" title="Eliminar"><i class="bi bi-trash"></i></button>' : ''}
                </td>
            </tr>`);
        const filas = [...filasCarpetas, ...filasArchivos];
        q('#listaArchivos').innerHTML = filas.length ? filas.join('')
            : `<tr><td colspan="5" class="text-muted small py-4 text-center">${filtro ? 'Nada coincide con la búsqueda.'
                : (ubicacionEsPropia() ? 'Esta carpeta está vacía. Arrastre archivos arriba para subirlos.' : 'No hay nada aquí todavía.')}</td></tr>`;
    }
    q('#buscarArchivo').addEventListener('input', renderLista);

    // ── Visor ────────────────────────────────────────────────────────────────
    function mostrarEnVisor(id) {
        const a = estado.archivos.find(x => x.sis_archivos_id === id);
        estado.activoId = a ? id : 0;
        q('#listaArchivos').querySelectorAll('tr[data-archivo]').forEach(tr => tr.classList.toggle('activa', Number(tr.dataset.archivo) === estado.activoId));
        const visor = q('#visorArchivo');
        if (!a) {
            visor.innerHTML = '<p class="text-muted small m-0">Elija un archivo para verlo aquí.</p>';
            return;
        }
        const url = `${appUrl}/archivos/ver?archivo_id=${a.sis_archivos_id}`;
        if (a.previsualizable && a.sis_archivos_mime === 'application/pdf') {
            visor.innerHTML = `<iframe src="${url}" title="${esc(a.sis_archivos_nombre)}"></iframe>`;
        } else if (a.previsualizable) {
            visor.innerHTML = `<img src="${url}" alt="${esc(a.sis_archivos_nombre)}">`;
        } else {
            visor.innerHTML = `<div class="visor-ficha">
                <i class="bi ${iconoArchivo(a)}"></i>
                <p class="fw-semibold mb-1 mt-2">${esc(a.sis_archivos_nombre)}${a.extension ? '.' + esc(a.extension) : ''}</p>
                <p class="small text-muted mb-3">${IntesisSubida.tamanoLegible(a.sis_archivos_tamano)} · subido por ${esc(a.subido_por)}</p>
                <a class="btn btn-sm btn-intesis" href="${appUrl}/archivos/descargar?archivo_id=${a.sis_archivos_id}"><i class="bi bi-download"></i> Descargar</a>
                <p class="small text-muted mt-2 mb-0">Este tipo de archivo no se puede mostrar aquí.</p></div>`;
        }
    }

    // ── Acciones en la lista ─────────────────────────────────────────────────
    q('#listaArchivos').addEventListener('click', async (e) => {
        const fila = e.target.closest('tr');
        if (!fila || e.target.closest('a') || e.target.closest('input')) return;
        const accion = e.target.closest('[data-accion]')?.dataset.accion;
        const carpetaId = Number(fila.dataset.carpeta || 0);
        const archivoId = Number(fila.dataset.archivo || 0);
        try {
            if (!accion) {
                if (carpetaId) abrir({ tipo: 'carpeta', id: carpetaId });
                else if (archivoId) mostrarEnVisor(archivoId);
            } else if (accion === 'info') {
                abrirInfo(archivoId);
            } else if (accion === 'compartir-archivo') {
                abrirCompartir('ARCHIVO', archivoId, estado.archivos.find(a => a.sis_archivos_id === archivoId).sis_archivos_nombre);
            } else if (accion === 'compartir-carpeta') {
                abrirCompartir('CARPETA', carpetaId, carpeta(carpetaId).sis_carpeta_nombre);
            } else if (accion === 'renombrar-carpeta') {
                await renombrarCarpeta(carpetaId);
            } else if (accion === 'eliminar-carpeta') {
                await eliminar('/archivos/carpetas/eliminar', { carpeta_id: carpetaId }, `la carpeta "${carpeta(carpetaId).sis_carpeta_nombre}"`, true);
            } else if (accion === 'eliminar-archivo') {
                const a = estado.archivos.find(x => x.sis_archivos_id === archivoId);
                await eliminar('/archivos/eliminar', { archivo_id: archivoId }, `"${a.sis_archivos_nombre}" con todas sus versiones`, false);
            }
        } catch (error) { avisarError(error); }
    });

    // Al editar el nombre se ve el archivo sin redibujar la lista (se perderia el foco)
    q('#listaArchivos').addEventListener('focusin', (e) => {
        if (e.target.matches('.nombre-archivo')) mostrarEnVisor(Number(e.target.closest('tr').dataset.archivo));
    });
    q('#listaArchivos').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('.nombre-archivo')) { e.preventDefault(); e.target.blur(); }
    });
    q('#listaArchivos').addEventListener('change', async (e) => {
        if (!e.target.matches('.nombre-archivo')) return;
        const a = estado.archivos.find(x => x.sis_archivos_id === Number(e.target.closest('tr').dataset.archivo));
        const nombre = e.target.value.trim();
        if (!nombre) { e.target.value = a.sis_archivos_nombre; return; }
        try {
            const json = await postForm('/archivos/renombrar', { archivo_id: a.sis_archivos_id, nombre });
            a.sis_archivos_nombre = json.data.nombre;
        } catch (error) { e.target.value = a.sis_archivos_nombre; avisarError(error); }
    });

    async function eliminar(url, datos, descripcion, esCarpeta) {
        const conf = await Swal.fire({
            icon: 'warning', title: esCarpeta ? '¿Eliminar carpeta?' : '¿Eliminar archivo?',
            text: `Se eliminará ${descripcion}.`, showCancelButton: true,
            confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
        });
        if (!conf.isConfirmed) return;
        await postForm(url, datos);
        if (esCarpeta) await cargarArbol();
        recargar();
    }

    async function pedirNombre(titulo, valor) {
        const r = await Swal.fire({
            title: titulo, input: 'text', inputValue: valor || '', inputAttributes: { maxlength: 150 },
            showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar', confirmButtonColor: '#1f6f68',
            inputValidator: (v) => !v.trim() ? 'Escriba un nombre.' : undefined,
        });
        return r.isConfirmed ? r.value.trim() : null;
    }

    async function renombrarCarpeta(id) {
        const nombre = await pedirNombre('Renombrar carpeta', carpeta(id).sis_carpeta_nombre);
        if (!nombre) return;
        await postForm('/archivos/carpetas/renombrar', { carpeta_id: id, nombre });
        await cargarArbol();
        recargar();
    }

    q('#btnNuevaCarpeta')?.addEventListener('click', async () => {
        try {
            const nombre = await pedirNombre('Nueva carpeta', '');
            if (!nombre) return;
            const padreId = estado.ubicacion.tipo === 'carpeta' ? estado.ubicacion.id : 0;
            await postForm('/archivos/carpetas/crear', { padre_id: padreId, nombre });
            if (padreId) estado.abiertas.add(padreId);
            await cargarArbol();
            recargar();
        } catch (error) { avisarError(error); }
    });

    q('#btnCompartirCarpeta')?.addEventListener('click', () => {
        const id = estado.ubicacion.id;
        abrirCompartir('CARPETA', id, carpeta(id).sis_carpeta_nombre);
    });

    // ── Subida ───────────────────────────────────────────────────────────────
    if (q('#zonaSubida')) {
        IntesisSubida.conectarZona(q('#zonaSubida'), q('#inpArchivos'), (archivos) => {
            IntesisSubida.subirArchivos(archivos, {
                url: appUrl + '/archivos/subir',
                campos: { carpeta_id: estado.ubicacion.tipo === 'carpeta' ? estado.ubicacion.id : 0 },
                contenedorCola: q('#colaSubida'),
                reglas: { maxMb, mimes: null, extensionesBloqueadas },
            }).then(recargar);
        });
    }

    // ── Compartir ────────────────────────────────────────────────────────────
    const compartir = { tipo: '', id: 0 };

    async function abrirCompartir(tipo, id, nombre) {
        compartir.tipo = tipo;
        compartir.id = id;
        q('#lblCompartirNombre').textContent = `"${nombre}"`;
        q('#lblCompartirAlcance').textContent = tipo === 'CARPETA' ? ' esta carpeta con todo su contenido' : ' este archivo y sus versiones';
        q('#buscarUsuario').value = '';
        q('#listaUsuarios').innerHTML = '<p class="text-muted small">Cargando usuarios...</p>';
        bootstrap.Modal.getOrCreateInstance(q('#modalCompartir')).show();
        try {
            const data = await getJson(`/archivos/compartidos?tipo=${tipo}&id=${id}`);
            q('#listaUsuarios').innerHTML = data.usuarios.length ? data.usuarios.map(u => `
                <label class="usuario-compartir" data-nombre="${esc(u.sis_usuarios_nombre.toLowerCase())}">
                    <input type="checkbox" class="form-check-input m-0" value="${u.sis_usuarios_id}" ${u.compartido_desde ? 'checked' : ''}>
                    <span>${esc(u.sis_usuarios_nombre)}</span>
                    ${u.compartido_desde ? `<small>compartido desde ${fecha(u.compartido_desde).slice(0, 10)}</small>` : ''}
                </label>`).join('')
                : '<p class="text-muted small mb-0">No hay otros usuarios en la empresa.</p>';
        } catch (error) {
            q('#listaUsuarios').innerHTML = `<p class="text-danger small">${esc(error.message)}</p>`;
        }
    }

    q('#buscarUsuario').addEventListener('input', (e) => {
        const t = e.target.value.trim().toLowerCase();
        q('#listaUsuarios').querySelectorAll('.usuario-compartir').forEach(l => l.classList.toggle('d-none', !!t && !l.dataset.nombre.includes(t)));
    });

    q('#btnGuardarCompartir').addEventListener('click', async () => {
        const usuarios = [...q('#listaUsuarios').querySelectorAll('input:checked')].map(i => i.value);
        try {
            const json = await postForm('/archivos/compartir', { tipo: compartir.tipo, id: compartir.id, usuarios });
            bootstrap.Modal.getInstance(q('#modalCompartir'))?.hide();
            Swal.fire({ icon: 'success', title: json.mensaje, timer: 1400, showConfirmButton: false });
            await cargarArbol();
            recargar();
        } catch (error) { avisarError(error); }
    });

    // ── Informacion ──────────────────────────────────────────────────────────
    async function abrirInfo(id) {
        const a = estado.archivos.find(x => x.sis_archivos_id === id);
        q('#lblInfoNombre').textContent = a.sis_archivos_nombre + (a.extension ? '.' + a.extension : '');
        q('#cuerpoInfo').innerHTML = '<p class="text-muted small">Cargando...</p>';
        bootstrap.Modal.getOrCreateInstance(q('#modalInfo')).show();
        try {
            const d = await getJson('/archivos/info?archivo_id=' + id);
            const ubicacion = q('#rutaActual').innerText.replace(/\s*\n\s*/g, ' › ');
            const accionTexto = { ABRIR: 'Abrió', DESCARGAR: 'Descargó' };
            q('#cuerpoInfo').innerHTML = `
                <section class="info-seccion"><h3>General</h3>
                    <dl class="info-datos">
                        <dt>Tipo</dt><dd>${esc(a.extension ? a.extension.toUpperCase() : 'Sin extensión')} (${esc(a.sis_archivos_mime)})</dd>
                        <dt>Peso</dt><dd>${IntesisSubida.tamanoLegible(a.sis_archivos_tamano)}</dd>
                        <dt>Dueño</dt><dd>${esc(a.subido_por)}</dd>
                        <dt>Última versión</dt><dd>v${Number(a.sis_archivos_version)}, subida el ${fecha(a.fecha_crea)}</dd>
                        <dt>Ubicación</dt><dd>${esc(ubicacion)}</dd>
                    </dl></section>
                <section class="info-seccion"><h3>Versiones</h3>
                    <table class="table table-sm mb-0"><tbody>${d.versiones.map((v, i) => `<tr>
                        <td>v${Number(v.sis_archivos_version)}${i === 0 ? ' <span class="text-muted small">(actual)</span>' : ''}</td>
                        <td>${fecha(v.fecha_crea)}</td><td>${esc(v.subido_por)}</td>
                        <td class="text-end">${IntesisSubida.tamanoLegible(v.sis_archivos_tamano)}</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="${appUrl}/archivos/descargar?archivo_id=${v.sis_archivos_id}" title="Descargar esta versión"><i class="bi bi-download"></i></a></td>
                    </tr>`).join('')}</tbody></table></section>
                <section class="info-seccion"><h3>Compartido con</h3>
                    ${d.compartido.length ? `<table class="table table-sm mb-0"><tbody>${d.compartido.map(c => `<tr>
                        <td>${esc(c.sis_usuarios_nombre)}</td><td>desde ${fecha(c.desde)}</td>
                        <td class="text-muted small">${c.origen ? 'por la carpeta ' + esc(c.origen) : 'directo'}</td></tr>`).join('')}</tbody></table>`
                        : '<p class="text-muted small mb-0">No está compartido.</p>'}</section>
                <section class="info-seccion mb-0"><h3>Accesos</h3>
                    ${d.accesos.length ? `<table class="table table-sm mb-0"><tbody>${d.accesos.map(x => `<tr>
                        <td class="text-nowrap">${fecha(x.fecha_crea)}</td><td>${esc(x.sis_usuarios_nombre)}</td>
                        <td>${accionTexto[x.accion] || esc(x.accion)} v${Number(x.version)}</td></tr>`).join('')}</tbody></table>`
                        : '<p class="text-muted small mb-0">Nadie lo ha abierto todavía.</p>'}</section>`;
        } catch (error) {
            q('#cuerpoInfo').innerHTML = `<p class="text-danger small">${esc(error.message)}</p>`;
        }
    }

    // ── Panel de carpetas colapsable (se recuerda por navegador) ─────────────
    const CLAVE_PANEL = 'intesis.archivos.panelOculto';
    function aplicarPanel(oculto) {
        q('#gestor').classList.toggle('panel-oculto', oculto);
        q('#btnPanel').setAttribute('aria-expanded', String(!oculto));
        q('#btnPanel').title = oculto ? 'Mostrar carpetas' : 'Ocultar carpetas';
    }
    q('#btnPanel').addEventListener('click', () => {
        const oculto = !q('#gestor').classList.contains('panel-oculto');
        aplicarPanel(oculto);
        try { localStorage.setItem(CLAVE_PANEL, oculto ? '1' : '0'); } catch { /* almacenamiento no disponible */ }
    });
    try { aplicarPanel(localStorage.getItem(CLAVE_PANEL) === '1'); } catch { aplicarPanel(false); }

    // Bootstrap y SweetAlert se cargan al final de la pagina (plantillas/pie.php)
    document.addEventListener('DOMContentLoaded', () => cargarArbol().then(() => abrir({ tipo: 'mios' })).catch(avisarError));
})();
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
