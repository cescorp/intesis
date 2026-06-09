<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\BodegaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class BodegaControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private BodegaModelo $bodegaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD DE BODEGAS POR EMPRESA.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/bodegas/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $this->vista->renderizar('inventario/bodegas', [
            'titulo' => 'Bodegas',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'bodegas' => $this->bodegaModelo->listar((int) $usuario['empresa_id'], $verTodas),
            'empresas' => $this->bodegaModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'esSuperusuario' => $verTodas,
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'USUARIO_DATOS_OBLIGATORIOS',
                'ERROR_GENERICO_GUARDAR',
                'ERROR_SIN_PERMISO',
                'CONFIRMAR_INACTIVAR_BODEGA',
                'CONFIRMAR_ELIMINAR_BODEGA',
            ]),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UNA BODEGA.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardar(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UNA BODEGA.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardar(true);
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE BODEGA.
     * ***************************************************************************
     */
    private function guardar(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/inventario/bodegas/editar' : '/inventario/bodegas/crear');
        try {
            $bodegaId = (int) ($_POST['bodega_id'] ?? 0);
            $bodega = $editar ? $this->obtenerBodegaPermitida($bodegaId, $usuario) : null;
            $datos = $this->normalizarDatos($usuario);
            $this->validarDatos($datos, $editar ? $bodegaId : null);
            $editar
                ? $this->bodegaModelo->actualizar((int) $bodega['inv_bodega_id'], $datos, (int) $usuario['id'])
                : $this->bodegaModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Bodega actualizada' : 'Bodega creada', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR BODEGA' : 'CREAR BODEGA', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/bodegas');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UNA BODEGA.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/inventario/bodegas/activar', 'ACTIVO', 'Bodega activada');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UNA BODEGA.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/inventario/bodegas/inactivar', 'INACTIVO', 'Bodega inactivada');
    }

    /**
     * ***************************************************************************
     * * ELIMINA LOGICAMENTE UNA BODEGA SI NO TIENE USO.
     * ***************************************************************************
     */
    public function eliminar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/bodegas/eliminar');
        try {
            $bodega = $this->obtenerBodegaPermitida((int) ($_POST['bodega_id'] ?? 0), $usuario);
            if ($this->bodegaModelo->estaUsada((int) $bodega['inv_bodega_id'])) {
                throw new \InvalidArgumentException('No se puede eliminar una bodega con stock o movimientos.');
            }
            $this->bodegaModelo->cambiarEstado((int) $bodega['inv_bodega_id'], 'ELIMINADO', (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Bodega eliminada', 'La bodega fue eliminada logicamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ELIMINAR BODEGA', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/bodegas');
    }

    /**
     * ***************************************************************************
     * * LISTA USUARIOS Y PERMISOS DE UNA BODEGA EN FORMATO JSON.
     * ***************************************************************************
     */
    public function usuarios(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/bodegas/usuarios');
        try {
            $bodega = $this->obtenerBodegaPermitida((int) ($_GET['bodega_id'] ?? 0), $usuario);
            $empresaId = (int) $bodega['sis_empresa_id'];
            $this->responderJson([
                'ok' => true,
                'data' => [
                    'bodega' => [
                        'id' => (int) $bodega['inv_bodega_id'],
                        'nombre' => (string) $bodega['inv_bodega_nombre'],
                        'codigo' => (string) $bodega['inv_bodega_codigo'],
                    ],
                    'usuarios' => $this->bodegaModelo->listarUsuariosEmpresa($empresaId),
                    'asignaciones' => $this->bodegaModelo->listarUsuariosBodega($empresaId, (int) $bodega['inv_bodega_id']),
                    'permisos' => $this->obtenerPermisos($usuario),
                ],
            ]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('LISTAR USUARIOS BODEGA', $excepcion);
            $this->responderJson(['ok' => false, 'mensaje' => $excepcion->getMessage()], 400);
        }
    }

    /**
     * ***************************************************************************
     * * GUARDA PERMISO DE USUARIO PARA UNA BODEGA.
     * ***************************************************************************
     */
    public function guardarUsuario(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/bodegas/usuarios/guardar');
        try {
            $bodega = $this->obtenerBodegaPermitida((int) ($_POST['bodega_id'] ?? 0), $usuario);
            $usuarioAsignadoId = (int) ($_POST['usuario_id'] ?? 0);
            if ($usuarioAsignadoId <= 0) {
                throw new \InvalidArgumentException('Seleccione un usuario valido.');
            }
            $this->bodegaModelo->guardarUsuarioBodega(
                (int) $bodega['sis_empresa_id'],
                (int) $bodega['inv_bodega_id'],
                $usuarioAsignadoId,
                !empty($_POST['predeterminada']),
                (int) $usuario['id']
            );
            $this->responderJson(['ok' => true, 'mensaje' => 'Usuario asignado a la bodega.']);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('GUARDAR USUARIO BODEGA', $excepcion);
            $this->responderJson(['ok' => false, 'mensaje' => $excepcion->getMessage()], 400);
        }
    }

    /**
     * ***************************************************************************
     * * ACTIVA PERMISO DE USUARIO PARA UNA BODEGA.
     * ***************************************************************************
     */
    public function activarUsuario(): void
    {
        $this->cambiarEstadoUsuario('/inventario/bodegas/usuarios/activar', 1, 'Usuario activado para la bodega.');
    }

    /**
     * ***************************************************************************
     * * INACTIVA PERMISO DE USUARIO PARA UNA BODEGA.
     * ***************************************************************************
     */
    public function inactivarUsuario(): void
    {
        $this->cambiarEstadoUsuario('/inventario/bodegas/usuarios/inactivar', 0, 'Usuario inactivado para la bodega.');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO O INACTIVO DE BODEGA.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $titulo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $bodega = $this->obtenerBodegaPermitida((int) ($_POST['bodega_id'] ?? 0), $usuario);
            $this->bodegaModelo->cambiarEstado((int) $bodega['inv_bodega_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO BODEGA', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/bodegas');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO JSON DE PERMISO USUARIO BODEGA.
     * ***************************************************************************
     */
    private function cambiarEstadoUsuario(string $permiso, int $estado, string $mensaje): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson($permiso);
        try {
            $bodega = $this->obtenerBodegaPermitida((int) ($_POST['bodega_id'] ?? 0), $usuario);
            $this->bodegaModelo->cambiarEstadoUsuarioBodega(
                (int) $bodega['sis_empresa_id'],
                (int) ($_POST['asignacion_id'] ?? 0),
                $estado,
                (int) $usuario['id']
            );
            $this->responderJson(['ok' => true, 'mensaje' => $mensaje]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR USUARIO BODEGA', $excepcion);
            $this->responderJson(['ok' => false, 'mensaje' => $excepcion->getMessage()], 400);
        }
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE FORMULARIO DE BODEGA.
     * ***************************************************************************
     */
    private function normalizarDatos(array $usuario): array
    {
        $empresaId = $this->esSuperusuario($usuario)
            ? (int) ($_POST['empresa_id'] ?? 0)
            : (int) $usuario['empresa_id'];

        return [
            'empresa_id' => $empresaId,
            'codigo' => strtoupper(trim((string) ($_POST['codigo'] ?? ''))),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'direccion' => trim((string) ($_POST['direccion'] ?? '')),
            'principal' => !empty($_POST['principal']),
            'virtual' => !empty($_POST['virtual']),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA REGLAS DE NEGOCIO DE BODEGA.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $bodegaId): void
    {
        if ($datos['empresa_id'] <= 0 || $datos['codigo'] === '' || $datos['nombre'] === '') {
            throw new \InvalidArgumentException('Empresa, codigo y nombre son obligatorios.');
        }
        if (!preg_match('/^[A-Z0-9_-]{1,30}$/', $datos['codigo'])) {
            throw new \InvalidArgumentException('El codigo solo permite letras, numeros, guion y guion bajo.');
        }
        if ($datos['virtual'] && $datos['principal']) {
            throw new \InvalidArgumentException('Una bodega virtual no puede ser principal.');
        }
        if ($this->bodegaModelo->existeCodigo((int) $datos['empresa_id'], $datos['codigo'], $bodegaId)) {
            throw new \InvalidArgumentException('Ya existe una bodega con ese codigo en la empresa.');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE BODEGA Y VALIDA ALCANCE DE EMPRESA.
     * ***************************************************************************
     */
    private function obtenerBodegaPermitida(int $bodegaId, array $usuario): array
    {
        $bodega = $bodegaId > 0 ? $this->bodegaModelo->buscar($bodegaId) : null;
        if (!$bodega) {
            throw new \InvalidArgumentException('Bodega no valida.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $bodega['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar bodegas de otra empresa.');
        }

        return $bodega;
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
            'crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/editar'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/inactivar'),
            'eliminar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/eliminar'),
            'usuarios' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/usuarios'),
            'usuariosGuardar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/usuarios/guardar'),
            'usuariosActivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/usuarios/activar'),
            'usuariosInactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/bodegas/usuarios/inactivar'),
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
     * * EXIGE SESION ACTIVA PARA RESPUESTAS JSON.
     * ***************************************************************************
     */
    private function exigirSesionJson(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->responderJson(['ok' => false, 'mensaje' => 'Sesion no activa.'], 401);
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
     * * VALIDA PERMISO BACKEND PARA ACCIONES JSON.
     * ***************************************************************************
     */
    private function exigirPermisoJson(string $url): void
    {
        $usuario = $this->exigirSesionJson();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->responderJson(['ok' => false, 'mensaje' => 'Su perfil no tiene permiso para esta accion.'], 403);
        }
    }

    /**
     * ***************************************************************************
     * * RESPONDE JSON Y FINALIZA LA PETICION.
     * ***************************************************************************
     */
    private function responderJson(array $datos, int $estado = 200): never
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
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
     * * REGISTRA ERRORES DEL CRUD BODEGA.
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
