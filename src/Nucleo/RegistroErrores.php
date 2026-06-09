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
     * * REGISTRA ERRORES PHP (set_error_handler) CON FORMATO COMPLETO.
     * ***************************************************************************
     */
    private function registrarError(int $nivel, string $mensaje, string $archivo, int $linea): bool
    {
        $this->guardar(
            detalle: 'ERROR PHP NIVEL ' . $nivel,
            error: $mensaje,
            archivo: $archivo,
            linea: $linea
        );

        return true;
    }

    /**
     * ***************************************************************************
     * * REGISTRA EXCEPCIONES NO CONTROLADAS Y RESPONDE AL NAVEGADOR.
     * ***************************************************************************
     */
    private function registrarExcepcion(Throwable $excepcion): never
    {
        $this->guardar(
            detalle: $excepcion::class,
            error: $excepcion->getMessage(),
            archivo: $excepcion->getFile(),
            linea: $excepcion->getLine()
        );

        http_response_code(500);

        if ($this->configuracion->obtener('APP_DEBUG') === 'true') {
            $archivo = htmlspecialchars($excepcion->getFile());
            $linea   = $excepcion->getLine();
            $error   = htmlspecialchars($excepcion->getMessage());
            $tipo    = htmlspecialchars($excepcion::class);
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>Error del sistema</title>
                <style>
                    body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; margin: 0; padding: 2rem; }
                    .caja { background: #252526; border-left: 4px solid #f44747; padding: 1.5rem 2rem; border-radius: 4px; max-width: 900px; }
                    h1 { color: #f44747; font-size: 1.1rem; margin: 0 0 1rem; text-transform: uppercase; letter-spacing: 1px; }
                    table { border-collapse: collapse; width: 100%; }
                    td { padding: .4rem .6rem; vertical-align: top; }
                    td:first-child { color: #9cdcfe; white-space: nowrap; width: 80px; }
                    td:last-child  { color: #ce9178; word-break: break-all; }
                    .nota { margin-top: 1rem; font-size: .8rem; color: #6a9955; }
                </style>
            </head>
            <body>
                <div class="caja">
                    <h1>Error interno del sistema</h1>
                    <table>
                        <tr><td>Tipo</td>   <td>{$tipo}</td></tr>
                        <tr><td>Error</td>  <td>{$error}</td></tr>
                        <tr><td>Archivo</td><td>{$archivo}</td></tr>
                        <tr><td>Linea</td>  <td>{$linea}</td></tr>
                    </table>
                    <p class="nota">Este detalle es visible solo con APP_DEBUG=true</p>
                </div>
            </body>
            </html>
            HTML;
        } else {
            echo 'Error interno del sistema.';
        }

        exit;
    }

    /**
     * ***************************************************************************
     * * REGISTRA EXCEPCION CONTROLADA DESDE CONTROLADORES (CRUD, SERVICIOS).
     * * Recibe el Throwable completo para capturar archivo y linea reales.
     * ***************************************************************************
     */
    public function escribirExcepcion(string $detalle, Throwable $excepcion): void
    {
        $this->guardar(
            detalle: $detalle,
            error: $excepcion->getMessage(),
            archivo: $excepcion->getFile(),
            linea: $excepcion->getLine()
        );
    }

    /**
     * ***************************************************************************
     * * REGISTRA MENSAJES SIMPLES SIN THROWABLE (AVISOS, ALERTAS DEL SISTEMA).
     * ***************************************************************************
     */
    public function escribir(string $mensaje): void
    {
        $this->guardar(
            detalle: 'AVISO',
            error: $mensaje,
            archivo: '',
            linea: null
        );
    }

    /**
     * ***************************************************************************
     * * ESCRIBE EL BLOQUE DE LOG CON FORMATO ESTANDAR EN EL ARCHIVO DIARIO.
     * ***************************************************************************
     */
    private function guardar(string $detalle, string $error, string $archivo, ?int $linea): void
    {
        $directorio = $this->configuracion->ruta('RUTA_LOG_ERRORES', 'log_errores');
        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        $archivoLog = $directorio . '/log_error_' . date('Y-m-d') . '.log';

        $bloque  = '################################################' . PHP_EOL;
        $bloque .= 'Fecha:   ' . date('Y-m-d H:i:s')                  . PHP_EOL;
        $bloque .= 'Archivo: ' . ($archivo !== '' ? $archivo : 'N/A')  . PHP_EOL;
        $bloque .= 'Detalle: ' . $detalle                              . PHP_EOL;
        $bloque .= 'Error:   ' . $error                                . PHP_EOL;
        $bloque .= 'Linea:   ' . ($linea !== null ? $linea : 'N/A')    . PHP_EOL;
        $bloque .= '################################################' . PHP_EOL . PHP_EOL;

        file_put_contents($archivoLog, $bloque, FILE_APPEND);
    }
}
