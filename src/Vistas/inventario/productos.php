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
                <li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Inventario</span><i class="bi bi-chevron-right"></i><strong>Productos</strong></span></li>
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
                    <?php if ($permisos['crear']): ?>
                        <div class="panel-crud-cabecera"><button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalProducto" data-modo="crear"><i class="bi bi-plus-square"></i> Nuevo producto</button></div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table id="tablaProductos" class="table table-hover tabla-intesis align-middle w-100">
                            <thead><tr><th>Imagen</th><th>Codigo</th><th>Nombre</th><th>Categoria</th><th>Marca</th><th>Unidad</th><th>IVA</th><th>Costo ultimo</th><th>Stock minimo</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
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
                                        <td><?= htmlspecialchars($producto['inv_categoria_nombre']) ?></td>
                                        <td><?= htmlspecialchars($producto['inv_marca_nombre']) ?></td>
                                        <td><?= htmlspecialchars($producto['inv_producto_unidad_medida']) ?></td>
                                        <td><span class="badge estado-badge <?= (int) $producto['inv_producto_lleva_iva'] === 1 ? 'estado-activo' : 'estado-inactivo' ?>"><?= (int) $producto['inv_producto_lleva_iva'] === 1 ? 'SI' : 'NO' ?></span></td>
                                        <td><?= number_format((float) $producto['inv_producto_costo_ultimo'], 2) ?></td>
                                        <td><?= number_format((float) $producto['inv_producto_stock_minimo'], 2) ?></td>
                                        <td><span class="badge estado-badge estado-<?= strtolower((string) $producto['sis_estado_codigo']) ?>"><?= htmlspecialchars($producto['sis_estado_nombre']) ?></span></td>
                                        <td class="text-end acciones-tabla">
                                            <button type="button" class="btn btn-accion btn-abrir-galeria-producto" title="Galeria" data-producto="<?= (int) $producto['inv_producto_id'] ?>" data-empresa="<?= (int) $producto['sis_empresa_id'] ?>" data-nombre="<?= htmlspecialchars($producto['inv_producto_nombre']) ?>" data-bs-toggle="modal" data-bs-target="#modalGaleriaProducto"><i class="bi bi-images"></i></button>
                                            <button type="button" class="btn btn-accion btn-editar-producto" title="Ver producto" data-bs-toggle="modal" data-bs-target="#modalProducto" data-modo="ver" data-id="<?= (int) $producto['inv_producto_id'] ?>" data-empresa="<?= (int) $producto['sis_empresa_id'] ?>" data-categoria="<?= (int) $producto['inv_categoria_id'] ?>" data-marca="<?= (int) $producto['inv_marca_id'] ?>" data-codigo="<?= htmlspecialchars($producto['inv_producto_codigo_principal']) ?>" data-nombre="<?= htmlspecialchars($producto['inv_producto_nombre']) ?>" data-descripcion="<?= htmlspecialchars($producto['inv_producto_descripcion'] ?? '') ?>" data-iva="<?= (int) $producto['inv_producto_lleva_iva'] ?>" data-costo="<?= htmlspecialchars((string) $producto['inv_producto_costo_ultimo']) ?>" data-minimo="<?= htmlspecialchars((string) $producto['inv_producto_stock_minimo']) ?>" data-maximo="<?= htmlspecialchars((string) $producto['inv_producto_stock_maximo']) ?>"><i class="bi bi-eye"></i></button>
                                            <?php if ($permisos['editar']): ?>
                                                <button type="button" class="btn btn-accion btn-editar-producto" title="Editar producto" data-bs-toggle="modal" data-bs-target="#modalProducto" data-modo="editar" data-id="<?= (int) $producto['inv_producto_id'] ?>" data-empresa="<?= (int) $producto['sis_empresa_id'] ?>" data-categoria="<?= (int) $producto['inv_categoria_id'] ?>" data-marca="<?= (int) $producto['inv_marca_id'] ?>" data-codigo="<?= htmlspecialchars($producto['inv_producto_codigo_principal']) ?>" data-nombre="<?= htmlspecialchars($producto['inv_producto_nombre']) ?>" data-descripcion="<?= htmlspecialchars($producto['inv_producto_descripcion'] ?? '') ?>" data-iva="<?= (int) $producto['inv_producto_lleva_iva'] ?>" data-costo="<?= htmlspecialchars((string) $producto['inv_producto_costo_ultimo']) ?>" data-minimo="<?= htmlspecialchars((string) $producto['inv_producto_stock_minimo']) ?>" data-maximo="<?= htmlspecialchars((string) $producto['inv_producto_stock_maximo']) ?>"><i class="bi bi-pencil-square"></i></button>
                                            <?php endif; ?>
                                            <?php if ($producto['sis_estado_codigo'] !== 'ACTIVO' && $permisos['activar']): ?>
                                                <form action="<?= $appUrl ?>/inventario/productos/activar" method="post" class="d-inline"><input type="hidden" name="producto_id" value="<?= (int) $producto['inv_producto_id'] ?>"><button type="submit" class="btn btn-accion btn-activar" title="Activar producto"><i class="bi bi-toggle-on"></i></button></form>
                                            <?php endif; ?>
                                            <?php if ($producto['sis_estado_codigo'] === 'ACTIVO' && $permisos['inactivar']): ?>
                                                <form action="<?= $appUrl ?>/inventario/productos/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_PRODUCTO"><input type="hidden" name="producto_id" value="<?= (int) $producto['inv_producto_id'] ?>"><button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar producto"><i class="bi bi-toggle-off"></i></button></form>
                                            <?php endif; ?>
                                        </td>
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

