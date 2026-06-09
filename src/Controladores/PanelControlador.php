<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\ModuloModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;

final class PanelControlador
{
    use ControladorComun;
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private ModuloModelo $moduloModelo,
        private MenuModelo $menuModelo,
        private Configuracion $configuracion
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL DASHBOARD PRINCIPAL CON LOS MODULOS ACTIVADOS POR LICENCIA.
     * ***************************************************************************
     */
    public function mostrarDashboard(): void
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->redirigir('/login');
        }

        $modulos = $this->moduloModelo->listarPorEmpresa((int) $usuario['empresa_id']);
        $this->vista->renderizar('panel/dashboard', [
            'titulo' => 'Dashboard',
            'usuario' => $usuario,
            'modulos' => $modulos,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'mensaje' => $this->sesion->consumirMensaje(),
        ]);
    }

}
