<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class EstadoModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA ESTADOS GLOBALES DEL SISTEMA.
     * ***************************************************************************
     */
    public function listar(): array
    {
        return $this->conexionBaseDatos->obtener()->query("
            SELECT *,
                (
                    SELECT count(*)
                    FROM information_schema.constraint_column_usage ccu
                    WHERE ccu.table_schema = 'public'
                      AND ccu.column_name = 'sis_estado_id'
                ) AS total_referencias
            FROM sis_estado
            ORDER BY sis_estado_modulo, sis_estado_entidad, sis_estado_orden, sis_estado_nombre
        ")->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA UN ESTADO GLOBAL.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_estado (
                sis_estado_modulo, sis_estado_entidad, sis_estado_codigo,
                sis_estado_nombre, sis_estado_descripcion, sis_estado_orden,
                sis_estado_activo, usuario_crea
            )
            VALUES (:modulo, :entidad, :codigo, :nombre, :descripcion, :orden, TRUE, :usuario_crea)
        ");
        $sentencia->execute([
            'modulo' => $datos['modulo'],
            'entidad' => $datos['entidad'],
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'orden' => $datos['orden'],
            'usuario_crea' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA DATOS EDITABLES DE UN ESTADO.
     * ***************************************************************************
     */
    public function actualizar(int $estadoId, array $datos, bool $permitirClave, int $usuarioId): void
    {
        $sqlClave = $permitirClave
            ? 'sis_estado_modulo = :modulo, sis_estado_entidad = :entidad, sis_estado_codigo = :codigo,'
            : '';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_estado
            SET {$sqlClave}
                sis_estado_nombre = :nombre,
                sis_estado_descripcion = :descripcion,
                sis_estado_orden = :orden,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_estado_id = :estado_id
        ");
        $parametros = [
            'estado_id' => $estadoId,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'orden' => $datos['orden'],
            'usuario_modifica' => $usuarioId,
        ];
        if ($permitirClave) {
            $parametros['modulo'] = $datos['modulo'];
            $parametros['entidad'] = $datos['entidad'];
            $parametros['codigo'] = $datos['codigo'];
        }
        $sentencia->execute($parametros);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO DEL CATALOGO.
     * ***************************************************************************
     */
    public function cambiarActivo(int $estadoId, bool $activo, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_estado
            SET sis_estado_activo = :activo,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_estado_id = :estado_id
        ");
        $sentencia->bindValue(':estado_id', $estadoId, PDO::PARAM_INT);
        $sentencia->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * BUSCA ESTADO POR ID.
     * ***************************************************************************
     */
    public function buscar(int $estadoId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("SELECT * FROM sis_estado WHERE sis_estado_id = :estado_id");
        $sentencia->execute(['estado_id' => $estadoId]);
        $estado = $sentencia->fetch();

        return $estado ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI EXISTE CODIGO REPETIDO POR MODULO Y ENTIDAD.
     * ***************************************************************************
     */
    public function existeCodigo(string $modulo, string $entidad, string $codigo, ?int $estadoId = null): bool
    {
        $sql = "SELECT 1 FROM sis_estado WHERE sis_estado_modulo = :modulo AND sis_estado_entidad = :entidad AND sis_estado_codigo = :codigo";
        $parametros = ['modulo' => $modulo, 'entidad' => $entidad, 'codigo' => $codigo];
        if ($estadoId !== null) {
            $sql .= ' AND sis_estado_id <> :estado_id';
            $parametros['estado_id'] = $estadoId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI UN ESTADO ESTA USADO EN TABLAS PRINCIPALES.
     * ***************************************************************************
     */
    public function estaUsado(int $estadoId): bool
    {
        $tablas = [
            'sis_empresa', 'sis_usuarios', 'sis_usuario_empresa',
            'sis_tipo_documento', 'sis_secuencias',
            'com_documento', 'com_proveedor', 'ven_documento', 'ven_cliente',
            'inv_producto', 'inv_bodega', 'inv_movimientos',
            'con_asiento', 'con_plan_cuentas',
        ];
        foreach ($tablas as $tabla) {
            $sentencia = $this->conexionBaseDatos->obtener()->prepare("SELECT 1 FROM {$tabla} WHERE sis_estado_id = :estado_id LIMIT 1");
            $sentencia->execute(['estado_id' => $estadoId]);
            if ($sentencia->fetchColumn()) {
                return true;
            }
        }

        return false;
    }
}
