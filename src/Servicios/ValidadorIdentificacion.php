<?php

declare(strict_types=1);

namespace Intesis\Servicios;

final class ValidadorIdentificacion
{
    /**
     * ***************************************************************************
     * * VALIDA FORMALMENTE UNA CEDULA ECUATORIANA DE 10 DIGITOS.
     * ***************************************************************************
     */
    public static function validarCedula(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }

        $provincia = (int) substr($cedula, 0, 2);
        $tercerDigito = (int) $cedula[2];
        if ($provincia < 1 || $provincia > 24 || $tercerDigito > 5) {
            return false;
        }

        return self::validarModuloDiez(substr($cedula, 0, 9), (int) $cedula[9]);
    }

    /**
     * ***************************************************************************
     * * VALIDA FORMALMENTE UN RUC ECUATORIANO NATURAL, PRIVADO O PUBLICO.
     * ***************************************************************************
     */
    public static function validarRuc(string $ruc): bool
    {
        if (!preg_match('/^\d{13}$/', $ruc) || substr($ruc, 10, 3) !== '001') {
            return false;
        }

        $tercerDigito = (int) $ruc[2];
        if ($tercerDigito <= 5) {
            return self::validarCedula(substr($ruc, 0, 10));
        }

        if ($tercerDigito === 6) {
            return self::validarModuloOnce(substr($ruc, 0, 8), (int) $ruc[8], [3, 2, 7, 6, 5, 4, 3, 2]);
        }

        if ($tercerDigito === 9) {
            return self::validarModuloOnce(substr($ruc, 0, 9), (int) $ruc[9], [4, 3, 2, 7, 6, 5, 4, 3, 2]);
        }

        return false;
    }

    /**
     * ***************************************************************************
     * * APLICA EL ALGORITMO MODULO 10 PARA CEDULA Y RUC NATURAL.
     * ***************************************************************************
     */
    private static function validarModuloDiez(string $base, int $verificador): bool
    {
        $suma = 0;
        for ($i = 0; $i < strlen($base); $i++) {
            $valor = (int) $base[$i];
            if ($i % 2 === 0) {
                $valor *= 2;
                if ($valor > 9) {
                    $valor -= 9;
                }
            }
            $suma += $valor;
        }

        $resultado = $suma % 10 === 0 ? 0 : 10 - ($suma % 10);
        return $resultado === $verificador;
    }

    /**
     * ***************************************************************************
     * * APLICA EL ALGORITMO MODULO 11 PARA RUC PUBLICO Y PRIVADO.
     * ***************************************************************************
     */
    private static function validarModuloOnce(string $base, int $verificador, array $coeficientes): bool
    {
        $suma = 0;
        foreach ($coeficientes as $indice => $coeficiente) {
            $suma += (int) $base[$indice] * $coeficiente;
        }

        $residuo = $suma % 11;
        $resultado = $residuo === 0 ? 0 : 11 - $residuo;
        return $resultado === $verificador;
    }
}
