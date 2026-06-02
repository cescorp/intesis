<?php

/******************************************************************************/
/*                                                                            */
/*  UTILIDADES GENERALES                                                      */
/*                                                                            */
/******************************************************************************/

class UtilidadesSistema
{
    /**************************************************************************/
    /*  CREA DIRECTORIOS SI NO EXISTEN                                        */
    /**************************************************************************/
    public static function asegurarDirectorios(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            if (!is_dir($ruta)) {
                mkdir($ruta, 0777, true);
            }
        }
    }

    /**************************************************************************/
    /*  LIMPIA TEXTO PARA XML                                                  */
    /**************************************************************************/
    public static function textoSeguro(string $valor, int $maximo = 300): string
    {
        $limpio = trim($valor);
        $limpio = preg_replace('/\s+/', ' ', $limpio) ?? '';
        $limpio = str_replace(['&', '<', '>'], ['y', ' ', ' '], $limpio);
        return substr($limpio, 0, $maximo);
    }

    /**************************************************************************/
    /*  RETORNA SOLO DIGITOS                                                   */
    /**************************************************************************/
    public static function soloNumeros(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    /**************************************************************************/
    /*  FORMATEA DECIMAL A 2 DECIMALES                                         */
    /**************************************************************************/
    public static function valorDecimal($valor): string
    {
        $numero = is_numeric($valor) ? (float)$valor : 0.00;
        return number_format($numero, 2, '.', '');
    }

    /**************************************************************************/
    /*  NORMALIZA FECHA A dd/mm/yyyy                                           */
    /**************************************************************************/
    public static function fechaEmisionNormalizada(string $fecha): string
    {
        $fecha = trim($fecha);

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            return $fecha;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            [$y, $m, $d] = explode('-', $fecha);
            return $d . '/' . $m . '/' . $y;
        }

        return date('d/m/Y');
    }

    /**************************************************************************/
    /*  CONVIERTE dd/mm/yyyy A ddmmyyyy                                        */
    /**************************************************************************/
    public static function fechaParaClave(string $fechaDdMmYyyy): string
    {
        $partes = explode('/', $fechaDdMmYyyy);
        if (count($partes) !== 3) {
            return date('dmY');
        }

        return $partes[0] . $partes[1] . $partes[2];
    }

    /**************************************************************************/
    /*  GUARDA TEXTO EN ARCHIVO                                                */
    /**************************************************************************/
    public static function guardarArchivo(string $rutaCompleta, string $contenido): void
    {
        file_put_contents($rutaCompleta, $contenido);
    }

    /**************************************************************************/
    /*  CARGA TEXTO DE UN ARCHIVO                                              */
    /**************************************************************************/
    public static function cargarArchivo(string $rutaCompleta): string
    {
        $contenido = file_get_contents($rutaCompleta);
        return $contenido === false ? '' : $contenido;
    }

    /**************************************************************************/
    /*  REGISTRA LOG SIMPLE                                                    */
    /**************************************************************************/
    public static function registrarLog(string $rutaLog, string $mensaje): void
    {
        $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
        file_put_contents($rutaLog, $linea, FILE_APPEND);
    }

    /**************************************************************************/
    /*  OBTIENE VALOR POST COMO TEXTO                                          */
    /**************************************************************************/
    public static function postTexto(string $campo, string $defecto = ''): string
    {
        return isset($_POST[$campo]) ? trim((string)$_POST[$campo]) : $defecto;
    }
}
