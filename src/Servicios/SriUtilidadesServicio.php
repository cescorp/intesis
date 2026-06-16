<?php

declare(strict_types=1);

namespace Intesis\Servicios;

final class SriUtilidadesServicio
{
    public function soloNumeros(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    public function fechaSri(string $fechaYmd): string
    {
        // Convierte YYYY-MM-DD a DD/MM/YYYY
        $partes = explode('-', substr($fechaYmd, 0, 10));
        if (count($partes) !== 3) {
            return date('d/m/Y');
        }
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }

    public function fechaParaClave(string $fechaYmd): string
    {
        // Retorna ddmmyyyy sin separadores
        $partes = explode('-', substr($fechaYmd, 0, 10));
        if (count($partes) !== 3) {
            return date('dmY');
        }
        return $partes[2] . $partes[1] . $partes[0];
    }

    public function codigoNumericoAleatorio(): string
    {
        return str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    public function textoSeguro(string $valor, int $max = 300): string
    {
        $limpio = trim(preg_replace('/[\x00-\x1f\x7f]/', ' ', $valor) ?? '');
        return mb_substr($limpio, 0, $max, 'UTF-8');
    }

    public function digitoVerificadorModulo11(string $cadena): string
    {
        $factor      = 2;
        $acumulado   = 0;
        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $acumulado += ((int) $cadena[$i]) * $factor;
            $factor++;
            if ($factor > 7) {
                $factor = 2;
            }
        }
        $resultado = 11 - ($acumulado % 11);
        if ($resultado === 11) $resultado = 0;
        if ($resultado === 10) $resultado = 1;
        return (string) $resultado;
    }

    public function codigoPorcentajeIva(float $tarifa): string
    {
        return match (true) {
            abs($tarifa - 15.0) < 0.01 => '4',
            abs($tarifa - 14.0) < 0.01 => '3',
            abs($tarifa - 12.0) < 0.01 => '2',
            abs($tarifa) < 0.01         => '0',
            default                     => '8',
        };
    }

    public function tipoIdentificacionSri(string $tipoSistema): string
    {
        return match (strtoupper($tipoSistema)) {
            'R'     => '04',  // RUC
            'C'     => '05',  // Cédula
            'P'     => '06',  // Pasaporte
            'O'     => '07',  // Consumidor final
            default => '07',
        };
    }

    public function parsearNumeroDocumento(string $numero): array
    {
        // Formato: 001-001-000000001
        $partes = explode('-', $numero);
        return [
            'estab'      => str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT),
            'pto_emi'    => str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT),
            'secuencial' => str_pad(ltrim($partes[2] ?? '1', '0') ?: '1', 9, '0', STR_PAD_LEFT),
        ];
    }

    public function f2(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    public function crearDirectoriosSiNoExisten(string $raiz, string $ruc): void
    {
        $base = $raiz . '/almacenamiento/ventas/' . $ruc;
        foreach (['temp', 'autorizados', 'error', 'pendiente'] as $dir) {
            if (!is_dir($base . '/' . $dir)) {
                mkdir($base . '/' . $dir, 0775, true);
            }
        }
    }
}
