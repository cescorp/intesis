<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre   = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl      = rtrim($configuracion->obtener('APP_URL', ''), '/');
$esEdicion   = isset($factura);
$facturaId   = $esEdicion ? (int) $factura['ven_documento_id'] : 0;
$editTipoDoc = $esEdicion ? (string)($factura['tipo_codigo'] ?? 'FACTURA_VENTA') : 'FACTURA_VENTA';
$lineasJson  = isset($lineas) ? json_encode($lineas, JSON_UNESCAPED_UNICODE) : '[]';
$ivaDefault      = 0;
$ivaDefaultValor = 0;
foreach ($ivaList as $iv) {
    $ivaDefault      = (int)   $iv['sis_iva_id'];
    $ivaDefaultValor = (float) $iv['sis_iva_valor'];
    break;
}
$editFecha       = $esEdicion ? substr((string)($factura['ven_documento_fecha_emision'] ?? ''), 0, 10) : date('Y-m-d');
$editObs         = $esEdicion ? htmlspecialchars((string)($factura['ven_documento_observacion'] ?? '')) : '';
$editNumero      = $esEdicion ? htmlspecialchars((string)$factura['ven_documento_numero']) : '';
$editFormaPagoId = $esEdicion ? (int)($factura['ven_forma_pago_id'] ?? 0) : 0;
if (!$esEdicion && empty($editFormaPagoId)) {
    foreach ($formasPago as $fp) {
        if (strtoupper($fp['ven_forma_pago_nombre']) === 'EFECTIVO') {
            $editFormaPagoId = (int) $fp['ven_forma_pago_id'];
            break;
        }
    }
}
// Mapear calculadora por id para pasar a JS
$formasPagoCalculadora = [];
foreach ($formasPago as $fp) {
    $formasPagoCalculadora[(int)$fp['ven_forma_pago_id']] = $fp['ven_forma_pago_calculadora'] ?? 'N';
}
?>
<div class="app-wrapper">
    <!-- NAVBAR -->
    <nav class="app-header navbar navbar-expand navbar-intesis">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li>
                <li class="nav-item"><span class="nav-link breadcrumb-navbar">
                    <span><?= htmlspecialchars($appNombre) ?></span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="<?= $appUrl ?>/ventas/facturas" class="text-decoration-none">Facturas</a>
                    <i class="bi bi-chevron-right"></i>
                    <strong><?= $esEdicion ? 'Editar' : 'Nueva' ?></strong>
                </span></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars((($usuario['bodega'] ?? null) ? $usuario['bodega'] . ' - ' : '') . $usuario['nombre']) ?></span></li>
                <li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li>
            </ul>
        </div>
    </nav>
    <!-- SIDEBAR -->
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div>
        <div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div>
    </aside>

    <main class="app-main"><div class="app-content"><div class="container-fluid">

        <!-- ═══ CABECERA + botones ═══ -->
        <div class="card mb-2">
            <div class="card-header d-flex align-items-center justify-content-between py-1 px-2">
                <span class="fw-semibold small" id="tituloCard"><i class="bi bi-receipt me-1"></i><?= $esEdicion ? 'Editar Factura' : 'Nueva Factura' ?></span>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($esEdicion): ?>
                    <span class="badge bg-secondary" style="font-size:.78rem;font-weight:500"><?= $editTipoDoc === 'NOTA_VENTA' ? 'Nota de Venta' : 'Factura' ?></span>
                    <?php else: ?>
                    <select id="selTipoDoc" class="form-select form-select-sm" style="width:auto;font-size:.82rem">
                        <option value="FACTURA_VENTA" <?= $editTipoDoc === 'FACTURA_VENTA' ? 'selected' : '' ?>>Factura</option>
                        <option value="NOTA_VENTA"    <?= $editTipoDoc === 'NOTA_VENTA'    ? 'selected' : '' ?>>Nota de Venta</option>
                    </select>
                    <?php endif; ?>
                    <a href="<?= $appUrl ?>/ventas/facturas" class="btn btn-sm btn-secundario px-2"><i class="bi bi-x-lg me-1"></i>Cancelar</a>
                    <button type="button" id="btnGuardar" class="btn btn-sm btn-intesis px-2"><i class="bi bi-save2 me-1"></i>Guardar</button>
                </div>
            </div>
            <div class="card-body py-2 px-2 formulario-compacto">
                <!-- Fila 1: datos principales -->
                <div class="row g-2 mb-2">
                    <?php if ($esSuperusuario): ?>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">Empresa</label>
                        <select id="selEmpresa" class="form-control form-control-sm">
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= $emp['sis_empresa_id'] ?>"
                                <?= ($esEdicion && $factura['sis_empresa_id'] == $emp['sis_empresa_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['sis_empresa_nombre_comercial']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($esEdicion): ?>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">Numero</label>
                        <input type="text" class="form-control form-control-sm font-monospace" value="<?= $editNumero ?>" disabled>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">Fecha</label>
                        <input type="date" id="inpFecha" class="form-control form-control-sm bg-light" value="<?= $editFecha ?>" readonly tabindex="-1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">RUC / Identificacion <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="ruc_input" class="form-control form-control-sm"
                                placeholder="Ingrese y presione Enter" autocomplete="off"
                                value="<?= $esEdicion ? htmlspecialchars($factura['ven_cliente_identificacion'] ?? '') : '' ?>">
                            <button type="button" class="btn btn-outline-secondary" id="btnBuscarRuc" tabindex="-1" title="Buscar"><i class="bi bi-search"></i></button>
                        </div>
                        <input type="hidden" id="cliente_id" value="<?= $esEdicion ? (int)$factura['ven_cliente_id'] : '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small">Razon social</label>
                        <input type="text" id="razon_social_display" class="form-control form-control-sm" disabled
                            placeholder="Se carga al ingresar el RUC"
                            value="<?= $esEdicion ? htmlspecialchars($factura['ven_cliente_razon_social'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small">Observacion</label>
                        <input type="text" id="inpObservacion" class="form-control form-control-sm" maxlength="300"
                            placeholder="Opcional..." value="<?= $editObs ?>">
                    </div>
                </div>
                <!-- Fila 2: forma de pago + datos de contacto del cliente -->
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label mb-0 small">Forma de pago <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <select id="selFormaPago" class="form-select form-select-sm">
                                <?php foreach ($formasPago as $fp): ?>
                                <option value="<?= $fp['ven_forma_pago_id'] ?>"
                                    data-calculadora="<?= $fp['ven_forma_pago_calculadora'] ?? 'N' ?>"
                                    <?= $editFormaPagoId === (int)$fp['ven_forma_pago_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fp['ven_forma_pago_nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($permisosFormaPago['crear'] ?? false): ?>
                            <button class="btn btn-outline-secondary" type="button" id="btnAbrirFormasPago" title="Administrar formas de pago"><i class="bi bi-plus-lg"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small">Correo</label>
                        <input type="text" id="cliente_correo_display" class="form-control form-control-sm" disabled
                            placeholder="—"
                            value="<?= $esEdicion ? htmlspecialchars($factura['ven_cliente_email'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">Telefono</label>
                        <input type="text" id="cliente_telefono_display" class="form-control form-control-sm" disabled
                            placeholder="—"
                            value="<?= $esEdicion ? htmlspecialchars($factura['ven_cliente_telefono'] ?? '') : '' ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ DETALLE ═══ -->
        <div class="card mb-2">
            <div class="card-header d-flex align-items-center justify-content-between py-1 px-2">
                <span class="fw-semibold small"><i class="bi bi-list-ul me-1"></i>Detalle</span>
                <button type="button" class="btn btn-sm btn-intesis px-2" id="btnAgregarLinea"><i class="bi bi-plus"></i> Agregar linea</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaDetalle" class="table table-sm table-bordered mb-0" style="font-size:.81rem">
                        <thead class="table-light"><tr>
                            <th style="width:26px">#</th>
                            <th style="width:15%">Codigo</th>
                            <th>Descripcion</th>
                            <th style="width:8%" class="text-center">Stock</th>
                            <th style="width:9%" class="text-center">Cant.</th>
                            <th style="width:10%">Precio</th>
                            <th style="width:9%" class="text-center">Desc%</th>
                            <th style="width:90px">IVA</th>
                            <th style="width:10%" class="text-end">Total</th>
                            <th style="width:32px"></th>
                            <th class="d-none"></th>
                        </tr></thead>
                        <tbody id="cuerpoDetalle"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══ TOTALES ═══ -->
        <div class="row justify-content-end mb-3">
            <div class="col-md-4 col-lg-3">
                <table class="table table-sm table-bordered mb-0" style="font-size:.82rem">
                    <tr><td class="text-muted ps-2">Subtotal base 0%</td>  <td class="text-end pe-2 fw-semibold" id="resBase0">$ 0.00</td></tr>
                    <tr><td class="text-muted ps-2">Subtotal base IVA</td> <td class="text-end pe-2 fw-semibold" id="resBaseIva">$ 0.00</td></tr>
                    <tr><td class="text-muted ps-2">Descuento</td>         <td class="text-end pe-2 fw-semibold" id="resDescuento">$ 0.00</td></tr>
                    <tr><td class="text-muted ps-2">IVA</td>               <td class="text-end pe-2 fw-semibold" id="resIva">$ 0.00</td></tr>
                    <tr class="table-light"><td class="fw-bold ps-2">Total</td><td class="text-end pe-2 fw-bold" id="resTotal">$ 0.00</td></tr>
                </table>
            </div>
        </div>

    </div></div></main>
</div>

<!-- ═══ MODAL BUSCAR CLIENTE ═══ -->
<div class="modal fade modal-intesis" id="modalBuscarCliente" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Ventas</p><h2 class="modal-title h6 mb-0">Buscar Cliente</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="text" id="buscarClienteInput" class="form-control form-control-sm mb-2" placeholder="Buscar por RUC o razon social...">
                <div class="table-responsive" style="max-height:280px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light" style="position:sticky;top:0"><tr><th>#</th><th>Identificacion</th><th>Razon Social</th><th>Nombre Comercial</th></tr></thead>
                        <tbody id="cuerpoClientesModal"><tr><td colspan="4" class="text-center text-muted py-3">Escriba para buscar...</td></tr></tbody>
                    </table>
                </div>
                <div id="panelNuevoCliente" class="d-none mt-3 border rounded p-2" style="background:#f8f9fa">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-person-plus me-2 text-intesis"></i>
                        <span class="fw-semibold small">Crear cliente nuevo</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label mb-0 small">Tipo</label>
                            <select id="nc_tipo" class="form-control form-control-sm">
                                <option value="">Seleccione</option>
                                <option value="R">RUC</option>
                                <option value="C">Cedula</option>
                                <option value="P">Pasaporte</option>
                                <option value="O">Consumidor Final</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label mb-0 small">Numero de identificacion</label>
                            <input type="text" id="nc_identificacion" class="form-control form-control-sm" maxlength="20">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-0 small">Razon social</label>
                            <input type="text" id="nc_razon_social" class="form-control form-control-sm" maxlength="300">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small">Telefono</label>
                            <input type="text" id="nc_telefono" class="form-control form-control-sm" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small">Correo</label>
                            <input type="email" id="nc_correo" class="form-control form-control-sm" maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small">Direccion</label>
                            <input type="text" id="nc_direccion" class="form-control form-control-sm" maxlength="300">
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-1">
                            <button type="button" id="btnCancelarNuevoCliente" class="btn btn-sm btn-secundario"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                            <button type="button" id="btnGuardarNuevoCliente" class="btn btn-sm btn-intesis"><i class="bi bi-save2 me-1"></i>Guardar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL PRODUCTOS ═══ -->
<div class="modal fade modal-intesis" id="modalProductosCodigo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Inventario</p><h2 class="modal-title h6 mb-0">Buscar Producto</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="text" id="buscarCodigoInput" class="form-control form-control-sm mb-2" placeholder="Codigo o descripcion...">
                <div class="table-responsive" style="max-height:420px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light" style="position:sticky;top:0"><tr><th style="width:26px">#</th><th style="width:56px">Foto</th><th>Codigo</th><th>Nombre Producto</th><th class="text-end">PVP</th><th class="text-end">Stock</th></tr></thead>
                        <tbody id="cuerpoCodigoModal"><tr><td colspan="6" class="text-center text-muted py-3">Escriba para buscar...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL GALERÍA PRODUCTO ═══ -->
<div class="modal fade" id="modalGaleria" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Inventario</p><h2 class="modal-title h6 mb-0" id="modalGaleriaTitulo">Galeria de imagenes</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div id="galeriaGrid" class="d-flex flex-wrap gap-2 justify-content-center">
                    <div class="text-muted text-center w-100 py-4"><i class="bi bi-hourglass-split me-1"></i>Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ LIGHTBOX ═══ -->
<div id="lightboxOverlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.82);cursor:zoom-out;align-items:center;justify-content:center">
    <img id="lightboxImg" src="" alt="" style="max-width:90vw;max-height:90vh;border-radius:6px;box-shadow:0 4px 32px rgba(0,0,0,.6)">
</div>

<!-- ═══ MODAL APROBACION DE PRECIO ═══ -->
<div class="modal fade" id="modalAprobacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-shield-lock me-2 text-warning"></i>Precio por debajo del minimo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3" id="txtAprobacionInfo"></p>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Usuario aprobador</label>
                    <input type="text" id="inpAprobUser" class="form-control form-control-sm" autocomplete="off">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-semibold">Contrasena</label>
                    <input type="password" id="inpAprobClave" class="form-control form-control-sm">
                </div>
                <div id="txtAprobError" class="text-danger small mt-2" style="display:none"></div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-secundario btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning btn-sm" id="btnAprobConfirmar">Aprobar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL CONFIRMACION / CALCULADORA ═══ -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-receipt-cutoff me-2 text-intesis"></i>Confirmar Factura</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">Total</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="text" id="cfmTotal" class="form-control form-control-sm text-end fw-bold" readonly>
                    </div>
                </div>
                <div id="panelCalculadora" class="d-none">
                    <div class="row g-2 align-items-end">
                        <div class="col-6">
                            <label class="form-label small fw-semibold mb-1">Valor recibido</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" id="cfmRecibido" class="form-control form-control-sm text-end" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold mb-1">Cambio</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="text" id="cfmCambio" class="form-control form-control-sm text-end fw-semibold" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnFacturar" class="btn btn-sm btn-intesis"><i class="bi bi-receipt me-1"></i>Facturar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL FORMAS DE PAGO (mini CRUD) ═══ -->
<div class="modal fade modal-intesis" id="modalFormasPago" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Ventas</p><h2 class="modal-title h6 mb-0">Formas de pago</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <form id="formFormaPago" class="row g-2 align-items-end mb-3 border-bottom pb-3">
                    <input type="hidden" id="fp_id">
                    <div class="col-md-5">
                        <label class="form-label mb-0 small">Nombre</label>
                        <input type="text" id="fp_nombre" class="form-control form-control-sm" maxlength="100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small">Cod. SRI</label>
                        <input type="text" id="fp_codigo_sri" class="form-control form-control-sm" maxlength="2">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check form-switch switch-intesis mt-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="fp_calculadora">
                            <label class="form-check-label small" for="fp_calculadora">Calculadora</label>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="button" id="btnCancelarFormaPago" class="btn btn-sm btn-secundario flex-fill d-none">Cancelar</button>
                        <button type="submit" id="btnGuardarFormaPago" class="btn btn-sm btn-intesis flex-fill">Guardar</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light"><tr><th>Nombre</th><th>Cod. SRI</th><th class="text-center">Calculadora</th><th class="text-center">Estado</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody id="cuerpoFormasPagoModal"><tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const appUrl         = <?= json_encode($appUrl) ?>;
const esEdicion      = <?= $esEdicion ? 'true' : 'false' ?>;
const facturaId      = <?= $facturaId ?>;
const tipoDocFijo    = <?= json_encode($editTipoDoc) ?>;
const ivaList        = <?= json_encode(array_values($ivaList), JSON_UNESCAPED_UNICODE) ?>;
const ivaDefault     = <?= (int) $ivaDefault ?>;
const ivaDefaultVal  = <?= (float) $ivaDefaultValor ?>;
const lineasIni      = <?= $lineasJson ?>;
const permisosFormaPago = <?= json_encode($permisosFormaPago ?? [], JSON_UNESCAPED_UNICODE) ?>;

let lineaIdx   = 0;
let filaActual = null;

/* ── Helpers ─────────────────────────────────────────────────────────────── */
function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/"/g,'&quot;')
        .replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
const fmt2    = (n) => parseFloat(n || 0).toFixed(2);
const q       = (sel) => document.querySelector(sel);
const fetchJson = async (url) => { const r = await fetch(url); if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); };

/* ── Tipo documento: título dinámico ────────────────────────────────────── */
const titulosDoc = { FACTURA_VENTA: ['Nueva Factura', 'Editar Factura'], NOTA_VENTA: ['Nueva Nota de Venta', 'Editar Nota de Venta'] };
function actualizarTituloCard(tipo) {
    const titulo = q('#tituloCard');
    if (!titulo) return;
    const textos = titulosDoc[tipo] || titulosDoc['FACTURA_VENTA'];
    titulo.innerHTML = `<i class="bi bi-receipt me-1"></i>${esEdicion ? textos[1] : textos[0]}`;
}
if (!esEdicion) {
    q('#selTipoDoc')?.addEventListener('change', (e) => actualizarTituloCard(e.target.value));
}
actualizarTituloCard(tipoDocFijo);

/* ── IVA select options ─────────────────────────────────────────────────── */
function opcionesIva(seleccionado) {
    let html = '<option value="" data-valor="0">0 %</option>';
    ivaList.forEach(iv => {
        const sel = parseInt(iv.sis_iva_id) === seleccionado ? 'selected' : '';
        html += `<option value="${iv.sis_iva_id}" data-valor="${iv.sis_iva_valor}" ${sel}>${parseFloat(iv.sis_iva_valor).toFixed(0)} %</option>`;
    });
    return html;
}

/* ── Crear fila ─────────────────────────────────────────────────────────── */
function crearFila(datos) {
    const idx = lineaIdx++;
    const tr  = document.createElement('tr');
    tr.dataset.idx        = idx;
    tr.dataset.productoId = String(datos.inv_producto_id || '');
    tr.dataset.pvp        = String(datos.pvp || 0);
    tr.dataset.precioMin  = String(datos.precio_min || 0);
    tr.dataset.aprobadoPor = String(datos.aprobado_por || 0);

    tr.innerHTML = `
      <td class="text-center text-muted small align-middle num-linea"></td>
      <td data-label="Codigo">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control form-control-sm inp-codigo" placeholder="Codigo" autocomplete="off" value="${esc(datos.codigo_interno||datos.codigo||'')}">
          <span class="input-group-text inp-codigo-lock d-none" style="color:#6c757d;cursor:default" title="Producto cargado"><i class="bi bi-lock-fill" style="font-size:.75rem"></i></span>
        </div>
      </td>
      <td data-label="Descripcion"><input type="text" class="form-control form-control-sm inp-descripcion" value="${esc(datos.producto_nombre||datos.descripcion||'')}" disabled></td>
      <td class="text-center align-middle inp-stock-display" data-label="Stock" style="font-size:.79rem;color:#666">${datos.inv_stock_cantidad_disponible !== undefined ? parseFloat(datos.inv_stock_cantidad_disponible).toFixed(2) : '—'}</td>
      <td class="text-center" data-label="Cant."><input type="number" class="form-control form-control-sm text-center inp-cantidad" value="${datos.cantidad||1}" min="0.0001" step="0.0001"></td>
      <td data-label="Precio"><input type="number" class="form-control form-control-sm inp-precio" value="${parseFloat(datos.precio||datos.pvp||0).toFixed(4)}" min="0" step="0.000001"></td>
      <td class="text-center" data-label="Desc%"><input type="number" class="form-control form-control-sm text-center inp-descuento" value="${parseFloat(datos.descuento||0).toFixed(2)}" min="0" max="100" step="0.01"></td>
      <td data-label="IVA">
        <select class="form-control form-control-sm inp-iva">
          ${opcionesIva(parseInt(datos.iva_id) || ivaDefault)}
        </select>
        <input type="hidden" class="inp-iva-valor" value="${datos.iva_valor !== undefined ? datos.iva_valor : ivaDefaultVal}">
        <input type="hidden" class="inp-precio-min" value="${parseFloat(datos.precio_min||0).toFixed(6)}">
        <input type="hidden" class="inp-aprobado-por" value="${parseInt(datos.aprobado_por||0)}">
      </td>
      <td class="text-end fw-semibold align-middle inp-total-display" data-label="Total">$ 0.00</td>
      <td class="text-center align-middle">
        <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-linea p-0 px-1" tabindex="-1">
          <i class="bi bi-trash" style="font-size:.75rem"></i>
        </button>
      </td>
      <td class="d-none">
        <input type="hidden" class="inp-producto-id" value="${esc(datos.inv_producto_id||'')}">
        <input type="hidden" class="inp-pvp-ref" value="${parseFloat(datos.pvp||0).toFixed(4)}">
        <input type="hidden" class="inp-stock" value="${datos.inv_stock_cantidad_disponible !== undefined ? parseFloat(datos.inv_stock_cantidad_disponible) : ''}">
      </td>`;

    document.getElementById('cuerpoDetalle').appendChild(tr);
    calcularFila(tr);
    actualizarNumeros();

    if (datos.inv_producto_id) {
        const inpCod = tr.querySelector('.inp-codigo');
        inpCod.disabled = true;
        inpCod.classList.add('bg-light');
        tr.querySelector('.inp-codigo-lock')?.classList.remove('d-none');
    }

    let busquedaDirecta = false;
    tr.querySelector('.inp-codigo').addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        e.stopPropagation();
        if (tr.querySelector('.inp-producto-id').value) return;
        const val = e.target.value.trim();
        if (!val) return;
        busquedaDirecta = true;
        filaActual = tr;
        try {
            const json = await fetchJson(appUrl + '/ventas/facturas/productos?q=' + encodeURIComponent(val));
            const lista = json.ok ? (json.data?.productos || []) : [];
            if (lista.length === 1) {
                cargarProductoEnFila(tr, lista[0]);
                filaActual = null;
            } else {
                abrirModalCodigo(val);
            }
        } catch {
            abrirModalCodigo(val);
        } finally {
            busquedaDirecta = false;
        }
    });
    tr.querySelector('.inp-codigo').addEventListener('focusout', (e) => {
        setTimeout(() => {
            if (!tr.isConnected) return;
            if (busquedaDirecta) return;
            const val = e.target.value.trim();
            if (!val) return;
            if (tr.querySelector('.inp-producto-id').value) return;
            filaActual = tr;
            abrirModalCodigo(val);
        }, 250);
    });

    tr.querySelector('.inp-precio').addEventListener('change', async (e) => {
        const precio    = parseFloat(e.target.value) || 0;
        const precioMin = parseFloat(tr.querySelector('.inp-precio-min').value) || 0;
        const pvp       = parseFloat(tr.dataset.pvp) || 0;
        if (precioMin > 0 && precio < precioMin) {
            const aprobadoPor = await solicitarAprobacion(pvp, precioMin, precio);
            if (!aprobadoPor) {
                e.target.value = precioMin.toFixed(6);
            } else {
                tr.querySelector('.inp-aprobado-por').value = aprobadoPor;
                tr.dataset.aprobadoPor = aprobadoPor;
            }
        }
        calcularFila(tr);
    });

    return tr;
}

