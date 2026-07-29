<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl    = rtrim($configuracion->obtener('APP_URL', ''), '/');

// Bodega predeterminada
$bodegaDefault = 0;
foreach ($bodegas as $b) {
    if (!empty($b['inv_bodega_usuarios_predeterminada']) || !empty($b['inv_bodega_es_principal'])) {
        $bodegaDefault = (int) $b['inv_bodega_id'];
        break;
    }
}
if ($bodegaDefault === 0 && count($bodegas) > 0) {
    $bodegaDefault = (int) $bodegas[0]['inv_bodega_id'];
}

// Tipo predeterminado: Factura de Compra
$tipoDefault = 0;
foreach ($tiposDocumento as $td) {
    if (($td['sis_tipo_documento_codigo'] ?? '') === 'FACTURA_COMPRA') {
        $tipoDefault = (int) $td['sis_tipo_documento_id'];
        break;
    }
}

// IVA default (primer registro activo)
$ivaDefault      = 0;
$ivaDefaultValor = 0;
foreach ($ivaList as $iv) {
    $ivaDefault      = (int)   $iv['sis_iva_id'];
    $ivaDefaultValor = (float) $iv['sis_iva_valor'];
    break;
}

// Modo editar vs nuevo
$modoEditar = !empty($documento);
$docId      = $modoEditar ? (int) $documento['com_documento_id'] : 0;
$formAction = $modoEditar
    ? $appUrl . '/compras/documentos/actualizar'
    : $appUrl . '/compras/documentos/crear';
$tituloCard = $modoEditar ? 'Editar documento de compra' : 'Nuevo documento de compra';
$tituloNav  = $modoEditar ? 'Editar' : 'Nuevo';

