<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use RuntimeException;

final class ProformaModelo
{
    public function __construct(
        private ConexionBaseDatos $conexionBaseDatos,
        private SecuenciaModelo $secuenciaModelo
    ) {
    }

    // =========================================================================
    // LISTADO Y BÚSQUEDA
    // =========================================================================

    public function listar(int $empresaId, bool $verTodas, string $estado = '', string $desde = '', string $hasta = ''): array
    {
        $filtroEmpresa = $verTodas ? '' : 'AND d.sis_empresa_id = :empresa_id';
        $filtroEstado  = $estado !== '' ? 'AND es.sis_estado_codigo = :estado' : '';
        $filtroDesde   = $desde !== '' ? 'AND d.ven_documento_fecha_emision >= :desde' : '';
        $filtroHasta   = $hasta !== '' ? 'AND d.ven_documento_fecha_emision <= :hasta' : '';

        $sentencia = $this->pdo()->prepare("
            SELECT d.*,
                   c.ven_cliente_razon_social,
                   c.ven_cliente_identificacion,
                   td.sis_tipo_documento_nombre AS tipo_nombre,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre,
                   e.sis_empresa_nombre_comercial
            FROM ven_documento d
            INNER JOIN ven_cliente c          ON c.ven_cliente_id          = d.ven_cliente_id
            INNER JOIN sis_tipo_documento td  ON td.sis_tipo_documento_id  = d.sis_tipo_documento_id
            INNER JOIN sis_estado es          ON es.sis_estado_id          = d.sis_estado_id
            INNER JOIN sis_empresa e          ON e.sis_empresa_id          = d.sis_empresa_id
            WHERE td.sis_tipo_documento_codigo = 'PROFORMA'
              AND es.sis_estado_codigo <> 'ELIMINADO'
              {$filtroEmpresa}
              {$filtroEstado}
              {$filtroDesde}
              {$filtroHasta}
            ORDER BY d.fecha_crea DESC
        ");
        $params = [];
        if (!$verTodas) $params['empresa_id'] = $empresaId;
        if ($estado !== '') $params['estado']  = $estado;
        if ($desde  !== '') $params['desde']   = $desde;
        if ($hasta  !== '') $params['hasta']   = $hasta;
        $sentencia->execute($params);
        return $sentencia->fetchAll();
    }

    public function buscar(int $proformaId, int $empresaId): ?array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT d.*,
                   c.ven_cliente_razon_social,
                   c.ven_cliente_identificacion,
                   c.ven_cliente_tipo_identificacion,
                   c.ven_cliente_telefono,
                   c.ven_cliente_email,
                   td.sis_tipo_documento_nombre AS tipo_nombre,
                   td.sis_tipo_documento_codigo AS tipo_codigo,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre
            FROM ven_documento d
            INNER JOIN ven_cliente c          ON c.ven_cliente_id         = d.ven_cliente_id
            INNER JOIN sis_tipo_documento td  ON td.sis_tipo_documento_id = d.sis_tipo_documento_id
            INNER JOIN sis_estado es          ON es.sis_estado_id         = d.sis_estado_id
            WHERE d.ven_documento_id = :id
              AND d.sis_empresa_id   = :empresa_id
            LIMIT 1
        ");
        $sentencia->execute(['id' => $proformaId, 'empresa_id' => $empresaId]);
        return $sentencia->fetch() ?: null;
    }

    public function listarDetalle(int $proformaId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT dd.*,
                   p.inv_producto_codigo_principal AS codigo_interno,
                   p.inv_producto_nombre           AS producto_nombre,
                   iv.sis_iva_valor
            FROM ven_documento_detalle dd
            INNER JOIN inv_producto p ON p.inv_producto_id = dd.inv_producto_id
            LEFT  JOIN sis_iva iv     ON iv.sis_iva_id     = dd.sis_iva_id
            WHERE dd.ven_documento_id = :id
              AND dd.ven_documento_detalle_estado = 1
            ORDER BY dd.ven_documento_detalle_id
        ");
        $sentencia->execute(['id' => $proformaId]);
        return $sentencia->fetchAll();
    }

