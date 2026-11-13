<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

/**
 * Datos del modulo Archivos: carpetas, versiones, compartidos y accesos.
 *
 * Reglas de visibilidad (las aplica ArchivoControlador con estos metodos):
 * - El dueño de una carpeta/archivo es usuario_crea.
 * - Una carpeta es visible para un usuario si es suya, o si ella o alguna carpeta
 *   superior esta compartida con el (compartir una carpeta comparte todo su contenido).
 * - Un archivo es visible si es suyo, si esta compartido directamente con el, o si su
 *   carpeta es visible.
 * - Las versiones de un archivo forman un "grupo" cuyo id es el de la version 1:
 *   COALESCE(sis_archivos_original_id, sis_archivos_id).
 */
final class GestorArchivosModelo
{
    public const TABLA = 'SIS_CARPETA';

    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    private function pdo(): PDO
    {
        return $this->conexionBaseDatos->obtener();
    }

    /** Ejecuta una escritura dentro de una transaccion (regla del proyecto). */
    private function enTransaccion(callable $accion): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $resultado = $accion($pdo);
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // CARPETAS
    // =========================================================================

    /**
     * Carpetas que el usuario puede ver: las suyas y las compartidas con el (con todo
     * su contenido). El cliente arma el arbol; una carpeta compartida cuya carpeta
     * padre no esta en la lista se muestra bajo "Compartido conmigo".
     */
    public function carpetasVisibles(int $empresaId, int $usuarioId): array
    {
        $sentencia = $this->pdo()->prepare("
            WITH RECURSIVE compartidas AS (
                SELECT c.sis_carpeta_id
                FROM sis_carpeta c
                INNER JOIN sis_compartido s ON s.sis_compartido_tipo = 'CARPETA'
                                           AND s.sis_compartido_objeto_id = c.sis_carpeta_id
                                           AND s.sis_usuarios_id = :usuario
                                           AND s.sis_compartido_estado = 1
                WHERE c.sis_empresa_id = :empresa AND c.sis_carpeta_estado = 1 AND c.usuario_crea <> :usuario2
                UNION
                SELECT h.sis_carpeta_id
                FROM sis_carpeta h
                INNER JOIN compartidas p ON h.sis_carpeta_padre_id = p.sis_carpeta_id
                WHERE h.sis_carpeta_estado = 1
            )
            SELECT c.sis_carpeta_id, c.sis_carpeta_padre_id, c.sis_carpeta_nombre, c.usuario_crea AS dueno_id,
                   u.sis_usuarios_nombre AS dueno_nombre,
                   (c.usuario_crea = :usuario3) AS propia,
                   EXISTS (SELECT 1 FROM sis_compartido s
                            WHERE s.sis_compartido_tipo = 'CARPETA' AND s.sis_compartido_objeto_id = c.sis_carpeta_id
                              AND s.sis_compartido_estado = 1) AS compartida
            FROM sis_carpeta c
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = c.usuario_crea
            WHERE c.sis_empresa_id = :empresa2 AND c.sis_carpeta_estado = 1
              AND (c.usuario_crea = :usuario4 OR c.sis_carpeta_id IN (SELECT sis_carpeta_id FROM compartidas))
            ORDER BY c.sis_carpeta_nombre
        ");
        $sentencia->execute([
            'usuario' => $usuarioId, 'usuario2' => $usuarioId, 'usuario3' => $usuarioId, 'usuario4' => $usuarioId,
            'empresa' => $empresaId, 'empresa2' => $empresaId,
        ]);
        return $sentencia->fetchAll();
    }

    public function buscarCarpeta(int $carpetaId, int $empresaId): ?array
    {
        $sentencia = $this->pdo()->prepare('
            SELECT * FROM sis_carpeta
            WHERE sis_carpeta_id = :id AND sis_empresa_id = :empresa AND sis_carpeta_estado = 1
        ');
        $sentencia->execute(['id' => $carpetaId, 'empresa' => $empresaId]);
        return $sentencia->fetch() ?: null;
    }

    /** True si la carpeta es del usuario o ella o alguna superior esta compartida con el. */
    public function carpetaVisible(int $carpetaId, int $usuarioId): bool
    {
        $sentencia = $this->pdo()->prepare("
            WITH RECURSIVE ancestros AS (
                SELECT sis_carpeta_id, sis_carpeta_padre_id, usuario_crea FROM sis_carpeta
                WHERE sis_carpeta_id = :id AND sis_carpeta_estado = 1
                UNION
                SELECT c.sis_carpeta_id, c.sis_carpeta_padre_id, c.usuario_crea FROM sis_carpeta c
                INNER JOIN ancestros a ON c.sis_carpeta_id = a.sis_carpeta_padre_id
                WHERE c.sis_carpeta_estado = 1
            )
            SELECT EXISTS (SELECT 1 FROM ancestros WHERE sis_carpeta_id = :id2 AND usuario_crea = :usuario)
                OR EXISTS (SELECT 1 FROM ancestros a
                           INNER JOIN sis_compartido s ON s.sis_compartido_tipo = 'CARPETA'
                                                      AND s.sis_compartido_objeto_id = a.sis_carpeta_id
                                                      AND s.sis_usuarios_id = :usuario2
                                                      AND s.sis_compartido_estado = 1)
        ");
        $sentencia->execute(['id' => $carpetaId, 'id2' => $carpetaId, 'usuario' => $usuarioId, 'usuario2' => $usuarioId]);
        return (bool) $sentencia->fetchColumn();
    }

    public function existeNombreCarpeta(int $empresaId, int $duenoId, ?int $padreId, string $nombre, int $excluirId = 0): bool
    {
        $sentencia = $this->pdo()->prepare('
            SELECT 1 FROM sis_carpeta
            WHERE sis_empresa_id = :empresa AND usuario_crea = :dueno AND sis_carpeta_estado = 1
              AND sis_carpeta_padre_id IS NOT DISTINCT FROM CAST(:padre AS BIGINT)
              AND upper(sis_carpeta_nombre) = upper(:nombre)
              AND sis_carpeta_id <> :excluir
            LIMIT 1
        ');
        $sentencia->execute(['empresa' => $empresaId, 'dueno' => $duenoId, 'padre' => $padreId, 'nombre' => $nombre, 'excluir' => $excluirId]);
        return (bool) $sentencia->fetchColumn();
    }

    public function crearCarpeta(int $empresaId, ?int $padreId, string $nombre, int $usuarioId): int
    {
        return $this->enTransaccion(function (PDO $pdo) use ($empresaId, $padreId, $nombre, $usuarioId) {
            $sentencia = $pdo->prepare('
                INSERT INTO sis_carpeta (sis_empresa_id, sis_carpeta_padre_id, sis_carpeta_nombre, usuario_crea)
                VALUES (:empresa, CAST(:padre AS BIGINT), :nombre, :usuario)
                RETURNING sis_carpeta_id
            ');
            $sentencia->execute(['empresa' => $empresaId, 'padre' => $padreId, 'nombre' => $nombre, 'usuario' => $usuarioId]);
            return (int) $sentencia->fetchColumn();
        });
    }

    public function renombrarCarpeta(int $carpetaId, string $nombre, int $usuarioId): void
    {
        $this->enTransaccion(fn (PDO $pdo) => $pdo->prepare('
            UPDATE sis_carpeta SET sis_carpeta_nombre = :nombre, usuario_modifica = :usuario, fecha_modifica = now()
            WHERE sis_carpeta_id = :id
        ')->execute(['nombre' => $nombre, 'usuario' => $usuarioId, 'id' => $carpetaId]));
    }

    public function carpetaTieneContenido(int $carpetaId): bool
    {
        $sentencia = $this->pdo()->prepare("
            SELECT EXISTS (SELECT 1 FROM sis_carpeta WHERE sis_carpeta_padre_id = :id AND sis_carpeta_estado = 1)
                OR EXISTS (SELECT 1 FROM sis_archivos WHERE sis_archivos_tabla = :tabla
                            AND sis_archivos_id_padre = :id2 AND sis_archivos_estado = 1)
        ");
        $sentencia->execute(['id' => $carpetaId, 'id2' => $carpetaId, 'tabla' => self::TABLA]);
        return (bool) $sentencia->fetchColumn();
    }

    public function eliminarCarpeta(int $carpetaId, int $usuarioId): void
    {
        $this->enTransaccion(fn (PDO $pdo) => $pdo->prepare('
            UPDATE sis_carpeta SET sis_carpeta_estado = 0, usuario_modifica = :usuario, fecha_modifica = now()
            WHERE sis_carpeta_id = :id
        ')->execute(['usuario' => $usuarioId, 'id' => $carpetaId]));
    }

    // =========================================================================
    // ARCHIVOS Y VERSIONES
    // =========================================================================

    private const COLUMNAS_ARCHIVO = "
        a.sis_archivos_id, COALESCE(a.sis_archivos_original_id, a.sis_archivos_id) AS grupo_id,
        a.sis_archivos_id_padre AS carpeta_id, a.sis_archivos_nombre, a.sis_archivos_mime,
        a.sis_archivos_tamano, a.sis_archivos_version, a.fecha_crea, a.usuario_crea AS dueno_id,
        u.sis_usuarios_nombre AS subido_por,
        lower(COALESCE(substring(a.sis_archivos_archivo from '\\.([^.]+)$'), '')) AS extension,
        EXISTS (SELECT 1 FROM sis_compartido s WHERE s.sis_compartido_tipo = 'ARCHIVO'
                 AND s.sis_compartido_objeto_id = COALESCE(a.sis_archivos_original_id, a.sis_archivos_id)
                 AND s.sis_compartido_estado = 1) AS compartido
    ";

    /** Ultima version de cada archivo de una carpeta (0 = raiz "Mis archivos" del dueño). */
    public function listarArchivos(int $empresaId, int $carpetaId, int $duenoId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT DISTINCT ON (COALESCE(a.sis_archivos_original_id, a.sis_archivos_id)) " . self::COLUMNAS_ARCHIVO . "
            FROM sis_archivos a
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = a.usuario_crea
            WHERE a.sis_empresa_id = :empresa AND a.sis_archivos_tabla = :tabla AND a.sis_archivos_estado = 1
              AND a.sis_archivos_id_padre = :carpeta
              AND (:carpeta2 > 0 OR a.usuario_crea = :dueno)
            ORDER BY COALESCE(a.sis_archivos_original_id, a.sis_archivos_id), a.sis_archivos_version DESC
        ");
        $sentencia->execute(['empresa' => $empresaId, 'tabla' => self::TABLA, 'carpeta' => $carpetaId, 'carpeta2' => $carpetaId, 'dueno' => $duenoId]);
        return $this->ordenarPorNombre($sentencia->fetchAll());
    }

    /** Archivos compartidos directamente con el usuario (se muestran en "Compartido conmigo"). */
    public function listarArchivosCompartidosConmigo(int $empresaId, int $usuarioId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT DISTINCT ON (COALESCE(a.sis_archivos_original_id, a.sis_archivos_id)) " . self::COLUMNAS_ARCHIVO . "
            FROM sis_archivos a
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = a.usuario_crea
            INNER JOIN sis_compartido c ON c.sis_compartido_tipo = 'ARCHIVO'
                                       AND c.sis_compartido_objeto_id = COALESCE(a.sis_archivos_original_id, a.sis_archivos_id)
                                       AND c.sis_usuarios_id = :usuario AND c.sis_compartido_estado = 1
            WHERE a.sis_empresa_id = :empresa AND a.sis_archivos_tabla = :tabla AND a.sis_archivos_estado = 1
            ORDER BY COALESCE(a.sis_archivos_original_id, a.sis_archivos_id), a.sis_archivos_version DESC
        ");
        $sentencia->execute(['empresa' => $empresaId, 'tabla' => self::TABLA, 'usuario' => $usuarioId]);
        return $this->ordenarPorNombre($sentencia->fetchAll());
    }

    private function ordenarPorNombre(array $archivos): array
    {
        usort($archivos, fn ($a, $b) => strcasecmp((string) $a['sis_archivos_nombre'], (string) $b['sis_archivos_nombre']));
        return $archivos;
    }

    /** Cualquier version activa del modulo, con el id de su grupo. */
    public function buscarArchivo(int $archivoId, int $empresaId): ?array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT a.*, COALESCE(a.sis_archivos_original_id, a.sis_archivos_id) AS grupo_id
            FROM sis_archivos a
            WHERE a.sis_archivos_id = :id AND a.sis_empresa_id = :empresa
              AND a.sis_archivos_tabla = :tabla AND a.sis_archivos_estado = 1
        ");
        $sentencia->execute(['id' => $archivoId, 'empresa' => $empresaId, 'tabla' => self::TABLA]);
        return $sentencia->fetch() ?: null;
    }

    /**
     * Ultima version de un archivo con el mismo nombre y extension en la misma carpeta y
     * del mismo dueño: subir otro con ese nombre crea una version nueva en vez de duplicarlo.
     */
    public function buscarParaNuevaVersion(int $empresaId, int $carpetaId, int $duenoId, string $nombre, string $extension): ?array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT COALESCE(a.sis_archivos_original_id, a.sis_archivos_id) AS grupo_id,
                   MAX(a.sis_archivos_version) AS ultima_version
            FROM sis_archivos a
            WHERE a.sis_empresa_id = :empresa AND a.sis_archivos_tabla = :tabla AND a.sis_archivos_estado = 1
              AND a.sis_archivos_id_padre = :carpeta AND a.usuario_crea = :dueno
              AND upper(a.sis_archivos_nombre) = upper(:nombre)
              AND lower(COALESCE(substring(a.sis_archivos_archivo from '\\.([^.]+)$'), '')) = :extension
            GROUP BY COALESCE(a.sis_archivos_original_id, a.sis_archivos_id)
            LIMIT 1
        ");
        $sentencia->execute(['empresa' => $empresaId, 'tabla' => self::TABLA, 'carpeta' => $carpetaId, 'dueno' => $duenoId, 'nombre' => $nombre, 'extension' => $extension]);
        return $sentencia->fetch() ?: null;
    }

    public function listarVersiones(int $grupoId): array
    {
        $sentencia = $this->pdo()->prepare('
            SELECT a.sis_archivos_id, a.sis_archivos_version, a.sis_archivos_tamano, a.fecha_crea,
                   u.sis_usuarios_nombre AS subido_por
            FROM sis_archivos a
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = a.usuario_crea
            WHERE COALESCE(a.sis_archivos_original_id, a.sis_archivos_id) = :grupo AND a.sis_archivos_estado = 1
            ORDER BY a.sis_archivos_version DESC
        ');
        $sentencia->execute(['grupo' => $grupoId]);
        return $sentencia->fetchAll();
    }

    /** Renombra todas las versiones para que el siguiente archivo con ese nombre sea su version nueva. */
    public function renombrarArchivo(int $grupoId, string $nombre, int $usuarioId): void
    {
        $this->enTransaccion(fn (PDO $pdo) => $pdo->prepare('
            UPDATE sis_archivos SET sis_archivos_nombre = :nombre, usuario_modifica = :usuario, fecha_modifica = now()
            WHERE COALESCE(sis_archivos_original_id, sis_archivos_id) = :grupo AND sis_archivos_estado = 1
        ')->execute(['nombre' => $nombre, 'usuario' => $usuarioId, 'grupo' => $grupoId]));
    }

    /** Borrado logico de todas las versiones; los archivos fisicos se conservan. */
    public function eliminarArchivo(int $grupoId, int $usuarioId): void
    {
        $this->enTransaccion(fn (PDO $pdo) => $pdo->prepare('
            UPDATE sis_archivos SET sis_archivos_estado = 0, usuario_modifica = :usuario, fecha_modifica = now()
            WHERE COALESCE(sis_archivos_original_id, sis_archivos_id) = :grupo AND sis_archivos_estado = 1
        ')->execute(['usuario' => $usuarioId, 'grupo' => $grupoId]));
    }

    // =========================================================================
    // COMPARTIR
    // =========================================================================

    public function compartidoCon(string $tipo, int $objetoId, int $usuarioId): bool
    {
        $sentencia = $this->pdo()->prepare('
            SELECT 1 FROM sis_compartido
            WHERE sis_compartido_tipo = :tipo AND sis_compartido_objeto_id = :objeto
              AND sis_usuarios_id = :usuario AND sis_compartido_estado = 1
            LIMIT 1
        ');
        $sentencia->execute(['tipo' => $tipo, 'objeto' => $objetoId, 'usuario' => $usuarioId]);
        return (bool) $sentencia->fetchColumn();
    }

    /** Usuarios activos de la empresa (menos el dueño) y si ya tienen el objeto compartido. */
    public function usuariosParaCompartir(int $empresaId, int $duenoId, string $tipo, int $objetoId): array
    {
        $sentencia = $this->pdo()->prepare("
            SELECT u.sis_usuarios_id, u.sis_usuarios_nombre, s.fecha_crea AS compartido_desde
            FROM sis_usuario_empresa ue
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = ue.sis_usuarios_id
            INNER JOIN sis_estado eu  ON eu.sis_estado_id  = u.sis_estado_id
            INNER JOIN sis_estado ee  ON ee.sis_estado_id  = ue.sis_estado_id
            LEFT JOIN sis_compartido s ON s.sis_usuarios_id = u.sis_usuarios_id
                                      AND s.sis_compartido_tipo = :tipo AND s.sis_compartido_objeto_id = :objeto
                                      AND s.sis_compartido_estado = 1
            WHERE ue.sis_empresa_id = :empresa AND u.sis_usuarios_id <> :dueno
              AND eu.sis_estado_codigo = 'ACTIVO' AND ee.sis_estado_codigo = 'ACTIVO'
            ORDER BY u.sis_usuarios_nombre
        ");
        $sentencia->execute(['empresa' => $empresaId, 'dueno' => $duenoId, 'tipo' => $tipo, 'objeto' => $objetoId]);
        return $sentencia->fetchAll();
    }

    /**
     * Deja compartido el objeto exactamente con $usuarioIds: revoca a los que ya no estan
     * (estado 0 + fecha_modifica = hasta cuando) y agrega a los nuevos (fecha_crea = desde).
     */
    public function sincronizarCompartidos(int $empresaId, string $tipo, int $objetoId, array $usuarioIds, int $porUsuarioId): void
    {
        $this->enTransaccion(function (PDO $pdo) use ($empresaId, $tipo, $objetoId, $usuarioIds, $porUsuarioId) {
            $actuales = $pdo->prepare('
                SELECT sis_usuarios_id FROM sis_compartido
                WHERE sis_compartido_tipo = :tipo AND sis_compartido_objeto_id = :objeto AND sis_compartido_estado = 1
            ');
            $actuales->execute(['tipo' => $tipo, 'objeto' => $objetoId]);
            $actuales = array_map('intval', $actuales->fetchAll(PDO::FETCH_COLUMN));

            $revocar = $pdo->prepare('
                UPDATE sis_compartido SET sis_compartido_estado = 0, usuario_modifica = :por, fecha_modifica = now()
                WHERE sis_compartido_tipo = :tipo AND sis_compartido_objeto_id = :objeto
                  AND sis_usuarios_id = :usuario AND sis_compartido_estado = 1
            ');
            foreach (array_diff($actuales, $usuarioIds) as $usuarioId) {
                $revocar->execute(['por' => $porUsuarioId, 'tipo' => $tipo, 'objeto' => $objetoId, 'usuario' => $usuarioId]);
            }

            $agregar = $pdo->prepare('
                INSERT INTO sis_compartido (sis_empresa_id, sis_compartido_tipo, sis_compartido_objeto_id, sis_usuarios_id, usuario_crea)
                VALUES (:empresa, :tipo, :objeto, :usuario, :por)
            ');
            foreach (array_diff($usuarioIds, $actuales) as $usuarioId) {
                $agregar->execute(['empresa' => $empresaId, 'tipo' => $tipo, 'objeto' => $objetoId, 'usuario' => $usuarioId, 'por' => $porUsuarioId]);
            }
        });
    }

    /**
     * Con quien esta compartido un archivo: directamente o a traves de su carpeta o de
     * cualquier carpeta superior (en "origen" va el nombre de esa carpeta).
     */
    public function compartidosDeArchivo(int $grupoId, int $carpetaId): array
    {
        $sentencia = $this->pdo()->prepare("
            WITH RECURSIVE ancestros AS (
                SELECT sis_carpeta_id, sis_carpeta_padre_id, sis_carpeta_nombre FROM sis_carpeta
                WHERE sis_carpeta_id = :carpeta AND sis_carpeta_estado = 1
                UNION
                SELECT c.sis_carpeta_id, c.sis_carpeta_padre_id, c.sis_carpeta_nombre FROM sis_carpeta c
                INNER JOIN ancestros a ON c.sis_carpeta_id = a.sis_carpeta_padre_id
                WHERE c.sis_carpeta_estado = 1
            )
            SELECT u.sis_usuarios_nombre, s.fecha_crea AS desde, NULL AS origen
            FROM sis_compartido s INNER JOIN sis_usuarios u ON u.sis_usuarios_id = s.sis_usuarios_id
            WHERE s.sis_compartido_tipo = 'ARCHIVO' AND s.sis_compartido_objeto_id = :grupo AND s.sis_compartido_estado = 1
            UNION ALL
            SELECT u.sis_usuarios_nombre, s.fecha_crea, a.sis_carpeta_nombre
            FROM ancestros a
            INNER JOIN sis_compartido s ON s.sis_compartido_tipo = 'CARPETA' AND s.sis_compartido_objeto_id = a.sis_carpeta_id
                                       AND s.sis_compartido_estado = 1
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = s.sis_usuarios_id
            ORDER BY 2
        ");
        $sentencia->execute(['carpeta' => $carpetaId, 'grupo' => $grupoId]);
        return $sentencia->fetchAll();
    }

    // =========================================================================
    // ACCESOS
    // =========================================================================

    public function registrarAcceso(int $archivoId, int $usuarioId, string $accion): void
    {
        $this->enTransaccion(fn (PDO $pdo) => $pdo->prepare('
            INSERT INTO sis_archivo_acceso (sis_archivos_id, sis_usuarios_id, sis_archivo_acceso_accion)
            VALUES (:archivo, :usuario, :accion)
        ')->execute(['archivo' => $archivoId, 'usuario' => $usuarioId, 'accion' => $accion]));
    }

    /** Ultimos accesos de todas las versiones de un archivo. */
    public function listarAccesos(int $grupoId): array
    {
        $sentencia = $this->pdo()->prepare('
            SELECT ac.fecha_crea, ac.sis_archivo_acceso_accion AS accion, a.sis_archivos_version AS version,
                   u.sis_usuarios_nombre
            FROM sis_archivo_acceso ac
            INNER JOIN sis_archivos a ON a.sis_archivos_id = ac.sis_archivos_id
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = ac.sis_usuarios_id
            WHERE COALESCE(a.sis_archivos_original_id, a.sis_archivos_id) = :grupo
            ORDER BY ac.fecha_crea DESC
            LIMIT 200
        ');
        $sentencia->execute(['grupo' => $grupoId]);
        return $sentencia->fetchAll();
    }
}
