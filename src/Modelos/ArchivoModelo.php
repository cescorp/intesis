<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

/**
 * Registro en BD de los adjuntos de cualquier entidad (tabla sis_archivos).
 * Lo usa AlmacenArchivosServicio; los modulos no deben escribir sis_archivos directamente.
 * Un adjunto pertenece a (empresa, tabla, id_padre), por ejemplo ('VEN_DOCUMENTO', 15).
 */
final class ArchivoModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    public function listar(int $empresaId, string $tabla, int $idPadre): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_archivos_id, sis_archivos_nombre, sis_archivos_mime, sis_archivos_tamano, fecha_crea
            FROM sis_archivos
            WHERE sis_empresa_id = :empresa_id
              AND sis_archivos_tabla = :tabla
              AND sis_archivos_id_padre = :id_padre
              AND sis_archivos_estado = 1
            ORDER BY sis_archivos_orden, sis_archivos_id
        ");
        $sentencia->execute(['empresa_id' => $empresaId, 'tabla' => $tabla, 'id_padre' => $idPadre]);
        return $sentencia->fetchAll();
    }

    /** Busca un adjunto activo verificando que pertenezca a la empresa y a la entidad indicada. */
    public function buscar(int $archivoId, int $empresaId, string $tabla): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM sis_archivos
            WHERE sis_archivos_id = :id
              AND sis_empresa_id = :empresa_id
              AND sis_archivos_tabla = :tabla
              AND sis_archivos_estado = 1
            LIMIT 1
        ");
        $sentencia->execute(['id' => $archivoId, 'empresa_id' => $empresaId, 'tabla' => $tabla]);
        return $sentencia->fetch() ?: null;
    }

    public function registrar(array $datos, int $usuarioId): int
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("
                INSERT INTO sis_archivos (
                    sis_empresa_id, sis_archivos_archivo, sis_archivos_nombre, sis_archivos_tabla,
                    sis_archivos_id_padre, sis_archivos_estado, sis_archivos_ubicacion,
                    sis_archivos_principal, sis_archivos_orden, sis_archivos_tipo,
                    sis_archivos_mime, sis_archivos_tamano, usuario_crea
                ) VALUES (
                    :empresa_id, :archivo, :nombre, :tabla,
                    :id_padre, 1, :ubicacion,
                    FALSE,
                    (SELECT COALESCE(MAX(sis_archivos_orden), 0) + 1 FROM sis_archivos
                      WHERE sis_empresa_id = :empresa_id2 AND sis_archivos_tabla = :tabla2 AND sis_archivos_id_padre = :id_padre2),
                    :tipo, :mime, :tamano, :usuario
                )
                RETURNING sis_archivos_id
            ");
            $sentencia->bindValue(':empresa_id', $datos['empresa_id'], PDO::PARAM_INT);
            $sentencia->bindValue(':empresa_id2', $datos['empresa_id'], PDO::PARAM_INT);
            $sentencia->bindValue(':archivo', $datos['archivo']);
            $sentencia->bindValue(':nombre', $datos['nombre']);
            $sentencia->bindValue(':tabla', $datos['tabla']);
            $sentencia->bindValue(':tabla2', $datos['tabla']);
            $sentencia->bindValue(':id_padre', $datos['id_padre'], PDO::PARAM_INT);
            $sentencia->bindValue(':id_padre2', $datos['id_padre'], PDO::PARAM_INT);
            $sentencia->bindValue(':ubicacion', $datos['ubicacion']);
            $sentencia->bindValue(':tipo', $datos['tipo']);
            $sentencia->bindValue(':mime', $datos['mime']);
            $sentencia->bindValue(':tamano', $datos['tamano'], PDO::PARAM_INT);
            $sentencia->bindValue(':usuario', $usuarioId, PDO::PARAM_INT);
            $sentencia->execute();
            $id = (int) $sentencia->fetchColumn();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function renombrar(int $archivoId, string $nombre, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE sis_archivos
                SET sis_archivos_nombre = :nombre, usuario_modifica = :usuario, fecha_modifica = now()
                WHERE sis_archivos_id = :id
            ")->execute(['nombre' => $nombre, 'usuario' => $usuarioId, 'id' => $archivoId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Borrado logico: el archivo fisico se conserva para auditoria. */
    public function eliminarLogico(int $archivoId, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE sis_archivos
                SET sis_archivos_estado = 0, usuario_modifica = :usuario, fecha_modifica = now()
                WHERE sis_archivos_id = :id
            ")->execute(['usuario' => $usuarioId, 'id' => $archivoId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
