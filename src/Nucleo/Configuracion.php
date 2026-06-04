<?php

declare(strict_types=1);

namespace Intesis\Nucleo;

final class Configuracion
{
    private array $valores = [];
    private string $raiz;

    /**
     * ***************************************************************************
     * * CARGA EL ARCHIVO .ENV Y NORMALIZA LAS RUTAS BASE DEL SISTEMA.
     * ***************************************************************************
     */
    public function __construct(string $raiz)
    {
        $this->raiz = rtrim(str_replace('\\', '/', $raiz), '/');
        $this->cargarArchivo($this->raiz . '/.env');
    }

    /**
     * ***************************************************************************
     * * LEE VARIABLES DE CONFIGURACION EN FORMATO CLAVE=VALOR.
     * ***************************************************************************
     */
    private function cargarArchivo(string $ruta): void
    {
        if (!is_file($ruta)) {
            return;
        }

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $this->valores[trim($clave)] = trim($valor, " \t\n\r\0\x0B\"'");
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE UNA VARIABLE DE CONFIGURACION O DEVUELVE UN VALOR POR DEFECTO.
     * ***************************************************************************
     */
    public function obtener(string $clave, ?string $defecto = null): ?string
    {
        return $this->valores[$clave] ?? $defecto;
    }

    /**
     * ***************************************************************************
     * * DEVUELVE LA RUTA ABSOLUTA DEL PROYECTO.
     * ***************************************************************************
     */
    public function raiz(): string
    {
        return $this->raiz;
    }

    /**
     * ***************************************************************************
     * * CONSTRUYE UNA RUTA ABSOLUTA DESDE UNA RUTA RELATIVA DEL .ENV.
     * ***************************************************************************
     */
    public function ruta(string $clave, string $defecto): string
    {
        $ruta = $this->obtener($clave, $defecto) ?? $defecto;
        $ruta = trim(str_replace('\\', '/', $ruta), '/');

        return $this->raiz . '/' . $ruta;
    }
}
