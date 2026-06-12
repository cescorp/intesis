<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\ProveedorModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class ProveedorControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private ProveedorModelo $proveedorModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD DE PROVEEDORES.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/proveedores/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $this->vista->renderizar('compras/proveedores', [
            'titulo'         => 'Proveedores',
            'usuario'        => $usuario,
            'menus'          => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'proveedores'    => $this->proveedorModelo->listar((int) $usuario['empresa_id'], $verTodas),
            'empresas'       => $this->proveedorModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'esSuperusuario' => $verTodas,
            'permisos'       => $this->obtenerPermisos($usuario),
            'mensaje'        => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'USUARIO_DATOS_OBLIGATORIOS',
                'CONFIRMAR_INACTIVAR_PROVEEDOR',
            ]),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UN PROVEEDOR.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardar(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UN PROVEEDOR.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardar(true);
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN PROVEEDOR.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/compras/proveedores/activar', 'ACTIVO', 'Proveedor activado');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN PROVEEDOR.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/compras/proveedores/inactivar', 'INACTIVO', 'Proveedor inactivado');
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE PROVEEDOR.
     * ***************************************************************************
     */
    private function guardar(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/compras/proveedores/editar' : '/compras/proveedores/crear');
        try {
            $proveedorId = (int) ($_POST['proveedor_id'] ?? 0);
            $proveedor   = $editar ? $this->obtenerProveedorPermitido($proveedorId, $usuario) : null;
            $datos       = $this->normalizarDatos($usuario);
            $this->validarDatos($datos, $editar ? $proveedorId : null);
            $editar
                ? $this->proveedorModelo->actualizar((int) $proveedor['com_proveedor_id'], $datos, (int) $usuario['id'])
                : $this->proveedorModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Proveedor actualizado' : 'Proveedor creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR PROVEEDOR' : 'CREAR PROVEEDOR', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/compras/proveedores');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO O INACTIVO DE PROVEEDOR.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $titulo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $proveedor = $this->obtenerProveedorPermitido((int) ($_POST['proveedor_id'] ?? 0), $usuario);
            $this->proveedorModelo->cambiarEstado((int) $proveedor['com_proveedor_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO PROVEEDOR', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo aplicar el cambio', $excepcion->getMessage());
        }
        $this->redirigir('/compras/proveedores');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE FORMULARIO DE PROVEEDOR.
     * ***************************************************************************
     */
    private function normalizarDatos(array $usuario): array
    {
        $tiposValidos = ['RUC', 'CEDULA', 'PASAPORTE'];
        $tipoRaw = strtoupper(trim((string) ($_POST['tipo_identificacion'] ?? '')));
        $tipo = in_array($tipoRaw, $tiposValidos, true) ? $tipoRaw : '';

        return [
            'empresa_id'          => $this->esSuperusuario($usuario) ? (int) ($_POST['empresa_id'] ?? 0) : (int) $usuario['empresa_id'],
            'tipo_identificacion'  => $tipo,
            'identificacion'      => trim((string) ($_POST['identificacion'] ?? '')),
            'razon_social'        => trim((string) ($_POST['razon_social'] ?? '')),
            'nombre_comercial'    => trim((string) ($_POST['nombre_comercial'] ?? '')),
            'telefono'            => trim((string) ($_POST['telefono']   ?? '')),
            'email'               => trim((string) ($_POST['email']      ?? '')),
            'direccion'           => trim((string) ($_POST['direccion']  ?? '')),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA REGLAS DE NEGOCIO DE PROVEEDOR.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $proveedorId = null): void
    {
        if ($datos['empresa_id'] <= 0) {
            throw new \InvalidArgumentException('Empresa no valida.');
        }
        if ($datos['tipo_identificacion'] === '') {
            throw new \InvalidArgumentException('Tipo de identificacion no valido.');
        }
        if ($datos['identificacion'] === '') {
            throw new \InvalidArgumentException('El numero de identificacion es obligatorio.');
        }
        if ($datos['razon_social'] === '') {
            throw new \InvalidArgumentException('La razon social es obligatoria.');
        }
        if ($this->proveedorModelo->existeIdentificacion($datos['empresa_id'], $datos['identificacion'], $proveedorId)) {
            throw new \InvalidArgumentException('Ya existe un proveedor con esa identificacion en la empresa.');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE PROVEEDOR Y VALIDA ALCANCE DE EMPRESA.
     * ***************************************************************************
     */
    private function obtenerProveedorPermitido(int $proveedorId, array $usuario): array
    {
        $proveedor = $proveedorId > 0 ? $this->proveedorModelo->buscar($proveedorId) : null;
        if (!$proveedor) {
            throw new \InvalidArgumentException('Proveedor no valido.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $proveedor['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar proveedores de otra empresa.');
        }

        return $proveedor;
    }

    /**
     * ***************************************************************************
     * * OBTIENE PERMISOS DE BOTONES DEL CRUD.
     * ***************************************************************************
     */
    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId  = (int) $usuario['perfil_id'];

        return [
            'crear'        => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/compras/proveedores/crear'),
            'editar'       => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/compras/proveedores/editar'),
            'activar'      => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/compras/proveedores/activar'),
            'inactivar'    => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/compras/proveedores/inactivar'),
            'ver_codigos'  => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/compras/codigos-proveedor/ver'),
        ];
    }
}
