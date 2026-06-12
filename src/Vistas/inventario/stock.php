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
                <li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Inventario</span><i class="bi bi-chevron-right"></i><strong>Stock</strong></span></li>
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
                        <?php if ($esSuperusuario): ?>
                            <form class="d-flex gap-2 align-items-center" method="get" action="<?= $appUrl ?>/inventario/stock">
                                <select class="form-control form-control-sm" name="empresa_id" onchange="this.form.submit()">
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?= (int) $empresa['sis_empresa_id'] ?>" <?= (int) $empresa['sis_empresa_id'] === (int) $empresaSeleccionada ? 'selected' : '' ?>><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                        <div class="ms-auto d-flex gap-2 flex-wrap">
                            <?php if ($permisos['plantilla']): ?><a class="btn btn-secundario btn-crud" href="<?= $appUrl ?>/inventario/stock/plantilla"><i class="bi bi-filetype-csv"></i> Plantilla</a><?php endif; ?>
                            <?php if ($permisos['importar']): ?><button type="button" class="btn btn-secundario btn-crud" data-bs-toggle="modal" data-bs-target="#modalImportarStock"><i class="bi bi-file-earmark-arrow-up"></i> Importar</button><?php endif; ?>
                            <?php if ($permisos['registrar']): ?><button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalSaldoStock"><i class="bi bi-plus-square"></i> Registrar saldo</button><?php endif; ?>
                        </div>
                    </div>
                    <div class="table-responsive stock-tabla-contenedor">
                        <table id="tablaStock" class="table table-hover tabla-intesis align-middle w-100">
                            <thead>
                                <tr>
                                    <th>#</th><th>Imagen</th><th>Codigo</th><th>Nombre</th>
                                    <?php foreach ($bodegas as $bodega): ?><th title="<?= htmlspecialchars($bodega['inv_bodega_nombre']) ?>"><?= htmlspecialchars($bodega['inv_bodega_codigo']) ?></th><?php endforeach; ?>
                                    <th>Total</th><th>PVP</th><th>Marca</th><th>Cod. proveedor</th><th>Ultima venta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock as $indice => $producto): ?>
                                    <?php $total = 0.0; ?>
                                    <tr>
                                        <td><?= $indice + 1 ?></td>
                                        <td>
                                            <button type="button" class="producto-miniatura btn-abrir-galeria-producto" title="Ver galeria" data-producto="<?= (int) $producto['inv_producto_id'] ?>" data-empresa="<?= (int) $producto['sis_empresa_id'] ?>" data-nombre="<?= htmlspecialchars($producto['inv_producto_nombre']) ?>" data-bs-toggle="modal" data-bs-target="#modalGaleriaProducto">
                                                <?php if (!empty($producto['imagen_principal_id'])): ?>
                                                    <img src="<?= $appUrl ?>/inventario/productos/archivos/ver?archivo_id=<?= (int) $producto['imagen_principal_id'] ?>" alt="Imagen producto">
                                                <?php else: ?>
                                                    <i class="bi bi-image"></i>
                                                <?php endif; ?>
                                            </button>
                                        </td>
                                        <td><?= htmlspecialchars($producto['inv_producto_codigo_principal']) ?></td>
                                        <td><?= htmlspecialchars($producto['inv_producto_nombre']) ?></td>
                                        <?php foreach ($bodegas as $bodega): ?>
                                            <?php $cantidad = (float) ($producto['saldos'][(int) $bodega['inv_bodega_id']] ?? 0); $total += $cantidad; ?>
                                            <td class="text-center"><?= number_format($cantidad, 0) ?></td>
                                        <?php endforeach; ?>
                                        <td class="text-center fw-semibold"><?= number_format($total, 0) ?></td>
                                        <td><button type="button" class="btn btn-link btn-sm btn-stock-precios" data-producto="<?= (int) $producto['inv_producto_id'] ?>" data-empresa="<?= (int) $producto['sis_empresa_id'] ?>"><?= number_format((float) $producto['pvp'], 2) ?></button></td>
                                        <td><?= htmlspecialchars($producto['inv_marca_nombre']) ?></td>
                                        <td><button type="button" class="btn btn-link btn-sm btn-stock-codigos" data-producto="<?= (int) $producto['inv_producto_id'] ?>" data-empresa="<?= (int) $producto['sis_empresa_id'] ?>"><?= htmlspecialchars($producto['inv_codigo_proveedor_codigo'] ?? 'Ver') ?></button></td>
                                        <td><?= !empty($producto['inv_producto_ultima_venta']) ? htmlspecialchars(date('d-m-Y', strtotime((string) $producto['inv_producto_ultima_venta']))) : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade modal-intesis" id="modalGaleriaProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalGaleriaProductoTitulo">Galeria producto</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="galeria_producto_id">
                <input type="hidden" id="galeria_empresa_id">
                <div class="galeria-producto-grid" id="galeriaProductoContenedor" data-solo-lectura="1"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
        </div>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalSaldoStock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= $appUrl ?>/inventario/stock/registrar">
            <input type="hidden" name="empresa_id" value="<?= (int) $empresaSeleccionada ?>">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title">Saldo inicial</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label">Codigo producto</label><input class="form-control form-control-sm" name="codigo_producto" required></div>
                    <div class="col-md-4"><label class="form-label">Codigo bodega</label><input class="form-control form-control-sm" name="codigo_bodega" required></div>
                    <div class="col-md-4"><label class="form-label">Accion</label><select class="form-control form-control-sm" name="accion_stock"><option value="sumar">Sumar</option><option value="reemplazar">Reemplazar</option><option value="saltar">Saltar si existe</option></select></div>
                    <div class="col-md-4"><label class="form-label">Cantidad</label><input type="number" min="0" step="0.0001" class="form-control form-control-sm" name="cantidad" value="0" required></div>
                    <div class="col-md-4"><label class="form-label">Costo unitario</label><input type="number" min="0" step="0.0001" class="form-control form-control-sm" name="costo" value="0" required></div>
                    <div class="col-md-4"><label class="form-label">Descripcion</label><input class="form-control form-control-sm" name="descripcion" maxlength="200"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalImportarStock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title">Importar stock</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <input type="hidden" id="stock_importar_empresa_id" value="<?= (int) $empresaSeleccionada ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label">Stock existente</label><select class="form-control form-control-sm" id="stock_importar_accion"><option value="sumar">Sumar</option><option value="reemplazar">Reemplazar</option><option value="saltar">Saltar</option></select></div>
                    <div class="col-md-3"><label class="form-label">Producto inexistente</label><select class="form-control form-control-sm" id="stock_importar_producto"><option value="crear">Crear producto</option><option value="saltar">Saltar</option></select></div>
                    <div class="col-md-4"><label class="form-label">CSV</label><input type="file" class="form-control form-control-sm" id="stock_importar_archivo" accept=".csv,text/csv"></div>
                    <div class="col-md-2"><button type="button" class="btn btn-intesis w-100" id="btnPrevisualizarStock"><i class="bi bi-eye"></i> Previsualizar</button></div>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle" id="tablaPrevisualizacionStock">
                        <thead><tr><th>Linea</th><th>Codigo</th><th>Nombre</th><th>Marca</th><th>Bodega</th><th>Cantidad</th><th>Costo</th><th>Estado</th><th>Mensaje</th></tr></thead>
                        <tbody><tr><td colspan="9" class="text-muted">Sin previsualizacion.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button><?php if ($permisos['confirmar']): ?><button type="button" class="btn btn-intesis" id="btnConfirmarImportacionStock" disabled><i class="bi bi-check2-square"></i> Confirmar</button><?php endif; ?></div>
        </div>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalStockDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalStockDetalleTitulo">Detalle</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><div class="table-responsive"><table class="table table-sm"><thead id="stockDetalleHead"></thead><tbody id="stockDetalleBody"></tbody></table></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
        </div>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>
window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