<div class="modal fade modal-intesis" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-producto-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="formularioProducto" method="post" action="<?= $appUrl ?>/inventario/productos/crear">
            <input type="hidden" name="producto_id" id="producto_id">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalProductoTitulo">Nuevo producto</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <?php if ($esSuperusuario): ?>
                        <div class="col-md-3"><label class="form-label" for="producto_empresa_id">Empresa</label><select class="form-control form-control-sm" id="producto_empresa_id" name="empresa_id" required><option value="">Seleccione</option><?php foreach ($empresas as $empresa): ?><option value="<?= (int) $empresa['sis_empresa_id'] ?>"><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></option><?php endforeach; ?></select></div>
                    <?php endif; ?>
                    <input type="hidden" id="producto_codigo_auxiliar" name="codigo_auxiliar" value="">
                    <div class="col-md-3"><label class="form-label" for="producto_codigo_principal">Codigo</label><input type="text" class="form-control form-control-sm campo-producto-uniforme" id="producto_codigo_principal" name="codigo_principal" maxlength="40" required></div>
                    <div class="col-md-3"><label class="form-label" for="producto_nombre">Nombre</label><input type="text" class="form-control form-control-sm campo-producto-uniforme" id="producto_nombre" name="nombre" maxlength="180" required></div>
                    <div class="col-md-3"><label class="form-label" for="producto_descripcion">Descripcion</label><input type="text" class="form-control form-control-sm campo-producto-uniforme" id="producto_descripcion" name="descripcion" maxlength="250"></div>
                    <div class="col-md-3"><label class="form-label" for="producto_categoria_id">Categoria</label><div class="input-group input-group-sm"><select class="form-control producto-select-empresa" id="producto_categoria_id" name="categoria_id" required><option value="">Seleccione</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int) $categoria['inv_categoria_id'] ?>" data-empresa="<?= (int) $categoria['sis_empresa_id'] ?>"><?= htmlspecialchars($categoria['inv_categoria_nombre']) ?></option><?php endforeach; ?></select><?php if ($permisos['crear_categoria']): ?><button class="btn btn-outline-secondary" type="button" data-bs-target="#modalCategoriaRapida" title="Nueva categoria"><i class="bi bi-plus-lg"></i></button><?php endif; ?></div></div>
                    <div class="col-md-3"><label class="form-label" for="producto_marca_id">Marca</label><div class="input-group input-group-sm"><select class="form-control producto-select-empresa" id="producto_marca_id" name="marca_id" required><option value="">Seleccione</option><?php foreach ($marcas as $marca): ?><option value="<?= (int) $marca['inv_marca_id'] ?>" data-empresa="<?= (int) $marca['sis_empresa_id'] ?>"><?= htmlspecialchars($marca['inv_marca_nombre']) ?></option><?php endforeach; ?></select><?php if ($permisos['crear_marca']): ?><button class="btn btn-outline-secondary" type="button" data-bs-target="#modalMarcaRapida" title="Nueva marca"><i class="bi bi-plus-lg"></i></button><?php endif; ?></div></div>
                    <div class="col-md-3"><label class="form-label" for="producto_costo_ultimo">Costo ultimo</label><input type="number" min="0" step="0.0001" class="form-control form-control-sm" id="producto_costo_ultimo" name="costo_ultimo" value="0"></div>
                    <div class="col-md-3"><label class="form-label" for="producto_stock_minimo">Stock minimo</label><input type="number" min="0" step="0.0001" class="form-control form-control-sm" id="producto_stock_minimo" name="stock_minimo" value="0"></div>
                    <div class="col-md-3"><label class="form-label" for="producto_stock_maximo">Stock maximo</label><input type="number" min="0" step="0.0001" class="form-control form-control-sm" id="producto_stock_maximo" name="stock_maximo" value="0"></div>
                    <div class="col-md-3"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="producto_lleva_iva" name="lleva_iva" value="1" checked><label class="form-check-label" for="producto_lleva_iva">IVA</label></div></div>
                    <div class="col-12">
                        <div class="galeria-producto-panel">
                            <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
                                <strong><i class="bi bi-images"></i> Galeria</strong>
                                <div class="d-flex gap-2">
                                    <input type="file" id="producto_imagenes" class="d-none" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
                                    <button type="button" class="btn btn-secundario btn-sm" id="btnSeleccionarImagenesProducto" disabled><i class="bi bi-cloud-upload"></i> Subir imagenes</button>
                                    <button type="button" class="btn btn-secundario btn-sm" id="btnAbrirGaleriaProductoModal" disabled data-bs-toggle="modal" data-bs-target="#modalGaleriaProducto"><i class="bi bi-eye"></i> Ver galeria</button>
                                </div>
                            </div>
                            <div class="texto-ayuda-producto" id="ayudaGaleriaProducto">Guarde el producto para habilitar la carga de imagenes.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis" id="btnGuardarProducto"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalGaleriaProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title" id="modalGaleriaProductoTitulo">Galeria producto</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="galeria_producto_id">
                <input type="hidden" id="galeria_empresa_id">
                <div class="galeria-producto-grid" id="galeriaProductoContenedor"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
        </div>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalCategoriaRapida" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form class="modal-content" id="formularioCategoriaRapida" method="post" action="<?= $appUrl ?>/inventario/categorias/ajax-crear">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title">Nueva categoria</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto"><input type="hidden" name="empresa_id" id="categoria_rapida_empresa_id"><label class="form-label" for="categoria_rapida_nombre">Nombre</label><input class="form-control form-control-sm" id="categoria_rapida_nombre" name="nombre" required><label class="form-label mt-2" for="categoria_rapida_descripcion">Descripcion</label><textarea class="form-control form-control-sm" id="categoria_rapida_descripcion" name="descripcion" rows="2"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalMarcaRapida" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form class="modal-content" id="formularioMarcaRapida" method="post" action="<?= $appUrl ?>/inventario/marcas/ajax-crear">
            <div class="modal-header"><div><p class="modal-etiqueta">Inventario</p><h2 class="modal-title">Nueva marca</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto"><input type="hidden" name="empresa_id" id="marca_rapida_empresa_id"><label class="form-label" for="marca_rapida_nombre">Nombre</label><input class="form-control form-control-sm" id="marca_rapida_nombre" name="nombre" required></div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>
window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;
window.INTESIS_EMPRESA_ACTIVA = <?= (int) $usuario['empresa_id'] ?>;
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
