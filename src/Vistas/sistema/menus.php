<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
$porPadreCrud = [];
foreach ($menusCrud as $menu) {
    $porPadreCrud[(int) ($menu['sis_menu_padre'] ?? 0)][] = $menu;
}
$menusTipoMenu = array_values(array_filter($menusCrud, fn (array $menu): bool => $menu['sis_menu_tipo'] === 'M'));
$accionesPorMenu = [];
foreach ($menusCrud as $menu) {
    if ($menu['sis_menu_tipo'] === 'B') {
        $accionesPorMenu[(int) ($menu['sis_menu_padre'] ?? 0)][] = $menu;
    }
}

/**
 * ***************************************************************************
 * * RENDERIZA ARBOL GLOBAL DE MENUS Y BOTONES PARA ADMINISTRACION.
 * ***************************************************************************
 */
$renderizarArbolMenus = function (int $padre, int $nivel) use (&$renderizarArbolMenus, $porPadreCrud, $permisos): void {
    foreach ($porPadreCrud[$padre] ?? [] as $menu) {
        if ($menu['sis_menu_tipo'] !== 'M') {
            continue;
        }
        $hijos = $porPadreCrud[(int) $menu['sis_menu_id']] ?? [];
        $hijosMenu = array_filter($hijos, fn (array $hijo): bool => $hijo['sis_menu_tipo'] === 'M');
        $menuId = (int) $menu['sis_menu_id'];
        ?>
        <div class="menu-admin-nodo nivel-menu-<?= min($nivel, 3) ?> <?= $padre === 0 ? 'menu-admin-desplegado' : '' ?> <?= (int) $menu['sis_menu_estado'] === 1 ? '' : 'inactivo' ?>" data-menu-id="<?= $menuId ?>" data-padre="<?= $padre ?>">
            <button type="button" class="btn-menu-admin-acordeon <?= $padre === 0 ? '' : 'invisible' ?>" title="Desplegar u ocultar">
                <i class="bi <?= $padre === 0 ? 'bi-chevron-down' : 'bi-chevron-right' ?>"></i>
            </button>
            <i class="<?= htmlspecialchars($menu['sis_menu_icono'] ?: 'bi bi-circle') ?>"></i>
            <span><?= htmlspecialchars($menu['sis_menu_nombre']) ?></span>
            <button type="button" class="btn-cargar-menu-admin" title="Cargar acciones" <?= $padre === 0 && (int) $menu['sis_menu_estado'] !== 1 ? 'disabled' : '' ?>>
                <i class="bi bi-arrow-right-square"></i>
            </button>
            <?php if ($permisos['editar']): ?>
                <button type="button" class="btn-menu-admin-editar btn-editar-menu" title="Editar menu" data-bs-toggle="modal" data-bs-target="#modalMenu" data-modo="editar" data-id="<?= $menuId ?>" data-nombre="<?= htmlspecialchars($menu['sis_menu_nombre']) ?>" data-padre="<?= (int) ($menu['sis_menu_padre'] ?? 0) ?>" data-tipo="<?= htmlspecialchars($menu['sis_menu_tipo']) ?>" data-url="<?= htmlspecialchars($menu['sis_menu_url']) ?>" data-icono="<?= htmlspecialchars($menu['sis_menu_icono'] ?? '') ?>" data-orden="<?= (int) $menu['sis_menu_orden'] ?>" data-estado="<?= (int) $menu['sis_menu_estado'] ?>">
                    <i class="bi bi-pencil-square"></i>
                </button>
            <?php endif; ?>
        </div>
        <?php if ($hijosMenu): ?>
            <?php $renderizarArbolMenus((int) $menu['sis_menu_id'], $nivel + 1); ?>
        <?php endif; ?>
        <?php
    }
};
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
                        <strong>Menus y botones</strong>
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
                            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalMenu" data-modo="crear">
                                <i class="bi bi-plus-square"></i>
                                Nuevo menu
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="menus-admin-layout">
                        <div class="menus-admin-arbol">
                            <?php $renderizarArbolMenus(0, 1); ?>
                        </div>
                        <div class="menus-admin-acciones" id="menusAdminAcciones">
                            <?php foreach ($menusTipoMenu as $menu): ?>
                                <?php $menuId = (int) $menu['sis_menu_id']; ?>
                                <section class="acciones-menu-admin-bloque d-none" data-menu-id="<?= $menuId ?>">
                                    <div class="acciones-menu-admin-cabecera">
                                        <div>
                                            <strong><?= htmlspecialchars($menu['sis_menu_nombre']) ?></strong>
                                            <span><?= htmlspecialchars($menu['sis_menu_url']) ?></span>
                                        </div>
                                        <?php if ($permisos['crear']): ?>
                                            <button type="button" class="btn btn-secundario btn-sm btn-nueva-accion-menu" data-bs-toggle="modal" data-bs-target="#modalMenu" data-modo="crear" data-tipo="B" data-padre="<?= $menuId ?>" <?= (int) $menu['sis_menu_estado'] === 1 ? '' : 'disabled title="Menu principal inactivo"' ?>>
                                                <i class="bi bi-plus-lg"></i>
                                                Accion
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="acciones-menu-admin-lista">
                                        <?php foreach ($accionesPorMenu[$menuId] ?? [] as $accion): ?>
                                            <article class="accion-menu-admin <?= (int) $accion['sis_menu_estado'] === 1 ? '' : 'inactivo' ?>">
                                                <div>
                                                    <i class="<?= htmlspecialchars($accion['sis_menu_icono'] ?: 'bi bi-dot') ?>"></i>
                                                    <strong><?= htmlspecialchars($accion['sis_menu_nombre']) ?></strong>
                                                    <span><?= htmlspecialchars($accion['sis_menu_url']) ?></span>
                                                </div>
                                                <div class="acciones-tabla">
                                                    <?php if ($permisos['editar']): ?>
                                                        <button type="button" class="btn btn-accion btn-editar-menu" title="Editar accion" data-bs-toggle="modal" data-bs-target="#modalMenu" data-modo="editar" data-id="<?= (int) $accion['sis_menu_id'] ?>" data-nombre="<?= htmlspecialchars($accion['sis_menu_nombre']) ?>" data-padre="<?= (int) ($accion['sis_menu_padre'] ?? 0) ?>" data-tipo="<?= htmlspecialchars($accion['sis_menu_tipo']) ?>" data-url="<?= htmlspecialchars($accion['sis_menu_url']) ?>" data-icono="<?= htmlspecialchars($accion['sis_menu_icono'] ?? '') ?>" data-orden="<?= (int) $accion['sis_menu_orden'] ?>" data-estado="<?= (int) $accion['sis_menu_estado'] ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ((int) $accion['sis_menu_estado'] === 1 && $permisos['inactivar']): ?>
                                                        <form action="<?= $appUrl ?>/sistema/menus/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_MENU">
                                                            <input type="hidden" name="menu_id" value="<?= (int) $accion['sis_menu_id'] ?>">
                                                            <button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar accion"><i class="bi bi-toggle-off"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ((int) $accion['sis_menu_estado'] === 0 && $permisos['activar']): ?>
                                                        <form action="<?= $appUrl ?>/sistema/menus/activar" method="post" class="d-inline">
                                                            <input type="hidden" name="menu_id" value="<?= (int) $accion['sis_menu_id'] ?>">
                                                            <button type="submit" class="btn btn-accion btn-activar" title="Activar accion"><i class="bi bi-toggle-on"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                        <?php if (empty($accionesPorMenu[$menuId])): ?>
                                            <div class="acciones-menu-admin-vacio">Sin acciones registradas.</div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                            <div class="acciones-menu-admin-vacio" id="menusAdminVacio">
                                Seleccione el icono de flecha de un menu para cargar sus acciones.
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade modal-intesis" id="modalMenu" tabindex="-1" aria-labelledby="modalMenuTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioMenu" method="post" action="<?= $appUrl ?>/sistema/menus/crear">
            <input type="hidden" name="menu_id" id="menu_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Sistema</p>
                    <h2 class="modal-title" id="modalMenuTitulo">Nuevo menu</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label" for="menu_nombre">Nombre</label>
                        <input type="text" class="form-control form-control-sm" id="menu_nombre" name="nombre" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="menu_tipo">Tipo</label>
                        <select class="form-control form-control-sm" id="menu_tipo" name="tipo" required>
                            <option value="M">Menu</option>
                            <option value="B">Boton / accion</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="menu_orden">Orden</label>
                        <input type="number" class="form-control form-control-sm" id="menu_orden" name="orden" min="1" value="1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="menu_padre">Padre</label>
                        <select class="form-control form-control-sm" id="menu_padre" name="padre">
                            <option value="">Sin padre</option>
                            <?php foreach ($menusPadre as $padre): ?>
                                <option value="<?= (int) $padre['sis_menu_id'] ?>"><?= htmlspecialchars($padre['sis_menu_nombre'] . ' - ' . $padre['sis_menu_url']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="menu_url">URL</label>
                        <input type="text" class="form-control form-control-sm" id="menu_url" name="url" placeholder="/sistema/ruta" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="menu_icono">Icono</label>
                        <input type="text" class="form-control form-control-sm" id="menu_icono" name="icono" value="bi bi-circle" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="vista-icono-menu">
                            <i id="menu_icono_vista" class="bi bi-circle"></i>
                            <span>Vista previa</span>
                        </div>
                    </div>
                    <div class="col-12" id="contenedorCrearVer">
                        <div class="form-check form-switch switch-intesis">
                            <input class="form-check-input" type="checkbox" role="switch" id="menu_crear_ver" name="crear_ver" checked>
                            <label class="form-check-label" for="menu_crear_ver">Crear accion Ver automaticamente</label>
                        </div>
                    </div>
                    <div class="col-md-4" id="contenedorMenuEstado">
                        <label class="form-label" for="menu_estado">Visualizacion</label>
                        <select class="form-control form-control-sm" id="menu_estado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button>
                <button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button>
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
