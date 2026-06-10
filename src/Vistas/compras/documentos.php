<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl    = rtrim($configuracion->obtener('APP_URL', ''), '/');
$estadoActual = $estadoFiltro ?? '';
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Compras</span><i class="bi bi-chevron-right"></i><strong>Documentos</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">

        <div class="panel-crud-cabecera d-flex align-items-center gap-2 flex-wrap">
            <?php if ($permisos['crear']): ?>
            <a href="<?= $appUrl ?>/compras/documentos/nuevo" class="btn btn-intesis btn-crud">
                <i class="bi bi-plus-square"></i> Nuevo documento
            </a>
            <?php endif; ?>

            <!-- Filtro por estado (server-side) -->
            <form method="get" action="<?= $appUrl ?>/compras/documentos" class="d-flex align-items-center gap-1 ms-auto">
                <label class="form-label mb-0 small fw-semibold text-muted">Estado:</label>
                <select name="estado" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
                    <option value=""<?= $estadoActual === '' ? ' selected' : '' ?>>Todos</option>
                    <option value="BORRADOR"<?= $estadoActual === 'BORRADOR' ? ' selected' : '' ?>>Borrador</option>
                    <option value="REGISTRADO"<?= $estadoActual === 'REGISTRADO' ? ' selected' : '' ?>>Registrado</option>
                    <option value="ANULADO"<?= $estadoActual === 'ANULADO' ? ' selected' : '' ?>>Anulado</option>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table id="tablaDocumentosCompra" class="table table-hover tabla-intesis align-middle w-100">
                <thead><tr>
                    <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                    <th>Tipo</th>
                    <th>Numero</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th class="text-end">Total</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($documentos as $doc): ?>
                <tr>
                    <?php if ($esSuperusuario): ?><td><?= htmlspecialchars($doc['sis_empresa_nombre_comercial']) ?></td><?php endif; ?>
                    <td><?= htmlspecialchars($doc['tipo_nombre']) ?></td>
                    <td><?= htmlspecialchars($doc['com_documento_numero']) ?></td>
                    <td><?= htmlspecialchars(substr((string) $doc['com_documento_fecha_emision'], 0, 10)) ?></td>
                    <td>
                        <span class="d-block"><?= htmlspecialchars($doc['com_proveedor_razon_social']) ?></span>
                        <small class="text-muted"><?= htmlspecialchars($doc['com_proveedor_identificacion']) ?></small>
                    </td>
                    <td class="text-end">$ <?= number_format((float) $doc['com_documento_valor_total'], 2) ?></td>
                    <td><span class="badge estado-badge estado-<?= strtolower((string) $doc['sis_estado_codigo']) ?>"><?= htmlspecialchars($doc['sis_estado_nombre']) ?></span></td>
                    <td class="text-end acciones-tabla">
                        <!-- Ver detalle: pasa estado para que el modal sepa si mostrar Confirmar Compra -->
                        <button type="button" class="btn btn-accion btn-ver-documento" title="Ver detalle"
                            data-id="<?= (int) $doc['com_documento_id'] ?>"
                            data-estado="<?= htmlspecialchars($doc['sis_estado_codigo']) ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($doc['sis_estado_codigo'] === 'BORRADOR' && $permisos['editar']): ?>
                        <a href="<?= $appUrl ?>/compras/documentos/editar?id=<?= (int) $doc['com_documento_id'] ?>"
                           class="btn btn-accion btn-editar" title="Editar documento">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($doc['sis_estado_codigo'] === 'BORRADOR' && $permisos['anular']): ?>
                        <form action="<?= $appUrl ?>/compras/documentos/anular" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_ANULAR_DOCUMENTO">
                            <input type="hidden" name="documento_id" value="<?= (int) $doc['com_documento_id'] ?>">
                            <button class="btn btn-accion btn-inactivar" type="submit" title="Anular documento"><i class="bi bi-x-circle"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section></div></div></main>
</div>

