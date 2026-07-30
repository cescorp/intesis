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
     * * LISTA EMPRESAS NO ELIMINADAS SEGUN ALCANCE DEL USUARIO.
     * ***************************************************************************
     */
    public function listar(?int $empresaId = null): array
    {
        $filtroEmpresa = $empresaId === null ? '' : 'AND e.sis_empresa_id = :empresa_id';
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
                e.sis_empresa_ambiente_sri,
                e.sis_empresa_certificado_ruta,
                e.sis_empresa_certificado_clave,
                e.sis_empresa_num_contribuyente_especial,
                e.sis_empresa_descuento_maximo_facturas,
                e.sis_empresa_descuento_maximo_notas_venta,
                e.sis_empresa_formula_descuento,
                es.sis_estado_codigo,
                es.sis_estado_nombre
            FROM sis_empresa e
            INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
            WHERE es.sis_estado_codigo <> 'ELIMINADO'
            {$filtroEmpresa}
            ORDER BY e.sis_empresa_id
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $sentencia->execute($empresaId === null ? [] : ['empresa_id' => $empresaId]);

        return $sentencia->fetchAll();
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
    public function crear(array $datos, int $usuarioId): int
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
                sis_empresa_ambiente_sri,
                sis_empresa_num_contribuyente_especial,
                sis_empresa_certificado_clave,
                sis_empresa_descuento_maximo_facturas,
                sis_empresa_descuento_maximo_notas_venta,
                sis_empresa_formula_descuento,
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
                :ambiente_sri,
                :num_contribuyente_especial,
                :certificado_clave,
                :descuento_maximo_facturas,
                :descuento_maximo_notas_venta,
                :formula_descuento,
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
            $this->crearSecuenciasBase($empresaId, $usuarioId);
            $this->crearFormasPagoBase($empresaId, $usuarioId);
            $pdo->commit();

            return $empresaId;
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
            SET sis_empresa_ruc                        = :ruc,
                sis_empresa_razon_social               = :razon_social,
                sis_empresa_nombre_comercial           = :nombre_comercial,
                sis_empresa_direccion                  = :direccion,
                sis_empresa_telefono                   = :telefono,
                sis_empresa_email                      = :email,
                sis_empresa_obligado_contabilidad      = :obligado_contabilidad,
                sis_empresa_contribuyente_especial     = :contribuyente_especial,
                sis_empresa_ambiente_sri               = :ambiente_sri,
                sis_empresa_num_contribuyente_especial = :num_contribuyente_especial,
                sis_empresa_descuento_maximo_facturas    = :descuento_maximo_facturas,
                sis_empresa_descuento_maximo_notas_venta = :descuento_maximo_notas_venta,
                sis_empresa_formula_descuento           = :formula_descuento,
                sis_empresa_certificado_ruta           = COALESCE(:certificado_ruta, sis_empresa_certificado_ruta),
                sis_empresa_certificado_clave          = CASE
                                                            WHEN :certificado_clave_check = '' THEN sis_empresa_certificado_clave
                                                            ELSE :certificado_clave
                                                         END,
                usuario_modifica                       = :usuario_modifica,
                fecha_modifica                         = now()
            WHERE sis_empresa_id = :empresa_id
        ";

        $sentencia = $this->conexionBaseDatos->obtener()->prepare($sql);
        $this->vincularDatosEmpresa($sentencia, $datos);
        $sentencia->bindValue(':certificado_ruta', $datos['certificado_ruta'] ?: null);
        $sentencia->bindValue(':certificado_clave_check', $datos['certificado_clave'] ?? '');
        $sentencia->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $sentencia->bindValue(':usuario_modifica', $usuarioId, PDO::PARAM_INT);
        $sentencia->execute();
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA SOLO LA RUTA DEL CERTIFICADO DE UNA EMPRESA.
     * ***************************************************************************
     */
    public function actualizarCertificadoRuta(int $empresaId, string $ruta, int $usuarioId): void
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            UPDATE sis_empresa
            SET sis_empresa_certificado_ruta = :ruta,
                usuario_modifica = :usuario_modifica,
                fecha_modifica = now()
            WHERE sis_empresa_id = :empresa_id
        ");
        $sentencia->execute([
            'ruta' => $ruta,
            'usuario_modifica' => $usuarioId,
            'empresa_id' => $empresaId,
        ]);
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
        $sentencia->bindValue(':ambiente_sri', $datos['ambiente_sri'] ?: '1');
        $sentencia->bindValue(':num_contribuyente_especial', $datos['num_contribuyente_especial'] ?: null);
        $sentencia->bindValue(':certificado_clave', $datos['certificado_clave'] ?: null);
        $sentencia->bindValue(':descuento_maximo_facturas', (float) $datos['descuento_maximo_facturas']);
        $sentencia->bindValue(':descuento_maximo_notas_venta', (float) $datos['descuento_maximo_notas_venta']);
        $sentencia->bindValue(':formula_descuento', in_array($datos['formula_descuento'] ?? 'C', ['A', 'B', 'C'], true) ? $datos['formula_descuento'] : 'C');
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
     * * CREA LA SECUENCIA 001-001 DE CADA TIPO DE DOCUMENTO OPERATIVO PARA
     * * UNA EMPRESA NUEVA, PARA QUE PUEDA FACTURAR SIN CONFIGURACION MANUAL.
     * ***************************************************************************
     */
    private function crearSecuenciasBase(int $empresaId, int $usuarioId): void
    {
        $tipos = [
            ['codigo' => 'FACTURA_VENTA', 'modulo' => 'VENTAS'],
            ['codigo' => 'NOTA_VENTA', 'modulo' => 'VENTAS'],
            ['codigo' => 'PROFORMA', 'modulo' => 'VENTAS'],
            ['codigo' => 'AJUSTE', 'modulo' => 'INVENTARIO'],
            ['codigo' => 'TRANSFERENCIA', 'modulo' => 'INVENTARIO'],
        ];

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO sis_secuencias (
                sis_empresa_id, sis_tipo_documento_id,
                sis_secuencias_establecimiento, sis_secuencias_punto_emision,
                sis_secuencias_desde, sis_secuencias_actual, sis_secuencias_hasta,
                sis_secuencias_observacion, sis_estado_id, usuario_crea
            )
            SELECT :empresa_id, td.sis_tipo_documento_id,
                   '001', '001',
                   1, 1, 999999999,
                   'Secuencia base creada automaticamente', :estado_id, :usuario_crea
            FROM sis_tipo_documento td
            WHERE td.sis_tipo_documento_codigo = :codigo
              AND td.sis_tipo_documento_modulo = :modulo
              AND NOT EXISTS (
                  SELECT 1 FROM sis_secuencias s
                  WHERE s.sis_empresa_id = :empresa_id
                    AND s.sis_tipo_documento_id = td.sis_tipo_documento_id
              )
        ");
        $estadoId = $this->obtenerEstadoIdSecuencia('ACTIVO');

        foreach ($tipos as $tipo) {
            $sentencia->execute([
                'empresa_id' => $empresaId,
                'estado_id' => $estadoId,
                'usuario_crea' => $usuarioId,
                'codigo' => $tipo['codigo'],
                'modulo' => $tipo['modulo'],
            ]);
        }
    }

    /**
     * ***************************************************************************
     * * CREA LAS FORMAS DE PAGO BASE (EFECTIVO, TRANSFERENCIA, TARJETA CREDITO)
     * * PARA UNA EMPRESA NUEVA, CON CODIGO SRI YA ASIGNADO.
     * ***************************************************************************
     */
    private function crearFormasPagoBase(int $empresaId, int $usuarioId): void
    {
        $formas = [
            ['nombre' => 'EFECTIVO', 'codigo_sri' => '01', 'calculadora' => 'S'],
            ['nombre' => 'TRANSFERENCIA', 'codigo_sri' => '16', 'calculadora' => 'N'],
            ['nombre' => 'TARJETA CRÉDITO', 'codigo_sri' => '19', 'calculadora' => 'N'],
        ];

        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            INSERT INTO ven_forma_pago (
                sis_empresa_id, ven_forma_pago_nombre, ven_forma_pago_codigo_sri,
                ven_forma_pago_calculadora, ven_forma_pago_estado, usuario_crea
            )
            SELECT CAST(:empresa_id_insert AS INTEGER), CAST(:nombre_insert AS VARCHAR(100)),
                   CAST(:codigo_sri AS VARCHAR(2)), CAST(:calculadora AS CHAR(1)),
                   'A', CAST(:usuario_crea AS INTEGER)
            WHERE NOT EXISTS (
                SELECT 1 FROM ven_forma_pago
                WHERE sis_empresa_id = CAST(:empresa_id_buscar AS INTEGER)
                  AND upper(ven_forma_pago_nombre) = upper(CAST(:nombre_buscar AS VARCHAR(100)))
            )
        ");

        foreach ($formas as $forma) {
            $sentencia->execute([
                'empresa_id_insert' => $empresaId,
                'empresa_id_buscar' => $empresaId,
                'nombre_insert' => $forma['nombre'],
                'nombre_buscar' => $forma['nombre'],
                'codigo_sri' => $forma['codigo_sri'],
                'calculadora' => $forma['calculadora'],
                'usuario_crea' => $usuarioId,
            ]);
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE EL ID DE ESTADO DE SECUENCIAS SEGUN SU CODIGO.
     * ***************************************************************************
     */
    private function obtenerEstadoIdSecuencia(string $codigo): int
    {
        $sentencia = $this->conexionBaseDatos->obtener()->prepare("
            SELECT sis_estado_id
            FROM sis_estado
            WHERE sis_estado_modulo = 'SISTEMA'
              AND sis_estado_entidad = 'SIS_SECUENCIAS'
              AND sis_estado_codigo = :codigo
            LIMIT 1
        ");
        $sentencia->execute(['codigo' => $codigo]);

        return (int) $sentencia->fetchColumn();
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
