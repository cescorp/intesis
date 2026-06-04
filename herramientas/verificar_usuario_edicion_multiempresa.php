<?php

declare(strict_types=1);

require __DIR__ . '/../src/Nucleo/Configuracion.php';
require __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require __DIR__ . '/../src/Modelos/UsuarioEmpresaModelo.php';
require __DIR__ . '/../src/Modelos/UsuarioModelo.php';

use Intesis\Modelos\UsuarioEmpresaModelo;
use Intesis\Modelos\UsuarioModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$pdo = $conexion->obtener();
$usuarioEmpresaModelo = new UsuarioEmpresaModelo($conexion);
$usuarioModelo = new UsuarioModelo($conexion);

$empresas = $pdo->query("
    SELECT e.sis_empresa_id, min(p.sis_perfil_id) AS sis_perfil_id
    FROM sis_empresa e
    INNER JOIN sis_perfil p ON p.sis_empresa_id = e.sis_empresa_id
    GROUP BY e.sis_empresa_id
    ORDER BY e.sis_empresa_id
    LIMIT 2
")->fetchAll();

if (count($empresas) < 2) {
    echo "USUARIO_EDICION_MULTIEMPRESA_SIN_DATOS\n";
    exit;
}

$usuarioId = 0;
try {
    $correo = 'verifica.multiempresa.' . time() . '@intesis.local';
    $estadoId = (int) $pdo->query("
        SELECT sis_estado_id
        FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA'
          AND sis_estado_entidad = 'SIS_USUARIOS'
          AND sis_estado_codigo = 'ACTIVO'
        LIMIT 1
    ")->fetchColumn();
    $sentencia = $pdo->prepare("
        INSERT INTO sis_usuarios (sis_usuarios_nombre, sis_usuarios_correo, sis_usuarios_password, sis_estado_id, usuario_crea)
        VALUES ('Verifica multiempresa', :correo, :clave, :estado_id, 1)
        RETURNING sis_usuarios_id
    ");
    $sentencia->execute([
        'correo' => $correo,
        'clave' => password_hash('Temporal123', PASSWORD_ARGON2ID),
        'estado_id' => $estadoId,
    ]);
    $usuarioId = (int) $sentencia->fetchColumn();

    $sentencia = $pdo->prepare("
        INSERT INTO sis_usuario_empresa (
            sis_usuarios_id, sis_empresa_id, sis_perfil_id, sis_estado_id,
            sis_usuario_empresa_predeterminada, usuario_crea
        )
        VALUES (:usuario_id, :empresa_id, :perfil_id, :estado_id, TRUE, 1)
        RETURNING sis_usuario_empresa_id
    ");
    $sentencia->execute([
        'usuario_id' => $usuarioId,
        'empresa_id' => (int) $empresas[0]['sis_empresa_id'],
        'perfil_id' => (int) $empresas[0]['sis_perfil_id'],
        'estado_id' => $estadoId,
    ]);
    $asignacionId = (int) $sentencia->fetchColumn();

    $usuarioEmpresaModelo->actualizarConAsignaciones($asignacionId, [
        'nombre' => 'Verifica multiempresa editado',
        'correo' => $correo,
        'asignaciones' => [
            [
                'asignacion_id' => $asignacionId,
                'empresa_id' => (int) $empresas[0]['sis_empresa_id'],
                'perfil_id' => (int) $empresas[0]['sis_perfil_id'],
                'inactivar' => false,
                'predeterminada' => false,
            ],
            [
                'asignacion_id' => 0,
                'empresa_id' => (int) $empresas[1]['sis_empresa_id'],
                'perfil_id' => (int) $empresas[1]['sis_perfil_id'],
                'inactivar' => false,
                'predeterminada' => true,
            ],
        ],
    ], 1);

    $asignaciones = $usuarioEmpresaModelo->listarAsignacionesUsuario($usuarioId, true, 0);
    $login = $usuarioModelo->listarEmpresasAsignadas($usuarioId);
    $predeterminadas = array_filter($asignaciones, fn (array $fila): bool => (bool) $fila['sis_usuario_empresa_predeterminada']);

    $ok = count($asignaciones) === 2
        && count($login) === 2
        && count($predeterminadas) === 1
        && (int) array_values($predeterminadas)[0]['sis_empresa_id'] === (int) $empresas[1]['sis_empresa_id'];

    echo $ok ? "USUARIO_EDICION_MULTIEMPRESA_OK\n" : "USUARIO_EDICION_MULTIEMPRESA_ERROR\n";
} finally {
    if ($usuarioId > 0) {
        $pdo->prepare('DELETE FROM sis_usuario_empresa WHERE sis_usuarios_id = :usuario_id')->execute(['usuario_id' => $usuarioId]);
        $pdo->prepare('DELETE FROM sis_usuarios WHERE sis_usuarios_id = :usuario_id')->execute(['usuario_id' => $usuarioId]);
    }
}