<!-- MODAL DETALLE DOCUMENTO -->
<div class="modal fade modal-intesis" id="modalDetalleDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras</p><h2 class="modal-title">Detalle del documento</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoDetalleDocumento">
                <div class="text-center py-4"><div class="spinner-border text-secondary" role="status"></div></div>
            </div>
            <div class="modal-footer" id="footerDetalleDocumento" style="display:none!important">
                <?php if ($permisos['registrar']): ?>
                <button type="button" id="btnConfirmarCompra" class="btn btn-intesis btn-sm">
                    <i class="bi bi-check2-circle me-1"></i>Confirmar Compra
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
(function () {
    'use strict';
    const appUrl       = '<?= $appUrl ?>';
    const puedeRegistrar = <?= $permisos['registrar'] ? 'true' : 'false' ?>;
    let documentoIdActual = 0;

    /* ── Abrir modal de detalle ──────────────────────────────────────────────── */
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-ver-documento');
        if (!btn) return;

        const id     = parseInt(btn.dataset.id, 10);
        const estado = btn.dataset.estado || '';
        documentoIdActual = id;

        const modal  = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleDocumento'));
        const cuerpo = document.getElementById('cuerpoDetalleDocumento');
        const footer = document.getElementById('footerDetalleDocumento');
        const btnConf = document.getElementById('btnConfirmarCompra');

        cuerpo.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-secondary" role="status"></div></div>';

        // Mostrar / ocultar footer con botón Confirmar Compra
        if (puedeRegistrar && estado === 'BORRADOR') {
            footer.style.removeProperty('display');
            if (btnConf) {
                btnConf.disabled = false;
                btnConf.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirmar Compra';
            }
        } else {
            footer.style.setProperty('display', 'none', 'important');
        }

        modal.show();

        try {
            const res  = await fetch(appUrl + '/compras/documentos/detalle?documento_id=' + id);
            const json = await res.json();
            if (!json.ok) throw new Error(json.mensaje || 'Error');
            const d      = json.documento;
            const lineas = json.lineas;

            const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

            let html = `
            <div class="row g-2 mb-3 formulario-compacto">
                <div class="col-md-3"><label class="form-label">Tipo</label><input class="form-control form-control-sm" value="${esc(d.tipo_nombre)}" disabled></div>
                <div class="col-md-3"><label class="form-label">Numero</label><input class="form-control form-control-sm" value="${esc(d.com_documento_numero)}" disabled></div>
                <div class="col-md-3"><label class="form-label">Fecha emision</label><input class="form-control form-control-sm" value="${String(d.com_documento_fecha_emision).substring(0,10)}" disabled></div>
                <div class="col-md-3"><label class="form-label">Estado</label><input class="form-control form-control-sm" value="${esc(d.sis_estado_nombre)}" disabled></div>
                <div class="col-md-6"><label class="form-label">Proveedor</label><input class="form-control form-control-sm" value="${esc(d.com_proveedor_razon_social)} (${esc(d.com_proveedor_identificacion)})" disabled></div>
                <div class="col-md-3"><label class="form-label">Bodega</label><input class="form-control form-control-sm" value="${esc(d.inv_bodega_nombre)}" disabled></div>
                ${d.com_documento_observacion ? `<div class="col-12"><label class="form-label">Observacion</label><input class="form-control form-control-sm" value="${esc(d.com_documento_observacion)}" disabled></div>` : ''}
            </div>
            <div class="table-responsive">
            <table class="table table-sm tabla-intesis">
                <thead><tr><th>#</th><th>Cod. Proveedor</th><th>Codigo</th><th>Descripcion</th><th>Marca</th>
                    <th class="text-end">Cant.</th><th class="text-end">Costo</th>
                    <th class="text-center">IVA %</th><th class="text-end">PVP</th><th class="text-end">Total</th>
                </tr></thead>
                <tbody>`;
            lineas.forEach((l, i) => {
                html += `<tr>
                    <td>${i+1}</td>
                    <td>${esc(l.cod_proveedor) || '—'}</td>
                    <td>${esc(l.codigo_interno)}</td>
                    <td>${esc(l.producto_nombre)}</td>
                    <td>${esc(l.marca_nombre) || '—'}</td>
                    <td class="text-end">${parseFloat(l.com_documento_detalle_cantidad).toFixed(2)}</td>
                    <td class="text-end">${parseFloat(l.com_documento_detalle_precio).toFixed(4)}</td>
                    <td class="text-center">${l.sis_iva_valor !== null ? parseFloat(l.sis_iva_valor).toFixed(0)+'%' : '—'}</td>
                    <td class="text-end">${parseFloat(l.com_documento_detalle_pvp).toFixed(2)}</td>
                    <td class="text-end">${parseFloat(l.com_documento_detalle_total).toFixed(2)}</td>
                </tr>`;
            });
            html += `</tbody></table></div>
            <div class="row justify-content-end mt-2 formulario-compacto">
                <div class="col-md-4">
                    <table class="table table-sm mb-0">
                        <tr><td>Subtotal</td><td class="text-end">$ ${parseFloat(d.com_documento_subtotal).toFixed(2)}</td></tr>
                        <tr><td>Descuento</td><td class="text-end">$ ${parseFloat(d.com_documento_descuento).toFixed(2)}</td></tr>
                        <tr><td>IVA</td><td class="text-end">$ ${parseFloat(d.com_documento_iva).toFixed(2)}</td></tr>
                        <tr class="fw-bold"><td>Total</td><td class="text-end">$ ${parseFloat(d.com_documento_valor_total).toFixed(2)}</td></tr>
                    </table>
                </div>
            </div>`;
            cuerpo.innerHTML = html;
        } catch (err) {
            cuerpo.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
        }
    });

    /* ── Confirmar Compra ───────────────────────────────────────────────────── */
    const btnConf = document.getElementById('btnConfirmarCompra');
    if (btnConf) {
        btnConf.addEventListener('click', async () => {
            if (!documentoIdActual) return;
            btnConf.disabled = true;
            btnConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';

            try {
                const fd = new FormData();
                fd.append('documento_id', documentoIdActual);
                const res  = await fetch(appUrl + '/compras/documentos/registrar', { method: 'POST', body: fd });
                const json = await res.json();

                if (!json.ok) throw new Error(json.mensaje || 'Error al registrar');

                // Cerrar modal y recargar la página conservando el filtro de estado actual
                bootstrap.Modal.getInstance(document.getElementById('modalDetalleDocumento'))?.hide();
                window.location.reload();
            } catch (err) {
                btnConf.disabled = false;
                btnConf.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirmar Compra';
                const cuerpo = document.getElementById('cuerpoDetalleDocumento');
                cuerpo.insertAdjacentHTML('afterbegin', `<div class="alert alert-danger alert-dismissible mb-2">
                    <strong>Error:</strong> ${err.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`);
            }
        });
    }

    /* ── Reset al cerrar modal ──────────────────────────────────────────────── */
    document.getElementById('modalDetalleDocumento').addEventListener('hidden.bs.modal', () => {
        documentoIdActual = 0;
        const footer = document.getElementById('footerDetalleDocumento');
        footer.style.setProperty('display', 'none', 'important');
    });
}());
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
