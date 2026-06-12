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
                    <div class="panel-crud-cabecera gap-2 flex-wrap align-items-end">
                        <div class="d-flex gap-2 align-items-end flex-wrap">
                            <div>
                                <label class="form-label mb-1 small text-muted">Desde</label>
                                <input type="date" id="filtroMovDesde" class="form-control form-control-sm" style="width:135px">
                            </div>
                            <div>
                                <label class="form-label mb-1 small text-muted">Hasta</label>
                                <input type="date" id="filtroMovHasta" class="form-control form-control-sm" style="width:135px">
                            </div>
                        </div>
                        <div class="nav nav-pills tabs-intesis" role="tablist">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabMovimientos" type="button">Movimientos</button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTransferencias" type="button">Transferencias Pendientes (<span id="contadorTransferencias"><?= count($transferenciasPendientes) ?></span>)</button>
                        </div>
                        <div class="ms-auto d-flex gap-2 flex-wrap">
                            <?php if ($permisos['ajusteIngreso'] || $permisos['ajusteEgreso']): ?><button class="btn btn-secundario btn-crud btn-nuevo-movimiento" data-bs-toggle="modal" data-bs-target="#modalMovimientoInterno" data-tipo="AJUSTE"><i class="bi bi-plus-slash-minus"></i> Ajuste</button><?php endif; ?>
                            <?php if ($permisos['transferencia']): ?><button class="btn btn-intesis btn-crud btn-nuevo-movimiento" data-bs-toggle="modal" data-bs-target="#modalMovimientoInterno" data-tipo="TRANSFERENCIA"><i class="bi bi-arrow-left-right"></i> Transferencia</button><?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabMovimientos">
                            <div class="d-flex gap-2 align-items-center py-2 flex-wrap">
                                <label class="form-label mb-0 small text-muted">Estado:</label>
                                <select id="filtroMovEstado" class="form-control form-control-sm" style="width:160px">
                                    <option value="">Todos</option>
                                    <option value="PENDIENTE">Pendiente</option>
                                    <option value="EN_TRANSITO">En Transito</option>
                                    <option value="PROCESADO">Procesado</option>
                                    <option value="RECIBIDO">Recibido</option>
                                    <option value="ANULADO">Anulado</option>
                                </select>
                            </div>
                            <div class="table-responsive stock-tabla-contenedor">
                                <table id="tablaMovimientosLista" class="table table-hover align-middle w-100">
                                    <thead><tr><th>#</th><th>Fecha</th><th>Numero</th><th>Tipo</th><th>Origen</th><th>Destino</th><th>Detalle</th><th class="text-end">Total</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($movimientos as $i => $movimiento): ?>
                                            <?php
                                                $fechaMov = (string) $movimiento['inv_movimientos_fecha'];
                                                $fechaSolo = substr($fechaMov, 0, 10);
                                                $horaMov = strlen($fechaMov) > 10 ? substr($fechaMov, 11, 5) : '';
                                            ?>
                                            <tr data-fecha="<?= htmlspecialchars($fechaSolo) ?>" data-estado="<?= htmlspecialchars((string) $movimiento['sis_estado_codigo']) ?>">
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($fechaSolo) ?><?= $horaMov ? ' <span class="text-muted small">' . htmlspecialchars($horaMov) . '</span>' : '' ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_numero']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['sis_tipo_documento_nombre']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_origen'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_destino'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_observacion'] ?? '') ?></td>
                                                <td class="text-end"><?= number_format((float) $movimiento['total'], 2) ?></td>
                                                <td><span class="badge estado-badge estado-<?= strtolower((string) $movimiento['sis_estado_codigo']) ?>"><?= htmlspecialchars($movimiento['sis_estado_nombre']) ?></span></td>
                                                <td class="text-end acciones-tabla">
                                                    <?php if ($permisos['detalle']): ?>
                                                        <button type="button" class="btn btn-accion btn-ver-detalle-movimiento" data-id="<?= (int) $movimiento['inv_movimientos_id'] ?>" data-bs-toggle="modal" data-bs-target="#modalDetalleMovimiento" title="Ver detalle"><i class="bi bi-eye"></i></button>
                                                    <?php endif; ?>
                                                    <?php $estadoCod = (string) $movimiento['sis_estado_codigo']; ?>
                                                    <?php if ($estadoCod !== 'PENDIENTE' && $estadoCod !== 'EN_TRANSITO'): ?>
                                                        <button type="button" class="btn btn-accion btn-mov-pdf"
                                                            data-empresa="<?= (int) $usuario['empresa_id'] ?>"
                                                            data-documento-id="<?= (int) $movimiento['inv_movimientos_id'] ?>"
                                                            data-documento-numero="<?= htmlspecialchars($movimiento['inv_movimientos_numero']) ?>"
                                                            title="Ver PDF"><i class="bi bi-file-pdf"></i></button>
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
                                <table id="tablaTransferenciasLista" class="table table-hover align-middle w-100">
                                    <thead><tr><th>#</th><th>Fecha</th><th>Numero</th><th>Origen</th><th>Destino</th><th>Detalle</th><th class="text-end">Acciones</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($transferenciasPendientes as $i => $movimiento): ?>
                                            <?php
                                                $fechaMov = (string) $movimiento['inv_movimientos_fecha'];
                                                $fechaSolo = substr($fechaMov, 0, 10);
                                                $horaMov = strlen($fechaMov) > 10 ? substr($fechaMov, 11, 5) : '';
                                            ?>
                                            <tr data-fecha="<?= htmlspecialchars($fechaSolo) ?>">
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($fechaSolo) ?><?= $horaMov ? ' <span class="text-muted small">' . htmlspecialchars($horaMov) . '</span>' : '' ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_numero']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_origen']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['bodega_destino']) ?></td>
                                                <td><?= htmlspecialchars($movimiento['inv_movimientos_observacion'] ?? '') ?></td>
                                                <td class="text-end acciones-tabla">
                                                    <?php if ($permisos['detalle']): ?>
                                                        <button type="button" class="btn btn-accion btn-ver-detalle-movimiento" data-id="<?= (int) $movimiento['inv_movimientos_id'] ?>" data-bs-toggle="modal" data-bs-target="#modalDetalleMovimiento" title="Ver detalle"><i class="bi bi-eye"></i></button>
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

