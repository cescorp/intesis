<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre   = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl      = rtrim($configuracion->obtener('APP_URL', ''), '/');
$estadoActual = $estadoFiltro ?? '';
$pendSri     = $pendientesSri ?? [];
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Compras</span><i class="bi bi-chevron-right"></i><strong>Documentos</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">

        <div class="panel-crud-cabecera d-flex align-items-center gap-2 flex-wrap">
            <?php if ($permisos['crear']): ?>
            <button type="button" class="btn btn-intesis btn-crud" id="btnNuevaCompra">
                <i class="bi bi-plus-square"></i> Nueva Compra
            </button>
            <?php endif; ?>

            <?php if ($permisos['subir_xml'] ?? false): ?>
            <button type="button" class="btn btn-outline-primary btn-crud" id="btnAbrirSubirXml">
                <i class="bi bi-file-earmark-code"></i> Subir XML
            </button>
            <?php endif; ?>

            <?php if ($permisos['procesar_txt'] ?? false): ?>
            <button type="button" class="btn btn-outline-secondary btn-crud" id="btnAbrirProcesarTxt">
                <i class="bi bi-file-earmark-text"></i> Procesar TXT
            </button>
            <?php endif; ?>

            <?php if (!empty($pendSri)): ?>
            <button type="button" class="btn btn-warning btn-crud" id="btnPendientesSri">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Pendientes SRI (<?= count($pendSri) ?> proveedor<?= count($pendSri) !== 1 ? 'es' : '' ?>)
            </button>
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
                <?php $esSri = ($doc['com_documento_origen'] ?? 'MANUAL') === 'SRI'; ?>
                <tr>
                    <?php if ($esSuperusuario): ?><td><?= htmlspecialchars($doc['sis_empresa_nombre_comercial']) ?></td><?php endif; ?>
                    <td>
                        <?= htmlspecialchars($doc['tipo_nombre']) ?>
                        <?php if ($esSri): ?>
                        <span class="badge bg-info text-dark ms-1" title="Importado desde SRI"><i class="bi bi-cloud-download"></i> SRI</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($doc['com_documento_numero']) ?></td>
                    <td><?= htmlspecialchars(substr((string) $doc['com_documento_fecha_emision'], 0, 10)) ?></td>
                    <td>
                        <span class="d-block"><?= htmlspecialchars($doc['com_proveedor_razon_social']) ?></span>
                        <small class="text-muted"><?= htmlspecialchars($doc['com_proveedor_identificacion']) ?></small>
                    </td>
                    <td class="text-end">$ <?= number_format((float) $doc['com_documento_valor_total'], 2) ?></td>
                    <td><span class="badge estado-badge estado-<?= strtolower((string) $doc['sis_estado_codigo']) ?>"><?= htmlspecialchars($doc['sis_estado_nombre']) ?></span></td>
                    <td class="text-end acciones-tabla">
                        <button type="button" class="btn btn-accion btn-ver-documento" title="Ver detalle"
                            data-id="<?= (int) $doc['com_documento_id'] ?>"
                            data-estado="<?= htmlspecialchars($doc['sis_estado_codigo']) ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($doc['sis_estado_codigo'] === 'BORRADOR' && $permisos['editar'] && !$esSri): ?>
                        <a href="<?= $appUrl ?>/compras/documentos/editar?id=<?= (int) $doc['com_documento_id'] ?>"
                           class="btn btn-accion btn-editar" title="Editar documento">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-accion btn-pdf-documento" title="Vista PDF"
                            data-id="<?= (int) $doc['com_documento_id'] ?>"
                            data-numero="<?= htmlspecialchars($doc['com_documento_numero']) ?>">
                            <i class="bi bi-file-pdf"></i>
                        </button>
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

