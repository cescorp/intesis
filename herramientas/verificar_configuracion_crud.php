<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/../src/Nucleo/RegistroErrores.php';
require_once __DIR__ . '/../src/Modelos/EstadoModelo.php';
require_once __DIR__ . '/../src/Modelos/MensajeSistemaModelo.php';

use Intesis\Modelos\EstadoModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$estadoModelo = new EstadoModelo($conexion);
$mensajeModelo = new MensajeSistemaModelo($conexion, new RegistroErrores($configuracion));
$pdo = $conexion->obtener();
$estadoId = null;
$mensajeId = null;

try {
    $estadoModelo->crear([
        'modulo' => 'TEMP',
        'entidad' => 'TEMP_ENTIDAD',
        'codigo' => 'TEMP_ESTADO',
        'nombre' => 'TEMP ESTADO',
        'descripcion' => 'VERIFICACION',
        'orden' => 99,
    ], 1);
    $sentencia = $pdo->prepare("SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='TEMP' AND sis_estado_entidad='TEMP_ENTIDAD' AND sis_estado_codigo='TEMP_ESTADO'");
    $sentencia->execute();
    $estadoId = (int) $sentencia->fetchColumn();
    $estadoModelo->actualizar($estadoId, [
        'modulo' => 'TEMP',
        'entidad' => 'TEMP_ENTIDAD',
        'codigo' => 'TEMP_ESTADO',
        'nombre' => 'TEMP ESTADO EDITADO',
        'descripcion' => 'VERIFICACION EDITADA',
        'orden' => 98,
    ], true, 1);
    $estadoModelo->cambiarActivo($estadoId, false, 1);
    $estado = $estadoModelo->buscar($estadoId);

    $mensajeModelo->crear([
        'codigo' => 'TEMP_MENSAJE_CRUD',
        'tipo' => 'ERROR',
        'titulo' => 'TEMP',
        'mensaje' => 'TEMP MENSAJE',
        'icono' => 'error',
        'modulo' => 'TEMP',
        'entidad' => 'GENERAL',
    ], 1);
    $sentencia = $pdo->prepare("SELECT sis_mensaje_errores_id FROM sis_mensaje_errores WHERE sis_mensaje_errores_codigo='TEMP_MENSAJE_CRUD'");
    $sentencia->execute();
    $mensajeId = (int) $sentencia->fetchColumn();
    $mensajeModelo->actualizar($mensajeId, [
        'codigo' => 'TEMP_MENSAJE_CRUD',
        'tipo' => 'INFO',
        'titulo' => 'TEMP EDITADO',
        'mensaje' => 'TEMP MENSAJE EDITADO',
        'icono' => 'info',
        'modulo' => 'TEMP',
        'entidad' => 'GENERAL',
    ], 1);
    $mensajeModelo->cambiarActivo($mensajeId, false, 1);
    $mensaje = $mensajeModelo->buscarPorId($mensajeId);

    $ok = $estado && $mensaje
        && $estado['sis_estado_nombre'] === 'TEMP ESTADO EDITADO'
        && !$estado['sis_estado_activo']
        && $mensaje['sis_mensaje_errores_tipo'] === 'INFO'
        && !$mensaje['sis_mensaje_errores_activo'];

    echo $ok ? "CONFIGURACION_CRUD_OK\n" : "CONFIGURACION_CRUD_ERROR\n";
} catch (Throwable $excepcion) {
    echo "CONFIGURACION_CRUD_ERROR\n";
} finally {
    if ($estadoId) {
        $sentencia = $pdo->prepare("DELETE FROM sis_estado WHERE sis_estado_id = :estado_id");
        $sentencia->execute(['estado_id' => $estadoId]);
    }
    if ($mensajeId) {
        $sentencia = $pdo->prepare("DELETE FROM sis_mensaje_errores WHERE sis_mensaje_errores_id = :mensaje_id");
        $sentencia->execute(['mensaje_id' => $mensajeId]);
    }
}
