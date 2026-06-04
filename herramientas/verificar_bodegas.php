<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/intesis/inventario/bodegas';
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
    'perfil_codigo' => 'SUPERUSUARIO',
    'empresa' => 'INTITECH',
];

ob_start();
require __DIR__ . '/../index.php';
$contenido = ob_get_clean();

$ok = str_contains($contenido, 'tablaBodegas')
    && str_contains($contenido, 'modalBodega')
    && str_contains($contenido, 'Nueva bodega');

echo $ok ? "BODEGAS_OK\n" : "BODEGAS_ERROR\n";