/* ── Calcular fila ──────────────────────────────────────────────────────── */
function calcularFila(tr) {
    const qty   = parseFloat(tr.querySelector('.inp-cantidad')?.value) || 0;
    const precio= parseFloat(tr.querySelector('.inp-precio')?.value)   || 0;
    const desc  = parseFloat(tr.querySelector('.inp-descuento')?.value)|| 0;
    const ivaS  = tr.querySelector('.inp-iva');
    const opt   = ivaS?.options[ivaS?.selectedIndex];
    const ivaV  = parseFloat(opt?.dataset?.valor || 0);
    tr.querySelector('.inp-iva-valor').value = ivaV;
    const dtoVal= parseFloat((qty * precio * desc / 100).toFixed(4));
    const base  = Math.max(0, qty * precio - dtoVal);
    const iva   = base * ivaV / 100;
    tr.querySelector('.inp-total-display').textContent = '$ ' + fmt2(base + iva);
    calcularTotales();
}

/* ── Calcular totales ───────────────────────────────────────────────────── */
function calcularTotales() {
    let base0 = 0, baseIva = 0, totalDesc = 0, totalIva = 0;
    document.querySelectorAll('#cuerpoDetalle tr').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.inp-cantidad')?.value) || 0;
        const precio= parseFloat(tr.querySelector('.inp-precio')?.value)   || 0;
        const desc  = parseFloat(tr.querySelector('.inp-descuento')?.value)|| 0;
        const ivaS  = tr.querySelector('.inp-iva');
        const opt   = ivaS?.options[ivaS?.selectedIndex];
        const ivaV  = parseFloat(opt?.dataset?.valor || 0);
        const dtoVal= parseFloat((qty * precio * desc / 100).toFixed(4));
        const base  = Math.max(0, qty * precio - dtoVal);
        totalDesc  += dtoVal;
        if (ivaV > 0) baseIva += base; else base0 += base;
        totalIva   += base * ivaV / 100;
    });
    const total = base0 + baseIva + totalIva;
    q('#resBase0').textContent     = '$ ' + fmt2(base0);
    q('#resBaseIva').textContent   = '$ ' + fmt2(baseIva);
    q('#resDescuento').textContent = '$ ' + fmt2(totalDesc);
    q('#resIva').textContent       = '$ ' + fmt2(totalIva);
    q('#resTotal').textContent     = '$ ' + fmt2(total);
}