// Valores precargados en edición
$editTipoId    = $modoEditar ? (int) $documento['sis_tipo_documento_id'] : $tipoDefault;
$editBodegaId  = $modoEditar ? (int) $documento['inv_bodega_id']         : $bodegaDefault;
$editNumero    = $modoEditar ? htmlspecialchars((string) $documento['com_documento_numero'])  : '';
$editFecha     = $modoEditar ? substr((string) $documento['com_documento_fecha_emision'], 0, 10) : date('Y-m-d');
$editObs       = $modoEditar ? htmlspecialchars((string) ($documento['com_documento_observacion'] ?? '')) : '';
$editDescuento = $modoEditar ? (float) ($documento['com_documento_descuento'] ?? 0) : 0;
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
                    <a href="<?= $appUrl ?>/compras/documentos" class="text-decoration-none">Documentos</a>
                    <i class="bi bi-chevron-right"></i>
                    <strong><?= $tituloNav ?></strong>
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

    <form method="post" action="<?= $formAction ?>" id="formDocumento" autocomplete="off">
        <?php if ($esSuperusuario): ?>
        <input type="hidden" name="empresa_id" value="<?= (int) $usuario['empresa_id'] ?>">
        <?php endif; ?>
        <?php if ($modoEditar): ?>
        <input type="hidden" name="documento_id" value="<?= $docId ?>">
        <?php endif; ?>

        <!-- ═══ CABECERA + botones ═══ -->
        <div class="card mb-2">
            <div class="card-header d-flex align-items-center justify-content-between py-1 px-2">
                <span class="fw-semibold small"><i class="bi bi-receipt me-1"></i><?= $tituloCard ?></span>
                <div class="d-flex gap-1">
                    <a href="<?= $appUrl ?>/compras/documentos" class="btn btn-sm btn-secundario px-2"><i class="bi bi-x-lg me-1"></i>Cancelar</a>
                    <button type="button" id="btnGuardar" class="btn btn-sm btn-intesis px-2"><i class="bi bi-save2 me-1"></i>Guardar borrador</button>
                </div>
            </div>
            <div class="card-body py-2 px-2 formulario-compacto">
                <div class="row g-2">
                    <div class="col-md-3 d-none">
                        <label class="form-label mb-0 small">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo_id" id="tipo_id" class="form-control form-control-sm" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($tiposDocumento as $td): ?>
                            <option value="<?= (int) $td['sis_tipo_documento_id'] ?>"
                                <?= (int) $td['sis_tipo_documento_id'] === $editTipoId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($td['sis_tipo_documento_nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">Numero <span class="text-danger">*</span></label>
                        <input type="text" name="numero" id="numero" class="form-control form-control-sm" maxlength="70" placeholder="001-001-000000001" value="<?= $editNumero ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_emision" id="fecha_emision" class="form-control form-control-sm" value="<?= $editFecha ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">Bodega destino <span class="text-danger">*</span></label>
                        <select name="bodega_id" id="bodega_id" class="form-control form-control-sm" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($bodegas as $b): ?>
                            <option value="<?= (int) $b['inv_bodega_id'] ?>" <?= (int) $b['inv_bodega_id'] === $editBodegaId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['inv_bodega_codigo']) ?> — <?= htmlspecialchars($b['inv_bodega_nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small">RUC / Identificacion <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="ruc_input" class="form-control form-control-sm" placeholder="Ingrese y presione Enter" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btnBuscarRuc" tabindex="-1" title="Buscar"><i class="bi bi-search"></i></button>
                        </div>
                        <input type="hidden" name="proveedor_id" id="proveedor_id">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small">Razon social</label>
                        <input type="text" id="razon_social_display" class="form-control form-control-sm" disabled placeholder="Se carga al ingresar el RUC">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-0 small">Observacion</label>
                        <input type="text" name="observacion" class="form-control form-control-sm" maxlength="500" value="<?= $editObs ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ LEYENDA CODIGOS NUEVOS ═══ -->
        <div id="leyendaCodigos" class="alert alert-warning py-1 px-2 mb-1 small d-none">
            <i class="bi bi-plus-circle me-1"></i><span id="leyendaCodigosTexto"></span>
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
                            <th style="min-width:105px">Cod. Interno</th>
                            <th style="min-width:105px">Cod. Proveedor</th>
                            <th>Descripcion</th>
                            <th style="min-width:110px">Marca</th>
                            <th style="width:70px" class="text-center">Cant.</th>
                            <th style="width:96px">Costo</th>
                            <th style="width:100px">IVA</th>
                            <th style="width:72px" class="text-center">% PVP</th>
                            <th style="width:96px">PVP</th>
                            <th style="width:88px" class="text-end">Total</th>
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
                    <tr>
                        <td class="text-muted ps-2 align-middle">Descuento</td>
                        <td class="text-end pe-2">
                            <input type="number" class="form-control form-control-sm text-end d-inline-block" style="width:110px" id="inp_descuento" name="descuento" value="<?= number_format($editDescuento, 2, '.', '') ?>" min="0" step="0.01">
                        </td>
                    </tr>
                    <tr><td class="text-muted ps-2">IVA</td>               <td class="text-end pe-2 fw-semibold" id="resIva">$ 0.00</td></tr>
                    <tr class="table-light"><td class="fw-bold ps-2">Total</td><td class="text-end pe-2 fw-bold" id="resTotal">$ 0.00</td></tr>
                </table>
            </div>
        </div>

        <input type="hidden" name="subtotal"  id="inp_subtotal">
        <input type="hidden" name="iva_total" id="inp_iva_total">
        <input type="hidden" name="total"     id="inp_total">
    </form>

    </div></div></main>
</div>

<!-- ═══ MODAL BUSCAR PROVEEDOR ═══ -->
<div class="modal fade modal-intesis" id="modalBuscarProveedor" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Compras</p><h2 class="modal-title h6 mb-0">Buscar Proveedor</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="text" id="buscarProveedorInput" class="form-control form-control-sm mb-2" placeholder="Buscar por RUC o razon social...">
                <div class="table-responsive" style="max-height:340px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light" style="position:sticky;top:0"><tr><th>#</th><th>Tipo</th><th>Identificacion</th><th>Razon Social</th><th>Nombre Comercial</th></tr></thead>
                        <tbody id="cuerpoProveedoresModal"><tr><td colspan="5" class="text-center text-muted py-3">Escriba para buscar...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL PRODUCTOS COD. PROVEEDOR ═══ -->
<div class="modal fade modal-intesis" id="modalProductosCodProv" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Inventario</p><h2 class="modal-title h6 mb-0">Buscar Producto</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="text" id="buscarCodProvInput" class="form-control form-control-sm mb-2" placeholder="Codigo de proveedor...">
                <div class="table-responsive" style="max-height:380px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light" style="position:sticky;top:0"><tr><th>#</th><th>Cod. Proveedor</th><th>Cod. Interno</th><th>Descripcion</th><th class="text-center">Stock</th></tr></thead>
                        <tbody id="cuerpoCodProvModal"><tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL PRODUCTOS CODIGO INTERNO ═══ -->
<div class="modal fade modal-intesis" id="modalProductosCodigo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div><p class="modal-etiqueta mb-0">Inventario</p><h2 class="modal-title h6 mb-0">Buscar por Codigo Interno</h2></div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="text" id="buscarCodigoInput" class="form-control form-control-sm mb-2" placeholder="Codigo o descripcion...">
                <div class="table-responsive" style="max-height:380px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light" style="position:sticky;top:0"><tr><th>#</th><th>Codigo</th><th>Nombre Producto</th><th>Marca</th><th class="text-end">Stock</th></tr></thead>
                        <tbody id="cuerpoCodigoModal"><tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                    </table>
                </div>
                <div id="panelNuevoProducto" class="d-none border rounded p-2 mt-2">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small">Codigo</label>
                            <input type="text" id="np_codigo" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label mb-0 small">Nombre</label>
                            <input type="text" id="np_nombre" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0 small">Categoria</label>
                            <select id="np_categoria" class="form-control form-control-sm"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0 small">Marca</label>
                            <select id="np_marca" class="form-control form-control-sm"></select>
                        </div>
                    </div>
                    <div class="text-end mt-2">
                        <button type="button" class="btn btn-sm btn-secundario" id="btnCancelarNuevoProducto">Cancelar</button>
                        <button type="button" class="btn btn-sm btn-intesis" id="btnGuardarNuevoProducto">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>
(function () {
'use strict';

const appUrl        = '<?= $appUrl ?>';
const ivaList       = <?= json_encode(array_values($ivaList), JSON_UNESCAPED_UNICODE) ?>;
const marcasList    = <?= json_encode(array_values($marcasList ?? []), JSON_UNESCAPED_UNICODE) ?>;
const categoriasList = <?= json_encode(array_values($categoriasList ?? []), JSON_UNESCAPED_UNICODE) ?>;
const ivaDefault    = <?= (int) $ivaDefault ?>;
const ivaDefaultVal = <?= (float) $ivaDefaultValor ?>;
let siguienteCodigoSugerido = <?= json_encode($siguienteCodigoSugerido ?? '1') ?>;
let lineaIdx   = 0;
let filaActual = null;

/* ── Escaper simple para atributos HTML ───────────────────────────────────── */
function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/"/g,'&quot;')
        .replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
const fmt2 = (n) => parseFloat(n || 0).toFixed(2);
const q    = (sel) => document.querySelector(sel);
const fetchJson = async (url) => {
    const r = await fetch(url);
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
};

/* ── Enter nunca envía el form (botón es type="button", submit es explícito) ─ */
document.getElementById('formDocumento').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') e.preventDefault();
});

/* ── IVA select options ───────────────────────────────────────────────────── */
function opcionesIva(seleccionado) {
    let html = '<option value="" data-valor="0">0 %</option>';
    ivaList.forEach(iv => {
        const sel = parseInt(iv.sis_iva_id) === seleccionado ? 'selected' : '';
        html += `<option value="${iv.sis_iva_id}" data-valor="${iv.sis_iva_valor}" ${sel}>${parseFloat(iv.sis_iva_valor).toFixed(0)} %</option>`;
    });
    return html;
}

/* ── Marca select options ─────────────────────────────────────────────────── */
function opcionesMarca(seleccionado) {
    let html = '<option value="">Sin marca</option>';
    marcasList.forEach(m => {
        const sel = parseInt(m.inv_marca_id) === seleccionado ? 'selected' : '';
        html += `<option value="${m.inv_marca_id}" ${sel}>${esc(m.inv_marca_nombre)}</option>`;
    });
    return html;
}

/* ── Categoria select options ─────────────────────────────────────────────── */
function opcionesCategoria(seleccionado) {
    let html = '<option value="">Seleccione</option>';
    categoriasList.forEach(c => {
        const sel = parseInt(c.inv_categoria_id) === seleccionado ? 'selected' : '';
        html += `<option value="${c.inv_categoria_id}" ${sel}>${esc(c.inv_categoria_nombre)}</option>`;
    });
    return html;
}

/* ── Siguiente codigo sugerido: mantiene el prefijo de letras, sube el numero ─ */
function siguienteCodigoDesdeActual(codigo) {
    const m = String(codigo || '').match(/^(.*?)(\d+)$/);
    if (!m) return '1';
    const prefijo = m[1];
    const numero  = m[2];
    const siguiente = String(parseInt(numero, 10) + 1).padStart(numero.length, '0');
    return prefijo + siguiente;
}

/* ── Crear fila ───────────────────────────────────────────────────────────── */
function crearFila(datos) {
    const idx = lineaIdx++;
    const num = document.querySelectorAll('#cuerpoDetalle tr').length + 1;
    const tr  = document.createElement('tr');
    tr.dataset.idx        = idx;
    tr.dataset.productoId = String(datos.inv_producto_id || '');

    tr.innerHTML = `
      <td class="text-center text-muted small align-middle num-linea">${num}</td>
      <td><input type="text"   class="form-control form-control-sm inp-cod-interno" placeholder="Codigo"      autocomplete="off" value="${esc(datos.codigo_interno||'')}"></td>
      <td><input type="text"   class="form-control form-control-sm inp-cod-prov"    placeholder="Cod. prov."  autocomplete="off" name="lineas[${idx}][cod_proveedor]" value="${esc(datos.cod_proveedor||'')}"></td>
      <td><input type="text"   class="form-control form-control-sm inp-descripcion" value="${esc(datos.producto_nombre||'')}" disabled></td>
      <td><select class="form-control form-control-sm inp-marca" name="lineas[${idx}][marca_id]">${opcionesMarca(parseInt(datos.marca_id) || 0)}</select></td>
      <td class="text-center"><input type="number" class="form-control form-control-sm text-center inp-cantidad" name="lineas[${idx}][cantidad]" value="${datos.cantidad}" min="1" step="1"></td>
      <td><input type="number" class="form-control form-control-sm inp-costo"       name="lineas[${idx}][costo]"     value="${datos.costo||0}"    min="0"       step="0.000001"></td>
      <td>
        <select class="form-control form-control-sm inp-iva" name="lineas[${idx}][iva_id]">
          ${opcionesIva(parseInt(datos.iva_id) || ivaDefault)}
        </select>
        <input type="hidden" name="lineas[${idx}][iva_valor]" class="inp-iva-valor"
               value="${datos.iva_valor !== undefined ? datos.iva_valor : ivaDefaultVal}">
      </td>
      <td class="text-center"><input type="number" class="form-control form-control-sm text-center inp-pvp-pct" value="${calcularPvpPct(parseFloat(datos.costo||0), parseFloat(datos.pvp||datos.costo||0)).toFixed(2)}" min="0" step="0.01"></td>
      <td><input type="number" class="form-control form-control-sm inp-pvp"
                 name="lineas[${idx}][pvp]" value="${parseFloat(datos.pvp||datos.costo||0).toFixed(2)}" min="0" step="0.01"></td>
      <td class="text-end fw-semibold align-middle inp-total-display">$ 0.00</td>
      <td class="text-center align-middle">
        <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-linea p-0 px-1" tabindex="-1">
          <i class="bi bi-trash" style="font-size:.75rem"></i>
        </button>
      </td>
      <td class="d-none">
        <input type="hidden" name="lineas[${idx}][producto_id]" class="inp-producto-id"
               value="${esc(datos.inv_producto_id||'')}">
      </td>`;

    document.getElementById('cuerpoDetalle').appendChild(tr);
    calcularFila(tr);
    actualizarNumeros();
    return tr;
}

/* ── Calcular fila ────────────────────────────────────────────────────────── */
function calcularFila(tr) {
    const qty  = parseFloat(tr.querySelector('.inp-cantidad')?.value)  || 0;
    const cost = parseFloat(tr.querySelector('.inp-costo')?.value)     || 0;
    const ivaS = tr.querySelector('.inp-iva');
    const opt  = ivaS?.options[ivaS?.selectedIndex];
    const ivaV = parseFloat(opt?.dataset?.valor || 0);
    tr.querySelector('.inp-iva-valor').value = ivaV;
    const base = Math.max(0, qty * cost);
    const iva  = base * ivaV / 100;
    tr.querySelector('.inp-total-display').textContent = '$ ' + fmt2(base + iva);
    calcularTotales();
}

/* ── Calcular totales generales ───────────────────────────────────────────── */
function calcularTotales() {
    let base0 = 0, baseIva = 0, totalIva = 0;
    document.querySelectorAll('#cuerpoDetalle tr').forEach(tr => {
        const qty  = parseFloat(tr.querySelector('.inp-cantidad')?.value)  || 0;
        const cost = parseFloat(tr.querySelector('.inp-costo')?.value)     || 0;
        const ivaS = tr.querySelector('.inp-iva');
        const opt  = ivaS?.options[ivaS?.selectedIndex];
        const ivaV = parseFloat(opt?.dataset?.valor || 0);
        const base = Math.max(0, qty * cost);
        if (ivaV > 0) baseIva += base; else base0 += base;
        totalIva  += base * ivaV / 100;
    });
    const descuento = parseFloat(q('#inp_descuento')?.value) || 0;
    const total = base0 + baseIva + totalIva - descuento;
    q('#resBase0').textContent     = '$ ' + fmt2(base0);
    q('#resBaseIva').textContent   = '$ ' + fmt2(baseIva);
    q('#resIva').textContent       = '$ ' + fmt2(totalIva);
    q('#resTotal').textContent     = '$ ' + fmt2(total);
    q('#inp_subtotal').value  = fmt2(base0 + baseIva);
    q('#inp_iva_total').value = fmt2(totalIva);
    q('#inp_total').value     = fmt2(total);
}

function actualizarNumeros() {
    document.querySelectorAll('#cuerpoDetalle tr').forEach((tr, i) => {
        const n = tr.querySelector('.num-linea');
        if (n) n.textContent = i + 1;
    });
}

/* ── Cargar producto en fila ──────────────────────────────────────────────── */
function calcularPvpPct(costo, pvp) {
    if (costo <= 0) return 0;
    return Math.max(0, ((pvp / costo) - 1) * 100);
}

function cargarProductoEnFila(tr, prod) {
    tr.dataset.productoId      = String(prod.inv_producto_id || '');
    tr.dataset.codProvOriginal = prod.cod_proveedor || '';
    const costo = parseFloat(prod.costo || 0);
    const pvp   = parseFloat(prod.pvp  || 0) > 0 ? parseFloat(prod.pvp) : costo;
    tr.querySelector('.inp-cod-prov').value    = prod.cod_proveedor       || '';
    tr.querySelector('.inp-cod-interno').value = prod.codigo_interno      || '';
    tr.querySelector('.inp-descripcion').value = prod.inv_producto_nombre || '';
    tr.querySelector('.inp-marca').value       = String(prod.marca_id || '');
    tr.querySelector('.inp-costo').value       = costo.toFixed(6);
    tr.querySelector('.inp-pvp-pct').value     = calcularPvpPct(costo, pvp).toFixed(2);
    tr.querySelector('.inp-pvp').value         = pvp.toFixed(2);
    tr.querySelector('.inp-producto-id').value = String(prod.inv_producto_id || '');
    calcularFila(tr);
    actualizarLeyendaCodigos();
}

/* ── Eventos del cuerpo de detalle ───────────────────────────────────────── */
const cuerpoDetalle = document.getElementById('cuerpoDetalle');

cuerpoDetalle.addEventListener('input', (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.matches('.inp-costo,.inp-pvp-pct')) {
        const costo = parseFloat(tr.querySelector('.inp-costo')?.value) || 0;
        const pct   = parseFloat(tr.querySelector('.inp-pvp-pct')?.value) || 0;
        tr.querySelector('.inp-pvp').value = (costo * (1 + pct / 100)).toFixed(2);
    }
    if (e.target.matches('.inp-pvp')) {
        const costo = parseFloat(tr.querySelector('.inp-costo')?.value) || 0;
        const pvp   = parseFloat(tr.querySelector('.inp-pvp')?.value)   || 0;
        tr.querySelector('.inp-pvp-pct').value = calcularPvpPct(costo, pvp).toFixed(2);
    }
    if (e.target.matches('.inp-cantidad,.inp-costo,.inp-pvp,.inp-pvp-pct')) calcularFila(tr);
    if (e.target.matches('.inp-cod-prov')) actualizarLeyendaCodigos();
});
cuerpoDetalle.addEventListener('change', (e) => {
    const tr = e.target.closest('tr');
    if (tr && e.target.matches('.inp-iva')) calcularFila(tr);
});

q('#btnAgregarLinea').addEventListener('click', () => crearFila({}));

q('#inp_descuento').addEventListener('input', calcularTotales);

cuerpoDetalle.addEventListener('click', (e) => {
    if (e.target.closest('.btn-eliminar-linea')) {
        e.target.closest('tr').remove();
        actualizarNumeros();
        calcularTotales();
    }
});

/* ── Abrir modal cuando el campo pierde foco ─────────────────────────────── */
cuerpoDetalle.addEventListener('focusout', (e) => {
    const target = e.target;
    const isCodProv    = target.matches('.inp-cod-prov');
    const isCodInterno = target.matches('.inp-cod-interno');
    if (!isCodProv && !isCodInterno) return;

    // Delay para que el relatedTarget se establezca y no abrir si el foco
    // sigue dentro del mismo <tr> (p.ej. Tab al campo siguiente)
    setTimeout(() => {
        const tr  = target.closest('tr');
        if (!tr)  return;
        const val = target.value.trim();
        if (!val) return;

        // Si ya hay producto cargado, no abrir modal
        const productoId = tr.querySelector('.inp-producto-id')?.value || tr.dataset.productoId || '';
        if (productoId) return;

        filaActual = tr;
        if (isCodProv)    abrirModalCodProv(val);
        if (isCodInterno) abrirModalCodInterno(val);
    }, 180);
});

/* ── Enter en Cod. Proveedor / Cod. Interno abre modal ───────────────────── */
cuerpoDetalle.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const target = e.target;
    const isCodProv    = target.matches('.inp-cod-prov');
    const isCodInterno = target.matches('.inp-cod-interno');
    if (!isCodProv && !isCodInterno) return;

    e.preventDefault();
    e.stopPropagation();

    const tr = target.closest('tr');
    if (!tr) return;
    const val = target.value.trim();

    const productoId = tr.querySelector('.inp-producto-id')?.value || tr.dataset.productoId || '';
    if (productoId) return;   // producto ya cargado, no abrir modal

    filaActual = tr;
    if (isCodProv)    abrirModalCodProv(val);
    if (isCodInterno) abrirModalCodInterno(val);
});

