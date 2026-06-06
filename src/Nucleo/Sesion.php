<?php

declare(strict_types=1);

namespace Intesis\Nucleo;

final class Sesion
{
    public function __construct(private Configuracion $configuracion)
    {
    }

    /**
     * ***************************************************************************
     * * INICIA LA SESION PHP USANDO EL DIRECTORIO LOCAL CONFIGURADO.
     * ***************************************************************************
     */
    public function iniciar(): void
    {
        $rutaSesiones = $this->configuracion->ruta('RUTA_SESIONES', 'almacenamiento/sesiones');
        if (!is_dir($rutaSesiones)) {
            mkdir($rutaSesiones, 0775, true);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_save_path($rutaSesiones);
            session_name('INTESIS_SESION');
            session_start();
        }
    }

    /**
     * ***************************************************************************
     * * GUARDA LOS DATOS MINIMOS DEL USUARIO AUTENTICADO.
     * ***************************************************************************
     */
    public function guardarUsuario(array $usuario): void
    {
        $_SESSION['usuario'] = $usuario;
    }

    /**
     * ***************************************************************************
     * * OBTIENE EL USUARIO AUTENTICADO ACTUAL.
     * ***************************************************************************
     */
    public function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    /**
     * ***************************************************************************
     * * INDICA SI EXISTE UN USUARIO AUTENTICADO.
     * ***************************************************************************
     */
    public function autenticado(): bool
    {
        return isset($_SESSION['usuario']);
    }

    /**
     * ***************************************************************************
     * * CIERRA LA SESION ACTUAL Y ELIMINA LOS DATOS DEL USUARIO.
     * ***************************************************************************
     */
    public function cerrar(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * ***************************************************************************
     * * GUARDA UN MENSAJE TEMPORAL PARA MOSTRARLO EN LA SIGUIENTE RESPUESTA.
     * ***************************************************************************
     */
    public function guardarMensaje(string $tipo, string $titulo, string $texto, int $tiempo = 0, int $posicion = 0): void
    {
        if ($posicion === 0) {
            $posicion = strtolower($tipo) === 'success' ? 2 : 4;
        }

        $_SESSION['mensaje_sistema'] = [
            'tipo' => $tipo,
            'titulo' => $titulo,
            'texto' => $texto,
            'tiempo' => $tiempo,
            'posicion' => $posicion,
        ];
    }

    /**
     * ***************************************************************************
     * * OBTIENE Y ELIMINA EL MENSAJE TEMPORAL DE LA SESION.
     * ***************************************************************************
     */
    public function consumirMensaje(): ?array
    {
        $mensaje = $_SESSION['mensaje_sistema'] ?? null;
        unset($_SESSION['mensaje_sistema']);

        return $mensaje;
    }
}