function actualizarNumeros() {
    document.querySelectorAll('#cuerpoDetalle tr').forEach((tr, i) => {
        const n = tr.querySelector('.num-linea');
        if (n) n.textContent = i + 1;
    });
}

/* ── Eventos delegados en la tabla ─────────────────────────────────────── */
const cuerpoDetalle = document.getElementById('cuerpoDetalle');

cuerpoDetalle.addEventListener('input', (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.matches('.inp-cantidad,.inp-descuento')) calcularFila(tr);
});
cuerpoDetalle.addEventListener('change', (e) => {
    const tr = e.target.closest('tr');
    if (tr && e.target.matches('.inp-iva')) calcularFila(tr);
});
cuerpoDetalle.addEventListener('click', (e) => {
    if (e.target.closest('.btn-eliminar-linea')) {
        e.target.closest('tr').remove();
        actualizarNumeros();
        calcularTotales();
    }
});

q('#btnAgregarLinea').addEventListener('click', () => crearFila({}));

/* ── Cargar producto en fila ────────────────────────────────────────────── */
function cargarProductoEnFila(tr, prod) {
    tr.dataset.productoId = String(prod.inv_producto_id || '');
    const pvp       = parseFloat(prod.pvp || 0);
    const descMax   = parseFloat(prod.descuento_max || 0);
    const precioMin = descMax > 0 ? parseFloat((pvp * (1 - descMax / 100)).toFixed(6)) : 0;
    const stock     = parseFloat(prod.stock_total || prod.inv_stock_cantidad_disponible || 0);
    tr.dataset.pvp       = pvp;
    tr.dataset.precioMin = precioMin;
    const inpCodigo = tr.querySelector('.inp-codigo');
    inpCodigo.value    = prod.codigo_interno || '';
    inpCodigo.disabled = true;
    inpCodigo.classList.add('bg-light');
    tr.querySelector('.inp-codigo-lock')?.classList.remove('d-none');
    tr.querySelector('.inp-descripcion').value      = prod.inv_producto_nombre || '';
    tr.querySelector('.inp-pvp-ref').value          = pvp.toFixed(4);
    tr.querySelector('.inp-precio').value           = pvp.toFixed(4);
    tr.querySelector('.inp-descuento').value         = '0.00';
    tr.querySelector('.inp-precio-min').value        = precioMin.toFixed(6);
    tr.querySelector('.inp-aprobado-por').value      = '0';
    tr.querySelector('.inp-producto-id').value       = String(prod.inv_producto_id || '');
    tr.querySelector('.inp-stock').value             = stock;
    tr.querySelector('.inp-stock-display').textContent = stock.toFixed(2);
    calcularFila(tr);
}

