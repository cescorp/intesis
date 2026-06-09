<?php

declare(strict_types=1);

namespace Intesis\Nucleo;

use Throwable;

/**
 * *****************************************************************************
 * * TRAIT CON METODOS AUXILIARES COMUNES A TODOS LOS CONTROLADORES.
 * * REQUIERE QUE LA CLASE POSEA LAS SIGUIENTES PROPIEDADES:
 * *   - Sesion         $sesion
 * *   - MenuModelo     $menuModelo
 * *   - Configuracion  $configuracion
 * *   - RegistroErrores $registroErrores  (solo para registrarErrorCrud)
 * *****************************************************************************
 *
 * @property Sesion           $sesion
 * @property \Intesis\Modelos\MenuModelo $menuModelo
 * @property Configuracion    $configuracion
 * @property RegistroErrores  $registroErrores
 */
trait ControladorComun
{
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
     * * EXIGE SESION ACTIVA PARA RESPUESTAS JSON. DEVUELVE 401 SI NO HAY SESION.
     * ***************************************************************************
     */
    private function exigirSesionJson(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            http_response_code(401);
            $this->responderJson(false, 'ERROR_SESION', 'Sesion no activa.');
        }

        return $usuario;
    }

    /**
     * ***************************************************************************
     * * VALIDA PERMISO BACKEND PARA UNA ACCION. REDIRIGE AL DASHBOARD SI FALTA.
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
     * * VALIDA PERMISO BACKEND PARA ACCIONES JSON. DEVUELVE 403 SI FALTA.
     * ***************************************************************************
     */
    private function exigirPermisoJson(string $url): void
    {
        $usuario = $this->exigirSesionJson();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            http_response_code(403);
            $this->responderJson(false, 'ERROR_SIN_PERMISO', 'Su perfil no tiene permiso para esta accion.');
        }
    }

    /**
     * ***************************************************************************
     * * IDENTIFICA SI EL PERFIL ACTIVO ES SUPERUSUARIO.
     * ***************************************************************************
     */
    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
    }

    /**
     * ***************************************************************************
     * * ENVIA RESPUESTA JSON ESTANDAR Y FINALIZA LA PETICION.
     * ***************************************************************************
     */
    private function responderJson(bool $ok, string $codigo, string $mensaje, array $data = []): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'codigo' => $codigo, 'mensaje' => $mensaje, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
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
     * * REGISTRA ERRORES CONTROLADOS EN EL LOG DEL SISTEMA.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribir($accion . ': ' . $excepcion->getMessage());
    }

    /**
     * ***************************************************************************
     * * GUARDA MENSAJE DE ERROR SIMPLE EN SESION PARA MOSTRARLO EN VISTA.
     * ***************************************************************************
     */
    private function guardarMensajeError(Throwable $excepcion): void
    {
        $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
    }
}
