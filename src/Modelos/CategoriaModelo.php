<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class CategoriaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA CATEGORIAS RESPETANDO EMPRESA ACTIVA O ALCANCE GLOBAL.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas, bool $soloActivas = false): array
    {
        $filtroEmpresa = $verTodas ? '' : 'AND c.sis_empresa_id = :empresa_id';
        $filtroEstado = $soloActivas ? "AND es.sis_estado_codigo = 'ACTIVO'" : "AND es.sis_estado_codigo <> 'ELIMINADO'";
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT c.*, e.sis_empresa_nombre_comercial, es.sis_estado_codigo, es.sis_estado_nombre
            FROM inv_categoria c
            INNER JOIN sis_empresa e ON e.sis_empresa_id = c.sis_empresa_id
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE 1 = 1
            {$filtroEmpresa}
            {$filtroEstado}
            ORDER BY e.sis_empresa_nombre_comercial, c.inv_categoria_nombre
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
     * * CREA UNA CATEGORIA DE PRODUCTOS.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_categoria (
                sis_empresa_id, inv_categoria_nombre, inv_categoria_descripcion,
                sis_estado_id, usuario_crea
            )
            VALUES (:empresa_id, :nombre, :descripcion, :estado_id, :usuario_crea)
            RETURNING inv_categoria_id
        ");
        $sentencia->execute([
            'empresa_id' => $datos['empresa_id'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'estado_id' => $this->obtenerEstadoId('ACTIVO'),
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UNA CATEGORIA EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $categoriaId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_categoria
            SET sis_empresa_id = :empresa_id,
                inv_categoria_nombre = :nombre,
                inv_categoria_descripcion = :descripcion,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_categoria_id = :categoria_id
        ");
        $sentencia->execute([
            'categoria_id' => $categoriaId,
            'empresa_id' => $datos['empresa_id'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UNA CATEGORIA.
     * ***************************************************************************
     */
    public function cambiarEstado(int $categoriaId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_categoria
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_categoria_id = :categoria_id
        ");
        $sentencia->execute([
            'categoria_id' => $categoriaId,
            'estado_id' => $this->obtenerEstadoId($estado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UNA CATEGORIA POR ID.
     * ***************************************************************************
     */
    public function buscar(int $categoriaId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT c.*, es.sis_estado_codigo
            FROM inv_categoria c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.inv_categoria_id = :categoria_id
            LIMIT 1
        ");
        $sentencia->execute(['categoria_id' => $categoriaId]);
        $categoria = $sentencia->fetch();

        return $categoria ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA NOMBRE REPETIDO POR EMPRESA.
     * ***************************************************************************
     */
    public function existeNombre(int $empresaId, string $nombre, ?int $categoriaId = null): bool
    {
        $sql = "
            SELECT 1
            FROM inv_categoria c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.sis_empresa_id = :empresa_id
              AND upper(c.inv_categoria_nombre) = upper(:nombre)
              AND es.sis_estado_codigo <> 'ELIMINADO'
        ";
        $parametros = ['empresa_id' => $empresaId, 'nombre' => $nombre];
        if ($categoriaId !== null) {
            $sql .= ' AND c.inv_categoria_id <> :categoria_id';
            $parametros['categoria_id'] = $categoriaId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VALIDA SI LA CATEGORIA ACTIVA PERTENECE A LA EMPRESA.
     * ***************************************************************************
     */
    public function perteneceEmpresa(int $categoriaId, int $empresaId): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM inv_categoria c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.inv_categoria_id = :categoria_id
              AND c.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ");
        $sentencia->execute(['categoria_id' => $categoriaId, 'empresa_id' => $empresaId]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE CATEGORIA POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'INVENTARIO'
              AND sis_estado_entidad = 'INV_CATEGORIA'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }
}