/* ── Enter en PVP agrega nueva linea automaticamente ─────────────────────── */
cuerpoDetalle.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (!e.target.matches('.inp-pvp')) return;

    e.preventDefault();
    e.stopPropagation();

    const nuevaFila = crearFila({});
    nuevaFila.querySelector('.inp-cod-interno')?.focus();
});

/* ── RUC ─────────────────────────────────────────────────────────────────── */
async function ejecutarBusquedaRuc() {
    const ruc = q('#ruc_input').value.trim();
    if (!ruc) { abrirModalProveedor(''); return; }
    try {
        const json = await fetchJson(appUrl + '/compras/documentos/buscar-proveedor?ruc=' + encodeURIComponent(ruc));
        if (json.proveedor) cargarProveedor(json.proveedor);
        else                abrirModalProveedor(ruc);
    } catch { abrirModalProveedor(ruc); }
}

q('#ruc_input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') ejecutarBusquedaRuc();
});
q('#btnBuscarRuc').addEventListener('click', ejecutarBusquedaRuc);

function cargarProveedor(p) {
    q('#proveedor_id').value         = p.com_proveedor_id;
    q('#ruc_input').value            = p.com_proveedor_identificacion;
    q('#razon_social_display').value = p.com_proveedor_razon_social;
}

/* ── Modal buscar proveedor ──────────────────────────────────────────────── */
let timerBuscProv = null;

