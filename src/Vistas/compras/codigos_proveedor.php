<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl    = rtrim($configuracion->obtener('APP_URL', ''), '/');
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Compras</span><i class="bi bi-chevron-right"></i><a href="<?= $appUrl ?>/compras/proveedores" class="breadcrumb-link">Proveedores</a><i class="bi bi-chevron-right"></i><strong>Códigos de Proveedor</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars((($usuario['bodega'] ?? null) ? $usuario['bodega'] . ' - ' : '') . $usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">

        <!-- Cabecera: botón crear + filtros -->
        <div class="panel-crud-cabecera flex-wrap gap-2">
            <?php if ($permisos['crear']): ?>
            <button type="button" class="btn btn-intesis btn-crud" id="btnNuevoCodigo">
                <i class="bi bi-plus-square"></i> Nuevo código
            </button>
            <?php endif; ?>
            <form method="get" action="<?= $appUrl ?>/compras/codigos-proveedor" class="d-flex flex-wrap gap-2 ms-auto align-items-center">
                <select name="proveedor_id" class="form-select form-select-sm" style="min-width:220px;max-width:320px">
                    <option value="">— Todos los proveedores —</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= (int) $p['com_proveedor_id'] ?>" <?= (int) $p['com_proveedor_id'] === $filtroProveedorId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['com_proveedor_razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="estado" class="form-select form-select-sm" style="width:140px">
                    <option value="">— Todos —</option>
                    <option value="1" <?= $filtroEstado === '1' ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= $filtroEstado === '0' ? 'selected' : '' ?>>Inactivo</option>
                </select>
                <button type="submit" class="btn btn-secundario btn-sm"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="<?= $appUrl ?>/compras/codigos-proveedor" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
            </form>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?= htmlspecialchars($mensaje['tipo']) ?> alert-dismissible fade show mt-2" role="alert">
            <strong><?= htmlspecialchars($mensaje['titulo']) ?></strong> <?= htmlspecialchars($mensaje['texto']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div id="alertaCodigos"></div>

        <div class="table-responsive">
            <table id="tablaCodigos" class="table table-hover tabla-intesis align-middle w-100">
                <thead><tr>
                    <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                    <th>Cod. Proveedor</th>
                    <th>Razón Social</th>
                    <th>Producto</th>
                    <th>Usuario asigna</th>
                    <th>Fecha</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($codigos as $c): ?>
                <tr>
                    <?php if ($esSuperusuario): ?><td><?= htmlspecialchars((string) ($c['empresa_nombre'] ?? '')) ?></td><?php endif; ?>
                    <td><code><?= htmlspecialchars($c['inv_codigo_proveedor_codigo']) ?></code></td>
                    <td><?= htmlspecialchars($c['com_proveedor_razon_social']) ?></td>
                    <td>
                        <?php if ($c['inv_producto_nombre']): ?>
                        <span class="text-muted small"><?= htmlspecialchars((string) $c['codigo_interno']) ?></span>
                        <span class="ms-1"><?= htmlspecialchars($c['inv_producto_nombre']) ?></span>
                        <?php else: ?>
                        <span class="text-danger small">Sin producto</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($c['usuario_asigna'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($c['fecha_asigna'] ?? '—')) ?></td>
                    <td class="text-center">
                        <?php if ($c['inv_codigo_proveedor_estado']): ?>
                        <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end acciones-tabla">
                        <?php if ($permisos['editar']): ?>
                        <button class="btn btn-accion btn-editar-codigo" title="Editar"
                            data-id="<?= (int) $c['inv_codigo_proveedor_id'] ?>"
                            data-proveedor-id="<?= (int) $c['com_proveedor_id'] ?>"
                            data-proveedor-nombre="<?= htmlspecialchars($c['com_proveedor_razon_social']) ?>"
                            data-codigo="<?= htmlspecialchars($c['inv_codigo_proveedor_codigo']) ?>"
                            data-producto-id="<?= (int) ($c['inv_producto_id'] ?? 0) ?>"
                            data-producto-nombre="<?= htmlspecialchars((string) $c['inv_producto_nombre']) ?>"
                            data-codigo-interno="<?= htmlspecialchars((string) $c['codigo_interno']) ?>">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($permisos['inactivar']): ?>
                        <?php if ($c['inv_codigo_proveedor_estado']): ?>
                        <button class="btn btn-accion btn-inactivar btn-toggle-estado" title="Inactivar"
                            data-id="<?= (int) $c['inv_codigo_proveedor_id'] ?>" data-accion="inactivar">
                            <i class="bi bi-toggle-off"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn btn-accion btn-activar btn-toggle-estado" title="Activar"
                            data-id="<?= (int) $c['inv_codigo_proveedor_id'] ?>" data-accion="activar">
                            <i class="bi bi-toggle-on"></i>
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($permisos['eliminar']): ?>
                        <button class="btn btn-accion btn-eliminar btn-eliminar-codigo" title="Eliminar permanente"
                            data-id="<?= (int) $c['inv_codigo_proveedor_id'] ?>"
                            data-codigo="<?= htmlspecialchars($c['inv_codigo_proveedor_codigo']) ?>">
                            <i class="bi bi-trash3"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </section></div></div></main>
</div>

<!-- ============================================================
     MODAL CREAR / EDITAR CÓDIGO
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalCodigo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras · Proveedores</p><h2 class="modal-title" id="modalCodigoTitulo">Nuevo código</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <div id="alertaModalCodigo"></div>
                <input type="hidden" id="codigoId">

                <!-- Proveedor (solo en crear) -->
                <div class="mb-3" id="grupoProveedorSelect">
                    <label class="form-label fw-semibold">Proveedor <span class="text-danger">*</span></label>
                    <select id="selectProveedor" class="form-select">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= (int) $p['com_proveedor_id'] ?>">
                            <?= htmlspecialchars($p['com_proveedor_razon_social']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Proveedor readonly (en editar) -->
                <div class="mb-3 d-none" id="grupoProveedorTexto">
                    <label class="form-label fw-semibold">Proveedor</label>
                    <input type="text" class="form-control" id="proveedorTexto" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Código de proveedor <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="inputCodigo" placeholder="Ej: ABC-001" maxlength="100">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Producto <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="inputProductoBuscar"
                            placeholder="Código interno o nombre — Enter para buscar"
                            autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="btnLimpiarProducto" title="Limpiar"><i class="bi bi-x"></i></button>
                    </div>
                    <input type="hidden" id="inputProductoId">
                    <div id="productoSeleccionado" class="mt-1 small text-success d-none">
                        <i class="bi bi-check-circle me-1"></i><span id="productoSeleccionadoTexto"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-intesis btn-sm" id="btnGuardarCodigo">
                    <i class="bi bi-floppy me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL BUSCAR PRODUCTO
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalBuscarProductoCod" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras · Proveedores</p><h2 class="modal-title">Buscar Producto</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="buscarProductoCodInput" placeholder="Código interno o nombre...">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="tablaModalBuscarProd">
                        <thead><tr>
                            <th>#</th>
                            <th>Cod. Interno</th>
                            <th>Nombre Producto</th>
                            <th>Cod. Proveedor existente</th>
                            <th>Razón Social</th>
                            <th class="text-center">Stock Total</th>
                        </tr></thead>
                        <tbody id="cuerpoBuscarProductoCod">
                            <tr><td colspan="6" class="text-center text-muted py-3">Escribe para buscar...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
const appUrl    = <?= json_encode($appUrl) ?>;
const puedeEditar    = <?= $permisos['editar']    ? 'true' : 'false' ?>;
const puedeInactivar = <?= $permisos['inactivar'] ? 'true' : 'false' ?>;
const puedeEliminar  = <?= $permisos['eliminar']  ? 'true' : 'false' ?>;

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function mostrarAlertaCodigos(tipo, texto) {
    document.getElementById('alertaCodigos').innerHTML =
        `<div class="alert alert-${tipo} alert-dismissible fade show mt-2" role="alert">
            ${esc(texto)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
         </div>`;
}

function mostrarAlertaModal(tipo, texto) {
    document.getElementById('alertaModalCodigo').innerHTML =
        `<div class="alert alert-${tipo} py-2 small mb-2">${esc(texto)}</div>`;
}

// ── Búsqueda de productos (Enter → coincidencia exacta o modal) ──────────────
const inputBuscar  = document.getElementById('inputProductoBuscar');
const inputProdId  = document.getElementById('inputProductoId');
const prodSel      = document.getElementById('productoSeleccionado');
const prodSelTexto = document.getElementById('productoSeleccionadoTexto');

function seleccionarProducto(id, nombre, codInterno) {
    inputProdId.value        = id;
    inputBuscar.value        = (codInterno ? codInterno + ' — ' : '') + nombre;
    prodSelTexto.textContent = (codInterno ? codInterno + ' — ' : '') + nombre;
    prodSel.classList.remove('d-none');
    bootstrap.Modal.getInstance(document.getElementById('modalBuscarProductoCod'))?.hide();
}

function limpiarProducto() {
    inputProdId.value = '';
    inputBuscar.value = '';
    prodSel.classList.add('d-none');
}

// Enter: exacto → asigna, sino → abre modal
inputBuscar?.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const q = inputBuscar.value.trim();
    if (!q) { abrirModalBuscarProducto(''); return; }
    try {
        const res  = await fetch(appUrl + '/compras/codigos-proveedor/productos?q=' + encodeURIComponent(q));
        const json = await res.json();
        const exacto = (json.productos || []).find(
            p => (p.codigo_interno || '').toUpperCase() === q.toUpperCase()
        );
        if (exacto) {
            seleccionarProducto(exacto.inv_producto_id, exacto.inv_producto_nombre, exacto.codigo_interno);
        } else {
            abrirModalBuscarProducto(q);
        }
    } catch {
        abrirModalBuscarProducto(q);
    }
});

document.getElementById('btnLimpiarProducto')?.addEventListener('click', limpiarProducto);

// ── Modal Buscar Producto ────────────────────────────────────────────────────
let timerBuscProd = null;

function abrirModalBuscarProducto(texto) {
    const inp        = document.getElementById('buscarProductoCodInput');
    const elCodigo   = document.getElementById('modalCodigo');
    const elBuscar   = document.getElementById('modalBuscarProductoCod');
    if (inp) inp.value = texto;

    // Ocultar modal código primero; abrir búsqueda cuando termine la animación
    const whenHidden = () => {
        elCodigo.removeEventListener('hidden.bs.modal', whenHidden);
        new bootstrap.Modal(elBuscar).show();
        buscarProductosModal(texto);
        setTimeout(() => inp?.focus(), 300);
    };
    elCodigo.addEventListener('hidden.bs.modal', whenHidden);
    bootstrap.Modal.getInstance(elCodigo)?.hide();
}

async function buscarProductosModal(term) {
    const tbody = document.getElementById('cuerpoBuscarProductoCod');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Buscando...</td></tr>';
    try {
        const res  = await fetch(appUrl + '/compras/codigos-proveedor/productos?q=' + encodeURIComponent(term));
        const json = await res.json();
        const list = json.productos || [];
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin resultados</td></tr>';
            return;
        }
        tbody.innerHTML = list.map((p, i) =>
            `<tr style="cursor:pointer" data-id="${p.inv_producto_id}"
                 data-nombre="${esc(p.inv_producto_nombre)}"
                 data-cod="${esc(p.codigo_interno||'')}">
                <td>${i + 1}</td>
                <td><strong>${esc(p.codigo_interno || '—')}</strong></td>
                <td>${esc(p.inv_producto_nombre)}</td>
                <td>${esc(p.cod_proveedor_existente || '—')}</td>
                <td>${esc(p.razon_social_existente || '—')}</td>
                <td class="text-center">${p.stock_total}</td>
            </tr>`
        ).join('');
    } catch {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al buscar</td></tr>';
    }
}

document.getElementById('buscarProductoCodInput')?.addEventListener('input', (e) => {
    clearTimeout(timerBuscProd);
    timerBuscProd = setTimeout(() => buscarProductosModal(e.target.value.trim()), 300);
});

document.getElementById('buscarProductoCodInput')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); buscarProductosModal(e.target.value.trim()); }
});

