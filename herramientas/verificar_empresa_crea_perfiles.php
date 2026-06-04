<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/../src/Modelos/EmpresaModelo.php';

use Intesis\Modelos\EmpresaModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$conexion = new ConexionBaseDatos(new Configuracion(dirname(__DIR__)));
$pdo = $conexion->obtener();
$modelo = new EmpresaModelo($conexion);
$ruc = '1799999999001';
$empresaId = null;

try {
    $modelo->crear([
        'ruc' => $ruc,
        'razon_social' => 'VERIFICACION PERFILES',
        'nombre_comercial' => 'VERIFICACION PERFILES',
        'direccion' => 'DIRECCION PRUEBA',
        'telefono' => '',
        'email' => '',
        'obligado_contabilidad' => false,
        'contribuyente_especial' => false,
    ], 1);

    $sentencia = $pdo->prepare("SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = :ruc LIMIT 1");
    $sentencia->execute(['ruc' => $ruc]);
    $empresaId = (int) $sentencia->fetchColumn();

    $sentencia = $pdo->prepare("SELECT count(*) FROM sis_perfil WHERE sis_empresa_id = :empresa_id AND sis_perfil_estado = 1");
    $sentencia->execute(['empresa_id' => $empresaId]);
    $perfiles = (int) $sentencia->fetchColumn();

    $sentencia = $pdo->prepare("
        SELECT count(*)
        FROM sis_perfil p
        INNER JOIN sis_perfil_permisos pp ON pp.sis_perfil_id = p.sis_perfil_id
        WHERE p.sis_empresa_id = :empresa_id
          AND p.sis_perfil_codigo = 'SUPERUSUARIO'
    ");
    $sentencia->execute(['empresa_id' => $empresaId]);
    $permisosSuperusuario = (int) $sentencia->fetchColumn();

    echo $perfiles >= 7 && $permisosSuperusuario > 0 ? "EMPRESA_CREA_PERFILES_OK\n" : "EMPRESA_CREA_PERFILES_ERROR\n";
} catch (Throwable $excepcion) {
    echo "EMPRESA_CREA_PERFILES_ERROR\n";
} finally {
    if ($empresaId) {
        $sentencia = $pdo->prepare("
            DELETE FROM sis_perfil_permisos
            WHERE sis_empresa_id = :empresa_id
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);

        $sentencia = $pdo->prepare("DELETE FROM sis_perfil WHERE sis_empresa_id = :empresa_id");
        $sentencia->execute(['empresa_id' => $empresaId]);

        $sentencia = $pdo->prepare("DELETE FROM sis_empresa WHERE sis_empresa_id = :empresa_id");
        $sentencia->execute(['empresa_id' => $empresaId]);
    }
}
