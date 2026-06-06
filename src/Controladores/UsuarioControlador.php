<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\UsuarioEmpresaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class UsuarioControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private UsuarioEmpresaModelo $usuarioEmpresaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD COMPACTO DE USUARIOS POR EMPRESA.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/usuarios/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $this->vista->renderizar('sistema/usuarios', [
            'titulo' => 'Usuarios',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'usuarios' => $this->usuarioEmpresaModelo->listar((int) $usuario['empresa_id'], $verTodas),
            'asignacionesUsuarios' => $this->obtenerAsignacionesUsuarios((int) $usuario['empresa_id'], $verTodas),
            'empresas' => $this->usuarioEmpresaModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'perfiles' => $this->usuarioEmpresaModelo->listarPerfilesActivos($verTodas, (int) $usuario['empresa_id']),
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'USUARIO_DATOS_OBLIGATORIOS',
                'USUARIO_CLAVE_INVALIDA',
                'CONFIRMAR_INACTIVAR_USUARIO',
                'CONFIRMAR_BLOQUEAR_USUARIO',
                'CONFIRMAR_ELIMINAR_USUARIO',
            ]),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UN USUARIO GLOBAL Y LO ASIGNA A UNA EMPRESA.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/usuarios/crear');

        try {
            $datos = $this->normalizarDatosFormulario(true);
            $this->validarDatos($datos, true);
            foreach ($datos['asignaciones'] as $asignacion) {
                if ($this->usuarioEmpresaModelo->existeCorreoEnEmpresa($datos['correo'], (int) $asignacion['empresa_id'])) {
                    throw new \InvalidArgumentException('USUARIO_CORREO_ASIGNADO');
                }
                $this->validarAlcanceEmpresa((int) $asignacion['empresa_id'], $usuario);
            }
            $this->usuarioEmpresaModelo->crear($datos, (int) $usuario['id']);
            $this->guardarMensajeCodigo('USUARIO_CREADO');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CREAR USUARIO', $excepcion);
            $this->guardarMensajeError($excepcion, 'ERROR_GENERICO_GUARDAR');
        }

        $this->redirigir('/sistema/usuarios');
    }

    /**
     * ***************************************************************************
     * * EDITA DATOS BASICOS DEL USUARIO Y SU ASIGNACION EMPRESA PERFIL.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/usuarios/editar');

        try {
            $asignacionId = (int) ($_POST['asignacion_id'] ?? 0);
            $asignacion = $this->obtenerAsignacionValida($asignacionId);
            $datos = $this->normalizarDatosFormulario(false);
            $this->validarDatos($datos, false, (int) $asignacion['sis_usuarios_id']);
            foreach ($datos['asignaciones'] as $asignacionFormulario) {
                $this->validarAlcanceEmpresa((int) $asignacionFormulario['empresa_id'], $usuario);
            }
            $this->usuarioEmpresaModelo->actualizarConAsignaciones($asignacionId, $datos, (int) $usuario['id']);
            $this->guardarMensajeCodigo('USUARIO_ACTUALIZADO');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('EDITAR USUARIO', $excepcion);
            $this->guardarMensajeError($excepcion, 'ERROR_GENERICO_GUARDAR');
        }

        $this->redirigir('/sistema/usuarios');
    }

    /**
     * ***************************************************************************
     * * ACTIVA LA ASIGNACION DE USUARIO A EMPRESA.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/sistema/usuarios/activar', 'ACTIVO', 'USUARIO_ACTIVADO');
    }

    /**
     * ***************************************************************************
     * * INACTIVA LA ASIGNACION DE USUARIO A EMPRESA.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/sistema/usuarios/inactivar', 'INACTIVO', 'USUARIO_INACTIVADO');
    }

    /**
     * ***************************************************************************
     * * BLOQUEA LA ASIGNACION DE USUARIO POR SEGURIDAD.
     * ***************************************************************************
     */
    public function bloquear(): void
    {
        $this->cambiarEstado('/sistema/usuarios/bloquear', 'BLOQUEADO', 'USUARIO_BLOQUEADO');
    }

    /**
     * ***************************************************************************
     * * ELIMINA LOGICAMENTE LA ASIGNACION DEL USUARIO.
     * ***************************************************************************
     */
    public function eliminar(): void
    {
        $this->cambiarEstado('/sistema/usuarios/eliminar', 'ELIMINADO', 'USUARIO_ELIMINADO');
    }

    /**
     * ***************************************************************************
     * * RESTABLECE LA CLAVE DEL USUARIO GLOBAL CON HASH SEGURO.
     * ***************************************************************************
     */
    public function restablecerClave(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/usuarios/restablecer-clave');

        try {
            $asignacionId = (int) ($_POST['asignacion_id'] ?? 0);
            $this->obtenerAsignacionValida($asignacionId);
            $clave = (string) ($_POST['clave'] ?? '');
            $confirmar = (string) ($_POST['confirmar_clave'] ?? '');
            if (strlen($clave) < 8 || $clave !== $confirmar) {
                throw new \InvalidArgumentException('USUARIO_CLAVE_INVALIDA');
            }

            $this->usuarioEmpresaModelo->restablecerClave($asignacionId, $clave, (int) $usuario['id']);
            $this->guardarMensajeCodigo('USUARIO_CLAVE_RESTABLECIDA');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('RESTABLECER CLAVE USUARIO', $excepcion);
            $this->guardarMensajeError($excepcion, 'ERROR_GENERICO_GUARDAR');
        }

        $this->redirigir('/sistema/usuarios');
    }

    /**
     * ***************************************************************************
     * * CAMBIA EL ESTADO DEL USUARIO VALIDANDO REGLAS DE SEGURIDAD.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $codigoExito): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);

        try {
            $asignacionId = (int) ($_POST['asignacion_id'] ?? 0);
            $asignacion = $this->obtenerAsignacionValida($asignacionId);
            $this->validarProteccionEstado($asignacion, $usuario, $estado);
            $this->usuarioEmpresaModelo->cambiarEstado($asignacionId, $estado, (int) $usuario['id']);
            $this->guardarMensajeCodigo($codigoExito);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO USUARIO', $excepcion);
            $this->guardarMensajeError($excepcion, 'ERROR_GENERICO_GUARDAR');
        }

        $this->redirigir('/sistema/usuarios');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA LOS DATOS DEL FORMULARIO DE USUARIO.
     * ***************************************************************************
     */
    private function normalizarDatosFormulario(bool $incluyeClave): array
    {
        $datos = [
            'empresa_id' => (int) ($_POST['empresa_id'] ?? 0),
            'perfil_id' => (int) ($_POST['perfil_id'] ?? 0),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'correo' => strtolower(trim((string) ($_POST['correo'] ?? ''))),
        ];

        if ($incluyeClave) {
            $datos['clave'] = (string) ($_POST['clave'] ?? '');
            $datos['confirmar_clave'] = (string) ($_POST['confirmar_clave'] ?? '');
            $datos['asignaciones'] = $this->normalizarAsignacionesFormulario($datos);
        } else {
            $datos['asignaciones'] = $this->normalizarAsignacionesFormulario($datos);
        }

        return $datos;
    }

    /**
     * ***************************************************************************
     * * NORMALIZA UNA O VARIAS ASIGNACIONES EMPRESA PERFIL EN CREACION.
     * ***************************************************************************
     */
    private function normalizarAsignacionesFormulario(array $datos): array
    {
        $asignacionesFormulario = $_POST['asignaciones'] ?? [];
        if (!is_array($asignacionesFormulario) || !$asignacionesFormulario) {
            return [[
                'asignacion_id' => (int) ($_POST['asignacion_id'] ?? 0),
                'empresa_id' => $datos['empresa_id'],
                'perfil_id' => $datos['perfil_id'],
                'inactivar' => false,
                'predeterminada' => true,
            ]];
        }

        $asignaciones = [];
        foreach ($asignacionesFormulario as $asignacion) {
            if (!is_array($asignacion)) {
                continue;
            }
            $asignaciones[] = [
                'asignacion_id' => (int) ($asignacion['asignacion_id'] ?? 0),
                'empresa_id' => (int) ($asignacion['empresa_id'] ?? 0),
                'perfil_id' => (int) ($asignacion['perfil_id'] ?? 0),
                'inactivar' => !empty($asignacion['inactivar']),
                'predeterminada' => !empty($asignacion['predeterminada']),
            ];
        }

        return $asignaciones;
    }

    /**
     * ***************************************************************************
     * * VALIDA CAMPOS OBLIGATORIOS, CORREO GLOBAL Y CLAVE.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, bool $incluyeClave, ?int $usuarioId = null): void
    {
        if ($incluyeClave) {
            $this->validarAsignaciones($datos['asignaciones'] ?? []);
        } else {
            $this->validarAsignaciones($datos['asignaciones'] ?? []);
        }

        if ($datos['nombre'] === '' || !filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('USUARIO_DATOS_OBLIGATORIOS');
        }

        if (!$incluyeClave && $this->usuarioEmpresaModelo->existeCorreo($datos['correo'], $usuarioId)) {
            throw new \InvalidArgumentException('USUARIO_CORREO_REGISTRADO');
        }

        if ($incluyeClave && (strlen($datos['clave']) < 8 || $datos['clave'] !== $datos['confirmar_clave'])) {
            throw new \InvalidArgumentException('USUARIO_CLAVE_INVALIDA');
        }
    }

    /**
     * ***************************************************************************
     * * VALIDA DUPLICADOS Y COHERENCIA EMPRESA PERFIL EN ASIGNACIONES.
     * ***************************************************************************
     */
    private function validarAsignaciones(array $asignaciones): void
    {
        if (!$asignaciones) {
            throw new \InvalidArgumentException('USUARIO_DATOS_OBLIGATORIOS');
        }

        $empresas = [];
        foreach ($asignaciones as $asignacion) {
            $empresaId = (int) ($asignacion['empresa_id'] ?? 0);
            $perfilId = (int) ($asignacion['perfil_id'] ?? 0);
            $inactiva = !empty($asignacion['inactivar']);
            if ($empresaId <= 0 || $perfilId <= 0 || (!$inactiva && isset($empresas[$empresaId]))) {
                throw new \InvalidArgumentException('USUARIO_DATOS_OBLIGATORIOS');
            }

            if (!$this->usuarioEmpresaModelo->perfilPerteneceEmpresa($perfilId, $empresaId)) {
                throw new \InvalidArgumentException('USUARIO_DATOS_OBLIGATORIOS');
            }

            if (!$inactiva) {
                $empresas[$empresaId] = true;
            }
        }
        if (!$empresas) {
            throw new \InvalidArgumentException('USUARIO_DATOS_OBLIGATORIOS');
        }
    }

    /**
     * ***************************************************************************
     * * AGRUPA ASIGNACIONES DE USUARIOS PARA EDICION MULTIEMPRESA.
     * ***************************************************************************
     */
    private function obtenerAsignacionesUsuarios(int $empresaId, bool $verTodas): array
    {
        $asignaciones = [];
        foreach ($this->usuarioEmpresaModelo->listar($empresaId, $verTodas) as $fila) {
            $usuarioId = (int) $fila['sis_usuarios_id'];
            if (isset($asignaciones[$usuarioId])) {
                continue;
            }
            $asignaciones[$usuarioId] = $this->usuarioEmpresaModelo->listarAsignacionesUsuario($usuarioId, $verTodas, $empresaId);
        }

        return $asignaciones;
    }

    /**
     * ***************************************************************************
     * * VALIDA QUE UN USUARIO NO SUPERUSUARIO SOLO TRABAJE CON SU EMPRESA.
     * ***************************************************************************
     */
    private function validarAlcanceEmpresa(int $empresaId, array $usuario): void
    {
        if (!$this->esSuperusuario($usuario) && $empresaId !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar usuarios de otra empresa.');
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
     * * IMPIDE BLOQUEAR EL USUARIO ACTUAL O EL ULTIMO SUPERUSUARIO ACTIVO.
     * ***************************************************************************
     */
    private function validarProteccionEstado(array $asignacion, array $usuario, string $estado): void
    {
        if ($estado === 'ACTIVO') {
            return;
        }

        if ((int) $asignacion['sis_usuarios_id'] === (int) $usuario['id']) {
            throw new \InvalidArgumentException('No puede cambiar el estado del usuario actualmente logueado.');
        }

        if ($asignacion['sis_perfil_codigo'] === 'SUPERUSUARIO' && $asignacion['sis_estado_codigo'] === 'ACTIVO') {
            $total = $this->usuarioEmpresaModelo->contarSuperusuariosActivos((int) $asignacion['sis_empresa_id']);
            if ($total <= 1) {
                throw new \InvalidArgumentException('No puede dejar la empresa sin superusuario activo.');
            }
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE UNA ASIGNACION EXISTENTE O LANZA ERROR DE VALIDACION.
     * ***************************************************************************
     */
    private function obtenerAsignacionValida(int $asignacionId): array
    {
        if ($asignacionId <= 0) {
            throw new \InvalidArgumentException('Asignacion de usuario no valida.');
        }

        $asignacion = $this->usuarioEmpresaModelo->buscarAsignacion($asignacionId);
        if (!$asignacion) {
            throw new \InvalidArgumentException('No se encontro la asignacion del usuario.');
        }

        return $asignacion;
    }

    /**
     * ***************************************************************************
     * * OBTIENE LOS PERMISOS DE ACCION DEL CRUD PARA MOSTRAR BOTONES.
     * ***************************************************************************
     */
    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];

        return [
            'crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/usuarios/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/usuarios/editar'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/usuarios/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/usuarios/inactivar'),
            'bloquear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/usuarios/bloquear'),
            'eliminar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/usuarios/eliminar'),
            'clave' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/usuarios/restablecer-clave'),
        ];
    }

    /**
     * ***************************************************************************
     * * EXIGE SESION ACTIVA PARA ACCEDER AL CRUD.
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
     * * VALIDA PERMISO DEL PERFIL ACTUAL PARA UNA RUTA DE ACCION.
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
     * * REDIRIGE A UNA RUTA LIMPIA DEL SISTEMA.
     * ***************************************************************************
     */
    private function redirigir(string $ruta): never
    {
        header('Location: ' . rtrim($this->configuracion->obtener('APP_URL', ''), '/') . $ruta);
        exit;
    }

    /**
     * ***************************************************************************
     * * REGISTRA ERRORES CONTROLADOS DEL CRUD USUARIO EN EL LOG DEL SISTEMA.
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