/* ── RUC / Cliente ──────────────────────────────────────────────────────── */
async function ejecutarBusquedaRuc() {
    const ruc = q('#ruc_input').value.trim();
    if (!ruc) { abrirModalCliente(''); return; }
    try {
        const json = await fetchJson(appUrl + '/ventas/facturas/buscar-cliente?ruc=' + encodeURIComponent(ruc));
        if (json.ok && json.data?.cliente) cargarCliente(json.data.cliente);
        else abrirModalCliente(ruc);
    } catch { abrirModalCliente(ruc); }
}

q('#ruc_input').addEventListener('keydown', (e) => { if (e.key === 'Enter') ejecutarBusquedaRuc(); });
q('#btnBuscarRuc').addEventListener('click', ejecutarBusquedaRuc);

function cargarCliente(c) {
    q('#cliente_id').value            = c.ven_cliente_id;
    q('#ruc_input').value             = c.ven_cliente_identificacion;
    q('#razon_social_display').value  = c.ven_cliente_razon_social;
    q('#cliente_correo_display').value    = c.ven_cliente_email    || '';
    q('#cliente_telefono_display').value  = c.ven_cliente_telefono || '';
}

/* ── Modal buscar cliente ─────────────────────────────────────────────── */
let timerCliente = null;

function abrirModalCliente(texto) {
    q('#buscarClienteInput').value = texto;
    bootstrap.Modal.getOrCreateInstance(q('#modalBuscarCliente')).show();
    buscarClientes(texto);
    setTimeout(() => q('#buscarClienteInput')?.focus(), 300);
}

