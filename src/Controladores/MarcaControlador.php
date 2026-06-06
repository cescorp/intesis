<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MarcaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class MarcaControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private MarcaModelo $marcaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD DE MARCAS POR EMPRESA.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/marcas/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $this->vista->renderizar('inventario/marcas', [
            'titulo' => 'Marcas',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'marcas' => $this->marcaModelo->listar((int) $usuario['empresa_id'], $verTodas),
            'empresas' => $this->marcaModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'esSuperusuario' => $verTodas,
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'USUARIO_DATOS_OBLIGATORIOS',
                'CONFIRMAR_INACTIVAR_MARCA',
            ]),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UNA MARCA.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardar(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UNA MARCA.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardar(true);
    }

    /**
     * ***************************************************************************
     * * CREA UNA MARCA POR AJAX PARA EL FORMULARIO DE PRODUCTO.
     * ***************************************************************************
     */
    public function crearAjax(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/marcas/crear');
        try {
            $datos = $this->normalizarDatos($usuario);
            $this->validarDatos($datos);
            $marcaId = $this->marcaModelo->crear($datos, (int) $usuario['id']);
            $this->responderJson(true, 'REGISTRO_GUARDADO', 'Registro guardado correctamente', [
                'id' => $marcaId,
                'nombre' => $datos['nombre'],
                'empresa_id' => $datos['empresa_id'],
            ]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CREAR MARCA AJAX', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * LISTA MARCAS ACTIVAS POR AJAX PARA SELECTS.
     * ***************************************************************************
     */
    public function listarAjax(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/marcas/ver');
        $verTodas = $this->esSuperusuario($usuario);
        $empresaId = $verTodas ? (int) ($_GET['empresa_id'] ?? 0) : (int) $usuario['empresa_id'];
        if ($empresaId <= 0) {
            $this->responderJson(false, 'ERROR_VALIDACION', 'Empresa no valida.');
        }
        $this->responderJson(true, 'REGISTROS_LISTADOS', 'Registros listados correctamente', [
            'marcas' => $this->marcaModelo->listar($empresaId, false, true),
        ]);
    }

    /**
     * ***************************************************************************
     * * ACTIVA UNA MARCA.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/inventario/marcas/activar', 'ACTIVO', 'Marca activada');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UNA MARCA.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/inventario/marcas/inactivar', 'INACTIVO', 'Marca inactivada');
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE MARCA.
     * ***************************************************************************
     */
    private function guardar(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/inventario/marcas/editar' : '/inventario/marcas/crear');
        try {
            $marcaId = (int) ($_POST['marca_id'] ?? 0);
            $marca = $editar ? $this->obtenerMarcaPermitida($marcaId, $usuario) : null;
            $datos = $this->normalizarDatos($usuario);
            $this->validarDatos($datos, $editar ? $marcaId : null);
            $editar
                ? $this->marcaModelo->actualizar((int) $marca['inv_marca_id'], $datos, (int) $usuario['id'])
                : $this->marcaModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Marca actualizada' : 'Marca creada', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR MARCA' : 'CREAR MARCA', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/marcas');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO O INACTIVO DE MARCA.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $titulo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $marca = $this->obtenerMarcaPermitida((int) ($_POST['marca_id'] ?? 0), $usuario);
            $this->marcaModelo->cambiarEstado((int) $marca['inv_marca_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO MARCA', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/marcas');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE FORMULARIO DE MARCA.
     * ***************************************************************************
     */
    private function normalizarDatos(array $usuario): array
    {
        return [
            'empresa_id' => $this->esSuperusuario($usuario) ? (int) ($_POST['empresa_id'] ?? 0) : (int) $usuario['empresa_id'],
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA REGLAS DE NEGOCIO DE MARCA.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $marcaId = null): void
    {
        if ($datos['empresa_id'] <= 0 || $datos['nombre'] === '') {
            throw new \InvalidArgumentException('Empresa y nombre son obligatorios.');
        }
        if ($this->marcaModelo->existeNombre((int) $datos['empresa_id'], $datos['nombre'], $marcaId)) {
            throw new \InvalidArgumentException('Ya existe una marca con ese nombre en la empresa.');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE MARCA Y VALIDA ALCANCE DE EMPRESA.
     * ***************************************************************************
     */
    private function obtenerMarcaPermitida(int $marcaId, array $usuario): array
    {
        $marca = $marcaId > 0 ? $this->marcaModelo->buscar($marcaId) : null;
        if (!$marca) {
            throw new \InvalidArgumentException('Marca no valida.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $marca['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar marcas de otra empresa.');
        }

        return $marca;
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
            'crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/marcas/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/marcas/editar'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/marcas/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/marcas/inactivar'),
        ];
    }

    /**
     * ***************************************************************************
     * * IDENTIFICA PERFIL SUPERUSUARIO.
     * ***************************************************************************
     */
    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
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
     * * EXIGE SESION ACTIVA PARA AJAX.
     * ***************************************************************************
     */
    private function exigirSesionJson(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->responderJson(false, 'ERROR_SESION', 'Sesion no activa.');
        }

        return $usuario;
    }

    /**
     * ***************************************************************************
     * * VALIDA PERMISO BACKEND PARA ACCION.
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
     * * VALIDA PERMISO BACKEND PARA AJAX.
     * ***************************************************************************
     */
    private function exigirPermisoJson(string $url): void
    {
        $usuario = $this->exigirSesionJson();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->responderJson(false, 'ERROR_SIN_PERMISO', 'Su perfil no tiene permiso para esta accion.');
        }
    }

    /**
     * ***************************************************************************
     * * ENVIA RESPUESTA JSON ESTANDAR.
     * ***************************************************************************
     */
    private function responderJson(bool $ok, string $codigo, string $mensaje, array $data = []): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $mensajeSistema = $this->mensajeSistemaModelo->obtener($codigo);
        $respuesta = [
            'ok' => $ok,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'mensaje_sistema' => [
                'codigo' => $codigo,
                'tipo' => $mensajeSistema['tipo'],
                'icono' => $mensajeSistema['icono'],
                'titulo' => $mensajeSistema['titulo'],
                'texto' => $mensajeSistema['texto'],
                'tiempo' => $mensajeSistema['tiempo'],
                'posicion' => $mensajeSistema['posicion'],
            ],
            'data' => $data,
        ];
        if (!$ok) {
            $respuesta['errores'] = [];
        }
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
        exit;
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
     * * REGISTRA ERRORES DEL CRUD MARCA.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribir($accion . ': ' . $excepcion->getMessage());
    }

    /**
     * ***************************************************************************
     * * GUARDA MENSAJE DE ERROR CONTROLADO.
     * ***************************************************************************
     */
    private function guardarMensajeError(Throwable $excepcion): void
    {
        $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
    }
}
