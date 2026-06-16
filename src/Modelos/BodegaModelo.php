<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use Intesis\Nucleo\ConexionBaseDatos;
use PDO;

final class BodegaModelo
{
    public function __construct(private ConexionBaseDatos $conexionBaseDatos)
    {
    }

    /**
     * ***************************************************************************
     * * LISTA BODEGAS RESPETANDO EMPRESA ACTIVA O ALCANCE GLOBAL.
     * ***************************************************************************
     */
    public function listar(int $empresaId, bool $verTodas): array
    {
        $filtro = $verTodas ? '' : 'AND b.sis_empresa_id = :empresa_id';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                b.*,
                e.sis_empresa_nombre_comercial,
                es.sis_estado_codigo,
                es.sis_estado_nombre,
                (SELECT COUNT(*) FROM inv_bodega_usuarios bu
                 WHERE bu.inv_bodega_id = b.inv_bodega_id
                   AND bu.inv_bodega_usuarios_estado = 1) AS total_usuarios
            FROM inv_bodega b
            INNER JOIN sis_empresa e ON e.sis_empresa_id = b.sis_empresa_id
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtro}
            ORDER BY e.sis_empresa_nombre_comercial, b.inv_bodega_codigo
        ");
        $sentencia->execute($verTodas ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA EMPRESAS ACTIVAS PARA FILTROS Y FORMULARIOS.
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
     * * CREA UNA BODEGA DE INVENTARIO.
     * ***************************************************************************
     */
    public function crear(array $datos, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            if ($datos['principal']) {
                $this->limpiarPrincipal((int) $datos['empresa_id'], $usuarioId);
            }
            $sentencia = $pdo->prepare("
                INSERT INTO inv_bodega (
                    sis_empresa_id, inv_bodega_codigo, inv_bodega_nombre,
                    inv_bodega_descripcion, inv_bodega_direccion,
                    inv_bodega_es_principal, inv_bodega_virtual,
                    inv_bodega_negativos, inv_bodega_autoaprobado,
                    inv_bodega_establecimiento, inv_bodega_punto_emision,
                    sis_estado_id, usuario_crea
                )
                VALUES (
                    :empresa_id, :codigo, :nombre,
                    :descripcion, :direccion,
                    :principal, :virtual,
                    :negativos, :autoaprobado,
                    :establecimiento, :punto_emision,
                    :estado_id, :usuario_crea
                )
            ");
            $this->vincularDatos($sentencia, $datos);
            $sentencia->bindValue(':estado_id', $this->obtenerEstadoId('ACTIVO'), PDO::PARAM_INT);
            $sentencia->bindValue(':usuario_crea', $usuarioId, PDO::PARAM_INT);
            $sentencia->execute();
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UNA BODEGA EXISTENTE.
     * ***************************************************************************
     */
    public function actualizar(int $bodegaId, array $datos, int $usuarioId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            if ($datos['principal']) {
                $this->limpiarPrincipal((int) $datos['empresa_id'], $usuarioId, $bodegaId);
            }
            $sentencia = $pdo->prepare("
                UPDATE inv_bodega
                SET sis_empresa_id = :empresa_id,
                    inv_bodega_codigo = :codigo,
                    inv_bodega_nombre = :nombre,
                    inv_bodega_descripcion = :descripcion,
                    inv_bodega_direccion = :direccion,
                    inv_bodega_es_principal = :principal,
                    inv_bodega_virtual = :virtual,
                    inv_bodega_negativos = :negativos,
                    inv_bodega_autoaprobado = :autoaprobado,
                    inv_bodega_establecimiento = :establecimiento,
                    inv_bodega_punto_emision = :punto_emision,
                    usuario_modifica = :usuario_modifica,
                    fecha_modifica = now()
                WHERE inv_bodega_id = :bodega_id
            ");
            $this->vincularDatos($sentencia, $datos);
            $sentencia->bindValue(':bodega_id', $bodegaId, PDO::PARAM_INT);
            $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
            $sentencia->execute();
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE UNA BODEGA.
     * ***************************************************************************
     */
    public function cambiarEstado(int $bodegaId, string $estado, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_bodega
            SET sis_estado_id = :estado_id,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_bodega_id = :bodega_id
        ");
        $sentencia->execute([
            'bodega_id' => $bodegaId,
            'estado_id' => $this->obtenerEstadoId($estado),
            'usuario_modifica' => $usuarioId,
        ]);
    }

    /**
     * ***************************************************************************
     * * BUSCA UNA BODEGA POR ID.
     * ***************************************************************************
     */
    public function buscar(int $bodegaId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT b.*, es.sis_estado_codigo
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE b.inv_bodega_id = :bodega_id
            LIMIT 1
        ");
        $sentencia->execute(['bodega_id' => $bodegaId]);
        $bodega = $sentencia->fetch();

        return $bodega ?: null;
    }

    /**
     * ***************************************************************************
     * * VERIFICA CODIGO REPETIDO POR EMPRESA.
     * ***************************************************************************
     */
    public function existeCodigo(int $empresaId, string $codigo, ?int $bodegaId = null): bool
    {
        $sql = "
            SELECT 1
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE b.sis_empresa_id = :empresa_id
              AND upper(b.inv_bodega_codigo) = upper(:codigo)
              AND es.sis_estado_codigo <> 'ELIMINADO'
        ";
        $parametros = ['empresa_id' => $empresaId, 'codigo' => $codigo];
        if ($bodegaId !== null) {
            $sql .= ' AND b.inv_bodega_id <> :bodega_id';
            $parametros['bodega_id'] = $bodegaId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql . ' LIMIT 1');
        $sentencia->execute($parametros);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI UNA BODEGA TIENE STOCK O MOVIMIENTOS ASOCIADOS.
     * ***************************************************************************
     */
    public function estaUsada(int $bodegaId): bool
    {
        $consultas = [
            'SELECT 1 FROM inv_stock WHERE inv_bodega_id = :bodega_id LIMIT 1',
            'SELECT 1 FROM inv_kardex WHERE inv_bodega_id = :bodega_id LIMIT 1',
            'SELECT 1 FROM inv_movimientos WHERE inv_bodega_origen_id = :bodega_id OR inv_bodega_destino_id = :bodega_id LIMIT 1',
        ];
        foreach ($consultas as $sql) {
            $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
            $sentencia->execute(['bodega_id' => $bodegaId]);
            if ($sentencia->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    /**
     * ***************************************************************************
     * * LISTA USUARIOS ACTIVOS ASIGNADOS A UNA EMPRESA.
     * ***************************************************************************
     */
    public function listarUsuariosEmpresa(int $empresaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT DISTINCT
                u.sis_usuarios_id,
                u.sis_usuarios_nombre,
                u.sis_usuarios_correo,
                p.sis_perfil_nombre
            FROM sis_usuario_empresa ue
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = ue.sis_usuarios_id
            INNER JOIN sis_perfil p ON p.sis_perfil_id = ue.sis_perfil_id
            INNER JOIN sis_estado eu ON eu.sis_estado_id = u.sis_estado_id
            INNER JOIN sis_estado eue ON eue.sis_estado_id = ue.sis_estado_id
            WHERE ue.sis_empresa_id = :empresa_id
              AND eu.sis_estado_codigo = 'ACTIVO'
              AND eue.sis_estado_codigo = 'ACTIVO'
              AND p.sis_perfil_estado = 1
            ORDER BY u.sis_usuarios_nombre, u.sis_usuarios_correo
        ");
        $sentencia->execute(['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * LISTA USUARIOS PERMITIDOS PARA UNA BODEGA.
     * ***************************************************************************
     */
    public function listarUsuariosBodega(int $empresaId, int $bodegaId): array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                bu.inv_bodega_usuarios_id,
                bu.sis_usuarios_id,
                bu.inv_bodega_id,
                bu.inv_bodega_usuarios_estado,
                bu.inv_bodega_usuarios_predeterminada,
                bu.fecha_crea AS fecha_asignacion,
                u.sis_usuarios_nombre,
                u.sis_usuarios_correo
            FROM inv_bodega_usuarios bu
            INNER JOIN sis_usuarios u ON u.sis_usuarios_id = bu.sis_usuarios_id
            WHERE bu.sis_empresa_id = :empresa_id
              AND bu.inv_bodega_id = :bodega_id
              AND bu.inv_bodega_usuarios_estado <> -1
            ORDER BY bu.inv_bodega_usuarios_estado DESC, bu.inv_bodega_usuarios_predeterminada DESC, u.sis_usuarios_nombre
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'bodega_id' => $bodegaId,
        ]);

        return $sentencia->fetchAll();
    }

    /**
     * ***************************************************************************
     * * GUARDA O REACTIVA PERMISO DE USUARIO PARA UNA BODEGA.
     * ***************************************************************************
     */
    public function guardarUsuarioBodega(int $empresaId, int $bodegaId, int $usuarioAsignadoId, bool $predeterminada, int $usuarioActualId): void
    {
        $pdo = $this->conexionBaseDatos->obtener();
        $pdo->beginTransaction();
        try {
            if (!$this->usuarioPerteneceEmpresa($empresaId, $usuarioAsignadoId)) {
                throw new \InvalidArgumentException('El usuario no pertenece a la empresa activa.');
            }
            if ($predeterminada) {
                $this->limpiarBodegaPredeterminadaUsuario($empresaId, $usuarioAsignadoId, $usuarioActualId);
            }

            $registro = $this->buscarUsuarioBodega($empresaId, $bodegaId, $usuarioAsignadoId);
            if ($registro) {
                $sentencia = $pdo->prepare("
                    UPDATE inv_bodega_usuarios
                    SET inv_bodega_usuarios_estado = 1,
                        inv_bodega_usuarios_predeterminada = :predeterminada,
                        usuario_modifica = :usuario_modifica,
                        fecha_modifica = now()
                    WHERE inv_bodega_usuarios_id = :asignacion_id
                ");
                $sentencia->bindValue(':asignacion_id', (int) $registro['inv_bodega_usuarios_id'], PDO::PARAM_INT);
                $sentencia->bindValue(':predeterminada', $predeterminada, PDO::PARAM_BOOL);
                $sentencia->bindValue(':usuario_modifica', $usuarioActualId, PDO::PARAM_INT);
                $sentencia->execute();
            } else {
                $sentencia = $pdo->prepare("
                    INSERT INTO inv_bodega_usuarios (
                        sis_empresa_id, sis_usuarios_id, inv_bodega_id,
                        inv_bodega_usuarios_estado, inv_bodega_usuarios_predeterminada,
                        usuario_crea
                    )
                    VALUES (
                        :empresa_id, :usuario_id, :bodega_id,
                        1, :predeterminada,
                        :usuario_crea
                    )
                ");
                $sentencia->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
                $sentencia->bindValue(':usuario_id', $usuarioAsignadoId, PDO::PARAM_INT);
                $sentencia->bindValue(':bodega_id', $bodegaId, PDO::PARAM_INT);
                $sentencia->bindValue(':predeterminada', $predeterminada, PDO::PARAM_BOOL);
                $sentencia->bindValue(':usuario_crea', $usuarioActualId, PDO::PARAM_INT);
                $sentencia->execute();
            }
            $pdo->commit();
        } catch (\Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO DE PERMISO USUARIO BODEGA.
     * ***************************************************************************
     */
    public function cambiarEstadoUsuarioBodega(int $empresaId, int $asignacionId, int $estado, int $usuarioActualId): void
    {
        $asignacion = $this->buscarAsignacionUsuarioBodega($empresaId, $asignacionId);
        if (!$asignacion) {
            throw new \InvalidArgumentException('Asignacion de bodega no valida.');
        }

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_bodega_usuarios
            SET inv_bodega_usuarios_estado = :estado,
                inv_bodega_usuarios_predeterminada = CASE WHEN :estado_predeterminado = 1 THEN inv_bodega_usuarios_predeterminada ELSE FALSE END,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_bodega_usuarios_id = :asignacion_id
              AND sis_empresa_id = :empresa_id
        ");
        $sentencia->execute([
            'estado' => $estado,
            'estado_predeterminado' => $estado,
            'usuario_modifica' => $usuarioActualId,
            'asignacion_id' => $asignacionId,
            'empresa_id' => $empresaId,
        ]);
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO DE BODEGA POR CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoId(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'INVENTARIO'
              AND sis_estado_entidad = 'INV_BODEGA'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * LIMPIA BODEGA PRINCIPAL PREVIA DE UNA EMPRESA.
     * ***************************************************************************
     */
    private function limpiarPrincipal(int $empresaId, int $usuarioId, ?int $exceptoBodegaId = null): void
    {
        $sql = "
            UPDATE inv_bodega
            SET inv_bodega_es_principal = FALSE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_empresa_id = :empresa_id
        ";
        $parametros = ['empresa_id' => $empresaId, 'usuario_modifica' => $usuarioId];
        if ($exceptoBodegaId !== null) {
            $sql .= ' AND inv_bodega_id <> :bodega_id';
            $parametros['bodega_id'] = $exceptoBodegaId;
        }
        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($parametros);
    }

    /**
     * ***************************************************************************
     * * VERIFICA SI UN USUARIO TIENE ASIGNACION ACTIVA EN LA EMPRESA.
     * ***************************************************************************
     */
    private function usuarioPerteneceEmpresa(int $empresaId, int $usuarioAsignadoId): bool
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT 1
            FROM sis_usuario_empresa ue
            INNER JOIN sis_estado es ON es.sis_estado_id = ue.sis_estado_id
            WHERE ue.sis_empresa_id = :empresa_id
              AND ue.sis_usuarios_id = :usuario_id
              AND es.sis_estado_codigo = 'ACTIVO'
            LIMIT 1
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioAsignadoId,
        ]);

        return (bool) $sentencia->fetchColumn();
    }

    /**
     * ***************************************************************************
     * * BUSCA PERMISO USUARIO BODEGA EXISTENTE.
     * ***************************************************************************
     */
    private function buscarUsuarioBodega(int $empresaId, int $bodegaId, int $usuarioAsignadoId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM inv_bodega_usuarios
            WHERE sis_empresa_id = :empresa_id
              AND inv_bodega_id = :bodega_id
              AND sis_usuarios_id = :usuario_id
              AND inv_bodega_usuarios_estado <> -1
            ORDER BY inv_bodega_usuarios_estado DESC, inv_bodega_usuarios_id DESC
            LIMIT 1
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'bodega_id' => $bodegaId,
            'usuario_id' => $usuarioAsignadoId,
        ]);
        $registro = $sentencia->fetch();

        return $registro ?: null;
    }

    /**
     * ***************************************************************************
     * * BUSCA ASIGNACION USUARIO BODEGA POR ID.
     * ***************************************************************************
     */
    private function buscarAsignacionUsuarioBodega(int $empresaId, int $asignacionId): ?array
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT *
            FROM inv_bodega_usuarios
            WHERE sis_empresa_id = :empresa_id
              AND inv_bodega_usuarios_id = :asignacion_id
              AND inv_bodega_usuarios_estado <> -1
            LIMIT 1
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'asignacion_id' => $asignacionId,
        ]);
        $asignacion = $sentencia->fetch();

        return $asignacion ?: null;
    }

    /**
     * ***************************************************************************
     * * MARCA ASIGNACION COMO BODEGA PREDETERMINADA DEL USUARIO.
     * ***************************************************************************
     */
    public function setPredeterminadaUsuarioBodega(int $empresaId, int $asignacionId, int $usuarioActualId): void
    {
        $asignacion = $this->buscarAsignacionUsuarioBodega($empresaId, $asignacionId);
        if (!$asignacion) {
            throw new \InvalidArgumentException('Asignacion de bodega no valida.');
        }
        $pdo = $this->conexionBaseDatos->obtener();
        $sentencia = $pdo->prepare("
            UPDATE inv_bodega_usuarios
            SET inv_bodega_usuarios_predeterminada = FALSE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_empresa_id = :empresa_id
              AND sis_usuarios_id = :usuario_id
              AND inv_bodega_usuarios_estado = 1
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'usuario_id' => (int) $asignacion['sis_usuarios_id'],
            'usuario_modifica' => $usuarioActualId,
        ]);
        $sentencia = $pdo->prepare("
            UPDATE inv_bodega_usuarios
            SET inv_bodega_usuarios_predeterminada = TRUE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE inv_bodega_usuarios_id = :asignacion_id
              AND sis_empresa_id = :empresa_id
        ");
        $sentencia->execute([
            'asignacion_id' => $asignacionId,
            'empresa_id' => $empresaId,
            'usuario_modifica' => $usuarioActualId,
        ]);
    }

    /**
     * ***************************************************************************
     * * LIMPIA BODEGA PREDETERMINADA PREVIA DEL USUARIO EN UNA EMPRESA.
     * ***************************************************************************
     */
    private function limpiarBodegaPredeterminadaUsuario(int $empresaId, int $usuarioAsignadoId, int $usuarioActualId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_bodega_usuarios
            SET inv_bodega_usuarios_predeterminada = FALSE,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_empresa_id = :empresa_id
              AND sis_usuarios_id = :usuario_id
              AND inv_bodega_usuarios_estado = 1
        ");
        $sentencia->execute([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioAsignadoId,
            'usuario_modifica' => $usuarioActualId,
        ]);
    }

    /**
     * ***************************************************************************
     * * VINCULA DATOS DE BODEGA CON TIPOS PDO CORRECTOS.
     * ***************************************************************************
     */
    private function vincularDatos(\PDOStatement $sentencia, array $datos): void
    {
        $sentencia->bindValue(':empresa_id', (int) $datos['empresa_id'], PDO::PARAM_INT);
        $sentencia->bindValue(':codigo', $datos['codigo']);
        $sentencia->bindValue(':nombre', $datos['nombre']);
        $sentencia->bindValue(':descripcion', $datos['descripcion']);
        $sentencia->bindValue(':direccion', $datos['direccion']);
        $sentencia->bindValue(':principal', (bool) $datos['principal'], PDO::PARAM_BOOL);
        $sentencia->bindValue(':virtual', (bool) $datos['virtual'], PDO::PARAM_BOOL);
        $sentencia->bindValue(':negativos', (bool) $datos['negativos'], PDO::PARAM_BOOL);
        $sentencia->bindValue(':autoaprobado', (bool) $datos['autoaprobado'], PDO::PARAM_BOOL);
        $est = isset($datos['establecimiento']) && $datos['establecimiento'] !== '' ? $datos['establecimiento'] : null;
        $pto = isset($datos['punto_emision']) && $datos['punto_emision'] !== '' ? $datos['punto_emision'] : null;
        $sentencia->bindValue(':establecimiento', $est);
        $sentencia->bindValue(':punto_emision', $pto);
    }

    public function actualizarEstPto(int $bodegaId, string $est, string $pto): void
    {
        $this->conexionBaseDatos->obtener()->prepare("
            UPDATE inv_bodega
            SET inv_bodega_establecimiento = :est,
                inv_bodega_punto_emision   = :pto,
                fecha_modifica = now()
            WHERE inv_bodega_id = :bodega_id
              AND (inv_bodega_establecimiento IS NULL OR inv_bodega_establecimiento = '')
        ")->execute(['est' => $est, 'pto' => $pto, 'bodega_id' => $bodegaId]);
    }

    /**
     * ***************************************************************************
     * * LISTA BODEGAS ACTIVAS POR EMPRESA PARA EL SELECTOR DE SECUENCIAS.
     * * Retorna array indexado por empresa_id para uso en JS.
     * ***************************************************************************
     */
    public function listarPorEmpresaParaSecuencia(?int $empresaFiltro = null): array
    {
        $filtro = $empresaFiltro !== null ? 'AND b.sis_empresa_id = :empresa_id' : '';
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT
                b.inv_bodega_id,
                b.sis_empresa_id,
                b.inv_bodega_codigo,
                b.inv_bodega_nombre,
                b.inv_bodega_es_principal,
                b.inv_bodega_establecimiento,
                b.inv_bodega_punto_emision
            FROM inv_bodega b
            INNER JOIN sis_estado es ON es.sis_estado_id = b.sis_estado_id
            WHERE es.sis_estado_codigo = 'ACTIVO'
            {$filtro}
            ORDER BY b.sis_empresa_id, b.inv_bodega_es_principal DESC, b.inv_bodega_codigo
        ");
        $sentencia->execute($empresaFiltro !== null ? ['empresa_id' => (int) $empresaFiltro] : []);
        $rows = $sentencia->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($rows as $row) {
            $resultado[(int) $row['sis_empresa_id']][] = $row;
        }

        return $resultado;
    }
}
