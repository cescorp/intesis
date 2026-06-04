<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/intesis/';
$_SERVER['SCRIPT_NAME'] = '/intesis/index.php';

ob_start();
require __DIR__ . '/../index.php';
$contenido = ob_get_clean();

echo str_contains($contenido, 'Acceso al sistema') ? "LOGIN_OK\n" : "LOGIN_ERROR\n";