function abrirModalProveedor(texto) {
    q('#buscarProveedorInput').value = texto;
    bootstrap.Modal.getOrCreateInstance(q('#modalBuscarProveedor')).show();
    buscarProveedores(texto);
    setTimeout(() => q('#buscarProveedorInput')?.focus(), 300);
}

async function buscarProveedores(term) {
    const tbody = q('#cuerpoProveedoresModal');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Buscando...</td></tr>';
    try {
        const json = await fetchJson(appUrl + '/compras/documentos/buscar-proveedores?q=' + encodeURIComponent(term));
        if (!json.proveedores?.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin resultados</td></tr>';
            return;
        }
        tbody.innerHTML = json.proveedores.map((p, i) => `
            <tr style="cursor:pointer" data-id="${p.com_proveedor_id}"
                data-ruc="${esc(p.com_proveedor_identificacion)}"
                data-razon="${esc(p.com_proveedor_razon_social)}">
              <td>${i+1}</td>
              <td>${esc(p.com_proveedor_tipo_identificacion)}</td>
              <td>${esc(p.com_proveedor_identificacion)}</td>
              <td>${esc(p.com_proveedor_razon_social)}</td>
              <td>${esc(p.com_proveedor_nombre_comercial||'—')}</td>
            </tr>`).join('');
    } catch {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error al buscar</td></tr>';
    }
}