<!-- MODAL DETALLE MOVIMIENTO -->
<div class="modal fade modal-intesis" id="modalDetalleMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="detalleMovimientoTitulo">Detalle del movimiento</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detalleMovimientoBody">
                <div class="text-center py-4 text-muted"><i class="bi bi-hourglass-split"></i> Cargando...</div>
            </div>
            <div class="modal-footer" id="detalleMovimientoFooter">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CREAR/CREAR MOVIMIENTO INTERNO -->
<div class="modal fade modal-intesis" id="modalMovimientoInterno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="formularioMovimientoInterno" method="post" action="<?= $appUrl ?>/inventario/movimientos/crear">
            <input type="hidden" name="tipo" id="movimiento_tipo">
            <input type="hidden" name="lineas_json" id="movimiento_lineas_json">
            <?php foreach ($bodegas as $bodega): ?><input type="hidden" name="bodega_autoaprobado[<?= (int) $bodega['inv_bodega_id'] ?>]" value="<?= !empty($bodega['inv_bodega_autoaprobado']) ? '1' : '0' ?>"><?php endforeach; ?>
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalMovimientoTitulo">Movimiento interno</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2 mb-3">
                    <div class="col-md-3"><label class="form-label">Fecha y hora</label><input type="text" class="form-control form-control-sm" value="<?= date('d/m/Y H:i') ?>" disabled></div>
                    <div class="col-md-3"><label class="form-label">Usuario</label><input class="form-control form-control-sm" value="<?= htmlspecialchars($usuario['nombre']) ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Detalle</label><input class="form-control form-control-sm" name="detalle" id="movimiento_detalle" required maxlength="180"></div>
                </div>
                <div class="table-responsive stock-tabla-contenedor">
                    <table class="table table-sm align-middle" id="tablaLineasMovimiento">
                        <thead><tr><th>Codigo</th><th>Descripcion</th><th>PVP</th><th>Accion</th><th>Origen</th><th class="th-stock-origen">Stock origen</th><th>Destino</th><th>Cantidad</th><th>Total</th><th></th></tr></thead>
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

<!-- MODAL BUSCAR PRODUCTO -->
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

<!-- MODAL EDITAR OBSERVACION -->
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

<div class="modal fade modal-intesis" id="modalPdfKardex" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalPdfKardexTitulo">Documento</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body p-0"><iframe id="visorPdfKardex" class="visor-pdf-kardex" title="Documento PDF"></iframe></div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
        </div>
    </div>
</div>

<script>
window.INTESIS_BODEGAS_MOVIMIENTO = <?= json_encode($bodegas, JSON_UNESCAPED_UNICODE) ?>;
window.INTESIS_MOVIMIENTO_PERMISOS = <?= json_encode($permisos, JSON_UNESCAPED_UNICODE) ?>;
window.INTESIS_APP_URL = <?= json_encode($appUrl, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
