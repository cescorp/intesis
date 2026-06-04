<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class ModuloModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA MODULOS Y LICENCIAS ACTIVAS DE LA EMPRESA DEL USUARIO.
     * ***************************************************************************
     */
    public function listarPorEmpresa(int $empresaId): array
    {
        $sql = "
            SELECT
                m.sis_modulo_id,
                m.sis_modulo_nombre,
                m.sis_modulo_descripcion,
                l.sis_licencia_tipo,
                l.sis_licencia_fecha_inicio,
                l.sis_licencia_fecha_fin,
                l.sis_licencia_estado
            FROM sis_modulo m
            LEFT JOIN sis_licencia l
                ON l.sis_modulo_id = m.sis_modulo_id
               AND l.sis_empresa_id = :empresa_id
               AND l.sis_licencia_estado = 'ACTIVO'
               AND current_date BETWEEN l.sis_licencia_fecha_inicio AND l.sis_licencia_fecha_fin
            WHERE m.sis_modulo_estado = 1
            ORDER BY m.sis_modulo_id
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute(['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }
}