q('#buscarProveedorInput').addEventListener('input', (e) => {
    clearTimeout(timerBuscProv);
    timerBuscProv = setTimeout(() => buscarProveedores(e.target.value), 300);
});
q('#cuerpoProveedoresModal').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    cargarProveedor({
        com_proveedor_id: tr.dataset.id,
        com_proveedor_identificacion: tr.dataset.ruc,
        com_proveedor_razon_social:   tr.dataset.razon,
    });
    bootstrap.Modal.getInstance(q('#modalBuscarProveedor'))?.hide();
});

/* ── Modal productos: Cod. Proveedor ─────────────────────────────────────── */
let timerCodProv = null;

function abrirModalCodProv(texto) {
    q('#buscarCodProvInput').value = texto;
    bootstrap.Modal.getOrCreateInstance(q('#modalProductosCodProv')).show();
    buscarPorCodProv(texto);
    setTimeout(() => q('#buscarCodProvInput')?.focus(), 300);
}

async function buscarPorCodProv(term) {
    const tbody = q('#cuerpoCodProvModal');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Buscando...</td></tr>';
    try {
        const json = await fetchJson(appUrl + '/compras/documentos/productos?tipo=cod_proveedor&q=' + encodeURIComponent(term));
        renderProductosCodProv(tbody, json.productos || []);
    } catch { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error</td></tr>'; }
}
q('#buscarCodProvInput').addEventListener('input', (e) => {
    clearTimeout(timerCodProv);
    timerCodProv = setTimeout(() => buscarPorCodProv(e.target.value), 300);
});
q('#cuerpoCodProvModal').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-prod]');
    if (!tr || !filaActual) return;
    try { cargarProductoEnFila(filaActual, JSON.parse(tr.dataset.prod)); } catch(ex) { console.error(ex); }
    filaActual = null;
    bootstrap.Modal.getInstance(q('#modalProductosCodProv'))?.hide();
});
// Limpiar filaActual si se cierra sin seleccionar
q('#modalProductosCodProv').addEventListener('hidden.bs.modal',  () => { filaActual = null; });

