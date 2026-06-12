<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\CodigoProveedorModelo;
use Intesis\Modelos\DocumentoCompraModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class CodigoProveedorControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private CodigoProveedorModelo $codigoProveedorModelo,
        private DocumentoCompraModelo $documentoCompraModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {}

    // =========================================================================
    // VISTA PRINCIPAL
    // =========================================================================

    public function listar(): void
    {
        $usuario     = $this->exigirSesion();
        $this->exigirPermiso('/compras/codigos-proveedor/ver');
        $verTodas    = $this->esSuperusuario($usuario);
        $empresaId   = (int) $usuario['empresa_id'];
        $proveedorId = (int) ($_GET['proveedor_id'] ?? 0);
        $estado      = (string) ($_GET['estado'] ?? '');

        $this->vista->renderizar('compras/codigos_proveedor', [
            'titulo'            => 'Códigos de Proveedor',
            'usuario'           => $usuario,
            'menus'             => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'codigos'           => $this->codigoProveedorModelo->listar($empresaId, $verTodas, $proveedorId, $estado),
            'proveedores'       => $this->codigoProveedorModelo->listarProveedores($empresaId, $verTodas),
            'esSuperusuario'    => $verTodas,
            'filtroProveedorId' => $proveedorId,
            'filtroEstado'      => $estado,
            'permisos'          => $this->obtenerPermisos($usuario),
            'mensaje'           => $this->sesion->consumirMensaje(),
        ]);
    }

    // =========================================================================
    // CREAR
    // =========================================================================

    public function crear(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/compras/codigos-proveedor/crear');
        try {
            [$proveedorId, $codigo, $productoId] = $this->normalizarDatos();
            $this->validar($proveedorId, $codigo, $productoId, null);
            $this->codigoProveedorModelo->crear($proveedorId, $codigo, $productoId, (int) $usuario['id']);
            $this->jsonOk(['mensaje' => 'Código creado correctamente.']);
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('CREAR CODIGO PROVEEDOR', $e);
            $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // EDITAR
    // =========================================================================

    public function editar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/compras/codigos-proveedor/editar');
        try {
            $id  = (int) ($_POST['codigo_id'] ?? 0);
            $reg = $this->obtenerRegistroPermitido($id, $usuario);
            [, $codigo, $productoId] = $this->normalizarDatos();
            $this->validar((int) $reg['com_proveedor_id'], $codigo, $productoId, $id);
            $this->codigoProveedorModelo->actualizar($id, $codigo, $productoId, (int) $usuario['id']);
            $this->jsonOk(['mensaje' => 'Código actualizado correctamente.']);
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('EDITAR CODIGO PROVEEDOR', $e);
            $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // ACTIVAR / INACTIVAR
    // =========================================================================

    public function activar(): void
    {
        $this->cambiarEstado('/compras/codigos-proveedor/inactivar', 1, 'Código activado.');
    }

    public function inactivar(): void
    {
        $this->cambiarEstado('/compras/codigos-proveedor/inactivar', 0, 'Código inactivado.');
    }

    // =========================================================================
    // ELIMINAR PERMANENTE
    // =========================================================================

    public function eliminar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/compras/codigos-proveedor/eliminar');
        try {
            $id = (int) ($_POST['codigo_id'] ?? 0);
            $this->obtenerRegistroPermitido($id, $usuario);
            $this->codigoProveedorModelo->eliminar($id);
            $this->jsonOk(['mensaje' => 'Código eliminado permanentemente.']);
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('ELIMINAR CODIGO PROVEEDOR', $e);
            $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // BÚSQUEDA DE PRODUCTOS (reutiliza DocumentoCompraModelo)
    // =========================================================================

    public function buscarProductos(): void
    {
        $usuario   = $this->exigirSesionJson();
        $empresaId = (int) $usuario['empresa_id'];
        $q         = trim((string) ($_GET['q'] ?? ''));
        try {
            $lista = $this->codigoProveedorModelo->buscarProductos($empresaId, $q);
            $this->jsonOk(['productos' => $lista]);
        } catch (Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private function cambiarEstado(string $permiso, int $estado, string $mensaje): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson($permiso);
        try {
            $id = (int) ($_POST['codigo_id'] ?? 0);
            $this->obtenerRegistroPermitido($id, $usuario);
            $this->codigoProveedorModelo->cambiarEstado($id, $estado, (int) $usuario['id']);
            $this->jsonOk(['mensaje' => $mensaje]);
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('CAMBIAR ESTADO CODIGO PROVEEDOR', $e);
            $this->jsonError($e->getMessage());
        }
    }

    private function normalizarDatos(): array
    {
        return [
            (int) ($_POST['proveedor_id'] ?? 0),
            strtoupper(trim((string) ($_POST['codigo'] ?? ''))),
            (int) ($_POST['producto_id'] ?? 0),
        ];
    }

    private function validar(int $proveedorId, string $codigo, int $productoId, ?int $excluirId): void
    {
        if ($proveedorId <= 0) {
            throw new \InvalidArgumentException('Seleccione un proveedor.');
        }
        if ($codigo === '') {
            throw new \InvalidArgumentException('El código no puede estar vacío.');
        }
        if ($productoId <= 0) {
            throw new \InvalidArgumentException('Seleccione un producto.');
        }
        if ($this->codigoProveedorModelo->existeCodigo($proveedorId, $codigo, $excluirId)) {
            throw new \InvalidArgumentException("El código \"$codigo\" ya existe para este proveedor.");
        }
    }

    private function obtenerRegistroPermitido(int $id, array $usuario): array
    {
        $reg = $id > 0 ? $this->codigoProveedorModelo->buscar($id) : null;
        if (!$reg) {
            throw new \InvalidArgumentException('Registro no válido.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $reg['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('Sin acceso a este registro.');
        }
        return $reg;
    }

    private function obtenerPermisos(array $usuario): array
    {
        $eId = (int) $usuario['empresa_id'];
        $pId = (int) $usuario['perfil_id'];
        return [
            'crear'     => $this->menuModelo->tienePermiso($eId, $pId, '/compras/codigos-proveedor/crear'),
            'editar'    => $this->menuModelo->tienePermiso($eId, $pId, '/compras/codigos-proveedor/editar'),
            'inactivar' => $this->menuModelo->tienePermiso($eId, $pId, '/compras/codigos-proveedor/inactivar'),
            'eliminar'  => $this->menuModelo->tienePermiso($eId, $pId, '/compras/codigos-proveedor/eliminar'),
        ];
    }

    private function jsonOk(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonError(string $mensaje): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
