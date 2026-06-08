<?php

declare(strict_types=1);

namespace Intesis\Servicios;

final class GeneradorPdf
{
    /**
     * ***************************************************************************
     * * GENERA UN PDF A PARTIR DE HTML USANDO DOMPDF.
     * ***************************************************************************
     */
    public function generarDesdeHtml(string $html): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Dompdf no esta instalado. Ejecute composer install.');
        }

        $opciones = new \Dompdf\Options();
        $opciones->set('isRemoteEnabled', true);
        $opciones->set('isHtml5ParserEnabled', true);
        $opciones->set('defaultFont', 'Arial');

        $dompdf = new \Dompdf\Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * ***************************************************************************
     * * GENERA PDF DE MOVIMIENTO DE INVENTARIO CON FORMATO HTML.
     * ***************************************************************************
     */
    public function generarMovimientoInventario(array $cabecera, array $detalles): string
    {
        return $this->generarDesdeHtml($this->crearHtmlMovimientoInventario($cabecera, $detalles));
    }

    /**
     * ***************************************************************************
     * * CREA HTML DEL DOCUMENTO INTERNO DE INVENTARIO.
     * ***************************************************************************
     */
    private function crearHtmlMovimientoInventario(array $cabecera, array $detalles): string
    {
        $filas = '';
        foreach ($detalles as $detalle) {
            $filas .= '<tr>'
                . '<td>' . $this->escapar((string) $detalle['codigo']) . '</td>'
                . '<td>' . $this->escapar((string) $detalle['producto']) . '</td>'
                . '<td>' . $this->escapar((string) $detalle['bodega']) . '</td>'
                . '<td class="numero text-center">' . $this->numero((float) $detalle['entrada']) . '</td>'
                . '<td class="numero text-center">' . $this->numero((float) $detalle['salida']) . '</td>'
                . '<td class="numero text-center">' . $this->numero((float) $detalle['saldo']) . '</td>'
                . '<td>' . $this->escapar((string) $detalle['observacion']) . '</td>'
                . '</tr>';
        }

        if ($filas === '') {
            $filas = '<tr><td colspan="7" class="sin-registros">Sin detalles registrados.</td></tr>';
        }

        return '<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 34px 38px 34px; }
        body { font-family: Arial, sans-serif; color: #2f3742; font-size: 11px; }
        .cabecera { display: table; width: 100%; border-bottom: 1px dotted #aeb7bf; padding-bottom: 14px; margin-bottom: 18px; }
        .cabecera-bloque { display: table-cell; width: 50%; vertical-align: top; }
        .documento { text-align: right; }
        h1 { margin: 0 0 5px 0; color: #284b63; font-size: 19px; letter-spacing: 0; }
        h2 { margin: 0 0 7px 0; color: #6b4e71; font-size: 18px; letter-spacing: 0; }
        .dato { margin: 3px 0; color: #52606d; }
        .etiqueta { color: #344054; font-weight: bold; }
        .resumen { width: 100%; margin: 12px 0 20px 0; border-collapse: collapse; }
        .resumen td { padding: 6px 8px; border-bottom: 1px dotted #c7cdd3; }
        .tabla-detalle { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .tabla-detalle thead th { background: #dfeee8; color: #274c43; padding: 8px 6px; text-align: left; border-bottom: 1px dotted #98aaa3; }
        .tabla-detalle tbody td { padding: 7px 6px; border-bottom: 1px dotted #c8d0d6; vertical-align: top; }
        .tabla-detalle tbody tr:nth-child(even) td { background: #f7faf9; }
        .numero { text-align: right; white-space: nowrap; }
        .sin-registros { text-align: center; color: #667085; padding: 16px; }
        .firma { margin-top: 70px; text-align: center; color: #344054; }
        .firma-linea { width: 180px; border-top: 1px dotted #8b98a5; margin: 0 auto 7px auto; }
        .responsable { margin-top: 4px; color: #52606d; }
    </style>
</head>
<body>
    <div class="cabecera">
        <div class="cabecera-bloque">
            <h1>' . $this->escapar(strtoupper((string) $cabecera['empresa_nombre'])) . '</h1>
            <div class="dato"><span class="etiqueta">RUC:</span> ' . $this->escapar((string) $cabecera['empresa_ruc']) . '</div>
            <div class="dato">' . $this->escapar((string) $cabecera['empresa_direccion']) . '</div>
        </div>
        <div class="cabecera-bloque documento">
            <h2>' . $this->escapar(strtoupper((string) $cabecera['tipo_documento'])) . '</h2>
            <div class="dato"><span class="etiqueta">Secuencia:</span> ' . $this->escapar((string) $cabecera['numero']) . '</div>
            <div class="dato"><span class="etiqueta">Fecha y hora:</span> ' . $this->escapar((string) $cabecera['fecha']) . ' ' . $this->escapar((string) $cabecera['hora']) . '</div>
        </div>
    </div>
    <table class="resumen">
        <tr><td width="18%" class="etiqueta">Referencia</td><td>' . $this->escapar((string) $cabecera['referencia']) . '</td></tr>
        <tr><td class="etiqueta">Observacion</td><td>' . $this->escapar((string) $cabecera['observacion']) . '</td></tr>
    </table>
    <table class="tabla-detalle">
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Producto</th>
                <th>Bodega</th>
                <th class="numero text-center">Cantidad</th>
                <th class="numero text-center">Salida</th>
                <th class="numero text-center">Saldo</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>' . $filas . '</tbody>
    </table>
    <div class="firma">
        <div class="firma-linea"></div>
        <div>Firma</div>
        <div class="responsable">' . $this->escapar((string) $cabecera['responsable']) . '</div>
    </div>
</body>
</html>';
    }

    private function escapar(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }
}
