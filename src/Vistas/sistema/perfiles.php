<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
$permisosPorPerfil = [];
foreach ($perfiles as $perfil) {
    $permisosPorPerfil[(int) $perfil['sis_perfil_id']] = [];
}
$menusSolo = array_values(array_filter($menusPermisos, fn (array $menu): bool => $menu['sis_menu_tipo'] === 'M'));
$accionesSolo = array_values(array_filter($menusPermisos, fn (array $menu): bool => $menu['sis_menu_tipo'] === 'B'));
$menusPorId = [];
$menusPorPadre = [];
foreach ($menusSolo as $menu) {
    $menusPorId[(int) $menu['sis_menu_id']] = $menu;
    $menusPorPadre[(int) ($menu['sis_menu_padre'] ?? 0)][] = $menu;
}
$accionesPorMenu = [];
foreach ($accionesSolo as $accion) {
    $accionesPorMenu[(int) $accion['sis_menu_padre']][] = $accion;
}
$accionesDescendientes = [];
foreach ($menusSolo as $menu) {
    $menuId = (int) $menu['sis_menu_id'];
    $accionesDescendientes[$menuId] = !empty($accionesPorMenu[$menuId]);
    foreach ($menusSolo as $hijo) {
        if ((int) ($hijo['sis_menu_padre'] ?? 0) === $menuId && !empty($accionesPorMenu[(int) $hijo['sis_menu_id']])) {
            $accionesDescendientes[$menuId] = true;
        }
    }
}

/**
 * ***************************************************************************
 * * RENDERIZA EL ARBOL DE MENUS PARA ASIGNAR PERMISOS POR PERFIL.
 * ***************************************************************************
 */
