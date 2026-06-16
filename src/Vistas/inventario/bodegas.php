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
                <li class="nav-item">
                    <span class="nav-link breadcrumb-navbar">
                        <span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Inventario</span><i class="bi bi-chevron-right"></i><strong>Bodegas</strong>
                    </span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li>
                <li class="nav-item">
                    <form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a>
        </div>
        <div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div>
    </aside>

    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <section class="panel-crud">
                    <?php if ($permisos['crear']): ?>
                        <div class="panel-crud-cabecera">
                            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalBodega" data-modo="crear">
                                <i class="bi bi-plus-square"></i> Nueva bodega
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table id="tablaBodegas" class="table table-hover tabla-intesis align-middle w-100">
                            <thead>
                                <tr>
                                     <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                                     <?php if ($esSuperusuario): ?><th>Codigo</th><?php endif; ?>
                                    <th>Nombre</th>
                                    <th>Principal</th>
                                    <th>Virtual</th>
                                    <th>Neg.</th>
                                    <th>Autoaprob.</th>
                                    <th>Estado</th>
                                    <th class="text-center">Usuarios</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bodegas as $bodega): ?>
                                    <tr>
                                        <?php if ($esSuperusuario): ?><td><?= htmlspecialchars($bodega['sis_empresa_nombre_comercial']) ?></td><?php endif; ?>
                                        <?php if ($esSuperusuario): ?><td><?= htmlspecialchars($bodega['inv_bodega_codigo']) ?></td><?php endif; ?>
                                        <td><?= htmlspecialchars($bodega['inv_bodega_nombre']) ?></td>
                                        <td><span class="badge estado-badge <?= $bodega['inv_bodega_es_principal'] ? 'estado-activo' : 'estado-inactivo' ?>"><?= $bodega['inv_bodega_es_principal'] ? 'SI' : 'NO' ?></span></td>
                                        <td><span class="badge estado-badge <?= $bodega['inv_bodega_virtual'] ? 'estado-activo' : 'estado-inactivo' ?>"><?= $bodega['inv_bodega_virtual'] ? 'SI' : 'NO' ?></span></td>
                                        <td><span class="badge estado-badge <?= !empty($bodega['inv_bodega_negativos']) ? 'estado-pendiente' : 'estado-inactivo' ?>"><?= !empty($bodega['inv_bodega_negativos']) ? 'SI' : 'NO' ?></span></td>
                                        <td><span class="badge estado-badge <?= !empty($bodega['inv_bodega_autoaprobado']) ? 'estado-procesado' : 'estado-inactivo' ?>"><?= !empty($bodega['inv_bodega_autoaprobado']) ? 'SI' : 'NO' ?></span></td>
                                        <td><span class="badge estado-badge estado-<?= strtolower((string) $bodega['sis_estado_codigo']) ?>"><?= htmlspecialchars($bodega['sis_estado_nombre']) ?></span></td>
                                        <td class="text-center">
                                            <?php $totalUsuarios = (int) ($bodega['total_usuarios'] ?? 0); ?>
                                            <?php if ($totalUsuarios > 0): ?>
                                                <button type="button" class="btn btn-link btn-sm p-0 btn-ver-usuarios-bodega" data-id="<?= (int) $bodega['inv_bodega_id'] ?>" data-nombre="<?= htmlspecialchars($bodega['inv_bodega_codigo'] . ' - ' . $bodega['inv_bodega_nombre']) ?>" title="Ver usuarios asignados">
                                                    <span class="badge bg-primary"><?= $totalUsuarios ?></span>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end acciones-tabla">
                                            <?php if ($permisos['editar']): ?>
                                                <button type="button" class="btn btn-accion btn-editar-bodega" title="Editar bodega" data-bs-toggle="modal" data-bs-target="#modalBodega" data-modo="editar" data-id="<?= (int) $bodega['inv_bodega_id'] ?>" data-empresa="<?= (int) $bodega['sis_empresa_id'] ?>" data-codigo="<?= htmlspecialchars($bodega['inv_bodega_codigo']) ?>" data-nombre="<?= htmlspecialchars($bodega['inv_bodega_nombre']) ?>" data-descripcion="<?= htmlspecialchars($bodega['inv_bodega_descripcion'] ?? '') ?>" data-direccion="<?= htmlspecialchars($bodega['inv_bodega_direccion'] ?? '') ?>" data-principal="<?= $bodega['inv_bodega_es_principal'] ? '1' : '0' ?>" data-virtual="<?= $bodega['inv_bodega_virtual'] ? '1' : '0' ?>" data-negativos="<?= !empty($bodega['inv_bodega_negativos']) ? '1' : '0' ?>" data-autoaprobado="<?= !empty($bodega['inv_bodega_autoaprobado']) ? '1' : '0' ?>" data-establecimiento="<?= htmlspecialchars($bodega['inv_bodega_establecimiento'] ?? '') ?>" data-punto="<?= htmlspecialchars($bodega['inv_bodega_punto_emision'] ?? '') ?>"><i class="bi bi-pencil-square"></i></button>
                                            <?php endif; ?>
                                            <?php if ($permisos['usuarios']): ?>
                                                <button type="button" class="btn btn-accion btn-bodega-usuarios" title="Usuarios de bodega" data-bs-toggle="modal" data-bs-target="#modalBodegaUsuarios" data-id="<?= (int) $bodega['inv_bodega_id'] ?>" data-nombre="<?= htmlspecialchars($bodega['inv_bodega_codigo'] . ' - ' . $bodega['inv_bodega_nombre']) ?>"><i class="bi bi-people"></i></button>
                                            <?php endif; ?>
                                            <?php if ($bodega['sis_estado_codigo'] !== 'ACTIVO' && $permisos['activar']): ?>
                                                <form action="<?= $appUrl ?>/inventario/bodegas/activar" method="post" class="d-inline"><input type="hidden" name="bodega_id" value="<?= (int) $bodega['inv_bodega_id'] ?>"><button type="submit" class="btn btn-accion btn-activar" title="Activar bodega"><i class="bi bi-toggle-on"></i></button></form>
                                            <?php endif; ?>
                                            <?php if ($bodega['sis_estado_codigo'] === 'ACTIVO' && $permisos['inactivar']): ?>
                                                <form action="<?= $appUrl ?>/inventario/bodegas/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_BODEGA"><input type="hidden" name="bodega_id" value="<?= (int) $bodega['inv_bodega_id'] ?>"><button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar bodega"><i class="bi bi-toggle-off"></i></button></form>
                                            <?php endif; ?>
                                            <?php if ($permisos['eliminar']): ?>
                                                <form action="<?= $appUrl ?>/inventario/bodegas/eliminar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_ELIMINAR_BODEGA"><input type="hidden" name="bodega_id" value="<?= (int) $bodega['inv_bodega_id'] ?>"><button type="submit" class="btn btn-accion btn-eliminar" title="Eliminar bodega"><i class="bi bi-trash3"></i></button></form>
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

