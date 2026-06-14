<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre    = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl       = rtrim($configuracion->obtener('APP_URL', ''), '/');
$estadoActual = $estadoFiltro ?? '';
$desdeFiltro  = $desdeFiltro  ?? '';
$hastaFiltro  = $hastaFiltro  ?? '';
$msgs         = $mensajesSistema ?? [];
$msgAnular    = $msgs['CONFIRMAR_ANULAR_PROFORMA']  ?? null;
$msgFacturar  = $msgs['CONFIRMAR_FACTURAR_PROFORMA'] ?? null;
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Ventas</span><i class="bi bi-chevron-right"></i><strong>Proformas</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">

        <div class="panel-crud-cabecera d-flex align-items-center gap-2 flex-wrap">
            <?php if ($permisos['crear']): ?>
            <a href="<?= $appUrl ?>/ventas/proformas/nuevo" class="btn btn-intesis btn-crud">
                <i class="bi bi-plus-square"></i> Nueva Proforma
            </a>
            <?php endif; ?>

            <form method="get" action="<?= $appUrl ?>/ventas/proformas" class="d-flex align-items-center gap-2 ms-auto flex-wrap">
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
                    <select name="estado" class="form-select form-select-sm" style="width:140px">
                        <option value=""<?= $estadoActual === '' ? ' selected' : '' ?>>Todos</option>
                        <option value="CREADA"<?=    $estadoActual === 'CREADA'    ? ' selected' : '' ?>>Creada</option>
                        <option value="FACTURADA"<?= $estadoActual === 'FACTURADA' ? ' selected' : '' ?>>Facturada</option>
                        <option value="ANULADA"<?=   $estadoActual === 'ANULADA'   ? ' selected' : '' ?>>Anulada</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secundario btn-sm"><i class="bi bi-search"></i> Filtrar</button>
            </form>
        </div>

        <div class="table-responsive">
            <table id="tablaProformas" class="table table-hover tabla-intesis align-middle w-100">
                <thead><tr>
                    <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th class="text-end">Total</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($proformas as $prof): ?>
                <tr>
                    <?php if ($esSuperusuario): ?>
                    <td><?= htmlspecialchars($prof['sis_empresa_nombre_comercial']) ?></td>
                    <?php endif; ?>
                    <td class="font-monospace"><?= htmlspecialchars($prof['ven_documento_numero']) ?></td>
                    <td><?= htmlspecialchars(substr((string)($prof['ven_documento_fecha_emision'] ?? ''), 0, 10)) ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($prof['ven_cliente_razon_social']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($prof['ven_cliente_identificacion']) ?></small>
                    </td>
                    <td class="text-end font-monospace">
                        $<?= number_format((float)$prof['ven_documento_total'], 2, '.', ',') ?>
                    </td>
                    <td>
                        <?php
                        $estadoCod = $prof['sis_estado_codigo'];
                        $claseEstado = match($estadoCod) {
                            'CREADA'    => 'estado-activo',
                            'FACTURADA' => 'estado-badge bg-primary text-white',
                            'ANULADA'   => 'estado-inactivo',
                            default     => 'estado-badge bg-secondary text-white',
                        };
                        ?>
                        <span class="badge <?= $claseEstado ?>"><?= htmlspecialchars($prof['sis_estado_nombre']) ?></span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <?php if ($permisos['editar'] && $estadoCod === 'CREADA'): ?>
                            <a href="<?= $appUrl ?>/ventas/proformas/editar?id=<?= $prof['ven_documento_id'] ?>"
                               class="btn btn-sm btn-outline-secondary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($permisos['facturar'] && $estadoCod === 'CREADA'): ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-success btn-facturar-proforma"
                                data-id="<?= $prof['ven_documento_id'] ?>"
                                data-numero="<?= htmlspecialchars($prof['ven_documento_numero']) ?>"
                                title="Facturar">
                                <i class="bi bi-receipt"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($permisos['anular'] && $estadoCod === 'CREADA'): ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-danger btn-anular-proforma"
                                data-id="<?= $prof['ven_documento_id'] ?>"
                                data-numero="<?= htmlspecialchars($prof['ven_documento_numero']) ?>"
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

<!-- Modal Facturar -->
<div class="modal fade" id="modalFacturar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Facturar Proforma <span id="lblNumProforma"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Ajuste las cantidades a facturar. Ingrese <strong>0</strong> para omitir una línea.</p>
                <div class="table-responsive">
                    <table class="table table-sm tabla-intesis align-middle" id="tablaLineasFacturar">
                        <thead><tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="text-end">Proformado</th>
                            <th class="text-end" style="width:130px">A Facturar</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Total</th>
                        </tr></thead>
                        <tbody id="cuerpoLineasFacturar"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-intesis" id="btnConfirmarFacturar">
                    <i class="bi bi-check-circle me-1"></i> Confirmar Facturación
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const appUrl = <?= json_encode(rtrim($configuracion->obtener('APP_URL', ''), '/')) ?>;
    const msgAnular   = <?= json_encode($msgAnular ?? ['sis_mensaje_errores_titulo' => 'Anular proforma', 'sis_mensaje_errores_mensaje' => '¿Desea anular esta proforma?', 'sis_mensaje_errores_icono' => 'warning']) ?>;
    const msgFacturar = <?= json_encode($msgFacturar ?? ['sis_mensaje_errores_titulo' => 'Facturar proforma', 'sis_mensaje_errores_mensaje' => '¿Confirma la facturación?', 'sis_mensaje_errores_icono' => 'question']) ?>;

    let proformaIdActual = 0;

    // ── DataTables ──────────────────────────────────────────────────────────
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#tablaProformas').DataTable({
            language: { url: appUrl + '/publico/plugins/datatables/es-ES.json' },
            pageLength: 25,
            order: [[0, 'desc']],
        });
    }

    // ── Anular ───────────────────────────────────────────────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-anular-proforma');
        if (!btn) return;
        const id = btn.dataset.id;
        const numero = btn.dataset.numero;
        const res = await Swal.fire({
            title:  msgAnular.sis_mensaje_errores_titulo,
            text:   `${msgAnular.sis_mensaje_errores_mensaje}\nProforma: ${numero}`,
            icon:   msgAnular.sis_mensaje_errores_icono,
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!res.isConfirmed) return;
        try {
            const resp = await fetch(appUrl + '/ventas/proformas/anular', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proforma_id: parseInt(id) }),
            });
            const data = await resp.json();
            if (data.ok) {
                await Swal.fire({ title: 'Anulada', text: data.mensaje, icon: 'success', timer: 1500, showConfirmButton: false });
                location.reload();
            } else {
                Swal.fire({ title: 'Error', text: data.mensaje, icon: 'error' });
            }
        } catch {
            Swal.fire({ title: 'Error', text: 'Error de conexión.', icon: 'error' });
        }
    });

    // ── Facturar: abrir modal ────────────────────────────────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-facturar-proforma');
        if (!btn) return;
        const id     = parseInt(btn.dataset.id);
        const numero = btn.dataset.numero;
        proformaIdActual = id;
        document.getElementById('lblNumProforma').textContent = numero;
        document.getElementById('cuerpoLineasFacturar').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div> Cargando...</td></tr>';
        const modal = new bootstrap.Modal(document.getElementById('modalFacturar'));
        modal.show();

        try {
            const resp = await fetch(appUrl + '/ventas/proformas/detalle?id=' + id);
            const data = await resp.json();
            if (!data.ok) {
                document.getElementById('cuerpoLineasFacturar').innerHTML = `<tr><td colspan="6" class="text-danger">${data.mensaje}</td></tr>`;
                return;
            }
            renderizarLineasFacturar(data.data.lineas);
        } catch {
            document.getElementById('cuerpoLineasFacturar').innerHTML = '<tr><td colspan="6" class="text-danger">Error al cargar detalle.</td></tr>';
        }
    });

    function renderizarLineasFacturar(lineas) {
        const tbody = document.getElementById('cuerpoLineasFacturar');
        if (!lineas || lineas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Sin líneas</td></tr>';
            return;
        }
        tbody.innerHTML = lineas.map(l => {
            const cant  = parseFloat(l.ven_documento_detalle_cantidad);
            const precio = parseFloat(l.ven_documento_detalle_precio);
            const total  = parseFloat(l.ven_documento_detalle_total);
            return `<tr data-detalle-id="${l.ven_documento_detalle_id}" data-cant-max="${cant}" data-precio="${precio}">
                <td class="font-monospace small">${escHtml(l.ven_documento_detalle_codigo || l.codigo_interno || '')}</td>
                <td>${escHtml(l.ven_documento_detalle_descripcion || l.producto_nombre || '')}</td>
                <td class="text-end">${cant}</td>
                <td class="text-end">
                    <input type="number" class="form-control form-control-sm text-end inp-cant-facturar"
                        value="${cant}" min="0" max="${cant}" step="0.0001" style="width:110px;margin-left:auto">
                </td>
                <td class="text-end font-monospace">$${precio.toFixed(4)}</td>
                <td class="text-end font-monospace celda-total-linea">$${total.toFixed(2)}</td>
            </tr>`;
        }).join('');

        // Actualizar total línea al cambiar cantidad
        tbody.querySelectorAll('.inp-cant-facturar').forEach(inp => {
            inp.addEventListener('input', () => {
                const tr     = inp.closest('tr');
                const max    = parseFloat(tr.dataset.cantMax);
                const precio = parseFloat(tr.dataset.precio);
                let val      = parseFloat(inp.value) || 0;
                if (val < 0) { val = 0; inp.value = 0; }
                if (val > max) { val = max; inp.value = max; }
                tr.querySelector('.celda-total-linea').textContent = '$' + (val * precio).toFixed(2);
            });
        });
    }

    // ── Confirmar facturación ────────────────────────────────────────────────
    document.getElementById('btnConfirmarFacturar').addEventListener('click', async () => {
        const filas  = document.querySelectorAll('#cuerpoLineasFacturar tr[data-detalle-id]');
        const lineas = Array.from(filas).map(tr => ({
            detalle_id: parseInt(tr.dataset.detalleId),
            cantidad:   parseFloat(tr.querySelector('.inp-cant-facturar').value) || 0,
        }));

        const conCantidad = lineas.filter(l => l.cantidad > 0);
        if (conCantidad.length === 0) {
            Swal.fire({ title: 'Aviso', text: 'Debe ingresar al menos una cantidad mayor a cero.', icon: 'warning' });
            return;
        }

        const confirm = await Swal.fire({
            title:  msgFacturar.sis_mensaje_errores_titulo,
            text:   msgFacturar.sis_mensaje_errores_mensaje,
            icon:   msgFacturar.sis_mensaje_errores_icono,
            showCancelButton: true,
            confirmButtonText: 'Sí, facturar',
            cancelButtonText:  'Cancelar',
            confirmButtonColor: '#1f6f68',
        });
        if (!confirm.isConfirmed) return;

        document.getElementById('btnConfirmarFacturar').disabled = true;
        try {
            const resp = await fetch(appUrl + '/ventas/proformas/facturar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proforma_id: proformaIdActual, lineas }),
            });
            const data = await resp.json();
            if (data.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalFacturar')).hide();
                await Swal.fire({ title: 'Facturada', text: `Factura #${data.data.factura_id} generada.`, icon: 'success', confirmButtonColor: '#1f6f68' });
                location.reload();
            } else {
                Swal.fire({ title: 'Error', text: data.mensaje, icon: 'error' });
            }
        } catch {
            Swal.fire({ title: 'Error', text: 'Error de conexión.', icon: 'error' });
        }
        document.getElementById('btnConfirmarFacturar').disabled = false;
    });

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
})();
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
