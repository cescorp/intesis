<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/../src/Modelos/ModuloModelo.php';

use Intesis\Modelos\ModuloModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$modelo = new ModuloModelo($conexion);
$modulos = $modelo->listarPorEmpresa(1);

echo count($modulos) === 6 ? "DASHBOARD_DATOS_OK\n" : "DASHBOARD_DATOS_ERROR\n";
