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
$pdo = $conexion->obtener();
$servicio = new AutenticacionServicio(new UsuarioModelo($conexion), new RegistroErrores($configuracion));
$usuarioIds = [];

$empresas = $pdo->query("
    SELECT e.sis_empresa_id, min(p.sis_perfil_id) AS sis_perfil_id
    FROM sis_empresa e
    INNER JOIN sis_perfil p ON p.sis_empresa_id = e.sis_empresa_id
    GROUP BY e.sis_empresa_id
    ORDER BY e.sis_empresa_id
    LIMIT 2
")->fetchAll();

if (count($empresas) < 2) {
    echo "LOGIN_EMPRESA_ASIGNADA_SIN_DATOS\n";
    exit;
}

try {
    $estadoId = (int) $pdo->query("
        SELECT sis_estado_id
        FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA'
          AND sis_estado_entidad = 'SIS_USUARIOS'
          AND sis_estado_codigo = 'ACTIVO'
        LIMIT 1
    ")->fetchColumn();

    $crearUsuario = function (string $correo, array $asignaciones) use ($pdo, $estadoId, &$usuarioIds): int {
        $sentencia = $pdo->prepare("
            INSERT INTO sis_usuarios (sis_usuarios_nombre, sis_usuarios_correo, sis_usuarios_password, sis_estado_id, usuario_crea)
            VALUES ('Verifica login empresa', :correo, :clave, :estado_id, 1)
            RETURNING sis_usuarios_id
        ");
        $sentencia->execute([
            'correo' => $correo,
            'clave' => password_hash('Temporal123', PASSWORD_ARGON2ID),
            'estado_id' => $estadoId,
        ]);
        $usuarioId = (int) $sentencia->fetchColumn();
        $usuarioIds[] = $usuarioId;

        foreach ($asignaciones as $indice => $asignacion) {
            $sentencia = $pdo->prepare("
                INSERT INTO sis_usuario_empresa (
                    sis_usuarios_id, sis_empresa_id, sis_perfil_id, sis_estado_id,
                    sis_usuario_empresa_predeterminada, usuario_crea
                )
                VALUES (:usuario_id, :empresa_id, :perfil_id, :estado_id, :predeterminada, 1)
            ");
            $sentencia->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $sentencia->bindValue(':empresa_id', (int) $asignacion['sis_empresa_id'], PDO::PARAM_INT);
            $sentencia->bindValue(':perfil_id', (int) $asignacion['sis_perfil_id'], PDO::PARAM_INT);
            $sentencia->bindValue(':estado_id', $estadoId, PDO::PARAM_INT);
            $sentencia->bindValue(':predeterminada', $indice === 0, PDO::PARAM_BOOL);
            $sentencia->execute();
        }

        return $usuarioId;
    };

    $correoUno = 'login.unica.' . time() . '@intesis.local';
    $correoDos = 'login.multiple.' . time() . '@intesis.local';
    $crearUsuario($correoUno, [$empresas[0]]);
    $crearUsuario($correoDos, [$empresas[0], $empresas[1]]);

    $usuarioUno = $servicio->autenticar($correoUno, 'Temporal123');
    $usuarioDos = $servicio->autenticar($correoDos, 'Temporal123');

    $ok = $usuarioUno && count($usuarioUno['empresas']) === 1
        && $usuarioDos && count($usuarioDos['empresas']) === 2;

    echo $ok ? "LOGIN_EMPRESA_ASIGNADA_OK\n" : "LOGIN_EMPRESA_ASIGNADA_ERROR\n";
} finally {
    foreach ($usuarioIds as $usuarioId) {
        $pdo->prepare('DELETE FROM sis_usuario_empresa WHERE sis_usuarios_id = :usuario_id')->execute(['usuario_id' => $usuarioId]);
        $pdo->prepare('DELETE FROM sis_usuarios WHERE sis_usuarios_id = :usuario_id')->execute(['usuario_id' => $usuarioId]);
    }
}
