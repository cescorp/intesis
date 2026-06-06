<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\EmpresaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Intesis\Servicios\ValidadorIdentificacion;
use Throwable;

final class EmpresaControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private EmpresaModelo $empresaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL LISTADO CRUD DE EMPRESAS CON PERMISOS POR PERFIL.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/empresas/ver');
        $esSuperusuario = $this->esSuperusuario($usuario);

        $this->vista->renderizar('sistema/empresas', [
            'titulo' => 'Empresas',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'empresas' => $this->empresaModelo->listar($esSuperusuario ? null : (int) $usuario['empresa_id']),
            'permisos' => $this->obtenerPermisos($usuario),
            'esSuperusuario' => $esSuperusuario,
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'EMPRESA_RUC_INVALIDO',
                'EMPRESA_DATOS_OBLIGATORIOS',
                'EMPRESA_EMAIL_INVALIDO',
                'CONFIRMAR_INACTIVAR_EMPRESA',
                'CONFIRMAR_ELIMINAR_EMPRESA',
            ]),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UNA EMPRESA VALIDANDO RUC, EMAIL Y CAMPOS OBLIGATORIOS.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirSuperusuario($usuario);
        $this->exigirPermiso('/sistema/empresas/crear');

        try {
            $datos = $this->normalizarDatosFormulario();
            $this->validarDatos($datos);
            $this->empresaModelo->crear($datos, (int) $usuario['id']);
            $this->guardarMensajeCodigo('EMPRESA_CREADA');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CREAR EMPRESA', $excepcion);
            $this->guardarMensajeError($excepcion, 'ERROR_GENERICO_GUARDAR');
        }

        $this->redirigir('/sistema/empresas');
    }

    /**
     * ***************************************************************************
     * * ACTUALIZA UNA EMPRESA EXISTENTE VALIDANDO SUS DATOS PRINCIPALES.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/empresas/editar');

        try {
            $empresaId = (int) ($_POST['empresa_id'] ?? 0);
            if ($empresaId <= 0) {
                throw new \InvalidArgumentException('Empresa no valida para editar.');
            }
            $this->validarEmpresaPermitida($empresaId, $usuario);

            $datos = $this->normalizarDatosFormulario();
            $this->validarDatos($datos);
            $this->empresaModelo->actualizar($empresaId, $datos, (int) $usuario['id']);
            $this->guardarMensajeCodigo('EMPRESA_ACTUALIZADA');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('EDITAR EMPRESA', $excepcion);
            $this->guardarMensajeError($excepcion, 'ERROR_GENERICO_GUARDAR');
        }

        $this->redirigir('/sistema/empresas');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UNA EMPRESA INACTIVA DESDE EL CRUD DE SISTEMA.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/sistema/empresas/activar', 'ACTIVO', 'EMPRESA_ACTIVADA');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UNA EMPRESA ACTIVA SIN ELIMINAR SUS DATOS.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/sistema/empresas/inactivar', 'INACTIVO', 'EMPRESA_INACTIVADA');
    }

    /**
     * ***************************************************************************
     * * ELIMINA LOGICAMENTE UNA EMPRESA DEL LISTADO PRINCIPAL.
     * ***************************************************************************
     */
    public function eliminar(): void
    {
        $this->cambiarEstado('/sistema/empresas/eliminar', 'ELIMINADO', 'EMPRESA_ELIMINADA');
    }

    /**
     * ***************************************************************************
     * * CAMBIA EL ESTADO DE UNA EMPRESA VALIDANDO PERMISO Y SESION.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $codigoExito): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirSuperusuario($usuario);
        $this->exigirPermiso($permiso);

        try {
            $empresaId = (int) ($_POST['empresa_id'] ?? 0);
            if ($empresaId <= 0) {
                throw new \InvalidArgumentException('Empresa no valida para cambiar estado.');
            }

            $this->empresaModelo->cambiarEstado($empresaId, $estado, (int) $usuario['id']);
            $this->guardarMensajeCodigo($codigoExito);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO EMPRESA', $excepcion);
            $this->guardarMensajeError($excepcion, 'ERROR_GENERICO_GUARDAR');
        }

        $this->redirigir('/sistema/empresas');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA CAMPOS DEL FORMULARIO DE EMPRESA PARA CREACION O EDICION.
     * ***************************************************************************
     */
    private function normalizarDatosFormulario(): array
    {
        return [
            'ruc' => preg_replace('/\D+/', '', (string) ($_POST['ruc'] ?? '')),
            'razon_social' => trim((string) ($_POST['razon_social'] ?? '')),
            'nombre_comercial' => trim((string) ($_POST['nombre_comercial'] ?? '')),
            'direccion' => trim((string) ($_POST['direccion'] ?? '')),
            'telefono' => trim((string) ($_POST['telefono'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'obligado_contabilidad' => isset($_POST['obligado_contabilidad']),
            'contribuyente_especial' => isset($_POST['contribuyente_especial']),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA RUC ECUATORIANO, EMAIL Y CAMPOS OBLIGATORIOS DE EMPRESA.
     * ***************************************************************************
     */
    private function validarDatos(array $datos): void
    {
        if (!ValidadorIdentificacion::validarRuc($datos['ruc'])) {
            throw new \InvalidArgumentException('EMPRESA_RUC_INVALIDO');
        }

        foreach (['razon_social', 'nombre_comercial', 'direccion'] as $campo) {
            if ($datos[$campo] === '') {
                throw new \InvalidArgumentException('EMPRESA_DATOS_OBLIGATORIOS');
            }
        }

        if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('EMPRESA_EMAIL_INVALIDO');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE LOS PERMISOS DE ACCION PARA MOSTRAR U OCULTAR BOTONES.
     * ***************************************************************************
     */
    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];
        $esSuperusuario = $this->esSuperusuario($usuario);

        return [
            'crear' => $esSuperusuario && $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/empresas/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/empresas/editar'),
            'activar' => $esSuperusuario && $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/empresas/activar'),
            'inactivar' => $esSuperusuario && $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/empresas/inactivar'),
            'eliminar' => $esSuperusuario && $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/empresas/eliminar'),
        ];
    }

    /**
     * ***************************************************************************
     * * IDENTIFICA SI EL USUARIO ACTUAL ES SUPERUSUARIO GLOBAL.
     * ***************************************************************************
     */
    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
    }

    /**
     * ***************************************************************************
     * * EXIGE PERFIL SUPERUSUARIO PARA ACCIONES GLOBALES DE EMPRESA.
     * ***************************************************************************
     */
    private function exigirSuperusuario(array $usuario): void
    {
        if (!$this->esSuperusuario($usuario)) {
            $this->guardarMensajeCodigo('ERROR_SIN_PERMISO');
            $this->redirigir('/dashboard');
        }
    }

    /**
     * ***************************************************************************
     * * VALIDA QUE USUARIO NORMAL SOLO EDITE SU EMPRESA ACTIVA.
     * ***************************************************************************
     */
    private function validarEmpresaPermitida(int $empresaId, array $usuario): void
    {
        if (!$this->esSuperusuario($usuario) && $empresaId !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('ERROR_SIN_PERMISO');
        }
    }

    /**
     * ***************************************************************************
     * * EXIGE SESION ACTIVA Y REDIRIGE AL LOGIN SI NO EXISTE.
     * ***************************************************************************
     */
    private function exigirSesion(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->redirigir('/login');
        }

        return $usuario;
    }

    /**
     * ***************************************************************************
     * * VALIDA EL PERMISO DEL USUARIO ACTUAL PARA UNA ACCION.
     * ***************************************************************************
     */
    private function exigirPermiso(string $url): void
    {
        $usuario = $this->exigirSesion();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->guardarMensajeCodigo('ERROR_SIN_PERMISO');
            $this->redirigir('/dashboard');
        }
    }

    /**
     * ***************************************************************************
     * * REDIRIGE A UNA RUTA LIMPIA USANDO LA URL BASE DEL SISTEMA.
     * ***************************************************************************
     */
    private function redirigir(string $ruta): never
    {
        header('Location: ' . rtrim($this->configuracion->obtener('APP_URL', ''), '/') . $ruta);
        exit;
    }

    /**
     * ***************************************************************************
     * * REGISTRA ERRORES CONTROLADOS DEL CRUD EMPRESA EN EL LOG DEL SISTEMA.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribir($accion . ': ' . $excepcion->getMessage());
    }

    /**
     * ***************************************************************************
     * * GUARDA MENSAJE DE ERROR USANDO CODIGO CONFIGURADO EN BASE.
     * ***************************************************************************
     */
    private function guardarMensajeError(Throwable $excepcion, string $codigoDefecto): void
    {
        $codigo = preg_match('/^[A-Z0-9_]+$/', $excepcion->getMessage()) ? $excepcion->getMessage() : $codigoDefecto;
        $this->guardarMensajeCodigo($codigo);
    }

    /**
     * ***************************************************************************
     * * GUARDA UN MENSAJE DE SESION USANDO UN CODIGO CONFIGURADO.
     * ***************************************************************************
     */
    private function guardarMensajeCodigo(string $codigo): void
    {
        $mensaje = $this->mensajeSistemaModelo->obtener($codigo);
        $this->sesion->guardarMensaje($mensaje['icono'], $mensaje['titulo'], $mensaje['texto'], $mensaje['tiempo'], $mensaje['posicion']);
    }
}
