<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class MarcaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA MARCAS RESPETANDO EMPRESA ACTIVA O ALCANCE GLOBAL.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas, bool $soloActivas = false): array
    {
        $filtroEmpresa = $verTodas ? '' : 'AND m.sis_empresa_id = :empresa_id';
        $filtroEstado = $soloActivas ? "AND es.sis_estado_codigo = 'ACTIVO'" : "AND es.sis_estado_codigo <> 'ELIMINADO'";
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT m.*, e.sis_empresa_nombre_comercial, es.sis_estado_codigo, es.sis_estado_nombre
            FROM inv_marca m
            INNER JOIN sis_empresa e ON e.sis_empresa_id = m.sis_empresa_id
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            WHERE 1 = 1
            {$filtroEmpresa}
            {$filtroEstado}
            ORDER BY e.sis_empresa_nombre_comercial, m.inv_marca_nombre
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
     * * CREA UNA MARCA DE PRODUCTOS.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_marca (
                sis_empresa_id, inv_marca_nombre, sis_estado_id, usuario_crea
            )
            VALUES (:empresa_id, :nombre, :estado_id, :usuario_crea)
            RETURNING inv_marca_id
        ");
        $sentencia->execute([
            'empresa_id' => $datos['empresa_id'],
            'nombre' => $datos['nombre'],
            'estado_id' => $this->obtenerEstadoId('ACTIVO'),
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UNA MARCA EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $marcaId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_marca
            SET sis_empresa_id = :empresa_id,
                inv_marca_nombre = :nombre,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_marca_id = :marca_id
        ");
        $sentencia->execute([
            'marca_id' => $marcaId,
            'empresa_id' => $datos['empresa_id'],
            'nombre' => $datos['nombre'],
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UNA MARCA.
     * ***************************************************************************
     */
    public function cambiarEstado(int $marcaId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_marca
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_marca_id = :marca_id
        ");
        $sentencia->execute([
            'marca_id' => $marcaId,
            'estado_id' => $this->obtenerEstadoId($estado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UNA MARCA POR ID.
     * ***************************************************************************
     */
    public function buscar(int $marcaId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT m.*, es.sis_estado_codigo
            FROM inv_marca m
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            WHERE m.inv_marca_id = :marca_id
            LIMIT 1
        ");
        $sentencia->execute(['marca_id' => $marcaId]);
        $marca = $sentencia->fetch();

        return $marca ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA NOMBRE REPETIDO POR EMPRESA.
     * ***************************************************************************
     */
    public function existeNombre(int $empresaId, string $nombre, ?int $marcaId = null): bool
    {
        $sql = "
            SELECT 1
            FROM inv_marca m
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            WHERE m.sis_empresa_id = :empresa_id
              AND upper(m.inv_marca_nombre) = upper(:nombre)
              AND es.sis_estado_codigo <> 'ELIMINADO'
        ";
        $parametros = ['empresa_id' => $empresaId, 'nombre' => $nombre];
        if ($marcaId !== null) {
            $sql .= ' AND m.inv_marca_id <> :marca_id';
            $parametros['marca_id'] = $marcaId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VALIDA SI LA MARCA ACTIVA PERTENECE A LA EMPRESA.
     * ***************************************************************************
     */
    public function perteneceEmpresa(int $marcaId, int $empresaId): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM inv_marca m
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            WHERE m.inv_marca_id = :marca_id
              AND m.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ");
        $sentencia->execute(['marca_id' => $marcaId, 'empresa_id' => $empresaId]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE MARCA POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'INVENTARIO'
              AND sis_estado_entidad = 'INV_MARCA'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }
}
