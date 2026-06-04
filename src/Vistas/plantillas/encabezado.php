<?php
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appNombre . ' | ' . ($titulo ?? 'Sistema')) ?></title>
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/iconos/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/datatables/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/recursos/sweetalert/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/publico/css/estilos.css">
</head>
<body class="<?= htmlspecialchars($claseCuerpo ?? '') ?>" data-base-url="<?= htmlspecialchars(parse_url($appUrl, PHP_URL_PATH) ?: '') ?>">
