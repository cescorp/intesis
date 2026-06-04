<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\CategoriaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class CategoriaControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private CategoriaModelo $categoriaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD DE CATEGORIAS POR EMPRESA.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/categorias/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $this->vista->renderizar('inventario/categorias', [
            'titulo' => 'Categorias',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'categorias' => $this->categoriaModelo->listar((int) $usuario['empresa_id'], $verTodas),
            'empresas' => $this->categoriaModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'esSuperusuario' => $verTodas,
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos(['USUARIO_DATOS_OBLIGATORIOS']),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UNA CATEGORIA.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardar(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UNA CATEGORIA.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardar(true);
    }

    /**
     * ***************************************************************************
     * * CREA UNA CATEGORIA POR AJAX PARA EL FORMULARIO DE PRODUCTO.
     * ***************************************************************************
     */
    public function crearAjax(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/categorias/crear');
        try {
            $datos = $this->normalizarDatos($usuario);
            $this->validarDatos($datos);
            $categoriaId = $this->categoriaModelo->crear($datos, (int) $usuario['id']);
            $this->responderJson(true, 'REGISTRO_GUARDADO', 'Registro guardado correctamente', [
                'id' => $categoriaId,
                'nombre' => $datos['nombre'],
                'empresa_id' => $datos['empresa_id'],
            ]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CREAR CATEGORIA AJAX', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * LISTA CATEGORIAS ACTIVAS POR AJAX PARA SELECTS.
     * ***************************************************************************
     */
    public function listarAjax(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/categorias/ver');
        $verTodas = $this->esSuperusuario($usuario);
        $empresaId = $verTodas ? (int) ($_GET['empresa_id'] ?? 0) : (int) $usuario['empresa_id'];
        if ($empresaId <= 0) {
            $this->responderJson(false, 'ERROR_VALIDACION', 'Empresa no valida.');
        }
        $this->responderJson(true, 'REGISTROS_LISTADOS', 'Registros listados correctamente', [
            'categorias' => $this->categoriaModelo->listar($empresaId, false, true),
        ]);
    }

    /**
     * ***************************************************************************
     * * ACTIVA UNA CATEGORIA.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/inventario/categorias/activar', 'ACTIVO', 'Categoria activada');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UNA CATEGORIA.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/inventario/categorias/inactivar', 'INACTIVO', 'Categoria inactivada');
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE CATEGORIA.
     * ***************************************************************************
     */
    private function guardar(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/inventario/categorias/editar' : '/inventario/categorias/crear');
        try {
            $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
            $categoria = $editar ? $this->obtenerCategoriaPermitida($categoriaId, $usuario) : null;
            $datos = $this->normalizarDatos($usuario);
            $this->validarDatos($datos, $editar ? $categoriaId : null);
            $editar
                ? $this->categoriaModelo->actualizar((int) $categoria['inv_categoria_id'], $datos, (int) $usuario['id'])
                : $this->categoriaModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Categoria actualizada' : 'Categoria creada', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR CATEGORIA' : 'CREAR CATEGORIA', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/categorias');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO O INACTIVO DE CATEGORIA.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $titulo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $categoria = $this->obtenerCategoriaPermitida((int) ($_POST['categoria_id'] ?? 0), $usuario);
            $this->categoriaModelo->cambiarEstado((int) $categoria['inv_categoria_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO CATEGORIA', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/categorias');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE FORMULARIO DE CATEGORIA.
     * ***************************************************************************
     */
    private function normalizarDatos(array $usuario): array
    {
        return [
            'empresa_id' => $this->esSuperusuario($usuario) ? (int) ($_POST['empresa_id'] ?? 0) : (int) $usuario['empresa_id'],
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA REGLAS DE NEGOCIO DE CATEGORIA.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $categoriaId = null): void
    {
        if ($datos['empresa_id'] <= 0 || $datos['nombre'] === '') {
            throw new \InvalidArgumentException('Empresa y nombre son obligatorios.');
        }
        if ($this->categoriaModelo->existeNombre((int) $datos['empresa_id'], $datos['nombre'], $categoriaId)) {
            throw new \InvalidArgumentException('Ya existe una categoria con ese nombre en la empresa.');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE CATEGORIA Y VALIDA ALCANCE DE EMPRESA.
     * ***************************************************************************
     */
    private function obtenerCategoriaPermitida(int $categoriaId, array $usuario): array
    {
        $categoria = $categoriaId > 0 ? $this->categoriaModelo->buscar($categoriaId) : null;
        if (!$categoria) {
            throw new \InvalidArgumentException('Categoria no valida.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $categoria['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar categorias de otra empresa.');
        }

        return $categoria;
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
            'crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/categorias/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/categorias/editar'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/categorias/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/categorias/inactivar'),
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
        $respuesta = ['ok' => $ok, 'codigo' => $codigo, 'mensaje' => $mensaje, 'data' => $data];
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
     * * REGISTRA ERRORES DEL CRUD CATEGORIA.
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