let _terminoSinResultado = '';

function ocultarPanelNuevoCliente() {
    q('#panelNuevoCliente').classList.add('d-none');
    ['nc_tipo','nc_identificacion','nc_razon_social','nc_telefono','nc_correo','nc_direccion'].forEach(id => {
        const el = q('#' + id);
        if (el) { el.value = ''; el.disabled = false; }
    });
}

function expandirFormularioNuevoCliente() {
    const panel  = q('#panelNuevoCliente');
    const nc_id  = q('#nc_identificacion');
    const nc_tipo = q('#nc_tipo');
    const limpio = _terminoSinResultado.trim().replace(/\D/g, '');
    nc_id.value   = limpio || _terminoSinResultado.trim();
    nc_id.disabled = false;
    if      (limpio.length === 13) nc_tipo.value = 'R';
    else if (limpio.length === 10) nc_tipo.value = 'C';
    else                           nc_tipo.value = '';
    panel.classList.remove('d-none');
    q('#btnAbrirNuevoCliente')?.classList.add('d-none');
    setTimeout(() => q('#nc_razon_social')?.focus(), 50);
}

async function buscarClientes(term) {
    const tbody = q('#cuerpoClientesModal');
    ocultarPanelNuevoCliente();
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Buscando...</td></tr>';
    try {
        const json = await fetchJson(appUrl + '/ventas/facturas/buscar-clientes?q=' + encodeURIComponent(term));
        const lista = json.ok ? (json.data?.clientes || []) : [];
        if (!lista.length) {
            _terminoSinResultado = term;
            tbody.innerHTML = `<tr><td colspan="4" class="py-2 text-center">
                <span class="text-muted me-2">Sin resultados para "<strong>${esc(term)}</strong>"</span>
                <button type="button" class="btn btn-sm btn-intesis" id="btnAbrirNuevoCliente">
                    <i class="bi bi-person-plus me-1"></i>Crear cliente
                </button>
            </td></tr>`;
            q('#btnAbrirNuevoCliente').addEventListener('click', expandirFormularioNuevoCliente);
            return;
        }
        tbody.innerHTML = lista.map((c, i) => `
            <tr style="cursor:pointer" data-id="${c.ven_cliente_id}"
                data-ruc="${esc(c.ven_cliente_identificacion)}"
                data-razon="${esc(c.ven_cliente_razon_social)}"
                data-correo="${esc(c.ven_cliente_email||'')}"
                data-telefono="${esc(c.ven_cliente_telefono||'')}">
              <td>${i+1}</td>
              <td>${esc(c.ven_cliente_identificacion)}</td>
              <td>${esc(c.ven_cliente_razon_social)}</td>
              <td>${esc(c.ven_cliente_nombre_comercial||'—')}</td>
            </tr>`).join('');
    } catch {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error al buscar</td></tr>';
    }
}

q('#buscarClienteInput').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    clearTimeout(timerCliente);
    buscarClientes(e.target.value);
});
q('#buscarClienteInput').addEventListener('input', (e) => {
    clearTimeout(timerCliente);
    timerCliente = setTimeout(() => buscarClientes(e.target.value), 300);
});
q('#cuerpoClientesModal').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    cargarCliente({
        ven_cliente_id: tr.dataset.id,
        ven_cliente_identificacion: tr.dataset.ruc,
        ven_cliente_razon_social: tr.dataset.razon,
        ven_cliente_email: tr.dataset.correo,
        ven_cliente_telefono: tr.dataset.telefono,
    });
    bootstrap.Modal.getInstance(q('#modalBuscarCliente'))?.hide();
});
q('#modalBuscarCliente').addEventListener('hidden.bs.modal', () => ocultarPanelNuevoCliente());
q('#btnCancelarNuevoCliente').addEventListener('click', () => ocultarPanelNuevoCliente());

q('#nc_tipo').addEventListener('change', function () {
    const nc_id = q('#nc_identificacion');
    if (this.value === 'O') {
        nc_id.value    = '9999999999999';
        nc_id.disabled = true;
    } else {
        nc_id.disabled = false;
        if (nc_id.value === '9999999999999') nc_id.value = '';
    }
});

q('#btnGuardarNuevoCliente').addEventListener('click', async () => {
    const tipo      = q('#nc_tipo').value.trim();
    const idNum     = q('#nc_identificacion').value.trim();
    const razon     = q('#nc_razon_social').value.trim();
    const telefono  = q('#nc_telefono').value.trim();
    const email     = q('#nc_correo').value.trim();
    const direccion = q('#nc_direccion').value.trim();

    if (!tipo)     { Swal.fire({ icon:'warning', title:'Datos incompletos', text:'Seleccione el tipo de identificacion.', toast:true, position:'top-end', showConfirmButton:false, timer:3000 }); return; }
    if (!idNum)    { Swal.fire({ icon:'warning', title:'Datos incompletos', text:'Ingrese el numero de identificacion.',  toast:true, position:'top-end', showConfirmButton:false, timer:3000 }); return; }
    if (!razon)    { Swal.fire({ icon:'warning', title:'Datos incompletos', text:'La razon social es obligatoria.',       toast:true, position:'top-end', showConfirmButton:false, timer:3000 }); return; }
    if (!telefono) { Swal.fire({ icon:'warning', title:'Datos incompletos', text:'El telefono es obligatorio.',           toast:true, position:'top-end', showConfirmButton:false, timer:3000 }); return; }
    if (!email)    { Swal.fire({ icon:'warning', title:'Datos incompletos', text:'El correo es obligatorio.',             toast:true, position:'top-end', showConfirmButton:false, timer:3000 }); return; }
    if (!direccion){ Swal.fire({ icon:'warning', title:'Datos incompletos', text:'La direccion es obligatoria.',          toast:true, position:'top-end', showConfirmButton:false, timer:3000 }); return; }

    const btn = q('#btnGuardarNuevoCliente');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    try {
        const form = new FormData();
        form.append('tipo_identificacion', tipo);
        form.append('identificacion',      idNum);
        form.append('razon_social',        razon);
        form.append('nombre_comercial',    '');
        form.append('telefono',            telefono);
        form.append('email',               email);
        form.append('direccion',           direccion);

        const resp = await fetch(appUrl + '/ventas/clientes/crear-rapido', { method:'POST', body: form });
        const json = await resp.json();
        if (!json.ok) throw new Error(json.mensaje || 'Error al crear cliente');

        cargarCliente({
            ven_cliente_id:             json.data.ven_cliente_id,
            ven_cliente_identificacion: json.data.ven_cliente_identificacion,
            ven_cliente_razon_social:   json.data.ven_cliente_razon_social,
        });
        bootstrap.Modal.getInstance(q('#modalBuscarCliente'))?.hide();
        Swal.fire({ icon:'success', title:'Cliente creado', text:'El cliente fue registrado y cargado en la factura.', toast:true, position:'top-end', showConfirmButton:false, timer:3000 });
    } catch(err) {
        Swal.fire({ icon:'error', title:'No se pudo crear', text: err.message, toast:true, position:'top-end', showConfirmButton:false, timer:4000 });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save2 me-1"></i>Guardar';
    }
});

/* ── Modal productos ─────────────────────────────────────────────────── */
let timerCodigo = null;

function abrirModalCodigo(texto) {
    q('#buscarCodigoInput').value = texto;
    bootstrap.Modal.getOrCreateInstance(q('#modalProductosCodigo')).show();
    buscarPorCodigo(texto);
    setTimeout(() => q('#buscarCodigoInput')?.focus(), 300);
}

