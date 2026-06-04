<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class BodegaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA BODEGAS RESPETANDO EMPRESA ACTIVA O ALCANCE GLOBAL.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas): array
    {
        $filtro = $verTodas ? '' : 'AND b.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                b.*,
                e.sis_empresa_nombre_comercial,
                es.sis_estado_codigo,
                es.sis_estado_nombre
            FROM inv_bodega b
            INNER JOIN sis_empresa e ON e.sis_empresa_id = b.sis_empresa_id
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial, b.inv_bodega_codigo
        ");
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA FILTROS Y FORMULARIOS.
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
     * * CREA UNA BODEGA DE INVENTARIO.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            if ($datos['principal']) {
                $this->limpiarPrincipal((int) $datos['empresa_id'], $usuarioId);
            }
            $sentencia = $pdo->prepare("
                INSERT INTO inv_bodega (
                    sis_empresa_id, inv_bodega_codigo, inv_bodega_nombre,
                    inv_bodega_descripcion, inv_bodega_direccion,
                    inv_bodega_es_principal, inv_bodega_virtual,
                    sis_estado_id, usuario_crea
                )
                VALUES (
                    :empresa_id, :codigo, :nombre,
                    :descripcion, :direccion,
                    :principal, :virtual,
                    :estado_id, :usuario_crea
                )
            ");
            $this->vincularDatos($sentencia, $datos);
            $sentencia->bindValue(':estado_id', $this->obtenerEstadoId('ACTIVO'), PDO::PARAM_INT);
            $sentencia->bindValue(':usuario_crea', $usuarioId, PDO::PARAM_INT);
            $sentencia->execute();
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UNA BODEGA EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $bodegaId, array $datos, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            if ($datos['principal']) {
                $this->limpiarPrincipal((int) $datos['empresa_id'], $usuarioId, $bodegaId);
            }
            $sentencia = $pdo->prepare("
                UPDATE inv_bodega
                SET sis_empresa_id = :empresa_id,
                    inv_bodega_codigo = :codigo,
                    inv_bodega_nombre = :nombre,
                    inv_bodega_descripcion = :descripcion,
                    inv_bodega_direccion = :direccion,
                    inv_bodega_es_principal = :principal,
                    inv_bodega_virtual = :virtual,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE inv_bodega_id = :bodega_id
            ");
            $this->vincularDatos($sentencia, $datos);
            $sentencia->bindValue(':bodega_id', $bodegaId, PDO::PARAM_INT);
            $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
            $sentencia->execute();
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UNA BODEGA.
     * ***************************************************************************
     */
    public function cambiarEstado(int $bodegaId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_bodega
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_bodega_id = :bodega_id
        ");
        $sentencia->execute([
            'bodega_id' => $bodegaId,
            'estado_id' => $this->obtenerEstadoId($estado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UNA BODEGA POR ID.
     * ***************************************************************************
     */
    public function buscar(int $bodegaId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT b.*, es.sis_estado_codigo
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE b.inv_bodega_id = :bodega_id
            LIMIT 1
        ");
        $sentencia->execute(['bodega_id' => $bodegaId]);
        $bodega = $sentencia->fetch();

        return $bodega ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA CODIGO REPETIDO POR EMPRESA.
     * ***************************************************************************
     */
    public function existeCodigo(int $empresaId, string $codigo, ?int $bodegaId = null): bool
    {
        $sql = "
            SELECT 1
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE b.sis_empresa_id = :empresa_id
              AND upper(b.inv_bodega_codigo) = upper(:codigo)
              AND es.sis_estado_codigo <> 'ELIMINADO'
        ";
        $parametros = ['empresa_id' => $empresaId, 'codigo' => $codigo];
        if ($bodegaId !== null) {
            $sql .= ' AND b.inv_bodega_id <> :bodega_id';
            $parametros['bodega_id'] = $bodegaId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI UNA BODEGA TIENE STOCK O MOVIMIENTOS ASOCIADOS.
     * ***************************************************************************
     */
    public function estaUsada(int $bodegaId): bool
    {
        $consultas = [
            'SELECT 1 FROM inv_stock WHERE inv_bodega_id = :bodega_id LIMIT 1',
            'SELECT 1 FROM inv_kardex WHERE inv_bodega_id = :bodega_id LIMIT 1',
            'SELECT 1 FROM inv_movimientos WHERE inv_bodega_origen_id = :bodega_id OR inv_bodega_destino_id = :bodega_id LIMIT 1',
        ];
        foreach ($consultas as $sql) {
            $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
            $sentencia->execute(['bodega_id' => $bodegaId]);
            if ($sentencia->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE BODEGA POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'INVENTARIO'
              AND sis_estado_entidad = 'INV_BODEGA'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * LIMPIA BODEGA PRINCIPAL PREVIA DE UNA EMPRESA.
     * ***************************************************************************
     */
    private function limpiarPrincipal(int $empresaId, int $usuarioId, ?int $exceptoBodegaId = null): void
    {
        $sql = "
            UPDATE inv_bodega
            SET inv_bodega_es_principal = FALSE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_empresa_id = :empresa_id
        ";
        $parametros = ['empresa_id' => $empresaId, 'usuario_modifica' => $usuarioId];
        if ($exceptoBodegaId !== null) {
            $sql .= ' AND inv_bodega_id <> :bodega_id';
            $parametros['bodega_id'] = $exceptoBodegaId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($parametros);
    }

    /**
     * ***************************************************************************
     * * VINCULA DATOS DE BODEGA CON TIPOS PDO CORRECTOS.
     * ***************************************************************************
     */
    private function vincularDatos(\PDOStatement $sentencia, array $datos): void
    {
        $sentencia->bindValue(':empresa_id', (int) $datos['empresa_id'], PDO::PARAM_INT);
        $sentencia->bindValue(':codigo', $datos['codigo']);
        $sentencia->bindValue(':nombre', $datos['nombre']);
        $sentencia->bindValue(':descripcion', $datos['descripcion']);
        $sentencia->bindValue(':direccion', $datos['direccion']);
        $sentencia->bindValue(':principal', (bool) $datos['principal'], PDO::PARAM_BOOL);
        $sentencia->bindValue(':virtual', (bool) $datos['virtual'], PDO::PARAM_BOOL);
    }
}
