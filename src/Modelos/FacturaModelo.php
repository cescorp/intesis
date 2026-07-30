<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use RuntimeException;

final class FacturaModelo
{
    public function __construct(
        private ConexionBaseDatos $conexionBaseDatos,
        private SecuenciaModelo   $secuenciaModelo
    ) {
    }

    // =========================================================================
    // LISTADO
    // =========================================================================

    public function listar(int $empresaId, bool $verTodas, string $estado = '', string $desde = '', string $hasta = ''): array
    {
        $filtroEmpresa = $verTodas ? '' : 'AND d.sis_empresa_id = :empresa_id';
        $filtroEstado  = $estado !== '' ? 'AND es.sis_estado_codigo = :estado' : '';
        $filtroDesde   = $desde  !== '' ? 'AND d.ven_documento_fecha_emision >= :desde' : '';
        $filtroHasta   = $hasta  !== '' ? 'AND d.ven_documento_fecha_emision <= :hasta' : '';

        $sentencia = $this->pdo()->prepare("
            SELECT d.*,
                   c.ven_cliente_razon_social,
                   c.ven_cliente_identificacion,
                   td.sis_tipo_documento_nombre AS tipo_nombre,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre,
                   e.sis_empresa_nombre_comercial,
                   fp.ven_forma_pago_nombre
            FROM ven_documento d
            INNER JOIN ven_cliente c         ON c.ven_cliente_id         = d.ven_cliente_id
            INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = d.sis_tipo_documento_id
            INNER JOIN sis_estado es         ON es.sis_estado_id         = d.sis_estado_id
            INNER JOIN sis_empresa e         ON e.sis_empresa_id         = d.sis_empresa_id
            LEFT  JOIN ven_forma_pago fp     ON fp.ven_forma_pago_id     = d.ven_forma_pago_id
            WHERE td.sis_tipo_documento_codigo IN ('FACTURA_VENTA', 'NOTA_VENTA')
              AND es.sis_estado_codigo <> 'ELIMINADO'
              AND d.ven_documento_proforma_id IS NULL
              {$filtroEmpresa}
              {$filtroEstado}
              {$filtroDesde}
              {$filtroHasta}
            ORDER BY d.fecha_crea DESC
        ");

        $params = [];
        if (!$verTodas)   $params['empresa_id'] = $empresaId;
        if ($estado !== '') $params['estado']   = $estado;
        if ($desde  !== '') $params['desde']    = $desde;
        if ($hasta  !== '') $params['hasta']    = $hasta;
        $sentencia->execute($params);
        return $sentencia->fetchAll();
    }

    public function buscar(int $facturaId, int $empresaId): ?array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT d.*,
                   c.ven_cliente_razon_social,
                   c.ven_cliente_identificacion,
                   c.ven_cliente_tipo_identificacion,
                   c.ven_cliente_telefono,
                   c.ven_cliente_email,
                   c.ven_cliente_direccion,
                   td.sis_tipo_documento_codigo AS tipo_codigo,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre,
                   fp.ven_forma_pago_nombre,
                   fp.ven_forma_pago_calculadora,
                   fp.ven_forma_pago_codigo_sri,
                   COALESCE(b.inv_bodega_establecimiento, '001') AS inv_bodega_establecimiento,
                   COALESCE(b.inv_bodega_punto_emision,   '001') AS inv_bodega_punto_emision
            FROM ven_documento d
            INNER JOIN ven_cliente c         ON c.ven_cliente_id         = d.ven_cliente_id
            INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = d.sis_tipo_documento_id
            INNER JOIN sis_estado es         ON es.sis_estado_id         = d.sis_estado_id
            LEFT  JOIN ven_forma_pago fp     ON fp.ven_forma_pago_id     = d.ven_forma_pago_id
            LEFT  JOIN inv_bodega b          ON b.inv_bodega_id          = d.inv_bodega_id
            WHERE d.ven_documento_id = :id
              AND d.sis_empresa_id   = :empresa_id
            LIMIT 1
        ");
        $sentencia->execute(['id' => $facturaId, 'empresa_id' => $empresaId]);
        return $sentencia->fetch() ?: null;
    }

