<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\EstadoModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\SecuenciaModelo;
use Intesis\Modelos\TipoDocumentoModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class ConfiguracionControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private EstadoModelo $estadoModelo,
        private MensajeSistemaModelo $mensajeModelo,
        private TipoDocumentoModelo $tipoDocumentoModelo,
        private SecuenciaModelo $secuenciaModelo,
        private MenuModelo $menuModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA CONFIGURACION CON PESTANAS DE ESTADOS Y MENSAJES.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/configuracion/ver');
        $esSuperusuario = $this->esSuperusuario($usuario);
        $empresaFiltro = $esSuperusuario ? null : (int) $usuario['empresa_id'];
        $this->vista->renderizar('sistema/configuracion', [
            'titulo' => 'Configuración',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'estados' => $this->estadoModelo->listar(),
            'mensajesError' => $this->mensajeModelo->listarTodos(),
            'tiposDocumento' => $this->tipoDocumentoModelo->listar(),
            'secuencias' => $this->secuenciaModelo->listar($empresaFiltro),
            'empresasSecuencia' => $this->secuenciaModelo->listarEmpresasActivas($empresaFiltro),
            'esSuperusuario' => $esSuperusuario,
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UN ESTADO GLOBAL.
     * ***************************************************************************
     */
    public function crearEstado(): void
    {
        $this->guardarEstado(false);
    }

    /**
     * ***************************************************************************
     * * ENVIA CONFIGURACION AL PRIMER MENU HIJO OPERATIVO.
     * ***************************************************************************
     */
    public function redirigirConfiguracion(): void
    {
        $this->exigirSesion();
        $this->redirigir('/sistema/configuracion/estados');
    }

    /**
     * ***************************************************************************
     * * MUESTRA ADMINISTRACION DE ESTADOS DEL SISTEMA SIN PESTANAS.
     * ***************************************************************************
     */
    public function listarEstados(): void
    {
        $this->renderizarPanel('estados', '/sistema/configuracion/estados', 'Estados', [
            'estados' => $this->estadoModelo->listar(),
        ]);
    }

    /**
     * ***************************************************************************
     * * MUESTRA ADMINISTRACION DE MENSAJES DE ERROR SIN PESTANAS.
     * ***************************************************************************
     */
    public function listarMensajes(): void
    {
        $this->renderizarPanel('mensajes', '/sistema/configuracion/mensajes-error', 'Mensajes Error', [
            'mensajesError' => $this->mensajeModelo->listarTodos(),
        ]);
    }

    /**
     * ***************************************************************************
     * * MUESTRA ADMINISTRACION DE TIPOS DE DOCUMENTO Y SECUENCIAS.
     * ***************************************************************************
     */
    public function listarTiposDocumento(): void
    {
        $usuario = $this->exigirSesion();
        $esSuperusuario = $this->esSuperusuario($usuario);
        $empresaFiltro = $esSuperusuario ? null : (int) $usuario['empresa_id'];
        $this->renderizarPanel('tipos', '/sistema/configuracion/tipos-documento', 'Tipos documento', [
            'tiposDocumento' => $this->tipoDocumentoModelo->listar(),
            'secuencias' => $this->secuenciaModelo->listar($empresaFiltro),
            'empresasSecuencia' => $this->secuenciaModelo->listarEmpresasActivas($empresaFiltro),
            'esSuperusuario' => $esSuperusuario,
        ]);
    }

    /**
     * ***************************************************************************
     * * RENDERIZA UNA SECCION DE CONFIGURACION SIN PESTANAS.
     * ***************************************************************************
     */
    private function renderizarPanel(string $panel, string $permiso, string $tituloSeccion, array $datos): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        $datosBase = [
            'titulo' => $tituloSeccion,
            'tituloSeccion' => $tituloSeccion,
            'panelConfiguracion' => $panel,
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'estados' => [],
            'mensajesError' => [],
            'tiposDocumento' => [],
            'secuencias' => [],
            'empresasSecuencia' => [],
            'esSuperusuario' => $this->esSuperusuario($usuario),
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
        ];
        $this->vista->renderizar('sistema/configuracion', array_merge($datosBase, $datos));
    }

    /**
     * ***************************************************************************
     * * EDITA UN ESTADO GLOBAL.
     * ***************************************************************************
     */
    public function editarEstado(): void
    {
        $this->guardarEstado(true);
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE ESTADO.
     * ***************************************************************************
     */
    private function guardarEstado(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/sistema/configuracion/estados/editar' : '/sistema/configuracion/estados/crear');
        try {
            $estadoId = (int) ($_POST['estado_id'] ?? 0);
            if ($editar) {
                $this->obtenerEstadoValido($estadoId);
            }
            $datos = $this->normalizarEstado();
            $permitirClave = !$editar || !$this->estadoModelo->estaUsado($estadoId);
            $this->validarEstado($datos, $editar ? $estadoId : null, $permitirClave);
            $editar
                ? $this->estadoModelo->actualizar($estadoId, $datos, $permitirClave, (int) $usuario['id'])
                : $this->estadoModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Estado actualizado' : 'Estado creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR ESTADO' : 'CREAR ESTADO', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/estados');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN ESTADO.
     * ***************************************************************************
     */
    public function activarEstado(): void
    {
        $this->cambiarActivoEstado('/sistema/configuracion/estados/activar', true);
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN ESTADO.
     * ***************************************************************************
     */
    public function inactivarEstado(): void
    {
        $this->cambiarActivoEstado('/sistema/configuracion/estados/inactivar', false);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO DE CATALOGO DE ESTADOS.
     * ***************************************************************************
     */
    private function cambiarActivoEstado(string $permiso, bool $activo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $estado = $this->obtenerEstadoValido((int) ($_POST['estado_id'] ?? 0));
            $this->estadoModelo->cambiarActivo((int) $estado['sis_estado_id'], $activo, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $activo ? 'Estado activado' : 'Estado inactivado', 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO CONFIGURACION', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo cambiar estado', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/estados');
    }

    /**
     * ***************************************************************************
     * * CREA UN MENSAJE DE ERROR CONFIGURABLE.
     * ***************************************************************************
     */
    public function crearMensaje(): void
    {
        $this->guardarMensaje(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UN MENSAJE DE ERROR CONFIGURABLE.
     * ***************************************************************************
     */
    public function editarMensaje(): void
    {
        $this->guardarMensaje(true);
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE MENSAJE.
     * ***************************************************************************
     */
    private function guardarMensaje(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/sistema/configuracion/mensajes-error/editar' : '/sistema/configuracion/mensajes-error/crear');
        try {
            $mensajeId = (int) ($_POST['mensaje_id'] ?? 0);
            if ($editar && !$this->mensajeModelo->buscarPorId($mensajeId)) {
                throw new \InvalidArgumentException('Mensaje no valido.');
            }
            $datos = $this->normalizarMensaje();
            $this->validarMensaje($datos, $editar ? $mensajeId : null);
            $editar
                ? $this->mensajeModelo->actualizar($mensajeId, $datos, (int) $usuario['id'])
                : $this->mensajeModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Mensaje actualizado' : 'Mensaje creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR MENSAJE' : 'CREAR MENSAJE', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/mensajes-error');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN MENSAJE.
     * ***************************************************************************
     */
    public function activarMensaje(): void
    {
        $this->cambiarActivoMensaje('/sistema/configuracion/mensajes-error/activar', true);
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN MENSAJE.
     * ***************************************************************************
     */
    public function inactivarMensaje(): void
    {
        $this->cambiarActivoMensaje('/sistema/configuracion/mensajes-error/inactivar', false);
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO DE MENSAJE.
     * ***************************************************************************
     */
    private function cambiarActivoMensaje(string $permiso, bool $activo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $mensajeId = (int) ($_POST['mensaje_id'] ?? 0);
            if (!$this->mensajeModelo->buscarPorId($mensajeId)) {
                throw new \InvalidArgumentException('Mensaje no valido.');
            }
            $this->mensajeModelo->cambiarActivo($mensajeId, $activo, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $activo ? 'Mensaje activado' : 'Mensaje inactivado', 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR MENSAJE', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo cambiar estado', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/mensajes-error');
    }

    /**
     * ***************************************************************************
     * * CREA UN TIPO DE DOCUMENTO GLOBAL.
     * ***************************************************************************
     */
    public function crearTipoDocumento(): void
    {
        $this->guardarTipoDocumento(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UN TIPO DE DOCUMENTO GLOBAL.
     * ***************************************************************************
     */
    public function editarTipoDocumento(): void
    {
        $this->guardarTipoDocumento(true);
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    private function guardarTipoDocumento(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/sistema/configuracion/tipos-documento/editar' : '/sistema/configuracion/tipos-documento/crear');
        try {
            $tipoDocumentoId = (int) ($_POST['tipo_documento_id'] ?? 0);
            if ($editar && !$this->tipoDocumentoModelo->buscar($tipoDocumentoId)) {
                throw new \InvalidArgumentException('Tipo de documento no valido.');
            }
            $datos = $this->normalizarTipoDocumento();
            $this->validarTipoDocumento($datos, $editar ? $tipoDocumentoId : null);
            $editar
                ? $this->tipoDocumentoModelo->actualizar($tipoDocumentoId, $datos, (int) $usuario['id'])
                : $this->tipoDocumentoModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Tipo actualizado' : 'Tipo creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR TIPO DOCUMENTO' : 'CREAR TIPO DOCUMENTO', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/tipos-documento');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    public function activarTipoDocumento(): void
    {
        $this->cambiarEstadoTipoDocumento('/sistema/configuracion/tipos-documento/activar', 'ACTIVO');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    public function inactivarTipoDocumento(): void
    {
        $this->cambiarEstadoTipoDocumento('/sistema/configuracion/tipos-documento/inactivar', 'INACTIVO');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DEL TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    private function cambiarEstadoTipoDocumento(string $permiso, string $estado): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $tipoDocumentoId = (int) ($_POST['tipo_documento_id'] ?? 0);
            if (!$this->tipoDocumentoModelo->buscar($tipoDocumentoId)) {
                throw new \InvalidArgumentException('Tipo de documento no valido.');
            }
            $this->tipoDocumentoModelo->cambiarEstado($tipoDocumentoId, $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $estado === 'ACTIVO' ? 'Tipo activado' : 'Tipo inactivado', 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR TIPO DOCUMENTO', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo cambiar estado', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/tipos-documento');
    }

    /**
     * ***************************************************************************
     * * CREA UNA SECUENCIA POR EMPRESA.
     * ***************************************************************************
     */
    public function crearSecuencia(): void
    {
        $this->guardarSecuencia(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UNA SECUENCIA POR EMPRESA.
     * ***************************************************************************
     */
    public function editarSecuencia(): void
    {
        $this->guardarSecuencia(true);
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE SECUENCIA.
     * ***************************************************************************
     */
    private function guardarSecuencia(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/sistema/configuracion/secuencias/editar' : '/sistema/configuracion/secuencias/crear');
        try {
            $secuenciaId = (int) ($_POST['secuencia_id'] ?? 0);
            $secuencia = $editar ? $this->obtenerSecuenciaPermitida($secuenciaId, $usuario) : null;
            $datos = $this->normalizarSecuencia($usuario);
            $this->validarSecuencia($datos, $editar ? $secuenciaId : null);
            $editar
                ? $this->secuenciaModelo->actualizar((int) $secuencia['sis_secuencias_id'], $datos, (int) $usuario['id'])
                : $this->secuenciaModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Secuencia actualizada' : 'Secuencia creada', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR SECUENCIA' : 'CREAR SECUENCIA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/tipos-documento');
    }

    /**
     * ***************************************************************************
     * * ACTIVA UNA SECUENCIA.
     * ***************************************************************************
     */
    public function activarSecuencia(): void
    {
        $this->cambiarEstadoSecuencia('/sistema/configuracion/secuencias/activar', 'ACTIVO');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UNA SECUENCIA.
     * ***************************************************************************
     */
    public function inactivarSecuencia(): void
    {
        $this->cambiarEstadoSecuencia('/sistema/configuracion/secuencias/inactivar', 'INACTIVO');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO LOGICO DE SECUENCIA VALIDANDO EMPRESA ACTIVA.
     * ***************************************************************************
     */
    private function cambiarEstadoSecuencia(string $permiso, string $estado): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $secuencia = $this->obtenerSecuenciaPermitida((int) ($_POST['secuencia_id'] ?? 0), $usuario);
            $this->secuenciaModelo->cambiarEstado((int) $secuencia['sis_secuencias_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $estado === 'ACTIVO' ? 'Secuencia activada' : 'Secuencia inactivada', 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR SECUENCIA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo cambiar estado', $excepcion->getMessage());
        }
        $this->redirigir('/sistema/configuracion/tipos-documento');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE ESTADO.
     * ***************************************************************************
     */
    private function normalizarEstado(): array
    {
        return [
            'modulo' => strtoupper(trim((string) ($_POST['modulo'] ?? ''))),
            'entidad' => strtoupper(trim((string) ($_POST['entidad'] ?? ''))),
            'codigo' => strtoupper(trim((string) ($_POST['codigo'] ?? ''))),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'orden' => max(1, (int) ($_POST['orden'] ?? 1)),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA DATOS DE ESTADO.
     * ***************************************************************************
     */
    private function validarEstado(array $datos, ?int $estadoId, bool $permitirClave): void
    {
        if ($datos['modulo'] === '' || $datos['entidad'] === '' || $datos['codigo'] === '' || $datos['nombre'] === '') {
            throw new \InvalidArgumentException('Modulo, entidad, codigo y nombre son obligatorios.');
        }
        if ($permitirClave && $this->estadoModelo->existeCodigo($datos['modulo'], $datos['entidad'], $datos['codigo'], $estadoId)) {
            throw new \InvalidArgumentException('Ya existe ese codigo para la entidad indicada.');
        }
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE MENSAJE.
     * ***************************************************************************
     */
    private function normalizarMensaje(): array
    {
        return [
            'codigo' => strtoupper(trim((string) ($_POST['codigo'] ?? ''))),
            'tipo' => strtoupper(trim((string) ($_POST['tipo'] ?? 'ERROR'))),
            'titulo' => trim((string) ($_POST['titulo'] ?? '')),
            'mensaje' => trim((string) ($_POST['mensaje'] ?? '')),
            'icono' => strtolower(trim((string) ($_POST['icono'] ?? 'error'))),
            'modulo' => strtoupper(trim((string) ($_POST['modulo'] ?? 'SISTEMA'))),
            'entidad' => strtoupper(trim((string) ($_POST['entidad'] ?? 'GENERAL'))),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA DATOS DE MENSAJE.
     * ***************************************************************************
     */
    private function validarMensaje(array $datos, ?int $mensajeId): void
    {
        foreach (['codigo', 'tipo', 'titulo', 'mensaje', 'icono', 'modulo', 'entidad'] as $campo) {
            if ($datos[$campo] === '') {
                throw new \InvalidArgumentException('Todos los campos del mensaje son obligatorios.');
            }
        }
        if ($this->mensajeModelo->existeCodigo($datos['codigo'], $mensajeId)) {
            throw new \InvalidArgumentException('Ya existe un mensaje con ese codigo.');
        }
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    private function normalizarTipoDocumento(): array
    {
        return [
            'codigo' => strtoupper(trim((string) ($_POST['codigo'] ?? ''))),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'modulo' => strtoupper(trim((string) ($_POST['modulo'] ?? ''))),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'afecta_contabilidad' => !empty($_POST['afecta_contabilidad']),
            'afecta_inventario' => !empty($_POST['afecta_inventario']),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA DATOS DE TIPO DE DOCUMENTO.
     * ***************************************************************************
     */
    private function validarTipoDocumento(array $datos, ?int $tipoDocumentoId): void
    {
        if ($datos['codigo'] === '' || $datos['nombre'] === '' || $datos['modulo'] === '') {
            throw new \InvalidArgumentException('Modulo, codigo y nombre son obligatorios.');
        }
        if (!preg_match('/^[A-Z0-9_]{2,30}$/', $datos['codigo'])) {
            throw new \InvalidArgumentException('El codigo debe usar letras, numeros o guion bajo.');
        }
        if ($this->tipoDocumentoModelo->existeCodigo($datos['modulo'], $datos['codigo'], $tipoDocumentoId)) {
            throw new \InvalidArgumentException('Ya existe ese tipo de documento en el modulo indicado.');
        }
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE SECUENCIA RESPETANDO EMPRESA ACTIVA.
     * ***************************************************************************
     */
    private function normalizarSecuencia(array $usuario): array
    {
        $empresaId = $this->esSuperusuario($usuario)
            ? (int) ($_POST['empresa_id'] ?? 0)
            : (int) $usuario['empresa_id'];

        return [
            'empresa_id' => $empresaId,
            'tipo_documento_id' => (int) ($_POST['tipo_documento_id'] ?? 0),
            'establecimiento' => str_pad(preg_replace('/\D+/', '', (string) ($_POST['establecimiento'] ?? '')), 3, '0', STR_PAD_LEFT),
            'punto_emision' => str_pad(preg_replace('/\D+/', '', (string) ($_POST['punto_emision'] ?? '')), 3, '0', STR_PAD_LEFT),
            'desde' => (int) ($_POST['desde'] ?? 1),
            'actual' => (int) ($_POST['actual'] ?? 1),
            'hasta' => (int) ($_POST['hasta'] ?? 999999999),
            'observacion' => trim((string) ($_POST['observacion'] ?? '')),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA DATOS DE SECUENCIA PARA FACTURACION ELECTRONICA.
     * ***************************************************************************
     */
    private function validarSecuencia(array $datos, ?int $secuenciaId): void
    {
        if ($datos['empresa_id'] <= 0 || $datos['tipo_documento_id'] <= 0) {
            throw new \InvalidArgumentException('Empresa y tipo de documento son obligatorios.');
        }
        if (!$this->tipoDocumentoModelo->buscar((int) $datos['tipo_documento_id'])) {
            throw new \InvalidArgumentException('Tipo de documento no valido.');
        }
        if (!preg_match('/^[0-9]{3}$/', $datos['establecimiento']) || !preg_match('/^[0-9]{3}$/', $datos['punto_emision'])) {
            throw new \InvalidArgumentException('Establecimiento y punto de emision deben tener tres digitos.');
        }
        if ($datos['desde'] < 1 || $datos['hasta'] > 999999999 || $datos['actual'] < $datos['desde'] || $datos['actual'] > $datos['hasta']) {
            throw new \InvalidArgumentException('La secuencia debe estar entre 1 y 999999999 y respetar el rango.');
        }
        if ($this->secuenciaModelo->existeClave($datos, $secuenciaId)) {
            throw new \InvalidArgumentException('Ya existe una secuencia para esa empresa, tipo, establecimiento y punto de emision.');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE ESTADO VALIDO.
     * ***************************************************************************
     */
    private function obtenerEstadoValido(int $estadoId): array
    {
        $estado = $estadoId > 0 ? $this->estadoModelo->buscar($estadoId) : null;
        if (!$estado) {
            throw new \InvalidArgumentException('Estado no valido.');
        }

        return $estado;
    }

    /**
     * ***************************************************************************
     * * OBTIENE SECUENCIA Y VALIDA EMPRESA ACTIVA DEL USUARIO.
     * ***************************************************************************
     */
    private function obtenerSecuenciaPermitida(int $secuenciaId, array $usuario): array
    {
        $secuencia = $secuenciaId > 0 ? $this->secuenciaModelo->buscar($secuenciaId) : null;
        if (!$secuencia) {
            throw new \InvalidArgumentException('Secuencia no valida.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $secuencia['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede modificar secuencias de otra empresa.');
        }

        return $secuencia;
    }

    /**
     * ***************************************************************************
     * * IDENTIFICA PERFIL SUPERUSUARIO GLOBAL.
     * ***************************************************************************
     */
    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
    }

    /**
     * ***************************************************************************
     * * OBTIENE PERMISOS DE ACCIONES DE CONFIGURACION.
     * ***************************************************************************
     */
    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];
        return [
            'estado_crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/estados/crear'),
            'estado_editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/estados/editar'),
            'estado_activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/estados/activar'),
            'estado_inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/estados/inactivar'),
            'mensaje_crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/mensajes-error/crear'),
            'mensaje_editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/mensajes-error/editar'),
            'mensaje_activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/mensajes-error/activar'),
            'mensaje_inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/mensajes-error/inactivar'),
            'tipo_crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/tipos-documento/crear'),
            'tipo_editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/tipos-documento/editar'),
            'tipo_activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/tipos-documento/activar'),
            'tipo_inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/tipos-documento/inactivar'),
            'secuencia_crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/secuencias/crear'),
            'secuencia_editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/secuencias/editar'),
            'secuencia_activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/secuencias/activar'),
            'secuencia_inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/secuencias/inactivar'),
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
     * * VALIDA PERMISO DE BACKEND.
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
     * * REGISTRA ERRORES CONTROLADOS DE CONFIGURACION.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribir($accion . ': ' . $excepcion->getMessage());
    }
}
