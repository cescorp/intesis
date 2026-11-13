<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use PDO;

final class FormaPagoModelo
{
    public function __construct(private PDO $pdo) {}

    public function listar(int $empresaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ven_forma_pago_id, ven_forma_pago_nombre, ven_forma_pago_codigo_sri,
                   ven_forma_pago_calculadora, ven_forma_pago_tipo, ven_forma_pago_estado,
                   ven_forma_pago_usa_entidad_bancaria
            FROM ven_forma_pago
            WHERE sis_empresa_id = :empresa_id
            ORDER BY ven_forma_pago_nombre
        ");
        $stmt->execute(['empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ven_forma_pago_id, sis_empresa_id, ven_forma_pago_nombre,
                   ven_forma_pago_codigo_sri, ven_forma_pago_calculadora, ven_forma_pago_tipo, ven_forma_pago_estado,
                   ven_forma_pago_usa_entidad_bancaria
            FROM ven_forma_pago
            WHERE ven_forma_pago_id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeNombre(int $empresaId, string $nombre, ?int $excluirId = null): bool
    {
        $sql = 'SELECT 1 FROM ven_forma_pago
                WHERE sis_empresa_id = :empresa_id AND upper(ven_forma_pago_nombre) = upper(:nombre)';
        $params = ['empresa_id' => $empresaId, 'nombre' => $nombre];
        if ($excluirId !== null) {
            $sql .= ' AND ven_forma_pago_id != :excluir';
            $params['excluir'] = $excluirId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function crear(int $empresaId, string $nombre, string $codigoSri, string $calculadora, string $tipo, string $usaEntidad, int $usuarioId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ven_forma_pago
                (sis_empresa_id, ven_forma_pago_nombre, ven_forma_pago_codigo_sri,
                 ven_forma_pago_calculadora, ven_forma_pago_tipo, ven_forma_pago_usa_entidad_bancaria,
                 ven_forma_pago_estado, usuario_crea, fecha_crea)
            VALUES (:empresa_id, :nombre, :codigo_sri, :calculadora, :tipo, :usa_entidad, 'A', :usuario, now())
            RETURNING ven_forma_pago_id
        ");
        $stmt->execute([
            'empresa_id'  => $empresaId,
            'nombre'      => $nombre,
            'codigo_sri'  => $codigoSri,
            'calculadora' => $calculadora,
            'tipo'        => $tipo,
            'usa_entidad' => $usaEntidad,
            'usuario'     => $usuarioId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function actualizar(int $id, string $nombre, string $codigoSri, string $calculadora, string $tipo, string $usaEntidad, int $usuarioId): void
    {
        $this->pdo->prepare("
            UPDATE ven_forma_pago
            SET ven_forma_pago_nombre      = :nombre,
                ven_forma_pago_codigo_sri  = :codigo_sri,
                ven_forma_pago_calculadora = :calculadora,
                ven_forma_pago_tipo        = :tipo,
                ven_forma_pago_usa_entidad_bancaria = :usa_entidad,
                usuario_modifica           = :usuario,
                fecha_modifica             = now()
            WHERE ven_forma_pago_id = :id
        ")->execute([
            'nombre'      => $nombre,
            'codigo_sri'  => $codigoSri,
            'calculadora' => $calculadora,
            'tipo'        => $tipo,
            'usa_entidad' => $usaEntidad,
            'usuario'     => $usuarioId,
            'id'          => $id,
        ]);
    }

    public function cambiarEstado(int $id, string $estado, int $usuarioId): void
    {
        $this->pdo->prepare("
            UPDATE ven_forma_pago
            SET ven_forma_pago_estado = :estado,
                usuario_modifica      = :usuario,
                fecha_modifica        = now()
            WHERE ven_forma_pago_id = :id
        ")->execute(['estado' => $estado, 'usuario' => $usuarioId, 'id' => $id]);
    }

    public function estaEnUso(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ven_documento WHERE ven_forma_pago_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function eliminar(int $id): void
    {
        $this->pdo->prepare('DELETE FROM ven_forma_pago WHERE ven_forma_pago_id = :id')->execute(['id' => $id]);
    }

    // =========================================================================
    // ENTIDADES BANCARIAS (catalogo por empresa, usado en formas de pago)
    // =========================================================================

    public function listarEntidades(int $empresaId, bool $soloActivas = false): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ven_entidad_bancaria_id, ven_entidad_bancaria_nombre, ven_entidad_bancaria_estado
            FROM ven_entidad_bancaria
            WHERE sis_empresa_id = :empresa_id" . ($soloActivas ? " AND ven_entidad_bancaria_estado = 'A'" : '') . "
            ORDER BY ven_entidad_bancaria_nombre
        ");
        $stmt->execute(['empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarEntidad(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ven_entidad_bancaria WHERE ven_entidad_bancaria_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeNombreEntidad(int $empresaId, string $nombre, ?int $excluirId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ven_entidad_bancaria
            WHERE sis_empresa_id = :empresa_id AND upper(ven_entidad_bancaria_nombre) = upper(:nombre)
              AND ven_entidad_bancaria_id <> :excluir LIMIT 1');
        $stmt->execute(['empresa_id' => $empresaId, 'nombre' => $nombre, 'excluir' => $excluirId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }

    public function crearEntidad(int $empresaId, string $nombre, int $usuarioId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ven_entidad_bancaria (sis_empresa_id, ven_entidad_bancaria_nombre, usuario_crea)
            VALUES (:empresa_id, :nombre, :usuario)
            RETURNING ven_entidad_bancaria_id
        ");
        $stmt->execute(['empresa_id' => $empresaId, 'nombre' => $nombre, 'usuario' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    public function actualizarEntidad(int $id, string $nombre, int $usuarioId): void
    {
        $this->pdo->prepare("
            UPDATE ven_entidad_bancaria
            SET ven_entidad_bancaria_nombre = :nombre, usuario_modifica = :usuario, fecha_modifica = now()
            WHERE ven_entidad_bancaria_id = :id
        ")->execute(['nombre' => $nombre, 'usuario' => $usuarioId, 'id' => $id]);
    }

    public function cambiarEstadoEntidad(int $id, string $estado, int $usuarioId): void
    {
        $this->pdo->prepare("
            UPDATE ven_entidad_bancaria
            SET ven_entidad_bancaria_estado = :estado, usuario_modifica = :usuario, fecha_modifica = now()
            WHERE ven_entidad_bancaria_id = :id
        ")->execute(['estado' => $estado, 'usuario' => $usuarioId, 'id' => $id]);
    }

    public function entidadEnUso(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ven_documento_forma_pago WHERE ven_entidad_bancaria_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function eliminarEntidad(int $id): void
    {
        $this->pdo->prepare('DELETE FROM ven_entidad_bancaria WHERE ven_entidad_bancaria_id = :id')->execute(['id' => $id]);
    }
}