    public function actualizarEstadoSri(int $facturaId, array $resultado, int $usuarioId): void
    {
        $estadoCodigo = $resultado['estado'] === 'AUTORIZADA' ? 'AUTORIZADA' : 'ERROR';
        $estadoId     = $this->obtenerEstadoId($estadoCodigo);

        $this->pdo()->prepare("
            UPDATE ven_documento SET
                sis_estado_id                    = :estado_id,
                ven_documento_clave_acceso        = :clave,
                ven_documento_autorizacion_sri    = :autorizacion,
                ven_documento_fecha_autorizacion  = :fecha_auth,
                ven_documento_xml_autorizado      = :xml,
                ven_documento_respuesta_sri       = :respuesta,
                ven_documento_ambiente_sri        = :ambiente,
                ven_documento_codigo_numerico     = :codigo_numerico,
                usuario_modifica                  = :usuario,
                fecha_modifica                    = now()
            WHERE ven_documento_id = :id
        ")->execute([
            'estado_id'       => $estadoId,
            'clave'           => $resultado['clave_acceso']       ?? null,
            'autorizacion'    => $resultado['autorizacion']        ?: null,
            'fecha_auth'      => $resultado['fecha_autorizacion']  ?: null,
            'xml'             => $resultado['xml_autorizado']      ?: null,
            'respuesta'       => $resultado['respuesta_sri']       ?? null,
            'ambiente'        => $resultado['ambiente']            ?? null,
            'codigo_numerico' => $resultado['codigo_numerico']     ?? null,
            'usuario'         => $usuarioId,
            'id'              => $facturaId,
        ]);
    }

    public function listarDetalle(int $facturaId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT dd.*,
                   p.inv_producto_codigo_principal AS codigo_interno,
                   p.inv_producto_nombre           AS producto_nombre,
                   iv.sis_iva_valor,
                   COALESCE(s.inv_stock_cantidad_disponible, 0) AS stock_disponible
            FROM ven_documento_detalle dd
            INNER JOIN inv_producto p ON p.inv_producto_id = dd.inv_producto_id
            LEFT  JOIN sis_iva iv     ON iv.sis_iva_id     = dd.sis_iva_id
            LEFT  JOIN inv_stock s    ON s.inv_producto_id = dd.inv_producto_id
                                     AND s.sis_empresa_id  = dd.sis_empresa_id
            WHERE dd.ven_documento_id          = :id
              AND dd.ven_documento_detalle_estado = 1
            ORDER BY dd.ven_documento_detalle_id
        ");
        $sentencia->execute(['id' => $facturaId]);
        return $sentencia->fetchAll();
    }

    // =========================================================================
    // CREAR
    // =========================================================================

    public function crear(array $cabecera, array $lineas, int $usuarioId, bool $ventaTodasBodegas = false): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $empresaId = (int) $cabecera['empresa_id'];
            $tipoCodigo = $cabecera['tipo_doc'] === 'NOTA_VENTA' ? 'NOTA_VENTA' : 'FACTURA_VENTA';
            $bodegaId  = $this->obtenerBodegaId($pdo, $empresaId, $usuarioId);
            $numero    = $this->secuenciaModelo->obtenerSiguiente($pdo, $empresaId, $tipoCodigo, 'VENTAS', $bodegaId);

            $this->validarStock($pdo, $empresaId, $lineas, $ventaTodasBodegas ? null : $bodegaId);

            $stmt = $pdo->prepare("
                INSERT INTO ven_documento (
                    sis_empresa_id, ven_cliente_id, sis_tipo_documento_id,
                    sis_usuarios_id, inv_bodega_id,
                    ven_documento_numero, ven_documento_fecha_emision,
                    ven_documento_subtotal, ven_documento_descuento,
                    ven_documento_iva, ven_documento_total,
                    ven_documento_subtotal_sin_impuestos,
                    ven_documento_impuesto_total,
                    ven_documento_valor_total,
                    ven_forma_pago_id,
                    ven_documento_observacion, sis_estado_id, usuario_crea
                ) VALUES (
                    :empresa_id, :cliente_id, :tipo_id,
                    :usuarios_id, :bodega_id,
                    :numero, :fecha_emision,
                    :subtotal, :descuento, :iva, :total,
                    :subtotal_sin_impuestos, :impuesto_total, :valor_total,
                    :forma_pago_id,
                    :observacion, :estado_id, :usuario_crea
                ) RETURNING ven_documento_id
            ");
            $stmt->execute([
                'empresa_id'             => $empresaId,
                'cliente_id'             => $cabecera['cliente_id'],
                'tipo_id'                => $this->obtenerTipoDocumentoId($tipoCodigo),
                'usuarios_id'            => $usuarioId,
                'bodega_id'              => $bodegaId,
                'numero'                 => $numero,
                'fecha_emision'          => $cabecera['fecha_emision'] !== '' ? $cabecera['fecha_emision'] : date('Y-m-d'),
                'subtotal'               => $cabecera['subtotal'],
                'descuento'              => $cabecera['descuento'],
                'iva'                    => $cabecera['iva'],
                'total'                  => $cabecera['total'],
                'subtotal_sin_impuestos' => $cabecera['subtotal'],
                'impuesto_total'         => $cabecera['iva'],
                'valor_total'            => $cabecera['total'],
                'forma_pago_id'          => $cabecera['forma_pago_id'],
                'observacion'            => $cabecera['observacion'] !== '' ? $cabecera['observacion'] : null,
                'estado_id'              => $this->obtenerEstadoId('CREADA'),
                'usuario_crea'           => $usuarioId,
            ]);
            $facturaId = (int) $stmt->fetchColumn();

            $this->insertarLineasYDescontarStock($pdo, $facturaId, $empresaId, $bodegaId, $numero, $lineas, $usuarioId);

            $this->generarCxcSiAplica($pdo, $facturaId, $empresaId, (int) $cabecera['cliente_id'], $cabecera['forma_pago_id'], $cabecera['total'], $usuarioId);

            $pdo->commit();
            return $facturaId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // ACTUALIZAR (solo estado CREADA)
    // =========================================================================

    public function actualizar(int $facturaId, array $cabecera, array $lineas, int $usuarioId, bool $ventaTodasBodegas = false): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $doc = $this->bloquearDocumento($pdo, $facturaId, (int) $cabecera['empresa_id']);
            if ($doc['sis_estado_codigo'] !== 'CREADA') {
                throw new RuntimeException('Solo se pueden editar facturas en estado CREADA.');
            }

            $this->revertirStockYKardex($pdo, $facturaId, $usuarioId);

            $empresaId = (int) $cabecera['empresa_id'];
            $bodegaId  = $this->obtenerBodegaId($pdo, $empresaId, $usuarioId);
            $this->validarStock($pdo, $empresaId, $lineas, $ventaTodasBodegas ? null : $bodegaId);

            $pdo->prepare("
                UPDATE ven_documento
                SET ven_cliente_id              = :cliente_id,
                    ven_documento_fecha_emision = :fecha_emision,
                    ven_documento_subtotal      = :subtotal,
                    ven_documento_descuento     = :descuento,
                    ven_documento_iva           = :iva,
                    ven_documento_total         = :total,
                    ven_documento_subtotal_sin_impuestos = :subtotal,
                    ven_documento_impuesto_total         = :iva,
                    ven_documento_valor_total            = :total,
                    ven_forma_pago_id           = :forma_pago_id,
                    ven_documento_observacion   = :observacion,
                    usuario_modifica            = :usuario,
                    fecha_modifica              = now()
                WHERE ven_documento_id = :id
            ")->execute([
                'cliente_id'    => $cabecera['cliente_id'],
                'fecha_emision' => $cabecera['fecha_emision'] !== '' ? $cabecera['fecha_emision'] : null,
                'subtotal'      => $cabecera['subtotal'],
                'descuento'     => $cabecera['descuento'],
                'iva'           => $cabecera['iva'],
                'total'         => $cabecera['total'],
                'forma_pago_id' => $cabecera['forma_pago_id'],
                'observacion'   => $cabecera['observacion'] !== '' ? $cabecera['observacion'] : null,
                'usuario'       => $usuarioId,
                'id'            => $facturaId,
            ]);

            $pdo->prepare("
                UPDATE ven_documento_detalle
                SET ven_documento_detalle_estado = 0, usuario_modifica = :u, fecha_modifica = now()
                WHERE ven_documento_id = :id AND ven_documento_detalle_estado = 1
            ")->execute(['u' => $usuarioId, 'id' => $facturaId]);

            $numero = $doc['ven_documento_numero'];
            $this->insertarLineasYDescontarStock($pdo, $facturaId, $empresaId, $bodegaId, $numero, $lineas, $usuarioId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // ANULAR
    // =========================================================================

    public function anular(int $facturaId, int $empresaId, int $usuarioId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $doc = $this->bloquearDocumento($pdo, $facturaId, $empresaId);
            if ($doc['sis_estado_codigo'] !== 'CREADA') {
                throw new RuntimeException('Solo se pueden anular facturas en estado CREADA.');
            }

            $this->revertirStockYKardex($pdo, $facturaId, $usuarioId);

            $pdo->prepare("
                UPDATE ven_documento
                SET sis_estado_id    = :estado_id,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
                WHERE ven_documento_id = :id
            ")->execute([
                'estado_id' => $this->obtenerEstadoId('ANULADA'),
                'usuario'   => $usuarioId,
                'id'        => $facturaId,
            ]);

            $pdo->prepare("
                UPDATE ven_cxc
                SET ven_cxc_estado    = 'A',
                    usuario_modifica  = :usuario,
                    fecha_modifica    = now()
                WHERE ven_documento_id = :id AND ven_cxc_estado = 'P'
            ")->execute(['usuario' => $usuarioId, 'id' => $facturaId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    public function listarFormasPago(int $empresaId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT ven_forma_pago_id, ven_forma_pago_nombre, ven_forma_pago_calculadora
            FROM ven_forma_pago
            WHERE sis_empresa_id = :empresa_id AND ven_forma_pago_estado = 'A'
            ORDER BY ven_forma_pago_nombre
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        return $sentencia->fetchAll();
    }

    public function listarEmpresasActivas(bool $verTodas, int $empresaId): array
    {
        $filtro = $verTodas ? '' : 'AND e.sis_empresa_id = :empresa_id';
        $sentencia = $this->pdo()->prepare("
            SELECT e.sis_empresa_id, e.sis_empresa_nombre_comercial
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE es.sis_estado_codigo = 'ACTIVO' {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial
        ");
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);
        return $sentencia->fetchAll();
    }

    public function listarIva(int $empresaId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT sis_iva_id, sis_iva_valor
            FROM sis_iva
            WHERE sis_empresa_id = :empresa_id AND sis_iva_estado = 1
            ORDER BY sis_iva_valor
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        return $sentencia->fetchAll();
    }

    public function buscarClientePorRuc(int $empresaId, string $ruc): ?array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT c.ven_cliente_id, c.ven_cliente_identificacion,
                   c.ven_cliente_tipo_identificacion, c.ven_cliente_razon_social,
                   c.ven_cliente_telefono, c.ven_cliente_email, c.ven_cliente_direccion
            FROM ven_cliente c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.sis_empresa_id = :empresa_id
              AND upper(c.ven_cliente_identificacion) = upper(:ruc)
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'ruc' => $ruc]);
        return $sentencia->fetch() ?: null;
    }

    public function buscarClientes(int $empresaId, string $termino): array
    {
        $like = '%' . $termino . '%';
        $sentencia = $this->pdo()->prepare("
            SELECT c.ven_cliente_id, c.ven_cliente_identificacion,
                   c.ven_cliente_tipo_identificacion, c.ven_cliente_razon_social,
                   c.ven_cliente_nombre_comercial,
                   c.ven_cliente_telefono, c.ven_cliente_email
            FROM ven_cliente c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
              AND (upper(c.ven_cliente_identificacion) LIKE upper(:like)
                OR upper(c.ven_cliente_razon_social)   LIKE upper(:like2))
            ORDER BY c.ven_cliente_razon_social
            LIMIT 30
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'like' => $like, 'like2' => $like]);
        return $sentencia->fetchAll();
    }

    public function buscarProductos(int $empresaId, string $termino, ?int $bodegaId = null): array
    {
        $like = '%' . $termino . '%';
        $filtroBodegaStock = $bodegaId !== null ? 'AND s.inv_bodega_id = :bodega_id' : '';
        $sentencia = $this->pdo()->prepare("
            SELECT p.inv_producto_id,
                   p.inv_producto_codigo_principal AS codigo_interno,
                   p.inv_producto_nombre,
                   COALESCE(m.inv_marca_nombre, '') AS marca_nombre,
                   COALESCE(SUM(s.inv_stock_cantidad_disponible), 0) AS stock_total,
                   COALESCE(lpd.ven_lista_precio_detalle_valor, 0)   AS pvp,
                   COALESCE(lp.ven_lista_precio_descuento, 0)        AS descuento_max,
                   COALESCE(iv.sis_iva_id, NULL)  AS sis_iva_id,
                   COALESCE(iv.sis_iva_valor, 0)  AS sis_iva_valor
            FROM inv_producto p
            LEFT JOIN inv_marca m     ON m.inv_marca_id    = p.inv_marca_id
            LEFT JOIN inv_stock s     ON s.inv_producto_id = p.inv_producto_id
                                     AND s.sis_empresa_id  = :empresa_id
                                     {$filtroBodegaStock}
            LEFT JOIN ven_lista_precio lp ON lp.sis_empresa_id = :empresa_id2
                                         AND lp.ven_lista_precio_predeterminado = 1
                                         AND lp.sis_estado = 1
            LEFT JOIN ven_lista_precio_detalle lpd ON lpd.ven_lista_precio_id = lp.ven_lista_precio_id
                                                   AND lpd.inv_producto_id    = p.inv_producto_id
                                                   AND lpd.sis_empresa_id     = :empresa_id3
                                                   AND COALESCE(lpd.ven_lista_precio_detalle_estado, 1) = 1
            LEFT JOIN sis_iva iv ON iv.sis_empresa_id = :empresa_id4
                                 AND iv.sis_iva_estado = 1 AND iv.sis_iva_valor > 0
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id5
              AND es.sis_estado_codigo = 'ACTIVO'
              AND (upper(p.inv_producto_codigo_principal) LIKE upper(:like)
                OR upper(p.inv_producto_nombre)           LIKE upper(:like2))
            GROUP BY p.inv_producto_id, m.inv_marca_nombre,
                     lpd.ven_lista_precio_detalle_valor, lp.ven_lista_precio_descuento,
                     iv.sis_iva_id, iv.sis_iva_valor
            ORDER BY p.inv_producto_nombre
            LIMIT 30
        ");
        $parametros = [
            'empresa_id'  => $empresaId, 'empresa_id2' => $empresaId,
            'empresa_id3' => $empresaId, 'empresa_id4' => $empresaId,
            'empresa_id5' => $empresaId,
            'like' => $like, 'like2' => $like,
        ];
        if ($bodegaId !== null) {
            $parametros['bodega_id'] = $bodegaId;
        }
        $sentencia->execute($parametros);
        return $sentencia->fetchAll();
    }

    public function obtenerDatosEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo()->prepare("
            SELECT sis_empresa_ruc, sis_empresa_razon_social,
                   sis_empresa_nombre_comercial, sis_empresa_direccion,
                   sis_empresa_telefono, sis_empresa_email,
                   sis_empresa_ambiente_sri,
                   sis_empresa_certificado_ruta,
                   sis_empresa_certificado_clave,
                   sis_empresa_num_contribuyente_especial
            FROM sis_empresa WHERE sis_empresa_id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $empresaId]);
        return $stmt->fetch() ?: [];
    }

    public function obtenerConfigDescuento(int $empresaId): array
    {
        $stmt = $this->pdo()->prepare("
            SELECT sis_empresa_formula_descuento AS formula,
                   sis_empresa_descuento_maximo_facturas AS max_facturas,
                   sis_empresa_descuento_maximo_notas_venta AS max_notas_venta
            FROM sis_empresa WHERE sis_empresa_id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $empresaId]);
        $fila = $stmt->fetch();

        return [
            'formula' => $fila['formula'] ?? 'C',
            'max_facturas' => (float) ($fila['max_facturas'] ?? 0),
            'max_notas_venta' => (float) ($fila['max_notas_venta'] ?? 0),
        ];
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function validarStock(\PDO $pdo, int $empresaId, array $lineas, ?int $bodegaId = null): void
    {
        $filtroBodega = $bodegaId !== null ? 'AND inv_bodega_id = :bodega_id' : '';
        foreach ($lineas as $linea) {
            $productoId = (int) $linea['producto_id'];
            $cantidad   = (float) $linea['cantidad'];

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(inv_stock_cantidad_disponible), 0) AS stock
                FROM inv_stock
                WHERE sis_empresa_id = :empresa_id AND inv_producto_id = :producto_id
                {$filtroBodega}
            ");
            $parametros = ['empresa_id' => $empresaId, 'producto_id' => $productoId];
            if ($bodegaId !== null) {
                $parametros['bodega_id'] = $bodegaId;
            }
            $stmt->execute($parametros);
            $stock = (float) $stmt->fetchColumn();

            if ($stock < $cantidad) {
                $nombre = $linea['descripcion'] ?: "Producto #$productoId";
                throw new RuntimeException("Stock insuficiente para '{$nombre}'. Disponible: {$stock}, solicitado: {$cantidad}.");
            }
        }
    }

    private function insertarLineasYDescontarStock(\PDO $pdo, int $facturaId, int $empresaId, int $bodegaId, string $numero, array $lineas, int $usuarioId): void
    {
        $stmtDet = $pdo->prepare("
            INSERT INTO ven_documento_detalle (
                sis_empresa_id, ven_documento_id, inv_producto_id,
                ven_documento_detalle_codigo, ven_documento_detalle_descripcion,
                ven_documento_detalle_cantidad,
                ven_documento_detalle_precio_unitario,
                ven_documento_detalle_descuento, ven_documento_detalle_descuento_val,
                ven_documento_detalle_precio_total_sin_impuestos,
                ven_documento_detalle_impuesto_total,
                ven_documento_detalle_precio_total,
                ven_documento_detalle_marca_iva,
                sis_iva_id, ven_documento_detalle_iva_valor,
                ven_documento_detalle_pvp, ven_documento_detalle_precio_min,
                usuario_crea
            ) VALUES (
                :empresa_id, :doc_id, :producto_id,
                :codigo, :descripcion,
                :cantidad, :precio_unitario,
                :descuento, :descuento_val,
                :base, :impuesto_total, :precio_total,
                :marca_iva, :iva_id, :iva_valor,
                :pvp, :precio_min, :usuario_crea
            )
        ");

        foreach ($lineas as $linea) {
            $productoId   = (int) $linea['producto_id'];
            $cantidad     = (float) $linea['cantidad'];
            $precio       = (float) $linea['precio'];
            $descPct      = (float) ($linea['descuento'] ?? 0);
            $descVal      = (float) ($linea['descuento_valor'] ?? 0);
            $base         = (float) $linea['total'];
            $impuesto     = (float) ($linea['iva_valor'] ?? 0);
            $precioTotal  = round($base + $impuesto, 4);

            $stmtDet->execute([
                'empresa_id'    => $empresaId,
                'doc_id'        => $facturaId,
                'producto_id'   => $productoId > 0 ? $productoId : throw new RuntimeException('Línea sin producto.'),
                'codigo'        => $linea['codigo'] !== '' ? $linea['codigo'] : null,
                'descripcion'   => $linea['descripcion'] !== '' ? $linea['descripcion'] : null,
                'cantidad'      => $cantidad,
                'precio_unitario' => $precio,
                'descuento'     => $descPct,
                'descuento_val' => $descVal,
                'base'          => $base,
                'impuesto_total'=> $impuesto,
                'precio_total'  => $precioTotal,
                'marca_iva'     => $impuesto > 0 ? 'S' : 'N',
                'iva_id'        => ((int)($linea['iva_id'] ?? 0)) > 0 ? (int)$linea['iva_id'] : null,
                'iva_valor'     => $impuesto,
                'pvp'           => (float)($linea['pvp'] ?? 0),
                'precio_min'    => (float)($linea['precio_min'] ?? 0),
                'usuario_crea'  => $usuarioId,
            ]);

            $this->descontarStock($pdo, $empresaId, $bodegaId, $productoId, $cantidad, $precio, $facturaId, $numero, $usuarioId);
        }
    }

    private function descontarStock(\PDO $pdo, int $empresaId, int $bodegaId, int $productoId, float $cantidad, float $costoUnit, int $facturaId, string $numero, int $usuarioId): void
    {
        $stmtStock = $pdo->prepare("
            SELECT inv_stock_id, inv_stock_cantidad_disponible, inv_stock_costo_promedio
            FROM inv_stock
            WHERE sis_empresa_id = :e AND inv_bodega_id = :b AND inv_producto_id = :p
            FOR UPDATE
        ");
        $stmtStock->execute(['e' => $empresaId, 'b' => $bodegaId, 'p' => $productoId]);
        $stock = $stmtStock->fetch();

        $qtdNueva   = $stock ? max(0, (float)$stock['inv_stock_cantidad_disponible'] - $cantidad) : 0;
        $costoProm  = $stock ? (float)$stock['inv_stock_costo_promedio'] : $costoUnit;
        $saldoValor = round($qtdNueva * $costoProm, 2);

        if ($stock) {
            $pdo->prepare("
                UPDATE inv_stock
                SET inv_stock_cantidad_disponible = :qty,
                    usuario_modifica = :u, fecha_modifica = now()
                WHERE inv_stock_id = :id
            ")->execute(['qty' => $qtdNueva, 'u' => $usuarioId, 'id' => $stock['inv_stock_id']]);
        }

        $pdo->prepare("
            INSERT INTO inv_kardex (
                sis_empresa_id, inv_bodega_id, inv_producto_id,
                inv_kardex_fecha_movimiento, inv_kardex_tipo_movimiento,
                inv_kardex_documento_tipo, inv_kardex_documento_id,
                inv_kardex_documento_numero, inv_kardex_cantidad_entrada,
                inv_kardex_cantidad_salida, inv_kardex_costo_unitario,
                inv_kardex_saldo_cantidad, inv_kardex_saldo_valor,
                inv_kardex_observacion, usuario_crea
            ) VALUES (
                :empresa_id, :bodega_id, :producto_id,
                now(), 'VENTA',
                'VEN_FACTURA', :doc_id,
                :doc_numero, 0,
                :cantidad_salida, :costo_unit,
                :saldo_qty, :saldo_valor,
                'Factura de venta', :usuario
            )
        ")->execute([
            'empresa_id'      => $empresaId,
            'bodega_id'       => $bodegaId,
            'producto_id'     => $productoId,
            'doc_id'          => $facturaId,
            'doc_numero'      => $numero,
            'cantidad_salida' => $cantidad,
            'costo_unit'      => $costoProm,
            'saldo_qty'       => $qtdNueva,
            'saldo_valor'     => $saldoValor,
            'usuario'         => $usuarioId,
        ]);
    }

    private function revertirStockYKardex(\PDO $pdo, int $facturaId, int $usuarioId): void
    {
        $lineas = $pdo->prepare("
            SELECT dd.inv_producto_id, dd.ven_documento_detalle_cantidad,
                   dd.ven_documento_detalle_precio_unitario,
                   d.sis_empresa_id, d.inv_bodega_id
            FROM ven_documento_detalle dd
            INNER JOIN ven_documento d ON d.ven_documento_id = dd.ven_documento_id
            WHERE dd.ven_documento_id = :id AND dd.ven_documento_detalle_estado = 1
        ");
        $lineas->execute(['id' => $facturaId]);

        foreach ($lineas->fetchAll() as $l) {
            $empresaId  = (int) $l['sis_empresa_id'];
            $bodegaId   = (int) $l['inv_bodega_id'];
            $productoId = (int) $l['inv_producto_id'];
            $cantidad   = (float) $l['ven_documento_detalle_cantidad'];
            $costo      = (float) $l['ven_documento_detalle_precio_unitario'];

            $stmtStock = $pdo->prepare("
                SELECT inv_stock_id, inv_stock_cantidad_disponible, inv_stock_costo_promedio
                FROM inv_stock
                WHERE sis_empresa_id = :e AND inv_bodega_id = :b AND inv_producto_id = :p
                FOR UPDATE
            ");
            $stmtStock->execute(['e' => $empresaId, 'b' => $bodegaId, 'p' => $productoId]);
            $stock = $stmtStock->fetch();

            $qtdNueva = $stock ? (float)$stock['inv_stock_cantidad_disponible'] + $cantidad : $cantidad;
            $costoProm = $stock ? (float)$stock['inv_stock_costo_promedio'] : $costo;

            if ($stock) {
                $pdo->prepare("
                    UPDATE inv_stock SET inv_stock_cantidad_disponible = :qty,
                    usuario_modifica = :u, fecha_modifica = now()
                    WHERE inv_stock_id = :id
                ")->execute(['qty' => $qtdNueva, 'u' => $usuarioId, 'id' => $stock['inv_stock_id']]);
            }

            $pdo->prepare("
                INSERT INTO inv_kardex (
                    sis_empresa_id, inv_bodega_id, inv_producto_id,
                    inv_kardex_fecha_movimiento, inv_kardex_tipo_movimiento,
                    inv_kardex_documento_tipo, inv_kardex_documento_id,
                    inv_kardex_documento_numero, inv_kardex_cantidad_entrada,
                    inv_kardex_cantidad_salida, inv_kardex_costo_unitario,
                    inv_kardex_saldo_cantidad, inv_kardex_saldo_valor,
                    inv_kardex_observacion, usuario_crea
                ) VALUES (
                    :e, :b, :p, now(), 'AJUSTE_IN',
                    'VEN_FACTURA', :doc_id, 'ANULACION',
                    :entrada, 0, :costo,
                    :saldo_qty, :saldo_valor, 'Reversión anulación factura', :u
                )
            ")->execute([
                'e' => $empresaId, 'b' => $bodegaId, 'p' => $productoId,
                'doc_id'  => $facturaId,
                'entrada' => $cantidad, 'costo' => $costoProm,
                'saldo_qty'   => $qtdNueva,
                'saldo_valor' => round($qtdNueva * $costoProm, 2),
                'u' => $usuarioId,
            ]);
        }
    }

    private function generarCxcSiAplica(\PDO $pdo, int $facturaId, int $empresaId, int $clienteId, int $formaPagoId, float $total, int $usuarioId): void
    {
        $stmt = $pdo->prepare("
            SELECT ven_forma_pago_solicitar_datos FROM ven_forma_pago
            WHERE ven_forma_pago_id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $formaPagoId]);
        $fp = $stmt->fetch();

        // Solo genera CXC si la forma de pago solicita datos (ej. Crédito) pero no es Efectivo
        $nombre = $pdo->prepare("SELECT ven_forma_pago_nombre FROM ven_forma_pago WHERE ven_forma_pago_id = :id LIMIT 1");
        $nombre->execute(['id' => $formaPagoId]);
        $nombreFp = strtoupper((string)$nombre->fetchColumn());

        if (!in_array($nombreFp, ['EFECTIVO', 'TARJETA DE CRÉDITO', 'TARJETA DE DEBITO', 'TRANSFERENCIA'], true)) {
            $pdo->prepare("
                INSERT INTO ven_cxc (
                    sis_empresa_id, ven_documento_id, ven_cliente_id,
                    ven_cxc_monto, ven_cxc_saldo,
                    ven_cxc_fecha_emision, ven_cxc_estado, usuario_crea
                ) VALUES (
                    :empresa_id, :doc_id, :cliente_id,
                    :monto, :monto,
                    CURRENT_DATE, 'P', :usuario
                )
            ")->execute([
                'empresa_id' => $empresaId,
                'doc_id'     => $facturaId,
                'cliente_id' => $clienteId,
                'monto'      => $total,
                'usuario'    => $usuarioId,
            ]);
        }
    }

    private function bloquearDocumento(\PDO $pdo, int $facturaId, int $empresaId): array
    {
        $stmt = $pdo->prepare("
            SELECT d.ven_documento_id, d.ven_documento_numero, es.sis_estado_codigo
            FROM ven_documento d
            INNER JOIN sis_estado es ON es.sis_estado_id = d.sis_estado_id
            WHERE d.ven_documento_id = :id AND d.sis_empresa_id = :empresa_id
            FOR UPDATE
        ");
        $stmt->execute(['id' => $facturaId, 'empresa_id' => $empresaId]);
        $doc = $stmt->fetch();
        if (!$doc) throw new RuntimeException('Factura no encontrada.');
        return $doc;
    }

    public function obtenerBodegaUsuario(int $empresaId, int $usuarioId): int
    {
        return $this->obtenerBodegaId($this->pdo(), $empresaId, $usuarioId);
    }

    private function obtenerBodegaId(\PDO $pdo, int $empresaId, int $usuarioId): int
    {
        $stmt = $pdo->prepare("
            SELECT bu.inv_bodega_id
            FROM inv_bodega_usuarios bu
            INNER JOIN inv_bodega b ON b.inv_bodega_id = bu.inv_bodega_id
            WHERE bu.sis_empresa_id = :empresa_id AND bu.sis_usuarios_id = :usuario_id
              AND bu.inv_bodega_usuarios_estado = 1 AND b.inv_bodega_virtual = FALSE
              AND bu.inv_bodega_usuarios_predeterminada = TRUE
            LIMIT 1
        ");
        $stmt->execute(['empresa_id' => $empresaId, 'usuario_id' => $usuarioId]);
        $id = (int) $stmt->fetchColumn();
        if ($id > 0) return $id;

        $stmt2 = $pdo->prepare("
            SELECT inv_bodega_id FROM inv_bodega
            WHERE sis_empresa_id = :empresa_id AND inv_bodega_virtual = FALSE
            ORDER BY inv_bodega_id LIMIT 1
        ");
        $stmt2->execute(['empresa_id' => $empresaId]);
        $id = (int) $stmt2->fetchColumn();
        if ($id === 0) throw new RuntimeException('No hay bodega configurada para esta empresa.');
        return $id;
    }

    private function obtenerTipoDocumentoId(string $codigo = 'FACTURA_VENTA'): int
    {
        $stmt = $this->pdo()->prepare("
            SELECT sis_tipo_documento_id FROM sis_tipo_documento
            WHERE sis_tipo_documento_codigo = :codigo
              AND sis_tipo_documento_modulo = 'VENTAS'
            LIMIT 1
        ");
        $stmt->execute(['codigo' => $codigo]);
        $id = (int) $stmt->fetchColumn();
        if ($id === 0) throw new RuntimeException("Tipo '{$codigo}' no configurado. Ejecute la migración.");
        return $id;
    }

    private function obtenerEstadoId(string $codigo): int
    {
        $stmt = $this->pdo()->prepare("
            SELECT sis_estado_id FROM sis_estado
            WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_DOCUMENTO'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $stmt->execute(['codigo' => $codigo]);
        $id = (int) $stmt->fetchColumn();
        if ($id === 0) throw new RuntimeException("Estado '{$codigo}' no configurado. Ejecute la migración.");
        return $id;
    }

    private function pdo(): \PDO
    {
        return $this->conexionBaseDatos->obtener();
    }
}