async function buscarPorCodigo(term) {
    const tbody = q('#cuerpoCodigoModal');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Buscando...</td></tr>';
    try {
        const json = await fetchJson(appUrl + '/ventas/facturas/productos?q=' + encodeURIComponent(term));
        const lista = json.ok ? (json.data?.productos || []) : [];
        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin resultados</td></tr>';
            return;
        }
        tbody.innerHTML = lista.map((p, i) => {
            const safeJson = JSON.stringify(p).replace(/'/g, '&#39;');
            const imgId    = parseInt(p.imagen_principal_id || 0);
            const imgCell  = imgId
                ? `<button type="button" class="btn btn-sm btn-outline-secondary p-0 px-1 img-thumb-modal"
                        data-producto-id="${p.inv_producto_id}" title="Ver galeria" style="font-size:.75rem">
                        <i class="bi bi-images"></i>
                   </button>`
                : `<span class="text-muted" style="font-size:.7rem">—</span>`;
            return `<tr style="cursor:pointer" data-prod='${safeJson}'>
              <td class="text-muted">${i+1}</td>
              <td class="text-center align-middle">${imgCell}</td>
              <td>${esc(p.codigo_interno||'')}</td>
              <td>${esc(p.inv_producto_nombre||'')}</td>
              <td class="text-end">$ ${parseFloat(p.pvp||0).toFixed(2)}</td>
              <td class="text-end">${parseFloat(p.stock_total||0).toFixed(2)}</td>
            </tr>`;
        }).join('');
    } catch {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al buscar</td></tr>';
    }
}
q('#buscarCodigoInput').addEventListener('input', (e) => {
    clearTimeout(timerCodigo);
    timerCodigo = setTimeout(() => buscarPorCodigo(e.target.value), 300);
});
q('#cuerpoCodigoModal').addEventListener('click', (e) => {
    const btnGal = e.target.closest('.img-thumb-modal');
    if (btnGal) { abrirGaleria(parseInt(btnGal.dataset.productoId)); return; }
    const tr = e.target.closest('tr[data-prod]');
    if (!tr || !filaActual) return;
    try { cargarProductoEnFila(filaActual, JSON.parse(tr.dataset.prod)); } catch(ex) { console.error(ex); }
    filaActual = null;
    bootstrap.Modal.getInstance(q('#modalProductosCodigo'))?.hide();
});
q('#modalProductosCodigo').addEventListener('hidden.bs.modal', () => { filaActual = null; });

/* ── Galería ────────────────────────────────────────────────────────────── */
async function abrirGaleria(productoId) {
    const grid = q('#galeriaGrid');
    grid.innerHTML = '<div class="text-muted text-center w-100 py-4"><i class="bi bi-hourglass-split me-1"></i>Cargando...</div>';
    bootstrap.Modal.getOrCreateInstance(q('#modalGaleria')).show();
    try {
        const json = await fetchJson(appUrl + '/inventario/productos/archivos/listar?producto_id=' + productoId);
        const imgs = json.data?.imagenes || [];
        if (!imgs.length) {
            grid.innerHTML = '<div class="text-muted text-center w-100 py-4"><i class="bi bi-image me-1"></i>Sin imagenes registradas</div>';
            return;
        }
        grid.innerHTML = imgs.map(img => `
            <div class="galeria-item text-center" style="cursor:zoom-in" data-url="${esc(img.url)}">
                <img src="${esc(img.url)}" alt=""
                     style="width:140px;height:140px;object-fit:contain;border:2px solid ${img.principal ? '#1f6f68' : '#dee2e6'};border-radius:6px;background:#f8f9fa;padding:4px;pointer-events:none">
                ${img.principal ? '<div style="font-size:.7rem;color:#1f6f68;margin-top:2px"><i class="bi bi-star-fill"></i> Principal</div>' : ''}
            </div>`).join('');
    } catch {
        grid.innerHTML = '<div class="text-danger text-center w-100 py-4">Error al cargar imagenes</div>';
    }
}
q('#galeriaGrid').addEventListener('click', (e) => {
    const item = e.target.closest('.galeria-item');
    if (item) abrirLightbox(item.dataset.url);
});

/* ── Lightbox ────────────────────────────────────────────────────────────── */
function abrirLightbox(src) {
    q('#lightboxImg').src = src;
    q('#lightboxOverlay').style.display = 'flex';
}
q('#lightboxOverlay').addEventListener('click', () => {
    q('#lightboxOverlay').style.display = 'none';
    q('#lightboxImg').src = '';
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const ov = q('#lightboxOverlay');
        if (ov.style.display !== 'none') { ov.style.display = 'none'; q('#lightboxImg').src = ''; }
    }
});

/* ── Aprobacion de precio ─────────────────────────────────────────────── */
let resolveAprobacion = null;

async function solicitarAprobacion(pvp, precioMin, precioSolicitado) {
    q('#txtAprobacionInfo').textContent =
        `PVP: $${parseFloat(pvp).toFixed(4)} | Minimo: $${parseFloat(precioMin).toFixed(4)} | Solicitado: $${parseFloat(precioSolicitado).toFixed(4)}`;
    q('#inpAprobUser').value  = '';
    q('#inpAprobClave').value = '';
    q('#txtAprobError').style.display = 'none';
    const modal = new bootstrap.Modal(q('#modalAprobacion'));
    modal.show();
    return new Promise(resolve => {
        resolveAprobacion = resolve;
        q('#btnAprobConfirmar').onclick = async () => {
            const user  = q('#inpAprobUser').value.trim();
            const clave = q('#inpAprobClave').value;
            if (!user || !clave) { mostrarErrorAprob('Ingrese usuario y contrasena.'); return; }
            q('#btnAprobConfirmar').disabled = true;
            try {
                const resp = await fetch(appUrl + '/ventas/proformas/verificar-aprobacion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ usuario: user, clave }),
                });
                const data = await resp.json();
                if (data.ok) { modal.hide(); resolve(data.data.aprobador_id); }
                else mostrarErrorAprob(data.mensaje);
            } catch { mostrarErrorAprob('Error de conexion.'); }
            q('#btnAprobConfirmar').disabled = false;
        };
        q('#modalAprobacion').addEventListener('hidden.bs.modal', () => {
            if (resolveAprobacion) { resolveAprobacion(null); resolveAprobacion = null; }
        }, { once: true });
    });
}
function mostrarErrorAprob(msg) {
    const el = q('#txtAprobError');
    el.textContent   = msg;
    el.style.display = 'block';
}

/* ── Calculadora en modal confirmacion ──────────────────────────────────── */
q('#cfmRecibido').addEventListener('input', () => {
    const total    = parseFloat(q('#cfmTotal').value.replace(',', '.')) || 0;
    const recibido = parseFloat(q('#cfmRecibido').value) || 0;
    const cambio   = Math.max(0, recibido - total);
    q('#cfmCambio').value = cambio.toFixed(2);
});

