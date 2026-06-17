<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class LicenciaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA LICENCIAS ACTIVAS POR EMPRESA (O TODAS PARA SUPERUSUARIO).
     * ***************************************************************************
     */
    public function listar(?int $empresaId): array
    {
        $filtro = $empresaId === null ? '' : 'AND l.sis_empresa_id = :empresa_id';
        $sql = "
            SELECT
                l.sis_licencia_id,
                l.sis_empresa_id,
                e.sis_empresa_razon_social,
                l.sis_modulo_id,
                m.sis_modulo_nombre,
                l.sis_licencia_tipo,
                l.sis_licencia_fecha_inicio,
                l.sis_licencia_fecha_fin,
                l.sis_licencia_estado,
                l.fecha_crea
            FROM sis_licencia l
            INNER JOIN sis_empresa e ON e.sis_empresa_id = l.sis_empresa_id
            INNER JOIN sis_modulo m ON m.sis_modulo_id = l.sis_modulo_id
            WHERE l.sis_licencia_estado <> 'E'
            {$filtro}
            ORDER BY l.sis_empresa_id, m.sis_modulo_nombre
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($empresaId === null ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA TODOS LOS MÓDULOS DISPONIBLES DEL SISTEMA.
     * ***************************************************************************
     */
    public function listarModulos(): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare(
            'SELECT sis_modulo_id, sis_modulo_nombre, sis_modulo_descripcion FROM sis_modulo WHERE sis_modulo_estado = 1 ORDER BY sis_modulo_nombre'
        );
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA EL FORMULARIO DE GENERACIÓN.
     * ***************************************************************************
     */
    public function listarEmpresasActivas(): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT e.sis_empresa_id, e.sis_empresa_razon_social, e.sis_empresa_ruc
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE es.sis_estado_codigo = 'ACTIVO'
            ORDER BY e.sis_empresa_razon_social
        ");
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * GUARDA O ACTUALIZA UNA LICENCIA DESDE EL JSON SUBIDO.
     * ***************************************************************************
     */
    public function guardarDesdeJson(int $empresaId, array $json, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            foreach ($json['modulos'] ?? [] as $modulo) {
                $moduloId    = (int) ($modulo['modulo_id'] ?? 0);
                $tipo        = trim((string) ($json['tipo'] ?? 'TRIAL'));
                $fechaInicio = trim((string) ($json['fecha_inicio'] ?? date('Y-m-d')));
                $fechaFin    = trim((string) ($json['fecha_fin'] ?? date('Y-m-d', strtotime('+1 year'))));

                if ($moduloId <= 0) {
                    continue;
                }

                $existe = $pdo->prepare("
                    SELECT sis_licencia_id FROM sis_licencia
                    WHERE sis_empresa_id = :empresa_id AND sis_modulo_id = :modulo_id
                    LIMIT 1
                ");
                $existe->execute(['empresa_id' => $empresaId, 'modulo_id' => $moduloId]);
                $licenciaId = $existe->fetchColumn();

                if ($licenciaId) {
                    $pdo->prepare("
                        UPDATE sis_licencia
                        SET sis_licencia_tipo        = :tipo,
                            sis_licencia_fecha_inicio = :fecha_inicio,
                            sis_licencia_fecha_fin    = :fecha_fin,
                            sis_licencia_estado       = 'A',
                            sis_licencia_json         = :json,
                            usuario_modifica          = :usuario_modifica,
                            fecha_modifica            = now()
                        WHERE sis_licencia_id = :id
                    ")->execute([
                        'tipo'             => $tipo,
                        'fecha_inicio'     => $fechaInicio,
                        'fecha_fin'        => $fechaFin,
                        'json'             => json_encode($json, JSON_UNESCAPED_UNICODE),
                        'usuario_modifica' => $usuarioId,
                        'id'               => $licenciaId,
                    ]);
                } else {
                    $pdo->prepare("
                        INSERT INTO sis_licencia
                            (sis_empresa_id, sis_modulo_id, sis_licencia_tipo, sis_licencia_fecha_inicio, sis_licencia_fecha_fin, sis_licencia_estado, sis_licencia_json, usuario_crea)
                        VALUES
                            (:empresa_id, :modulo_id, :tipo, :fecha_inicio, :fecha_fin, 'A', :json, :usuario_crea)
                    ")->execute([
                        'empresa_id'   => $empresaId,
                        'modulo_id'    => $moduloId,
                        'tipo'         => $tipo,
                        'fecha_inicio' => $fechaInicio,
                        'fecha_fin'    => $fechaFin,
                        'json'         => json_encode($json, JSON_UNESCAPED_UNICODE),
                        'usuario_crea' => $usuarioId,
                    ]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
