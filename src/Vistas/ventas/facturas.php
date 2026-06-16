<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre    = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl       = rtrim($configuracion->obtener('APP_URL', ''), '/');
$estadoActual = $estadoFiltro ?? '';
$desdeFiltro  = $desdeFiltro  ?? '';
$hastaFiltro  = $hastaFiltro  ?? '';
$msgs         = $mensajesSistema ?? [];
$msgAnular    = $msgs['CONFIRMAR_ANULAR_FACTURA'] ?? null;
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Ventas</span><i class="bi bi-chevron-right"></i><strong>Facturas</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">

        <div class="panel-crud-cabecera d-flex align-items-center gap-2 flex-wrap">
            <?php if ($permisos['crear']): ?>
            <button type="button" class="btn btn-intesis btn-crud" onclick="window.location.href='<?= $appUrl ?>/ventas/facturas/nuevo'">
                <i class="bi bi-plus-square"></i> Nueva Factura
            </button>
            <?php endif; ?>

            <form method="get" action="<?= $appUrl ?>/ventas/facturas" class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label mb-0 small fw-semibold text-muted">Desde:</label>
                    <input type="date" name="desde" class="form-control form-control-sm" style="width:140px" value="<?= htmlspecialchars($desdeFiltro) ?>">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label mb-0 small fw-semibold text-muted">Hasta:</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" style="width:140px" value="<?= htmlspecialchars($hastaFiltro) ?>">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label mb-0 small fw-semibold text-muted">Estado:</label>
                    <select name="estado" class="form-select form-select-sm" style="width:150px">
                        <option value=""<?= $estadoActual === '' ? ' selected' : '' ?>>Todos</option>
                        <option value="CREADA"<?=     $estadoActual === 'CREADA'     ? ' selected' : '' ?>>Creada</option>
                        <option value="ANULADA"<?=    $estadoActual === 'ANULADA'    ? ' selected' : '' ?>>Anulada</option>
                        <option value="AUTORIZADA"<?= $estadoActual === 'AUTORIZADA' ? ' selected' : '' ?>>Autorizada</option>
                        <option value="ERROR"<?=      $estadoActual === 'ERROR'      ? ' selected' : '' ?>>Error</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secundario btn-sm"><i class="bi bi-search"></i> Filtrar</button>
            </form>
        </div>

        <div class="table-responsive">
            <table id="tablaFacturas" class="table table-hover tabla-intesis align-middle w-100">
                <thead><tr>
                    <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Forma pago</th>
                    <th class="text-end">Total</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($facturas as $fac): ?>
                <tr>
                    <?php if ($esSuperusuario): ?>
                    <td><?= htmlspecialchars($fac['sis_empresa_nombre_comercial']) ?></td>
                    <?php endif; ?>
                    <td class="font-monospace"><?= htmlspecialchars($fac['ven_documento_numero']) ?></td>
                    <td><?= htmlspecialchars(substr((string)($fac['ven_documento_fecha_emision'] ?? ''), 0, 10)) ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($fac['ven_cliente_razon_social']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($fac['ven_cliente_identificacion']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($fac['ven_forma_pago_nombre'] ?? '') ?></td>
                    <td class="text-end font-monospace">
                        $<?= number_format((float)$fac['ven_documento_total'], 2, '.', ',') ?>
                    </td>
                    <td>
                        <?php
                        $estadoCod = $fac['sis_estado_codigo'];
                        $claseEstado = match($estadoCod) {
                            'CREADA'     => 'estado-activo',
                            'AUTORIZADA' => 'estado-badge bg-primary text-white',
                            'ERROR'      => 'estado-badge bg-warning text-dark',
                            'ANULADA'    => 'estado-inactivo',
                            default      => 'estado-badge bg-secondary text-white',
                        };
                        ?>
                        <span class="badge <?= $claseEstado ?>"><?= htmlspecialchars($fac['sis_estado_nombre']) ?></span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <button type="button"
                                class="btn btn-sm btn-outline-secondary btn-ver-factura"
                                data-id="<?= $fac['ven_documento_id'] ?>"
                                data-numero="<?= htmlspecialchars($fac['ven_documento_numero']) ?>"
                                title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </button>
                            <a href="<?= $appUrl ?>/ventas/facturas/pdf?id=<?= $fac['ven_documento_id'] ?>"
                               class="btn btn-sm btn-outline-secondary" title="PDF" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            <?php if (($fac['sis_tipo_documento_codigo'] ?? $fac['tipo_nombre'] ?? '') !== 'NOTA_VENTA'): ?>
                                <?php if ($estadoCod === 'CREADA'): ?>
                                <button type="button"
                                    class="btn btn-sm btn-outline-primary btn-enviar-sri"
                                    data-id="<?= $fac['ven_documento_id'] ?>"
                                    data-numero="<?= htmlspecialchars($fac['ven_documento_numero']) ?>"
                                    title="Enviar al SRI">
                                    <i class="bi bi-send"></i>
                                </button>
                                <?php elseif ($estadoCod === 'ERROR'): ?>
                                <button type="button"
                                    class="btn btn-sm btn-outline-warning btn-enviar-sri"
                                    data-id="<?= $fac['ven_documento_id'] ?>"
                                    data-numero="<?= htmlspecialchars($fac['ven_documento_numero']) ?>"
                                    title="Reenviar al SRI">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($permisos['editar'] && $estadoCod === 'CREADA'): ?>
                            <a href="<?= $appUrl ?>/ventas/facturas/editar?id=<?= $fac['ven_documento_id'] ?>"
                               class="btn btn-sm btn-outline-secondary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($permisos['anular'] && $estadoCod === 'CREADA'): ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-danger btn-anular-factura"
                                data-id="<?= $fac['ven_documento_id'] ?>"
                                data-numero="<?= htmlspecialchars($fac['ven_documento_nombre'] ?? $fac['ven_documento_numero']) ?>"
                                title="Anular">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </section></div></div></main>
</div>

<!-- Modal Ver Detalle -->
<div class="modal fade modal-intesis" id="modalVerFactura" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <p class="modal-etiqueta mb-0">Ventas</p>
                    <h2 class="modal-title h6 mb-0">Detalle de Factura <span id="lblVerNumero" class="font-monospace text-muted ms-1"></span></h2>
                </div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2 px-3" id="cuerpoVerFactura">
                <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Cargando...</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const appUrl = <?= json_encode(rtrim($configuracion->obtener('APP_URL', ''), '/')) ?>;
    const msgAnular = <?= json_encode($msgAnular ?? ['sis_mensaje_errores_titulo' => 'Anular factura', 'sis_mensaje_errores_mensaje' => '¿Desea anular esta factura?', 'sis_mensaje_errores_icono' => 'warning']) ?>;

    try {
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#tablaFacturas').DataTable({
                language: { url: appUrl + '/publico/plugins/datatables/es-ES.json' },
                pageLength: 25,
                columnDefs: [{ orderable: false, targets: -1 }],
            });
        }
    } catch(ex) { console.warn('DataTables:', ex); }

    // ── Ver detalle ──────────────────────────────────────────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-ver-factura');
        if (!btn) return;
        const id     = parseInt(btn.dataset.id);
        const numero = btn.dataset.numero;
        document.getElementById('lblVerNumero').textContent = numero;
        document.getElementById('cuerpoVerFactura').innerHTML =
            '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Cargando...</div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVerFactura')).show();

        try {
            const resp = await fetch(appUrl + '/ventas/facturas/detalle?id=' + id);
            const data = await resp.json();
            if (!data.ok) {
                document.getElementById('cuerpoVerFactura').innerHTML = `<p class="text-danger">${data.mensaje}</p>`;
                return;
            }
            const fac = data.data.factura;
            const lineas = data.data.lineas;
            let html = `
            <div class="row g-2 mb-2" style="font-size:.82rem">
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <tr><td class="text-muted" style="width:130px">Cliente</td><td class="fw-semibold">${escHtml(fac.ven_cliente_razon_social)}</td></tr>
                        <tr><td class="text-muted">Identificación</td><td>${escHtml(fac.ven_cliente_identificacion)}</td></tr>
                        <tr><td class="text-muted">Fecha</td><td>${escHtml(String(fac.ven_documento_fecha_emision||'').substring(0,10))}</td></tr>
                        <tr><td class="text-muted">Forma de pago</td><td>${escHtml(fac.ven_forma_pago_nombre||'')}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <tr><td class="text-muted" style="width:130px">Número</td><td class="font-monospace">${escHtml(fac.ven_documento_numero)}</td></tr>
                        <tr><td class="text-muted">Estado</td><td>${escHtml(fac.sis_estado_nombre)}</td></tr>
                        <tr><td class="text-muted">Subtotal</td><td class="font-monospace">$${parseFloat(fac.ven_documento_subtotal).toFixed(2)}</td></tr>
                        <tr><td class="text-muted">IVA</td><td class="font-monospace">$${parseFloat(fac.ven_documento_iva).toFixed(2)}</td></tr>
                        <tr><td class="text-muted fw-bold">Total</td><td class="font-monospace fw-bold">$${parseFloat(fac.ven_documento_total).toFixed(2)}</td></tr>
                    </table>
                </div>
            </div>
            <table class="table table-sm table-bordered" style="font-size:.8rem">
                <thead class="table-light"><tr><th>Código</th><th>Descripción</th><th class="text-end">Cant.</th><th class="text-end">Precio</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
                <tbody>`;
            lineas.forEach(l => {
                const base  = parseFloat(l.ven_documento_detalle_precio_total_sin_impuestos || 0);
                const imp   = parseFloat(l.ven_documento_detalle_impuesto_total || 0);
                html += `<tr>
                    <td class="font-monospace">${escHtml(l.ven_documento_detalle_codigo||l.codigo_interno||'')}</td>
                    <td>${escHtml(l.ven_documento_detalle_descripcion||l.producto_nombre||'')}</td>
                    <td class="text-end">${parseFloat(l.ven_documento_detalle_cantidad).toFixed(2)}</td>
                    <td class="text-end font-monospace">$${parseFloat(l.ven_documento_detalle_precio_unitario||0).toFixed(4)}</td>
                    <td class="text-end font-monospace">$${imp.toFixed(2)}</td>
                    <td class="text-end font-monospace">$${(base+imp).toFixed(2)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            if (fac.ven_documento_observacion) {
                html += `<p class="text-muted small mb-0"><strong>Obs:</strong> ${escHtml(fac.ven_documento_observacion)}</p>`;
            }
            document.getElementById('cuerpoVerFactura').innerHTML = html;
        } catch {
            document.getElementById('cuerpoVerFactura').innerHTML = '<p class="text-danger">Error al cargar detalle.</p>';
        }
    });

    // ── Enviar / Reenviar al SRI ─────────────────────────────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-enviar-sri');
        if (!btn) return;
        const id     = parseInt(btn.dataset.id);
        const numero = btn.dataset.numero;
        const esReenvio = btn.classList.contains('btn-outline-warning');

        const result = await Swal.fire({
            title:             esReenvio ? 'Reenviar al SRI' : 'Enviar al SRI',
            text:              (esReenvio ? 'Reintentar envío de la factura ' : 'Enviar la factura ') + numero + ' al SRI Ecuador.',
            icon:              'question',
            showCancelButton:  true,
            confirmButtonText: esReenvio ? 'Sí, reenviar' : 'Sí, enviar',
            cancelButtonText:  'Cancelar',
            confirmButtonColor:'#1f6f68',
        });
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Procesando...', text: 'Firmando y enviando al SRI. Por favor espere.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const resp = await fetch(appUrl + '/ventas/facturas/enviar-sri', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ factura_id: id }),
            });
            const data = await resp.json();
            if (data.ok) {
                Swal.fire({ title: '¡Autorizada!', text: data.mensaje, icon: 'success' })
                    .then(() => location.reload());
            } else {
                Swal.fire({ title: 'Error SRI', text: data.mensaje, icon: 'error' });
            }
        } catch {
            Swal.fire({ title: 'Error', text: 'Error de conexión.', icon: 'error' });
        }
    });

    // ── Anular ───────────────────────────────────────────────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-anular-factura');
        if (!btn) return;
        const id     = parseInt(btn.dataset.id);
        const numero = btn.dataset.numero;

        const result = await Swal.fire({
            title:             msgAnular.sis_mensaje_errores_titulo,
            text:              msgAnular.sis_mensaje_errores_mensaje + ' ' + numero,
            icon:              msgAnular.sis_mensaje_errores_icono,
            showCancelButton:  true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText:  'Cancelar',
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(appUrl + '/ventas/facturas/anular', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ factura_id: id }),
            });
            const data = await resp.json();
            if (data.ok) {
                Swal.fire({ title: 'Anulada', text: data.mensaje, icon: 'success', timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ title: 'Error', text: data.mensaje, icon: 'error' });
            }
        } catch {
            Swal.fire({ title: 'Error', text: 'Error de conexión.', icon: 'error' });
        }
    });

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>

<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
