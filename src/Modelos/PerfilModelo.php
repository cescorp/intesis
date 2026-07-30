<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class PerfilModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA PERFILES POR ALCANCE DE EMPRESA DEL USUARIO ACTUAL.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas): array
    {
        $filtro = $verTodas ? '' : 'WHERE p.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT p.*, e.sis_empresa_nombre_comercial,
                (SELECT count(*) FROM sis_usuario_empresa ue WHERE ue.sis_perfil_id = p.sis_perfil_id) AS total_usuarios
            FROM sis_perfil p
            INNER JOIN sis_empresa e ON e.sis_empresa_id = p.sis_empresa_id
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial, p.sis_perfil_nombre
        ");
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA CREAR O EDITAR PERFILES.
     * ***************************************************************************
     */
    public function listarEmpresasActivas(bool $verTodas, int $empresaId): array
    {
        $filtro = $verTodas ? '' : 'AND e.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT e.sis_empresa_id, e.sis_empresa_nombre_comercial
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE es.sis_estado_codigo = 'ACTIVO'
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial
        ");
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA UN PERFIL CON CODIGO GENERADO Y ESTADO ACTIVO.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_perfil (
                sis_empresa_id, sis_perfil_codigo, sis_perfil_nombre,
                sis_perfil_venta_todas_bodegas,
                sis_perfil_estado, usuario_crea
            )
            VALUES (:empresa_id, :codigo, :nombre, :venta_todas_bodegas, 1, :usuario_crea)
        ");
        $sentencia->execute([
            'empresa_id' => $datos['empresa_id'],
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'venta_todas_bodegas' => $datos['venta_todas_bodegas'] ? 1 : 0,
            'usuario_crea' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA NOMBRE Y CODIGO DE UN PERFIL NO PROTEGIDO.
     * ***************************************************************************
     */
    public function actualizar(int $perfilId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_perfil
            SET sis_empresa_id = :empresa_id,
                sis_perfil_codigo = :codigo,
                sis_perfil_nombre = :nombre,
                sis_perfil_venta_todas_bodegas = :venta_todas_bodegas,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_perfil_id = :perfil_id
        ");
        $sentencia->execute([
            'perfil_id' => $perfilId,
            'empresa_id' => $datos['empresa_id'],
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'venta_todas_bodegas' => $datos['venta_todas_bodegas'] ? 1 : 0,
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO NUMERICO DEL PERFIL.
     * ***************************************************************************
     */
    public function cambiarEstado(int $perfilId, int $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_perfil
            SET sis_perfil_estado = :estado,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_perfil_id = :perfil_id
        ");
        $sentencia->execute([
            'perfil_id' => $perfilId,
            'estado' => $estado,
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * ELIMINA UN PERFIL SIN USUARIOS ASIGNADOS.
     * ***************************************************************************
     */
    public function eliminar(int $perfilId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("DELETE FROM sis_perfil_permisos WHERE sis_perfil_id = :perfil_id");
            $sentencia->execute(['perfil_id' => $perfilId]);
            $sentencia = $pdo->prepare("DELETE FROM sis_perfil WHERE sis_perfil_id = :perfil_id");
            $sentencia->execute(['perfil_id' => $perfilId]);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * BUSCA UN PERFIL POR ID.
     * ***************************************************************************
     */
    public function buscar(int $perfilId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT p.*,
                (SELECT count(*) FROM sis_usuario_empresa ue WHERE ue.sis_perfil_id = p.sis_perfil_id) AS total_usuarios
            FROM sis_perfil p
            WHERE p.sis_perfil_id = :perfil_id
            LIMIT 1
        ");
        $sentencia->execute(['perfil_id' => $perfilId]);
        $perfil = $sentencia->fetch();

        return $perfil ?: null;
    }

    /**
     * ***************************************************************************
     * * VALIDA SI EXISTE CODIGO REPETIDO POR EMPRESA.
     * ***************************************************************************
     */
    public function existeCodigo(int $empresaId, string $codigo, ?int $perfilId = null): bool
    {
        $sql = 'SELECT 1 FROM sis_perfil WHERE sis_empresa_id = :empresa_id AND sis_perfil_codigo = :codigo';
        $parametros = ['empresa_id' => $empresaId, 'codigo' => $codigo];
        if ($perfilId !== null) {
            $sql .= ' AND sis_perfil_id <> :perfil_id';
            $parametros['perfil_id'] = $perfilId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * LISTA MENUS Y BOTONES PARA ARMAR ARBOL DE PERMISOS.
     * ***************************************************************************
     */
    public function listarMenusPermisos(): array
    {
        return $this->conexionBaseDatos->obtener()->query("
            SELECT sis_menu_id, sis_menu_nombre, sis_menu_padre, sis_menu_icono,
                   sis_menu_url, sis_menu_orden, sis_menu_tipo
            FROM sis_menu
            WHERE sis_menu_estado = 1
            ORDER BY sis_menu_padre NULLS FIRST, sis_menu_orden, sis_menu_nombre
        ")->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA IDS DE MENU ASIGNADOS A UN PERFIL.
     * ***************************************************************************
     */
    public function listarPermisosPerfil(int $perfilId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_menu_id
            FROM sis_perfil_permisos
            WHERE sis_perfil_id = :perfil_id
              AND sis_perfil_permisos_estado = 1
        ");
        $sentencia->execute(['perfil_id' => $perfilId]);

        return array_map('intval', array_column($sentencia->fetchAll(), 'sis_menu_id'));
    }

    /**
     * ***************************************************************************
     * * REEMPLAZA PERMISOS DE UN PERFIL CON LOS MENUS SELECCIONADOS.
     * ***************************************************************************
     */
    public function guardarPermisos(int $perfilId, array $menuIds, int $usuarioId): void
    {
        $perfil = $this->buscar($perfilId);
        if (!$perfil) {
            throw new \InvalidArgumentException('PERFIL_DATOS_OBLIGATORIOS');
        }

        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("DELETE FROM sis_perfil_permisos WHERE sis_perfil_id = :perfil_id");
            $sentencia->execute(['perfil_id' => $perfilId]);

            $sentencia = $pdo->prepare("
                INSERT INTO sis_perfil_permisos (
                    sis_empresa_id, sis_perfil_id, sis_menu_id,
                    sis_perfil_permisos_estado, usuario_crea
                )
                VALUES (:empresa_id, :perfil_id, :menu_id, 1, :usuario_crea)
            ");
            foreach (array_unique($menuIds) as $menuId) {
                $sentencia->execute([
                    'empresa_id' => (int) $perfil['sis_empresa_id'],
                    'perfil_id' => $perfilId,
                    'menu_id' => (int) $menuId,
                    'usuario_crea' => $usuarioId,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }
}
