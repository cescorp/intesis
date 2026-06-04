<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/intesis/sistema/empresas';
$_SERVER['SCRIPT_NAME'] = '/intesis/index.php';

session_save_path(__DIR__ . '/../almacenamiento/sesiones');
session_name('INTESIS_SESION');
session_start();

$_SESSION['usuario'] = [
    'id' => 1,
    'empresa_id' => 1,
    'perfil_id' => 1,
    'nombre' => 'ADMINISTRADOR SISTEMA',
    'correo' => 'cescorp@hotmail.es',
    'perfil' => 'SUPERUSUARIO',
    'empresa' => 'INTITECH',
];

ob_start();
require __DIR__ . '/../index.php';
$contenido = ob_get_clean();

$ok = str_contains($contenido, 'Empresas')
    && str_contains($contenido, 'data-lte-toggle="treeview"')
    && str_contains($contenido, 'menu-open');

echo $ok ? "MENU_EMPRESAS_OK\n" : "MENU_EMPRESAS_ERROR\n";
