<?php

declare(strict_types=1);

namespace Intesis\Servicios;

final class SriClaveAccesoServicio
{
    public function __construct(private SriUtilidadesServicio $util) {}

    /**
     * Genera la clave de acceso de 49 dígitos según especificación SRI Ecuador.
     *
     * Estructura:
     *   8  dígitos: ddmmyyyy (fecha emisión)
     *   2  dígitos: tipo comprobante (01=factura)
     *  13  dígitos: RUC emisor
     *   1  dígito : ambiente (1=pruebas, 2=producción)
     *   3  dígitos: establecimiento
     *   3  dígitos: punto de emisión
     *   9  dígitos: secuencial
     *   8  dígitos: código numérico
     *   1  dígito : tipo emisión (1=normal)
     *   1  dígito : verificador módulo 11
     * = 49 dígitos
     */
    public function generar(
        string $fechaYmd,
        string $tipoComprobante,
        string $ruc,
        string $ambiente,
        string $establecimiento,
        string $puntoEmision,
        string $secuencial,
        string $codigoNumerico,
        string $tipoEmision = '1'
    ): string {
        $base =
            $this->util->fechaParaClave($fechaYmd) .
            str_pad($tipoComprobante, 2, '0', STR_PAD_LEFT) .
            str_pad($this->util->soloNumeros($ruc), 13, '0', STR_PAD_LEFT) .
            substr($ambiente, 0, 1) .
            str_pad($this->util->soloNumeros($establecimiento), 3, '0', STR_PAD_LEFT) .
            str_pad($this->util->soloNumeros($puntoEmision), 3, '0', STR_PAD_LEFT) .
            str_pad($this->util->soloNumeros($secuencial), 9, '0', STR_PAD_LEFT) .
            str_pad($this->util->soloNumeros($codigoNumerico), 8, '0', STR_PAD_LEFT) .
            substr($tipoEmision, 0, 1);

        if (strlen($base) !== 48) {
            throw new \RuntimeException('Error interno al generar clave de acceso: longitud incorrecta (' . strlen($base) . ').');
        }

        return $base . $this->util->digitoVerificadorModulo11($base);
    }
}
