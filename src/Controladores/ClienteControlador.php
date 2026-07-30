<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\ClienteModelo;
use Intesis\Modelos\FacturaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\ProformaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class ClienteControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private ClienteModelo $clienteModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores,
        private FacturaModelo $facturaModelo,
        private ProformaModelo $proformaModelo
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD DE CLIENTES.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/ventas/clientes/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $this->vista->renderizar('ventas/clientes', [
            'titulo'          => 'Clientes',
            'usuario'         => $usuario,
            'menus'           => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'clientes'        => $this->clienteModelo->listar((int) $usuario['empresa_id'], $verTodas),
            'empresas'        => $this->clienteModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'esSuperusuario'  => $verTodas,
            'permisos'        => $this->obtenerPermisos($usuario),
            'mensaje'         => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'USUARIO_DATOS_OBLIGATORIOS',
                'CONFIRMAR_INACTIVAR_CLIENTE',
            ]),
        ]);
    }

    /**
     * ***************************************************************************
     * * LISTA FACTURAS, NOTAS DE VENTA Y PROFORMAS DE UN CLIENTE.
     * ***************************************************************************
     */
    public function historialVentas(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/clientes/ver');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];
        $clienteId = (int) ($_GET['cliente_id'] ?? 0);

        if ($clienteId <= 0) {
            $this->responderJson(false, 'ERROR_VALIDACION', 'Cliente no válido.');
        }

        $documentos = $this->facturaModelo->listarPorCliente($clienteId, $empresaId, $verTodas);
        $facturas = array_values(array_filter($documentos, fn (array $d): bool => $d['tipo_codigo'] === 'FACTURA_VENTA'));
        $notasVenta = array_values(array_filter($documentos, fn (array $d): bool => $d['tipo_codigo'] === 'NOTA_VENTA'));

        $this->responderJson(true, 'OK', '', [
            'facturas' => $facturas,
            'notas_venta' => $notasVenta,
            'proformas' => $this->proformaModelo->listarPorCliente($clienteId, $empresaId, $verTodas),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UN CLIENTE.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardar(false);
    }

    /**
     * ***************************************************************************
     * * CREA UN CLIENTE RAPIDO DESDE MODAL (AJAX - JSON).
     * ***************************************************************************
     */
    public function crearRapido(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            echo json_encode(['ok' => false, 'mensaje' => 'Sesion no activa.']);
            return;
        }
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], '/ventas/clientes/crear')) {
            echo json_encode(['ok' => false, 'mensaje' => 'Sin permiso para crear clientes.']);
            return;
        }
        try {
            $datos = $this->normalizarDatos($usuario);
            /* en creacion rapida la empresa siempre es la activa del usuario */
            $datos['empresa_id'] = (int) $usuario['empresa_id'];
            $this->validarDatos($datos);
            $id = $this->clienteModelo->crear($datos, (int) $usuario['id']);
            $cliente = $this->clienteModelo->buscar($id);
            echo json_encode(['ok' => true, 'data' => $cliente]);
        } catch (\Throwable $excepcion) {
            $this->registroErrores->escribirExcepcion('CREAR RAPIDO CLIENTE', $excepcion);
            echo json_encode(['ok' => false, 'mensaje' => $excepcion->getMessage()]);
        }
    }

    /**
     * ***************************************************************************
     * * EDITA UN CLIENTE.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardar(true);
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN CLIENTE.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/ventas/clientes/activar', 'ACTIVO', 'Cliente activado');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN CLIENTE.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/ventas/clientes/inactivar', 'INACTIVO', 'Cliente inactivado');
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE CLIENTE.
     * ***************************************************************************
     */
    private function guardar(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/ventas/clientes/editar' : '/ventas/clientes/crear');
        try {
            $clienteId = (int) ($_POST['cliente_id'] ?? 0);
            $cliente   = $editar ? $this->obtenerClientePermitido($clienteId, $usuario) : null;
            $datos     = $this->normalizarDatos($usuario);
            $this->validarDatos($datos, $editar ? $clienteId : null);
            $editar
                ? $this->clienteModelo->actualizar((int) $cliente['ven_cliente_id'], $datos, (int) $usuario['id'])
                : $this->clienteModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Cliente actualizado' : 'Cliente creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR CLIENTE' : 'CREAR CLIENTE', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/ventas/clientes');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO O INACTIVO DE CLIENTE.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $titulo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $cliente = $this->obtenerClientePermitido((int) ($_POST['cliente_id'] ?? 0), $usuario);
            $this->clienteModelo->cambiarEstado((int) $cliente['ven_cliente_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO CLIENTE', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo aplicar el cambio', $excepcion->getMessage());
        }
        $this->redirigir('/ventas/clientes');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE FORMULARIO DE CLIENTE.
     * ***************************************************************************
     */
    private function normalizarDatos(array $usuario): array
    {
        $tiposValidos = ['R', 'C', 'P', 'O'];
        $tipoRaw = strtoupper(trim((string) ($_POST['tipo_identificacion'] ?? '')));
        $tipo = in_array($tipoRaw, $tiposValidos, true) ? $tipoRaw : '';

        return [
            'empresa_id'          => $this->esSuperusuario($usuario) ? (int) ($_POST['empresa_id'] ?? 0) : (int) $usuario['empresa_id'],
            'tipo_identificacion' => $tipo,
            'identificacion'      => trim((string) ($_POST['identificacion']    ?? '')),
            'razon_social'        => trim((string) ($_POST['razon_social']      ?? '')),
            'nombre_comercial'    => trim((string) ($_POST['nombre_comercial']  ?? '')),
            'telefono'            => trim((string) ($_POST['telefono']           ?? '')),
            'email'               => trim((string) ($_POST['email']              ?? '')),
            'direccion'           => trim((string) ($_POST['direccion']          ?? '')),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA REGLAS DE NEGOCIO DE CLIENTE.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $clienteId = null): void
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
        if ($datos['tipo_identificacion'] === 'R' && strlen($datos['identificacion']) !== 13) {
            throw new \InvalidArgumentException('El RUC debe tener 13 digitos.');
        }
        if ($datos['tipo_identificacion'] === 'C' && strlen($datos['identificacion']) !== 10) {
            throw new \InvalidArgumentException('La cedula debe tener 10 digitos.');
        }
        if ($datos['tipo_identificacion'] === 'O') {
            $datos['identificacion'] = '9999999999999';
        }
        if ($datos['razon_social'] === '') {
            throw new \InvalidArgumentException('La razon social es obligatoria.');
        }
        if ($datos['telefono'] === '') {
            throw new \InvalidArgumentException('El telefono es obligatorio.');
        }
        if ($datos['email'] === '') {
            throw new \InvalidArgumentException('El correo es obligatorio.');
        }
        if ($datos['direccion'] === '') {
            throw new \InvalidArgumentException('La direccion es obligatoria.');
        }
        if ($datos['tipo_identificacion'] !== 'CONSUMIDOR_FINAL'
            && $this->clienteModelo->existeIdentificacion($datos['empresa_id'], $datos['identificacion'], $clienteId)
        ) {
            throw new \InvalidArgumentException('Ya existe un cliente con esa identificacion en la empresa.');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE CLIENTE Y VALIDA ALCANCE DE EMPRESA.
     * ***************************************************************************
     */
    private function obtenerClientePermitido(int $clienteId, array $usuario): array
    {
        $cliente = $clienteId > 0 ? $this->clienteModelo->buscar($clienteId) : null;
        if (!$cliente) {
            throw new \InvalidArgumentException('Cliente no valido.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $cliente['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar clientes de otra empresa.');
        }

        return $cliente;
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
            'crear'     => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/ventas/clientes/crear'),
            'editar'    => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/ventas/clientes/editar'),
            'activar'   => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/ventas/clientes/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/ventas/clientes/inactivar'),
        ];
    }
}
