<?php

declare(strict_types=1);

namespace Intesis\Nucleo;

use Throwable;

final class RegistroErrores
{
    public function __construct(private Configuracion $configuracion)
    {
    }

    /**
     * ***************************************************************************
     * * CONFIGURA EL MANEJO CENTRALIZADO DE ERRORES Y EXCEPCIONES DEL SISTEMA.
     * ***************************************************************************
     */
    public function registrarManejadores(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        set_error_handler(fn (int $nivel, string $mensaje, string $archivo, int $linea): bool => $this->registrarError($nivel, $mensaje, $archivo, $linea));
        set_exception_handler(fn (Throwable $excepcion): never => $this->registrarExcepcion($excepcion));
    }

    /**
     * ***************************************************************************
     * * GUARDA ERRORES PHP EN EL ARCHIVO DIARIO DEFINIDO PARA EL SISTEMA.
     * ***************************************************************************
     */
    private function registrarError(int $nivel, string $mensaje, string $archivo, int $linea): bool
    {
        $this->escribir("ERROR {$nivel}: {$mensaje} EN {$archivo}:{$linea}");
        return true;
    }

    /**
     * ***************************************************************************
     * * GUARDA EXCEPCIONES NO CONTROLADAS Y RESPONDE CON ERROR GENERAL.
     * ***************************************************************************
     */
    private function registrarExcepcion(Throwable $excepcion): never
    {
        $this->escribir($excepcion::class . ': ' . $excepcion->getMessage() . ' EN ' . $excepcion->getFile() . ':' . $excepcion->getLine());
        http_response_code(500);
        echo 'Error interno del sistema.';
        exit;
    }

    /**
     * ***************************************************************************
     * * ESCRIBE UNA LINEA EN EL LOG CENTRAL log_errores.log DE LA RAIZ.
     * ***************************************************************************
     */
    public function escribir(string $mensaje): void
    {
        $archivo = $this->configuracion->ruta('RUTA_LOG_ERRORES', 'log_errores.log');
        $directorio = dirname($archivo);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        file_put_contents($archivo, '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL, FILE_APPEND);
    }
}