<div class="modal fade modal-intesis" id="modalBodega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioBodega" method="post" action="<?= $appUrl ?>/inventario/bodegas/crear">
            <input type="hidden" name="bodega_id" id="bodega_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Inventario</p>
                    <h2 class="modal-title" id="modalBodegaTitulo">Nueva bodega</h2>
                </div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <?php if ($esSuperusuario): ?>
                        <div class="col-md-6"><label class="form-label" for="bodega_empresa_id">Empresa</label><select class="form-control form-control-sm" id="bodega_empresa_id" name="empresa_id" required>
                                <option value="">Seleccione</option><?php foreach ($empresas as $empresa): ?><option value="<?= (int) $empresa['sis_empresa_id'] ?>"><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></option><?php endforeach; ?>
                            </select></div>
                    <?php endif; ?>
                    <div class="<?= $esSuperusuario ? 'col-md-3' : 'col-md-4' ?>"><label class="form-label" for="bodega_nombre">Nombre</label><input type="text" class="form-control form-control-sm" id="bodega_nombre" name="nombre" maxlength="120" required></div>
                    <div class="<?= $esSuperusuario ? 'col-md-3' : 'col-md-4 d-none' ?>"><label class="form-label" for="bodega_codigo">Codigo</label><input type="text" class="form-control form-control-sm bg-body-secondary" id="bodega_codigo" name="codigo" maxlength="30" required></div>
                    <div class="col-md-4"><label class="form-label" for="bodega_direccion">Direccion</label><input type="text" class="form-control form-control-sm" id="bodega_direccion" name="direccion" maxlength="180"></div>
                    <div class="col-4"><label class="form-label" for="bodega_descripcion">Descripcion</label><textarea class="form-control form-control-sm" id="bodega_descripcion" name="descripcion" rows="2"></textarea></div>
                    <div class="col-md-3"><label class="form-label" for="bodega_establecimiento">Establecimiento <span class="text-muted">(3 dig.)</span></label><input type="text" class="form-control form-control-sm" id="bodega_establecimiento" name="establecimiento" maxlength="3" inputmode="numeric" placeholder="001"><div class="form-text">Codigo de sede SRI (ej. 001)</div></div>
                    <div class="col-md-3"><label class="form-label" for="bodega_punto_emision">Punto emision <span class="text-muted">(3 dig.)</span></label><input type="text" class="form-control form-control-sm" id="bodega_punto_emision" name="punto_emision" maxlength="3" inputmode="numeric" placeholder="001"><div class="form-text">Terminal dentro del local (ej. 001)</div></div>
                    <div class="col-md-3" title="Marcar como bodega principal">
                        <div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="bodega_principal" name="principal" value="1"><label class="form-check-label" for="bodega_principal">Principal</label></div>
                    </div>
                    <div class="col-md-3" title="Marcar como bodega virtual (no fisica)">
                        <div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="bodega_virtual" name="virtual" value="1"><label class="form-check-label" for="bodega_virtual">Virtual</label></div>
                    </div>
                    <div class="col-md-3" title="Permitir stock negativo">
                        <div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="bodega_negativos" name="negativos" value="1"><label class="form-check-label" for="bodega_negativos">Stock negativo</label></div>
                    </div>
                    <div class="col-md-3" title="Esta bodega no necesita aprobación en procesos de inventario">
                        <div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="bodega_autoaprobado" name="autoaprobado" value="1"><label class="form-check-label" for="bodega_autoaprobado">Auto Aprueba</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<?php if ($permisos['usuarios']): ?>
    <div class="modal fade modal-intesis" id="modalBodegaUsuarios" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="modal-etiqueta">Inventario</p>
                        <h2 class="modal-title">Usuarios <span id="bodegaUsuariosTitulo"></span></h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body formulario-compacto">
                    <form id="formularioBodegaUsuarios" class="row g-2 align-items-end">
                        <input type="hidden" id="bodega_usuarios_bodega_id" name="bodega_id">
                        <div class="col-md-10">
                            <label class="form-label" for="bodega_usuarios_usuario_id">Usuario</label>
                            <select class="form-control form-control-sm" id="bodega_usuarios_usuario_id" name="usuario_id" required>
                                <option value="">Seleccione</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <?php if ($permisos['usuariosGuardar']): ?>
                                <button type="submit" class="btn btn-intesis btn-sm w-100"><i class="bi bi-save2"></i> Agregar</button>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div class="table-responsive mt-3">
                        <table class="table table-hover align-middle w-100" id="tablaBodegaUsuarios">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th class="text-center">Predeterminada</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Seleccione una bodega.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL USUARIOS DE BODEGA (SOLO LECTURA) -->
<div class="modal fade modal-intesis" id="modalVerUsuariosBodega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Inventario</p>
                    <h2 class="modal-title" id="modalVerUsuariosBodegaTitulo">Usuarios asignados</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="verUsuariosBodegaCargando" class="text-center py-4 text-muted"><i class="bi bi-hourglass-split"></i> Cargando...</div>
                <div class="table-responsive d-none" id="verUsuariosBodegaTablaContenedor">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Bodega</th>
                                <th>Fecha asignacion</th>
                            </tr>
                        </thead>
                        <tbody id="verUsuariosBodegaCuerpo"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button></div>
        </div>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>
        window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;
    </script><?php endif; ?>
<script>
    window.INTESIS_BODEGA_USUARIO_PERMISOS = <?= json_encode($permisos, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
    window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>