    // =========================================================================
    // CREAR
    // =========================================================================

    public function crear(array $cabecera, array $lineas, int $usuarioId): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $numero = $this->secuenciaModelo->obtenerSiguiente($pdo, (int) $cabecera['empresa_id'], 'PROFORMA', 'VENTAS');

            $sentencia = $pdo->prepare("
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
                    :subtotal_sin_impuestos,
                    :impuesto_total,
                    :valor_total,
                    :forma_pago_id,
                    :observacion, :estado_id, :usuario_crea
                ) RETURNING ven_documento_id
            ");
            $sentencia->execute([
                'empresa_id'             => $cabecera['empresa_id'],
                'cliente_id'             => $cabecera['cliente_id'],
                'tipo_id'                => $this->obtenerTipoDocumentoId('PROFORMA'),
                'usuarios_id'            => $usuarioId,
                'bodega_id'              => $this->obtenerBodegaId((int) $cabecera['empresa_id'], $usuarioId),
                'numero'                 => $numero,
                'fecha_emision'          => $cabecera['fecha_emision'] !== '' ? $cabecera['fecha_emision'] : date('Y-m-d'),
                'subtotal'               => $cabecera['subtotal'],
                'descuento'              => $cabecera['descuento'],
                'iva'                    => $cabecera['iva'],
                'total'                  => $cabecera['total'],
                'subtotal_sin_impuestos' => $cabecera['subtotal'],
                'impuesto_total'         => $cabecera['iva'],
                'valor_total'            => $cabecera['total'],
                'forma_pago_id'          => (int) $cabecera['forma_pago_id'],
                'observacion'            => $cabecera['observacion'] !== '' ? $cabecera['observacion'] : null,
                'estado_id'              => $this->obtenerEstadoId('CREADA'),
                'usuario_crea'           => $usuarioId,
            ]);
            $proformaId = (int) $sentencia->fetchColumn();

            $this->insertarLineas($pdo, $proformaId, (int) $cabecera['empresa_id'], $lineas, $usuarioId);

            $pdo->commit();
            return $proformaId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // ACTUALIZAR (solo en estado CREADA)
    // =========================================================================

