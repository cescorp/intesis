<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use PDO;

final class CodigoProveedorModelo
{
    public function __construct(private PDO $pdo) {}

    public function listar(int $empresaId, bool $verTodas, int $proveedorId = 0, string $estado = ''): array
    {
        $conds  = [];
        $params = [];

        if (!$verTodas) {
            $conds[]              = 'cp.sis_empresa_id = :empresa_id';
            $params['empresa_id'] = $empresaId;
        } elseif ($empresaId > 0) {
            $conds[]              = 'cp.sis_empresa_id = :empresa_id';
            $params['empresa_id'] = $empresaId;
        }

        if ($proveedorId > 0) {
            $conds[]                = 'icp.com_proveedor_id = :proveedor_id';
            $params['proveedor_id'] = $proveedorId;
        }

        if ($estado === '1' || $estado === '0') {
            $conds[]          = 'icp.inv_codigo_proveedor_estado = :estado';
            $params['estado'] = (int) $estado;
        }

        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

        $sql = "
            SELECT  icp.inv_codigo_proveedor_id,
                    icp.inv_codigo_proveedor_codigo,
                    icp.inv_codigo_proveedor_estado,
                    icp.com_proveedor_id,
                    cp.com_proveedor_razon_social,
                    cp.sis_empresa_id,
                    icp.inv_producto_id,
                    ip.inv_producto_nombre,
                    ip.inv_producto_codigo_principal AS codigo_interno,
                    COALESCE(um.sis_usuarios_nombre, uc.sis_usuarios_nombre) AS usuario_asigna,
                    to_char(COALESCE(icp.fecha_modifica, icp.fecha_crea), 'DD/MM/YYYY') AS fecha_asigna
            FROM    inv_codigo_proveedor icp
            JOIN    com_proveedor cp ON cp.com_proveedor_id = icp.com_proveedor_id
            LEFT JOIN inv_producto ip ON ip.inv_producto_id = icp.inv_producto_id
            LEFT JOIN sis_usuarios uc ON uc.sis_usuarios_id = icp.usuario_crea
            LEFT JOIN sis_usuarios um ON um.sis_usuarios_id = icp.usuario_modifica
            $where
            ORDER BY cp.com_proveedor_razon_social, icp.inv_codigo_proveedor_codigo
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT  icp.*,
                     cp.com_proveedor_razon_social,
                     cp.sis_empresa_id,
                     ip.inv_producto_nombre,
                     ip.inv_producto_codigo_principal AS codigo_interno
             FROM    inv_codigo_proveedor icp
             JOIN    com_proveedor cp ON cp.com_proveedor_id = icp.com_proveedor_id
             LEFT JOIN inv_producto ip ON ip.inv_producto_id = icp.inv_producto_id
             WHERE   icp.inv_codigo_proveedor_id = :id
             LIMIT   1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeCodigo(int $proveedorId, string $codigo, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT 1 FROM inv_codigo_proveedor
                   WHERE com_proveedor_id = :proveedor_id
                     AND inv_codigo_proveedor_codigo = :codigo';
        $params = ['proveedor_id' => $proveedorId, 'codigo' => $codigo];
        if ($excluirId !== null) {
            $sql           .= ' AND inv_codigo_proveedor_id != :excluir';
            $params['excluir'] = $excluirId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function crear(int $proveedorId, string $codigo, int $productoId, int $usuarioId): void
    {
        $this->pdo->prepare(
            'INSERT INTO inv_codigo_proveedor
                 (com_proveedor_id, inv_codigo_proveedor_codigo, inv_producto_id,
                  inv_codigo_proveedor_estado, usuario_crea, fecha_crea)
             VALUES (:proveedor_id, :codigo, :producto_id, 1, :usuario, now())'
        )->execute([
            'proveedor_id' => $proveedorId,
            'codigo'       => $codigo,
            'producto_id'  => $productoId,
            'usuario'      => $usuarioId,
        ]);
    }

    public function actualizar(int $id, string $codigo, int $productoId, int $usuarioId): void
    {
        $this->pdo->prepare(
            'UPDATE inv_codigo_proveedor
             SET    inv_codigo_proveedor_codigo = :codigo,
                    inv_producto_id             = :producto_id,
                    usuario_modifica            = :usuario,
                    fecha_modifica              = now()
             WHERE  inv_codigo_proveedor_id = :id'
        )->execute([
            'codigo'      => $codigo,
            'producto_id' => $productoId,
            'usuario'     => $usuarioId,
            'id'          => $id,
        ]);
    }

    public function cambiarEstado(int $id, int $estado, int $usuarioId): void
    {
        $this->pdo->prepare(
            'UPDATE inv_codigo_proveedor
             SET    inv_codigo_proveedor_estado = :estado,
                    usuario_modifica            = :usuario,
                    fecha_modifica              = now()
             WHERE  inv_codigo_proveedor_id = :id'
        )->execute(['estado' => $estado, 'usuario' => $usuarioId, 'id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->pdo->prepare(
            'DELETE FROM inv_codigo_proveedor WHERE inv_codigo_proveedor_id = :id'
        )->execute(['id' => $id]);
    }

    public function buscarProductos(int $empresaId, string $termino): array
    {
        $like = '%' . $termino . '%';
        $stmt = $this->pdo->prepare("
            SELECT  p.inv_producto_id,
                    p.inv_producto_codigo_principal AS codigo_interno,
                    p.inv_producto_nombre,
                    COALESCE(ROUND(SUM(s.inv_stock_cantidad_disponible)::numeric, 0), 0) AS stock_total,
                    (SELECT icp2.inv_codigo_proveedor_codigo
                     FROM   inv_codigo_proveedor icp2
                     WHERE  icp2.inv_producto_id = p.inv_producto_id
                       AND  icp2.inv_codigo_proveedor_estado = 1
                     LIMIT  1) AS cod_proveedor_existente,
                    (SELECT cp2.com_proveedor_razon_social
                     FROM   inv_codigo_proveedor icp2
                     JOIN   com_proveedor cp2 ON cp2.com_proveedor_id = icp2.com_proveedor_id
                     WHERE  icp2.inv_producto_id = p.inv_producto_id
                       AND  icp2.inv_codigo_proveedor_estado = 1
                     LIMIT  1) AS razon_social_existente
            FROM    inv_producto p
            LEFT JOIN inv_stock s  ON s.inv_producto_id = p.inv_producto_id
                                   AND s.sis_empresa_id  = :empresa_id
            INNER JOIN sis_estado es ON es.sis_estado_id = p.sis_estado_id
            WHERE   p.sis_empresa_id       = :empresa_id2
              AND   es.sis_estado_codigo   = 'ACTIVO'
              AND   (
                  upper(p.inv_producto_codigo_principal) LIKE upper(:like)
                  OR upper(p.inv_producto_nombre)        LIKE upper(:like2)
              )
            GROUP BY p.inv_producto_id, p.inv_producto_codigo_principal, p.inv_producto_nombre
            ORDER BY p.inv_producto_nombre
            LIMIT   50
        ");
        $stmt->execute([
            'empresa_id'  => $empresaId,
            'empresa_id2' => $empresaId,
            'like'        => $like,
            'like2'       => $like,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listarProveedores(int $empresaId, bool $verTodas): array
    {
        $params = [];
        $where  = "WHERE es.sis_estado_codigo = 'ACTIVO'";
        if (!$verTodas) {
            $where           .= ' AND cp.sis_empresa_id = :empresa_id';
            $params['empresa_id'] = $empresaId;
        }
        $sql = "
            SELECT cp.com_proveedor_id,
                   cp.com_proveedor_razon_social,
                   cp.com_proveedor_identificacion
            FROM   com_proveedor cp
            JOIN   sis_estado es ON es.sis_estado_id = cp.sis_estado_id
            $where
            ORDER BY cp.com_proveedor_razon_social
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
