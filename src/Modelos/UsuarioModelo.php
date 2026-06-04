<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class UsuarioModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * BUSCA UN USUARIO ACTIVO POR CORREO PARA VALIDAR EL ACCESO AL SISTEMA.
     * ***************************************************************************
     */
    public function buscarPorCorreo(string $correo): ?array
    {
        $sql = "
            SELECT
                u.sis_usuarios_id,
                u.sis_usuarios_nombre,
                u.sis_usuarios_correo,
                u.sis_usuarios_password,
                es.sis_estado_codigo
            FROM sis_usuarios u
            INNER JOIN sis_estado es ON es.sis_estado_id = u.sis_estado_id
            WHERE lower(u.sis_usuarios_correo) = lower(:correo)
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute(['correo' => $correo]);
        $usuario = $sentencia->fetch();

        return $usuario ?: null;
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS ASIGNADAS AL USUARIO GLOBAL PARA EL LOGIN.
     * ***************************************************************************
     */
    public function listarEmpresasAsignadas(int $usuarioId): array
    {
        $sql = "
            SELECT
                ue.sis_usuario_empresa_id,
                ue.sis_usuarios_id,
                ue.sis_empresa_id,
                ue.sis_perfil_id,
                e.sis_empresa_razon_social,
                e.sis_empresa_nombre_comercial,
                p.sis_perfil_codigo,
                p.sis_perfil_nombre
            FROM sis_usuario_empresa ue
            INNER JOIN sis_empresa e ON e.sis_empresa_id = ue.sis_empresa_id
            INNER JOIN sis_estado ee ON ee.sis_estado_id = e.sis_estado_id
            INNER JOIN sis_perfil p ON p.sis_perfil_id = ue.sis_perfil_id
            INNER JOIN sis_estado eu ON eu.sis_estado_id = ue.sis_estado_id
            WHERE ue.sis_usuarios_id = :usuario_id
              AND eu.sis_estado_codigo = 'ACTIVO'
              AND ee.sis_estado_codigo = 'ACTIVO'
              AND p.sis_perfil_estado = 1
            ORDER BY ue.sis_usuario_empresa_predeterminada DESC, e.sis_empresa_nombre_comercial
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute(['usuario_id' => $usuarioId]);

        return $sentencia->fetchAll();
    }
}
