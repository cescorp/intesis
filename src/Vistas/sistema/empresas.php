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
                <li class="nav-item">
                    <button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button">
                        <i class="bi bi-list"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <span class="nav-link breadcrumb-navbar">
                        <span><?= htmlspecialchars($appNombre) ?></span>
                        <i class="bi bi-chevron-right"></i>
                        <span>Sistema</span>
                        <i class="bi bi-chevron-right"></i>
                        <strong>Empresas</strong>
                    </span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block">
                    <span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span>
                </li>
                <li class="nav-item">
                    <form action="<?= $appUrl ?>/salir" method="post">
                        <button class="btn btn-salir" type="submit" title="Cerrar sesion">
                            <i class="bi bi-power"></i>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="<?= $appUrl ?>/dashboard" class="brand-link">
                <span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span>
                <span class="brand-text"><?= htmlspecialchars($appNombre) ?></span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <section class="panel-crud">
                    <?php if ($permisos['crear']): ?>
                        <div class="panel-crud-cabecera">
                            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalEmpresa" data-modo="crear">
                                <i class="bi bi-building-add"></i>
                                Nueva empresa
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="tablaEmpresas" class="table table-hover tabla-intesis align-middle w-100">
                            <thead>
                                <tr>
                                    <th>RUC</th>
                                    <th>Razon social</th>
                                    <th>Nombre comercial</th>
                                    <th>Telefono</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empresas as $empresa): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_ruc']) ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_razon_social']) ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_telefono'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_email'] ?: '-') ?></td>
                                        <td>
                                            <span class="badge estado-badge estado-<?= strtolower((string) $empresa['sis_estado_codigo']) ?>">
                                                <?= htmlspecialchars($empresa['sis_estado_nombre']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end acciones-tabla">
                                            <?php if ($permisos['editar']): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-accion btn-editar-empresa"
                                                    title="Editar empresa"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEmpresa"
                                                    data-modo="editar"
                                                    data-id="<?= (int) $empresa['sis_empresa_id'] ?>"
                                                    data-ruc="<?= htmlspecialchars($empresa['sis_empresa_ruc']) ?>"
                                                    data-razon="<?= htmlspecialchars($empresa['sis_empresa_razon_social']) ?>"
                                                    data-comercial="<?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?>"
                                                    data-direccion="<?= htmlspecialchars($empresa['sis_empresa_direccion']) ?>"
                                                    data-telefono="<?= htmlspecialchars($empresa['sis_empresa_telefono'] ?? '') ?>"
                                                    data-email="<?= htmlspecialchars($empresa['sis_empresa_email'] ?? '') ?>"
                                                    data-obligado="<?= $empresa['sis_empresa_obligado_contabilidad'] ? '1' : '0' ?>"
                                                    data-especial="<?= $empresa['sis_empresa_contribuyente_especial'] ? '1' : '0' ?>"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($empresa['sis_estado_codigo'] === 'ACTIVO' && $permisos['inactivar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/empresas/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_EMPRESA">
                                                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['sis_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar empresa">
                                                        <i class="bi bi-toggle-off"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($empresa['sis_estado_codigo'] === 'INACTIVO' && $permisos['activar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/empresas/activar" method="post" class="d-inline">
                                                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['sis_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-activar" title="Activar empresa">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($permisos['eliminar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/empresas/eliminar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_ELIMINAR_EMPRESA">
                                                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['sis_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-eliminar" title="Eliminar empresa">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
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

<div class="modal fade modal-intesis" id="modalEmpresa" tabindex="-1" aria-labelledby="modalEmpresaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="formularioEmpresa" method="post" action="<?= $appUrl ?>/sistema/empresas/crear">
            <input type="hidden" name="empresa_id" id="empresa_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Sistema</p>
                    <h2 class="modal-title" id="modalEmpresaTitulo">Nueva empresa</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="ruc">RUC</label>
                        <input type="text" class="form-control" id="ruc" name="ruc" maxlength="13" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="razon_social">Razon social</label>
                        <input type="text" class="form-control" id="razon_social" name="razon_social" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nombre_comercial">Nombre comercial</label>
                        <input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="direccion">Direccion matriz</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="telefono">Telefono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch switch-intesis mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="contribuyente_especial" name="contribuyente_especial">
                            <label class="form-check-label" for="contribuyente_especial">Contribuyente especial</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch switch-intesis">
                            <input class="form-check-input" type="checkbox" role="switch" id="obligado_contabilidad" name="obligado_contabilidad">
                            <label class="form-check-label" for="obligado_contabilidad">Obligado a llevar contabilidad</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn btn-intesis">
                    <i class="bi bi-save2"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <script>
        window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;
    </script>
<?php endif; ?>
<script>
    window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
