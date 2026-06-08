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
                <li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Inventario</span><i class="bi bi-chevron-right"></i><strong>Kardex</strong></span></li>
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
                            <form class="d-flex gap-2 align-items-center" method="get" action="<?= $appUrl ?>/inventario/kardex">
                                <select class="form-control form-control-sm" name="empresa_id" onchange="this.form.submit()">
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?= (int) $empresa['sis_empresa_id'] ?>" <?= (int) $empresa['sis_empresa_id'] === (int) $empresaSeleccionada ? 'selected' : '' ?>><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive stock-tabla-contenedor">
                        <table id="tablaKardex" class="table table-hover tabla-intesis align-middle w-100">
                            <thead>
                                <tr>
                                    <th>#</th><th>Acciones</th><th>Imagen</th><th>Codigo</th><th>Nombre</th>
                                    <?php foreach ($bodegas as $bodega): ?><th title="<?= htmlspecialchars($bodega['inv_bodega_nombre']) ?>"><?= htmlspecialchars($bodega['inv_bodega_codigo']) ?></th><?php endforeach; ?>
                                    <th>PVP</th><th>Movimientos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kardex as $indice => $producto): ?>
                                    <tr>
                                        <td><?= $indice + 1 ?></td>
                                        <td>
                                            <button type="button" class="btn btn-accion btn-kardex-detalle" title="Ver detalle" data-producto="<?= (int) $producto['inv_producto_id'] ?>" data-empresa="<?= (int) $producto['sis_empresa_id'] ?>" data-codigo="<?= htmlspecialchars($producto['inv_producto_codigo_principal']) ?>" data-nombre="<?= htmlspecialchars($producto['inv_producto_nombre']) ?>"><i class="bi bi-list-columns-reverse"></i></button>
                                        </td>
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
                                            <?php $cantidad = (float) ($producto['saldos'][(int) $bodega['inv_bodega_id']] ?? 0); ?>
                                            <td class="text-end"><?= number_format($cantidad, 2) ?></td>
                                        <?php endforeach; ?>
                                        <td class="text-end"><?= number_format((float) $producto['pvp'], 2) ?></td>
                                        <td class="text-end"><span class="badge estado-badge estado-activo"><?= (int) $producto['movimientos'] ?></span></td>
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

<div class="modal fade modal-intesis" id="modalDetalleKardex" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalDetalleKardexTitulo">Kardex</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <input type="hidden" id="kardex_detalle_empresa_id">
                <input type="hidden" id="kardex_detalle_producto_id">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-3"><label class="form-label">Desde</label><input type="date" class="form-control form-control-sm" id="kardex_detalle_desde"></div>
                    <div class="col-md-3"><label class="form-label">Hasta</label><input type="date" class="form-control form-control-sm" id="kardex_detalle_hasta"></div>
                    <div class="col-md-2"><button type="button" class="btn btn-intesis w-100" id="btnConsultarDetalleKardex"><i class="bi bi-search"></i> Consultar</button></div>
                </div>
                <div class="table-responsive stock-tabla-contenedor">
                    <table class="table table-sm table-hover align-middle" id="tablaDetalleKardex">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
        </div>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalPdfKardex" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalPdfKardexTitulo">Documento</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body p-0">
                <iframe id="visorPdfKardex" class="visor-pdf-kardex" title="Documento PDF"></iframe>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
        </div>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>
window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
