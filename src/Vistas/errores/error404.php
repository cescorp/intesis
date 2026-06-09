<?php
/*
 * *****************************************************************************
 * * VISTA DE ERROR 404 - PAGINA NO ENCONTRADA.
 * * SE MUESTRA CON LAYOUT COMPLETO SI HAY SESION ACTIVA, SIN MENU SI NO.
 * *****************************************************************************
 */
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl    = rtrim($configuracion->obtener('APP_URL', ''), '/');
$hayUsuario = !empty($usuario);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appNombre) ?> | 404 - Pagina no encontrada</title>
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/iconos/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/publico/css/estilos.css">
</head>
<body class="<?= $hayUsuario ? 'layout-fixed sidebar-expand-lg bg-body-tertiary' : 'bg-body-tertiary' ?>">

<?php if ($hayUsuario): ?>
<!-- ============================================================== -->
<!-- ENCABEZADO CON ENLACE AL DASHBOARD                              -->
<!-- ============================================================== -->
<nav class="app-header navbar navbar-expand bg-body-tertiary shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand fw-bold text-primary">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i>
            <?= htmlspecialchars($appNombre) ?>
        </span>
        <span class="text-secondary ms-3 small">
            <?= htmlspecialchars((string) ($usuario['nombre'] ?? '')) ?>
        </span>
        <div class="ms-auto">
            <a href="<?= $appUrl ?>/dashboard" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-house-door me-1"></i>Dashboard
            </a>
        </div>
    </div>
</nav>
<div class="app-wrapper">
<main class="app-main">
<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h3 class="mb-0">Error 404</h3>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item">
                        <a href="<?= $appUrl ?>/dashboard">
                            <?= htmlspecialchars($appNombre) ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active">404</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<div class="app-content">
<div class="container-fluid">
<?php endif; ?>

<!-- ============================================================== -->
<!-- TARJETA CENTRAL DE ERROR                                         -->
<!-- ============================================================== -->
<div class="<?= $hayUsuario ? '' : 'd-flex align-items-center justify-content-center' ?>"
     <?= $hayUsuario ? '' : 'style="min-height:100vh"' ?>>
    <div class="card shadow-sm border-0 <?= $hayUsuario ? 'mt-4' : '' ?>" style="max-width:480px;<?= $hayUsuario ? '' : 'width:100%;margin:auto' ?>">
        <div class="card-body text-center p-5">
            <div class="mb-3">
                <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:4rem"></i>
            </div>
            <h1 class="display-1 fw-bold text-secondary">404</h1>
            <h4 class="mb-2">Pagina no encontrada</h4>
            <p class="text-muted mb-4">
                La ruta que intentas acceder no existe o fue movida.<br>
                Verifica la URL o regresa al inicio.
            </p>
            <?php if ($hayUsuario): ?>
                <a href="<?= $appUrl ?>/dashboard" class="btn btn-primary">
                    <i class="bi bi-house-door me-1"></i>Ir al Dashboard
                </a>
            <?php else: ?>
                <a href="<?= $appUrl ?>/login" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Ir al Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($hayUsuario): ?>
</div><!-- .container-fluid -->
</div><!-- .app-content -->
</main>
</div><!-- .app-wrapper -->
<?php endif; ?>

<script src="<?= $appUrl ?>/recursos/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
