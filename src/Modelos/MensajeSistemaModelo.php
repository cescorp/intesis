<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\RegistroErrores;
use PDO;

final class MensajeSistemaModelo
{
    public function __construct(
        private ConexionBaseDatos $conexionBaseDatos,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * LISTA MENSAJES ACTIVOS POR CODIGO PARA USO EN PHP Y JAVASCRIPT.
     * ***************************************************************************
     */
    public function listarPorCodigos(array $codigos): array
    {
        if (!$codigos) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($codigos), '?'));
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                sis_mensaje_errores_codigo,
                sis_mensaje_errores_tipo,
                sis_mensaje_errores_titulo,
                sis_mensaje_errores_mensaje,
                sis_mensaje_errores_icono
            FROM sis_mensaje_errores
            WHERE sis_mensaje_errores_activo = TRUE
              AND sis_mensaje_errores_codigo IN ({$marcadores})
        ");
        $sentencia->execute(array_values($codigos));

        $mensajes = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $mensajes[$fila['sis_mensaje_errores_codigo']] = [
                'tipo' => strtolower((string) $fila['sis_mensaje_errores_tipo']),
                'titulo' => $fila['sis_mensaje_errores_titulo'],
                'texto' => $fila['sis_mensaje_errores_mensaje'],
                'icono' => strtolower((string) $fila['sis_mensaje_errores_icono']),
            ];
        }

        foreach ($codigos as $codigo) {
            if (!isset($mensajes[$codigo])) {
                $this->registroErrores->escribir('MENSAJE NO CONFIGURADO: ' . $codigo);
                $mensajes[$codigo] = [
                    'tipo' => 'alerta',
                    'titulo' => 'Mensaje no configurado',
                    'texto' => 'Codigo pendiente de configurar: ' . $codigo,
                    'icono' => 'warning',
                ];
            }
        }

        return $mensajes;
    }

    /**
     * ***************************************************************************
     * * OBTIENE UN MENSAJE POR CODIGO CON FALLBACK Y CODIGO VISIBLE.
     * ***************************************************************************
     */
    public function obtener(string $codigo): array
    {
        return $this->listarPorCodigos([$codigo])[$codigo];
    }

    /**
     * ***************************************************************************
     * * LISTA TODOS LOS MENSAJES CONFIGURABLES DEL SISTEMA.
     * ***************************************************************************
     */
    public function listarTodos(): array
    {
        return $this->conexionBaseDatos->obtener()->query("
            SELECT *
            FROM sis_mensaje_errores
            ORDER BY sis_mensaje_errores_modulo, sis_mensaje_errores_entidad, sis_mensaje_errores_codigo
        ")->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA UN MENSAJE CONFIGURABLE.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_mensaje_errores (
                sis_mensaje_errores_codigo, sis_mensaje_errores_tipo,
                sis_mensaje_errores_titulo, sis_mensaje_errores_mensaje,
                sis_mensaje_errores_icono, sis_mensaje_errores_modulo,
                sis_mensaje_errores_entidad, sis_mensaje_errores_activo,
                usuario_crea
            )
            VALUES (:codigo, :tipo, :titulo, :mensaje, :icono, :modulo, :entidad, TRUE, :usuario_crea)
        ");
        $sentencia->execute($this->parametrosMensaje($datos, $usuarioId));
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UN MENSAJE CONFIGURABLE.
     * ***************************************************************************
     */
    public function actualizar(int $mensajeId, array $datos, int $usuarioId): void
    {
        $parametros = $this->parametrosMensaje($datos, $usuarioId);
        $parametros['mensaje_id'] = $mensajeId;
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_mensaje_errores
            SET sis_mensaje_errores_codigo = :codigo,
                sis_mensaje_errores_tipo = :tipo,
                sis_mensaje_errores_titulo = :titulo,
                sis_mensaje_errores_mensaje = :mensaje,
                sis_mensaje_errores_icono = :icono,
                sis_mensaje_errores_modulo = :modulo,
                sis_mensaje_errores_entidad = :entidad,
                usuario_modifica = :usuario_crea,
                fecha_modifica = now()
            WHERE sis_mensaje_errores_id = :mensaje_id
        ");
        $sentencia->execute($parametros);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO DE UN MENSAJE.
     * ***************************************************************************
     */
    public function cambiarActivo(int $mensajeId, bool $activo, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_mensaje_errores
            SET sis_mensaje_errores_activo = :activo,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_mensaje_errores_id = :mensaje_id
        ");
        $sentencia->bindValue(':mensaje_id', $mensajeId, PDO::PARAM_INT);
        $sentencia->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * BUSCA MENSAJE POR ID.
     * ***************************************************************************
     */
    public function buscarPorId(int $mensajeId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT * FROM sis_mensaje_errores WHERE sis_mensaje_errores_id = :mensaje_id
        ");
        $sentencia->execute(['mensaje_id' => $mensajeId]);
        $mensaje = $sentencia->fetch();

        return $mensaje ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA CODIGO REPETIDO EN MENSAJES.
     * ***************************************************************************
     */
    public function existeCodigo(string $codigo, ?int $mensajeId = null): bool
    {
        $sql = 'SELECT 1 FROM sis_mensaje_errores WHERE sis_mensaje_errores_codigo = :codigo';
        $parametros = ['codigo' => $codigo];
        if ($mensajeId !== null) {
            $sql .= ' AND sis_mensaje_errores_id <> :mensaje_id';
            $parametros['mensaje_id'] = $mensajeId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * PREPARA PARAMETROS DE MENSAJE PARA INSERT Y UPDATE.
     * ***************************************************************************
     */
    private function parametrosMensaje(array $datos, int $usuarioId): array
    {
        return [
            'codigo' => $datos['codigo'],
            'tipo' => $datos['tipo'],
            'titulo' => $datos['titulo'],
            'mensaje' => $datos['mensaje'],
            'icono' => $datos['icono'],
            'modulo' => $datos['modulo'],
            'entidad' => $datos['entidad'],
            'usuario_crea' => $usuarioId,
        ];
    }
}
