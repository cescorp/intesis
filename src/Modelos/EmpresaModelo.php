<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class EmpresaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS NO ELIMINADAS CON SU ESTADO ACTUAL.
     * ***************************************************************************
     */
    public function listar(): array
    {
        $sql = "
            SELECT
                e.sis_empresa_id,
                e.sis_empresa_ruc,
                e.sis_empresa_razon_social,
                e.sis_empresa_nombre_comercial,
                e.sis_empresa_direccion,
                e.sis_empresa_telefono,
                e.sis_empresa_email,
                CASE WHEN e.sis_empresa_obligado_contabilidad THEN 1 ELSE 0 END AS sis_empresa_obligado_contabilidad,
                CASE WHEN e.sis_empresa_contribuyente_especial THEN 1 ELSE 0 END AS sis_empresa_contribuyente_especial,
                es.sis_estado_codigo,
                es.sis_estado_nombre
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            ORDER BY e.sis_empresa_id
        ";

        return $this->conexionBaseDatos->obtener()->query($sql)->fetchAll();
    }

    /**
     * ***************************************************************************
     * * BUSCA UNA EMPRESA POR ID PARA EDICION O VALIDACION.
     * ***************************************************************************
     */
    public function buscarPorId(int $empresaId): ?array
    {
        $sql = "
            SELECT
                e.*,
                es.sis_estado_codigo
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE e.sis_empresa_id = :empresa_id
            LIMIT 1
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute(['empresa_id' => $empresaId]);
        $empresa = $sentencia->fetch();

        return $empresa ?: null;
    }

    /**
     * ***************************************************************************
     * * CREA UNA EMPRESA ACTIVA CON PERFILES BASE Y PERMISOS INICIALES.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $sql = "
            INSERT INTO sis_empresa (
                sis_empresa_ruc,
                sis_empresa_razon_social,
                sis_empresa_nombre_comercial,
                sis_empresa_direccion,
                sis_empresa_telefono,
                sis_empresa_email,
                sis_empresa_obligado_contabilidad,
                sis_empresa_contribuyente_especial,
                sis_estado_id,
                usuario_crea
            )
            VALUES (
                :ruc,
                :razon_social,
                :nombre_comercial,
                :direccion,
                :telefono,
                :email,
                :obligado_contabilidad,
                :contribuyente_especial,
                :estado_id,
                :usuario_crea
            )
            RETURNING sis_empresa_id
        ";

        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare($sql);
            $this->vincularDatosEmpresa($sentencia, $datos);
            $sentencia->bindValue(':estado_id', $this->obtenerEstadoId('ACTIVO'), PDO::PARAM_INT);
            $sentencia->bindValue(':usuario_crea', $usuarioId, PDO::PARAM_INT);
            $sentencia->execute();

            $empresaId = (int) $sentencia->fetchColumn();
            $this->crearPerfilesBase($empresaId, $usuarioId);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA LOS DATOS EDITABLES DE UNA EMPRESA EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $empresaId, array $datos, int $usuarioId): void
    {
        $sql = "
            UPDATE sis_empresa
            SET sis_empresa_ruc = :ruc,
                sis_empresa_razon_social = :razon_social,
                sis_empresa_nombre_comercial = :nombre_comercial,
                sis_empresa_direccion = :direccion,
                sis_empresa_telefono = :telefono,
                sis_empresa_email = :email,
                sis_empresa_obligado_contabilidad = :obligado_contabilidad,
                sis_empresa_contribuyente_especial = :contribuyente_especial,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_empresa_id = :empresa_id
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $this->vincularDatosEmpresa($sentencia, $datos);
        $sentencia->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * CAMBIA EL ESTADO LOGICO DE LA EMPRESA EN SIS_ESTADO.
     * ***************************************************************************
     */
    public function cambiarEstado(int $empresaId, string $codigoEstado, int $usuarioId): void
    {
        $sql = "
            UPDATE sis_empresa
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_empresa_id = :empresa_id
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'estado_id' => $this->obtenerEstadoId($codigoEstado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * OBTIENE EL ID DEL ESTADO DE EMPRESA SEGUN SU CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sql = "
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'SISTEMA'
              AND sis_estado_entidad = 'SIS_EMPRESA'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VINCULA DATOS DE EMPRESA USANDO TIPOS PDO COMPATIBLES CON POSTGRESQL.
     * ***************************************************************************
     */
    private function vincularDatosEmpresa(\PDOStatement $sentencia, array $datos): void
    {
        $sentencia->bindValue(':ruc', $datos['ruc']);
        $sentencia->bindValue(':razon_social', $datos['razon_social']);
        $sentencia->bindValue(':nombre_comercial', $datos['nombre_comercial']);
        $sentencia->bindValue(':direccion', $datos['direccion']);
        $sentencia->bindValue(':telefono', $datos['telefono']);
        $sentencia->bindValue(':email', $datos['email']);
        $sentencia->bindValue(':obligado_contabilidad', (bool) $datos['obligado_contabilidad'], PDO::PARAM_BOOL);
        $sentencia->bindValue(':contribuyente_especial', (bool) $datos['contribuyente_especial'], PDO::PARAM_BOOL);
    }

    /**
     * ***************************************************************************
     * * CREA LOS PERFILES OPERATIVOS BASE PARA UNA EMPRESA NUEVA.
     * ***************************************************************************
     */
    private function crearPerfilesBase(int $empresaId, int $usuarioId): void
    {
        $perfiles = [
            'SUPERUSUARIO' => 'SUPERUSUARIO',
            'GERENCIA' => 'GERENCIA',
            'CONTADOR' => 'CONTADOR',
            'GERENTE_VENTAS' => 'GERENTE DE VENTAS',
            'VENDEDOR' => 'VENDEDOR',
            'COMPRAS' => 'COMPRAS',
            'BODEGUERO' => 'BODEGUERO',
        ];

        foreach ($perfiles as $codigo => $nombre) {
            $perfilId = $this->crearPerfilBase($empresaId, $codigo, $nombre, $usuarioId);
            $this->crearPermisosPerfilBase($empresaId, $perfilId, $codigo, $usuarioId);
        }
    }

    /**
     * ***************************************************************************
     * * INSERTA UN PERFIL BASE SI NO EXISTE EN LA EMPRESA.
     * ***************************************************************************
     */
    private function crearPerfilBase(int $empresaId, string $codigo, string $nombre, int $usuarioId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_perfil (
                sis_empresa_id,
                sis_perfil_codigo,
                sis_perfil_nombre,
                sis_perfil_estado,
                usuario_crea
            )
            SELECT :empresa_id_insert, CAST(:codigo_insert AS VARCHAR(20)), CAST(:nombre_insert AS VARCHAR(75)), 1, :usuario_crea_insert
            WHERE NOT EXISTS (
                SELECT 1
                FROM sis_perfil
                WHERE sis_empresa_id = :empresa_id_buscar
                  AND sis_perfil_codigo = CAST(:codigo_buscar AS VARCHAR(20))
            )
            RETURNING sis_perfil_id
        ");
        $sentencia->execute([
            'empresa_id_insert' => $empresaId,
            'codigo_insert' => $codigo,
            'nombre_insert' => $nombre,
            'usuario_crea_insert' => $usuarioId,
            'empresa_id_buscar' => $empresaId,
            'codigo_buscar' => $codigo,
        ]);
        $perfilId = $sentencia->fetchColumn();

        if ($perfilId !== false) {
            return (int) $perfilId;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_perfil_id
            FROM sis_perfil
            WHERE sis_empresa_id = :empresa_id
              AND sis_perfil_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'codigo' => $codigo,
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * ASIGNA PERMISOS INICIALES A PERFILES BASE DE LA EMPRESA.
     * ***************************************************************************
     */
    private function crearPermisosPerfilBase(int $empresaId, int $perfilId, string $codigoPerfil, int $usuarioId): void
    {
        $urls = [];
        if ($codigoPerfil === 'SUPERUSUARIO') {
            $urls = null;
        } elseif (in_array($codigoPerfil, ['GERENCIA', 'CONTADOR'], true)) {
            $urls = [
                '/sistema',
                '/sistema/empresas',
                '/sistema/empresas/ver',
                '/sistema/usuarios',
                '/sistema/usuarios/ver',
            ];
        } else {
            return;
        }

        $filtroUrls = $urls === null ? '' : 'AND sis_menu_url IN (' . implode(',', array_fill(0, count($urls), '?')) . ')';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_menu_id
            FROM sis_menu
            WHERE sis_menu_estado = 1
            {$filtroUrls}
        ");
        $sentencia->execute($urls ?? []);
        $menus = $sentencia->fetchAll();

        $sentenciaPermiso = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_perfil_permisos (
                sis_empresa_id,
                sis_perfil_id,
                sis_menu_id,
                sis_perfil_permisos_estado,
                usuario_crea
            )
            SELECT :empresa_id, :perfil_id, :menu_id, 1, :usuario_crea
            WHERE NOT EXISTS (
                SELECT 1
                FROM sis_perfil_permisos
                WHERE sis_empresa_id = :empresa_id
                  AND sis_perfil_id = :perfil_id
                  AND sis_menu_id = :menu_id
            )
        ");

        foreach ($menus as $menu) {
            $sentenciaPermiso->execute([
                'empresa_id' => $empresaId,
                'perfil_id' => $perfilId,
                'menu_id' => (int) $menu['sis_menu_id'],
                'usuario_crea' => $usuarioId,
            ]);
        }
    }
}
