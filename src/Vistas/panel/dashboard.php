<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
$iconos = [
    'VENTAS' => 'bi-receipt-cutoff',
    'COMPRAS' => 'bi-bag-check',
    'INVENTARIO' => 'bi-boxes',
    'CONTABILIDAD' => 'bi-calculator',
    'REPORTES' => 'bi-graph-up-arrow',
    'SISTEMA' => 'bi-sliders',
];
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
                        <span>Operacion central</span>
                        <i class="bi bi-chevron-right"></i>
                        <strong>Dashboard ejecutivo</strong>
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
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="indicador-dashboard indicador-cobre">
                            <span><i class="bi bi-building-check"></i></span>
                            <div>
                                <small>Empresa</small>
                                <strong><?= htmlspecialchars($usuario['empresa']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="indicador-dashboard indicador-verde">
                            <span><i class="bi bi-person-badge"></i></span>
                            <div>
                                <small>Perfil</small>
                                <strong><?= htmlspecialchars($usuario['perfil']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="indicador-dashboard indicador-tinta">
                            <span><i class="bi bi-diagram-3"></i></span>
                            <div>
                                <small>Modulos</small>
                                <strong><?= count($modulos) ?> registrados</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modulos-grid">
                    <?php foreach ($modulos as $modulo): ?>
                        <?php
                        $activo = !empty($modulo['sis_licencia_estado']);
                        $dias = $activo && !empty($modulo['sis_licencia_fecha_fin'])
                            ? max(0, (int) floor((strtotime((string) $modulo['sis_licencia_fecha_fin']) - time()) / 86400))
                            : 0;
                        ?>
                        <article class="modulo-card <?= $activo ? 'activo' : 'inactivo' ?>">
                            <div class="modulo-icono">
                                <i class="bi <?= htmlspecialchars($iconos[$modulo['sis_modulo_nombre']] ?? 'bi-folder2-open') ?>"></i>
                            </div>
                            <div class="modulo-contenido">
                                <div class="modulo-cabecera">
                                    <h2><?= htmlspecialchars(ucfirst(strtolower($modulo['sis_modulo_nombre']))) ?></h2>
                                    <span><?= $activo ? 'Activo' : 'Sin licencia' ?></span>
                                </div>
                                <p><?= htmlspecialchars($modulo['sis_modulo_descripcion']) ?></p>
                                <div class="modulo-pie">
                                    <small><?= htmlspecialchars($modulo['sis_licencia_tipo'] ?? 'NO ASIGNADO') ?></small>
                                    <strong><?= $activo ? $dias . ' dias' : '-' ?></strong>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php if (!empty($mensaje)): ?>
    <script>
        window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;
    </script>
<?php endif; ?>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
