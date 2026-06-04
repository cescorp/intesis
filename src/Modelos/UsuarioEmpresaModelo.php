<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;

final class UsuarioEmpresaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA USUARIOS ASIGNADOS SEGUN EL ALCANCE DE EMPRESA DEL PERFIL.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas): array
    {
        $filtro = $verTodas ? '' : 'AND ue.sis_empresa_id = :empresa_id';
        $sql = "
            SELECT
                ue.sis_usuario_empresa_id,
                u.sis_usuarios_id,
                ue.sis_empresa_id,
                ue.sis_perfil_id,
                ue.sis_usuario_empresa_predeterminada,
                e.sis_empresa_nombre_comercial,
                p.sis_perfil_codigo,
                p.sis_perfil_nombre,
                u.sis_usuarios_nombre,
                u.sis_usuarios_correo,
                es.sis_estado_codigo,
                es.sis_estado_nombre
            FROM sis_usuario_empresa ue
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = ue.sis_usuarios_id
            INNER JOIN sis_empresa e ON e.sis_empresa_id = ue.sis_empresa_id
            INNER JOIN sis_perfil p ON p.sis_perfil_id = ue.sis_perfil_id
            INNER JOIN sis_estado es ON es.sis_estado_id = ue.sis_estado_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial, u.sis_usuarios_nombre
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA ASIGNAR USUARIOS.
     * ***************************************************************************
     */
    public function listarEmpresasActivas(bool $verTodas, int $empresaId): array
    {
        $filtro = $verTodas ? '' : 'AND e.sis_empresa_id = :empresa_id';
        $sql = "
            SELECT e.sis_empresa_id, e.sis_empresa_nombre_comercial
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE es.sis_estado_codigo = 'ACTIVO'
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA PERFILES ACTIVOS DISPONIBLES POR EMPRESA.
     * ***************************************************************************
     */
    public function listarPerfilesActivos(bool $verTodas, int $empresaId): array
    {
        $filtro = $verTodas ? '' : 'AND p.sis_empresa_id = :empresa_id';
        $sql = "
            SELECT p.sis_perfil_id, p.sis_empresa_id, p.sis_perfil_codigo, p.sis_perfil_nombre
            FROM sis_perfil p
            WHERE p.sis_perfil_estado = 1
            {$filtro}
            ORDER BY p.sis_empresa_id, p.sis_perfil_nombre
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * CREA USUARIO GLOBAL Y UNA O VARIAS ASIGNACIONES EMPRESA PERFIL.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioCrea): void
    {
        $asignaciones = $datos['asignaciones'] ?? [[
            'empresa_id' => $datos['empresa_id'],
            'perfil_id' => $datos['perfil_id'],
        ]];
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $usuarioId = $this->buscarUsuarioIdPorCorreo($datos['correo']);
            if ($usuarioId === null) {
                $sentencia = $pdo->prepare("
                    INSERT INTO sis_usuarios (
                        sis_usuarios_nombre,
                        sis_usuarios_correo,
                        sis_usuarios_password,
                        sis_estado_id,
                        usuario_crea
                    )
                    VALUES (:nombre, :correo, :clave, :estado_id, :usuario_crea)
                    RETURNING sis_usuarios_id
                ");
                $sentencia->execute([
                    'nombre' => $datos['nombre'],
                    'correo' => $datos['correo'],
                    'clave' => password_hash($datos['clave'], PASSWORD_ARGON2ID),
                    'estado_id' => $this->obtenerEstadoId('ACTIVO'),
                    'usuario_crea' => $usuarioCrea,
                ]);
                $usuarioId = (int) $sentencia->fetchColumn();
            }

            foreach ($asignaciones as $asignacion) {
                $this->crearAsignacion($usuarioId, (int) $asignacion['empresa_id'], (int) $asignacion['perfil_id'], 'ACTIVO', $usuarioCrea);
            }
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA DATOS BASICOS Y LA ASIGNACION EMPRESA PERFIL DEL USUARIO.
     * ***************************************************************************
     */
    public function actualizar(int $asignacionId, array $datos, int $usuarioModifica): void
    {
        $asignacion = $this->buscarAsignacion($asignacionId);
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("
                UPDATE sis_usuarios
                SET sis_usuarios_nombre = :nombre,
                    sis_usuarios_correo = :correo,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE sis_usuarios_id = :usuario_id
            ");
            $sentencia->execute([
                'usuario_id' => $asignacion['sis_usuarios_id'],
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'usuario_modifica' => $usuarioModifica,
            ]);

            $sentencia = $pdo->prepare("
                UPDATE sis_usuario_empresa
                SET sis_empresa_id = :empresa_id,
                    sis_perfil_id = :perfil_id,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE sis_usuario_empresa_id = :asignacion_id
            ");
            $sentencia->execute([
                'asignacion_id' => $asignacionId,
                'empresa_id' => $datos['empresa_id'],
                'perfil_id' => $datos['perfil_id'],
                'usuario_modifica' => $usuarioModifica,
            ]);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA DATOS BASICOS Y MULTIPLES ASIGNACIONES DEL USUARIO.
     * ***************************************************************************
     */
    public function actualizarConAsignaciones(int $asignacionId, array $datos, int $usuarioModifica): void
    {
        $asignacionBase = $this->buscarAsignacion($asignacionId);
        $usuarioId = (int) $asignacionBase['sis_usuarios_id'];
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("
                UPDATE sis_usuarios
                SET sis_usuarios_nombre = :nombre,
                    sis_usuarios_correo = :correo,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE sis_usuarios_id = :usuario_id
            ");
            $sentencia->execute([
                'usuario_id' => $usuarioId,
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'usuario_modifica' => $usuarioModifica,
            ]);

            $predeterminadaAsignada = false;
            foreach ($datos['asignaciones'] as $asignacion) {
                $id = (int) ($asignacion['asignacion_id'] ?? 0);
                $estado = !empty($asignacion['inactivar']) ? 'INACTIVO' : 'ACTIVO';
                $predeterminada = !$predeterminadaAsignada && empty($asignacion['inactivar']) && !empty($asignacion['predeterminada']);
                if ($predeterminada) {
                    $predeterminadaAsignada = true;
                }

                if ($id > 0) {
                    $this->actualizarAsignacion($id, $usuarioId, (int) $asignacion['empresa_id'], (int) $asignacion['perfil_id'], $estado, $predeterminada, $usuarioModifica);
                    continue;
                }

                $this->crearAsignacion($usuarioId, (int) $asignacion['empresa_id'], (int) $asignacion['perfil_id'], $estado, $usuarioModifica, $predeterminada);
            }

            if (!$predeterminadaAsignada) {
                $this->marcarPrimeraActivaPredeterminada($usuarioId, $usuarioModifica);
            }
            $this->sincronizarEstadoGlobal($usuarioId, 'INACTIVO', $usuarioModifica);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO DE LA ASIGNACION Y SINCRONIZA EL ESTADO GLOBAL PRINCIPAL.
     * ***************************************************************************
     */
    public function cambiarEstado(int $asignacionId, string $estado, int $usuarioModifica): void
    {
        $estadoId = $this->obtenerEstadoId($estado);
        $asignacion = $this->buscarAsignacion($asignacionId);
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            $sentencia = $pdo->prepare("
                UPDATE sis_usuario_empresa
                SET sis_estado_id = :estado_id,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE sis_usuario_empresa_id = :asignacion_id
            ");
            $sentencia->execute([
                'asignacion_id' => $asignacionId,
                'estado_id' => $estadoId,
                'usuario_modifica' => $usuarioModifica,
            ]);

            $this->sincronizarEstadoGlobal((int) $asignacion['sis_usuarios_id'], $estado, $usuarioModifica);
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * RESTABLECE LA CLAVE GLOBAL DEL USUARIO CON HASH ARGON2ID.
     * ***************************************************************************
     */
    public function restablecerClave(int $asignacionId, string $clave, int $usuarioModifica): void
    {
        $asignacion = $this->buscarAsignacion($asignacionId);
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_usuarios
            SET sis_usuarios_password = :clave,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_usuarios_id = :usuario_id
        ");
        $sentencia->execute([
            'usuario_id' => $asignacion['sis_usuarios_id'],
            'clave' => password_hash($clave, PASSWORD_ARGON2ID),
            'usuario_modifica' => $usuarioModifica,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UNA ASIGNACION USUARIO EMPRESA POR ID.
     * ***************************************************************************
     */
    public function buscarAsignacion(int $asignacionId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT ue.*, p.sis_perfil_codigo, es.sis_estado_codigo
            FROM sis_usuario_empresa ue
            INNER JOIN sis_perfil p ON p.sis_perfil_id = ue.sis_perfil_id
            INNER JOIN sis_estado es ON es.sis_estado_id = ue.sis_estado_id
            WHERE ue.sis_usuario_empresa_id = :asignacion_id
            LIMIT 1
        ");
        $sentencia->execute(['asignacion_id' => $asignacionId]);
        $asignacion = $sentencia->fetch();

        return $asignacion ?: null;
    }

    /**
     * ***************************************************************************
     * * LISTA TODAS LAS ASIGNACIONES DE UN USUARIO GLOBAL.
     * ***************************************************************************
     */
    public function listarAsignacionesUsuario(int $usuarioId, bool $verTodas, int $empresaId): array
    {
        $filtro = $verTodas ? '' : 'AND ue.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                ue.sis_usuario_empresa_id,
                ue.sis_usuarios_id,
                ue.sis_empresa_id,
                ue.sis_perfil_id,
                ue.sis_usuario_empresa_predeterminada,
                es.sis_estado_codigo
            FROM sis_usuario_empresa ue
            INNER JOIN sis_estado es ON es.sis_estado_id = ue.sis_estado_id
            WHERE ue.sis_usuarios_id = :usuario_id
              AND es.sis_estado_codigo <> 'ELIMINADO'
              {$filtro}
            ORDER BY ue.sis_usuario_empresa_predeterminada DESC, ue.sis_usuario_empresa_id
        ");
        $parametros = ['usuario_id' => $usuarioId];
        if (!$verTodas) {
            $parametros['empresa_id'] = $empresaId;
        }
        $sentencia->execute($parametros);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI EXISTE OTRO USUARIO CON EL MISMO CORREO GLOBAL.
     * ***************************************************************************
     */
    public function existeCorreo(string $correo, ?int $usuarioId = null): bool
    {
        $sql = 'SELECT 1 FROM sis_usuarios WHERE lower(sis_usuarios_correo) = lower(:correo)';
        $parametros = ['correo' => $correo];
        if ($usuarioId !== null) {
            $sql .= ' AND sis_usuarios_id <> :usuario_id';
            $parametros['usuario_id'] = $usuarioId;
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI UN CORREO YA TIENE ASIGNACION EN UNA EMPRESA.
     * ***************************************************************************
     */
    public function existeCorreoEnEmpresa(string $correo, int $empresaId): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM sis_usuarios u
            INNER JOIN sis_usuario_empresa ue ON ue.sis_usuarios_id = u.sis_usuarios_id
            WHERE lower(u.sis_usuarios_correo) = lower(:correo)
              AND ue.sis_empresa_id = :empresa_id
            LIMIT 1
        ");
        $sentencia->execute([
            'correo' => $correo,
            'empresa_id' => $empresaId,
        ]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VERIFICA QUE UN PERFIL PERTENEZCA A LA EMPRESA SELECCIONADA.
     * ***************************************************************************
     */
    public function perfilPerteneceEmpresa(int $perfilId, int $empresaId): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM sis_perfil
            WHERE sis_perfil_id = :perfil_id
              AND sis_empresa_id = :empresa_id
              AND sis_perfil_estado = 1
            LIMIT 1
        ");
        $sentencia->execute([
            'perfil_id' => $perfilId,
            'empresa_id' => $empresaId,
        ]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * CUENTA SUPERUSUARIOS ACTIVOS DE UNA EMPRESA PARA PROTECCION OPERATIVA.
     * ***************************************************************************
     */
    public function contarSuperusuariosActivos(int $empresaId): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT count(*)
            FROM sis_usuario_empresa ue
            INNER JOIN sis_perfil p ON p.sis_perfil_id = ue.sis_perfil_id
            INNER JOIN sis_estado es ON es.sis_estado_id = ue.sis_estado_id
            WHERE ue.sis_empresa_id = :empresa_id
              AND p.sis_perfil_codigo = 'SUPERUSUARIO'
              AND es.sis_estado_codigo = 'ACTIVO'
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * OBTIENE EL ID DE ESTADO DE USUARIO SEGUN CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'SISTEMA'
              AND sis_estado_entidad = 'SIS_USUARIOS'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * CREA UNA ASIGNACION EMPRESA PERFIL PARA UN USUARIO GLOBAL.
     * ***************************************************************************
     */
    private function crearAsignacion(int $usuarioId, int $empresaId, int $perfilId, string $estado, int $usuarioCrea, bool $predeterminada = false): void
    {
        if ($predeterminada) {
            $this->limpiarPredeterminada($usuarioId, $usuarioCrea);
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_usuario_empresa (
                sis_usuarios_id,
                sis_empresa_id,
                sis_perfil_id,
                sis_estado_id,
                sis_usuario_empresa_predeterminada,
                usuario_crea
            )
            VALUES (:usuario_id, :empresa_id, :perfil_id, :estado_id, :predeterminada, :usuario_crea)
        ");
        $sentencia->bindValue(':usuario_id', $usuarioId, \PDO::PARAM_INT);
        $sentencia->bindValue(':empresa_id', $empresaId, \PDO::PARAM_INT);
        $sentencia->bindValue(':perfil_id', $perfilId, \PDO::PARAM_INT);
        $sentencia->bindValue(':estado_id', $this->obtenerEstadoId($estado), \PDO::PARAM_INT);
        $sentencia->bindValue(':predeterminada', $predeterminada, \PDO::PARAM_BOOL);
        $sentencia->bindValue(':usuario_crea', $usuarioCrea, \PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UNA ASIGNACION EXISTENTE DEL USUARIO.
     * ***************************************************************************
     */
    private function actualizarAsignacion(int $asignacionId, int $usuarioId, int $empresaId, int $perfilId, string $estado, bool $predeterminada, int $usuarioModifica): void
    {
        if ($predeterminada) {
            $this->limpiarPredeterminada($usuarioId, $usuarioModifica);
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_usuario_empresa
            SET sis_empresa_id = :empresa_id,
                sis_perfil_id = :perfil_id,
                sis_estado_id = :estado_id,
                sis_usuario_empresa_predeterminada = :predeterminada,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_usuario_empresa_id = :asignacion_id
              AND sis_usuarios_id = :usuario_id
        ");
        $sentencia->bindValue(':asignacion_id', $asignacionId, \PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_id', $usuarioId, \PDO::PARAM_INT);
        $sentencia->bindValue(':empresa_id', $empresaId, \PDO::PARAM_INT);
        $sentencia->bindValue(':perfil_id', $perfilId, \PDO::PARAM_INT);
        $sentencia->bindValue(':estado_id', $this->obtenerEstadoId($estado), \PDO::PARAM_INT);
        $sentencia->bindValue(':predeterminada', $estado === 'ACTIVO' && $predeterminada, \PDO::PARAM_BOOL);
        $sentencia->bindValue(':usuario_modifica', $usuarioModifica, \PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * LIMPIA LA MARCA PREDETERMINADA DE UN USUARIO.
     * ***************************************************************************
     */
    private function limpiarPredeterminada(int $usuarioId, int $usuarioModifica): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_usuario_empresa
            SET sis_usuario_empresa_predeterminada = FALSE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_usuarios_id = :usuario_id
        ");
        $sentencia->execute([
            'usuario_id' => $usuarioId,
            'usuario_modifica' => $usuarioModifica,
        ]);
    }

    /**
     * ***************************************************************************
     * * MARCA LA PRIMERA ASIGNACION ACTIVA SI NO HAY PREDETERMINADA.
     * ***************************************************************************
     */
    private function marcarPrimeraActivaPredeterminada(int $usuarioId, int $usuarioModifica): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_usuario_empresa
            SET sis_usuario_empresa_predeterminada = TRUE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_usuario_empresa_id = (
                SELECT ue.sis_usuario_empresa_id
                FROM sis_usuario_empresa ue
                INNER JOIN sis_estado es ON es.sis_estado_id = ue.sis_estado_id
                WHERE ue.sis_usuarios_id = :usuario_id_buscar
                  AND es.sis_estado_codigo = 'ACTIVO'
                ORDER BY ue.sis_usuario_empresa_id
                LIMIT 1
            )
        ");
        $sentencia->execute([
            'usuario_id_buscar' => $usuarioId,
            'usuario_modifica' => $usuarioModifica,
        ]);
    }

    /**
     * ***************************************************************************
     * * SINCRONIZA ESTADO GLOBAL SEGUN ASIGNACIONES ACTIVAS DEL USUARIO.
     * ***************************************************************************
     */
    private function sincronizarEstadoGlobal(int $usuarioId, string $estadoDefecto, int $usuarioModifica): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM sis_usuario_empresa ue
            INNER JOIN sis_estado es ON es.sis_estado_id = ue.sis_estado_id
            WHERE ue.sis_usuarios_id = :usuario_id
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ");
        $sentencia->execute(['usuario_id' => $usuarioId]);
        $estadoGlobal = $sentencia->fetchColumn() ? 'ACTIVO' : $estadoDefecto;

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_usuarios
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_usuarios_id = :usuario_id
        ");
        $sentencia->execute([
            'usuario_id' => $usuarioId,
            'estado_id' => $this->obtenerEstadoId($estadoGlobal),
            'usuario_modifica' => $usuarioModifica,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA EL ID GLOBAL DE UN USUARIO POR CORREO.
     * ***************************************************************************
     */
    private function buscarUsuarioIdPorCorreo(string $correo): ?int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_usuarios_id
            FROM sis_usuarios
            WHERE lower(sis_usuarios_correo) = lower(:correo)
            LIMIT 1
        ");
        $sentencia->execute(['correo' => $correo]);
        $usuarioId = $sentencia->fetchColumn();

        return $usuarioId === false ? null : (int) $usuarioId;
    }
}
