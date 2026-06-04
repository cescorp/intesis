<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/RegistroErrores.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/../src/Modelos/UsuarioModelo.php';
require_once __DIR__ . '/../src/Servicios/AutenticacionServicio.php';

use Intesis\Modelos\UsuarioModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Servicios\AutenticacionServicio;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$registro = new RegistroErrores($configuracion);
$modelo = new UsuarioModelo($conexion);
$servicio = new AutenticacionServicio($modelo, $registro);
$usuario = $servicio->autenticar('cescorp@hotmail.es', '50245058');

echo $usuario ? "AUTH_OK\n" : "AUTH_ERROR\n";