/* ── Modal productos: Codigo Interno ─────────────────────────────────────── */
let timerCodInterno = null;

function abrirModalCodInterno(texto) {
    q('#buscarCodigoInput').value = texto;
    bootstrap.Modal.getOrCreateInstance(q('#modalProductosCodigo')).show();
    buscarPorCodigo(texto);
    setTimeout(() => q('#buscarCodigoInput')?.focus(), 300);
}

async function buscarPorCodigo(term) {
    const tbody = q('#cuerpoCodigoModal');
    ocultarPanelNuevoProducto();
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Buscando...</td></tr>';
    try {
        const json = await fetchJson(appUrl + '/compras/documentos/productos?tipo=codigo&q=' + encodeURIComponent(term));
        renderProductos(tbody, json.productos || [], term);
    } catch { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error</td></tr>'; }
}
q('#buscarCodigoInput').addEventListener('input', (e) => {
    clearTimeout(timerCodInterno);
    timerCodInterno = setTimeout(() => buscarPorCodigo(e.target.value), 300);
});
q('#cuerpoCodigoModal').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-prod]');
    if (!tr || !filaActual) return;
    try { cargarProductoEnFila(filaActual, JSON.parse(tr.dataset.prod)); } catch(ex) { console.error(ex); }
    filaActual = null;
    bootstrap.Modal.getInstance(q('#modalProductosCodigo'))?.hide();
});
q('#modalProductosCodigo').addEventListener('hidden.bs.modal', () => { filaActual = null; ocultarPanelNuevoProducto(); });

