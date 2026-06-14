<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class StockModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA FILTROS DE STOCK.
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
     * * LISTA BODEGAS DE UNA EMPRESA PARA COLUMNAS DE STOCK.
     * ***************************************************************************
     */
    public function listarBodegas(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT b.inv_bodega_id, b.inv_bodega_codigo, b.inv_bodega_nombre, b.inv_bodega_virtual
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
     * * LISTA PRODUCTOS CON SALDOS, IMAGEN, PVP Y CODIGO PROVEEDOR.
     * ***************************************************************************
     */
    public function listarStockGlobal(int $empresaId): array
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
     * * LISTA PRECIOS DE UN PRODUCTO POR TODAS LAS LISTAS DE LA EMPRESA.
     * ***************************************************************************
     */
    public function listarPreciosProducto(int $empresaId, int $productoId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                lp.ven_lista_precio_descripcion,
                lp.ven_lista_precio_predeterminado,
                COALESCE(d.ven_lista_precio_detalle_valor, 0) AS precio
            FROM ven_lista_precio lp
            LEFT JOIN ven_lista_precio_detalle d ON d.ven_lista_precio_id = lp.ven_lista_precio_id
                AND d.inv_producto_id = :producto_id
                AND d.sis_empresa_id = lp.sis_empresa_id
                AND COALESCE(d.ven_lista_precio_detalle_estado, 1) = 1
            WHERE lp.sis_empresa_id = :empresa_id
              AND COALESCE(lp.sis_estado, 1) = 1
            ORDER BY COALESCE(lp.ven_lista_precio_predeterminado, 0) DESC, lp.ven_lista_precio_orden NULLS LAST, lp.ven_lista_precio_descripcion
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'producto_id' => $productoId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA CODIGOS DE PROVEEDOR DE UN PRODUCTO.
     * ***************************************************************************
     */
    public function listarCodigosProveedor(int $productoId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                cp.inv_codigo_proveedor_codigo,
                'SIN PROVEEDOR RELACIONADO' AS proveedor
            FROM inv_codigo_proveedor cp
            WHERE cp.inv_producto_id = :producto_id
              AND cp.inv_codigo_proveedor_estado = 1
            ORDER BY cp.inv_codigo_proveedor_id
        ");
        $sentencia->execute(['producto_id' => $productoId]);

        return $sentencia->fetchAll();
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
     * * BUSCA PRODUCTO POR CODIGO EN UNA EMPRESA.
     * ***************************************************************************
     */
    public function buscarProductoPorCodigo(int $empresaId, string $codigo): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM inv_producto
            WHERE sis_empresa_id = :empresa_id
              AND upper(inv_producto_codigo_principal) = upper(:codigo)
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'codigo' => $codigo]);
        $producto = $sentencia->fetch();

        return $producto ?: null;
    }

    public function cargarProductosPorEmpresa(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT inv_producto_id, inv_producto_codigo_principal, inv_producto_nombre,
                   inv_marca_id, sis_empresa_id
            FROM inv_producto
            WHERE sis_empresa_id = :empresa_id
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        $mapa = [];
        foreach ($sentencia->fetchAll() as $row) {
            $mapa[strtoupper((string) $row['inv_producto_codigo_principal'])] = $row;
        }
        return $mapa;
    }

    public function cargarBodegasPorEmpresa(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT inv_bodega_id, inv_bodega_codigo, sis_empresa_id
            FROM inv_bodega
            WHERE sis_empresa_id = :empresa_id
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        $mapa = [];
        foreach ($sentencia->fetchAll() as $row) {
            $mapa[strtoupper((string) $row['inv_bodega_codigo'])] = $row;
        }
        return $mapa;
    }

    /**
     * ***************************************************************************
     * * BUSCA BODEGA POR CODIGO EN UNA EMPRESA.
     * ***************************************************************************
     */
    public function buscarBodegaPorCodigo(int $empresaId, string $codigo): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM inv_bodega
            WHERE sis_empresa_id = :empresa_id
              AND upper(inv_bodega_codigo) = upper(:codigo)
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'codigo' => $codigo]);
        $bodega = $sentencia->fetch();

        return $bodega ?: null;
    }

    /**
     * ***************************************************************************
     * * CREA CATEGORIA GENERAL SI NO EXISTE.
     * ***************************************************************************
     */
    public function obtenerOCrearCategoriaGeneral(int $empresaId, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT inv_categoria_id
            FROM inv_categoria
            WHERE sis_empresa_id = :empresa_id
              AND upper(inv_categoria_nombre) = 'GENERAL'
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        $categoriaId = (int) $sentencia->fetchColumn();
        if ($categoriaId > 0) {
            return $categoriaId;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_categoria (
                sis_empresa_id, inv_categoria_nombre, inv_categoria_descripcion,
                sis_estado_id, usuario_crea
            )
            VALUES (:empresa_id, 'GENERAL', 'CATEGORIA GENERAL CREADA POR IMPORTACION', :estado_id, :usuario_crea)
            RETURNING inv_categoria_id
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'estado_id' => $this->obtenerEstadoId('INV_CATEGORIA', 'ACTIVO'),
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * CREA MARCA SI NO EXISTE Y DEVUELVE SU IDENTIFICADOR.
     * ***************************************************************************
     */
    public function obtenerOCrearMarca(int $empresaId, string $nombre, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT inv_marca_id
            FROM inv_marca
            WHERE sis_empresa_id = :empresa_id
              AND upper(inv_marca_nombre) = upper(:nombre)
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'nombre' => $nombre]);
        $marcaId = (int) $sentencia->fetchColumn();
        if ($marcaId > 0) {
            return $marcaId;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_marca (sis_empresa_id, inv_marca_nombre, sis_estado_id, usuario_crea)
            VALUES (:empresa_id, :nombre, :estado_id, :usuario_crea)
            RETURNING inv_marca_id
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'estado_id' => $this->obtenerEstadoId('INV_MARCA', 'ACTIVO'),
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * CREA PRODUCTO MINIMO DESDE IMPORTACION DE STOCK.
     * ***************************************************************************
     */
    public function crearProductoImportado(array $datos, int $usuarioId): int
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
                :codigo, '', :nombre, :descripcion,
                'UND', 1, :costo, :costo, 0, 0,
                :estado_id, :usuario_crea
            )
            RETURNING inv_producto_id
        ");
        $sentencia->execute([
            'empresa_id' => $datos['empresa_id'],
            'categoria_id' => $datos['categoria_id'],
            'marca_id' => $datos['marca_id'],
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'costo' => $datos['costo'],
            'estado_id' => $this->obtenerEstadoId('INV_PRODUCTO', 'ACTIVO'),
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * REGISTRA SALDO INICIAL EN STOCK, MOVIMIENTO Y KARDEX.
     * ***************************************************************************
     */
    public function registrarSaldoInicial(array $datos, int $usuarioId): array
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $resultado = $this->registrarSaldoInicialEnTransaccion($datos, $usuarioId);
            $pdo->commit();

            return $resultado;
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * REGISTRA VARIAS FILAS DE SALDO INICIAL EN UNA TRANSACCION.
     * ***************************************************************************
     */
    public function registrarSaldosImportados(array $filas, int $usuarioId): array
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $resultado = ['registradas' => 0, 'omitidas' => 0];
            foreach ($filas as $fila) {
                if (($fila['estado'] ?? '') !== 'OK') {
                    $resultado['omitidas']++;
                    continue;
                }
                $this->registrarSaldoInicialEnTransaccion($fila, $usuarioId);
                $resultado['registradas']++;
            }
            $pdo->commit();

            return $resultado;
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * REGISTRA ARCHIVO CSV DE RESPALDO EN SIS_ARCHIVOS.
     * ***************************************************************************
     */
    public function registrarArchivoStock(int $empresaId, string $archivo, string $ubicacion, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_archivos (
                sis_empresa_id, sis_archivos_archivo, sis_archivos_tabla,
                sis_archivos_id_padre, sis_archivos_estado, sis_archivos_ubicacion,
                sis_archivos_principal, sis_archivos_orden, sis_archivos_tipo,
                usuario_crea
            )
            VALUES (
                :empresa_id, :archivo, 'INV_STOCK',
                0, 1, :ubicacion,
                FALSE, 1, 'CSV',
                :usuario_crea
            )
            RETURNING sis_archivos_id
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'archivo' => $archivo,
            'ubicacion' => $ubicacion,
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    public function actualizarPvp(int $empresaId, int $productoId, float $pvp, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();

        $stmt = $pdo->prepare("
            SELECT ven_lista_precio_id FROM ven_lista_precio
            WHERE sis_empresa_id                 = :empresa_id
              AND ven_lista_precio_predeterminado = 1
              AND sis_estado                      = 1
            LIMIT 1
        ");
        $stmt->execute(['empresa_id' => $empresaId]);
        $listaId = $stmt->fetchColumn();

        if (!$listaId) {
            $pdo->prepare("
                INSERT INTO ven_lista_precio (
                    sis_empresa_id, ven_lista_precio_descripcion,
                    ven_lista_precio_predeterminado, ven_lista_precio_descuento,
                    ven_lista_precio_orden, sis_estado, id_session, user_crea, fecha_crea
                ) VALUES (:empresa_id, 'PVP', 1, 0, 1, 1, 0, :usuario, now())
            ")->execute(['empresa_id' => $empresaId, 'usuario' => $usuarioId]);
            $listaId = $pdo->lastInsertId();
        }

        $stmtExiste = $pdo->prepare("
            SELECT ven_lista_precio_detalle_id FROM ven_lista_precio_detalle
            WHERE ven_lista_precio_id = :lista_id
              AND inv_producto_id     = :producto_id
              AND sis_empresa_id      = :empresa_id
            LIMIT 1
        ");
        $stmtExiste->execute(['lista_id' => $listaId, 'producto_id' => $productoId, 'empresa_id' => $empresaId]);
        $detalleId = $stmtExiste->fetchColumn();

        if ($detalleId) {
            $pdo->prepare("
                UPDATE ven_lista_precio_detalle
                SET ven_lista_precio_detalle_valor = :pvp,
                    user_modifica = :usuario,
                    fecha_modifica = now()
                WHERE ven_lista_precio_detalle_id = :id
            ")->execute(['pvp' => $pvp, 'usuario' => $usuarioId, 'id' => $detalleId]);
        } else {
            $pdo->prepare("
                INSERT INTO ven_lista_precio_detalle (
                    ven_lista_precio_id, inv_producto_id, ven_lista_precio_detalle_valor,
                    sis_empresa_id, id_session, user_crea, fecha_crea, ven_lista_precio_detalle_estado
                ) VALUES (:lista_id, :producto_id, :pvp, :empresa_id, 0, :usuario, now(), 1)
            ")->execute([
                'lista_id'    => $listaId,
                'producto_id' => $productoId,
                'pvp'         => $pvp,
                'empresa_id'  => $empresaId,
                'usuario'     => $usuarioId,
            ]);
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE FILAS BASE DE PRODUCTOS PARA LA CONSULTA GLOBAL.
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
                p.inv_producto_costo_ultimo,
                p.inv_producto_ultima_venta,
                m.inv_marca_nombre,
                COALESCE(precio.pvp, 0) AS pvp,
                codigo.inv_codigo_proveedor_codigo,
                img.sis_archivos_id AS imagen_principal_id
            FROM inv_producto p
            INNER JOIN inv_marca m ON m.inv_marca_id = p.inv_marca_id
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
                SELECT cp.inv_codigo_proveedor_codigo
                FROM inv_codigo_proveedor cp
                WHERE cp.inv_producto_id = p.inv_producto_id
                  AND cp.inv_codigo_proveedor_estado = 1
                ORDER BY cp.inv_codigo_proveedor_id
                LIMIT 1
            ) codigo ON TRUE
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
     * * AGRUPA SALDOS POR PRODUCTO Y BODEGA.
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

    /**
     * ***************************************************************************
     * * APLICA CAMBIO DE STOCK ASUMIENDO TRANSACCION ACTIVA.
     * ***************************************************************************
     */
    private function registrarSaldoInicialEnTransaccion(array $datos, int $usuarioId): array
    {
        $stock = $this->obtenerStockParaActualizar((int) $datos['empresa_id'], (int) $datos['bodega_id'], (int) $datos['producto_id']);
        $actual = $stock ? (float) $stock['inv_stock_cantidad_disponible'] : 0.0;
        if ($datos['accion'] === 'saltar' && $stock) {
            return ['estado' => 'OMITIDA', 'diferencia' => 0];
        }

        $cantidad = (float) $datos['cantidad'];
        $nuevo = $datos['accion'] === 'sumar' ? $actual + $cantidad : $cantidad;
        $diferencia = $nuevo - $actual;

        if ($stock) {
            $sentencia = $this->conexionBaseDatos->obtener()->prepare("
                UPDATE inv_stock
                SET inv_stock_cantidad_disponible = :cantidad,
                    inv_stock_costo_promedio = :costo,
                    inv_stock_ultima_actualizacion = now(),
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE inv_stock_id = :stock_id
            ");
            $sentencia->execute([
                'stock_id' => (int) $stock['inv_stock_id'],
                'cantidad' => (string) $nuevo,
                'costo' => (string) $datos['costo'],
                'usuario_modifica' => $usuarioId,
            ]);
        } else {
            $sentencia = $this->conexionBaseDatos->obtener()->prepare("
                INSERT INTO inv_stock (
                    sis_empresa_id, inv_bodega_id, inv_producto_id,
                    inv_stock_cantidad_disponible, inv_stock_costo_promedio,
                    usuario_crea
                )
                VALUES (:empresa_id, :bodega_id, :producto_id, :cantidad, :costo, :usuario_crea)
            ");
            $sentencia->execute([
                'empresa_id' => (int) $datos['empresa_id'],
                'bodega_id' => (int) $datos['bodega_id'],
                'producto_id' => (int) $datos['producto_id'],
                'cantidad' => (string) $nuevo,
                'costo' => (string) $datos['costo'],
                'usuario_crea' => $usuarioId,
            ]);
        }

        $this->actualizarCostosProducto((int) $datos['producto_id'], (string) $datos['costo'], $usuarioId);
        $movimientoId = $this->crearMovimiento($datos, $usuarioId);
        $this->crearKardex($datos, $movimientoId, $diferencia, $nuevo, $usuarioId);

        return ['estado' => 'REGISTRADA', 'diferencia' => $diferencia];
    }

    /**
     * ***************************************************************************
     * * BLOQUEA STOCK ACTUAL PARA ACTUALIZARLO EN TRANSACCION.
     * ***************************************************************************
     */
    private function obtenerStockParaActualizar(int $empresaId, int $bodegaId, int $productoId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM inv_stock
            WHERE sis_empresa_id = :empresa_id
              AND inv_bodega_id = :bodega_id
              AND inv_producto_id = :producto_id
            FOR UPDATE
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'bodega_id' => $bodegaId,
            'producto_id' => $productoId,
        ]);
        $stock = $sentencia->fetch();

        return $stock ?: null;
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA COSTOS DEL PRODUCTO SEGUN SALDO INICIAL.
     * ***************************************************************************
     */
    private function actualizarCostosProducto(int $productoId, string $costo, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_producto
            SET inv_producto_costo_ultimo = :costo,
                inv_producto_costo_promedio = :costo,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_producto_id = :producto_id
        ");
        $sentencia->execute([
            'producto_id' => $productoId,
            'costo' => $costo,
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA CABECERA DEL MOVIMIENTO DE SALDO INICIAL.
     * ***************************************************************************
     */
    private function crearMovimiento(array $datos, int $usuarioId): int
    {
        $numero = 'SI-' . date('Ymd-His');
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_movimientos (
                sis_empresa_id, sis_tipo_documento_id, inv_movimientos_numero,
                inv_movimientos_fecha, inv_bodega_destino_id,
                inv_movimientos_referencia, inv_movimientos_observacion,
                sis_estado_id, usuario_crea
            )
            VALUES (
                :empresa_id, :tipo_documento_id, :numero,
                CURRENT_DATE, :bodega_id,
                :referencia, :observacion,
                :estado_id, :usuario_crea
            )
            RETURNING inv_movimientos_id
        ");
        $sentencia->execute([
            'empresa_id' => (int) $datos['empresa_id'],
            'tipo_documento_id' => $this->obtenerTipoDocumentoSaldoInicial(),
            'numero' => $numero,
            'bodega_id' => (int) $datos['bodega_id'],
            'referencia' => $datos['referencia'] ?? 'SALDO INICIAL',
            'observacion' => $datos['descripcion'] ?? '',
            'estado_id' => $this->obtenerEstadoId('INV_MOVIMIENTOS', 'REGISTRADO'),
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * CREA REGISTRO KARDEX DEL SALDO INICIAL.
     * ***************************************************************************
     */
    private function crearKardex(array $datos, int $movimientoId, float $diferencia, float $saldo, int $usuarioId): void
    {
        $entrada = max($diferencia, 0);
        $salida = abs(min($diferencia, 0));
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_kardex (
                sis_empresa_id, inv_bodega_id, inv_producto_id,
                inv_kardex_tipo_movimiento, inv_kardex_documento_tipo,
                inv_kardex_documento_id, inv_kardex_documento_numero,
                inv_kardex_cantidad_entrada, inv_kardex_cantidad_salida,
                inv_kardex_costo_unitario, inv_kardex_saldo_cantidad,
                inv_kardex_saldo_valor, inv_kardex_observacion,
                usuario_crea
            )
            VALUES (
                :empresa_id, :bodega_id, :producto_id,
                'SALDO_INICIAL', 'INV_MOVIMIENTOS',
                :movimiento_id, :documento_numero,
                :entrada, :salida,
                :costo, :saldo_cantidad,
                :saldo_valor, :observacion,
                :usuario_crea
            )
        ");
        $sentencia->execute([
            'empresa_id' => (int) $datos['empresa_id'],
            'bodega_id' => (int) $datos['bodega_id'],
            'producto_id' => (int) $datos['producto_id'],
            'movimiento_id' => $movimientoId,
            'documento_numero' => 'SI-' . $movimientoId,
            'entrada' => (string) $entrada,
            'salida' => (string) $salida,
            'costo' => (string) $datos['costo'],
            'saldo_cantidad' => (string) $saldo,
            'saldo_valor' => (string) ($saldo * (float) $datos['costo']),
            'observacion' => $datos['descripcion'] ?? '',
            'usuario_crea' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO CENTRALIZADO DE INVENTARIO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $entidad, string $codigo): int
    {
        $modulo = str_starts_with($entidad, 'SIS_') ? 'SISTEMA' : 'INVENTARIO';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = :modulo
              AND sis_estado_entidad = :entidad
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['modulo' => $modulo, 'entidad' => $entidad, 'codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE TIPO DOCUMENTO DE SALDO INICIAL.
     * ***************************************************************************
     */
    private function obtenerTipoDocumentoSaldoInicial(): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_tipo_documento_id
            FROM sis_tipo_documento
            WHERE sis_tipo_documento_codigo = 'SALDO_INICIAL'
            LIMIT 1
        ");
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