<!-- ============================================================
     MODAL PDF DOCUMENTO
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalPdfDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="height:90vh">
            <div class="modal-header py-2">
                <div>
                    <p class="modal-etiqueta mb-0">Compras</p>
                    <h2 class="modal-title h6 mb-0" id="modalPdfDocumentoTitulo">Vista Previa PDF</h2>
                </div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="flex:1;overflow:hidden">
                <iframe id="iframePdfDocumento" src="" style="width:100%;height:100%;border:0"
                    title="Vista previa del PDF"></iframe>
            </div>
            <div class="modal-footer py-2 gap-2">
                <a id="btnDescPdf" href="#" target="_blank" class="btn btn-sm btn-intesis">
                    <i class="bi bi-file-pdf me-1"></i>Descargar PDF
                </a>
                <button type="button" id="btnDescXml" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-file-earmark-code me-1"></i>Descargar XML
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL DETALLE DOCUMENTO
     ============================================================ -->
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
                <?php if ($permisos['anular']): ?>
                <button type="button" id="btnAnularModal" class="btn btn-sm btn-secundario">
                    <i class="bi bi-x-circle me-1"></i>Anular
                </button>
                <?php endif; ?>
                <?php if ($permisos['registrar']): ?>
                <button type="button" id="btnConfirmarCompra" class="btn btn-intesis btn-sm">
                    <i class="bi bi-check2-circle me-1"></i>Confirmar Compra
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL SUBIR XML
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalSubirXml" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras · SRI</p><h2 class="modal-title">Subir XML SRI</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="alertaSubirXml"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Bodega destino <span class="text-danger">*</span></label>
                    <?php
                    $bodegaDefaultXml = 0;
                    foreach ($bodegas ?? [] as $b) { if (!empty($b['predeterminada'])) { $bodegaDefaultXml = (int) $b['inv_bodega_id']; break; } }
                    if (!$bodegaDefaultXml) foreach ($bodegas ?? [] as $b) { if (!empty($b['inv_bodega_es_principal'])) { $bodegaDefaultXml = (int) $b['inv_bodega_id']; break; } }
                    ?>
                    <select id="bodegaSubirXml" class="form-select">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($bodegas ?? [] as $b): ?>
                        <option value="<?= (int) $b['inv_bodega_id'] ?>" <?= (int) $b['inv_bodega_id'] === $bodegaDefaultXml ? 'selected' : '' ?>><?= htmlspecialchars($b['inv_bodega_nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Archivo XML <span class="text-danger">*</span></label>
                    <input type="file" id="archivoXml" class="form-control" accept=".xml">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-intesis btn-sm" id="btnProcesarXml">
                    <i class="bi bi-upload me-1"></i>Procesar XML
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL PREVIEW XML (previsualización antes de guardar)
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalPreviewXml" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras · SRI</p><h2 class="modal-title">Vista previa — Factura SRI</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoPreviewXml"></div>
            <div class="modal-footer">
                <div id="alertaPreviewXml" class="me-auto"></div>
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-intesis btn-sm" id="btnGuardarSriUno" disabled>
                    <i class="bi bi-save me-1"></i>Guardar como Borrador
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL ASIGNAR CÓDIGOS (por proveedor, persiste entre cierres)
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalAsignarCodigos" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras · SRI</p><h2 class="modal-title">Asignar productos a códigos proveedor</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Asigna cada código del proveedor a un producto del inventario. Los cambios se guardan inmediatamente. Puedes cerrar y reabrir sin perder avance.
                </p>
                <div id="alertaAsignarCodigos"></div>
                <div id="tablaAsignarCodigos"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-danger" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-intesis btn-sm" id="btnContinuarTrasAsignar" disabled>
                    <i class="bi bi-check2 me-1"></i>Continuar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL PROCESAR TXT
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalProcesarTxt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras · SRI</p><h2 class="modal-title">Procesar TXT SRI</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="alertaProcesarTxt"></div>
                <div id="formProcesarTxt">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bodega destino <span class="text-danger">*</span></label>
                        <?php
                        $bodegaDefaultTxt = 0;
                        foreach ($bodegas ?? [] as $b) { if (!empty($b['predeterminada'])) { $bodegaDefaultTxt = (int) $b['inv_bodega_id']; break; } }
                        if (!$bodegaDefaultTxt) foreach ($bodegas ?? [] as $b) { if (!empty($b['inv_bodega_es_principal'])) { $bodegaDefaultTxt = (int) $b['inv_bodega_id']; break; } }
                        ?>
                        <select id="bodegaProcesarTxt" class="form-select">
                            <option value="">— Seleccione —</option>
                            <?php foreach ($bodegas ?? [] as $b): ?>
                            <option value="<?= (int) $b['inv_bodega_id'] ?>" <?= (int) $b['inv_bodega_id'] === $bodegaDefaultTxt ? 'selected' : '' ?>><?= htmlspecialchars($b['inv_bodega_nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Archivo TXT del SRI <span class="text-danger">*</span></label>
                        <input type="file" id="archivoTxt" class="form-control" accept=".txt">
                    </div>
                </div>
                <div id="resultadosTxt" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secundario" id="btnCerrarProcesarTxt" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-intesis btn-sm" id="btnEnviarTxt">
                    <i class="bi bi-upload me-1"></i>Procesar TXT
                </button>
                <button type="button" class="btn btn-intesis btn-sm d-none" id="btnGuardarTxt">
                    <i class="bi bi-save me-1"></i>Guardar facturas
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL BUSCAR PRODUCTO (para asignar códigos SRI)
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalBuscarProductoAsig" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Inventario</p><h2 class="modal-title h6 mb-0">Buscar Producto</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="text" id="buscarProductoAsigInput" class="form-control form-control-sm mb-2" placeholder="Código o descripción...">
                <div class="table-responsive" style="max-height:380px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light" style="position:sticky;top:0"><tr><th>#</th><th>Código</th><th>Nombre Producto</th><th>Marca</th><th class="text-end">Stock</th></tr></thead>
                        <tbody id="cuerpoProductoAsigModal"><tr><td colspan="5" class="text-center text-muted py-3">Escriba para buscar...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL PENDIENTES SRI
     ============================================================ -->
<div class="modal fade modal-intesis" id="modalPendientesSri" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Compras · SRI</p><h2 class="modal-title">Documentos SRI con productos pendientes</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Los siguientes proveedores tienen facturas SRI con líneas sin producto asignado.</p>
                <table class="table table-sm tabla-intesis">
                    <thead><tr><th>RUC</th><th>Proveedor</th><th class="text-end">Líneas pendientes</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($pendSri as $ps): ?>
                    <tr>
                        <td><?= htmlspecialchars($ps['com_proveedor_ruc']) ?></td>
                        <td><?= htmlspecialchars($ps['com_proveedor_razon_social']) ?></td>
                        <td class="text-end"><?= (int) $ps['lineas_pendientes'] ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-intesis btn-asignar-pendiente"
                                data-proveedor-id="<?= (int) $ps['com_proveedor_id'] ?>"
                                data-razon="<?= htmlspecialchars($ps['com_proveedor_razon_social']) ?>">
                                Asignar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
(function () {
    'use strict';
    const appUrl          = '<?= $appUrl ?>';
    const puedeRegistrar  = <?= $permisos['registrar'] ? 'true' : 'false' ?>;
    const puedeAnular     = <?= $permisos['anular'] ? 'true' : 'false' ?>;
    const tipoFacturaId   = <?= json_encode($tipoFacturaId) ?>;
    let documentoIdActual = 0;

    // Estado compartido entre modales SRI
    let sriState = {
        facturas: [],         // facturas ya resueltas (con proveedor + detalles + inv_producto_id)
        bodegaId: 0,
        codigosSinMapeo: [],  // [{proveedor_id, cod_principal, descripcion_sri}]
        archivosIds: [],      // archive_ids para marcado posterior
        modoTxt: false,       // true cuando viene del flujo TXT
    };

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function mostrarAlerta(contenedorId, tipo, html) {
        const el = document.getElementById(contenedorId);
        if (el) el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>${html}</div>`;
    }
    function limpiarAlerta(contenedorId) {
        const el = document.getElementById(contenedorId);
        if (el) el.innerHTML = '';
    }
    function getModal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }

    /* ══════════════════════════════════════════════════════════
       MODAL DETALLE DOCUMENTO
    ══════════════════════════════════════════════════════════ */
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-ver-documento');
        if (!btn) return;
        const id     = parseInt(btn.dataset.id, 10);
        const estado = btn.dataset.estado || '';
        documentoIdActual = id;
        const modal  = getModal('modalDetalleDocumento');
        const cuerpo = document.getElementById('cuerpoDetalleDocumento');
        const footer = document.getElementById('footerDetalleDocumento');
        const btnConf = document.getElementById('btnConfirmarCompra');
        const btnAnul = document.getElementById('btnAnularModal');
        cuerpo.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-secondary" role="status"></div></div>';
        const esBorrador = estado === 'BORRADOR';
        const esRegistrado = estado === 'REGISTRADO';
        const mostrarConf = puedeRegistrar && esBorrador;
        const mostrarAnul = puedeAnular && (esBorrador || esRegistrado);
        if (btnConf) { btnConf.style.display = mostrarConf ? '' : 'none'; btnConf.disabled = false; btnConf.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirmar Compra'; }
        if (btnAnul) { btnAnul.style.display = mostrarAnul ? '' : 'none'; btnAnul.disabled = false; btnAnul.innerHTML = '<i class="bi bi-x-circle me-1"></i>Anular'; }
        if (mostrarConf || mostrarAnul) footer.style.removeProperty('display');
        else footer.style.setProperty('display', 'none', 'important');
        modal.show();
        try {
            const res  = await fetch(appUrl + '/compras/documentos/detalle?documento_id=' + id);
            const json = await res.json();
            if (!json.ok) throw new Error(json.mensaje || 'Error');
            const d = json.documento;
            const lineas = json.lineas;
            let sriBadge = d.com_documento_origen === 'SRI' ? `<span class="badge bg-info text-dark ms-1"><i class="bi bi-cloud-download"></i> SRI</span>` : '';
            let sriInfo = '';
            if (d.com_documento_origen === 'SRI' && d.com_documento_clave_acceso) {
                sriInfo = `<div class="col-md-6"><label class="form-label">Clave de acceso</label><input class="form-control form-control-sm" value="${esc(d.com_documento_clave_acceso)}" disabled></div>`;
            }
            let html = `<div class="row g-2 mb-3 formulario-compacto">
                <div class="col-md-3"><label class="form-label">Tipo</label><div class="d-flex align-items-center gap-1"><input class="form-control form-control-sm" value="${esc(d.tipo_nombre)}" disabled>${sriBadge}</div></div>
                <div class="col-md-3"><label class="form-label">Numero</label><input class="form-control form-control-sm" value="${esc(d.com_documento_numero)}" disabled></div>
                <div class="col-md-3"><label class="form-label">Fecha emision</label><input class="form-control form-control-sm" value="${String(d.com_documento_fecha_emision).substring(0,10)}" disabled></div>
                <div class="col-md-3"><label class="form-label">Estado</label><input class="form-control form-control-sm" value="${esc(d.sis_estado_nombre)}" disabled></div>
                <div class="col-md-6"><label class="form-label">Proveedor</label><input class="form-control form-control-sm" value="${esc(d.com_proveedor_razon_social)} (${esc(d.com_proveedor_identificacion)})" disabled></div>
                <div class="col-md-3"><label class="form-label">Bodega</label><input class="form-control form-control-sm" value="${esc(d.inv_bodega_nombre)}" disabled></div>
                ${d.com_documento_observacion ? `<div class="col-6"><label class="form-label">Observacion</label><input class="form-control form-control-sm" value="${esc(d.com_documento_observacion)}" disabled></div>` : ''}
                ${sriInfo}
            </div>
            <div class="table-responsive"><table class="table table-sm tabla-intesis">
                <thead><tr><th>#</th><th>Cod. Proveedor</th><th>Codigo</th><th>Descripcion</th><th>Marca</th>
                <th class="text-center">Cant.</th><th class="text-end">Costo</th>
                <th class="text-center">IVA %</th><th class="text-end">PVP</th><th class="text-end">Total</th></tr></thead><tbody>`;
            lineas.forEach((l, i) => {
                html += `<tr><td>${i+1}</td><td>${esc(l.cod_proveedor)||'—'}</td><td>${esc(l.codigo_interno)}</td>
                <td>${esc(l.producto_nombre)}</td><td>${esc(l.marca_nombre)||'—'}</td>
                <td class="text-center">${Math.round(parseFloat(l.com_documento_detalle_cantidad) || 0)}</td>
                <td class="text-end">${parseFloat(l.com_documento_detalle_precio).toFixed(4)}</td>
                <td class="text-center">${l.sis_iva_valor !== null ? parseFloat(l.sis_iva_valor).toFixed(0)+'%' : '—'}</td>
                <td class="text-end">${parseFloat(l.com_documento_detalle_pvp).toFixed(2)}</td>
                <td class="text-end">${parseFloat(l.com_documento_detalle_total).toFixed(2)}</td></tr>`;
            });
            html += `</tbody></table></div>
            <div class="row justify-content-end mt-2 formulario-compacto"><div class="col-md-4">
                <table class="table table-sm mb-0">
                    <tr><td>Subtotal</td><td class="text-end">$ ${parseFloat(d.com_documento_subtotal).toFixed(2)}</td></tr>
                    <tr><td>Descuento</td><td class="text-end">$ ${parseFloat(d.com_documento_descuento).toFixed(2)}</td></tr>
                    <tr><td>IVA</td><td class="text-end">$ ${parseFloat(d.com_documento_iva).toFixed(2)}</td></tr>
                    <tr class="fw-bold"><td>Total</td><td class="text-end">$ ${parseFloat(d.com_documento_valor_total).toFixed(2)}</td></tr>
                </table>
            </div></div>`;
            cuerpo.innerHTML = html;
        } catch (err) {
            cuerpo.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
        }
    });

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
                getModal('modalDetalleDocumento').hide();
                window.location.reload();
            } catch (err) {
                btnConf.disabled = false;
                btnConf.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirmar Compra';
                document.getElementById('cuerpoDetalleDocumento').insertAdjacentHTML('afterbegin',
                    `<div class="alert alert-danger alert-dismissible mb-2"><strong>Error:</strong> ${err.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
            }
        });
    }

    const btnAnulModal = document.getElementById('btnAnularModal');
    if (btnAnulModal) {
        btnAnulModal.addEventListener('click', async () => {
            if (!documentoIdActual) return;
            if (!confirm('¿Anular este documento? Esta acción no se puede deshacer.')) return;
            btnAnulModal.disabled = true;
            btnAnulModal.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Anulando...';
            try {
                const fd = new FormData();
                fd.append('documento_id', documentoIdActual);
                const res = await fetch(appUrl + '/compras/documentos/anular', { method: 'POST', body: fd });
                if (!res.ok && !res.redirected) throw new Error('Error al anular');
                getModal('modalDetalleDocumento').hide();
                window.location.reload();
            } catch (err) {
                btnAnulModal.disabled = false;
                btnAnulModal.innerHTML = '<i class="bi bi-x-circle me-1"></i>Anular';
                document.getElementById('cuerpoDetalleDocumento').insertAdjacentHTML('afterbegin',
                    `<div class="alert alert-danger alert-dismissible mb-2"><strong>Error:</strong> ${err.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
            }
        });
    }

    document.getElementById('modalDetalleDocumento').addEventListener('hidden.bs.modal', () => {
        documentoIdActual = 0;
        document.getElementById('footerDetalleDocumento').style.setProperty('display', 'none', 'important');
    });

    /* ══════════════════════════════════════════════════════════
       MODAL SUBIR XML
    ══════════════════════════════════════════════════════════ */
    const btnNuevaCompra = document.getElementById('btnNuevaCompra');
    if (btnNuevaCompra) {
        btnNuevaCompra.addEventListener('click', () => {
            window.location.href = appUrl + '/compras/documentos/nuevo';
        });
    }

    const btnAbrirXml = document.getElementById('btnAbrirSubirXml');
    if (btnAbrirXml) {
        btnAbrirXml.addEventListener('click', () => {
            limpiarAlerta('alertaSubirXml');
            document.getElementById('archivoXml').value = '';
            const selXml = document.getElementById('bodegaSubirXml');
            const defXml = selXml.querySelector('option[selected]');
            selXml.value = defXml ? defXml.value : '';
            getModal('modalSubirXml').show();
        });
    }

    const btnProcesarXml = document.getElementById('btnProcesarXml');
    if (btnProcesarXml) {
        btnProcesarXml.addEventListener('click', async () => {
            const bodegaId = document.getElementById('bodegaSubirXml').value;
            const archivoInp = document.getElementById('archivoXml');
            limpiarAlerta('alertaSubirXml');
            if (!bodegaId) { mostrarAlerta('alertaSubirXml', 'warning', 'Seleccione una bodega.'); return; }
            if (!archivoInp.files.length) { mostrarAlerta('alertaSubirXml', 'warning', 'Seleccione un archivo XML.'); return; }

            btnProcesarXml.disabled = true;
            btnProcesarXml.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';

            try {
                const fd = new FormData();
                fd.append('bodega_id', bodegaId);
                fd.append('xml_file', archivoInp.files[0]);
                const res  = await fetch(appUrl + '/compras/documentos/subir-xml', { method: 'POST', body: fd });
                const json = await res.json();

                if (!json.ok) {
                    if (json.tipo_no_soportado) {
                        mostrarAlerta('alertaSubirXml', 'warning', '<strong>Tipo no procesable:</strong> ' + esc(json.mensaje) + '. Solo se importan facturas.');
                    } else {
                        mostrarAlerta('alertaSubirXml', 'danger', esc(json.mensaje));
                    }
                    return;
                }

                if (json.duplicado) {
                    mostrarAlerta('alertaSubirXml', 'warning',
                        `<strong>Documento duplicado.</strong> La factura <em>${esc(json.numero)}</em> ya fue importada anteriormente.`);
                    return;
                }

                // Guardar contexto
                sriState.bodegaId    = parseInt(bodegaId, 10);
                sriState.modoTxt     = false;
                sriState.facturas    = [Object.assign({}, json.factura, {
                    proveedor_id: json.proveedor_id,
                    bodega_id:    json.bodega_id,
                    archivo_id:   json.archivo_id,
                    archivo_ruta: json.archivo_ruta,
                })];
                sriState.codigosSinMapeo = json.pendientes_codigos || [];

                getModal('modalSubirXml').hide();

                if (sriState.codigosSinMapeo.length > 0) {
                    abrirModalAsignarCodigos(sriState.codigosSinMapeo, () => {
                        abrirPreviewXml(sriState.facturas[0]);
                    });
                } else {
                    abrirPreviewXml(sriState.facturas[0]);
                }
            } catch (err) {
                mostrarAlerta('alertaSubirXml', 'danger', err.message);
            } finally {
                btnProcesarXml.disabled = false;
                btnProcesarXml.innerHTML = '<i class="bi bi-upload me-1"></i>Procesar XML';
            }
        });
    }

    /* ══════════════════════════════════════════════════════════
       MODAL PREVIEW XML
    ══════════════════════════════════════════════════════════ */
    function abrirPreviewXml(factura) {
        const cuerpo = document.getElementById('cuerpoPreviewXml');
        const btnGuardar = document.getElementById('btnGuardarSriUno');
        limpiarAlerta('alertaPreviewXml');
        btnGuardar.disabled = false;

        const numero = `${esc(factura.estab||'')}-${esc(factura.pto_emi||'')}-${esc(factura.secuencial||'')}`;
        const detalles = factura.detalles || [];
        const todosAsignados = detalles.every(d => d.inv_producto_id);

        let html = `<div class="row g-2 mb-3 formulario-compacto">
            <div class="col-md-4"><label class="form-label">Número</label><input class="form-control form-control-sm" value="${numero}" disabled></div>
            <div class="col-md-4"><label class="form-label">Fecha emisión</label><input class="form-control form-control-sm" value="${esc(factura.fecha_emision||'')}" disabled></div>
            <div class="col-md-4"><label class="form-label">Autorización</label><input class="form-control form-control-sm" value="${esc(factura.fecha_autorizacion||'')}" disabled></div>
            <div class="col-md-8"><label class="form-label">Proveedor (RUC)</label><input class="form-control form-control-sm" value="${esc(factura.razon_social||'')} — ${esc(factura.ruc_emisor||'')}" disabled></div>
            <div class="col-md-4"><label class="form-label">Total</label><input class="form-control form-control-sm text-end" value="$ ${parseFloat(factura.importe_total||0).toFixed(2)}" disabled></div>
        </div>
        <div class="table-responsive"><table class="table table-sm tabla-intesis">
        <thead><tr><th>#</th><th>Cod. SRI</th><th>Descripcion SRI</th><th>Producto asignado</th>
        <th class="text-center">Cant.</th><th class="text-end">P.Unit</th><th class="text-end">Total</th></tr></thead><tbody>`;

        detalles.forEach((det, i) => {
            const asig = det.inv_producto_id
                ? `<span class="badge bg-success"><i class="bi bi-check"></i> Asignado</span>`
                : `<span class="badge bg-danger"><i class="bi bi-x"></i> Sin asignar</span>`;
            html += `<tr>
                <td>${i+1}</td>
                <td><code>${esc(det.cod_principal)}</code></td>
                <td>${esc(det.descripcion_sri)}</td>
                <td>${asig}</td>
                <td class="text-center">${Math.round(parseFloat(det.cantidad) || 1)}</td>
                <td class="text-end">${parseFloat(det.precio_unitario||0).toFixed(4)}</td>
                <td class="text-end">${parseFloat(det.precio_total_sin_impuesto||0).toFixed(2)}</td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
        cuerpo.innerHTML = html;

        if (!todosAsignados) {
            btnGuardar.disabled = true;
            document.getElementById('alertaPreviewXml').innerHTML =
                `<span class="text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i>Hay líneas sin producto asignado. Cierre esta ventana y asigne los productos pendientes.</span>`;
        }
        getModal('modalPreviewXml').show();
    }

    const btnGuardarSriUno = document.getElementById('btnGuardarSriUno');
    if (btnGuardarSriUno) {
        btnGuardarSriUno.addEventListener('click', async () => {
            btnGuardarSriUno.disabled = true;
            btnGuardarSriUno.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
            try {
                const fd = new FormData();
                fd.append('bodega_id', sriState.bodegaId);
                fd.append('facturas_json', JSON.stringify(sriState.facturas));
                const res  = await fetch(appUrl + '/compras/documentos/guardar-sri', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.mensaje || 'Error');
                if (json.errores && json.errores.length > 0 && json.guardados.length === 0) {
                    throw new Error(json.errores.map(e => e.numero + ': ' + e.mensaje).join('\n'));
                }
                getModal('modalPreviewXml').hide();
                window.location.reload();
            } catch (err) {
                mostrarAlerta('alertaPreviewXml', 'danger', err.message);
                btnGuardarSriUno.disabled = false;
                btnGuardarSriUno.innerHTML = '<i class="bi bi-save me-1"></i>Guardar como Borrador';
            }
        });
    }

    /* ══════════════════════════════════════════════════════════
       MODAL ASIGNAR CÓDIGOS
    ══════════════════════════════════════════════════════════ */
    let asignarCallback      = null;
    let asignarEstado        = {}; // clave: "provId|cod" → productoId
    let codigosAsigActuales  = []; // referencia a los códigos del modal activo
    let inpAsigActual        = null; // input que activó el modal de búsqueda de producto
    let padreModalAsignar    = null; // id del modal que abrió modalAsignarCodigos
    let ocultandoAsignarTemp = false; // true cuando se oculta Asignar sólo para mostrar BuscarProducto

    function abrirModalAsignarCodigos(codigos, callback, padreId = null) {
        asignarCallback     = callback;
        asignarEstado       = {};
        codigosAsigActuales = codigos;
        padreModalAsignar   = padreId;
        if (padreId) getModal(padreId).hide();
        const contenedor = document.getElementById('tablaAsignarCodigos');
        limpiarAlerta('alertaAsignarCodigos');

        if (!codigos.length) {
            if (callback) callback();
            return;
        }

        let html = `<table class="table table-sm tabla-intesis" id="tbAsignarCods">
        <thead><tr><th>Cod. Prov.</th><th>Descripción del XML</th><th class="text-center">Cant. Entrante</th><th class="text-center">Stock Total</th><th>Producto a Asignar</th><th class="text-center">Estado</th></tr></thead><tbody>`;
        codigos.forEach((cod, i) => {
            html += `<tr id="filaCod${i}">
                <td><code>${esc(cod.cod_principal)}</code></td>
                <td>${esc(cod.descripcion_sri)}</td>
                <td class="text-center">${Math.round(parseFloat(cod.cantidad) || 0)}</td>
                <td class="text-center stock-asig-${i}">—</td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control input-buscar-producto-cod"
                            placeholder="Buscar producto..."
                            data-idx="${i}"
                            data-proveedor-id="${cod.proveedor_id}"
                            data-cod="${esc(cod.cod_principal)}"
                            autocomplete="off">
                        <button class="btn btn-outline-secondary btn-limpiar-asig" data-idx="${i}" type="button"><i class="bi bi-x"></i></button>
                    </div>
                    <input type="hidden" class="input-producto-id-asig" data-idx="${i}" value="">
                </td>
                <td class="text-center estado-asig-${i}"><span class="badge bg-secondary">Pendiente</span></td>
            </tr>`;
        });
        html += `</tbody></table>`;
        contenedor.innerHTML = html;

        actualizarBtnContinuar(codigos);
        getModal('modalAsignarCodigos').show();

        // Enter: asignación directa por código interno exacto, o abre modal avanzado
        contenedor.querySelectorAll('.input-buscar-producto-cod').forEach(inp => {
            inp.addEventListener('keydown', async (e) => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                e.stopPropagation();
                const inpHidden = inp.closest('tr')?.querySelector('.input-producto-id-asig');
                if (inpHidden?.value) return; // ya tiene producto asignado
                const q = inp.value.trim();
                if (!q) { abrirModalBuscarProductoAsig('', inp); return; }
                try {
                    const res  = await fetch(appUrl + '/compras/documentos/productos?tipo=codigo&q=' + encodeURIComponent(q));
                    const json = await res.json();
                    const prods = json.productos || [];
                    const exacto = prods.find(p => (p.codigo_interno || '').toUpperCase() === q.toUpperCase());
                    if (exacto) {
                        guardarAsignacionCod(inp.dataset.idx, parseInt(exacto.inv_producto_id, 10), exacto.inv_producto_nombre, parseFloat(exacto.stock_total ?? null));
                    } else {
                        abrirModalBuscarProductoAsig(q, inp);
                    }
                } catch {
                    abrirModalBuscarProductoAsig(q, inp);
                }
            });
        });

        // Limpiar fila
        contenedor.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-limpiar-asig');
            if (!btn) return;
            const idx = btn.dataset.idx;
            const fila = contenedor.querySelector(`#filaCod${idx}`);
            fila.querySelector('.input-buscar-producto-cod').value = '';
            fila.querySelector('.input-producto-id-asig').value = '';
            contenedor.querySelector(`.estado-asig-${idx}`).innerHTML = '<span class="badge bg-secondary">Pendiente</span>';
            const stockEl = contenedor.querySelector(`.stock-asig-${idx}`);
            if (stockEl) stockEl.textContent = '—';
        });
    }

    /* ── Guardar asignación de código SRI → producto (reutilizable) ─────────── */
    async function guardarAsignacionCod(idx, productoId, nombre, stockTotal = null) {
        const contenedor  = document.getElementById('tablaAsignarCodigos');
        const fila        = contenedor?.querySelector(`#filaCod${idx}`);
        if (!fila) return;
        const inpBuscar   = fila.querySelector('.input-buscar-producto-cod');
        const inpHidden   = fila.querySelector('.input-producto-id-asig');
        const estadoEl    = contenedor.querySelector(`.estado-asig-${idx}`);
        const proveedorId = parseInt(inpBuscar.dataset.proveedorId, 10);
        const cod         = inpBuscar.dataset.cod;

        inpBuscar.value = nombre;
        inpHidden.value = productoId;
        estadoEl.innerHTML = '<span class="badge bg-info"><i class="bi bi-arrow-repeat"></i> Guardando...</span>';

        try {
            const fd = new FormData();
            fd.append('proveedor_id',  proveedorId);
            fd.append('cod_principal', cod);
            fd.append('producto_id',   productoId);
            const res  = await fetch(appUrl + '/compras/documentos/asignar-codigo', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) throw new Error(json.mensaje);
            estadoEl.innerHTML = '<span class="badge bg-success"><i class="bi bi-check"></i> Guardado</span>';
            asignarEstado[`${proveedorId}|${cod}`] = productoId;
            if (stockTotal !== null) {
                const stockEl = contenedor?.querySelector(`.stock-asig-${idx}`);
                if (stockEl) stockEl.textContent = Math.round(stockTotal);
            }
            actualizarBtnContinuar(codigosAsigActuales);
        } catch (err) {
            estadoEl.innerHTML = '<span class="badge bg-danger"><i class="bi bi-x"></i> Error</span>';
            Swal.fire({
                toast: true, position: 'top-end', icon: 'error',
                title: err.message, showConfirmButton: false, timer: 5000, timerProgressBar: true,
            });
        }
    }

    /* ── Modal buscar producto avanzado (para Asignar Códigos SRI) ───────────── */
    let timerBuscProdAsig = null;

    function abrirModalBuscarProductoAsig(texto, inp) {
        inpAsigActual = inp;
        const buscarInp = document.getElementById('buscarProductoAsigInput');
        if (buscarInp) buscarInp.value = texto;
        // Ocultar Asignar primero; mostrar BuscarProducto cuando termine la animación
        ocultandoAsignarTemp = true;
        const asignarEl = document.getElementById('modalAsignarCodigos');
        const whenHidden = () => {
            asignarEl.removeEventListener('hidden.bs.modal', whenHidden);
            getModal('modalBuscarProductoAsig').show();
            buscarProductosAsig(texto);
            setTimeout(() => document.getElementById('buscarProductoAsigInput')?.focus(), 300);
        };
        asignarEl.addEventListener('hidden.bs.modal', whenHidden);
        getModal('modalAsignarCodigos').hide();
    }

    async function buscarProductosAsig(term) {
        const tbody = document.getElementById('cuerpoProductoAsigModal');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Buscando...</td></tr>';
        try {
            const res  = await fetch(appUrl + '/compras/documentos/productos?tipo=codigo&q=' + encodeURIComponent(term));
            const json = await res.json();
            if (!json.ok || !json.productos?.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin resultados</td></tr>';
                return;
            }
            tbody.innerHTML = json.productos.map((p, i) => {
                const safeJson = JSON.stringify(p).replace(/'/g, '&#39;');
                return `<tr style="cursor:pointer" data-prod='${safeJson}'>
                    <td>${i + 1}</td>
                    <td>${esc(p.codigo_interno || '—')}</td>
                    <td>${esc(p.inv_producto_nombre || '')}</td>
                    <td>${esc(p.marca_nombre || '—')}</td>
                    <td class="text-center">${Math.round(parseFloat(p.stock_total) || 0)}</td>
                </tr>`;
            }).join('');
        } catch {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al buscar</td></tr>';
        }
    }

    const buscarProductoAsigInput = document.getElementById('buscarProductoAsigInput');
    if (buscarProductoAsigInput) {
        buscarProductoAsigInput.addEventListener('input', (e) => {
            clearTimeout(timerBuscProdAsig);
            timerBuscProdAsig = setTimeout(() => buscarProductosAsig(e.target.value), 300);
        });
        buscarProductoAsigInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); buscarProductosAsig(e.target.value); }
        });
    }

    document.getElementById('cuerpoProductoAsigModal')?.addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-prod]');
        if (!tr || !inpAsigActual) return;
        let prod;
        try { prod = JSON.parse(tr.dataset.prod); } catch { return; }
        const idx    = inpAsigActual.dataset.idx;
        const nombre = prod.inv_producto_nombre || '';
        const id     = parseInt(prod.inv_producto_id, 10);
        getModal('modalBuscarProductoAsig').hide();
        inpAsigActual = null;
        guardarAsignacionCod(idx, id, nombre, parseFloat(prod.stock_total ?? null));
    });

    document.getElementById('modalBuscarProductoAsig')?.addEventListener('hidden.bs.modal', () => {
        inpAsigActual = null;
        // Restaurar el modal Asignar cuando cierra BuscarProducto (con o sin selección)
        getModal('modalAsignarCodigos').show();
    });

    function actualizarBtnContinuar(codigos) {
        const btn = document.getElementById('btnContinuarTrasAsignar');
        if (!btn) return;
        const asignados = Object.keys(asignarEstado).length;
        const total = codigos.length;
        btn.disabled = asignados < 1;
        btn.innerHTML = asignados >= total
            ? '<i class="bi bi-check2 me-1"></i>Continuar'
            : `<i class="bi bi-check2 me-1"></i>Continuar (${asignados}/${total} asignados)`;
    }

    const btnContinuarAsig = document.getElementById('btnContinuarTrasAsignar');
    if (btnContinuarAsig) {
        btnContinuarAsig.addEventListener('click', () => {
            getModal('modalAsignarCodigos').hide();
            if (typeof asignarCallback === 'function') {
                // Re-resolver productos con los nuevos mapeos
                resolverProductosLocal(sriState.facturas, asignarEstado);
                asignarCallback();
            }
        });
    }

    // Restaurar el modal padre cuando se cierra el modal Asignar
    document.getElementById('modalAsignarCodigos')?.addEventListener('hidden.bs.modal', () => {
        if (ocultandoAsignarTemp) {
            // Oculto temporalmente para mostrar BuscarProducto; no restaurar padre todavía
            ocultandoAsignarTemp = false;
            return;
        }
        if (padreModalAsignar) {
            const pid = padreModalAsignar;
            padreModalAsignar = null;
            getModal(pid).show();
        }
    });

    // Tras asignar, aplica los mapeos del asignarEstado a las facturas en memoria
    function resolverProductosLocal(facturas, estado) {
        facturas.forEach(fac => {
            (fac.detalles || []).forEach(det => {
                const llave = `${fac.proveedor_id}|${det.cod_principal}`;
                if (!det.inv_producto_id && estado[llave]) {
                    det.inv_producto_id = estado[llave];
                }
            });
        });
    }

    /* ══════════════════════════════════════════════════════════
       MODAL PROCESAR TXT
    ══════════════════════════════════════════════════════════ */
    const btnAbrirTxt = document.getElementById('btnAbrirProcesarTxt');
    if (btnAbrirTxt) {
        btnAbrirTxt.addEventListener('click', () => {
            document.getElementById('archivoTxt').value = '';
            const selTxt = document.getElementById('bodegaProcesarTxt');
            const defTxt = selTxt.querySelector('option[selected]');
            selTxt.value = defTxt ? defTxt.value : '';
            document.getElementById('resultadosTxt').style.display = 'none';
            document.getElementById('resultadosTxt').innerHTML = '';
            document.getElementById('formProcesarTxt').style.display = '';
            document.getElementById('btnEnviarTxt').classList.remove('d-none');
            document.getElementById('btnGuardarTxt').classList.add('d-none');
            limpiarAlerta('alertaProcesarTxt');
            getModal('modalProcesarTxt').show();
        });
    }

    const btnEnviarTxt = document.getElementById('btnEnviarTxt');
    if (btnEnviarTxt) {
        btnEnviarTxt.addEventListener('click', async () => {
            const bodegaId = document.getElementById('bodegaProcesarTxt').value;
            const archivoInp = document.getElementById('archivoTxt');
            limpiarAlerta('alertaProcesarTxt');
            if (!bodegaId) { mostrarAlerta('alertaProcesarTxt', 'warning', 'Seleccione una bodega.'); return; }
            if (!archivoInp.files.length) { mostrarAlerta('alertaProcesarTxt', 'warning', 'Seleccione un archivo TXT.'); return; }

            btnEnviarTxt.disabled = true;
            btnEnviarTxt.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando (puede tardar varios minutos)...';

            try {
                const fd = new FormData();
                fd.append('bodega_id', bodegaId);
                fd.append('txt_file', archivoInp.files[0]);
                const res  = await fetch(appUrl + '/compras/documentos/procesar-txt', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.mensaje || 'Error');

                sriState.bodegaId        = parseInt(bodegaId, 10);
                sriState.modoTxt         = true;
                sriState.facturas        = json.facturas_para_guardar || [];
                sriState.codigosSinMapeo = json.codigos_sin_mapeo || [];

                // Mostrar resumen
                mostrarResumenTxt(json);

                // Mostrar botón guardar si hay facturas; deshabilitarlo mientras haya códigos sin mapear
                const hayFacturas   = sriState.facturas.length > 0;
                const hayPendientes = sriState.codigosSinMapeo.length > 0;
                const btnGuardar    = document.getElementById('btnGuardarTxt');
                if (hayFacturas) {
                    btnGuardar.classList.remove('d-none');
                    btnGuardar.disabled = hayPendientes;
                    btnGuardar.title    = hayPendientes ? 'Asigne todos los productos antes de guardar' : '';
                }
                document.getElementById('btnEnviarTxt').classList.add('d-none');

            } catch (err) {
                mostrarAlerta('alertaProcesarTxt', 'danger', err.message);
            } finally {
                btnEnviarTxt.disabled = false;
                btnEnviarTxt.innerHTML = '<i class="bi bi-upload me-1"></i>Procesar TXT';
            }
        });
    }

    function mostrarResumenTxt(json) {
        const cont = document.getElementById('resultadosTxt');
        cont.style.display = '';
        let html = '';

        if (json.facturas && json.facturas.length) {
            html += `<h6 class="text-success"><i class="bi bi-check-circle me-1"></i>Facturas descargadas: ${json.facturas.length}</h6>
            <div class="table-responsive"><table class="table table-sm tabla-intesis mb-3"><thead><tr><th>Número</th><th>RUC Emisor</th><th>Razón Social</th><th class="text-end">Total</th></tr></thead><tbody>`;
            json.facturas.forEach(f => {
                html += `<tr><td>${esc(f.numero)}</td><td>${esc(f.ruc_emisor)}</td><td>${esc(f.razon_social)}</td><td class="text-end">$ ${parseFloat(f.importe_total||0).toFixed(2)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        if (json.codigos_sin_mapeo && json.codigos_sin_mapeo.length) {
            html += `<div class="alert alert-warning py-2">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <strong>${json.codigos_sin_mapeo.length}</strong> código(s) sin producto asignado.
                <button type="button" class="btn btn-sm btn-warning ms-2" id="btnAsignarCodigosTxt">Asignar ahora</button>
            </div>`;
        }

        if (json.duplicados && json.duplicados.length) {
            html += `<h6 class="text-warning"><i class="bi bi-skip-forward me-1"></i>Duplicados ignorados: ${json.duplicados.length}</h6>
            <ul class="small">` + json.duplicados.map(d => `<li>${esc(d.serie||'')} — ${esc(d.ruc_emisor||'')}</li>`).join('') + '</ul>';
        }

        if (json.ignorados && json.ignorados.length) {
            html += `<h6 class="text-secondary"><i class="bi bi-dash-circle me-1"></i>No procesados: ${json.ignorados.length}</h6>
            <ul class="small">` + json.ignorados.map(d => `<li>${esc(d.serie||'')} — <em>${esc(d.mensaje_ignorado||'')}</em></li>`).join('') + '</ul>';
        }

        if (json.errores && json.errores.length) {
            html += `<h6 class="text-danger"><i class="bi bi-x-circle me-1"></i>Errores: ${json.errores.length}</h6>
            <ul class="small">` + json.errores.map(e => `<li>${esc(e.fila?.serie||'')} — ${esc(e.mensaje)}</li>`).join('') + '</ul>';
            if (json.hay_pendientes) {
                html += `<p class="text-muted small"><i class="bi bi-info-circle me-1"></i>Puede reintentarlos más tarde desde "Procesar TXT" con el mismo archivo.</p>`;
            }
        }

        cont.innerHTML = html;

        // Botón asignar dentro del resumen
        const btnAsignarTxt = document.getElementById('btnAsignarCodigosTxt');
        if (btnAsignarTxt) {
            btnAsignarTxt.addEventListener('click', () => {
                abrirModalAsignarCodigos(sriState.codigosSinMapeo, () => {  // padre: modalProcesarTxt se oculta al abrir Asignar y se restaura al cerrar
                    // Tras asignar, actualizar facturas en memoria
                    resolverProductosLocal(sriState.facturas, asignarEstado);
                    // Verificar si todos tienen productos
                    const sinProducto = sriState.facturas.some(f =>
                        (f.detalles||[]).some(d => !d.inv_producto_id)
                    );
                    const alertEl = cont.querySelector('.alert-warning');
                    const btnG    = document.getElementById('btnGuardarTxt');
                    if (!sinProducto) {
                        if (alertEl) alertEl.innerHTML = '<i class="bi bi-check-circle-fill me-1 text-success"></i>Todos los productos asignados. Ya puede guardar.';
                        if (btnG) { btnG.disabled = false; btnG.title = ''; }
                    } else {
                        if (alertEl) alertEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Aún quedan productos sin asignar.';
                    }
                }, 'modalProcesarTxt');  // ← padre: se oculta al abrir Asignar y reaparece al cerrarlo
            });
        }
    }

    const btnGuardarTxt = document.getElementById('btnGuardarTxt');
    if (btnGuardarTxt) {
        btnGuardarTxt.addEventListener('click', async () => {
            // Verificar que todos tengan producto
            const sinProducto = sriState.facturas.some(f =>
                (f.detalles||[]).some(d => !d.inv_producto_id)
            );
            if (sinProducto) {
                mostrarAlerta('alertaProcesarTxt', 'warning', 'Hay líneas sin producto asignado. Asigne todos los productos antes de guardar.');
                return;
            }
            btnGuardarTxt.disabled = true;
            btnGuardarTxt.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
            try {
                const fd = new FormData();
                fd.append('bodega_id', sriState.bodegaId);
                fd.append('facturas_json', JSON.stringify(sriState.facturas));
                const res  = await fetch(appUrl + '/compras/documentos/guardar-sri', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.mensaje || 'Error');
                if (json.errores && json.errores.length > 0 && json.guardados.length === 0) {
                    throw new Error(json.errores.map(e => e.numero + ': ' + e.mensaje).join('\n'));
                }
                getModal('modalProcesarTxt').hide();
                window.location.reload();
            } catch (err) {
                mostrarAlerta('alertaProcesarTxt', 'danger', err.message);
                btnGuardarTxt.disabled = false;
                btnGuardarTxt.innerHTML = '<i class="bi bi-save me-1"></i>Guardar facturas';
            }
        });
    }

    /* ══════════════════════════════════════════════════════════
       MODAL PENDIENTES SRI
    ══════════════════════════════════════════════════════════ */
    const btnPendSri = document.getElementById('btnPendientesSri');
    if (btnPendSri) {
        btnPendSri.addEventListener('click', () => getModal('modalPendientesSri').show());
    }

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-asignar-pendiente');
        if (!btn) return;
        const proveedorId = parseInt(btn.dataset.proveedorId, 10);
        // Cargar lineas pendientes del proveedor via detalle AJAX
        // Construir codigos sin mapeo con la info disponible en el modal pendientes
        const res = await fetch(appUrl + '/compras/documentos/detalle?documento_id=0&pendientes_proveedor=' + proveedorId).catch(() => null);
        // Por ahora, abrimos el modal de asignar con datos mínimos — el servidor propaga el mapeo
        mostrarAlerta('alertaSubirXml', 'info', 'Cargando pendientes...');
        getModal('modalPendientesSri').hide();
        // Re-usar el endpoint de productos pendientes sería lo ideal, pero abrimos con vacío para que el usuario busque
        abrirModalAsignarCodigos(
            [{
                proveedor_id: proveedorId,
                cod_principal: '',
                descripcion_sri: '(Asigne productos manualmente para este proveedor)',
            }],
            () => window.location.reload()
        );
    });

    /* ══════════════════════════════════════════════════════════
       MODAL PDF DOCUMENTO
    ══════════════════════════════════════════════════════════ */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-pdf-documento');
        if (!btn) return;
        const id      = btn.dataset.id;
        const numero  = btn.dataset.numero || id;
        const pdfUrl  = appUrl + '/compras/documentos/pdf?id=' + id;
        const descUrl = appUrl + '/compras/documentos/pdf?id=' + id + '&modo=descargar';
        const xmlUrl  = appUrl + '/compras/documentos/xml?id=' + id;

        document.getElementById('modalPdfDocumentoTitulo').textContent = 'Factura ' + numero;
        document.getElementById('iframePdfDocumento').src = pdfUrl;
        document.getElementById('btnDescPdf').href = descUrl;

        const btnXml = document.getElementById('btnDescXml');
        btnXml.onclick = () => { window.open(xmlUrl, '_blank'); };

        getModal('modalPdfDocumento').show();
    });

    document.getElementById('modalPdfDocumento')?.addEventListener('hidden.bs.modal', () => {
        document.getElementById('iframePdfDocumento').src = '';
        document.getElementById('modalPdfDocumentoTitulo').textContent = 'Vista Previa PDF';
    });

}());
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
