<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class KardexModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA FILTROS DE KARDEX.
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
     * * LISTA BODEGAS DE UNA EMPRESA PARA COLUMNAS DINAMICAS.
     * ***************************************************************************
     */
    public function listarBodegas(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT b.inv_bodega_id, b.inv_bodega_codigo, b.inv_bodega_nombre
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE b.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo <> 'ELIMINADO'
            ORDER BY b.inv_bodega_codigo
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA PRODUCTOS CON SALDOS, PVP, IMAGEN Y CONTADOR DE MOVIMIENTOS.
     * ***************************************************************************
     */
    public function listarKardexGlobal(int $empresaId): array
    {
        $productos = $this->listarProductosBase($empresaId);
        $saldos = $this->listarSaldosPorProducto($empresaId);
        foreach ($productos as &$producto) {
            $productoId = (int) $producto['inv_producto_id'];
            $producto['saldos'] = $saldos[$productoId] ?? [];
        }
        unset($producto);

        return $productos;
    }

    /**
     * ***************************************************************************
     * * VERIFICA QUE UN PRODUCTO PERTENEZCA A LA EMPRESA CONSULTADA.
     * ***************************************************************************
     */
    public function productoPerteneceEmpresa(int $empresaId, int $productoId): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM inv_producto
            WHERE sis_empresa_id = :empresa_id
              AND inv_producto_id = :producto_id
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'producto_id' => $productoId]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * LISTA MOVIMIENTOS DE KARDEX DE UN PRODUCTO POR FECHAS.
     * ***************************************************************************
     */
    public function listarDetalleProducto(int $empresaId, int $productoId, ?string $desde, ?string $hasta): array
    {
        $filtroDesde = $desde ? 'AND k.inv_kardex_fecha_movimiento::date >= :desde' : '';
        $filtroHasta = $hasta ? 'AND k.inv_kardex_fecha_movimiento::date <= :hasta' : '';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                k.inv_kardex_id,
                k.inv_bodega_id,
                k.inv_kardex_tipo_movimiento,
                k.inv_kardex_documento_tipo,
                k.inv_kardex_documento_id,
                k.inv_kardex_documento_numero,
                k.inv_kardex_cantidad_entrada,
                k.inv_kardex_cantidad_salida,
                k.inv_kardex_saldo_cantidad,
                k.inv_kardex_observacion,
                k.inv_kardex_fecha_movimiento::date AS fecha,
                to_char(k.inv_kardex_fecha_movimiento, 'HH24:MI:SS') AS hora
            FROM inv_kardex k
            WHERE k.sis_empresa_id = :empresa_id
              AND k.inv_producto_id = :producto_id
              {$filtroDesde}
              {$filtroHasta}
            ORDER BY k.inv_kardex_fecha_movimiento DESC, k.inv_kardex_id DESC
        ");
        $parametros = ['empresa_id' => $empresaId, 'producto_id' => $productoId];
        if ($desde) {
            $parametros['desde'] = $desde;
        }
        if ($hasta) {
            $parametros['hasta'] = $hasta;
        }
        $sentencia->execute($parametros);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * OBTIENE PRODUCTOS BASE PARA LA CONSULTA KARDEX.
     * ***************************************************************************
     */
    private function listarProductosBase(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                p.inv_producto_id,
                p.sis_empresa_id,
                p.inv_producto_codigo_principal,
                p.inv_producto_nombre,
                COALESCE(precio.pvp, 0) AS pvp,
                COALESCE(kardex.movimientos, 0) AS movimientos,
                img.sis_archivos_id AS imagen_principal_id
            FROM inv_producto p
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            LEFT JOIN LATERAL (
                SELECT d.ven_lista_precio_detalle_valor AS pvp
                FROM ven_lista_precio lp
                INNER JOIN ven_lista_precio_detalle d ON d.ven_lista_precio_id = lp.ven_lista_precio_id
                    AND d.inv_producto_id = p.inv_producto_id
                    AND d.sis_empresa_id = p.sis_empresa_id
                    AND COALESCE(d.ven_lista_precio_detalle_estado, 1) = 1
                WHERE lp.sis_empresa_id = p.sis_empresa_id
                  AND COALESCE(lp.sis_estado, 1) = 1
                  AND COALESCE(lp.ven_lista_precio_predeterminado, 0) = 1
                ORDER BY lp.ven_lista_precio_id
                LIMIT 1
            ) precio ON TRUE
            LEFT JOIN LATERAL (
                SELECT count(*) AS movimientos
                FROM inv_kardex k
                WHERE k.sis_empresa_id = p.sis_empresa_id
                  AND k.inv_producto_id = p.inv_producto_id
            ) kardex ON TRUE
            LEFT JOIN sis_archivos img ON img.sis_empresa_id = p.sis_empresa_id
                AND img.sis_archivos_tabla = 'INV_PRODUCTO'
                AND img.sis_archivos_id_padre = p.inv_producto_id
                AND img.sis_archivos_estado = 1
                AND img.sis_archivos_principal = TRUE
            WHERE p.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo <> 'ELIMINADO'
            ORDER BY p.inv_producto_nombre
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * AGRUPA SALDOS ACTUALES POR PRODUCTO Y BODEGA.
     * ***************************************************************************
     */
    private function listarSaldosPorProducto(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT inv_producto_id, inv_bodega_id, inv_stock_cantidad_disponible
            FROM inv_stock
            WHERE sis_empresa_id = :empresa_id
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        $saldos = [];
        foreach ($sentencia->fetchAll() as $saldo) {
            $saldos[(int) $saldo['inv_producto_id']][(int) $saldo['inv_bodega_id']] = (float) $saldo['inv_stock_cantidad_disponible'];
        }

        return $saldos;
    }
}
