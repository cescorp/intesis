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
                    <button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button>
                </li>
                <li class="nav-item">
                    <span class="nav-link breadcrumb-navbar">
                        <span><?= htmlspecialchars($appNombre) ?></span>
                        <i class="bi bi-chevron-right"></i>
                        <span>Sistema</span>
                        <i class="bi bi-chevron-right"></i>
                        <span>Configuración</span>
                        <i class="bi bi-chevron-right"></i>
                        <strong>Licencia</strong>
                    </span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block">
                    <span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span>
                </li>
                <li class="nav-item">
                    <form action="<?= $appUrl ?>/salir" method="post">
                        <button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button>
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

                    <!-- Pestañas principales -->
                    <ul class="nav nav-tabs mb-3" id="tabsLicencia" role="tablist">
                        <?php if ($permisos['activar']): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-activar-btn" data-bs-toggle="tab" data-bs-target="#tabActivar" type="button" role="tab">
                                <i class="bi bi-upload me-1"></i>Activar
                            </button>
                        </li>
                        <?php endif; ?>
                        <?php if ($esSuperusuario && $permisos['generar']): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= !$permisos['activar'] ? 'active' : '' ?>" id="tab-generar-btn" data-bs-toggle="tab" data-bs-target="#tabGenerar" type="button" role="tab">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Generar licencia
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="tab-content" id="tabsLicenciaContent">

                        <!-- TAB: Activar -->
                        <?php if ($permisos['activar']): ?>
                        <div class="tab-pane fade show active" id="tabActivar" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-5">
                                    <div class="card card-intesis">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3">
                                                <i class="bi bi-patch-check me-2 text-success"></i>Subir archivo de licencia
                                            </h5>
                                            <form action="<?= $appUrl ?>/sistema/configuracion/licencia/activar" method="post" enctype="multipart/form-data">
                                                <div class="mb-3">
                                                    <label class="form-label" for="licencia_json">Archivo de licencia (.json)</label>
                                                    <input type="file" class="form-control" id="licencia_json" name="licencia_json" accept=".json" required>
                                                    <div class="form-text">Seleccione el archivo .json proporcionado por el proveedor.</div>
                                                </div>
                                                <button type="submit" class="btn btn-intesis">
                                                    <i class="bi bi-upload me-1"></i>Activar licencia
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Licencias activas -->
                                <div class="col-lg-7">
                                    <div class="card card-intesis">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3">
                                                <i class="bi bi-list-check me-2"></i>Módulos con licencia activa
                                            </h5>
                                            <?php if (empty($licencias)): ?>
                                                <p class="text-muted mb-0">No hay licencias activas.</p>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm tabla-intesis align-middle">
                                                        <thead>
                                                            <tr>
                                                                <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                                                                <th>Módulo</th>
                                                                <th>Tipo</th>
                                                                <th>Desde</th>
                                                                <th>Hasta</th>
                                                                <th>Estado</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($licencias as $lic): ?>
                                                                <?php
                                                                $vencida = $lic['sis_licencia_fecha_fin'] < date('Y-m-d');
                                                                $clase   = $vencida ? 'estado-inactivo' : 'estado-activo';
                                                                $texto   = $vencida ? 'VENCIDA' : 'ACTIVA';
                                                                ?>
                                                                <tr>
                                                                    <?php if ($esSuperusuario): ?>
                                                                        <td><?= htmlspecialchars($lic['sis_empresa_razon_social']) ?></td>
                                                                    <?php endif; ?>
                                                                    <td><?= htmlspecialchars($lic['sis_modulo_nombre']) ?></td>
                                                                    <td><?= htmlspecialchars($lic['sis_licencia_tipo']) ?></td>
                                                                    <td><?= htmlspecialchars($lic['sis_licencia_fecha_inicio']) ?></td>
                                                                    <td><?= htmlspecialchars($lic['sis_licencia_fecha_fin']) ?></td>
                                                                    <td><span class="badge estado-badge <?= $clase ?>"><?= $texto ?></span></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- TAB: Generar licencia (solo superusuario) -->
                        <?php if ($esSuperusuario && $permisos['generar']): ?>
                        <div class="tab-pane fade <?= !$permisos['activar'] ? 'show active' : '' ?>" id="tabGenerar" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="card card-intesis">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3">
                                                <i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Generar archivo de licencia
                                            </h5>
                                            <form action="<?= $appUrl ?>/sistema/configuracion/licencia/generar" method="post">
                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label" for="gen_empresa_id">Empresa</label>
                                                        <select class="form-select" id="gen_empresa_id" name="empresa_id">
                                                            <option value="">Seleccione empresa</option>
                                                            <?php foreach ($empresas as $emp): ?>
                                                                <option value="<?= (int) $emp['sis_empresa_id'] ?>">
                                                                    <?= htmlspecialchars($emp['sis_empresa_razon_social']) ?>
                                                                    (<?= htmlspecialchars($emp['sis_empresa_ruc']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Módulos</label>
                                                        <div class="row g-2">
                                                            <?php foreach ($modulos as $mod): ?>
                                                                <div class="col-6">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="modulos[]" id="mod_<?= (int) $mod['sis_modulo_id'] ?>" value="<?= (int) $mod['sis_modulo_id'] ?>">
                                                                        <label class="form-check-label" for="mod_<?= (int) $mod['sis_modulo_id'] ?>">
                                                                            <?= htmlspecialchars($mod['sis_modulo_nombre']) ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label" for="gen_tipo">Tipo de licencia</label>
                                                        <select class="form-select" id="gen_tipo" name="tipo">
                                                            <option value="TRIAL">TRIAL</option>
                                                            <option value="FULL">FULL</option>
                                                            <option value="MODULAR">MODULAR</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <!-- espacio -->
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label" for="gen_fecha_inicio">Fecha inicio</label>
                                                        <input type="date" class="form-control" id="gen_fecha_inicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label" for="gen_fecha_fin">Fecha fin</label>
                                                        <input type="date" class="form-control" id="gen_fecha_fin" name="fecha_fin" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                                                    </div>
                                                    <div class="col-12">
                                                        <button type="submit" class="btn btn-intesis">
                                                            <i class="bi bi-download me-1"></i>Descargar licencia .json
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<?php if (!empty($mensaje)): ?>
    <script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
