<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class MenuModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA MENUS AUTORIZADOS PARA EL PERFIL DEL USUARIO EN HASTA TRES NIVELES.
     * ***************************************************************************
     */
    public function listarMenusPorPerfil(int $empresaId, int $perfilId): array
    {
        $sql = "
            SELECT
                m.sis_menu_id,
                m.sis_menu_nombre,
                m.sis_menu_padre,
                m.sis_menu_icono,
                m.sis_menu_url,
                m.sis_menu_orden,
                m.sis_menu_tipo
            FROM sis_menu m
            INNER JOIN sis_perfil_permisos pp ON pp.sis_menu_id = m.sis_menu_id
            WHERE pp.sis_empresa_id = :empresa_id
              AND pp.sis_perfil_id = :perfil_id
              AND pp.sis_perfil_permisos_estado = 1
              AND m.sis_menu_estado = 1
              AND m.sis_menu_tipo = 'M'
            ORDER BY m.sis_menu_padre NULLS FIRST, m.sis_menu_orden, m.sis_menu_nombre
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'perfil_id' => $perfilId,
        ]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI EL PERFIL TIENE ACCESO A UNA URL DE MENU O ACCION.
     * ***************************************************************************
     */
    public function tienePermiso(int $empresaId, int $perfilId, string $url): bool
    {
        $sql = "
            SELECT 1
            FROM sis_menu m
            INNER JOIN sis_perfil_permisos pp ON pp.sis_menu_id = m.sis_menu_id
            WHERE pp.sis_empresa_id = :empresa_id
              AND pp.sis_perfil_id = :perfil_id
              AND pp.sis_perfil_permisos_estado = 1
              AND m.sis_menu_estado = 1
              AND m.sis_menu_url = :url
            LIMIT 1
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'perfil_id' => $perfilId,
            'url' => $url,
        ]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * LISTA TODOS LOS MENUS Y BOTONES DEL SISTEMA PARA ADMINISTRACION GLOBAL.
     * ***************************************************************************
     */
    public function listarTodos(): array
    {
        return $this->conexionBaseDatos->obtener()->query("
            SELECT
                m.*,
                p.sis_menu_nombre AS sis_menu_padre_nombre,
                (SELECT count(*) FROM sis_menu h WHERE h.sis_menu_padre = m.sis_menu_id) AS total_hijos,
                (SELECT count(*) FROM sis_perfil_permisos pp WHERE pp.sis_menu_id = m.sis_menu_id) AS total_permisos
            FROM sis_menu m
            LEFT JOIN sis_menu p ON p.sis_menu_id = m.sis_menu_padre
            ORDER BY m.sis_menu_padre NULLS FIRST, m.sis_menu_orden, m.sis_menu_nombre
        ")->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA MENUS TIPO M PARA USARLOS COMO PADRES VALIDOS.
     * ***************************************************************************
     */
    public function listarMenusPadre(): array
    {
        return $this->conexionBaseDatos->obtener()->query("
            SELECT sis_menu_id, sis_menu_nombre, sis_menu_padre, sis_menu_url
            FROM sis_menu
            WHERE sis_menu_estado = 1
              AND sis_menu_tipo = 'M'
            ORDER BY sis_menu_padre NULLS FIRST, sis_menu_orden, sis_menu_nombre
        ")->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA UN MENU O BOTON Y ASIGNA PERMISO A SUPERUSUARIOS.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): int
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("
                INSERT INTO sis_menu (
                    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
                    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
                )
                VALUES (
                    :nombre, :padre, :icono, :url,
                    :orden, 1, :tipo, :usuario_crea
                )
                RETURNING sis_menu_id
            ");
            $sentencia->execute([
                'nombre' => $datos['nombre'],
                'padre' => $datos['padre'],
                'icono' => $datos['icono'],
                'url' => $datos['url'],
                'orden' => $datos['orden'],
                'tipo' => $datos['tipo'],
                'usuario_crea' => $usuarioId,
            ]);
            $menuId = (int) $sentencia->fetchColumn();
            $this->asignarSuperusuario($menuId, $usuarioId);

            if ($datos['tipo'] === 'M' && !empty($datos['crear_ver'])) {
                $accionId = $this->crearAccionVer($menuId, $datos, $usuarioId);
                $this->asignarSuperusuario($accionId, $usuarioId);
            }

            $pdo->commit();
            return $menuId;
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA DATOS EDITABLES DE UN MENU O BOTON.
     * ***************************************************************************
     */
    public function actualizar(int $menuId, array $datos, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_menu
            SET sis_menu_nombre = :nombre,
                sis_menu_padre = :padre,
                sis_menu_icono = :icono,
                sis_menu_url = :url,
                sis_menu_orden = :orden,
                sis_menu_tipo = :tipo,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_menu_id = :menu_id
        ");
        $sentencia->execute([
            'menu_id' => $menuId,
            'nombre' => $datos['nombre'],
            'padre' => $datos['padre'],
            'icono' => $datos['icono'],
            'url' => $datos['url'],
            'orden' => $datos['orden'],
            'tipo' => $datos['tipo'],
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO DE VISIBILIDAD DEL MENU O BOTON.
     * ***************************************************************************
     */
    public function cambiarEstado(int $menuId, int $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_menu
            SET sis_menu_estado = :estado,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_menu_id = :menu_id
        ");
        $sentencia->execute([
            'menu_id' => $menuId,
            'estado' => $estado,
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UN MENU O BOTON POR ID.
     * ***************************************************************************
     */
    public function buscar(int $menuId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT * FROM sis_menu WHERE sis_menu_id = :menu_id LIMIT 1
        ");
        $sentencia->execute(['menu_id' => $menuId]);
        $menu = $sentencia->fetch();

        return $menu ?: null;
    }

    /**
     * ***************************************************************************
     * * CALCULA EL NIVEL JERARQUICO DE UN MENU PARA LIMITAR HASTA TRES NIVELES.
     * ***************************************************************************
     */
    public function calcularNivel(int $menuId): int
    {
        $nivel = 0;
        $actual = $this->buscar($menuId);
        while ($actual) {
            $nivel++;
            $padreId = (int) ($actual['sis_menu_padre'] ?? 0);
            $actual = $padreId > 0 ? $this->buscar($padreId) : null;
        }

        return $nivel;
    }

    /**
     * ***************************************************************************
     * * VALIDA URL UNICA EN SIS_MENU.
     * ***************************************************************************
     */
    public function existeUrl(string $url, ?int $menuId = null): bool
    {
        $sql = 'SELECT 1 FROM sis_menu WHERE sis_menu_url = :url';
        $parametros = ['url' => $url];
        if ($menuId !== null) {
            $sql .= ' AND sis_menu_id <> :menu_id';
            $parametros['menu_id'] = $menuId;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * CREA ACCION VER AUTOMATICA PARA UN MENU NUEVO.
     * ***************************************************************************
     */
    private function crearAccionVer(int $menuId, array $datos, int $usuarioId): int
    {
        $url = rtrim((string) $datos['url'], '/') . '/ver';
        if ($this->existeUrl($url)) {
            return 0;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_menu (
                sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
                sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
            )
            VALUES (:nombre, :padre, 'bi bi-eye', :url, 1, 1, 'B', :usuario_crea)
            RETURNING sis_menu_id
        ");
        $sentencia->execute([
            'nombre' => 'Ver ' . $datos['nombre'],
            'padre' => $menuId,
            'url' => $url,
            'usuario_crea' => $usuarioId,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ASIGNA UN MENU O BOTON A TODOS LOS PERFILES SUPERUSUARIO.
     * ***************************************************************************
     */
    private function asignarSuperusuario(int $menuId, int $usuarioId): void
    {
        if ($menuId <= 0) {
            return;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_perfil_permisos (
                sis_empresa_id, sis_perfil_id, sis_menu_id,
                sis_perfil_permisos_estado, usuario_crea
            )
            SELECT p.sis_empresa_id, p.sis_perfil_id, :menu_id_insert, 1, :usuario_crea
            FROM sis_perfil p
            WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
              AND NOT EXISTS (
                  SELECT 1
                  FROM sis_perfil_permisos pp
                  WHERE pp.sis_empresa_id = p.sis_empresa_id
                    AND pp.sis_perfil_id = p.sis_perfil_id
                    AND pp.sis_menu_id = :menu_id_buscar
              )
        ");
        $sentencia->execute([
            'menu_id_insert' => $menuId,
            'menu_id_buscar' => $menuId,
            'usuario_crea' => $usuarioId,
        ]);
    }
}
