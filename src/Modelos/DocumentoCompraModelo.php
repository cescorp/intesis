<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use RuntimeException;

final class DocumentoCompraModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    // =========================================================================
    // LISTADO Y BÚSQUEDA
    // =========================================================================

    public function listar(int $empresaId, bool $verTodas, string $estado = ''): array
    {
        $filtroEmpresa = $verTodas ? '' : 'AND d.sis_empresa_id = :empresa_id';
        $filtroEstado  = $estado !== '' ? 'AND es.sis_estado_codigo = :estado' : '';
        $sentencia = $this->pdo()->prepare("
            SELECT d.*,
                   p.com_proveedor_razon_social,
                   p.com_proveedor_identificacion,
                   td.sis_tipo_documento_nombre AS tipo_nombre,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre,
                   e.sis_empresa_nombre_comercial,
                   b.inv_bodega_nombre
            FROM com_documento d
            INNER JOIN com_proveedor p       ON p.com_proveedor_id        = d.com_proveedor_id
            INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id  = d.sis_tipo_documento_id
            INNER JOIN sis_estado es         ON es.sis_estado_id          = d.sis_estado_id
            INNER JOIN sis_empresa e         ON e.sis_empresa_id          = d.sis_empresa_id
            INNER JOIN inv_bodega b          ON b.inv_bodega_id           = d.inv_bodega_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtroEmpresa}
            {$filtroEstado}
            ORDER BY d.fecha_crea DESC
        ");
        $params = [];
        if (!$verTodas) $params['empresa_id'] = $empresaId;
        if ($estado !== '') $params['estado']  = $estado;
        $sentencia->execute($params);
        return $sentencia->fetchAll();
    }

    public function buscar(int $documentoId): ?array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT d.*,
                   p.com_proveedor_razon_social,
                   p.com_proveedor_identificacion,
                   p.com_proveedor_tipo_identificacion,
                   td.sis_tipo_documento_nombre  AS tipo_nombre,
                   td.sis_tipo_documento_codigo  AS tipo_codigo,
                   td.sis_tipo_documento_afecta_inventario,
                   es.sis_estado_codigo,
                   es.sis_estado_nombre,
                   b.inv_bodega_nombre
            FROM com_documento d
            INNER JOIN com_proveedor p       ON p.com_proveedor_id        = d.com_proveedor_id
            INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id  = d.sis_tipo_documento_id
            INNER JOIN sis_estado es         ON es.sis_estado_id          = d.sis_estado_id
            INNER JOIN inv_bodega b          ON b.inv_bodega_id           = d.inv_bodega_id
            WHERE d.com_documento_id = :id
            LIMIT 1
        ");
        $sentencia->execute(['id' => $documentoId]);
        return $sentencia->fetch() ?: null;
    }

    public function listarDetalle(int $documentoId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT dd.*,
                   p.inv_producto_codigo_principal  AS codigo_interno,
                   p.inv_producto_nombre            AS producto_nombre,
                   m.inv_marca_nombre               AS marca_nombre,
                   iv.sis_iva_valor,
                   COALESCE(
                       NULLIF(dd.com_documento_detalle_cod_proveedor, ''),
                       cp.inv_codigo_proveedor_codigo,
                       ''
                   ) AS cod_proveedor
            FROM com_documento_detalle dd
            INNER JOIN com_documento d    ON d.com_documento_id   = dd.com_documento_id
            INNER JOIN inv_producto p     ON p.inv_producto_id    = dd.inv_producto_id
            LEFT  JOIN inv_marca m        ON m.inv_marca_id       = p.inv_marca_id
            LEFT  JOIN sis_iva iv         ON iv.sis_iva_id        = dd.sis_iva_id
            LEFT  JOIN inv_codigo_proveedor cp ON cp.inv_producto_id   = dd.inv_producto_id
                                              AND cp.com_proveedor_id  = d.com_proveedor_id
                                              AND cp.inv_codigo_proveedor_estado = 1
            WHERE dd.com_documento_id = :id
              AND dd.com_documento_detalle_estado = 1
            ORDER BY dd.com_documento_detalle_id
        ");
        $sentencia->execute(['id' => $documentoId]);
        return $sentencia->fetchAll();
    }

    // =========================================================================
    // CREAR (BORRADOR)
    // =========================================================================

    public function crear(array $cabecera, array $lineas, int $usuarioId): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            // Cabecera
            $sentencia = $pdo->prepare("
                INSERT INTO com_documento (
                    sis_empresa_id, com_proveedor_id, sis_tipo_documento_id,
                    inv_bodega_id, com_documento_numero, com_documento_fecha_emision,
                    com_documento_subtotal, com_documento_descuento,
                    com_documento_iva, com_documento_valor_total,
                    com_documento_observacion, sis_estado_id, usuario_crea
                ) VALUES (
                    :empresa_id, :proveedor_id, :tipo_id,
                    :bodega_id, :numero, :fecha_emision,
                    :subtotal, :descuento, :iva, :total,
                    :observacion, :estado_id, :usuario_crea
                ) RETURNING com_documento_id
            ");
            $sentencia->execute([
                'empresa_id'    => $cabecera['empresa_id'],
                'proveedor_id'  => $cabecera['proveedor_id'],
                'tipo_id'       => $cabecera['tipo_id'],
                'bodega_id'     => $cabecera['bodega_id'],
                'numero'        => $cabecera['numero'],
                'fecha_emision' => $cabecera['fecha_emision'],
                'subtotal'      => $cabecera['subtotal'],
                'descuento'     => $cabecera['descuento'],
                'iva'           => $cabecera['iva'],
                'total'         => $cabecera['total'],
                'observacion'   => $cabecera['observacion'] !== '' ? $cabecera['observacion'] : null,
                'estado_id'     => $this->obtenerEstadoId('BORRADOR'),
                'usuario_crea'  => $usuarioId,
            ]);
            $documentoId = (int) $sentencia->fetchColumn();

            // Detalle
            $stmtDet = $pdo->prepare("
                INSERT INTO com_documento_detalle (
                    sis_empresa_id, com_documento_id, inv_producto_id,
                    com_documento_detalle_cantidad, com_documento_detalle_precio,
                    com_documento_detalle_descuento, com_documento_detalle_total,
                    com_documento_detalle_marca_iva, sis_iva_id,
                    com_documento_detalle_pvp, com_documento_detalle_cod_proveedor,
                    usuario_crea
                ) VALUES (
                    :empresa_id, :documento_id, :producto_id,
                    :cantidad, :precio, :descuento, :total,
                    :marca_iva, :iva_id, :pvp, :cod_proveedor,
                    :usuario_crea
                )
            ");
            foreach ($lineas as $linea) {
                $stmtDet->execute([
                    'empresa_id'   => $cabecera['empresa_id'],
                    'documento_id' => $documentoId,
                    'producto_id'  => $linea['producto_id'],
                    'cantidad'     => $linea['cantidad'],
                    'precio'       => $linea['costo'],
                    'descuento'    => $linea['descuento'] ?? 0,
                    'total'        => $linea['total'],
                    'marca_iva'    => ((float) ($linea['iva_valor'] ?? 0)) > 0 ? 'S' : 'N',
                    'iva_id'       => $linea['iva_id'] ?: null,
                    'pvp'          => $linea['pvp'] ?? 0,
                    'cod_proveedor'=> $linea['cod_proveedor'] !== '' ? $linea['cod_proveedor'] : null,
                    'usuario_crea' => $usuarioId,
                ]);
            }

            $pdo->commit();
            return $documentoId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // ACTUALIZAR BORRADOR (cabecera + reemplaza líneas, conserva traza)
    // =========================================================================

    public function actualizar(int $documentoId, array $cabecera, array $lineas, int $usuarioId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            // Verificar que el documento existe, pertenece a la empresa y está en BORRADOR
            $stmtDoc = $pdo->prepare("
                SELECT d.com_documento_id, es.sis_estado_codigo
                FROM com_documento d
                INNER JOIN sis_estado es ON es.sis_estado_id = d.sis_estado_id
                WHERE d.com_documento_id = :id AND d.sis_empresa_id = :empresa_id
                FOR UPDATE
            ");
            $stmtDoc->execute(['id' => $documentoId, 'empresa_id' => $cabecera['empresa_id']]);
            $doc = $stmtDoc->fetch();
            if (!$doc) {
                throw new \RuntimeException('Documento no encontrado.');
            }
            if ($doc['sis_estado_codigo'] !== 'BORRADOR') {
                throw new \RuntimeException('Solo se pueden editar documentos en estado BORRADOR.');
            }

            // Actualizar cabecera
            $pdo->prepare("
                UPDATE com_documento
                SET com_proveedor_id          = :proveedor_id,
                    sis_tipo_documento_id      = :tipo_id,
                    inv_bodega_id              = :bodega_id,
                    com_documento_numero       = :numero,
                    com_documento_fecha_emision= :fecha_emision,
                    com_documento_subtotal     = :subtotal,
                    com_documento_descuento    = :descuento,
                    com_documento_iva          = :iva,
                    com_documento_valor_total  = :total,
                    com_documento_observacion  = :observacion,
                    usuario_modifica           = :usuario,
                    fecha_modifica             = now()
                WHERE com_documento_id = :id
            ")->execute([
                'proveedor_id'  => $cabecera['proveedor_id'],
                'tipo_id'       => $cabecera['tipo_id'],
                'bodega_id'     => $cabecera['bodega_id'],
                'numero'        => $cabecera['numero'],
                'fecha_emision' => $cabecera['fecha_emision'],
                'subtotal'      => $cabecera['subtotal'],
                'descuento'     => $cabecera['descuento'],
                'iva'           => $cabecera['iva'],
                'total'         => $cabecera['total'],
                'observacion'   => $cabecera['observacion'] !== '' ? $cabecera['observacion'] : null,
                'usuario'       => $usuarioId,
                'id'            => $documentoId,
            ]);

            // Traza: soft-delete de las líneas anteriores (estado=0)
            $pdo->prepare("
                UPDATE com_documento_detalle
                SET com_documento_detalle_estado = 0,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
                WHERE com_documento_id = :id
                  AND com_documento_detalle_estado = 1
            ")->execute(['usuario' => $usuarioId, 'id' => $documentoId]);

            // Insertar las nuevas líneas
            $stmtDet = $pdo->prepare("
                INSERT INTO com_documento_detalle (
                    sis_empresa_id, com_documento_id, inv_producto_id,
                    com_documento_detalle_cantidad, com_documento_detalle_precio,
                    com_documento_detalle_descuento, com_documento_detalle_total,
                    com_documento_detalle_marca_iva, sis_iva_id,
                    com_documento_detalle_pvp, com_documento_detalle_cod_proveedor,
                    usuario_crea
                ) VALUES (
                    :empresa_id, :documento_id, :producto_id,
                    :cantidad, :precio, :descuento, :total,
                    :marca_iva, :iva_id, :pvp, :cod_proveedor,
                    :usuario_crea
                )
            ");
            foreach ($lineas as $linea) {
                $stmtDet->execute([
                    'empresa_id'   => $cabecera['empresa_id'],
                    'documento_id' => $documentoId,
                    'producto_id'  => $linea['producto_id'],
                    'cantidad'     => $linea['cantidad'],
                    'precio'       => $linea['costo'],
                    'descuento'    => $linea['descuento'] ?? 0,
                    'total'        => $linea['total'],
                    'marca_iva'    => ((float) ($linea['iva_valor'] ?? 0)) > 0 ? 'S' : 'N',
                    'iva_id'       => $linea['iva_id'] ?: null,
                    'pvp'          => $linea['pvp'] ?? 0,
                    'cod_proveedor'=> $linea['cod_proveedor'] !== '' ? $linea['cod_proveedor'] : null,
                    'usuario_crea' => $usuarioId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // REGISTRAR (BORRADOR → REGISTRADO, afecta stock/costos/kardex/pvp)
    // =========================================================================

    public function registrar(int $documentoId, int $empresaId, int $usuarioId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            // Bloquear y validar cabecera
            $stmtDoc = $pdo->prepare("
                SELECT d.*, td.sis_tipo_documento_afecta_inventario,
                       es.sis_estado_codigo
                FROM com_documento d
                INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = d.sis_tipo_documento_id
                INNER JOIN sis_estado es          ON es.sis_estado_id         = d.sis_estado_id
                WHERE d.com_documento_id = :id AND d.sis_empresa_id = :empresa_id
                FOR UPDATE
            ");
            $stmtDoc->execute(['id' => $documentoId, 'empresa_id' => $empresaId]);
            $doc = $stmtDoc->fetch();
            if (!$doc) {
                throw new RuntimeException('Documento no encontrado.');
            }
            if ($doc['sis_estado_codigo'] !== 'BORRADOR') {
                throw new RuntimeException('Solo se pueden registrar documentos en estado BORRADOR.');
            }

            // Detalle
            $lineas = $this->listarDetalle($documentoId);
            if (empty($lineas)) {
                throw new RuntimeException('El documento no tiene lineas de detalle.');
            }

            if ($doc['sis_tipo_documento_afecta_inventario']) {
                foreach ($lineas as $linea) {
                    $this->procesarLineaStock($pdo, $doc, $linea, $usuarioId);
                }
            }

            // Upsert códigos de proveedor en inv_codigo_proveedor
            foreach ($lineas as $linea) {
                $codProv = trim((string) ($linea['cod_proveedor'] ?? ''));
                if ($codProv !== '') {
                    $this->upsertCodigoProveedor(
                        $pdo,
                        (int) $linea['inv_producto_id'],
                        (int) $doc['com_proveedor_id'],
                        $codProv,
                        $usuarioId
                    );
                }
            }

            // Actualizar PVP en lista predeterminada para todas las líneas con pvp > 0
            foreach ($lineas as $linea) {
                if ((float) $linea['com_documento_detalle_pvp'] > 0) {
                    $this->actualizarPvp($pdo, (int) $doc['sis_empresa_id'], (int) $linea['inv_producto_id'], (float) $linea['com_documento_detalle_pvp'], $usuarioId);
                }
            }

            // Cambiar estado
            $pdo->prepare("
                UPDATE com_documento
                SET sis_estado_id    = :estado_id,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
                WHERE com_documento_id = :id
            ")->execute([
                'estado_id' => $this->obtenerEstadoId('REGISTRADO'),
                'usuario'   => $usuarioId,
                'id'        => $documentoId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // ANULAR
    // =========================================================================

    public function anular(int $documentoId, int $empresaId, int $usuarioId): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $stmtDoc = $pdo->prepare("
                SELECT d.*, es.sis_estado_codigo
                FROM com_documento d
                INNER JOIN sis_estado es ON es.sis_estado_id = d.sis_estado_id
                WHERE d.com_documento_id = :id AND d.sis_empresa_id = :empresa_id
                FOR UPDATE
            ");
            $stmtDoc->execute(['id' => $documentoId, 'empresa_id' => $empresaId]);
            $doc = $stmtDoc->fetch();
            if (!$doc) {
                throw new RuntimeException('Documento no encontrado.');
            }
            if ($doc['sis_estado_codigo'] === 'ANULADO') {
                throw new RuntimeException('El documento ya esta anulado.');
            }

            // Si estaba REGISTRADO → revertir stock y kardex
            if ($doc['sis_estado_codigo'] === 'REGISTRADO') {
                $lineas = $this->listarDetalle($documentoId);
                foreach ($lineas as $linea) {
                    $this->revertirLineaStock($pdo, $doc, $linea, $usuarioId);
                }
            }

            $pdo->prepare("
                UPDATE com_documento
                SET sis_estado_id    = :estado_id,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
                WHERE com_documento_id = :id
            ")->execute([
                'estado_id' => $this->obtenerEstadoId('ANULADO'),
                'usuario'   => $usuarioId,
                'id'        => $documentoId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // LOOKUPS PARA EL FORMULARIO
    // =========================================================================

    public function buscarProveedorPorRuc(int $empresaId, string $ruc): ?array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT p.com_proveedor_id, p.com_proveedor_identificacion,
                   p.com_proveedor_tipo_identificacion, p.com_proveedor_razon_social,
                   p.com_proveedor_nombre_comercial, p.com_proveedor_telefono,
                   p.com_proveedor_email, p.com_proveedor_direccion
            FROM com_proveedor p
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id
              AND upper(p.com_proveedor_identificacion) = upper(:ruc)
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'ruc' => $ruc]);
        return $sentencia->fetch() ?: null;
    }

    public function buscarProveedores(int $empresaId, string $termino): array
    {
        $like = '%' . $termino . '%';
        $sentencia = $this->pdo()->prepare("
            SELECT p.com_proveedor_id, p.com_proveedor_identificacion,
                   p.com_proveedor_tipo_identificacion, p.com_proveedor_razon_social,
                   p.com_proveedor_nombre_comercial
            FROM com_proveedor p
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
              AND (
                  upper(p.com_proveedor_identificacion) LIKE upper(:like)
                  OR upper(p.com_proveedor_razon_social) LIKE upper(:like2)
              )
            ORDER BY p.com_proveedor_razon_social
            LIMIT 30
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'like' => $like, 'like2' => $like]);
        return $sentencia->fetchAll();
    }

    public function listarBodegasPermitidas(int $empresaId, int $usuarioId, bool $superusuario): array
    {
        $pdo = $this->pdo();
        if ($superusuario) {
            $stmt = $pdo->prepare("
                SELECT b.inv_bodega_id, b.inv_bodega_codigo, b.inv_bodega_nombre,
                       b.inv_bodega_es_principal, b.inv_bodega_virtual,
                       0 AS predeterminada
                FROM inv_bodega b
                INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
                WHERE b.sis_empresa_id = :empresa_id
                  AND es.sis_estado_codigo = 'ACTIVO'
                ORDER BY b.inv_bodega_es_principal DESC, b.inv_bodega_codigo
            ");
            $stmt->execute(['empresa_id' => $empresaId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT b.inv_bodega_id, b.inv_bodega_codigo, b.inv_bodega_nombre,
                       b.inv_bodega_es_principal, b.inv_bodega_virtual,
                       COALESCE(bu.inv_bodega_usuarios_predeterminada, FALSE) AS predeterminada
                FROM inv_bodega b
                INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
                LEFT  JOIN inv_bodega_usuarios bu ON bu.inv_bodega_id = b.inv_bodega_id
                    AND bu.sis_usuarios_id = :usuario_id
                    AND bu.sis_empresa_id  = :empresa_id
                    AND bu.inv_bodega_usuarios_estado = 1
                WHERE b.sis_empresa_id = :empresa_id2
                  AND es.sis_estado_codigo = 'ACTIVO'
                  AND (
                      b.inv_bodega_virtual = TRUE
                      OR bu.inv_bodega_id IS NOT NULL
                  )
                ORDER BY predeterminada DESC, b.inv_bodega_es_principal DESC, b.inv_bodega_codigo
            ");
            $stmt->execute(['empresa_id' => $empresaId, 'usuario_id' => $usuarioId, 'empresa_id2' => $empresaId]);
        }
        return $stmt->fetchAll();
    }

    public function listarTiposDocumento(): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT sis_tipo_documento_id, sis_tipo_documento_codigo, sis_tipo_documento_nombre,
                   sis_tipo_documento_afecta_inventario
            FROM sis_tipo_documento
            WHERE sis_tipo_documento_modulo = 'COMPRAS'
            ORDER BY sis_tipo_documento_nombre
        ");
        $sentencia->execute();
        return $sentencia->fetchAll();
    }

    public function listarIva(int $empresaId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT sis_iva_id, sis_iva_valor
            FROM sis_iva
            WHERE sis_empresa_id = :empresa_id
              AND sis_iva_estado = 1
            ORDER BY sis_iva_valor
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
    // BÚSQUEDA DE PRODUCTOS (AJAX)
    // =========================================================================

    public function buscarProductosPorCodProveedor(int $empresaId, string $termino): array
    {
        $like = '%' . $termino . '%';
        $sentencia = $this->pdo()->prepare("
            SELECT p.inv_producto_id,
                   p.inv_producto_codigo_principal  AS codigo_interno,
                   p.inv_producto_nombre,
                   p.inv_producto_costo_ultimo      AS costo,
                   COALESCE(m.inv_marca_nombre, '')  AS marca_nombre,
                   cp.inv_codigo_proveedor_codigo    AS cod_proveedor,
                   COALESCE(SUM(s.inv_stock_cantidad_disponible), 0) AS stock_total
            FROM inv_codigo_proveedor cp
            INNER JOIN inv_producto p    ON p.inv_producto_id  = cp.inv_producto_id
            LEFT  JOIN inv_marca m       ON m.inv_marca_id     = p.inv_marca_id
            LEFT  JOIN inv_stock s       ON s.inv_producto_id  = p.inv_producto_id
                                        AND s.sis_empresa_id   = :empresa_id
            INNER JOIN sis_estado es     ON es.sis_estado_id   = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id2
              AND cp.inv_codigo_proveedor_estado = 1
              AND es.sis_estado_codigo = 'ACTIVO'
              AND upper(cp.inv_codigo_proveedor_codigo) LIKE upper(:like)
            GROUP BY p.inv_producto_id, p.inv_producto_codigo_principal, p.inv_producto_nombre,
                     p.inv_producto_costo_ultimo, m.inv_marca_nombre, cp.inv_codigo_proveedor_codigo
            ORDER BY p.inv_producto_nombre
            LIMIT 50
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'empresa_id2' => $empresaId, 'like' => $like]);
        return $sentencia->fetchAll();
    }

    public function buscarProductosPorCodigo(int $empresaId, string $termino): array
    {
        $like = '%' . $termino . '%';
        $sentencia = $this->pdo()->prepare("
            SELECT p.inv_producto_id,
                   p.inv_producto_codigo_principal  AS codigo_interno,
                   p.inv_producto_nombre,
                   p.inv_producto_costo_ultimo      AS costo,
                   COALESCE(m.inv_marca_nombre, '')  AS marca_nombre,
                   COALESCE(SUM(s.inv_stock_cantidad_disponible), 0) AS stock_total
            FROM inv_producto p
            LEFT  JOIN inv_marca m  ON m.inv_marca_id    = p.inv_marca_id
            LEFT  JOIN inv_stock s  ON s.inv_producto_id = p.inv_producto_id
                                   AND s.sis_empresa_id   = :empresa_id
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE p.sis_empresa_id = :empresa_id2
              AND es.sis_estado_codigo = 'ACTIVO'
              AND (
                  upper(p.inv_producto_codigo_principal) LIKE upper(:like)
                  OR upper(p.inv_producto_nombre) LIKE upper(:like2)
              )
            GROUP BY p.inv_producto_id, p.inv_producto_codigo_principal, p.inv_producto_nombre,
                     p.inv_producto_costo_ultimo, m.inv_marca_nombre
            ORDER BY p.inv_producto_nombre
            LIMIT 50
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'empresa_id2' => $empresaId, 'like' => $like, 'like2' => $like]);
        return $sentencia->fetchAll();
    }

    // =========================================================================
    // PROCESAMIENTO INTERNO (stock, kardex, pvp)
    // =========================================================================

    private function procesarLineaStock(\PDO $pdo, array $doc, array $linea, int $usuarioId): void
    {
        $empresaId  = (int) $doc['sis_empresa_id'];
        $bodegaId   = (int) $doc['inv_bodega_id'];
        $productoId = (int) $linea['inv_producto_id'];
        $cantidad   = (float) $linea['com_documento_detalle_cantidad'];
        $costoUnit  = (float) $linea['com_documento_detalle_precio'];

        // Stock actual (FOR UPDATE)
        $stmtStock = $pdo->prepare("
            SELECT inv_stock_id, inv_stock_cantidad_disponible, inv_stock_costo_promedio
            FROM inv_stock
            WHERE sis_empresa_id = :empresa_id AND inv_bodega_id = :bodega_id AND inv_producto_id = :producto_id
            FOR UPDATE
        ");
        $stmtStock->execute(['empresa_id' => $empresaId, 'bodega_id' => $bodegaId, 'producto_id' => $productoId]);
        $stockActual = $stmtStock->fetch();

        if ($stockActual) {
            $qtdActual   = (float) $stockActual['inv_stock_cantidad_disponible'];
            $costoProm   = (float) $stockActual['inv_stock_costo_promedio'];
            $qtdNueva    = $qtdActual + $cantidad;
            $costoPromNuevo = $qtdNueva > 0
                ? (($qtdActual * $costoProm) + ($cantidad * $costoUnit)) / $qtdNueva
                : $costoUnit;

            $pdo->prepare("
                UPDATE inv_stock
                SET inv_stock_cantidad_disponible = :qty,
                    inv_stock_costo_promedio      = :costo_prom,
                    inv_stock_ultima_actualizacion = now(),
                    usuario_modifica              = :usuario,
                    fecha_modifica                = now()
                WHERE inv_stock_id = :id
            ")->execute([
                'qty'        => $qtdNueva,
                'costo_prom' => round($costoPromNuevo, 6),
                'usuario'    => $usuarioId,
                'id'         => (int) $stockActual['inv_stock_id'],
            ]);
        } else {
            $qtdNueva       = $cantidad;
            $costoPromNuevo = $costoUnit;

            $pdo->prepare("
                INSERT INTO inv_stock (
                    sis_empresa_id, inv_bodega_id, inv_producto_id,
                    inv_stock_cantidad_disponible, inv_stock_costo_promedio,
                    inv_stock_ultima_actualizacion, usuario_crea
                ) VALUES (:empresa_id, :bodega_id, :producto_id, :qty, :costo_prom, now(), :usuario)
            ")->execute([
                'empresa_id' => $empresaId,
                'bodega_id'  => $bodegaId,
                'producto_id'=> $productoId,
                'qty'        => $qtdNueva,
                'costo_prom' => round($costoPromNuevo, 6),
                'usuario'    => $usuarioId,
            ]);
        }

        // Actualizar producto: costo promedio y último costo
        $pdo->prepare("
            UPDATE inv_producto
            SET inv_producto_costo_promedio = :costo_prom,
                inv_producto_costo_ultimo   = :costo_ult,
                usuario_modifica            = :usuario,
                fecha_modifica              = now()
            WHERE inv_producto_id = :id
        ")->execute([
            'costo_prom' => round($costoPromNuevo, 6),
            'costo_ult'  => $costoUnit,
            'usuario'    => $usuarioId,
            'id'         => $productoId,
        ]);

        // Kardex
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
                :fecha, 'COMPRA',
                'COM_DOCUMENTO', :doc_id,
                :doc_numero, :cantidad_entrada,
                0, :costo_unit,
                :saldo_qty, :saldo_valor,
                :observacion, :usuario
            )
        ")->execute([
            'empresa_id'      => $empresaId,
            'bodega_id'       => $bodegaId,
            'producto_id'     => $productoId,
            'fecha'           => $doc['com_documento_fecha_emision'],
            'doc_id'          => (int) $doc['com_documento_id'],
            'doc_numero'      => $doc['com_documento_numero'],
            'cantidad_entrada'=> $cantidad,
            'costo_unit'      => $costoUnit,
            'saldo_qty'       => $qtdNueva,
            'saldo_valor'     => round($qtdNueva * $costoPromNuevo, 2),
            'observacion'     => $doc['com_documento_observacion'] ?? '',
            'usuario'         => $usuarioId,
        ]);
    }

    /**
     * Revierte el movimiento de stock/kardex de una línea al anular un doc REGISTRADO.
     * Resta la cantidad ingresada y registra un AJUSTE_OUT en kardex.
     */
    private function revertirLineaStock(\PDO $pdo, array $doc, array $linea, int $usuarioId): void
    {
        $empresaId  = (int) $doc['sis_empresa_id'];
        $bodegaId   = (int) $doc['inv_bodega_id'];
        $productoId = (int) $linea['inv_producto_id'];
        $cantidad   = (float) $linea['com_documento_detalle_cantidad'];
        $costoUnit  = (float) $linea['com_documento_detalle_precio'];

        // Stock actual (FOR UPDATE)
        $stmtStock = $pdo->prepare("
            SELECT inv_stock_id, inv_stock_cantidad_disponible, inv_stock_costo_promedio
            FROM inv_stock
            WHERE sis_empresa_id = :empresa_id AND inv_bodega_id = :bodega_id AND inv_producto_id = :producto_id
            FOR UPDATE
        ");
        $stmtStock->execute(['empresa_id' => $empresaId, 'bodega_id' => $bodegaId, 'producto_id' => $productoId]);
        $stockActual = $stmtStock->fetch();

        $qtdNueva    = $stockActual ? max(0, (float) $stockActual['inv_stock_cantidad_disponible'] - $cantidad) : 0;
        $costoProm   = $stockActual ? (float) $stockActual['inv_stock_costo_promedio'] : 0;

        if ($stockActual) {
            $pdo->prepare("
                UPDATE inv_stock
                SET inv_stock_cantidad_disponible  = :qty,
                    inv_stock_ultima_actualizacion = now(),
                    usuario_modifica               = :usuario,
                    fecha_modifica                 = now()
                WHERE inv_stock_id = :id
            ")->execute([
                'qty'     => $qtdNueva,
                'usuario' => $usuarioId,
                'id'      => (int) $stockActual['inv_stock_id'],
            ]);
        }

        // Kardex: entrada negativa → AJUSTE_OUT
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
                now(), 'AJUSTE_OUT',
                'COM_DOCUMENTO', :doc_id,
                :doc_numero, 0,
                :cantidad_salida, :costo_unit,
                :saldo_qty, :saldo_valor,
                :observacion, :usuario
            )
        ")->execute([
            'empresa_id'      => $empresaId,
            'bodega_id'       => $bodegaId,
            'producto_id'     => $productoId,
            'doc_id'          => (int) $doc['com_documento_id'],
            'doc_numero'      => $doc['com_documento_numero'],
            'cantidad_salida' => $cantidad,
            'costo_unit'      => $costoUnit,
            'saldo_qty'       => $qtdNueva,
            'saldo_valor'     => round($qtdNueva * $costoProm, 2),
            'observacion'     => 'ANULACION: ' . ($doc['com_documento_observacion'] ?? ''),
            'usuario'         => $usuarioId,
        ]);
    }

    private function actualizarPvp(\PDO $pdo, int $empresaId, int $productoId, float $pvp, int $usuarioId): void
    {
        // Buscar lista predeterminada de la empresa
        $stmtLista = $pdo->prepare("
            SELECT ven_lista_precio_id FROM ven_lista_precio
            WHERE sis_empresa_id = :empresa_id AND ven_lista_precio_predeterminado = 1 AND sis_estado = 1
            LIMIT 1
        ");
        $stmtLista->execute(['empresa_id' => $empresaId]);
        $listaId = $stmtLista->fetchColumn();
        if (!$listaId) {
            return; // No hay lista predeterminada, no se actualiza
        }

        $stmtExiste = $pdo->prepare("
            SELECT ven_lista_precio_detalle_id FROM ven_lista_precio_detalle
            WHERE ven_lista_precio_id = :lista_id AND inv_producto_id = :producto_id AND sis_empresa_id = :empresa_id
            LIMIT 1
        ");
        $stmtExiste->execute(['lista_id' => $listaId, 'producto_id' => $productoId, 'empresa_id' => $empresaId]);
        $detalleId = $stmtExiste->fetchColumn();

        if ($detalleId) {
            $pdo->prepare("
                UPDATE ven_lista_precio_detalle
                SET ven_lista_precio_detalle_valor = :pvp, user_modifica = :usuario, fecha_modifica = now()
                WHERE ven_lista_precio_detalle_id = :id
            ")->execute(['pvp' => $pvp, 'usuario' => $usuarioId, 'id' => $detalleId]);
        } else {
            $pdo->prepare("
                INSERT INTO ven_lista_precio_detalle (
                    ven_lista_precio_id, inv_producto_id, ven_lista_precio_detalle_valor,
                    sis_empresa_id, user_crea, fecha_crea, id_session, ven_lista_precio_detalle_estado
                ) VALUES (:lista_id, :producto_id, :pvp, :empresa_id, :usuario, now(), 0, 1)
            ")->execute([
                'lista_id'   => $listaId,
                'producto_id'=> $productoId,
                'pvp'        => $pvp,
                'empresa_id' => $empresaId,
                'usuario'    => $usuarioId,
            ]);
        }
    }

    /**
     * Upsert del código de proveedor para un producto:
     * - Si ya existe un registro activo para (producto, proveedor) → actualiza el código
     * - Si no existe → inserta uno nuevo
     */
    private function upsertCodigoProveedor(\PDO $pdo, int $productoId, int $proveedorId, string $codigo, int $usuarioId): void
    {
        $stmt = $pdo->prepare("
            SELECT inv_codigo_proveedor_id
            FROM inv_codigo_proveedor
            WHERE inv_producto_id          = :producto_id
              AND com_proveedor_id         = :proveedor_id
              AND inv_codigo_proveedor_estado = 1
            LIMIT 1
        ");
        $stmt->execute(['producto_id' => $productoId, 'proveedor_id' => $proveedorId]);
        $existeId = $stmt->fetchColumn();

        if ($existeId) {
            $pdo->prepare("
                UPDATE inv_codigo_proveedor
                SET inv_codigo_proveedor_codigo = :codigo,
                    usuario_modifica            = :usuario,
                    fecha_modifica              = now()
                WHERE inv_codigo_proveedor_id = :id
            ")->execute(['codigo' => $codigo, 'usuario' => $usuarioId, 'id' => $existeId]);
        } else {
            $pdo->prepare("
                INSERT INTO inv_codigo_proveedor (
                    inv_producto_id, com_proveedor_id,
                    inv_codigo_proveedor_codigo, inv_codigo_proveedor_estado,
                    usuario_crea, fecha_crea
                ) VALUES (
                    :producto_id, :proveedor_id,
                    :codigo, 1,
                    :usuario, now()
                )
            ")->execute([
                'producto_id'  => $productoId,
                'proveedor_id' => $proveedorId,
                'codigo'       => $codigo,
                'usuario'      => $usuarioId,
            ]);
        }
    }

    // =========================================================================
    // CREACIÓN DESDE SRI
    // =========================================================================

    /**
     * Crea un documento de compra a partir de datos parseados del XML del SRI.
     * El documento se crea en estado BORRADOR con origen='SRI'.
     * Todos los detalles deben tener inv_producto_id asignado (validar antes de llamar).
     *
     * @param array $cabecera {
     *   sis_empresa_id, sis_tipo_documento_id, com_proveedor_id, inv_bodega_id,
     *   com_documento_numero, com_documento_fecha, com_documento_observacion,
     *   com_documento_subtotal, com_documento_iva, com_documento_total,
     *   com_documento_clave_acceso, com_documento_num_autorizacion,
     *   com_documento_fecha_autorizacion, com_documento_ruc_emisor,
     *   com_documento_razon_emisor, usuario_id
     * }
     * @param array[] $lineas  Cada elemento con campos de detalle
     */
    public function crearDesdeSri(int $empresaId, array $cabecera, array $lineas, int $usuarioId): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $estadoId = $this->obtenerEstadoId('BORRADOR');
            if (!$estadoId) {
                throw new RuntimeException('Estado BORRADOR no encontrado.');
            }

            $stmtDoc = $pdo->prepare("
                INSERT INTO com_documento (
                    sis_empresa_id, sis_tipo_documento_id, com_proveedor_id, inv_bodega_id,
                    sis_estado_id, com_documento_numero, com_documento_fecha_emision,
                    com_documento_observacion, com_documento_subtotal,
                    com_documento_iva, com_documento_valor_total,
                    com_documento_origen,
                    com_documento_clave_acceso, com_documento_num_autorizacion,
                    com_documento_fecha_autorizacion, com_documento_ruc_emisor,
                    com_documento_razon_emisor,
                    usuario_crea, fecha_crea
                ) VALUES (
                    :empresa_id, :tipo_doc_id, :proveedor_id, :bodega_id,
                    :estado_id, :numero, :fecha_emision,
                    :observacion, :subtotal,
                    :iva, :valor_total,
                    'SRI',
                    :clave_acceso, :num_autorizacion,
                    :fecha_autorizacion, :ruc_emisor,
                    :razon_emisor,
                    :usuario, now()
                )
                RETURNING com_documento_id
            ");
            $stmtDoc->execute([
                'empresa_id'         => $empresaId,
                'tipo_doc_id'        => (int) $cabecera['sis_tipo_documento_id'],
                'proveedor_id'       => (int) $cabecera['com_proveedor_id'],
                'bodega_id'          => (int) $cabecera['inv_bodega_id'],
                'estado_id'          => $estadoId,
                'numero'             => $cabecera['com_documento_numero'],
                'fecha_emision'      => $cabecera['com_documento_fecha'],
                'observacion'        => $cabecera['com_documento_observacion'] ?? '',
                'subtotal'           => (float) $cabecera['com_documento_subtotal'],
                'iva'                => (float) $cabecera['com_documento_iva'],
                'valor_total'        => (float) $cabecera['com_documento_total'],
                'clave_acceso'       => $cabecera['com_documento_clave_acceso']       ?? null,
                'num_autorizacion'   => $cabecera['com_documento_num_autorizacion']   ?? null,
                'fecha_autorizacion' => $cabecera['com_documento_fecha_autorizacion'] ?? null,
                'ruc_emisor'         => $cabecera['com_documento_ruc_emisor']         ?? null,
                'razon_emisor'       => $cabecera['com_documento_razon_emisor']       ?? null,
                'usuario'            => $usuarioId,
            ]);
            $documentoId = (int) $stmtDoc->fetchColumn();

            $stmtDet = $pdo->prepare("
                INSERT INTO com_documento_detalle (
                    sis_empresa_id, com_documento_id, inv_producto_id,
                    com_documento_detalle_descripcion_sri,
                    com_documento_detalle_cod_proveedor,
                    com_documento_detalle_cantidad,
                    com_documento_detalle_precio,
                    com_documento_detalle_descuento,
                    com_documento_detalle_total,
                    com_documento_detalle_marca_iva,
                    sis_iva_id,
                    com_documento_detalle_pvp,
                    usuario_crea
                ) VALUES (
                    :empresa_id, :documento_id, :producto_id,
                    :descripcion_sri,
                    :cod_proveedor,
                    :cantidad,
                    :precio,
                    :descuento,
                    :total,
                    :marca_iva,
                    :iva_id,
                    :pvp,
                    :usuario
                )
            ");
            foreach ($lineas as $linea) {
                $tarivaIva = (float) ($linea['tarifa_iva'] ?? 0);
                $stmtDet->execute([
                    'empresa_id'      => $empresaId,
                    'documento_id'    => $documentoId,
                    'producto_id'     => (int) $linea['inv_producto_id'],
                    'descripcion_sri' => $linea['descripcion_sri'] ?? null,
                    'cod_proveedor'   => $linea['cod_principal']   ?? null,
                    'cantidad'        => (float) ($linea['cantidad'] ?? 1),
                    'precio'          => (float) ($linea['precio_unitario'] ?? 0),
                    'descuento'       => (float) ($linea['descuento'] ?? 0),
                    'total'           => (float) ($linea['precio_total_sin_impuesto'] ?? 0),
                    'marca_iva'       => $tarivaIva > 0 ? 'S' : 'N',
                    'iva_id'          => null,
                    'pvp'             => (float) ($linea['pvp'] ?? 0),
                    'usuario'         => $usuarioId,
                ]);
            }

            $pdo->commit();
            return $documentoId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->pdo()->prepare("
            SELECT sis_estado_id FROM sis_estado
            WHERE sis_estado_modulo = 'COMPRAS'
              AND sis_estado_entidad = 'COM_DOCUMENTO'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);
        return (int) $sentencia->fetchColumn();
    }

    private function pdo(): \PDO
    {
        return $this->conexionBaseDatos->obtener();
    }
}
