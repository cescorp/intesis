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
                . '<td class="numero text-center">' . $this->numeroEntero((float) $detalle['entrada']) . '</td>'
                . '<td class="numero text-center">' . $this->numeroEntero((float) $detalle['salida']) . '</td>'
                . '<td class="numero text-center">' . $this->numeroEntero((float) $detalle['saldo']) . '</td>'
                . '</tr>';
        }

        if ($filas === '') {
            $filas = '<tr><td colspan="6" class="sin-registros">Sin detalles registrados.</td></tr>';
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
        .text-center { text-align: center; }
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
        <tr>
            <td width="50%"><span class="etiqueta">Realizado por:</span> ' . $this->escapar((string) $cabecera['responsable']) . '</td>
            <td>' . (!empty($cabecera['aprobado_por']) ? '<span class="etiqueta">Aprobado por:</span> ' . $this->escapar((string) $cabecera['aprobado_por']) : '') . '</td>
        </tr>
        <tr><td colspan="2"><span class="etiqueta">Observacion:</span> ' . $this->escapar((string) $cabecera['observacion']) . '</td></tr>
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

    public function generarProforma(array $proforma, array $lineas, array $empresa): string
    {
        return $this->generarDesdeHtml($this->crearHtmlProforma($proforma, $lineas, $empresa));
    }

    private function crearHtmlProforma(array $proforma, array $lineas, array $empresa): string
    {
        $filas = '';
        $num   = 0;
        foreach ($lineas as $l) {
            $num++;
            $cant   = (float) ($l['ven_documento_detalle_cantidad']      ?? 0);
            $precio = (float) ($l['ven_documento_detalle_precio_unitario'] ?? 0);
            $dto    = (float) ($l['ven_documento_detalle_descuento']      ?? 0);
            $total  = (float) ($l['ven_documento_detalle_precio_total_sin_impuestos'] ?? $l['ven_documento_detalle_precio_total'] ?? 0);
            $iva    = (float) ($l['ven_documento_detalle_impuesto_total'] ?? 0);
            $filas .= '<tr>'
                . '<td class="text-center">' . $num . '</td>'
                . '<td class="mono">' . $this->escapar((string) ($l['codigo_interno'] ?? $l['ven_documento_detalle_codigo'] ?? '')) . '</td>'
                . '<td>' . $this->escapar((string) ($l['producto_nombre'] ?? $l['ven_documento_detalle_descripcion'] ?? '')) . '</td>'
                . '<td class="numero">' . number_format($cant, 2, '.', '') . '</td>'
                . '<td class="numero">$' . number_format($precio, 4, '.', '') . '</td>'
                . '<td class="numero">' . number_format($dto, 2, '.', '') . '%</td>'
                . '<td class="numero">$' . number_format($iva, 2, '.', '') . '</td>'
                . '<td class="numero">$' . number_format($total + $iva, 2, '.', '') . '</td>'
                . '</tr>';
        }
        if ($filas === '') {
            $filas = '<tr><td colspan="8" class="sin-registros">Sin líneas registradas.</td></tr>';
        }

        $subtotal = (float) ($proforma['ven_documento_subtotal_sin_impuestos'] ?? $proforma['ven_documento_subtotal'] ?? 0);
        $iva      = (float) ($proforma['ven_documento_impuesto_total']          ?? $proforma['ven_documento_iva']     ?? 0);
        $total    = (float) ($proforma['ven_documento_valor_total']              ?? $proforma['ven_documento_total']  ?? 0);
        $descuento= (float) ($proforma['ven_documento_descuento'] ?? 0);

        $nombreEmpresa = $this->escapar((string) ($empresa['sis_empresa_nombre_comercial'] ?: $empresa['sis_empresa_razon_social'] ?? ''));
        $rucEmpresa    = $this->escapar((string) ($empresa['sis_empresa_ruc']       ?? ''));
        $dirEmpresa    = $this->escapar((string) ($empresa['sis_empresa_direccion'] ?? ''));
        $telEmpresa    = $this->escapar((string) ($empresa['sis_empresa_telefono']  ?? ''));

        $cliente  = $this->escapar((string) ($proforma['ven_cliente_razon_social']     ?? ''));
        $rucCli   = $this->escapar((string) ($proforma['ven_cliente_identificacion']   ?? ''));
        $numero   = $this->escapar((string) ($proforma['ven_documento_numero']         ?? ''));
        $fecha    = $this->escapar(substr((string) ($proforma['ven_documento_fecha_emision'] ?? ''), 0, 10));
        $obs      = $this->escapar((string) ($proforma['ven_documento_observacion']    ?? ''));

        return '<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 34px 38px 34px; }
    body { font-family: Arial, sans-serif; color: #2f3742; font-size: 10px; }
    .cabecera { display: table; width: 100%; border-bottom: 2px solid #1f6f68; padding-bottom: 12px; margin-bottom: 14px; }
    .cab-izq { display: table-cell; width: 60%; vertical-align: top; }
    .cab-der { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
    h1 { margin: 0 0 3px 0; color: #1f6f68; font-size: 16px; }
    h2 { margin: 0 0 4px 0; color: #1f6f68; font-size: 14px; letter-spacing: 1px; }
    .dato { margin: 2px 0; color: #52606d; }
    .etiqueta { font-weight: bold; color: #344054; }
    .numero-doc { font-size: 13px; font-weight: bold; color: #344054; }
    .bloque-cliente { background: #f0f7f6; border-left: 3px solid #1f6f68; padding: 8px 10px; margin-bottom: 12px; }
    .tabla-detalle { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 9.5px; }
    .tabla-detalle thead th { background: #1f6f68; color: #fff; padding: 6px 5px; text-align: left; }
    .tabla-detalle tbody td { padding: 5px 5px; border-bottom: 1px dotted #c8d0d6; vertical-align: top; }
    .tabla-detalle tbody tr:nth-child(even) td { background: #f7faf9; }
    .numero { text-align: right; white-space: nowrap; }
    .mono { font-family: "Courier New", monospace; font-size: 9px; }
    .text-center { text-align: center; }
    .sin-registros { text-align: center; color: #667085; padding: 12px; }
    .totales { width: 260px; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
    .totales td { padding: 4px 8px; border-bottom: 1px dotted #c8d0d6; }
    .totales .total-final td { font-weight: bold; font-size: 11px; color: #1f6f68; border-top: 2px solid #1f6f68; border-bottom: none; }
    .obs { margin-top: 14px; color: #52606d; font-size: 9.5px; }
    .footer { margin-top: 50px; display: table; width: 100%; text-align: center; color: #8b98a5; font-size: 9px; border-top: 1px dotted #c8d0d6; padding-top: 8px; }
</style>
</head>
<body>
<div class="cabecera">
    <div class="cab-izq">
        <h1>' . $nombreEmpresa . '</h1>
        <div class="dato"><span class="etiqueta">RUC:</span> ' . $rucEmpresa . '</div>
        <div class="dato">' . $dirEmpresa . '</div>
        <div class="dato"><span class="etiqueta">Tel:</span> ' . $telEmpresa . '</div>
    </div>
    <div class="cab-der">
        <h2>PROFORMA</h2>
        <div class="numero-doc">' . $numero . '</div>
        <div class="dato"><span class="etiqueta">Fecha:</span> ' . $fecha . '</div>
    </div>
</div>

<div class="bloque-cliente">
    <span class="etiqueta">Cliente:</span> ' . $cliente . '
    &nbsp;&nbsp;<span class="etiqueta">RUC/ID:</span> ' . $rucCli . '
</div>

<table class="tabla-detalle">
    <thead>
        <tr>
            <th class="text-center" style="width:22px">#</th>
            <th style="width:80px">Codigo</th>
            <th>Descripcion</th>
            <th class="numero" style="width:50px">Cant.</th>
            <th class="numero" style="width:70px">Precio</th>
            <th class="numero" style="width:40px">Desc.</th>
            <th class="numero" style="width:55px">IVA</th>
            <th class="numero" style="width:65px">Total</th>
        </tr>
    </thead>
    <tbody>' . $filas . '</tbody>
</table>

<table class="totales">
    <tr><td>Subtotal sin impuestos</td><td class="numero">$' . number_format($subtotal, 2, '.', ',') . '</td></tr>
    <tr><td>Descuento</td><td class="numero">$' . number_format($descuento, 2, '.', ',') . '</td></tr>
    <tr><td>IVA</td><td class="numero">$' . number_format($iva, 2, '.', ',') . '</td></tr>
    <tr class="total-final"><td>TOTAL</td><td class="numero">$' . number_format($total, 2, '.', ',') . '</td></tr>
</table>

' . ($obs ? '<div class="obs"><span class="etiqueta">Observacion:</span> ' . $obs . '</div>' : '') . '

<div class="footer">
    Documento generado electronicamente &mdash; ' . $nombreEmpresa . ' &mdash; ' . date('Y-m-d H:i') . '
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

    private function numeroEntero(float $valor): string
    {
        return number_format((int) round($valor), 0, '.', '');
    }
}
