<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class TipoDocumentoModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA TIPOS DE DOCUMENTO GLOBALES CON SU ESTADO.
     * ***************************************************************************
     */
    public function listar(): array
    {
        return $this->conexionBaseDatos->obtener()->query("
            SELECT
                t.*,
                e.sis_estado_codigo,
                e.sis_estado_nombre
            FROM sis_tipo_documento t
            INNER JOIN sis_estado e ON e.sis_estado_id = t.sis_estado_id
            ORDER BY t.sis_tipo_documento_modulo, t.sis_tipo_documento_nombre
        ")->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA UN TIPO DE DOCUMENTO GLOBAL.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_tipo_documento (
                sis_tipo_documento_codigo, sis_tipo_documento_nombre,
                sis_tipo_documento_modulo, sis_tipo_documento_descripcion,
                sis_tipo_documento_afecta_contabilidad, sis_tipo_documento_afecta_inventario,
                sis_estado_id, usuario_crea
            )
            VALUES (
                :codigo, :nombre, :modulo, :descripcion,
                :afecta_contabilidad, :afecta_inventario,
                :estado_id, :usuario_crea
            )
        ");
        $this->vincularDatos($sentencia, $datos);
        $sentencia->bindValue(':estado_id', $this->obtenerEstadoId('ACTIVO'), PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_crea', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA DATOS EDITABLES DE UN TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    public function actualizar(int $tipoDocumentoId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_tipo_documento
            SET sis_tipo_documento_codigo = :codigo,
                sis_tipo_documento_nombre = :nombre,
                sis_tipo_documento_modulo = :modulo,
                sis_tipo_documento_descripcion = :descripcion,
                sis_tipo_documento_afecta_contabilidad = :afecta_contabilidad,
                sis_tipo_documento_afecta_inventario = :afecta_inventario,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_tipo_documento_id = :tipo_documento_id
        ");
        $this->vincularDatos($sentencia, $datos);
        $sentencia->bindValue(':tipo_documento_id', $tipoDocumentoId, PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DEL TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    public function cambiarEstado(int $tipoDocumentoId, string $codigoEstado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_tipo_documento
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_tipo_documento_id = :tipo_documento_id
        ");
        $sentencia->execute([
            'tipo_documento_id' => $tipoDocumentoId,
            'estado_id' => $this->obtenerEstadoId($codigoEstado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA TIPO DE DOCUMENTO POR ID.
     * ***************************************************************************
     */
    public function buscar(int $tipoDocumentoId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT * FROM sis_tipo_documento WHERE sis_tipo_documento_id = :tipo_documento_id LIMIT 1
        ");
        $sentencia->execute(['tipo_documento_id' => $tipoDocumentoId]);
        $tipo = $sentencia->fetch();

        return $tipo ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA CODIGO REPETIDO POR MODULO.
     * ***************************************************************************
     */
    public function existeCodigo(string $modulo, string $codigo, ?int $tipoDocumentoId = null): bool
    {
        $sql = "
            SELECT 1
            FROM sis_tipo_documento
            WHERE sis_tipo_documento_modulo = :modulo
              AND sis_tipo_documento_codigo = :codigo
        ";
        $parametros = ['modulo' => $modulo, 'codigo' => $codigo];
        if ($tipoDocumentoId !== null) {
            $sql .= ' AND sis_tipo_documento_id <> :tipo_documento_id';
            $parametros['tipo_documento_id'] = $tipoDocumentoId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE TIPO DOCUMENTO POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'SISTEMA'
              AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VINCULA DATOS BOOLEANOS Y TEXTO DEL TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    private function vincularDatos(\PDOStatement $sentencia, array $datos): void
    {
        $sentencia->bindValue(':codigo', $datos['codigo']);
        $sentencia->bindValue(':nombre', $datos['nombre']);
        $sentencia->bindValue(':modulo', $datos['modulo']);
        $sentencia->bindValue(':descripcion', $datos['descripcion']);
        $sentencia->bindValue(':afecta_contabilidad', (bool) $datos['afecta_contabilidad'], PDO::PARAM_BOOL);
        $sentencia->bindValue(':afecta_inventario', (bool) $datos['afecta_inventario'], PDO::PARAM_BOOL);
    }
}