document.getElementById('cuerpoBuscarProductoCod')?.addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    seleccionarProducto(tr.dataset.id, tr.dataset.nombre, tr.dataset.cod);
});

// Al cerrar el modal de búsqueda, volver al modal de código
document.getElementById('modalBuscarProductoCod')?.addEventListener('hidden.bs.modal', () => {
    const modalCodigo = document.getElementById('modalCodigo');
    if (modalCodigo && !bootstrap.Modal.getInstance(modalCodigo)) {
        new bootstrap.Modal(modalCodigo).show();
    } else {
        bootstrap.Modal.getInstance(modalCodigo)?.show();
    }
});

// ── Abrir modal crear ────────────────────────────────────────────────────────
document.getElementById('btnNuevoCodigo')?.addEventListener('click', () => {
    document.getElementById('codigoId').value = '';
    document.getElementById('selectProveedor').value = '';
    document.getElementById('grupoProveedorSelect').classList.remove('d-none');
    document.getElementById('grupoProveedorTexto').classList.add('d-none');
    document.getElementById('inputCodigo').value = '';
    document.getElementById('alertaModalCodigo').innerHTML = '';
    document.getElementById('modalCodigoTitulo').textContent = 'Nuevo código';
    limpiarProducto();
    new bootstrap.Modal(document.getElementById('modalCodigo')).show();
});

