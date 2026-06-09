<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li>
                <li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Inventario</span><i class="bi bi-chevron-right"></i><strong>Movimientos internos</strong></span></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li>
                <li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li>
            </ul>
        </div>
    </nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div>
        <div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div>
    </aside>
    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <section class="panel-crud">
                    <div class="panel-crud-cabecera gap-2 flex-wrap">
                        <div class="nav nav-pills tabs-intesis" role="tablist">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabMovimientos" type="button">Movimientos</button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTransferencias" type="button">Transferencias pendientes</button>
                        </div>
                        <div class="ms-auto d-flex gap-2 flex-wrap">
                            <?php if ($permisos['ajusteIngreso'] || $permisos['ajusteEgreso']): ?><button class="btn btn-secundario btn-crud btn-nuevo-movimiento" data-bs-toggle="modal" data-bs-target="#modalMovimientoInterno" data-tipo="AJUSTE"><i class="bi bi-plus-slash-minus"></i> Ajuste</button><?php endif; ?>
                            <?php if ($permisos['transferencia']): ?><button class="btn btn-intesis btn-crud btn-nuevo-movimiento" data-bs-toggle="modal" data-bs-target="#modalMovimientoInterno" data-tipo="TRANSFERENCIA"><i class="bi bi-arrow-left-right"></i> Transferencia</button><?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabMovimientos">
                            <div class="table-responsive stock-tabla-contenedor">
                                <table class="table table-hover tabla-intesis align-middle w-100">
                                    <thead><tr><th>#</th><th>Fecha</th><th>Numero</th><th>Tipo</th><th>Origen</th><th>Destino</th><th>Detalle</th><th>Total</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($movimientos as $i => $movimiento): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars((string) $movimiento['inv_movimientos_fecha']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_numero']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['sis_tipo_documento_nombre']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_origen'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_destino'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_observacion'] ?? '') ?></td>
                                                <td class="text-end"><?= number_format((float) $movimiento['total'], 2) ?></td>
                                                <td><span class="badge estado-badge estado-<?= strtolower((string) $movimiento['sis_estado_codigo']) ?>"><?= htmlspecialchars($movimiento['sis_estado_nombre']) ?></span></td>
                                                <td class="text-end acciones-tabla">
                                                    <?php if ($permisos['editar'] && in_array($movimiento['sis_estado_codigo'], ['PENDIENTE', 'EN_TRANSITO'], true)): ?>
                                                        <button class="btn btn-accion btn-editar-observacion-movimiento" data-bs-toggle="modal" data-bs-target="#modalEditarMovimiento" data-id="<?= (int) $movimiento['inv_movimientos_id'] ?>" data-detalle="<?= htmlspecialchars($movimiento['inv_movimientos_observacion'] ?? '') ?>" title="Editar"><i class="bi bi-pencil-square"></i></button>
                                                    <?php endif; ?>
                                                    <?php if ($permisos['aprobar'] && $movimiento['sis_estado_codigo'] === 'PENDIENTE'): ?>
                                                        <form method="post" action="<?= $appUrl ?>/inventario/movimientos/aprobar" class="d-inline formulario-confirmar"><input type="hidden" name="movimiento_id" value="<?= (int) $movimiento['inv_movimientos_id'] ?>"><button class="btn btn-accion btn-activar" title="Aprobar"><i class="bi bi-check2-square"></i></button></form>
                                                    <?php endif; ?>
                                                    <?php if ($permisos['anular'] && in_array($movimiento['sis_estado_codigo'], ['PENDIENTE', 'EN_TRANSITO'], true)): ?>
                                                        <form method="post" action="<?= $appUrl ?>/inventario/movimientos/anular" class="d-inline formulario-confirmar"><input type="hidden" name="movimiento_id" value="<?= (int) $movimiento['inv_movimientos_id'] ?>"><button class="btn btn-accion btn-inactivar" title="Anular"><i class="bi bi-x-octagon"></i></button></form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tabTransferencias">
                            <div class="table-responsive">
                                <table class="table table-hover tabla-intesis align-middle w-100">
                                    <thead><tr><th>#</th><th>Fecha</th><th>Numero</th><th>Origen</th><th>Destino</th><th>Detalle</th><th class="text-end">Acciones</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($transferenciasPendientes as $i => $movimiento): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars((string) $movimiento['inv_movimientos_fecha']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_numero']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_origen']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_destino']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_observacion'] ?? '') ?></td>
                                                <td class="text-end">
                                                    <?php if ($permisos['recibir']): ?>
                                                        <form method="post" action="<?= $appUrl ?>/inventario/movimientos/recibir" class="d-inline formulario-confirmar"><input type="hidden" name="movimiento_id" value="<?= (int) $movimiento['inv_movimientos_id'] ?>"><button class="btn btn-accion btn-activar" title="Recibir"><i class="bi bi-box-arrow-in-down"></i></button></form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade modal-intesis" id="modalMovimientoInterno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="formularioMovimientoInterno" method="post" action="<?= $appUrl ?>/inventario/movimientos/crear">
            <input type="hidden" name="tipo" id="movimiento_tipo">
            <input type="hidden" name="lineas_json" id="movimiento_lineas_json">
            <?php foreach ($bodegas as $bodega): ?><input type="hidden" name="bodega_autoaprobado[<?= (int) $bodega['inv_bodega_id'] ?>]" value="<?= !empty($bodega['inv_bodega_autoaprobado']) ? '1' : '0' ?>"><?php endforeach; ?>
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalMovimientoTitulo">Movimiento interno</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2 mb-3">
                    <div class="col-md-3"><label class="form-label">Fecha</label><input type="date" class="form-control form-control-sm" name="fecha" id="movimiento_fecha" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Usuario</label><input class="form-control form-control-sm" value="<?= htmlspecialchars($usuario['nombre']) ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Detalle</label><input class="form-control form-control-sm" name="detalle" id="movimiento_detalle" required maxlength="180"></div>
                </div>
                <div class="table-responsive stock-tabla-contenedor">
                    <table class="table table-sm align-middle" id="tablaLineasMovimiento">
                        <thead><tr><th>Codigo</th><th>Descripcion</th><th>Costo</th><th>PVP</th><th>Accion</th><th>Origen</th><th>Destino</th><th>Cantidad</th><th>Total</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-secundario btn-sm" id="btnAgregarLineaMovimiento"><i class="bi bi-plus-lg"></i> Agregar linea</button>
                <div class="text-end fw-semibold mt-2">Total: <span id="movimiento_total">0.00</span></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalBuscarProductoMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title">Buscar producto</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="input-group input-group-sm mb-2"><input class="form-control" id="buscar_producto_movimiento_texto" placeholder="Buscar por codigo o nombre"><button class="btn btn-intesis" id="btnBuscarProductoMovimiento" type="button"><i class="bi bi-search"></i></button></div>
                <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Imagen</th><th>Codigo</th><th>Producto</th><th>Marca</th><th>PVP</th><th>Stock</th><th></th></tr></thead><tbody id="tablaBuscarProductoMovimiento"></tbody></table></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalEditarMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= $appUrl ?>/inventario/movimientos/editar">
            <input type="hidden" name="movimiento_id" id="editar_movimiento_id">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title">Editar observacion</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><label class="form-label">Detalle</label><input class="form-control form-control-sm" name="detalle" id="editar_movimiento_detalle" required></div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<script>
window.INTESIS_BODEGAS_MOVIMIENTO = <?= json_encode($bodegas, JSON_UNESCAPED_UNICODE) ?>;
window.INTESIS_MOVIMIENTO_PERMISOS = <?= json_encode($permisos, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
