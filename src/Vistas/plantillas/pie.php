<?php
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
$versionSistemaJs = filemtime($configuracion->raiz() . '/publico/js/sistema.js') ?: time();
?>
    <script src="<?= $appUrl ?>/recursos/jquery/jquery.min.js"></script>
    <script src="<?= $appUrl ?>/recursos/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $appUrl ?>/recursos/adminlte/js/adminlte.min.js"></script>
    <script src="<?= $appUrl ?>/recursos/datatables/js/dataTables.min.js"></script>
    <script src="<?= $appUrl ?>/recursos/datatables/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= $appUrl ?>/recursos/sweetalert/sweetalert2.all.min.js"></script>
    <script src="<?= $appUrl ?>/publico/js/sistema.js?v=<?= $versionSistemaJs ?>"></script>
</body>
</html>
