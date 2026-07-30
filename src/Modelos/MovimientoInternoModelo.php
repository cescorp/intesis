<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class MovimientoInternoModelo
{
    public function __construct(
        private ConexionBaseDatos $conexionBaseDatos,
        private SecuenciaModelo $secuenciaModelo
    ) {
    }

    /**
     * ***************************************************************************
     * * LISTA MOVIMIENTOS INTERNOS DE UNA EMPRESA.
     * ***************************************************************************
     */
    public function listar(int $empresaId, ?array $bodegasDestinoPermitidas = null): array
    {
        $filtroBodega = '';
        $parametros = ['empresa_id' => $empresaId];
        if ($bodegasDestinoPermitidas !== null) {
            if (!$bodegasDestinoPermitidas) {
                return [];
            }
            $marcadores = [];
            foreach (array_values($bodegasDestinoPermitidas) as $indice => $bodegaId) {
                $clave = "bodega_destino_{$indice}";
                $marcadores[] = ":{$clave}";
                $parametros[$clave] = $bodegaId;
            }
            /* El destino de AJUSTE vive por linea en inv_movimientos_detalle (el
               encabezado inv_movimientos.inv_bodega_destino_id solo se llena en
               TRANSFERENCIA), por eso se filtra contra el detalle. */
            $filtroBodega = ' AND EXISTS (
                SELECT 1 FROM inv_movimientos_detalle d
                WHERE d.inv_movimientos_id = m.inv_movimientos_id
                  AND d.inv_bodega_destino_id IN (' . implode(',', $marcadores) . ')
            )';
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                m.*, es.sis_estado_codigo, es.sis_estado_nombre,
                td.sis_tipo_documento_nombre,
                u.sis_usuarios_nombre AS usuario_nombre,
                bo.inv_bodega_codigo AS bodega_origen,
                bd.inv_bodega_codigo AS bodega_destino,
                COALESCE((SELECT sum(d.inv_movimientos_detalle_total) FROM inv_movimientos_detalle d WHERE d.inv_movimientos_id = m.inv_movimientos_id AND d.inv_movimientos_detalle_estado = 1), 0) AS total
            FROM inv_movimientos m
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = m.sis_tipo_documento_id
            LEFT JOIN sis_usuarios u ON u.sis_usuarios_id = m.usuario_crea
            LEFT JOIN inv_bodega bo ON bo.inv_bodega_id = m.inv_bodega_origen_id
            LEFT JOIN inv_bodega bd ON bd.inv_bodega_id = m.inv_bodega_destino_id
            WHERE m.sis_empresa_id = :empresa_id
              AND COALESCE(m.inv_movimientos_tipo, '') IN ('AJUSTE', 'AJUSTE_IN', 'AJUSTE_OUT', 'TRANSFERENCIA')
              {$filtroBodega}
            ORDER BY m.fecha_crea DESC, m.inv_movimientos_id DESC
        ");
        $sentencia->execute($parametros);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA TRANSFERENCIAS PENDIENTES DE RECEPCION.
     * ***************************************************************************
     */
    public function listarTransferenciasPendientes(int $empresaId, int $usuarioId, bool $superusuario): array
    {
        $pdo = $this->conexionBaseDatos->obtener();

        if ($superusuario) {
            $sentencia = $pdo->prepare("
                SELECT m.*, bo.inv_bodega_codigo AS bodega_origen, bd.inv_bodega_codigo AS bodega_destino
                FROM inv_movimientos m
                INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
                INNER JOIN inv_bodega bo ON bo.inv_bodega_id = m.inv_bodega_origen_id
                INNER JOIN inv_bodega bd ON bd.inv_bodega_id = m.inv_bodega_destino_id
                WHERE m.sis_empresa_id = :empresa_id
                  AND m.inv_movimientos_tipo = 'TRANSFERENCIA'
                  AND es.sis_estado_codigo = 'EN_TRANSITO'
                ORDER BY m.fecha_crea
            ");
            $sentencia->execute(['empresa_id' => $empresaId]);

            return $sentencia->fetchAll();
        }

        /* Para usuarios normales: solo transferencias cuya bodega destino esté
           explícitamente asignada al usuario en inv_bodega_usuarios. Sin fallback. */
        $sentencia = $pdo->prepare("
            SELECT m.*, bo.inv_bodega_codigo AS bodega_origen, bd.inv_bodega_codigo AS bodega_destino
            FROM inv_movimientos m
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            INNER JOIN inv_bodega bo ON bo.inv_bodega_id = m.inv_bodega_origen_id
            INNER JOIN inv_bodega bd ON bd.inv_bodega_id = m.inv_bodega_destino_id
            INNER JOIN inv_bodega_usuarios bu ON bu.inv_bodega_id = m.inv_bodega_destino_id
                AND bu.sis_empresa_id = :empresa_id
                AND bu.sis_usuarios_id = :usuario_id
                AND bu.inv_bodega_usuarios_estado = 1
            WHERE m.sis_empresa_id = :empresa_id
              AND m.inv_movimientos_tipo = 'TRANSFERENCIA'
              AND es.sis_estado_codigo = 'EN_TRANSITO'
            ORDER BY m.fecha_crea
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'usuario_id' => $usuarioId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI EL USUARIO PUEDE RECIBIR LA BODEGA DESTINO.
     * ***************************************************************************
     */
    public function usuarioPuedeRecibir(int $empresaId, int $movimientoId, int $usuarioId, bool $superusuario): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT inv_bodega_destino_id
            FROM inv_movimientos
            WHERE sis_empresa_id = :empresa_id
              AND inv_movimientos_id = :movimiento_id
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'movimiento_id' => $movimientoId]);
        $destinoId = (int) $sentencia->fetchColumn();
        if ($destinoId <= 0) {
            return false;
        }
        foreach ($this->listarBodegasPermitidas($empresaId, $usuarioId, $superusuario) as $bodega) {
            if ((int) $bodega['inv_bodega_id'] === $destinoId) {
                return true;
            }
        }

        return false;
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI EL USUARIO TIENE ASIGNADA LA BODEGA ORIGEN DEL MOVIMIENTO.
     * ***************************************************************************
     */
    public function usuarioPuedeDespachar(int $empresaId, int $movimientoId, int $usuarioId, bool $superusuario): bool
    {
        if ($superusuario) {
            return true;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT inv_bodega_origen_id
            FROM inv_movimientos
            WHERE sis_empresa_id = :empresa_id
              AND inv_movimientos_id = :movimiento_id
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'movimiento_id' => $movimientoId]);
        $origenId = (int) $sentencia->fetchColumn();
        if ($origenId <= 0) {
            return false;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM inv_bodega_usuarios
            WHERE sis_empresa_id = :empresa_id
              AND sis_usuarios_id = :usuario_id
              AND inv_bodega_id = :bodega_id
              AND inv_bodega_usuarios_estado = 1
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'usuario_id' => $usuarioId, 'bodega_id' => $origenId]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * LISTA BODEGAS PERMITIDAS PARA EL USUARIO.
     * ***************************************************************************
     */
    public function listarBodegasPermitidas(int $empresaId, int $usuarioId, bool $superusuario): array
    {
        $pdo = $this->conexionBaseDatos->obtener();

        if (!$superusuario) {
            $sentencia = $pdo->prepare("
                SELECT b.*
                FROM inv_bodega_usuarios bu
                INNER JOIN inv_bodega b ON b.inv_bodega_id = bu.inv_bodega_id
                INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
                WHERE bu.sis_empresa_id = :empresa_id
                  AND bu.sis_usuarios_id = :usuario_id
                  AND bu.inv_bodega_usuarios_estado = 1
                  AND es.sis_estado_codigo = 'ACTIVO'
                UNION
                SELECT b.*
                FROM inv_bodega b
                INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
                WHERE b.sis_empresa_id = :empresa_id
                  AND b.inv_bodega_virtual = TRUE
                  AND es.sis_estado_codigo = 'ACTIVO'
                ORDER BY inv_bodega_codigo
            ");
            $sentencia->execute(['empresa_id' => $empresaId, 'usuario_id' => $usuarioId]);
            $asignadas = $sentencia->fetchAll();
            if ($asignadas) {
                return $asignadas;
            }
        }

        $sentencia = $pdo->prepare("
            SELECT b.*
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE b.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
            ORDER BY b.inv_bodega_codigo
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA TODAS LAS BODEGAS ACTIVAS DE LA EMPRESA (SIN FILTRO DE USUARIO).
     * ***************************************************************************
     */
    public function listarTodasBodegasActivas(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT b.*
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE b.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
            ORDER BY b.inv_bodega_codigo
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);
        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * BUSCA PRODUCTOS PARA MODAL DE SELECCION. FILTRA SALDOS POR BODEGA.
     * * SI bodegaId > 0, SOLO MUESTRA SALDO DE ESA BODEGA Y NO DE TODA LA EMPRESA.
     * ***************************************************************************
     */
    public function buscarProductos(int $empresaId, string $termino, int $bodegaId = 0): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                p.inv_producto_id,
                p.inv_producto_codigo_principal,
                p.inv_producto_nombre,
                COALESCE(p.inv_producto_descripcion, '') AS inv_producto_descripcion,
                COALESCE(p.inv_producto_costo_promedio, 0) AS costo_promedio,
                COALESCE(precio.pvp, 0) AS pvp,
                m.inv_marca_nombre,
                img.sis_archivos_id AS imagen_principal_id
            FROM inv_producto p
            INNER JOIN inv_marca m ON m.inv_marca_id = p.inv_marca_id
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            LEFT JOIN LATERAL (
                SELECT d.ven_lista_precio_detalle_valor AS pvp
                FROM ven_lista_precio lp
                INNER JOIN ven_lista_precio_detalle d ON d.ven_lista_precio_id = lp.ven_lista_precio_id
                    AND d.inv_producto_id = p.inv_producto_id
                    AND COALESCE(d.ven_lista_precio_detalle_estado, 1) = 1
                WHERE lp.sis_empresa_id = p.sis_empresa_id
                  AND COALESCE(lp.sis_estado, 1) = 1
                  AND COALESCE(lp.ven_lista_precio_predeterminado, 0) = 1
                LIMIT 1
            ) precio ON TRUE
            LEFT JOIN sis_archivos img ON img.sis_empresa_id = p.sis_empresa_id
                AND img.sis_archivos_tabla = 'INV_PRODUCTO'
                AND img.sis_archivos_id_padre = p.inv_producto_id
                AND img.sis_archivos_estado = 1
                AND img.sis_archivos_principal = TRUE
            WHERE p.sis_empresa_id = :empresa_id
              AND es.sis_estado_codigo = 'ACTIVO'
              AND (
                  upper(p.inv_producto_codigo_principal) LIKE upper(:termino)
                  OR upper(p.inv_producto_nombre) LIKE upper(:termino)
              )
            ORDER BY p.inv_producto_nombre
            LIMIT 50
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'termino' => '%' . $termino . '%']);
        $productos = $sentencia->fetchAll();
        if (!$productos) {
            return [];
        }
        $idsProducto = array_map(fn (array $p): int => (int) $p['inv_producto_id'], $productos);
        $saldos = $this->listarSaldosPorProductos($empresaId, $idsProducto, $bodegaId);
        foreach ($productos as &$producto) {
            $producto['saldos'] = $saldos[(int) $producto['inv_producto_id']] ?? [];
        }
        unset($producto);

        return $productos;
    }

    /**
     * ***************************************************************************
     * * BUSCA PRODUCTO EXACTO POR CODIGO. PASA BODEGA AL BUSCADOR DE SALDOS.
     * ***************************************************************************
     */
    public function buscarProductoPorCodigo(int $empresaId, string $codigo, int $bodegaId = 0): ?array
    {
        $productos = $this->buscarProductos($empresaId, $codigo, $bodegaId);
        foreach ($productos as $producto) {
            if (strtoupper((string) $producto['inv_producto_codigo_principal']) === strtoupper($codigo)) {
                return $producto;
            }
        }

        return null;
    }

    /**
     * ***************************************************************************
     * * CREA MOVIMIENTO INTERNO Y PROCESA SI CORRESPONDE.
     * ***************************************************************************
     */
    public function crear(array $datos, array $lineas, int $usuarioId): int
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $tipoDocumento = $this->obtenerTipoDocumentoId($datos['tipo']);
            $estado = $datos['autoaprobado'] ? ($datos['tipo'] === 'TRANSFERENCIA' ? 'EN_TRANSITO' : 'PROCESADO') : 'PENDIENTE';
            $numero = $this->generarNumero($datos['tipo'], (int) $datos['empresa_id'], $pdo);
            $sentencia = $pdo->prepare("
                INSERT INTO inv_movimientos (
                    sis_empresa_id, sis_tipo_documento_id, inv_movimientos_numero,
                    inv_movimientos_fecha, inv_bodega_origen_id, inv_bodega_destino_id,
                    inv_movimientos_referencia, inv_movimientos_observacion,
                    inv_movimientos_tipo, sis_estado_id, usuario_crea
                )
                VALUES (
                    :empresa_id, :tipo_documento_id, :numero,
                    :fecha, :origen_id, :destino_id,
                    :referencia, :observacion,
                    :tipo, :estado_id, :usuario_crea
                )
                RETURNING inv_movimientos_id
            ");
            $sentencia->execute([
                'empresa_id' => $datos['empresa_id'],
                'tipo_documento_id' => $tipoDocumento,
                'numero' => $numero,
                'fecha' => $datos['fecha'],
                'origen_id' => $datos['tipo'] === 'TRANSFERENCIA' ? ($lineas[0]['origen_id'] ?: null) : null,
                'destino_id' => $datos['tipo'] === 'TRANSFERENCIA' ? ($lineas[0]['destino_id'] ?: null) : null,
                'referencia' => $datos['detalle'],
                'observacion' => $datos['detalle'],
                'tipo' => $datos['tipo'],
                'estado_id' => $this->obtenerEstadoId($estado),
                'usuario_crea' => $usuarioId,
            ]);
            $movimientoId = (int) $sentencia->fetchColumn();
            foreach ($lineas as $linea) {
                $this->crearDetalle($movimientoId, $datos['empresa_id'], $linea, $usuarioId);
            }
            if ($datos['autoaprobado']) {
                $this->procesarMovimiento($movimientoId, $usuarioId, $datos['tipo'] === 'TRANSFERENCIA' ? 'APROBAR_TRANSFERENCIA' : 'PROCESAR_AJUSTE');
            }
            $pdo->commit();

            return $movimientoId;
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA SOLO OBSERVACION DE MOVIMIENTO EN TRANSITO O PENDIENTE.
     * ***************************************************************************
     */
    public function actualizarObservacion(int $empresaId, int $movimientoId, string $detalle, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_movimientos m
            SET inv_movimientos_referencia = :detalle,
                inv_movimientos_observacion = :detalle,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            FROM sis_estado es
            WHERE es.sis_estado_id = m.sis_estado_id
              AND m.sis_empresa_id = :empresa_id
              AND m.inv_movimientos_id = :movimiento_id
              AND es.sis_estado_codigo IN ('PENDIENTE', 'EN_TRANSITO')
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'movimiento_id' => $movimientoId,
            'detalle' => $detalle,
            'usuario_modifica' => $usuarioId,
        ]);
        if ($sentencia->rowCount() === 0) {
            throw new \InvalidArgumentException('Solo puede editar observacion si el movimiento sigue pendiente o en transito.');
        }
    }

    /**
     * ***************************************************************************
     * * APRUEBA MOVIMIENTO PENDIENTE Y AFECTA STOCK.
     * ***************************************************************************
     */
    public function aprobar(int $empresaId, int $movimientoId, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $movimiento = $this->obtenerMovimientoBloqueado($empresaId, $movimientoId);
            if ($movimiento['sis_estado_codigo'] !== 'PENDIENTE') {
                throw new \InvalidArgumentException('Solo se aprueban movimientos pendientes.');
            }
            $estado = $movimiento['inv_movimientos_tipo'] === 'TRANSFERENCIA' ? 'EN_TRANSITO' : 'PROCESADO';
            $this->procesarMovimiento($movimientoId, $usuarioId, $movimiento['inv_movimientos_tipo'] === 'TRANSFERENCIA' ? 'APROBAR_TRANSFERENCIA' : 'PROCESAR_AJUSTE');
            $this->cambiarEstado($movimientoId, $estado, $usuarioId);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * RECIBE TRANSFERENCIA EN TRANSITO Y AFECTA STOCK DESTINO.
     * ***************************************************************************
     */
    public function recibir(int $empresaId, int $movimientoId, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $movimiento = $this->obtenerMovimientoBloqueado($empresaId, $movimientoId);
            if ($movimiento['sis_estado_codigo'] !== 'EN_TRANSITO') {
                throw new \InvalidArgumentException('La transferencia ya no esta en transito.');
            }
            $this->procesarMovimiento($movimientoId, $usuarioId, 'RECIBIR_TRANSFERENCIA');
            $sentencia = $pdo->prepare("
                UPDATE inv_movimientos
                SET sis_estado_id = :estado_id,
                    inv_movimientos_recibido_fecha = now(),
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE inv_movimientos_id = :movimiento_id
            ");
            $sentencia->execute([
                'estado_id' => $this->obtenerEstadoId('RECIBIDO'),
                'usuario_modifica' => $usuarioId,
                'movimiento_id' => $movimientoId,
            ]);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * RETORNA CABECERA, LINEAS Y FLAGS DE PERMISO PARA EL MODAL DE DETALLE.
     * ***************************************************************************
     */
    public function detalle(int $empresaId, int $movimientoId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                m.*,
                es.sis_estado_codigo,
                es.sis_estado_nombre,
                td.sis_tipo_documento_nombre,
                u.sis_usuarios_nombre AS usuario_nombre,
                bo.inv_bodega_codigo AS bodega_origen,
                bd.inv_bodega_codigo AS bodega_destino,
                COALESCE((
                    SELECT sum(d.inv_movimientos_detalle_total)
                    FROM inv_movimientos_detalle d
                    WHERE d.inv_movimientos_id = m.inv_movimientos_id
                      AND d.inv_movimientos_detalle_estado = 1
                ), 0) AS total
            FROM inv_movimientos m
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = m.sis_tipo_documento_id
            LEFT JOIN sis_usuarios u ON u.sis_usuarios_id = m.usuario_crea
            LEFT JOIN inv_bodega bo ON bo.inv_bodega_id = m.inv_bodega_origen_id
            LEFT JOIN inv_bodega bd ON bd.inv_bodega_id = m.inv_bodega_destino_id
            WHERE m.sis_empresa_id = :empresa_id
              AND m.inv_movimientos_id = :movimiento_id
            LIMIT 1
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'movimiento_id' => $movimientoId]);
        $cabecera = $sentencia->fetch();
        if (!$cabecera) {
            throw new \InvalidArgumentException('Movimiento no valido.');
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                d.*,
                p.inv_producto_codigo_principal AS producto_codigo,
                p.inv_producto_nombre AS producto_nombre,
                bo.inv_bodega_codigo AS bodega_origen,
                bd.inv_bodega_codigo AS bodega_destino,
                u.sis_usuarios_nombre AS usuario_nombre
            FROM inv_movimientos_detalle d
            INNER JOIN inv_producto p ON p.inv_producto_id = d.inv_producto_id
            LEFT JOIN inv_bodega bo ON bo.inv_bodega_id = d.inv_bodega_origen_id
            LEFT JOIN inv_bodega bd ON bd.inv_bodega_id = d.inv_bodega_destino_id
            LEFT JOIN sis_usuarios u ON u.sis_usuarios_id = d.usuario_crea
            WHERE d.inv_movimientos_id = :movimiento_id
              AND d.inv_movimientos_detalle_estado = 1
            ORDER BY d.inv_movimientos_detalle_id
        ");
        $sentencia->execute(['movimiento_id' => $movimientoId]);
        $lineas = $sentencia->fetchAll();

        /*
         * Verificacion de mismo dia para anular procesado/recibido.
         * PROCESADO: se usa fecha_modifica (momento en que cambio al estado PROCESADO).
         * RECIBIDO:  se usa inv_movimientos_recibido_fecha.
         */
        $puedeAnularProcesado = false;
        $estado = (string) $cabecera['sis_estado_codigo'];
        if ($estado === 'PROCESADO') {
            $fechaRef = substr((string) ($cabecera['fecha_modifica'] ?? $cabecera['fecha_crea']), 0, 10);
            $puedeAnularProcesado = ($fechaRef === date('Y-m-d'));
        } elseif ($estado === 'RECIBIDO') {
            $fechaRef = substr((string) ($cabecera['inv_movimientos_recibido_fecha'] ?? ''), 0, 10);
            $puedeAnularProcesado = ($fechaRef === date('Y-m-d'));
        }

        return [
            'cabecera'               => $cabecera,
            'lineas'                 => $lineas,
            'puede_anular_procesado' => $puedeAnularProcesado,
        ];
    }

    /**
     * ***************************************************************************
     * * ANULA MOVIMIENTO PROCESADO O RECIBIDO REVIRTIENDO STOCK Y KARDEX.
     * * SOLO PERMITE LA ANULACION EL MISMO DIA DE PROCESADO/RECIBIDO.
     * ***************************************************************************
     */
    public function anularProcesado(int $empresaId, int $movimientoId, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $movimiento = $this->obtenerMovimientoBloqueado($empresaId, $movimientoId);
            $estado = (string) $movimiento['sis_estado_codigo'];

            if ($estado === 'PROCESADO') {
                $fechaRef = substr((string) ($movimiento['fecha_modifica'] ?? $movimiento['fecha_crea']), 0, 10);
                if ($fechaRef !== date('Y-m-d')) {
                    throw new \InvalidArgumentException('Solo se puede anular un ajuste procesado el mismo dia en que fue procesado.');
                }
                $this->procesarMovimiento($movimientoId, $usuarioId, 'REVERSA_AJUSTE');
            } elseif ($estado === 'RECIBIDO') {
                $fechaRef = substr((string) ($movimiento['inv_movimientos_recibido_fecha'] ?? ''), 0, 10);
                if ($fechaRef !== date('Y-m-d')) {
                    throw new \InvalidArgumentException('Solo se puede anular una transferencia recibida el mismo dia en que fue recibida.');
                }
                $this->procesarMovimiento($movimientoId, $usuarioId, 'REVERSA_TRANSFERENCIA_RECIBIDO');
            } else {
                throw new \InvalidArgumentException('Solo se pueden anular movimientos en estado procesado o recibido.');
            }

            $this->cambiarEstado($movimientoId, 'ANULADO', $usuarioId);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ANULA MOVIMIENTO PENDIENTE O REVERSA TRANSFERENCIA EN TRANSITO.
     * ***************************************************************************
     */
    public function anular(int $empresaId, int $movimientoId, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $movimiento = $this->obtenerMovimientoBloqueado($empresaId, $movimientoId);
            if ($movimiento['sis_estado_codigo'] === 'EN_TRANSITO') {
                $this->procesarMovimiento($movimientoId, $usuarioId, 'ANULAR_TRANSITO');
            } elseif ($movimiento['sis_estado_codigo'] !== 'PENDIENTE') {
                throw new \InvalidArgumentException('Solo se anulan pendientes o transferencias en transito.');
            }
            $this->cambiarEstado($movimientoId, 'ANULADO', $usuarioId);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    private function crearDetalle(int $movimientoId, int $empresaId, array $linea, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_movimientos_detalle (
                sis_empresa_id, inv_movimientos_id, inv_producto_id,
                inv_movimientos_detalle_tipo, inv_movimientos_detalle_factor,
                inv_movimientos_detalle_cantidad, inv_movimientos_detalle_costo,
                inv_movimientos_detalle_pvp, inv_movimientos_detalle_total,
                inv_bodega_origen_id, inv_bodega_destino_id,
                inv_movimientos_detalle_observacion, usuario_crea
            )
            VALUES (
                :empresa_id, :movimiento_id, :producto_id,
                :tipo, :factor,
                :cantidad, :costo,
                :pvp, :total,
                :origen_id, :destino_id,
                :observacion, :usuario_crea
            )
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'movimiento_id' => $movimientoId,
            'producto_id' => $linea['producto_id'],
            'tipo' => $linea['accion'],
            'factor' => $linea['accion'] === 'EGRESO' ? -1 : 1,
            'cantidad' => $linea['cantidad'],
            'costo' => $linea['costo'],
            'pvp' => $linea['pvp'],
            'total' => $linea['cantidad'] * $linea['costo'],
            'origen_id' => $linea['origen_id'] ?: null,
            'destino_id' => $linea['destino_id'] ?: null,
            'observacion' => $linea['observacion'] ?? '',
            'usuario_crea' => $usuarioId,
        ]);
    }

    private function procesarMovimiento(int $movimientoId, int $usuarioId, string $modo): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT m.*, d.*
            FROM inv_movimientos m
            INNER JOIN inv_movimientos_detalle d ON d.inv_movimientos_id = m.inv_movimientos_id
            WHERE m.inv_movimientos_id = :movimiento_id
              AND d.inv_movimientos_detalle_estado = 1
            ORDER BY d.inv_movimientos_detalle_id
        ");
        $sentencia->execute(['movimiento_id' => $movimientoId]);
        foreach ($sentencia->fetchAll() as $linea) {
            if ($modo === 'PROCESAR_AJUSTE') {
                $bodegaId = $linea['inv_movimientos_detalle_tipo'] === 'INGRESO' ? (int) $linea['inv_bodega_destino_id'] : (int) $linea['inv_bodega_origen_id'];
                $tipoKardex = $linea['inv_movimientos_detalle_tipo'] === 'INGRESO' ? 'AJUSTE_IN' : 'AJUSTE_OUT';
                $this->aplicarStock($linea, $bodegaId, $tipoKardex, $linea['inv_movimientos_detalle_tipo'] === 'INGRESO', $usuarioId);
            } elseif ($modo === 'APROBAR_TRANSFERENCIA') {
                $this->aplicarStock($linea, (int) $linea['inv_bodega_origen_id'], 'TRANS_OUT', false, $usuarioId);
            } elseif ($modo === 'RECIBIR_TRANSFERENCIA') {
                $this->aplicarStock($linea, (int) $linea['inv_bodega_destino_id'], 'TRANS_IN', true, $usuarioId);
            } elseif ($modo === 'ANULAR_TRANSITO') {
                $this->aplicarStock($linea, (int) $linea['inv_bodega_origen_id'], 'TRANS_IN', true, $usuarioId);
            } elseif ($modo === 'REVERSA_AJUSTE') {
                /*
                 * REVERSA_AJUSTE: deshace un ajuste ya procesado.
                 * INGRESO fue: AJUSTE_IN al destino  → reversa: AJUSTE_OUT del destino.
                 * EGRESO  fue: AJUSTE_OUT del origen → reversa: AJUSTE_IN al origen.
                 */
                if ($linea['inv_movimientos_detalle_tipo'] === 'INGRESO') {
                    $this->aplicarStock($linea, (int) $linea['inv_bodega_destino_id'], 'AJUSTE_OUT', false, $usuarioId, 'REVERSA: ');
                } else {
                    $this->aplicarStock($linea, (int) $linea['inv_bodega_origen_id'], 'AJUSTE_IN', true, $usuarioId, 'REVERSA: ');
                }
            } elseif ($modo === 'REVERSA_TRANSFERENCIA_RECIBIDO') {
                /*
                 * REVERSA_TRANSFERENCIA_RECIBIDO: deshace transferencia completamente recibida.
                 * Fue: TRANS_OUT del origen + TRANS_IN al destino.
                 * Reversa: TRANS_OUT del destino + TRANS_IN al origen.
                 */
                $this->aplicarStock($linea, (int) $linea['inv_bodega_destino_id'], 'TRANS_OUT', false, $usuarioId, 'REVERSA: ');
                $this->aplicarStock($linea, (int) $linea['inv_bodega_origen_id'], 'TRANS_IN', true, $usuarioId, 'REVERSA: ');
            }
        }
    }

    private function aplicarStock(array $linea, int $bodegaId, string $tipoKardex, bool $entrada, int $usuarioId, string $prefijo = ''): void
    {
        $stock = $this->obtenerStockBloqueado((int) $linea['sis_empresa_id'], $bodegaId, (int) $linea['inv_producto_id']);
        $actual = $stock ? (float) $stock['inv_stock_cantidad_disponible'] : 0.0;
        $cantidad = (float) $linea['inv_movimientos_detalle_cantidad'];
        $nuevo = $entrada ? $actual + $cantidad : $actual - $cantidad;
        $bodega = $this->buscarBodega((int) $linea['sis_empresa_id'], $bodegaId);
        if (!$entrada && $nuevo < 0 && empty($bodega['inv_bodega_negativos'])) {
            throw new \InvalidArgumentException('La bodega ' . $bodega['inv_bodega_codigo'] . ' no permite stock negativo.');
        }
        if ($stock) {
            $sentencia = $this->conexionBaseDatos->obtener()->prepare("
                UPDATE inv_stock
                SET inv_stock_cantidad_disponible = :cantidad,
                    inv_stock_ultima_actualizacion = now(),
                    usuario_modifica = :usuario,
                    fecha_modifica = now()
                WHERE inv_stock_id = :stock_id
            ");
            $sentencia->execute(['cantidad' => $nuevo, 'usuario' => $usuarioId, 'stock_id' => $stock['inv_stock_id']]);
        } else {
            $sentencia = $this->conexionBaseDatos->obtener()->prepare("
                INSERT INTO inv_stock (
                    sis_empresa_id, inv_bodega_id, inv_producto_id,
                    inv_stock_cantidad_disponible, inv_stock_costo_promedio, usuario_crea
                )
                VALUES (:empresa_id, :bodega_id, :producto_id, :cantidad, :costo, :usuario)
            ");
            $sentencia->execute([
                'empresa_id' => $linea['sis_empresa_id'],
                'bodega_id' => $bodegaId,
                'producto_id' => $linea['inv_producto_id'],
                'cantidad' => $nuevo,
                'costo' => $linea['inv_movimientos_detalle_costo'],
                'usuario' => $usuarioId,
            ]);
        }
        $this->crearKardex($linea, $bodegaId, $tipoKardex, $entrada ? $cantidad : 0, $entrada ? 0 : $cantidad, $nuevo, $usuarioId, $prefijo);
    }

    private function crearKardex(array $linea, int $bodegaId, string $tipo, float $entrada, float $salida, float $saldo, int $usuarioId, string $prefijo = ''): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO inv_kardex (
                sis_empresa_id, inv_bodega_id, inv_producto_id,
                inv_kardex_tipo_movimiento, inv_kardex_documento_tipo,
                inv_kardex_documento_id, inv_kardex_documento_numero,
                inv_kardex_cantidad_entrada, inv_kardex_cantidad_salida,
                inv_kardex_costo_unitario, inv_kardex_saldo_cantidad,
                inv_kardex_saldo_valor, inv_kardex_observacion, usuario_crea
            )
            VALUES (
                :empresa_id, :bodega_id, :producto_id,
                :tipo, 'INV_MOVIMIENTOS',
                :documento_id, :numero,
                :entrada, :salida,
                :costo, :saldo,
                :saldo_valor, :observacion, :usuario
            )
        ");
        $sentencia->execute([
            'empresa_id' => $linea['sis_empresa_id'],
            'bodega_id' => $bodegaId,
            'producto_id' => $linea['inv_producto_id'],
            'tipo' => $tipo,
            'documento_id' => $linea['inv_movimientos_id'],
            'numero' => $linea['inv_movimientos_numero'],
            'entrada' => $entrada,
            'salida' => $salida,
            'costo' => $linea['inv_movimientos_detalle_costo'],
            'saldo' => $saldo,
            'saldo_valor' => $saldo * (float) $linea['inv_movimientos_detalle_costo'],
            'observacion' => $prefijo . ($linea['inv_movimientos_detalle_observacion'] ?? ''),
            'usuario' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * OBTIENE SALDOS SOLO PARA LOS PRODUCTOS DADOS Y OPCIONALMENTE UNA BODEGA.
     * * EVITA TRAER EL STOCK COMPLETO DE LA EMPRESA EN CADA BUSQUEDA DEL MODAL.
     * ***************************************************************************
     */
    private function listarSaldosPorProductos(int $empresaId, array $idsProducto, int $bodegaId = 0): array
    {
        if (!$idsProducto) {
            return [];
        }
        $pdo = $this->conexionBaseDatos->obtener();
        $marcadores = implode(',', array_fill(0, count($idsProducto), '?'));
        $params = array_merge([$empresaId], $idsProducto);
        $filtroBodega = '';
        if ($bodegaId > 0) {
            $filtroBodega = ' AND s.inv_bodega_id = ?';
            $params[] = $bodegaId;
        }
        $sentencia = $pdo->prepare("
            SELECT s.inv_producto_id, b.inv_bodega_codigo, b.inv_bodega_id, s.inv_stock_cantidad_disponible
            FROM inv_stock s
            INNER JOIN inv_bodega b ON b.inv_bodega_id = s.inv_bodega_id
            WHERE s.sis_empresa_id = ?
              AND s.inv_producto_id IN ({$marcadores})
              {$filtroBodega}
            ORDER BY b.inv_bodega_codigo
        ");
        $sentencia->execute($params);
        $saldos = [];
        foreach ($sentencia->fetchAll() as $saldo) {
            $saldos[(int) $saldo['inv_producto_id']][] = $saldo;
        }

        return $saldos;
    }

    private function obtenerStockBloqueado(int $empresaId, int $bodegaId, int $productoId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM inv_stock
            WHERE sis_empresa_id = :empresa_id
              AND inv_bodega_id = :bodega_id
              AND inv_producto_id = :producto_id
            FOR UPDATE
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'bodega_id' => $bodegaId, 'producto_id' => $productoId]);
        $stock = $sentencia->fetch();

        return $stock ?: null;
    }

    private function obtenerMovimientoBloqueado(int $empresaId, int $movimientoId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT m.*, es.sis_estado_codigo
            FROM inv_movimientos m
            INNER JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
            WHERE m.sis_empresa_id = :empresa_id
              AND m.inv_movimientos_id = :movimiento_id
            FOR UPDATE
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'movimiento_id' => $movimientoId]);
        $movimiento = $sentencia->fetch();
        if (!$movimiento) {
            throw new \InvalidArgumentException('Movimiento no valido.');
        }

        return $movimiento;
    }

    private function buscarBodega(int $empresaId, int $bodegaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("SELECT * FROM inv_bodega WHERE sis_empresa_id = :empresa_id AND inv_bodega_id = :bodega_id LIMIT 1");
        $sentencia->execute(['empresa_id' => $empresaId, 'bodega_id' => $bodegaId]);
        $bodega = $sentencia->fetch();
        if (!$bodega) {
            throw new \InvalidArgumentException('Bodega no valida.');
        }

        return $bodega;
    }

    private function cambiarEstado(int $movimientoId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_movimientos
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario,
                fecha_modifica = now()
            WHERE inv_movimientos_id = :movimiento_id
        ");
        $sentencia->execute(['estado_id' => $this->obtenerEstadoId($estado), 'usuario' => $usuarioId, 'movimiento_id' => $movimientoId]);
    }

    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'INVENTARIO'
              AND sis_estado_entidad = 'INV_MOVIMIENTOS'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    private function obtenerTipoDocumentoId(string $tipo): int
    {
        $codigo = $tipo === 'TRANSFERENCIA' ? 'TRANSFERENCIA' : ($tipo === 'AJUSTE' ? 'AJUSTE' : $tipo);
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("SELECT sis_tipo_documento_id FROM sis_tipo_documento WHERE sis_tipo_documento_codigo = :codigo LIMIT 1");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    private function generarNumero(string $tipo, int $empresaId, \PDO $pdo): string
    {
        return $this->secuenciaModelo->obtenerSiguiente($pdo, $empresaId, $tipo, 'INVENTARIO');
    }
}