/* ── Panel "Crear producto" dentro del modal Codigo Interno ──────────────── */
function ocultarPanelNuevoProducto() {
    q('#panelNuevoProducto').classList.add('d-none');
}

function mostrarPanelNuevoProducto(terminoBuscado) {
    q('#btnAbrirNuevoProducto')?.classList.add('d-none');
    q('#panelNuevoProducto').classList.remove('d-none');
    q('#np_codigo').value    = siguienteCodigoSugerido;
    q('#np_nombre').value    = /\d/.test(terminoBuscado || '') ? '' : (terminoBuscado || '');
    q('#np_categoria').innerHTML = opcionesCategoria(0);
    q('#np_marca').innerHTML     = opcionesMarca(0);
    setTimeout(() => q('#np_nombre')?.focus(), 50);
}

q('#btnCancelarNuevoProducto').addEventListener('click', () => {
    ocultarPanelNuevoProducto();
    buscarPorCodigo(q('#buscarCodigoInput').value);
});

q('#btnGuardarNuevoProducto').addEventListener('click', async () => {
    const codigo    = q('#np_codigo').value.trim();
    const nombre    = q('#np_nombre').value.trim();
    const categoria = q('#np_categoria').value;
    const marca     = q('#np_marca').value;
    if (!codigo || !nombre || !categoria || !marca) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Codigo, nombre, categoria y marca son obligatorios.' });
        return;
    }
    const btn = q('#btnGuardarNuevoProducto');
    btn.disabled = true;
    try {
        const fd = new FormData();
        const empresaIdInput = q('input[name="empresa_id"]');
        if (empresaIdInput) fd.append('empresa_id', empresaIdInput.value);
        fd.append('codigo_principal', codigo);
        fd.append('nombre', nombre);
        fd.append('categoria_id', categoria);
        fd.append('marca_id', marca);
        const resultado = await fetch(appUrl + '/inventario/productos/crear', {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await resultado.json();
        if (!json.ok) throw new Error(json.mensaje || 'No se pudo crear el producto.');
        const marcaNombre = marcasList.find(m => parseInt(m.inv_marca_id) === parseInt(marca))?.inv_marca_nombre || '';
        if (filaActual) {
            cargarProductoEnFila(filaActual, {
                inv_producto_id: json.data.producto_id,
                codigo_interno: codigo,
                inv_producto_nombre: nombre,
                marca_nombre: marcaNombre,
                marca_id: marca,
                costo: 0,
                pvp: 0,
                cod_proveedor: '',
            });
        }
        siguienteCodigoSugerido = siguienteCodigoDesdeActual(codigo);
        filaActual = null;
        ocultarPanelNuevoProducto();
        bootstrap.Modal.getInstance(q('#modalProductosCodigo'))?.hide();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'No se pudo crear', text: error.message || 'Revise los datos.' });
    } finally {
        btn.disabled = false;
    }
});

/* ── Render tabla productos: modal Cod. Proveedor ─────────────────────────── */
function renderProductosCodProv(tbody, lista) {
    if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin resultados</td></tr>';
        return;
    }
    tbody.innerHTML = lista.map((p, i) => {
        const safeJson = JSON.stringify(p).replace(/'/g, '&#39;');
        return `<tr style="cursor:pointer" data-prod='${safeJson}'>
          <td>${i+1}</td>
          <td>${esc(p.cod_proveedor||'—')}</td>
          <td>${esc(p.codigo_interno||'')}</td>
          <td>${esc(p.inv_producto_nombre||'')}</td>
          <td class="text-center">${parseFloat(p.stock_total||0)}</td>
        </tr>`;
    }).join('');
}

