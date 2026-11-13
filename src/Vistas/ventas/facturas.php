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
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Ventas</span><i class="bi bi-chevron-right"></i><strong>Facturas</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars((($usuario['bodega'] ?? null) ? $usuario['bodega'] . ' - ' : '') . $usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">

        <div class="panel-crud-cabecera d-flex align-items-center gap-2 flex-wrap">
            <?php if ($permisos['crear']): ?>
            <button type="button" class="btn btn-intesis btn-crud" onclick="window.location.href='<?= $appUrl ?>/ventas/facturas/nuevo'">
                <i class="bi bi-plus-square"></i> Nueva Factura
            </button>
            <?php endif; ?>

            <button type="button" id="btnEnviarLoteSri" class="btn btn-outline-primary btn-sm d-none">
                <i class="bi bi-send"></i> Enviar <span id="lblLoteCount">0</span> al SRI
            </button>

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
                    <th style="width:30px"><input type="checkbox" id="chkTodosSri" title="Seleccionar todas"></th>
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
                <?php
                    $estadoCodFila   = $fac['sis_estado_codigo'];
                    $esFacturaVenta  = ($fac['sis_tipo_documento_codigo'] ?? $fac['tipo_nombre'] ?? '') !== 'NOTA_VENTA';
                    $elegibleSri     = $esFacturaVenta && in_array($estadoCodFila, ['CREADA', 'ERROR'], true);
                ?>
                <tr>
                    <td>
                        <?php if ($elegibleSri): ?>
                        <input type="checkbox" class="chk-factura-sri"
                            data-id="<?= $fac['ven_documento_id'] ?>"
                            data-numero="<?= htmlspecialchars($fac['ven_documento_numero']) ?>">
                        <?php endif; ?>
                    </td>
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
                        $estadoCod = $estadoCodFila;
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
                            <?php if ($permisos['adjuntos_ver'] ?? false): ?>
                            <?php $totalAdjuntos = (int) ($fac['adjuntos_total'] ?? 0); ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-secondary btn-adjuntos-factura position-relative"
                                data-id="<?= $fac['ven_documento_id'] ?>"
                                data-numero="<?= htmlspecialchars($fac['ven_documento_numero']) ?>"
                                title="Adjuntos">
                                <i class="bi bi-paperclip"></i>
                                <span class="contador-adjuntos<?= $totalAdjuntos ? '' : ' d-none' ?>"><?= $totalAdjuntos ?></span>
                            </button>
                            <?php endif; ?>
                            <?php if ($esFacturaVenta): ?>
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

<!-- Modal Adjuntos -->
<div class="modal fade modal-intesis" id="modalAdjuntos" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <p class="modal-etiqueta mb-0">Ventas</p>
                    <h2 class="modal-title h6 mb-0">Adjuntos de la factura <span id="lblAdjNumero" class="font-monospace text-muted ms-1"></span></h2>
                </div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0 adjuntos-cuerpo">
                    <div class="col-lg-5 p-3 adjuntos-lateral">
                        <?php if ($permisos['adjuntos_subir'] ?? false): ?>
                        <label class="zona-subida" id="zonaAdjuntos">
                            <input type="file" id="inpAdjuntos" multiple hidden
                                accept="application/pdf,image/jpeg,image/png,image/webp,image/gif">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <span class="fw-semibold">Arrastre archivos aquí</span>
                            <span class="small">o <u>seleccione desde su equipo</u></span>
                            <span class="small text-muted">PDF o imágenes (JPG, PNG, WEBP, GIF), hasta <span id="lblAdjMax">80</span> MB cada uno</span>
                        </label>
                        <?php endif; ?>
                        <div id="colaAdjuntos" class="mt-2"></div>
                        <div id="listaAdjuntos" class="mt-2"></div>
                    </div>
                    <div class="col-lg-7 visor-adjuntos" id="visorAdjuntos">
                        <p class="text-muted small m-0">Elija un adjunto de la lista para verlo aquí.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Adjuntos: el unico acento es la zona de arrastre y la fila elegida (color corporativo) */
.btn-adjuntos-factura .contador-adjuntos {
    position: absolute; top: -6px; right: -6px; min-width: 16px; height: 16px; padding: 0 4px;
    border-radius: 8px; background: #1f6f68; color: #fff; font-size: .62rem; line-height: 16px;
}
.adjuntos-cuerpo { min-height: 65vh; }
.adjuntos-lateral { border-right: 1px solid #dee2e6; max-height: 75vh; overflow-y: auto; }
.adjunto-fila {
    display: flex; align-items: center; gap: .5rem; padding: .35rem .4rem;
    border-left: 3px solid transparent; border-radius: 4px; cursor: pointer;
}
.adjunto-fila:hover { background: #f4f6f6; }
.adjunto-fila.activa { border-left-color: #1f6f68; background: #e6f1f0; }
.adjunto-fila input { font-size: .82rem; }
.adjunto-fila .tamano { font-size: .72rem; color: #6c757d; white-space: nowrap; }
.visor-adjuntos {
    display: flex; align-items: center; justify-content: center;
    background: #eef1f1; min-height: 65vh; padding: .75rem;
}
.visor-adjuntos iframe { width: 100%; height: 72vh; border: 0; background: #fff; }
.visor-adjuntos img { max-width: 100%; max-height: 72vh; object-fit: contain; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
@media (max-width: 991.98px) {
    .adjuntos-lateral { border-right: 0; border-bottom: 1px solid #dee2e6; max-height: none; }
    .visor-adjuntos, .adjuntos-cuerpo { min-height: 50vh; }
}
</style>

<script src="<?= $appUrl ?>/publico/js/subida-archivos.js?v=<?= filemtime($configuracion->raiz() . '/publico/js/subida-archivos.js') ?>"></script>
<script>
(function () {
    'use strict';
    const appUrl = <?= json_encode(rtrim($configuracion->obtener('APP_URL', ''), '/')) ?>;
    const permisosAdjuntos = <?= json_encode([
        'subir'    => (bool) ($permisos['adjuntos_subir'] ?? false),
        'editar'   => (bool) ($permisos['adjuntos_editar'] ?? false),
        'eliminar' => (bool) ($permisos['adjuntos_eliminar'] ?? false),
    ]) ?>;
    const msgAnular = <?= json_encode($msgAnular ?? ['sis_mensaje_errores_titulo' => 'Anular factura', 'sis_mensaje_errores_mensaje' => '¿Desea anular esta factura?', 'sis_mensaje_errores_icono' => 'warning']) ?>;

    try {
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#tablaFacturas').DataTable({
                language: { url: appUrl + '/publico/plugins/datatables/es-ES.json' },
                pageLength: 25,
                columnDefs: [{ orderable: false, searchable: false, targets: 0 }, { orderable: false, targets: -1 }],
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
                        <tr><td class="text-muted">Estado</td><td>${fac.sis_estado_codigo === 'ANULADA' ? `<span class="fw-bold text-danger">${escHtml(fac.sis_estado_nombre)}</span>` : escHtml(fac.sis_estado_nombre)}</td></tr>
                        ${fac.sis_estado_codigo === 'ANULADA' ? `<tr><td class="text-muted">Motivo</td><td class="text-danger">${escHtml(fac.ven_documento_motivo_anulacion||'')}</td></tr>` : ''}
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

    // ── Enviar al SRI por lote ──────────────────────────────────────────────
    function actualizarBotonLoteSri() {
        const marcados = document.querySelectorAll('.chk-factura-sri:checked').length;
        document.getElementById('lblLoteCount').textContent = marcados;
        document.getElementById('btnEnviarLoteSri').classList.toggle('d-none', marcados === 0);
    }

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('chk-factura-sri')) {
            actualizarBotonLoteSri();
        } else if (e.target.id === 'chkTodosSri') {
            document.querySelectorAll('.chk-factura-sri').forEach((chk) => {
                if (chk.closest('tr').offsetParent !== null) chk.checked = e.target.checked;
            });
            actualizarBotonLoteSri();
        }
    });

    document.getElementById('btnEnviarLoteSri').addEventListener('click', async () => {
        const seleccion = Array.from(document.querySelectorAll('.chk-factura-sri:checked'))
            .map((chk) => ({ id: parseInt(chk.dataset.id), numero: chk.dataset.numero }));
        if (seleccion.length === 0) return;

        const result = await Swal.fire({
            title:              'Enviar al SRI',
            text:               `¿Enviar ${seleccion.length} factura(s) seleccionada(s) al SRI Ecuador?`,
            icon:               'question',
            showCancelButton:   true,
            confirmButtonText:  'Sí, enviar',
            cancelButtonText:   'Cancelar',
            confirmButtonColor: '#1f6f68',
        });
        if (!result.isConfirmed) return;

        const okList = [];
        const errList = [];
        for (let i = 0; i < seleccion.length; i++) {
            const f = seleccion[i];
            Swal.fire({
                title:            'Procesando...',
                text:             `Enviando ${i + 1} de ${seleccion.length}... (Factura ${f.numero})`,
                allowOutsideClick: false,
                allowEscapeKey:    false,
                didOpen:           () => Swal.showLoading(),
            });
            try {
                const resp = await fetch(appUrl + '/ventas/facturas/enviar-sri', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ factura_id: f.id }),
                });
                const data = await resp.json();
                if (data.ok) {
                    okList.push(f.numero);
                } else {
                    errList.push({ numero: f.numero, mensaje: data.mensaje });
                }
            } catch {
                errList.push({ numero: f.numero, mensaje: 'Error de conexión.' });
            }
        }

        let html = `<p>${okList.length} autorizada(s), ${errList.length} con error.</p>`;
        if (errList.length) {
            html += '<ul class="text-start small text-danger mb-0">' +
                errList.map((e) => `<li><strong>${escHtml(e.numero)}</strong> — ${escHtml(e.mensaje)}</li>`).join('') +
                '</ul>';
        }
        Swal.fire({
            title: 'Proceso terminado',
            html,
            icon:  errList.length ? 'warning' : 'success',
        }).then(() => location.reload());
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
            input:             'text',
            inputLabel:        'Motivo',
            inputPlaceholder:  'Escriba el motivo de anulación...',
            inputValidator:    (valor) => !valor?.trim() ? 'El motivo es obligatorio.' : undefined,
            showCancelButton:  true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText:  'Cancelar',
        });
        if (!result.isConfirmed) return;
        const motivo = result.value.trim();

        try {
            const resp = await fetch(appUrl + '/ventas/facturas/anular', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ factura_id: id, motivo }),
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

    // ── Adjuntos ─────────────────────────────────────────────────────────────
    // La subida (cola, % por archivo, validacion previa) es la comun: publico/js/subida-archivos.js
    const MIMES_ADJUNTOS = <?= json_encode(\Intesis\Servicios\AlmacenArchivosServicio::TIPOS_PDF_E_IMAGENES) ?>;
    const adj = { facturaId: 0, boton: null, lista: [], activoId: 0, maxMb: 80 };
    const q = (sel) => document.querySelector(sel);
    const tamanoLegible = IntesisSubida.tamanoLegible;

    function urlAdjunto(id) {
        return appUrl + '/ventas/facturas/adjuntos/ver?archivo_id=' + encodeURIComponent(id);
    }

    function actualizarContador() {
        const span = adj.boton?.querySelector('.contador-adjuntos');
        if (!span) return;
        span.textContent = adj.lista.length;
        span.classList.toggle('d-none', adj.lista.length === 0);
    }

    function renderListaAdjuntos() {
        const cont = q('#listaAdjuntos');
        if (!adj.lista.length) {
            cont.innerHTML = '<p class="text-muted small mb-0">Esta factura no tiene adjuntos.</p>';
            return;
        }
        cont.innerHTML = adj.lista.map(a => `
            <div class="adjunto-fila${a.sis_archivos_id === adj.activoId ? ' activa' : ''}" data-id="${a.sis_archivos_id}">
                <i class="bi ${a.sis_archivos_mime === 'application/pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary'}"></i>
                <input type="text" class="form-control form-control-sm adj-nombre" maxlength="150"
                    value="${escHtml(a.sis_archivos_nombre)}" aria-label="Nombre del adjunto" ${permisosAdjuntos.editar ? '' : 'readonly'}>
                <span class="tamano">${tamanoLegible(a.sis_archivos_tamano)}</span>
                ${permisosAdjuntos.eliminar ? '<button type="button" class="btn btn-sm btn-outline-danger adj-eliminar" title="Eliminar adjunto"><i class="bi bi-trash"></i></button>' : ''}
            </div>`).join('');
    }

    function mostrarEnVisor(id) {
        const a = adj.lista.find(x => x.sis_archivos_id === id);
        const visor = q('#visorAdjuntos');
        adj.activoId = a ? id : 0;
        renderListaAdjuntos();
        visor.innerHTML = htmlVisor(a);
    }

    function htmlVisor(a) {
        if (!a) return '<p class="text-muted small m-0">Elija un adjunto de la lista para verlo aquí.</p>';
        return a.sis_archivos_mime === 'application/pdf'
            ? `<iframe src="${urlAdjunto(a.sis_archivos_id)}" title="${escHtml(a.sis_archivos_nombre)}"></iframe>`
            : `<img src="${urlAdjunto(a.sis_archivos_id)}" alt="${escHtml(a.sis_archivos_nombre)}">`;
    }

    async function cargarAdjuntos() {
        q('#listaAdjuntos').innerHTML = '<p class="text-muted small mb-0"><span class="spinner-border spinner-border-sm me-1"></span>Cargando adjuntos...</p>';
        try {
            const resp = await fetch(appUrl + '/ventas/facturas/adjuntos?factura_id=' + adj.facturaId);
            const json = await resp.json();
            if (!json.ok) throw new Error(json.mensaje);
            adj.lista  = (json.data.adjuntos || []).map(a => ({ ...a, sis_archivos_id: Number(a.sis_archivos_id) }));
            adj.maxMb  = Number(json.data.max_mb) || 80;
            if (q('#lblAdjMax')) q('#lblAdjMax').textContent = adj.maxMb;
            mostrarEnVisor(adj.lista[0]?.sis_archivos_id || 0);
            actualizarContador();
        } catch (error) {
            q('#listaAdjuntos').innerHTML = `<p class="text-danger small mb-0">${escHtml(error.message || 'No se pudieron cargar los adjuntos.')}</p>`;
        }
    }

    function agregarArchivos(archivos) {
        IntesisSubida.subirArchivos(archivos, {
            url: appUrl + '/ventas/facturas/adjuntos/subir',
            campos: { factura_id: adj.facturaId },
            contenedorCola: q('#colaAdjuntos'),
            reglas: { maxMb: adj.maxMb, mimes: MIMES_ADJUNTOS },
            alSubir: (json) => {
                const nuevo = { ...json.data.adjunto, sis_archivos_id: Number(json.data.adjunto.sis_archivos_id) };
                adj.lista.push(nuevo);
                mostrarEnVisor(nuevo.sis_archivos_id);
                actualizarContador();
            },
        });
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-adjuntos-factura');
        if (!btn) return;
        adj.facturaId = parseInt(btn.dataset.id);
        adj.boton = btn;
        adj.lista = [];
        q('#lblAdjNumero').textContent = btn.dataset.numero;
        q('#colaAdjuntos').innerHTML = '';
        bootstrap.Modal.getOrCreateInstance(q('#modalAdjuntos')).show();
        cargarAdjuntos();
    });

    if (q('#zonaAdjuntos')) IntesisSubida.conectarZona(q('#zonaAdjuntos'), q('#inpAdjuntos'), agregarArchivos);

    q('#listaAdjuntos').addEventListener('click', async (e) => {
        const fila = e.target.closest('.adjunto-fila');
        if (!fila) return;
        const id = Number(fila.dataset.id);
        if (e.target.closest('.adj-eliminar')) {
            const conf = await Swal.fire({
                icon: 'warning', title: '¿Eliminar adjunto?', text: 'Dejará de aparecer en esta factura.',
                showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
            });
            if (!conf.isConfirmed) return;
            const datos = new FormData();
            datos.append('archivo_id', id);
            const json = await (await fetch(appUrl + '/ventas/facturas/adjuntos/eliminar', { method: 'POST', body: datos })).json();
            if (!json.ok) { Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: json.mensaje }); return; }
            adj.lista = adj.lista.filter(a => a.sis_archivos_id !== id);
            mostrarEnVisor(adj.activoId === id ? (adj.lista[0]?.sis_archivos_id || 0) : adj.activoId);
            actualizarContador();
            return;
        }
        if (id !== adj.activoId && !e.target.closest('.adj-nombre')) mostrarEnVisor(id);
    });

    // El nombre se guarda al salir del campo (o con Enter) solo si cambio
    q('#listaAdjuntos').addEventListener('change', async (e) => {
        if (!e.target.matches('.adj-nombre')) return;
        const id = Number(e.target.closest('.adjunto-fila').dataset.id);
        const a = adj.lista.find(x => x.sis_archivos_id === id);
        const nombre = e.target.value.trim();
        if (!a || !nombre) { e.target.value = a?.sis_archivos_nombre || ''; return; }
        const datos = new FormData();
        datos.append('archivo_id', id);
        datos.append('nombre', nombre);
        const json = await (await fetch(appUrl + '/ventas/facturas/adjuntos/editar', { method: 'POST', body: datos })).json();
        if (!json.ok) { Swal.fire({ icon: 'error', title: 'No se pudo renombrar', text: json.mensaje }); e.target.value = a.sis_archivos_nombre; return; }
        a.sis_archivos_nombre = json.data.nombre;
    });
    q('#listaAdjuntos').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('.adj-nombre')) { e.preventDefault(); e.target.blur(); }
    });
    q('#listaAdjuntos').addEventListener('focusin', (e) => {
        const fila = e.target.closest('.adjunto-fila');
        if (fila && e.target.matches('.adj-nombre') && Number(fila.dataset.id) !== adj.activoId) {
            // Ver el adjunto al editar su nombre sin redibujar la lista (perderia el foco)
            adj.activoId = Number(fila.dataset.id);
            q('#listaAdjuntos').querySelectorAll('.adjunto-fila').forEach(f => f.classList.toggle('activa', f === fila));
            q('#visorAdjuntos').innerHTML = htmlVisor(adj.lista.find(x => x.sis_archivos_id === adj.activoId));
        }
    });
})();
</script>

<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
