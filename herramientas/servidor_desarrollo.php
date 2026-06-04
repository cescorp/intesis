<?php

declare(strict_types=1);

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$raizServidor = dirname(__DIR__, 2);
$archivo = $raizServidor . $ruta;

if (is_file($archivo)) {
    return false;
}

require __DIR__ . '/../index.php';