/* ── Render tabla productos: modal Cod. Interno ───────────────────────────── */
function renderProductos(tbody, lista, termino) {
    if (!lista.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="py-2 text-center">
            <span class="text-muted me-2">Sin resultados${termino ? ` para "<strong>${esc(termino)}</strong>"` : ''}</span>
            <button type="button" class="btn btn-sm btn-intesis" id="btnAbrirNuevoProducto">
                <i class="bi bi-plus-lg me-1"></i>Crear producto
            </button>
        </td></tr>`;
        q('#btnAbrirNuevoProducto')?.addEventListener('click', () => mostrarPanelNuevoProducto(termino));
        return;
    }
    ocultarPanelNuevoProducto();
    tbody.innerHTML = lista.map((p, i) => {
        const safeJson = JSON.stringify(p).replace(/'/g, '&#39;');
        return `<tr style="cursor:pointer" data-prod='${safeJson}'>
          <td>${i+1}</td>
          <td>${esc(p.codigo_interno)}</td>
          <td>${esc(p.inv_producto_nombre||'')}</td>
          <td>${esc(p.marca_nombre||'—')}</td>
          <td class="text-center">${parseFloat(p.stock_total||0)}</td>
        </tr>`;
    }).join('');
}

/* ── Leyenda codigos nuevos ───────────────────────────────────────────────── */
function actualizarLeyendaCodigos() {
    const codigos = [];
    document.querySelectorAll('#cuerpoDetalle tr').forEach(tr => {
        const productoId = tr.dataset.productoId || tr.querySelector('.inp-producto-id')?.value || '';
        if (!productoId) return;
        const original = tr.dataset.codProvOriginal || '';
        const actual   = (tr.querySelector('.inp-cod-prov')?.value || '').trim();
        if (actual && actual !== original) codigos.push(actual);
    });
    const div  = document.getElementById('leyendaCodigos');
    const span = document.getElementById('leyendaCodigosTexto');
    if (codigos.length) {
        span.textContent = 'Se creará el código ' + codigos.map(c => '[' + c + ']').join(', ');
        div.classList.remove('d-none');
    } else {
        div.classList.add('d-none');
    }
}

/* ── Guardar: validar y enviar (botón type="button" → submit explícito) ──── */
q('#btnGuardar').addEventListener('click', async () => {
    if (!q('#numero').value.trim()) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'El número de documento es obligatorio.' });
        q('#numero').focus();
        return;
    }
    if (!q('#fecha_emision').value) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'La fecha de emisión es obligatoria.' });
        q('#fecha_emision').focus();
        return;
    }
    if (!q('#bodega_id').value) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Debe seleccionar la bodega destino.' });
        q('#bodega_id').focus();
        return;
    }
    if (!q('#proveedor_id').value) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Debe seleccionar un proveedor antes de guardar.' });
        q('#ruc_input').focus();
        return;
    }
    const filas = document.querySelectorAll('#cuerpoDetalle tr');
    if (!filas.length) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Debe agregar al menos una línea de detalle.' });
        return;
    }
    // Sincronizar producto_id desde dataset al hidden input
    filas.forEach(tr => {
        const h = tr.querySelector('.inp-producto-id');
        if (h && tr.dataset.productoId) h.value = tr.dataset.productoId;
    });
    // Advertir si alguna línea tiene PVP = 0
    const sinPvp = [...filas].some(tr => (parseFloat(tr.querySelector('.inp-pvp')?.value) || 0) <= 0);
    if (sinPvp) {
        const res = await Swal.fire({
            icon: 'warning',
            title: 'PVP sin definir',
            text: 'Una o más líneas tienen PVP en 0. ¿Desea guardar de todas formas?',
            showCancelButton: true,
            confirmButtonText: 'Guardar igual',
            cancelButtonText: 'Revisar',
            confirmButtonColor: '#f59e0b',
        });
        if (!res.isConfirmed) return;
    }
    document.getElementById('formDocumento').submit();
});

/* ── Inicializar líneas de detalle ───────────────────────────────────────── */
<?php if ($modoEditar && !empty($lineas)): ?>
<?php foreach ($lineas as $linea): ?>
crearFila(<?= json_encode([
    'inv_producto_id' => (int) $linea['inv_producto_id'],
    'cod_proveedor'   => (string) ($linea['cod_proveedor'] ?? ''),
    'codigo_interno'  => (string) ($linea['codigo_interno'] ?? ''),
    'producto_nombre' => (string) ($linea['producto_nombre'] ?? ''),
    'marca_nombre'    => (string) ($linea['marca_nombre'] ?? ''),
    'marca_id'        => (int)    ($linea['marca_id'] ?? 0),
    'cantidad'        => (float)  $linea['com_documento_detalle_cantidad'],
    'costo'           => (float)  $linea['com_documento_detalle_precio'],
    'iva_id'          => (int)    ($linea['sis_iva_id'] ?? 0),
    'iva_valor'       => (float)  ($linea['sis_iva_valor'] ?? 0),
    'pvp'             => (float)  $linea['com_documento_detalle_pvp'],
], JSON_UNESCAPED_UNICODE) ?>);
<?php endforeach; ?>
<?php else: ?>
crearFila({});
<?php endif; ?>

<?php if ($modoEditar): ?>
// Pre-cargar proveedor en modo edición
(function() {
    const rucInp = q('#ruc_input');
    const provId = q('#proveedor_id');
    const razon  = q('#razon_social_display');
    if (rucInp)  rucInp.value  = <?= json_encode((string) ($documento['com_proveedor_identificacion'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    if (provId)  provId.value  = <?= (int) ($documento['com_proveedor_id'] ?? 0) ?>;
    if (razon)   razon.value   = <?= json_encode((string) ($documento['com_proveedor_razon_social'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
})();
<?php endif; ?>

}());
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