/* ── Construir payload para guardar ─────────────────────────────────────── */
function construirPayload() {
    const clienteId = parseInt(q('#cliente_id').value || 0);
    if (!clienteId) return null;
    const filas = document.querySelectorAll('#cuerpoDetalle tr');
    if (!filas.length) return null;

    let base0 = 0, baseIva = 0, totalDesc = 0, totalIva = 0;
    const lineas = [];
    filas.forEach(tr => {
        const productoId = tr.querySelector('.inp-producto-id').value;
        const codigo     = tr.querySelector('.inp-codigo').value.trim();
        const descripcion= tr.querySelector('.inp-descripcion').value.trim();
        const cantidad   = parseFloat(tr.querySelector('.inp-cantidad').value) || 0;
        const precio     = parseFloat(tr.querySelector('.inp-precio').value)   || 0;
        const descPct    = parseFloat(tr.querySelector('.inp-descuento').value)|| 0;
        const ivaS       = tr.querySelector('.inp-iva');
        const ivaId      = ivaS?.value ? parseInt(ivaS.value) : 0;
        const ivaV       = parseFloat(ivaS?.options[ivaS?.selectedIndex]?.dataset?.valor || 0);
        const pvp        = parseFloat(tr.querySelector('.inp-pvp-ref').value)  || 0;
        const precioMin  = parseFloat(tr.querySelector('.inp-precio-min').value) || 0;
        const aprobadoPor= parseInt(tr.querySelector('.inp-aprobado-por').value) || 0;
        const dtoVal     = parseFloat((cantidad * precio * descPct / 100).toFixed(4));
        const base       = Math.max(0, cantidad * precio - dtoVal);
        const iva        = base * ivaV / 100;
        totalDesc += dtoVal;
        if (ivaV > 0) baseIva += base; else base0 += base;
        totalIva  += iva;
        lineas.push({ producto_id: parseInt(productoId)||0, codigo, descripcion, cantidad, precio,
            descuento: descPct, descuento_valor: dtoVal, total: parseFloat(base.toFixed(4)),
            iva_id: ivaId, iva_valor: parseFloat(iva.toFixed(4)), pvp, precio_min: precioMin, aprobado_por: aprobadoPor });
    });
    const total    = base0 + baseIva + totalIva;
    const subtotal = base0 + baseIva;

    return {
        factura_id:    esEdicion ? facturaId : 0,
        empresa_id:    parseInt(q('#selEmpresa')?.value || 0),
        cliente_id:    clienteId,
        fecha_emision: q('#inpFecha').value,
        observacion:   q('#inpObservacion').value.trim(),
        tipo_doc:      esEdicion ? tipoDocFijo : (q('#selTipoDoc')?.value || 'FACTURA_VENTA'),
        forma_pago_id: parseInt(q('#selFormaPago').value || 0),
        subtotal:      parseFloat(subtotal.toFixed(4)),
        descuento:     parseFloat(totalDesc.toFixed(4)),
        iva:           parseFloat(totalIva.toFixed(4)),
        total:         parseFloat(total.toFixed(4)),
        lineas,
    };
}