// ── Abrir modal editar ───────────────────────────────────────────────────────
document.querySelectorAll('.btn-editar-codigo').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('codigoId').value = btn.dataset.id;
        document.getElementById('grupoProveedorSelect').classList.add('d-none');
        document.getElementById('grupoProveedorTexto').classList.remove('d-none');
        document.getElementById('proveedorTexto').value = btn.dataset.proveedorNombre;
        document.getElementById('inputCodigo').value    = btn.dataset.codigo;
        document.getElementById('alertaModalCodigo').innerHTML = '';
        document.getElementById('modalCodigoTitulo').textContent = 'Editar código';
        limpiarProducto();
        if (btn.dataset.productoId && btn.dataset.productoId !== '0') {
            seleccionarProducto(btn.dataset.productoId, btn.dataset.productoNombre, btn.dataset.codigoInterno);
        }
        new bootstrap.Modal(document.getElementById('modalCodigo')).show();
    });
});

// ── Guardar (crear/editar) ───────────────────────────────────────────────────
document.getElementById('btnGuardarCodigo')?.addEventListener('click', async () => {
    const id         = document.getElementById('codigoId').value;
    const esEditar   = !!id;
    const proveedorId = esEditar
        ? null
        : document.getElementById('selectProveedor').value;
    const codigo     = document.getElementById('inputCodigo').value.trim();
    const productoId = document.getElementById('inputProductoId').value;

    if (!esEditar && !proveedorId) { mostrarAlertaModal('warning', 'Seleccione un proveedor.'); return; }
    if (!codigo)     { mostrarAlertaModal('warning', 'Ingrese un código.'); return; }
    if (!productoId) { mostrarAlertaModal('warning', 'Seleccione un producto.'); return; }

    const fd = new FormData();
    if (esEditar) fd.append('codigo_id', id);
    else          fd.append('proveedor_id', proveedorId);
    fd.append('codigo',      codigo);
    fd.append('producto_id', productoId);

    const url = esEditar
        ? appUrl + '/compras/codigos-proveedor/editar'
        : appUrl + '/compras/codigos-proveedor/crear';

    try {
        const res  = await fetch(url, { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.ok) { mostrarAlertaModal('danger', json.mensaje); return; }
        bootstrap.Modal.getInstance(document.getElementById('modalCodigo'))?.hide();
        location.reload();
    } catch (err) {
        mostrarAlertaModal('danger', 'Error de conexión.');
    }
});

// ── Activar / Inactivar ──────────────────────────────────────────────────────
document.querySelectorAll('.btn-toggle-estado').forEach(btn => {
    btn.addEventListener('click', async () => {
        const accion = btn.dataset.accion;
        const id     = btn.dataset.id;
        const titulo = accion === 'inactivar' ? '¿Inactivar este código?' : '¿Activar este código?';
        const result = await Swal.fire({
            title: titulo, icon: 'question',
            showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'No',
            confirmButtonColor: accion === 'inactivar' ? '#6c757d' : '#198754',
        });
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('codigo_id', id);
        try {
            const res  = await fetch(appUrl + '/compras/codigos-proveedor/' + accion, { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) { mostrarAlertaCodigos('danger', json.mensaje); return; }
            location.reload();
        } catch {
            mostrarAlertaCodigos('danger', 'Error de conexión.');
        }
    });
});

// ── Eliminar permanente ──────────────────────────────────────────────────────
document.querySelectorAll('.btn-eliminar-codigo').forEach(btn => {
    btn.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: '¿Eliminar permanentemente?',
            text: `El código "${btn.dataset.codigo}" se eliminará sin posibilidad de recuperación.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('codigo_id', btn.dataset.id);
        try {
            const res  = await fetch(appUrl + '/compras/codigos-proveedor/eliminar', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) { mostrarAlertaCodigos('danger', json.mensaje); return; }
            btn.closest('tr')?.remove();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.mensaje, showConfirmButton: false, timer: 3000 });
        } catch {
            mostrarAlertaCodigos('danger', 'Error de conexión.');
        }
    });
});
</script>

<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
