<?php
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
$rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$baseUrl = rtrim(parse_url($appUrl, PHP_URL_PATH) ?: '', '/');
if ($baseUrl !== '' && str_starts_with($rutaActual, $baseUrl)) {
    $rutaActual = substr($rutaActual, strlen($baseUrl)) ?: '/';
}
$porPadre = [];
foreach (($menus ?? []) as $menu) {
    $padre = $menu['sis_menu_padre'] === null ? 0 : (int) $menu['sis_menu_padre'];
    $porPadre[$padre][] = $menu;
}

/**
 * ***************************************************************************
 * * RENDERIZA MENUS CON SANGRIA VISUAL PARA HASTA TRES NIVELES.
 * ***************************************************************************
 */
$renderizarMenus = function (int $padre, int $nivel) use (&$renderizarMenus, $porPadre, $appUrl, $rutaActual): void {
    foreach ($porPadre[$padre] ?? [] as $menu) {
        $hijos = $porPadre[(int) $menu['sis_menu_id']] ?? [];
        $activo = str_starts_with($rutaActual, (string) $menu['sis_menu_url']);
        $claseNivel = 'nivel-menu-' . min($nivel, 3);
        ?>
        <li class="nav-item <?= $hijos ? 'menu-item-padre' : '' ?> <?= ($hijos && $activo) ? 'menu-open' : '' ?>">
            <a href="<?= $hijos ? '#' : $appUrl . htmlspecialchars($menu['sis_menu_url']) ?>" class="nav-link <?= $activo ? 'active' : '' ?> <?= $claseNivel ?>">
                <i class="nav-icon <?= htmlspecialchars($menu['sis_menu_icono'] ?: 'bi bi-circle') ?>"></i>
                <p>
                    <?= htmlspecialchars($menu['sis_menu_nombre']) ?>
                    <?php if ($hijos): ?><i class="nav-arrow bi bi-chevron-right"></i><?php endif; ?>
                </p>
            </a>
            <?php if ($hijos): ?>
                <ul class="nav nav-treeview">
                    <?php $renderizarMenus((int) $menu['sis_menu_id'], $nivel + 1); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
};
?>
<nav class="mt-3">
    <ul class="nav sidebar-menu flex-column nav-indent" data-lte-toggle="treeview" role="menu">
        <li class="nav-item">
            <a href="<?= $appUrl ?>/dashboard" class="nav-link <?= str_ends_with($rutaActual, '/dashboard') ? 'active' : '' ?>">
                <i class="nav-icon bi bi-speedometer2"></i>
                <p>Dashboard</p>
            </a>
        </li>
        <?php $renderizarMenus(0, 1); ?>
    </ul>
</nav>