async function ejecutarGuardar(payload) {
    const btn = q('#btnFacturar');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'; }
    q('#btnGuardar').disabled = true;
    try {
        const resp = await fetch(appUrl + '/ventas/facturas/guardar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await resp.json();
        if (data.ok) {
            bootstrap.Modal.getInstance(q('#modalConfirmacion'))?.hide();
            await Swal.fire({ title: 'Guardado', text: data.mensaje, icon: 'success', timer: 1500, showConfirmButton: false });
            window.location.href = appUrl + '/ventas/facturas';
        } else {
            Swal.fire({ title: 'Error', text: data.mensaje, icon: 'error', confirmButtonColor: '#1f6f68' });
        }
    } catch {
        Swal.fire({ title: 'Error', text: 'Error de conexion.', icon: 'error', confirmButtonColor: '#1f6f68' });
    }
    q('#btnGuardar').disabled = false;
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-receipt me-1"></i>Facturar'; }
}

/* ── Guardar (boton principal) ──────────────────────────────────────────── */
q('#btnGuardar').addEventListener('click', async () => {
    const clienteId = parseInt(q('#cliente_id').value || 0);
    if (!clienteId) {
        Swal.fire({ title: 'Error', text: 'Seleccione un cliente.', icon: 'warning', confirmButtonColor: '#1f6f68' });
        q('#ruc_input').focus();
        return;
    }
    const filas = document.querySelectorAll('#cuerpoDetalle tr');
    if (!filas.length) {
        Swal.fire({ title: 'Error', text: 'Agregue al menos un producto.', icon: 'warning', confirmButtonColor: '#1f6f68' });
        return;
    }

    const payload = construirPayload();
    if (!payload) return;

    /* Verificar si la forma de pago tiene calculadora */
    const selFP  = q('#selFormaPago');
    const optFP  = selFP?.options[selFP?.selectedIndex];
    const calc   = optFP?.dataset?.calculadora || 'N';

    if (calc === 'S') {
        /* Mostrar modal de confirmacion con calculadora */
        q('#cfmTotal').value   = payload.total.toFixed(2);
        q('#cfmRecibido').value = '';
        q('#cfmCambio').value  = '';
        q('#panelCalculadora').classList.remove('d-none');
        bootstrap.Modal.getOrCreateInstance(q('#modalConfirmacion')).show();
        setTimeout(() => q('#cfmRecibido')?.focus(), 300);

        q('#btnFacturar').onclick = () => ejecutarGuardar(payload);
    } else {
        /* Sin calculadora: modal sin panel, confirmar y guardar */
        q('#cfmTotal').value = payload.total.toFixed(2);
        q('#panelCalculadora').classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(q('#modalConfirmacion')).show();

        q('#btnFacturar').onclick = () => ejecutarGuardar(payload);
    }
});

/* ── Inicializar lineas en edicion ──────────────────────────────────────── */
<?php if ($esEdicion && !empty($lineas)): ?>
<?php foreach ($lineas as $linea): ?>
crearFila(<?= json_encode([
    'inv_producto_id'                   => (int)   $linea['inv_producto_id'],
    'codigo_interno'                    => (string)($linea['ven_documento_detalle_codigo']        ?? $linea['codigo_interno'] ?? ''),
    'descripcion'                       => (string)($linea['ven_documento_detalle_descripcion']   ?? $linea['producto_nombre'] ?? ''),
    'cantidad'                          => (float)  $linea['ven_documento_detalle_cantidad'],
    'precio'                            => (float)  ($linea['ven_documento_detalle_precio_unitario'] ?? $linea['ven_documento_detalle_precio'] ?? 0),
    'descuento'                         => (float)  $linea['ven_documento_detalle_descuento'],
    'pvp'                               => (float)  ($linea['ven_documento_detalle_pvp']       ?? 0),
    'precio_min'                        => (float)  ($linea['ven_documento_detalle_precio_min'] ?? 0),
    'iva_id'                            => (int)    ($linea['sis_iva_id']                       ?? 0),
    'iva_valor'                         => (float)  ($linea['ven_documento_detalle_iva_valor']  ?? 0),
    'aprobado_por'                      => (int)    ($linea['ven_documento_detalle_aprobado_por'] ?? 0),
    'inv_stock_cantidad_disponible'     => isset($linea['inv_stock_cantidad_disponible']) ? (float)$linea['inv_stock_cantidad_disponible'] : null,
], JSON_UNESCAPED_UNICODE) ?>);
<?php endforeach; ?>
<?php else: ?>
crearFila({});
<?php endif; ?>

<?php if ($esEdicion): ?>
(function() {
    const rucInp = q('#ruc_input');
    const cliId  = q('#cliente_id');
    const razon  = q('#razon_social_display');
    if (rucInp) rucInp.value = <?= json_encode((string)($factura['ven_cliente_identificacion'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    if (cliId)  cliId.value  = <?= (int)($factura['ven_cliente_id'] ?? 0) ?>;
    if (razon)  razon.value  = <?= json_encode((string)($factura['ven_cliente_razon_social'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
})();
<?php endif; ?>

/* ── Mini CRUD: Formas de pago ────────────────────────────────────────── */
const btnAbrirFormasPago = q('#btnAbrirFormasPago');
if (btnAbrirFormasPago) {
    let formaPagoEditandoId = null;

    function resetFormularioFormaPago() {
        formaPagoEditandoId = null;
        q('#fp_id').value = '';
        q('#fp_nombre').value = '';
        q('#fp_codigo_sri').value = '';
        q('#fp_calculadora').checked = false;
        q('#btnCancelarFormaPago').classList.add('d-none');
        q('#btnGuardarFormaPago').innerHTML = 'Guardar';
    }

    function sincronizarSelectFormaPago(lista, seleccionarId) {
        const select = q('#selFormaPago');
        const valorPrevio = select.value;
        select.innerHTML = '';
        lista.filter(fp => fp.ven_forma_pago_estado === 'A').forEach(fp => {
            const opcion = new Option(fp.ven_forma_pago_nombre, fp.ven_forma_pago_id);
            opcion.dataset.calculadora = fp.ven_forma_pago_calculadora || 'N';
            select.add(opcion);
        });
        const idFinal = seleccionarId ? String(seleccionarId) : valorPrevio;
        if ([...select.options].some(o => o.value === idFinal)) {
            select.value = idFinal;
        }
    }

    function renderFormasPago(lista) {
        const tbody = q('#cuerpoFormasPagoModal');
        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin formas de pago</td></tr>';
            return;
        }
        tbody.innerHTML = lista.map(fp => {
            const activo = fp.ven_forma_pago_estado === 'A';
            let acciones = '';
            if (permisosFormaPago.editar) {
                acciones += `<button type="button" class="btn btn-sm btn-outline-secondary btn-editar-forma-pago" data-id="${fp.ven_forma_pago_id}" title="Editar"><i class="bi bi-pencil"></i></button> `;
            }
            if (permisosFormaPago.inactivar) {
                acciones += `<button type="button" class="btn btn-sm btn-outline-secondary btn-toggle-forma-pago" data-id="${fp.ven_forma_pago_id}" data-activo="${activo ? '1' : '0'}" title="${activo ? 'Inactivar' : 'Activar'}"><i class="bi ${activo ? 'bi-toggle-on' : 'bi-toggle-off'}"></i></button> `;
            }
            if (permisosFormaPago.eliminar) {
                acciones += `<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-forma-pago" data-id="${fp.ven_forma_pago_id}" title="Eliminar"><i class="bi bi-trash"></i></button>`;
            }
            return `<tr>
              <td>${esc(fp.ven_forma_pago_nombre)}</td>
              <td>${esc(fp.ven_forma_pago_codigo_sri || '—')}</td>
              <td class="text-center">${fp.ven_forma_pago_calculadora === 'S' ? 'Si' : 'No'}</td>
              <td class="text-center"><span class="badge ${activo ? 'bg-success' : 'bg-secondary'}">${activo ? 'Activo' : 'Inactivo'}</span></td>
              <td class="text-end">${acciones}</td>
            </tr>`;
        }).join('');
    }

    let listaFormasPagoActual = [];

    async function cargarFormasPago(seleccionarId) {
        const tbody = q('#cuerpoFormasPagoModal');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr>';
        try {
            const json = await fetchJson(appUrl + '/ventas/formas-pago');
            listaFormasPagoActual = json.data?.formasPago || [];
            renderFormasPago(listaFormasPagoActual);
            sincronizarSelectFormaPago(listaFormasPagoActual, seleccionarId);
        } catch {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error al cargar</td></tr>';
        }
    }

    btnAbrirFormasPago.addEventListener('click', () => {
        resetFormularioFormaPago();
        bootstrap.Modal.getOrCreateInstance(q('#modalFormasPago')).show();
        cargarFormasPago();
    });

    q('#btnCancelarFormaPago').addEventListener('click', resetFormularioFormaPago);

    q('#formFormaPago').addEventListener('submit', async (e) => {
        e.preventDefault();
        const nombre = q('#fp_nombre').value.trim();
        if (!nombre) {
            Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'El nombre es obligatorio.' });
            return;
        }
        const form = new FormData();
        form.append('nombre', nombre);
        form.append('codigo_sri', q('#fp_codigo_sri').value.trim());
        if (q('#fp_calculadora').checked) form.append('calculadora', '1');
        if (formaPagoEditandoId) form.append('forma_pago_id', String(formaPagoEditandoId));

        const url = appUrl + (formaPagoEditandoId ? '/ventas/formas-pago/editar' : '/ventas/formas-pago/crear');
        try {
            const resp = await fetch(url, { method: 'POST', body: form });
            const json = await resp.json();
            if (!json.ok) throw new Error(json.mensaje || 'No se pudo guardar.');
            const idGuardado = json.data.forma_pago.ven_forma_pago_id;
            resetFormularioFormaPago();
            cargarFormasPago(idGuardado);
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: error.message || 'Revise los datos.' });
        }
    });

    q('#cuerpoFormasPagoModal').addEventListener('click', async (e) => {
        const btnEditar  = e.target.closest('.btn-editar-forma-pago');
        const btnToggle  = e.target.closest('.btn-toggle-forma-pago');
        const btnEliminar = e.target.closest('.btn-eliminar-forma-pago');

        if (btnEditar) {
            const id = btnEditar.dataset.id;
            const fp = listaFormasPagoActual.find(f => String(f.ven_forma_pago_id) === id);
            if (!fp) return;
            formaPagoEditandoId = fp.ven_forma_pago_id;
            q('#fp_id').value = fp.ven_forma_pago_id;
            q('#fp_nombre').value = fp.ven_forma_pago_nombre;
            q('#fp_codigo_sri').value = fp.ven_forma_pago_codigo_sri || '';
            q('#fp_calculadora').checked = fp.ven_forma_pago_calculadora === 'S';
            q('#btnCancelarFormaPago').classList.remove('d-none');
            q('#btnGuardarFormaPago').innerHTML = 'Actualizar';
            q('#fp_nombre').focus();
            return;
        }

        if (btnToggle) {
            const id = btnToggle.dataset.id;
            const activo = btnToggle.dataset.activo === '1';
            const form = new FormData();
            form.append('forma_pago_id', id);
            try {
                const resp = await fetch(appUrl + (activo ? '/ventas/formas-pago/inactivar' : '/ventas/formas-pago/activar'), { method: 'POST', body: form });
                const json = await resp.json();
                if (!json.ok) throw new Error(json.mensaje || 'No se pudo cambiar el estado.');
                cargarFormasPago();
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'No se pudo cambiar el estado', text: error.message || '' });
            }
            return;
        }

        if (btnEliminar) {
            const id = btnEliminar.dataset.id;
            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: '¿Eliminar forma de pago?',
                text: 'Esta acción no se puede deshacer.',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!confirmacion.isConfirmed) return;
            const form = new FormData();
            form.append('forma_pago_id', id);
            try {
                const resp = await fetch(appUrl + '/ventas/formas-pago/eliminar', { method: 'POST', body: form });
                const json = await resp.json();
                if (!json.ok) throw new Error(json.mensaje || 'No se pudo eliminar.');
                cargarFormasPago();
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: error.message || '' });
            }
        }
    });
}

}());
</script>

<style>
@media (max-width: 767.98px) {
    #tablaDetalle thead { display: none; }
    #tablaDetalle tbody tr {
        display: block;
        position: relative;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        margin-bottom: .5rem;
        padding: .4rem .5rem;
    }
    #tablaDetalle tbody td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .25rem .1rem;
        border: none;
        font-size: .8rem;
    }
    #tablaDetalle tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        font-size: .75rem;
        flex: 0 0 90px;
    }
    #tablaDetalle tbody td.num-linea { display: none; }
    #tablaDetalle tbody td:last-child { justify-content: flex-end; }
}
</style>

<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
