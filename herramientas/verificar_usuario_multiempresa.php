<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/../src/Modelos/UsuarioEmpresaModelo.php';

use Intesis\Modelos\UsuarioEmpresaModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$modelo = new UsuarioEmpresaModelo($conexion);
$pdo = $conexion->obtener();
$correo = 'verificacion.multiempresa@intesis.local';
$perfilTemporalId = null;
$usuarioId = null;

try {
    $empresaPrincipal = $pdo->query("
        SELECT e.sis_empresa_id, p.sis_perfil_id
        FROM sis_empresa e
        INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
        INNER JOIN sis_perfil p ON p.sis_empresa_id = e.sis_empresa_id AND p.sis_perfil_estado = 1
        WHERE es.sis_estado_codigo = 'ACTIVO'
        ORDER BY e.sis_empresa_id
        LIMIT 1
    ")->fetch();

    $empresaSecundaria = $pdo->query("
        SELECT e.sis_empresa_id
        FROM sis_empresa e
        INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
        WHERE es.sis_estado_codigo = 'ACTIVO'
          AND e.sis_empresa_id <> " . (int) $empresaPrincipal['sis_empresa_id'] . "
        ORDER BY e.sis_empresa_id
        LIMIT 1
    ")->fetch();

    if (!$empresaPrincipal || !$empresaSecundaria) {
        echo "USUARIO_MULTIEMPRESA_SIN_DATOS\n";
        exit;
    }

    $sentencia = $pdo->prepare("
        INSERT INTO sis_perfil (
            sis_empresa_id,
            sis_perfil_codigo,
            sis_perfil_nombre,
            sis_perfil_estado,
            usuario_crea
        )
        VALUES (:empresa_id, 'TEMP_MULTI', 'TEMP MULTI', 1, 1)
        RETURNING sis_perfil_id
    ");
    $sentencia->execute(['empresa_id' => $empresaSecundaria['sis_empresa_id']]);
    $perfilTemporalId = (int) $sentencia->fetchColumn();

    $modelo->crear([
        'nombre' => 'Verificacion Multiempresa',
        'correo' => $correo,
        'clave' => 'Temporal123',
        'confirmar_clave' => 'Temporal123',
        'empresa_id' => (int) $empresaPrincipal['sis_empresa_id'],
        'perfil_id' => (int) $empresaPrincipal['sis_perfil_id'],
        'asignaciones' => [
            [
                'empresa_id' => (int) $empresaPrincipal['sis_empresa_id'],
                'perfil_id' => (int) $empresaPrincipal['sis_perfil_id'],
            ],
            [
                'empresa_id' => (int) $empresaSecundaria['sis_empresa_id'],
                'perfil_id' => $perfilTemporalId,
            ],
        ],
    ], 1);

    $sentencia = $pdo->prepare("SELECT sis_usuarios_id FROM sis_usuarios WHERE sis_usuarios_correo = :correo");
    $sentencia->execute(['correo' => $correo]);
    $usuarioId = (int) $sentencia->fetchColumn();

    $sentencia = $pdo->prepare("SELECT count(*) FROM sis_usuario_empresa WHERE sis_usuarios_id = :usuario_id");
    $sentencia->execute(['usuario_id' => $usuarioId]);
    $total = (int) $sentencia->fetchColumn();

    echo $total === 2 ? "USUARIO_MULTIEMPRESA_OK\n" : "USUARIO_MULTIEMPRESA_ERROR\n";
} catch (Throwable $excepcion) {
    echo "USUARIO_MULTIEMPRESA_ERROR\n";
} finally {
    if ($usuarioId) {
        $sentencia = $pdo->prepare("DELETE FROM sis_usuario_empresa WHERE sis_usuarios_id = :usuario_id");
        $sentencia->execute(['usuario_id' => $usuarioId]);

        $sentencia = $pdo->prepare("DELETE FROM sis_usuarios WHERE sis_usuarios_id = :usuario_id");
        $sentencia->execute(['usuario_id' => $usuarioId]);
    }

    if ($perfilTemporalId) {
        $sentencia = $pdo->prepare("DELETE FROM sis_perfil WHERE sis_perfil_id = :perfil_id");
        $sentencia->execute(['perfil_id' => $perfilTemporalId]);
    }
}
