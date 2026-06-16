<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class SecuenciaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA SECUENCIAS RESPETANDO EMPRESA ACTIVA O VISTA GLOBAL.
     * ***************************************************************************
     */
    public function listar(?int $empresaId = null): array
    {
        $filtroEmpresa = $empresaId === null ? '' : 'AND s.sis_empresa_id = :empresa_id';
        $sql = "
            SELECT
                s.*,
                td.sis_tipo_documento_codigo,
                td.sis_tipo_documento_nombre,
                td.sis_tipo_documento_modulo,
                td.sis_tipo_documento_afecta_inventario,
                emp.sis_empresa_razon_social,
                emp.sis_empresa_nombre_comercial,
                e.sis_estado_codigo,
                e.sis_estado_nombre,
                b.inv_bodega_nombre,
                b.inv_bodega_codigo
            FROM sis_secuencias s
            INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = s.sis_tipo_documento_id
            INNER JOIN sis_empresa emp ON emp.sis_empresa_id = s.sis_empresa_id
            INNER JOIN sis_estado e ON e.sis_estado_id = s.sis_estado_id
            LEFT JOIN inv_bodega b ON b.inv_bodega_id = s.inv_bodega_id
            WHERE 1 = 1
            {$filtroEmpresa}
            ORDER BY emp.sis_empresa_razon_social, td.sis_tipo_documento_modulo, td.sis_tipo_documento_nombre, s.sis_secuencias_establecimiento, s.sis_secuencias_punto_emision
        ";
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($empresaId === null ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA FILTROS DE SUPERUSUARIO.
     * ***************************************************************************
     */
    public function listarEmpresasActivas(?int $empresaId = null): array
    {
        $filtroEmpresa = $empresaId === null ? '' : 'AND emp.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                emp.sis_empresa_id,
                emp.sis_empresa_razon_social,
                emp.sis_empresa_nombre_comercial
            FROM sis_empresa emp
            INNER JOIN sis_estado e ON e.sis_estado_id = emp.sis_estado_id
            WHERE e.sis_estado_codigo = 'ACTIVO'
            {$filtroEmpresa}
            ORDER BY emp.sis_empresa_razon_social
        ");
        $sentencia->execute($empresaId === null ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA UNA SECUENCIA POR EMPRESA Y TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_secuencias (
                sis_empresa_id, sis_tipo_documento_id, inv_bodega_id,
                sis_secuencias_establecimiento, sis_secuencias_punto_emision,
                sis_secuencias_desde, sis_secuencias_actual, sis_secuencias_hasta,
                sis_secuencias_observacion, sis_estado_id, usuario_crea
            )
            VALUES (
                :empresa_id, :tipo_documento_id, :bodega_id,
                :establecimiento, :punto_emision,
                :desde, :actual, :hasta,
                :observacion, :estado_id, :usuario_crea
            )
        ");
        $this->vincularDatos($sentencia, $datos);
        $sentencia->bindValue(':estado_id', $this->obtenerEstadoId('ACTIVO'), \PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_crea', $usuarioId, \PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA DATOS EDITABLES DE UNA SECUENCIA.
     * ***************************************************************************
     */
    public function actualizar(int $secuenciaId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_secuencias
            SET sis_empresa_id = :empresa_id,
                sis_tipo_documento_id = :tipo_documento_id,
                inv_bodega_id = :bodega_id,
                sis_secuencias_establecimiento = :establecimiento,
                sis_secuencias_punto_emision = :punto_emision,
                sis_secuencias_desde = :desde,
                sis_secuencias_actual = :actual,
                sis_secuencias_hasta = :hasta,
                sis_secuencias_observacion = :observacion,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_secuencias_id = :secuencia_id
        ");
        $this->vincularDatos($sentencia, $datos);
        $sentencia->bindValue(':secuencia_id', $secuenciaId, \PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_modifica', $usuarioId, \PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UNA SECUENCIA.
     * ***************************************************************************
     */
    public function cambiarEstado(int $secuenciaId, string $codigoEstado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_secuencias
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_secuencias_id = :secuencia_id
        ");
        $sentencia->execute([
            'secuencia_id' => $secuenciaId,
            'estado_id' => $this->obtenerEstadoId($codigoEstado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA SECUENCIA POR ID.
     * ***************************************************************************
     */
    public function buscar(int $secuenciaId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT * FROM sis_secuencias WHERE sis_secuencias_id = :secuencia_id LIMIT 1
        ");
        $sentencia->execute(['secuencia_id' => $secuenciaId]);
        $secuencia = $sentencia->fetch();

        return $secuencia ?: null;
    }

    /**
     * ***************************************************************************
     * * OBTIENE Y CONSUME EL SIGUIENTE NUMERO DE SECUENCIA (TRANSACCIONAL).
     * * DEBE LLAMARSE DENTRO DE UNA TRANSACCION ACTIVA EN EL PDO RECIBIDO.
     * ***************************************************************************
     */
    public function obtenerSiguiente(\PDO $pdo, int $empresaId, string $tipoCodigo, string $modulo, ?int $bodegaId = null): string
    {
        /* Busca la secuencia activa para esta empresa + tipo de documento.
           Si se indica bodega, cruza establecimiento/punto_emision de la bodega
           con los mismos campos de la secuencia. Hace fallback a cualquier
           secuencia activa si no hay coincidencia exacta por bodega. */
        if ($bodegaId !== null) {
            $sentencia = $pdo->prepare("
                SELECT
                    s.sis_secuencias_id,
                    s.sis_secuencias_establecimiento,
                    s.sis_secuencias_punto_emision,
                    s.sis_secuencias_actual,
                    s.sis_secuencias_hasta,
                    td.sis_tipo_documento_codigo
                FROM sis_secuencias s
                INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = s.sis_tipo_documento_id
                INNER JOIN sis_estado e ON e.sis_estado_id = s.sis_estado_id
                INNER JOIN inv_bodega b ON b.inv_bodega_id = :bodega_id
                WHERE s.sis_empresa_id = :empresa_id
                  AND td.sis_tipo_documento_codigo = :tipo_codigo
                  AND td.sis_tipo_documento_modulo = :modulo
                  AND e.sis_estado_codigo = 'ACTIVO'
                  AND s.sis_secuencias_establecimiento = b.inv_bodega_establecimiento
                  AND s.sis_secuencias_punto_emision   = b.inv_bodega_punto_emision
                ORDER BY s.sis_secuencias_id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $sentencia->execute([
                'empresa_id'  => $empresaId,
                'tipo_codigo' => $tipoCodigo,
                'modulo'      => $modulo,
                'bodega_id'   => $bodegaId,
            ]);
            $secuencia = $sentencia->fetch(\PDO::FETCH_ASSOC);
            /* Fallback: si la bodega no tiene est/pto configurados, usa cualquier secuencia activa */
            if (!$secuencia) {
                $sentencia = $pdo->prepare("
                    SELECT s.sis_secuencias_id, s.sis_secuencias_establecimiento,
                           s.sis_secuencias_punto_emision, s.sis_secuencias_actual,
                           s.sis_secuencias_hasta, td.sis_tipo_documento_codigo
                    FROM sis_secuencias s
                    INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = s.sis_tipo_documento_id
                    INNER JOIN sis_estado e ON e.sis_estado_id = s.sis_estado_id
                    WHERE s.sis_empresa_id = :empresa_id
                      AND td.sis_tipo_documento_codigo = :tipo_codigo
                      AND td.sis_tipo_documento_modulo = :modulo
                      AND e.sis_estado_codigo = 'ACTIVO'
                    ORDER BY s.sis_secuencias_id ASC
                    LIMIT 1 FOR UPDATE
                ");
                $sentencia->execute(['empresa_id' => $empresaId, 'tipo_codigo' => $tipoCodigo, 'modulo' => $modulo]);
                $secuencia = $sentencia->fetch(\PDO::FETCH_ASSOC);
            }
        } else {
            $sentencia = $pdo->prepare("
                SELECT
                    s.sis_secuencias_id,
                    s.sis_secuencias_establecimiento,
                    s.sis_secuencias_punto_emision,
                    s.sis_secuencias_actual,
                    s.sis_secuencias_hasta,
                    td.sis_tipo_documento_codigo
                FROM sis_secuencias s
                INNER JOIN sis_tipo_documento td ON td.sis_tipo_documento_id = s.sis_tipo_documento_id
                INNER JOIN sis_estado e ON e.sis_estado_id = s.sis_estado_id
                WHERE s.sis_empresa_id = :empresa_id
                  AND td.sis_tipo_documento_codigo = :tipo_codigo
                  AND td.sis_tipo_documento_modulo = :modulo
                  AND e.sis_estado_codigo = 'ACTIVO'
                ORDER BY s.sis_secuencias_id ASC
                LIMIT 1 FOR UPDATE
            ");
            $sentencia->execute(['empresa_id' => $empresaId, 'tipo_codigo' => $tipoCodigo, 'modulo' => $modulo]);
            $secuencia = $sentencia->fetch(\PDO::FETCH_ASSOC);
        }
        $secuencia = $sentencia->fetch(\PDO::FETCH_ASSOC);

        if (!$secuencia) {
            throw new \RuntimeException(
                "No hay una secuencia activa para el tipo de documento {$tipoCodigo}. " .
                "Configure una en Sistema \u{2192} Configuracion \u{2192} Tipos documento."
            );
        }

        $actual = (int) $secuencia['sis_secuencias_actual'];
        $hasta  = (int) $secuencia['sis_secuencias_hasta'];

        if ($actual > $hasta) {
            throw new \RuntimeException(
                "La secuencia de {$tipoCodigo} ha alcanzado su limite ({$hasta}). " .
                "Configure una nueva secuencia en Sistema \u{2192} Configuracion \u{2192} Tipos documento."
            );
        }

        /* Incrementa el contador. */
        $actualizar = $pdo->prepare("
            UPDATE sis_secuencias
            SET sis_secuencias_actual = sis_secuencias_actual + 1,
                fecha_modifica = now()
            WHERE sis_secuencias_id = :secuencia_id
        ");
        $actualizar->execute(['secuencia_id' => $secuencia['sis_secuencias_id']]);

        /* Formato: PREFIJO-EST-PTO-XXXXXXXXX */
        $prefijo = match ($tipoCodigo) {
            'AJUSTE'        => 'AJ',
            'TRANSFERENCIA' => 'TR',
            'PROFORMA'      => 'PRF',
            default         => strtoupper(substr($tipoCodigo, 0, 2)),
        };

        return sprintf(
            '%s-%s-%s-%09d',
            $prefijo,
            $secuencia['sis_secuencias_establecimiento'],
            $secuencia['sis_secuencias_punto_emision'],
            $actual
        );
    }

    /**
     * ***************************************************************************
     * * VERIFICA CLAVE REPETIDA DE SECUENCIA POR EMPRESA Y PUNTO.
     * ***************************************************************************
     */
    public function existeClave(array $datos, ?int $secuenciaId = null): bool
    {
        $sql = "
            SELECT 1
            FROM sis_secuencias
            WHERE sis_empresa_id = :empresa_id
              AND sis_tipo_documento_id = :tipo_documento_id
              AND sis_secuencias_establecimiento = :establecimiento
              AND sis_secuencias_punto_emision = :punto_emision
        ";
        $parametros = [
            'empresa_id' => $datos['empresa_id'],
            'tipo_documento_id' => $datos['tipo_documento_id'],
            'establecimiento' => $datos['establecimiento'],
            'punto_emision' => $datos['punto_emision'],
        ];
        if ($secuenciaId !== null) {
            $sql .= ' AND sis_secuencias_id <> :secuencia_id';
            $parametros['secuencia_id'] = $secuenciaId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE SECUENCIA POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'SISTEMA'
              AND sis_estado_entidad = 'SIS_SECUENCIAS'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VINCULA DATOS DE SECUENCIA CON TIPOS NUMERICOS VALIDOS.
     * ***************************************************************************
     */
    private function vincularDatos(\PDOStatement $sentencia, array $datos): void
    {
        $sentencia->bindValue(':empresa_id', (int) $datos['empresa_id'], \PDO::PARAM_INT);
        $sentencia->bindValue(':tipo_documento_id', (int) $datos['tipo_documento_id'], \PDO::PARAM_INT);
        $bodegaId = isset($datos['bodega_id']) && $datos['bodega_id'] > 0 ? (int) $datos['bodega_id'] : null;
        $sentencia->bindValue(':bodega_id', $bodegaId, $bodegaId !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $sentencia->bindValue(':establecimiento', $datos['establecimiento']);
        $sentencia->bindValue(':punto_emision', $datos['punto_emision']);
        $sentencia->bindValue(':desde', (int) $datos['desde'], \PDO::PARAM_INT);
        $sentencia->bindValue(':actual', (int) $datos['actual'], \PDO::PARAM_INT);
        $sentencia->bindValue(':hasta', (int) $datos['hasta'], \PDO::PARAM_INT);
        $sentencia->bindValue(':observacion', $datos['observacion']);
    }
}
