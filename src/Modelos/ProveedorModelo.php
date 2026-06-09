<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class ProveedorModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA PROVEEDORES POR EMPRESA.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas): array
    {
        $filtroEmpresa = $verTodas ? '' : 'AND p.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT p.*,
                   e.sis_empresa_nombre_comercial,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre
            FROM com_proveedor p
            INNER JOIN sis_empresa e  ON e.sis_empresa_id  = p.sis_empresa_id
            INNER JOIN sis_estado es  ON es.sis_estado_id  = p.sis_estado_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtroEmpresa}
            ORDER BY p.com_proveedor_razon_social
        ");
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA FORMULARIOS DE SUPERUSUARIO.
     * ***************************************************************************
     */
    public function listarEmpresasActivas(bool $verTodas, int $empresaId): array
    {
        $filtro = $verTodas ? '' : 'AND e.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT e.sis_empresa_id, e.sis_empresa_nombre_comercial
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE es.sis_estado_codigo = 'ACTIVO'
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial
        ");
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA UN PROVEEDOR.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO com_proveedor (
                sis_empresa_id,
                com_proveedor_tipo_identificacion,
                com_proveedor_identificacion,
                com_proveedor_razon_social,
                com_proveedor_nombre_comercial,
                com_proveedor_telefono,
                com_proveedor_email,
                com_proveedor_direccion,
                sis_estado_id,
                usuario_crea
            )
            VALUES (
                :empresa_id,
                :tipo_identificacion,
                :identificacion,
                :razon_social,
                :nombre_comercial,
                :telefono,
                :email,
                :direccion,
                :estado_id,
                :usuario_crea
            )
            RETURNING com_proveedor_id
        ");
        $sentencia->execute([
            'empresa_id'          => $datos['empresa_id'],
            'tipo_identificacion' => $datos['tipo_identificacion'],
            'identificacion'      => $datos['identificacion'],
            'razon_social'        => $datos['razon_social'],
            'nombre_comercial'    => $datos['nombre_comercial'] !== '' ? $datos['nombre_comercial'] : null,
            'telefono'            => $datos['telefono']  !== '' ? $datos['telefono']  : null,
            'email'               => $datos['email']     !== '' ? $datos['email']     : null,
            'direccion'           => $datos['direccion'] !== '' ? $datos['direccion'] : null,
            'estado_id'           => $this->obtenerEstadoId('ACTIVO'),
            'usuario_crea'        => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UN PROVEEDOR EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $proveedorId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE com_proveedor
            SET com_proveedor_tipo_identificacion = :tipo_identificacion,
                com_proveedor_identificacion      = :identificacion,
                com_proveedor_razon_social        = :razon_social,
                com_proveedor_nombre_comercial    = :nombre_comercial,
                com_proveedor_telefono            = :telefono,
                com_proveedor_email               = :email,
                com_proveedor_direccion           = :direccion,
                usuario_modifica                  = :usuario_modifica,
                fecha_modifica                    = now()
            WHERE com_proveedor_id = :proveedor_id
        ");
        $sentencia->execute([
            'proveedor_id'        => $proveedorId,
            'tipo_identificacion' => $datos['tipo_identificacion'],
            'identificacion'      => $datos['identificacion'],
            'razon_social'        => $datos['razon_social'],
            'nombre_comercial'    => $datos['nombre_comercial'] !== '' ? $datos['nombre_comercial'] : null,
            'telefono'            => $datos['telefono']  !== '' ? $datos['telefono']  : null,
            'email'               => $datos['email']     !== '' ? $datos['email']     : null,
            'direccion'           => $datos['direccion'] !== '' ? $datos['direccion'] : null,
            'usuario_modifica'    => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UN PROVEEDOR.
     * ***************************************************************************
     */
    public function cambiarEstado(int $proveedorId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE com_proveedor
            SET sis_estado_id     = :estado_id,
                usuario_modifica  = :usuario_modifica,
                fecha_modifica    = now()
            WHERE com_proveedor_id = :proveedor_id
        ");
        $sentencia->execute([
            'proveedor_id'     => $proveedorId,
            'estado_id'        => $this->obtenerEstadoId($estado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UN PROVEEDOR POR ID.
     * ***************************************************************************
     */
    public function buscar(int $proveedorId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT p.*, es.sis_estado_codigo
            FROM com_proveedor p
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.com_proveedor_id = :proveedor_id
            LIMIT 1
        ");
        $sentencia->execute(['proveedor_id' => $proveedorId]);
        $fila = $sentencia->fetch();

        return $fila ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA IDENTIFICACION DUPLICADA POR EMPRESA.
     * ***************************************************************************
     */
    public function existeIdentificacion(int $empresaId, string $identificacion, ?int $proveedorId = null): bool
    {
        $sql = "
            SELECT 1
            FROM com_proveedor p
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id
              AND upper(p.com_proveedor_identificacion) = upper(:identificacion)
              AND es.sis_estado_codigo <> 'ELIMINADO'
        ";
        $parametros = [
            'empresa_id'     => $empresaId,
            'identificacion' => $identificacion,
        ];
        if ($proveedorId !== null) {
            $sql .= ' AND p.com_proveedor_id <> :proveedor_id';
            $parametros['proveedor_id'] = $proveedorId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE PROVEEDOR POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo  = 'COMPRAS'
              AND sis_estado_entidad = 'COM_PROVEEDOR'
              AND sis_estado_codigo  = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }
}
