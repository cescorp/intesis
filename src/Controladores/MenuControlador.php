<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class MenuControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD GLOBAL DE MENUS Y BOTONES INTERNOS.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/menus/ver');

        $this->vista->renderizar('sistema/menus', [
            'titulo' => 'Menus',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'menusCrud' => $this->menuModelo->listarTodos(),
            'menusPadre' => $this->menuModelo->listarMenusPadre(),
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'CONFIRMAR_INACTIVAR_MENU',
            ]),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UN MENU O BOTON VALIDANDO TIPO, PADRE Y URL UNICA.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardar(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UN MENU O BOTON EXISTENTE.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardar(true);
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DEL MENU.
     * ***************************************************************************
     */
    private function guardar(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/sistema/menus/editar' : '/sistema/menus/crear');
        try {
            $menuId = (int) ($_POST['menu_id'] ?? 0);
            if ($editar && !$this->menuModelo->buscar($menuId)) {
                throw new \InvalidArgumentException('Menu no valido.');
            }

            $menuActual = $editar ? $this->menuModelo->buscar($menuId) : null;
            $datos = $this->normalizarDatos($menuActual, $usuario);
            $this->validarDatos($datos, $editar ? $menuId : null);
            $editar
                ? $this->menuModelo->actualizar($menuId, $datos, (int) $usuario['id'])
                : $this->menuModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Menu actualizado' : 'Menu creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR MENU' : 'CREAR MENU', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/menus');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN MENU O BOTON.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/sistema/menus/activar', 1, 'Menu activado', 'El registro quedo activo.');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN MENU O BOTON SIN ELIMINARLO.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/sistema/menus/inactivar', 0, 'Menu inactivado', 'El registro quedo inactivo.');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO DE MENU O BOTON.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, int $estado, string $titulo, string $texto): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $menuId = (int) ($_POST['menu_id'] ?? 0);
            if (!$this->menuModelo->buscar($menuId)) {
                throw new \InvalidArgumentException('Menu no valido.');
            }
            $this->menuModelo->cambiarEstado($menuId, $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, $texto);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO MENU', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo cambiar estado', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/menus');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA CAMPOS DEL FORMULARIO DE MENU.
     * ***************************************************************************
     */
    private function normalizarDatos(?array $menuActual, array $usuario): array
    {
        $padre = (int) ($_POST['padre'] ?? 0) ?: null;
        $tipo = strtoupper(trim((string) ($_POST['tipo'] ?? 'M')));
        $esHijo = $padre !== null;
        $estadoActual = $menuActual ? (int) $menuActual['sis_menu_estado'] : 1;
        $estadoFormulario = (int) ($_POST['estado'] ?? 1) === 1 ? 1 : 0;
        $estado = $esHijo && $this->esSuperusuario($usuario)
            ? $estadoFormulario
            : $estadoActual;

        return [
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'padre' => $padre,
            'icono' => trim((string) ($_POST['icono'] ?? 'bi bi-circle')),
            'url' => '/' . trim((string) ($_POST['url'] ?? ''), '/'),
            'orden' => max(1, (int) ($_POST['orden'] ?? 1)),
            'tipo' => $tipo,
            'estado' => $estado,
            'crear_ver' => isset($_POST['crear_ver']),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA DATOS, URL UNICA, TIPO Y PADRE DE MENUS.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $menuId = null): void
    {
        if ($datos['nombre'] === '' || $datos['url'] === '/' || !in_array($datos['tipo'], ['M', 'B'], true)) {
            throw new \InvalidArgumentException('Nombre, tipo y URL son obligatorios.');
        }

        if ($this->menuModelo->existeUrl($datos['url'], $menuId)) {
            throw new \InvalidArgumentException('Ya existe un menu o boton con esa URL.');
        }

        if ($datos['tipo'] === 'B' && empty($datos['padre'])) {
            throw new \InvalidArgumentException('Las acciones internas deben depender de un menu.');
        }

        if (!empty($datos['padre'])) {
            $padre = $this->menuModelo->buscar((int) $datos['padre']);
            if (!$padre || $padre['sis_menu_tipo'] !== 'M' || (int) $padre['sis_menu_estado'] !== 1) {
                throw new \InvalidArgumentException('El padre debe ser un menu activo.');
            }
            if ($datos['tipo'] === 'M' && $this->menuModelo->calcularNivel((int) $datos['padre']) >= 3) {
                throw new \InvalidArgumentException('Solo se permiten hasta tres niveles de menu.');
            }
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE PERMISOS DE BOTONES DEL CRUD.
     * ***************************************************************************
     */
    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];
        return [
            'crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/menus/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/menus/editar'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/menus/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/menus/inactivar'),
        ];
    }

    /**
     * ***************************************************************************
     * * EXIGE SESION ACTIVA.
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
     * * IDENTIFICA SI EL PERFIL ACTUAL ES SUPERUSUARIO.
     * ***************************************************************************
     */
    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
    }

    /**
     * ***************************************************************************
     * * VALIDA PERMISO DE BACKEND PARA LA ACCION.
     * ***************************************************************************
     */
    private function exigirPermiso(string $url): void
    {
        $usuario = $this->exigirSesion();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->sesion->guardarMensaje('error', 'Acceso restringido', 'Su perfil no tiene permiso para esta accion.');
            $this->redirigir('/dashboard');
        }
    }

    /**
     * ***************************************************************************
     * * REDIRIGE A RUTA LIMPIA.
     * ***************************************************************************
     */
    private function redirigir(string $ruta): never
    {
        header('Location: ' . rtrim($this->configuracion->obtener('APP_URL', ''), '/') . $ruta);
        exit;
    }

    /**
     * ***************************************************************************
     * * REGISTRA ERRORES CONTROLADOS DEL CRUD MENU.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribir($accion . ': ' . $excepcion->getMessage());
    }
}