$renderizarArbolPermisos = function (int $padreId, int $nivel) use (&$renderizarArbolPermisos, $menusPorPadre, $accionesDescendientes): void {
    foreach ($menusPorPadre[$padreId] ?? [] as $menu) {
        $menuId = (int) $menu['sis_menu_id'];
        $esPrincipal = $padreId === 0;
        ?>
        <div class="permiso-nodo permiso-menu nivel-menu-<?= min($nivel, 3) ?> <?= $esPrincipal ? 'menu-desplegado' : '' ?>" data-menu-id="<?= $menuId ?>" data-padre="<?= $padreId ?>">
            <button type="button" class="btn-menu-acordeon <?= $esPrincipal ? '' : 'invisible' ?>" title="Desplegar u ocultar">
                <i class="bi <?= $esPrincipal ? 'bi-chevron-down' : 'bi-chevron-right' ?>"></i>
            </button>
            <label class="permiso-menu-check">
                <input type="checkbox" name="menu_ids[]" value="<?= $menuId ?>">
                <i class="<?= htmlspecialchars($menu['sis_menu_icono'] ?: 'bi bi-dot') ?>"></i>
                <span><?= htmlspecialchars($menu['sis_menu_nombre']) ?></span>
            </label>
            <?php if (!empty($accionesDescendientes[$menuId])): ?>
                <button type="button" class="btn-cargar-acciones" title="Cargar acciones">
                    <i class="bi bi-arrow-right-square"></i>
                </button>
            <?php endif; ?>
        </div>
        <?php $renderizarArbolPermisos($menuId, $nivel + 1); ?>
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
                        <strong>Perfiles</strong>
                    </span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block">
                    <span class="usuario-navbar"><?= htmlspecialchars((($usuario['bodega'] ?? null) ? $usuario['bodega'] . ' - ' : '') . $usuario['nombre']) ?></span>
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
                            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalPerfil" data-modo="crear">
                                <i class="bi bi-person-plus"></i>
                                Nuevo perfil
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($esSuperusuario): ?>
                        <div class="panel-crud-filtros mb-3">
                            <select id="filtroEmpresaPerfil" class="form-select form-select-sm w-auto">
                                <option value="">Todas las empresas</option>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?>">
                                        <?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="tablaPerfiles" class="table table-hover tabla-intesis align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Codigo</th>
                                    <th>Perfil</th>
                                    <th>Usuarios</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perfiles as $perfil): ?>
                                    <?php $protegido = $perfil['sis_perfil_codigo'] === 'SUPERUSUARIO'; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($perfil['sis_empresa_nombre_comercial']) ?></td>
                                        <td><?= htmlspecialchars($perfil['sis_perfil_codigo']) ?></td>
                                        <td><?= htmlspecialchars($perfil['sis_perfil_nombre']) ?></td>
                                        <td><?= (int) $perfil['total_usuarios'] ?></td>
                                        <td>
                                            <span class="badge estado-badge <?= (int) $perfil['sis_perfil_estado'] === 1 ? 'estado-activo' : 'estado-inactivo' ?>">
                                                <?= (int) $perfil['sis_perfil_estado'] === 1 ? 'ACTIVO' : 'INACTIVO' ?>
                                            </span>
                                        </td>
                                        <td class="text-end acciones-tabla">
                                            <?php if ($permisos['permisos']): ?>
                                                <button type="button" class="btn btn-accion btn-permisos-perfil" title="Permisos" data-bs-toggle="modal" data-bs-target="#modalPermisosPerfil" data-id="<?= (int) $perfil['sis_perfil_id'] ?>" data-nombre="<?= htmlspecialchars($perfil['sis_perfil_nombre']) ?>">
                                                    <i class="bi bi-shield-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($permisos['editar'] && !$protegido): ?>
                                                <button type="button" class="btn btn-accion btn-editar-perfil" title="Editar perfil" data-bs-toggle="modal" data-bs-target="#modalPerfil" data-modo="editar" data-id="<?= (int) $perfil['sis_perfil_id'] ?>" data-empresa="<?= (int) $perfil['sis_empresa_id'] ?>" data-nombre="<?= htmlspecialchars($perfil['sis_perfil_nombre']) ?>" data-venta-todas-bodegas="<?= $perfil['sis_perfil_venta_todas_bodegas'] ? '1' : '0' ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ((int) $perfil['sis_perfil_estado'] === 1 && $permisos['inactivar'] && !$protegido): ?>
                                                <form action="<?= $appUrl ?>/sistema/perfiles/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_PERFIL">
                                                    <input type="hidden" name="perfil_id" value="<?= (int) $perfil['sis_perfil_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar perfil"><i class="bi bi-toggle-off"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ((int) $perfil['sis_perfil_estado'] === 0 && $permisos['activar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/perfiles/activar" method="post" class="d-inline">
                                                    <input type="hidden" name="perfil_id" value="<?= (int) $perfil['sis_perfil_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-activar" title="Activar perfil"><i class="bi bi-toggle-on"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($permisos['eliminar'] && !$protegido && (int) $perfil['total_usuarios'] === 0): ?>
                                                <form action="<?= $appUrl ?>/sistema/perfiles/eliminar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_ELIMINAR_PERFIL">
                                                    <input type="hidden" name="perfil_id" value="<?= (int) $perfil['sis_perfil_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-eliminar" title="Eliminar perfil"><i class="bi bi-trash3"></i></button>
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

<div class="modal fade modal-intesis" id="modalPerfil" tabindex="-1" aria-labelledby="modalPerfilTitulo" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form class="modal-content" id="formularioPerfil" method="post" action="<?= $appUrl ?>/sistema/perfiles/crear">
            <input type="hidden" name="perfil_id" id="perfil_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Sistema</p>
                    <h2 class="modal-title" id="modalPerfilTitulo">Nuevo perfil</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="perfil_empresa_id">Empresa</label>
                        <select class="form-control form-control-sm" id="perfil_empresa_id" name="empresa_id" required>
                            <option value="">Seleccione empresa</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= (int) $empresa['sis_empresa_id'] ?>"><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="perfil_nombre">Nombre</label>
                        <input type="text" class="form-control form-control-sm" id="perfil_nombre" name="nombre" required>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch switch-intesis">
                            <input class="form-check-input" type="checkbox" role="switch" id="perfil_venta_todas_bodegas" name="venta_todas_bodegas">
                            <label class="form-check-label" for="perfil_venta_todas_bodegas">Vender stock de todas las bodegas</label>
                        </div>
                        <small class="text-muted">Si está desactivado, este perfil solo vende lo disponible en su bodega asignada (predeterminado).</small>
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

<div class="modal fade modal-intesis" id="modalPermisosPerfil" tabindex="-1" aria-labelledby="modalPermisosPerfilTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="formularioPermisosPerfil" method="post" action="<?= $appUrl ?>/sistema/perfiles/permisos">
            <input type="hidden" name="perfil_id" id="permisos_perfil_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Asignaciones</p>
                    <h2 class="modal-title" id="modalPermisosPerfilTitulo">Permisos</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="permisos-layout">
                    <div class="permisos-arbol">
                        <?php $renderizarArbolPermisos(0, 1); ?>
                    </div>
                    <div class="permisos-detalle" id="permisosDetallePerfil">
                        <?php foreach ($menusSolo as $menu): ?>
                            <?php
                            $menuId = (int) $menu['sis_menu_id'];
                            $accionesBloque = $accionesPorMenu[$menuId] ?? [];
                            if (!$accionesBloque) {
                                continue;
                            }
                            ?>
                            <section class="acciones-permiso-bloque" data-menu-id="<?= $menuId ?>" data-padre="<?= (int) ($menu['sis_menu_padre'] ?? 0) ?>">
                                <div class="acciones-permiso-cabecera">
                                    <h3><?= htmlspecialchars($menu['sis_menu_nombre']) ?></h3>
                                    <label>
                                        <input type="checkbox" class="marcar-todas-acciones" data-menu-id="<?= $menuId ?>">
                                        Todas
                                    </label>
                                </div>
                                <div class="acciones-permiso-grid">
                                    <?php foreach ($accionesBloque as $accion): ?>
                                        <label class="permiso-accion" data-menu-id="<?= (int) $accion['sis_menu_id'] ?>" data-padre="<?= $menuId ?>">
                                            <input type="checkbox" name="menu_ids[]" value="<?= (int) $accion['sis_menu_id'] ?>">
                                            <span><?= htmlspecialchars($accion['sis_menu_nombre']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                        <div class="permisos-vacio" id="permisosVacioPerfil">
                            <strong>Seleccione un menu</strong>
                            <span>Las acciones del menu seleccionado se cargaran aqui.</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button>
                <button type="submit" class="btn btn-intesis"><i class="bi bi-shield-check"></i> Guardar</button>
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
    window.INTESIS_PERMISOS_PERFIL = <?= json_encode($permisosPerfil ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if ($esSuperusuario): ?>
<script>
(function () {
    'use strict';

    const filtro = document.getElementById('filtroEmpresaPerfil');
    if (!filtro) return;

    filtro.addEventListener('change', function () {
        if (!window.jQuery || !jQuery.fn.DataTable) return;
        // Columna 0 = Empresa; búsqueda exacta con regex para evitar coincidencias parciales
        jQuery('#tablaPerfiles').DataTable().column(0).search(
            this.value ? '^' + this.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$' : '',
            true,
            false
        ).draw();
    });
}());
</script>
<?php endif; ?>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
