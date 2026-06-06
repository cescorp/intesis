<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class ArchivoProductoModelo
{
    private const TABLA_PRODUCTO = 'INV_PRODUCTO';

    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA IMAGENES ACTIVAS DE UN PRODUCTO.
     * ***************************************************************************
     */
    public function listarPorProducto(int $empresaId, int $productoId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM sis_archivos
            WHERE sis_empresa_id = :empresa_id
              AND sis_archivos_tabla = :tabla
              AND sis_archivos_id_padre = :producto_id
              AND sis_archivos_estado = 1
              AND sis_archivos_tipo = 'IMAGEN'
            ORDER BY sis_archivos_principal DESC, sis_archivos_orden, sis_archivos_id
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'tabla' => self::TABLA_PRODUCTO,
            'producto_id' => $productoId,
        ]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * BUSCA UN ARCHIVO ACTIVO POR ID.
     * ***************************************************************************
     */
    public function buscar(int $archivoId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM sis_archivos
            WHERE sis_archivos_id = :archivo_id
              AND sis_archivos_estado = 1
            LIMIT 1
        ");
        $sentencia->execute(['archivo_id' => $archivoId]);
        $archivo = $sentencia->fetch();

        return $archivo ?: null;
    }

    /**
     * ***************************************************************************
     * * REGISTRA UNA IMAGEN DE PRODUCTO Y DEFINE PRINCIPAL SI APLICA.
     * ***************************************************************************
     */
    public function registrarImagen(int $empresaId, int $productoId, string $archivo, string $ubicacion, int $usuarioId): int
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $principal = !$this->tieneImagenPrincipal($empresaId, $productoId);
        $orden = $this->siguienteOrden($empresaId, $productoId);

        $sentencia = $pdo->prepare("
            INSERT INTO sis_archivos (
                sis_empresa_id, sis_archivos_archivo, sis_archivos_tabla,
                sis_archivos_id_padre, sis_archivos_estado, sis_archivos_ubicacion,
                sis_archivos_principal, sis_archivos_orden, sis_archivos_tipo,
                usuario_crea
            )
            VALUES (
                :empresa_id, :archivo, :tabla,
                :producto_id, 1, :ubicacion,
                :principal, :orden, 'IMAGEN',
                :usuario_crea
            )
            RETURNING sis_archivos_id
        ");
        $sentencia->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $sentencia->bindValue(':archivo', $archivo);
        $sentencia->bindValue(':tabla', self::TABLA_PRODUCTO);
        $sentencia->bindValue(':producto_id', $productoId, PDO::PARAM_INT);
        $sentencia->bindValue(':ubicacion', $ubicacion);
        $sentencia->bindValue(':principal', $principal, PDO::PARAM_BOOL);
        $sentencia->bindValue(':orden', $orden, PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_crea', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * MARCA UNA IMAGEN COMO PRINCIPAL DEL PRODUCTO.
     * ***************************************************************************
     */
    public function marcarPrincipal(int $archivoId, int $usuarioId): void
    {
        $archivo = $this->buscar($archivoId);
        if (!$archivo) {
            throw new \InvalidArgumentException('Imagen no valida.');
        }

        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("
                UPDATE sis_archivos
                SET sis_archivos_principal = FALSE,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE sis_empresa_id = :empresa_id
                  AND sis_archivos_tabla = :tabla
                  AND sis_archivos_id_padre = :producto_id
                  AND sis_archivos_estado = 1
            ");
            $sentencia->execute([
                'empresa_id' => (int) $archivo['sis_empresa_id'],
                'tabla' => self::TABLA_PRODUCTO,
                'producto_id' => (int) $archivo['sis_archivos_id_padre'],
                'usuario_modifica' => $usuarioId,
            ]);

            $sentencia = $pdo->prepare("
                UPDATE sis_archivos
                SET sis_archivos_principal = TRUE,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE sis_archivos_id = :archivo_id
            ");
            $sentencia->execute([
                'archivo_id' => $archivoId,
                'usuario_modifica' => $usuarioId,
            ]);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ELIMINA LOGICAMENTE UNA IMAGEN DE PRODUCTO.
     * ***************************************************************************
     */
    public function eliminarLogico(int $archivoId, int $usuarioId): void
    {
        $archivo = $this->buscar($archivoId);
        if (!$archivo) {
            throw new \InvalidArgumentException('Imagen no valida.');
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_archivos
            SET sis_archivos_estado = -1,
                sis_archivos_principal = FALSE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_archivos_id = :archivo_id
        ");
        $sentencia->execute([
            'archivo_id' => $archivoId,
            'usuario_modifica' => $usuarioId,
        ]);

        $this->asegurarPrincipal((int) $archivo['sis_empresa_id'], (int) $archivo['sis_archivos_id_padre'], $usuarioId);
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI YA EXISTE IMAGEN PRINCIPAL ACTIVA.
     * ***************************************************************************
     */
    private function tieneImagenPrincipal(int $empresaId, int $productoId): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM sis_archivos
            WHERE sis_empresa_id = :empresa_id
              AND sis_archivos_tabla = :tabla
              AND sis_archivos_id_padre = :producto_id
              AND sis_archivos_estado = 1
              AND sis_archivos_principal = TRUE
            LIMIT 1
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'tabla' => self::TABLA_PRODUCTO,
            'producto_id' => $productoId,
        ]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * CALCULA EL SIGUIENTE ORDEN DE IMAGEN DEL PRODUCTO.
     * ***************************************************************************
     */
    private function siguienteOrden(int $empresaId, int $productoId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT COALESCE(MAX(sis_archivos_orden), 0) + 1
            FROM sis_archivos
            WHERE sis_empresa_id = :empresa_id
              AND sis_archivos_tabla = :tabla
              AND sis_archivos_id_padre = :producto_id
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'tabla' => self::TABLA_PRODUCTO,
            'producto_id' => $productoId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ASIGNA UNA NUEVA PRINCIPAL SI EL PRODUCTO QUEDO SIN PRINCIPAL.
     * ***************************************************************************
     */
    private function asegurarPrincipal(int $empresaId, int $productoId, int $usuarioId): void
    {
        if ($this->tieneImagenPrincipal($empresaId, $productoId)) {
            return;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_archivos
            SET sis_archivos_principal = TRUE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_archivos_id = (
                SELECT sis_archivos_id
                FROM sis_archivos
                WHERE sis_empresa_id = :empresa_id
                  AND sis_archivos_tabla = :tabla
                  AND sis_archivos_id_padre = :producto_id
                  AND sis_archivos_estado = 1
                  AND sis_archivos_tipo = 'IMAGEN'
                ORDER BY sis_archivos_orden, sis_archivos_id
                LIMIT 1
            )
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'tabla' => self::TABLA_PRODUCTO,
            'producto_id' => $productoId,
            'usuario_modifica' => $usuarioId,
        ]);
    }
}
