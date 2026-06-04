<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/RegistroErrores.php';

use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;

$registro = new RegistroErrores(new Configuracion(dirname(__DIR__)));
$registro->escribir('VERIFICACION CONTROLADA DEL REGISTRO DE ERRORES');

echo "LOG_ERRORES_OK\n";
