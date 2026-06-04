<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/../src/Modelos/PerfilModelo.php';

use Intesis\Modelos\PerfilModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$conexion = new ConexionBaseDatos(new Configuracion(dirname(__DIR__)));
$pdo = $conexion->obtener();
$modelo = new PerfilModelo($conexion);
$perfilId = null;

try {
    $modelo->crear([
        'empresa_id' => 1,
        'codigo' => 'TEMP_CRUD',
        'nombre' => 'TEMP PERFIL CRUD',
    ], 1);

    $sentencia = $pdo->prepare("SELECT sis_perfil_id FROM sis_perfil WHERE sis_empresa_id = 1 AND sis_perfil_codigo = 'TEMP_CRUD'");
    $sentencia->execute();
    $perfilId = (int) $sentencia->fetchColumn();

    $modelo->guardarPermisos($perfilId, [1, 2, 3], 1);
    $permisos = $modelo->listarPermisosPerfil($perfilId);

    $modelo->actualizar($perfilId, [
        'empresa_id' => 1,
        'codigo' => 'TEMP_CRUD_EDIT',
        'nombre' => 'TEMP PERFIL CRUD EDITADO',
    ], 1);

    $modelo->cambiarEstado($perfilId, 0, 1);
    $perfil = $modelo->buscar($perfilId);

    $ok = count($permisos) === 3
        && $perfil
        && $perfil['sis_perfil_codigo'] === 'TEMP_CRUD_EDIT'
        && (int) $perfil['sis_perfil_estado'] === 0;

    echo $ok ? "PERFIL_CRUD_OK\n" : "PERFIL_CRUD_ERROR\n";
} catch (Throwable $excepcion) {
    echo "PERFIL_CRUD_ERROR\n";
} finally {
    if ($perfilId) {
        $sentencia = $pdo->prepare("DELETE FROM sis_perfil_permisos WHERE sis_perfil_id = :perfil_id");
        $sentencia->execute(['perfil_id' => $perfilId]);
        $sentencia = $pdo->prepare("DELETE FROM sis_perfil WHERE sis_perfil_id = :perfil_id");
        $sentencia->execute(['perfil_id' => $perfilId]);
    }
}
