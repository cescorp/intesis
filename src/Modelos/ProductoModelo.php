<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class ProductoModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA PRODUCTOS RESPETANDO EMPRESA ACTIVA O ALCANCE GLOBAL.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas): array
    {
        $filtro = $verTodas ? '' : 'AND p.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                p.*, e.sis_empresa_nombre_comercial,
                c.inv_categoria_nombre, m.inv_marca_nombre,
                es.sis_estado_codigo, es.sis_estado_nombre,
                img.sis_archivos_id AS imagen_principal_id,
                img.sis_archivos_ubicacion AS imagen_principal_ubicacion
            FROM inv_producto p
            INNER JOIN sis_empresa e ON e.sis_empresa_id = p.sis_empresa_id
            INNER JOIN inv_categoria c ON c.inv_categoria_id = p.inv_categoria_id
            INNER JOIN inv_marca m ON m.inv_marca_id = p.inv_marca_id
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            LEFT JOIN sis_archivos img ON img.sis_empresa_id = p.sis_empresa_id
                AND img.sis_archivos_tabla = 'INV_PRODUCTO'
                AND img.sis_archivos_id_padre = p.inv_producto_id
                AND img.sis_archivos_estado = 1
                AND img.sis_archivos_principal = TRUE
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial, p.inv_producto_nombre
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
     * * CREA UN PRODUCTO DE INVENTARIO.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_producto (
                sis_empresa_id, inv_categoria_id, inv_marca_id,
                inv_producto_codigo_principal, inv_producto_codigo_auxiliar,
                inv_producto_nombre, inv_producto_descripcion,
                inv_producto_unidad_medida, inv_producto_lleva_iva,
                inv_producto_costo_promedio, inv_producto_costo_ultimo,
                inv_producto_stock_minimo, inv_producto_stock_maximo,
                sis_estado_id, usuario_crea
            )
            VALUES (
                :empresa_id, :categoria_id, :marca_id,
                :codigo_principal, :codigo_auxiliar,
                :nombre, :descripcion,
                'UND', :lleva_iva,
                0, :costo_ultimo,
                :stock_minimo, :stock_maximo,
                :estado_id, :usuario_crea
            )
            RETURNING inv_producto_id
        ");
        $this->vincularDatos($sentencia, $datos);
        $sentencia->bindValue(':estado_id', $this->obtenerEstadoId('ACTIVO'), PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_crea', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UN PRODUCTO EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $productoId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_producto
            SET sis_empresa_id = :empresa_id,
                inv_categoria_id = :categoria_id,
                inv_marca_id = :marca_id,
                inv_producto_codigo_principal = :codigo_principal,
                inv_producto_codigo_auxiliar = :codigo_auxiliar,
                inv_producto_nombre = :nombre,
                inv_producto_descripcion = :descripcion,
                inv_producto_unidad_medida = 'UND',
                inv_producto_lleva_iva = :lleva_iva,
                inv_producto_costo_ultimo = :costo_ultimo,
                inv_producto_stock_minimo = :stock_minimo,
                inv_producto_stock_maximo = :stock_maximo,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_producto_id = :producto_id
        ");
        $this->vincularDatos($sentencia, $datos);
        $sentencia->bindValue(':producto_id', $productoId, PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UN PRODUCTO.
     * ***************************************************************************
     */
    public function cambiarEstado(int $productoId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_producto
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_producto_id = :producto_id
        ");
        $sentencia->execute([
            'producto_id' => $productoId,
            'estado_id' => $this->obtenerEstadoId($estado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UN PRODUCTO POR ID.
     * ***************************************************************************
     */
    public function buscar(int $productoId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT p.*, es.sis_estado_codigo
            FROM inv_producto p
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.inv_producto_id = :producto_id
            LIMIT 1
        ");
        $sentencia->execute(['producto_id' => $productoId]);
        $producto = $sentencia->fetch();

        return $producto ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA CODIGO PRINCIPAL REPETIDO POR EMPRESA.
     * ***************************************************************************
     */
    public function existeCodigo(int $empresaId, string $codigo, ?int $productoId = null): bool
    {
        $sql = "
            SELECT 1
            FROM inv_producto p
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id
              AND upper(p.inv_producto_codigo_principal) = upper(:codigo)
              AND es.sis_estado_codigo <> 'ELIMINADO'
        ";
        $parametros = ['empresa_id' => $empresaId, 'codigo' => $codigo];
        if ($productoId !== null) {
            $sql .= ' AND p.inv_producto_id <> :producto_id';
            $parametros['producto_id'] = $productoId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE PRODUCTO POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'INVENTARIO'
              AND sis_estado_entidad = 'INV_PRODUCTO'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VINCULA DATOS DE PRODUCTO CON TIPOS PDO CORRECTOS.
     * ***************************************************************************
     */
    private function vincularDatos(\PDOStatement $sentencia, array $datos): void
    {
        $sentencia->bindValue(':empresa_id', (int) $datos['empresa_id'], PDO::PARAM_INT);
        $sentencia->bindValue(':categoria_id', (int) $datos['categoria_id'], PDO::PARAM_INT);
        $sentencia->bindValue(':marca_id', (int) $datos['marca_id'], PDO::PARAM_INT);
        $sentencia->bindValue(':codigo_principal', $datos['codigo_principal']);
        $sentencia->bindValue(':codigo_auxiliar', $datos['codigo_auxiliar']);
        $sentencia->bindValue(':nombre', $datos['nombre']);
        $sentencia->bindValue(':descripcion', $datos['descripcion']);
        $sentencia->bindValue(':lleva_iva', (int) $datos['lleva_iva'], PDO::PARAM_INT);
        $sentencia->bindValue(':costo_ultimo', (string) $datos['costo_ultimo']);
        $sentencia->bindValue(':stock_minimo', (string) $datos['stock_minimo']);
        $sentencia->bindValue(':stock_maximo', (string) $datos['stock_maximo']);
    }
}
