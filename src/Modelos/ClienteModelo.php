<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class ClienteModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA CLIENTES POR EMPRESA.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas): array
    {
        $filtroEmpresa = $verTodas ? '' : 'AND c.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT c.*,
                   e.sis_empresa_nombre_comercial,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre
            FROM ven_cliente c
            INNER JOIN sis_empresa e ON e.sis_empresa_id = c.sis_empresa_id
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtroEmpresa}
            ORDER BY c.ven_cliente_razon_social
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
     * * CREA UN CLIENTE.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO ven_cliente (
                sis_empresa_id,
                ven_cliente_tipo_identificacion,
                ven_cliente_identificacion,
                ven_cliente_razon_social,
                ven_cliente_nombre_comercial,
                ven_cliente_telefono,
                ven_cliente_email,
                ven_cliente_direccion,
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
            RETURNING ven_cliente_id
        ");
        $sentencia->execute([
            'empresa_id'          => $datos['empresa_id'],
            'tipo_identificacion' => $datos['tipo_identificacion'],
            'identificacion'      => $datos['identificacion'],
            'razon_social'        => $datos['razon_social'],
            'nombre_comercial'    => $datos['nombre_comercial'] !== '' ? $datos['nombre_comercial'] : null,
            'telefono'            => $datos['telefono'],
            'email'               => $datos['email'],
            'direccion'           => $datos['direccion'],
            'estado_id'           => $this->obtenerEstadoId('ACTIVO'),
            'usuario_crea'        => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UN CLIENTE EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $clienteId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE ven_cliente
            SET ven_cliente_tipo_identificacion = :tipo_identificacion,
                ven_cliente_identificacion      = :identificacion,
                ven_cliente_razon_social        = :razon_social,
                ven_cliente_nombre_comercial    = :nombre_comercial,
                ven_cliente_telefono            = :telefono,
                ven_cliente_email               = :email,
                ven_cliente_direccion           = :direccion,
                usuario_modifica                = :usuario_modifica,
                fecha_modifica                  = now()
            WHERE ven_cliente_id = :cliente_id
        ");
        $sentencia->execute([
            'cliente_id'          => $clienteId,
            'tipo_identificacion' => $datos['tipo_identificacion'],
            'identificacion'      => $datos['identificacion'],
            'razon_social'        => $datos['razon_social'],
            'nombre_comercial'    => $datos['nombre_comercial'] !== '' ? $datos['nombre_comercial'] : null,
            'telefono'            => $datos['telefono'],
            'email'               => $datos['email'],
            'direccion'           => $datos['direccion'],
            'usuario_modifica'    => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UN CLIENTE.
     * ***************************************************************************
     */
    public function cambiarEstado(int $clienteId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE ven_cliente
            SET sis_estado_id    = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica   = now()
            WHERE ven_cliente_id = :cliente_id
        ");
        $sentencia->execute([
            'cliente_id'       => $clienteId,
            'estado_id'        => $this->obtenerEstadoId($estado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UN CLIENTE POR ID.
     * ***************************************************************************
     */
    public function buscar(int $clienteId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT c.*, es.sis_estado_codigo
            FROM ven_cliente c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.ven_cliente_id = :cliente_id
            LIMIT 1
        ");
        $sentencia->execute(['cliente_id' => $clienteId]);
        $fila = $sentencia->fetch();

        return $fila ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA IDENTIFICACION DUPLICADA POR EMPRESA.
     * ***************************************************************************
     */
    public function existeIdentificacion(int $empresaId, string $identificacion, ?int $clienteId = null): bool
    {
        $sql = "
            SELECT 1
            FROM ven_cliente c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.sis_empresa_id = :empresa_id
              AND upper(c.ven_cliente_identificacion) = upper(:identificacion)
              AND es.sis_estado_codigo <> 'ELIMINADO'
        ";
        $parametros = [
            'empresa_id'     => $empresaId,
            'identificacion' => $identificacion,
        ];
        if ($clienteId !== null) {
            $sql .= ' AND c.ven_cliente_id <> :cliente_id';
            $parametros['cliente_id'] = $clienteId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE CLIENTE POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo  = 'VENTAS'
              AND sis_estado_entidad = 'VEN_CLIENTE'
              AND sis_estado_codigo  = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }
}