    public function actualizar(int $proformaId, array $cabecera, array $lineas, int $usuarioId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $stmtDoc = $pdo->prepare("
                SELECT d.ven_documento_id, es.sis_estado_codigo
                FROM ven_documento d
                INNER JOIN sis_estado es ON es.sis_estado_id = d.sis_estado_id
                WHERE d.ven_documento_id = :id AND d.sis_empresa_id = :empresa_id
                FOR UPDATE
            ");
            $stmtDoc->execute(['id' => $proformaId, 'empresa_id' => $cabecera['empresa_id']]);
            $doc = $stmtDoc->fetch();
            if (!$doc) {
                throw new RuntimeException('Proforma no encontrada.');
            }
            if ($doc['sis_estado_codigo'] !== 'CREADA') {
                throw new RuntimeException('Solo se pueden editar proformas en estado CREADA.');
            }

            $pdo->prepare("
                UPDATE ven_documento
                SET ven_cliente_id              = :cliente_id,
                    ven_documento_fecha_emision = :fecha_emision,
                    ven_documento_subtotal      = :subtotal,
                    ven_documento_descuento     = :descuento,
                    ven_documento_iva           = :iva,
                    ven_documento_total         = :total,
                    ven_documento_observacion   = :observacion,
                    ven_forma_pago_id           = :forma_pago_id,
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
                'observacion'   => $cabecera['observacion'] !== '' ? $cabecera['observacion'] : null,
                'forma_pago_id' => (int) $cabecera['forma_pago_id'],
                'usuario'       => $usuarioId,
                'id'            => $proformaId,
            ]);

            // Soft-delete líneas anteriores (traza)
            $pdo->prepare("
                UPDATE ven_documento_detalle
                SET ven_documento_detalle_estado = 0,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
                WHERE ven_documento_id = :id
                  AND ven_documento_detalle_estado = 1
            ")->execute(['usuario' => $usuarioId, 'id' => $proformaId]);

            $this->insertarLineas($pdo, $proformaId, (int) $cabecera['empresa_id'], $lineas, $usuarioId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // ANULAR
    // =========================================================================

    public function anular(int $proformaId, int $empresaId, int $usuarioId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $stmtDoc = $pdo->prepare("
                SELECT d.ven_documento_id, es.sis_estado_codigo
                FROM ven_documento d
                INNER JOIN sis_estado es ON es.sis_estado_id = d.sis_estado_id
                WHERE d.ven_documento_id = :id AND d.sis_empresa_id = :empresa_id
                FOR UPDATE
            ");
            $stmtDoc->execute(['id' => $proformaId, 'empresa_id' => $empresaId]);
            $doc = $stmtDoc->fetch();
            if (!$doc) {
                throw new RuntimeException('Proforma no encontrada.');
            }
            if ($doc['sis_estado_codigo'] !== 'CREADA') {
                throw new RuntimeException('Solo se pueden anular proformas en estado CREADA.');
            }

            $pdo->prepare("
                UPDATE ven_documento
                SET sis_estado_id    = :estado_id,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
                WHERE ven_documento_id = :id
            ")->execute([
                'estado_id' => $this->obtenerEstadoId('ANULADA'),
                'usuario'   => $usuarioId,
                'id'        => $proformaId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // FACTURAR (parcial o total)
    // =========================================================================

    /**
     * Genera una FACTURA_VENTA a partir de una proforma.
     * $lineas = [['detalle_id' => N, 'cantidad' => X], ...]
     * donde cantidad = 0 significa omitir esa línea.
     *
     * @return int ID de la factura generada
     */
    public function facturar(int $proformaId, int $empresaId, array $lineas, int $usuarioId): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            // Bloquear y validar proforma
            $stmtProf = $pdo->prepare("
                SELECT d.*, es.sis_estado_codigo, c.ven_cliente_id AS cli_id
                FROM ven_documento d
                INNER JOIN sis_estado es ON es.sis_estado_id = d.sis_estado_id
                INNER JOIN ven_cliente c  ON c.ven_cliente_id = d.ven_cliente_id
                WHERE d.ven_documento_id = :id AND d.sis_empresa_id = :empresa_id
                FOR UPDATE
            ");
            $stmtProf->execute(['id' => $proformaId, 'empresa_id' => $empresaId]);
            $proforma = $stmtProf->fetch();
            if (!$proforma) {
                throw new RuntimeException('Proforma no encontrada.');
            }
            if ($proforma['sis_estado_codigo'] !== 'CREADA') {
                throw new RuntimeException('Solo se pueden facturar proformas en estado CREADA.');
            }

            // Cargar detalle original para validación
            $stmtDet = $pdo->prepare("
                SELECT * FROM ven_documento_detalle
                WHERE ven_documento_id = :id
                  AND ven_documento_detalle_estado = 1
            ");
            $stmtDet->execute(['id' => $proformaId]);
            $detalleOriginal = [];
            foreach ($stmtDet->fetchAll() as $row) {
                $detalleOriginal[(int) $row['ven_documento_detalle_id']] = $row;
            }

            // Filtrar líneas a facturar y validar cantidades
            $lineasFactura = [];
            foreach ($lineas as $linea) {
                $detalleId = (int) ($linea['detalle_id'] ?? 0);
                $cantidad  = (float) ($linea['cantidad'] ?? 0);
                if ($cantidad <= 0) {
                    continue;
                }
                if (!isset($detalleOriginal[$detalleId])) {
                    throw new RuntimeException("Línea {$detalleId} no pertenece a esta proforma.");
                }
                $cantMax = (float) $detalleOriginal[$detalleId]['ven_documento_detalle_cantidad'];
                if ($cantidad > $cantMax) {
                    throw new RuntimeException("La cantidad a facturar ({$cantidad}) supera la cantidad proformada ({$cantMax}).");
                }
                $lineasFactura[] = ['original' => $detalleOriginal[$detalleId], 'cantidad' => $cantidad];
            }

            if (empty($lineasFactura)) {
                throw new RuntimeException('Debe indicar al menos una línea con cantidad mayor a cero para facturar.');
            }

            // Calcular totales de la factura
            $subtotal  = 0.0;
            $descuento = 0.0;
            $ivaTotal  = 0.0;
            foreach ($lineasFactura as $lf) {
                $orig     = $lf['original'];
                $cant     = $lf['cantidad'];
                $precio   = (float) $orig['ven_documento_detalle_precio'];
                $dto      = (float) $orig['ven_documento_detalle_descuento'];
                $dtoVal   = round($precio * $cant * $dto / 100, 4);
                $lineTotal = round($precio * $cant - $dtoVal, 4);
                $ivaPorc  = 0.0;
                if ($orig['ven_documento_detalle_marca_iva'] === 'S' && $orig['sis_iva_id']) {
                    $stmtIva = $pdo->prepare("SELECT sis_iva_valor FROM sis_iva WHERE sis_iva_id = :id LIMIT 1");
                    $stmtIva->execute(['id' => $orig['sis_iva_id']]);
                    $ivaPorc = (float) ($stmtIva->fetchColumn() ?: 0);
                }
                $ivaLinea = round($lineTotal * $ivaPorc / 100, 4);
                $subtotal  += $lineTotal;
                $descuento += $dtoVal;
                $ivaTotal  += $ivaLinea;
            }
            $total = round($subtotal + $ivaTotal, 4);

            // Número de factura
            $numero = $this->secuenciaModelo->obtenerSiguiente($pdo, $empresaId, 'FACTURA_VENTA', 'VENTAS');

            // Insertar cabecera de factura
            $stmtFact = $pdo->prepare("
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
                    ven_documento_proforma_id,
                    sis_estado_id, usuario_crea
                ) VALUES (
                    :empresa_id, :cliente_id, :tipo_id,
                    :usuarios_id, :bodega_id,
                    :numero, :fecha_emision,
                    :subtotal, :descuento, :iva, :total,
                    :subtotal_sin_impuestos,
                    :impuesto_total,
                    :valor_total,
                    :forma_pago_id,
                    :proforma_id,
                    :estado_id, :usuario_crea
                ) RETURNING ven_documento_id
            ");
            $stmtFact->execute([
                'empresa_id'             => $empresaId,
                'cliente_id'             => $proforma['ven_cliente_id'],
                'tipo_id'                => $this->obtenerTipoDocumentoId('FACTURA_VENTA'),
                'usuarios_id'            => $usuarioId,
                'bodega_id'              => $this->obtenerBodegaId($empresaId, $usuarioId),
                'numero'                 => $numero,
                'fecha_emision'          => date('Y-m-d'),
                'subtotal'               => round($subtotal, 4),
                'descuento'              => round($descuento, 4),
                'iva'                    => round($ivaTotal, 4),
                'total'                  => $total,
                'subtotal_sin_impuestos' => round($subtotal, 4),
                'impuesto_total'         => round($ivaTotal, 4),
                'valor_total'            => $total,
                'forma_pago_id'          => $proforma['ven_forma_pago_id'] ?? $this->obtenerFormaPagoId($empresaId, 'EFECTIVO'),
                'proforma_id'            => $proformaId,
                'estado_id'              => $this->obtenerEstadoId('CREADA'),
                'usuario_crea'           => $usuarioId,
            ]);
            $facturaId = (int) $stmtFact->fetchColumn();

            // Insertar líneas de la factura
            $stmtDetFact = $pdo->prepare("
                INSERT INTO ven_documento_detalle (
                    sis_empresa_id, ven_documento_id, inv_producto_id,
                    ven_documento_detalle_codigo, ven_documento_detalle_descripcion,
                    ven_documento_detalle_cantidad, ven_documento_detalle_precio,
                    ven_documento_detalle_descuento, ven_documento_detalle_descuento_val,
                    ven_documento_detalle_total, ven_documento_detalle_marca_iva,
                    sis_iva_id, ven_documento_detalle_iva_valor,
                    ven_documento_detalle_pvp, ven_documento_detalle_precio_min,
                    ven_documento_detalle_aprobado_por,
                    ven_documento_detalle_proforma_linea_id,
                    usuario_crea
                ) VALUES (
                    :empresa_id, :documento_id, :producto_id,
                    :codigo, :descripcion,
                    :cantidad, :precio,
                    :descuento, :descuento_val,
                    :total, :marca_iva,
                    :iva_id, :iva_valor,
                    :pvp, :precio_min,
                    :aprobado_por,
                    :proforma_linea_id,
                    :usuario_crea
                )
            ");
            foreach ($lineasFactura as $lf) {
                $orig    = $lf['original'];
                $cant    = $lf['cantidad'];
                $precio  = (float) $orig['ven_documento_detalle_precio'];
                $dto     = (float) $orig['ven_documento_detalle_descuento'];
                $dtoVal  = round($precio * $cant * $dto / 100, 4);
                $lineTot = round($precio * $cant - $dtoVal, 4);
                $ivaPorc = 0.0;
                if ($orig['ven_documento_detalle_marca_iva'] === 'S' && $orig['sis_iva_id']) {
                    $stmtIva = $pdo->prepare("SELECT sis_iva_valor FROM sis_iva WHERE sis_iva_id = :id LIMIT 1");
                    $stmtIva->execute(['id' => $orig['sis_iva_id']]);
                    $ivaPorc = (float) ($stmtIva->fetchColumn() ?: 0);
                }
                $ivaLinea = round($lineTot * $ivaPorc / 100, 4);

                $stmtDetFact->execute([
                    'empresa_id'        => $empresaId,
                    'documento_id'      => $facturaId,
                    'producto_id'       => $orig['inv_producto_id'],
                    'codigo'            => $orig['ven_documento_detalle_codigo'],
                    'descripcion'       => $orig['ven_documento_detalle_descripcion'],
                    'cantidad'          => $cant,
                    'precio'            => $precio,
                    'descuento'         => $dto,
                    'descuento_val'     => $dtoVal,
                    'total'             => $lineTot,
                    'marca_iva'         => $orig['ven_documento_detalle_marca_iva'],
                    'iva_id'            => $orig['sis_iva_id'] ?: null,
                    'iva_valor'         => $ivaLinea,
                    'pvp'               => $orig['ven_documento_detalle_pvp'],
                    'precio_min'        => $orig['ven_documento_detalle_precio_min'],
                    'aprobado_por'      => $orig['ven_documento_detalle_aprobado_por'] ?: null,
                    'proforma_linea_id' => (int) $orig['ven_documento_detalle_id'],
                    'usuario_crea'      => $usuarioId,
                ]);
            }

            // Cambiar estado proforma a FACTURADA
            $pdo->prepare("
                UPDATE ven_documento
                SET sis_estado_id    = :estado_id,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
                WHERE ven_documento_id = :id
            ")->execute([
                'estado_id' => $this->obtenerEstadoId('FACTURADA'),
                'usuario'   => $usuarioId,
                'id'        => $proformaId,
            ]);

            $pdo->commit();
            return $facturaId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // LOOKUPS PARA EL FORMULARIO
    // =========================================================================

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
                   c.ven_cliente_nombre_comercial
            FROM ven_cliente c
            INNER JOIN sis_estado es ON es.sis_estado_id = c.sis_estado_id
            WHERE c.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
              AND (
                  upper(c.ven_cliente_identificacion) LIKE upper(:like)
                  OR upper(c.ven_cliente_razon_social) LIKE upper(:like2)
              )
            ORDER BY c.ven_cliente_razon_social
            LIMIT 30
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'like' => $like, 'like2' => $like]);
        return $sentencia->fetchAll();
    }

    public function buscarProductos(int $empresaId, string $termino): array
    {
        $like = '%' . $termino . '%';
        $sentencia = $this->pdo()->prepare("
            SELECT p.inv_producto_id,
                   p.inv_producto_codigo_principal AS codigo_interno,
                   p.inv_producto_nombre,
                   COALESCE(m.inv_marca_nombre, '') AS marca_nombre,
                   COALESCE(SUM(s.inv_stock_cantidad_disponible), 0) AS stock_total,
                   COALESCE(lpd.ven_lista_precio_detalle_valor, 0) AS pvp,
                   COALESCE(lp.ven_lista_precio_descuento, 0)      AS descuento_max,
                   COALESCE(iv.sis_iva_id, NULL) AS sis_iva_id,
                   COALESCE(iv.sis_iva_valor, 0) AS sis_iva_valor,
                   img.sis_archivos_id AS imagen_principal_id
            FROM inv_producto p
            LEFT  JOIN inv_marca m    ON m.inv_marca_id    = p.inv_marca_id
            LEFT  JOIN inv_stock s    ON s.inv_producto_id = p.inv_producto_id
                                     AND s.sis_empresa_id  = :empresa_id
            LEFT  JOIN ven_lista_precio lp ON lp.sis_empresa_id = :empresa_id2
                                          AND lp.ven_lista_precio_predeterminado = 1
                                          AND lp.sis_estado = 1
            LEFT  JOIN ven_lista_precio_detalle lpd ON lpd.ven_lista_precio_id = lp.ven_lista_precio_id
                                                    AND lpd.inv_producto_id = p.inv_producto_id
                                                    AND lpd.sis_empresa_id  = :empresa_id3
                                                    AND COALESCE(lpd.ven_lista_precio_detalle_estado, 1) = 1
            LEFT  JOIN sis_iva iv ON iv.sis_empresa_id = :empresa_id4
                                  AND iv.sis_iva_estado = 1
                                  AND iv.sis_iva_valor > 0
            LEFT  JOIN sis_archivos img ON img.sis_empresa_id      = p.sis_empresa_id
                                       AND img.sis_archivos_tabla   = 'INV_PRODUCTO'
                                       AND img.sis_archivos_id_padre= p.inv_producto_id
                                       AND img.sis_archivos_estado  = 1
                                       AND img.sis_archivos_principal = TRUE
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id5
              AND es.sis_estado_codigo = 'ACTIVO'
              AND (
                  upper(p.inv_producto_codigo_principal) LIKE upper(:like)
                  OR upper(p.inv_producto_nombre) LIKE upper(:like2)
              )
            GROUP BY p.inv_producto_id, p.inv_producto_codigo_principal, p.inv_producto_nombre,
                     m.inv_marca_nombre, lpd.ven_lista_precio_detalle_valor,
                     lp.ven_lista_precio_descuento, iv.sis_iva_id, iv.sis_iva_valor,
                     img.sis_archivos_id
            ORDER BY p.inv_producto_nombre
            LIMIT 50
        ");
        $sentencia->execute([
            'empresa_id'  => $empresaId,
            'empresa_id2' => $empresaId,
            'empresa_id3' => $empresaId,
            'empresa_id4' => $empresaId,
            'empresa_id5' => $empresaId,
            'like'        => $like,
            'like2'       => $like,
        ]);
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

    public function listarFormasPago(int $empresaId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT ven_forma_pago_id, ven_forma_pago_nombre
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

    // =========================================================================
    // VERIFICACIÓN DE APROBACIÓN DE PRECIO
    // =========================================================================

    /**
     * Verifica si un usuario tiene permiso para aprobar precios por debajo del mínimo.
     * Retorna el ID del usuario si es válido, null si no.
     */
    public function verificarAprobador(int $empresaId, string $usuario, string $clave): ?int
    {
        $sentencia = $this->pdo()->prepare("
            SELECT u.sis_usuarios_id, u.sis_usuarios_clave, p.sis_perfil_id, p.sis_empresa_id
            FROM sis_usuarios u
            INNER JOIN sis_perfil p     ON p.sis_perfil_id  = u.sis_perfil_id
            INNER JOIN sis_estado es    ON es.sis_estado_id = u.sis_estado_id
            WHERE upper(u.sis_usuarios_usuario) = upper(:usuario)
              AND u.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ");
        $sentencia->execute(['usuario' => $usuario, 'empresa_id' => $empresaId]);
        $row = $sentencia->fetch();
        if (!$row) {
            return null;
        }
        if (!password_verify($clave, (string) $row['sis_usuarios_clave'])) {
            return null;
        }
        // Verificar permiso
        $stmtPerm = $this->pdo()->prepare("
            SELECT 1
            FROM sis_perfil_permisos pp
            INNER JOIN sis_menu sm ON sm.sis_menu_id = pp.sis_menu_id
            WHERE pp.sis_perfil_id  = :perfil_id
              AND pp.sis_empresa_id = :empresa_id
              AND sm.sis_menu_url   = '/ventas/proformas/aprobar-precio'
            LIMIT 1
        ");
        $stmtPerm->execute(['perfil_id' => $row['sis_perfil_id'], 'empresa_id' => $empresaId]);
        if (!$stmtPerm->fetchColumn()) {
            return null;
        }
        return (int) $row['sis_usuarios_id'];
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function insertarLineas(\PDO $pdo, int $documentoId, int $empresaId, array $lineas, int $usuarioId): void
    {
        $stmt = $pdo->prepare("
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
                ven_documento_detalle_aprobado_por,
                usuario_crea
            ) VALUES (
                :empresa_id, :documento_id, :producto_id,
                :codigo, :descripcion,
                :cantidad,
                :precio_unitario,
                :descuento, :descuento_val,
                :precio_total_sin_impuestos,
                :impuesto_total,
                :precio_total,
                :marca_iva,
                :iva_id, :iva_valor,
                :pvp, :precio_min,
                :aprobado_por,
                :usuario_crea
            )
        ");
        foreach ($lineas as $linea) {
            $cantidad    = (float) $linea['cantidad'];
            $precio      = (float) $linea['precio'];
            $descPct     = (float) ($linea['descuento'] ?? 0);
            $descVal     = (float) ($linea['descuento_valor'] ?? 0);
            $ivaVal      = (float) ($linea['iva_valor'] ?? 0);
            $base        = (float) $linea['total'];          // total ya viene sin IVA
            $impuesto    = round($base * ((float)($linea['iva_pct'] ?? 0)) / 100, 4);
            // iva_valor que viene del JS es el monto de IVA de la línea
            $impuestoTotal = (float) ($linea['iva_valor'] ?? 0);
            $precioTotal   = round($base + $impuestoTotal, 4);

            $stmt->execute([
                'empresa_id'                  => $empresaId,
                'documento_id'                => $documentoId,
                'producto_id'                 => ((int) $linea['producto_id']) > 0 ? (int) $linea['producto_id'] : throw new \RuntimeException('Todas las líneas deben tener un producto seleccionado.'),
                'codigo'                      => $linea['codigo'] !== '' ? $linea['codigo'] : null,
                'descripcion'                 => $linea['descripcion'] !== '' ? $linea['descripcion'] : null,
                'cantidad'                    => $cantidad,
                'precio_unitario'             => $precio,
                'descuento'                   => $descPct,
                'descuento_val'               => $descVal,
                'precio_total_sin_impuestos'  => $base,
                'impuesto_total'              => $impuestoTotal,
                'precio_total'                => $precioTotal,
                'marca_iva'                   => $impuestoTotal > 0 ? 'S' : 'N',
                'iva_id'                      => ((int) ($linea['iva_id'] ?? 0)) > 0 ? (int) $linea['iva_id'] : null,
                'iva_valor'                   => $ivaVal,
                'pvp'                         => (float) ($linea['pvp'] ?? 0),
                'precio_min'                  => (float) ($linea['precio_min'] ?? 0),
                'aprobado_por'                => ((int) ($linea['aprobado_por'] ?? 0)) > 0 ? (int) $linea['aprobado_por'] : null,
                'usuario_crea'                => $usuarioId,
            ]);
        }
    }

    public function obtenerDatosEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo()->prepare("
            SELECT sis_empresa_ruc, sis_empresa_razon_social,
                   sis_empresa_nombre_comercial, sis_empresa_direccion,
                   sis_empresa_telefono, sis_empresa_email
            FROM sis_empresa WHERE sis_empresa_id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $empresaId]);
        return $stmt->fetch() ?: [];
    }

    private function obtenerFormaPagoId(int $empresaId, string $nombre): int
    {
        $sentencia = $this->pdo()->prepare("
            SELECT ven_forma_pago_id FROM ven_forma_pago
            WHERE sis_empresa_id        = :empresa_id
              AND ven_forma_pago_nombre = :nombre
              AND ven_forma_pago_estado = 'A'
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'nombre' => $nombre]);
        $id = (int) $sentencia->fetchColumn();
        if ($id === 0) {
            throw new RuntimeException("Forma de pago '{$nombre}' no configurada para la empresa. Ejecute la migración.");
        }
        return $id;
    }

    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->pdo()->prepare("
            SELECT sis_estado_id FROM sis_estado
            WHERE sis_estado_modulo  = 'VENTAS'
              AND sis_estado_entidad = 'VEN_DOCUMENTO'
              AND sis_estado_codigo  = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);
        $id = (int) $sentencia->fetchColumn();
        if ($id === 0) {
            throw new RuntimeException("Estado '{$codigo}' no configurado para VEN_DOCUMENTO. Ejecute la migración.");
        }
        return $id;
    }

    private function obtenerTipoDocumentoId(string $codigo): int
    {
        $sentencia = $this->pdo()->prepare("
            SELECT sis_tipo_documento_id FROM sis_tipo_documento
            WHERE sis_tipo_documento_codigo  = :codigo
              AND sis_tipo_documento_modulo  = 'VENTAS'
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);
        $id = (int) $sentencia->fetchColumn();
        if ($id === 0) {
            throw new RuntimeException("Tipo de documento '{$codigo}' no encontrado. Ejecute la migración.");
        }
        return $id;
    }

    /**
     * ***************************************************************************
     * * OBTIENE LA BODEGA PREDETERMINADA DEL USUARIO O LA PRINCIPAL DE LA EMPRESA.
     * ***************************************************************************
     */
    private function obtenerBodegaId(int $empresaId, int $usuarioId): int
    {
        /* Bodega predeterminada asignada al usuario */
        $sentencia = $this->pdo()->prepare("
            SELECT bu.inv_bodega_id
            FROM inv_bodega_usuarios bu
            INNER JOIN inv_bodega b ON b.inv_bodega_id = bu.inv_bodega_id
            WHERE bu.sis_empresa_id = :empresa_id
              AND bu.sis_usuarios_id = :usuario_id
              AND bu.inv_bodega_usuarios_estado = 1
              AND b.inv_bodega_virtual = FALSE
            ORDER BY bu.inv_bodega_usuarios_predeterminada DESC, bu.inv_bodega_id ASC
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'usuario_id' => $usuarioId]);
        $id = (int) $sentencia->fetchColumn();
        if ($id > 0) {
            return $id;
        }

        /* Fallback: bodega principal de la empresa */
        $sentencia = $this->pdo()->prepare("
            SELECT inv_bodega_id FROM inv_bodega
            WHERE sis_empresa_id = :empresa_id
              AND inv_bodega_es_principal = TRUE
              AND inv_bodega_virtual = FALSE
            ORDER BY inv_bodega_id ASC
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        $id = (int) $sentencia->fetchColumn();
        if ($id > 0) {
            return $id;
        }

        throw new \RuntimeException('No hay una bodega activa configurada para este usuario. Configure una en Inventario → Bodegas.');
    }

    private function pdo(): \PDO
    {
        return $this->conexionBaseDatos->obtener();
    }
}
