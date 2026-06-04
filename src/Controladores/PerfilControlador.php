<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\PerfilModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class PerfilControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private PerfilModelo $perfilModelo,
        private MenuModelo $menuModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD DE PERFILES CON ARBOL DE PERMISOS.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/perfiles/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $this->vista->renderizar('sistema/perfiles', [
            'titulo' => 'Perfiles',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'perfiles' => $this->perfilModelo->listar((int) $usuario['empresa_id'], $verTodas),
            'empresas' => $this->perfilModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'menusPermisos' => $this->perfilModelo->listarMenusPermisos(),
            'permisosPerfil' => $this->obtenerPermisosPerfil($this->perfilModelo->listar((int) $usuario['empresa_id'], $verTodas)),
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'appNombre' => $this->configuracion->obtener('APP_NOMBRE', 'INTESIS'),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UN PERFIL NUEVO GENERANDO CODIGO DESDE EL NOMBRE.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardarPerfil(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UN PERFIL EXISTENTE RESPETANDO PERFILES PROTEGIDOS.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardarPerfil(true);
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE PERFIL.
     * ***************************************************************************
     */
    private function guardarPerfil(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/sistema/perfiles/editar' : '/sistema/perfiles/crear');
        try {
            $perfilId = (int) ($_POST['perfil_id'] ?? 0);
            if ($editar) {
                $perfil = $this->obtenerPerfilValido($perfilId);
                $this->exigirNoProtegido($perfil);
            }
            $datos = $this->normalizarDatos();
            $this->validarDatos($datos, $editar ? $perfilId : null);
            $this->validarAlcanceEmpresa((int) $datos['empresa_id'], $usuario);
            $editar
                ? $this->perfilModelo->actualizar($perfilId, $datos, (int) $usuario['id'])
                : $this->perfilModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Perfil actualizado' : 'Perfil creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR PERFIL' : 'CREAR PERFIL', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/perfiles');
    }

    /**
     * ***************************************************************************
     * * GUARDA LOS PERMISOS SELECCIONADOS EN EL ARBOL.
     * ***************************************************************************
     */
    public function permisos(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/perfiles/permisos');
        try {
            $perfilId = (int) ($_POST['perfil_id'] ?? 0);
            $perfil = $this->obtenerPerfilValido($perfilId);
            $this->validarAlcanceEmpresa((int) $perfil['sis_empresa_id'], $usuario);
            $this->perfilModelo->guardarPermisos($perfilId, array_map('intval', $_POST['menu_ids'] ?? []), (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Permisos guardados', 'La asignacion fue actualizada correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('PERMISOS PERFIL', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/perfiles');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN PERFIL INACTIVO.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/sistema/perfiles/activar', 1, 'Perfil activado', 'El perfil quedo activo.');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN PERFIL ACTIVO.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/sistema/perfiles/inactivar', 0, 'Perfil inactivado', 'El perfil quedo inactivo.');
    }

    /**
     * ***************************************************************************
     * * ELIMINA UN PERFIL SIN USUARIOS ASIGNADOS.
     * ***************************************************************************
     */
    public function eliminar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/perfiles/eliminar');
        try {
            $perfil = $this->obtenerPerfilValido((int) ($_POST['perfil_id'] ?? 0));
            $this->exigirNoProtegido($perfil);
            if ((int) $perfil['total_usuarios'] > 0) {
                throw new \InvalidArgumentException('No se puede eliminar un perfil con usuarios asignados.');
            }
            $this->validarAlcanceEmpresa((int) $perfil['sis_empresa_id'], $usuario);
            $this->perfilModelo->eliminar((int) $perfil['sis_perfil_id']);
            $this->sesion->guardarMensaje('success', 'Perfil eliminado', 'El perfil fue eliminado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ELIMINAR PERFIL', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo eliminar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/perfiles');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO DE UN PERFIL VALIDANDO PROTECCION Y ALCANCE.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, int $estado, string $titulo, string $texto): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $perfil = $this->obtenerPerfilValido((int) ($_POST['perfil_id'] ?? 0));
            $this->exigirNoProtegido($perfil);
            $this->validarAlcanceEmpresa((int) $perfil['sis_empresa_id'], $usuario);
            $this->perfilModelo->cambiarEstado((int) $perfil['sis_perfil_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, $texto);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO PERFIL', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo cambiar estado', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/perfiles');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA NOMBRE, EMPRESA Y CODIGO GENERADO DEL PERFIL.
     * ***************************************************************************
     */
    private function normalizarDatos(): array
    {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        return [
            'empresa_id' => (int) ($_POST['empresa_id'] ?? 0),
            'nombre' => mb_strtoupper($nombre, 'UTF-8'),
            'codigo' => $this->generarCodigo($nombre),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA CAMPOS Y CODIGO UNICO POR EMPRESA.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $perfilId = null): void
    {
        if ($datos['empresa_id'] <= 0 || $datos['nombre'] === '' || $datos['codigo'] === '') {
            throw new \InvalidArgumentException('Empresa y nombre son obligatorios.');
        }
        if ($this->perfilModelo->existeCodigo((int) $datos['empresa_id'], $datos['codigo'], $perfilId)) {
            throw new \InvalidArgumentException('Ya existe un perfil con ese codigo en la empresa.');
        }
    }

    /**
     * ***************************************************************************
     * * GENERA CODIGO MAYUSCULA SIN ESPACIOS DESDE EL NOMBRE.
     * ***************************************************************************
     */
    private function generarCodigo(string $nombre): string
    {
        $codigo = iconv('UTF-8', 'ASCII//TRANSLIT', $nombre) ?: $nombre;
        $codigo = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $codigo) ?? '');
        return substr(trim($codigo, '_'), 0, 20);
    }

    /**
     * ***************************************************************************
     * * BUSCA PERFIL O LANZA ERROR CONTROLADO.
     * ***************************************************************************
     */
    private function obtenerPerfilValido(int $perfilId): array
    {
        $perfil = $perfilId > 0 ? $this->perfilModelo->buscar($perfilId) : null;
        if (!$perfil) {
            throw new \InvalidArgumentException('Perfil no valido.');
        }
        return $perfil;
    }

    /**
     * ***************************************************************************
     * * PROTEGE EL PERFIL SUPERUSUARIO CONTRA EDICION Y ELIMINACION.
     * ***************************************************************************
     */
    private function exigirNoProtegido(array $perfil): void
    {
        if ($perfil['sis_perfil_codigo'] === 'SUPERUSUARIO') {
            throw new \InvalidArgumentException('El perfil SUPERUSUARIO no se puede modificar.');
        }
    }

    /**
     * ***************************************************************************
     * * VALIDA ALCANCE DE EMPRESA PARA USUARIOS NO SUPERUSUARIO.
     * ***************************************************************************
     */
    private function validarAlcanceEmpresa(int $empresaId, array $usuario): void
    {
        if (!$this->esSuperusuario($usuario) && $empresaId !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar perfiles de otra empresa.');
        }
    }

    /**
     * ***************************************************************************
     * * IDENTIFICA PERFIL SUPERUSUARIO USANDO CODIGO ESTABLE DEL PERFIL.
     * ***************************************************************************
     */
    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
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
            'crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/perfiles/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/perfiles/editar'),
            'permisos' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/perfiles/permisos'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/perfiles/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/perfiles/inactivar'),
            'eliminar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/perfiles/eliminar'),
        ];
    }

    /**
     * ***************************************************************************
     * * PREPARA PERMISOS ASIGNADOS POR PERFIL PARA USO EN JAVASCRIPT.
     * ***************************************************************************
     */
    private function obtenerPermisosPerfil(array $perfiles): array
    {
        $permisos = [];
        foreach ($perfiles as $perfil) {
            $permisos[(int) $perfil['sis_perfil_id']] = $this->perfilModelo->listarPermisosPerfil((int) $perfil['sis_perfil_id']);
        }

        return $permisos;
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
     * * VALIDA PERMISO PARA RUTA.
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
     * * REGISTRA ERRORES CONTROLADOS DEL CRUD PERFIL.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribir($accion . ': ' . $excepcion->getMessage());
    }
}
