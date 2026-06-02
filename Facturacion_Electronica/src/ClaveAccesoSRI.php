<?php

/******************************************************************************/
/*                                                                            */
/*  GENERADOR DE CLAVE DE ACCESO SRI (49 DIGITOS)                            */
/*                                                                            */
/******************************************************************************/

class ClaveAccesoSRI
{
    /**************************************************************************/
    /*  CONSTRUYE LA CLAVE DE ACCESO                                           */
    /**************************************************************************/
    public static function generar(
        string $fechaDdMmYyyy,
        string $codigoComprobante,
        string $ruc,
        string $ambiente,
        string $establecimiento,
        string $puntoEmision,
        string $secuencial,
        string $codigoNumerico = '12345678',
        string $tipoEmision = '1'
    ): string {
        $base =
            self::normalizarDigitos(UtilidadesSistema::fechaParaClave($fechaDdMmYyyy), 8) .
            self::normalizarDigitos($codigoComprobante, 2) .
            self::normalizarDigitos($ruc, 13) .
            self::normalizarDigitos($ambiente, 1) .
            self::normalizarDigitos($establecimiento, 3) .
            self::normalizarDigitos($puntoEmision, 3) .
            self::normalizarDigitos($secuencial, 9) .
            self::normalizarDigitos($codigoNumerico, 8) .
            self::normalizarDigitos($tipoEmision, 1);

        return $base . self::digitoVerificadorModulo11($base);
    }

    /**************************************************************************/
    /*  NORMALIZA LONGITUD NUMERICA                                            */
    /**************************************************************************/
    private static function normalizarDigitos(string $valor, int $longitud): string
    {
        $solo = UtilidadesSistema::soloNumeros($valor);
        return str_pad(substr($solo, -$longitud), $longitud, '0', STR_PAD_LEFT);
    }

    /**************************************************************************/
    /*  DIGITO VERIFICADOR MODULO 11                                           */
    /**************************************************************************/
    private static function digitoVerificadorModulo11(string $cadena): string
    {
        $factor = 2;
        $acumulado = 0;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $acumulado += ((int)$cadena[$i]) * $factor;
            $factor++;
            if ($factor > 7) {
                $factor = 2;
            }
        }

        $resultado = 11 - ($acumulado % 11);

        if ($resultado === 11) {
            $resultado = 0;
        }

        if ($resultado === 10) {
            $resultado = 1;
        }

        return (string)$resultado;
    }
}
