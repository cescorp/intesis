<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\BodegaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Intesis\Servicios\AutenticacionServicio;

final class AutenticacionControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private AutenticacionServicio $autenticacionServicio,
        private Configuracion $configuracion,
        private BodegaModelo $bodegaModelo
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA LA PANTALLA DE LOGIN O REDIRIGE AL DASHBOARD SI YA HAY SESION.
     * ***************************************************************************
     */
    public function mostrarLogin(): void
    {
        if ($this->sesion->autenticado()) {
            $this->redirigir('/dashboard');
        }

        $this->vista->renderizar('autenticacion/login', [
            'titulo' => 'Ingreso',
            'error' => $_SESSION['error_login'] ?? null,
            'correoIngresado' => $_SESSION['correo_login'] ?? '',
            'seleccionEmpresas' => $_SESSION['login_empresas'] ?? null,
            'usuarioPendiente' => $_SESSION['login_usuario'] ?? null,
        ]);
        unset($_SESSION['error_login'], $_SESSION['correo_login']);
    }

    /**
     * ***************************************************************************
     * * PROCESA EL FORMULARIO DE LOGIN Y CREA LA SESION DEL USUARIO VALIDO.
     * ***************************************************************************
     */
    public function procesarLogin(): void
    {
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $clave = (string) ($_POST['clave'] ?? '');
        $usuario = $this->autenticacionServicio->autenticar($correo, $clave);

        if (!$usuario) {
            $_SESSION['error_login'] = 'Credenciales incorrectas o usuario inactivo.';
            $_SESSION['correo_login'] = $correo;
            $this->redirigir('/login');
        }

        if (count($usuario['empresas']) > 1) {
            $_SESSION['login_usuario'] = [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'correo' => $usuario['correo'],
            ];
            $_SESSION['login_empresas'] = $usuario['empresas'];
            $this->redirigir('/login');
        }

        $this->sesion->guardarUsuario($this->construirSesionUsuario($usuario, $usuario['empresas'][0]));
        $this->redirigir('/dashboard');
    }

    /**
     * ***************************************************************************
     * * COMPLETA EL LOGIN CUANDO EL USUARIO TIENE VARIAS EMPRESAS ASIGNADAS.
     * ***************************************************************************
     */
    public function seleccionarEmpresa(): void
    {
        $usuario = $_SESSION['login_usuario'] ?? null;
        $empresas = $_SESSION['login_empresas'] ?? [];
        $empresaId = (int) ($_POST['empresa_id'] ?? 0);

        foreach ($empresas as $empresa) {
            if ((int) $empresa['sis_empresa_id'] === $empresaId) {
                unset($_SESSION['login_usuario'], $_SESSION['login_empresas']);
                $this->sesion->guardarUsuario($this->construirSesionUsuario($usuario, $empresa));
                $this->redirigir('/dashboard');
            }
        }

        $_SESSION['error_login'] = 'Seleccione una empresa valida.';
        $this->redirigir('/login');
    }

    /**
     * ***************************************************************************
     * * CIERRA LA SESION ACTUAL Y RETORNA AL LOGIN.
     * ***************************************************************************
     */
    public function cerrarSesion(): void
    {
        $this->sesion->cerrar();
        $this->redirigir('/login');
    }


    /**
     * ***************************************************************************
     * * CONSTRUYE LOS DATOS DE SESION SEGUN LA EMPRESA ELEGIDA.
     * ***************************************************************************
     */
    private function construirSesionUsuario(array $usuario, array $empresa): array
    {
        $empresaId = (int) $empresa['sis_empresa_id'];
        $bodega = $this->bodegaModelo->obtenerBodegaPredeterminadaUsuario($empresaId, (int) $usuario['id']);

        return [
            'id' => (int) $usuario['id'],
            'empresa_id' => $empresaId,
            'perfil_id' => (int) $empresa['sis_perfil_id'],
            'nombre' => $usuario['nombre'],
            'correo' => $usuario['correo'],
            'perfil' => $empresa['sis_perfil_nombre'],
            'perfil_codigo' => $empresa['sis_perfil_codigo'],
            'empresa' => $empresa['sis_empresa_razon_social'],
            'bodega' => $bodega['inv_bodega_nombre'] ?? null,
            'venta_todas_bodegas' => (bool) ($empresa['sis_perfil_venta_todas_bodegas'] ?? false),
        ];
    }
}
