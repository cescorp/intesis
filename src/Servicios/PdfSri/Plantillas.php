<?php
/***********************************************************************
 * PLANTILLAS PDF SRI
 * Copiado del subproyecto importar_sri_leer_xml/lib/pdf_sri/Plantillas.php
 * No depende de base de datos ni de rutas externas.
 **********************************************************************/

require_once __DIR__ . '/Barcode128.php';

if (!function_exists('gpdf_e')) {
    function gpdf_e($valor)
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gpdf_valor')) {
    function gpdf_valor($arreglo, $campo, $defecto = '')
    {
        if (!is_array($arreglo) || !array_key_exists($campo, $arreglo) || $arreglo[$campo] === null) {
            return $defecto;
        }
        return $arreglo[$campo];
    }
}

if (!function_exists('gpdf_moneda')) {
    function gpdf_moneda($valor)
    {
        if ($valor === '' || $valor === null) {
            $valor = 0;
        }
        return number_format((float) $valor, 2, '.', ',');
    }
}

if (!function_exists('gpdf_tipo_documento')) {
    function gpdf_tipo_documento($cabecera)
    {
        $tipo   = strtoupper(trim((string) gpdf_valor($cabecera, 'tipo_documento', '')));
        $codigo = trim((string) gpdf_valor($cabecera, 'codigo_tipo_documento', ''));
        if ($tipo === 'RETENCION' || $tipo === 'COMPROBANTE DE RETENCION' || $codigo === '07') {
            return 'RETENCION';
        }
        return 'FACTURA';
    }
}

if (!function_exists('gpdf_nombre_tipo_documento')) {
    function gpdf_nombre_tipo_documento($cabecera)
    {
        return gpdf_tipo_documento($cabecera) === 'RETENCION' ? 'COMPROBANTE DE RETENCION' : 'FACTURA';
    }
}

if (!function_exists('gpdf_numero_documento')) {
    function gpdf_numero_documento($cabecera)
    {
        $numero = trim((string) gpdf_valor($cabecera, 'numero_documento', ''));
        if ($numero !== '') {
            return $numero;
        }
        $estab      = trim((string) gpdf_valor($cabecera, 'estab', ''));
        $ptoEmi     = trim((string) gpdf_valor($cabecera, 'pto_emi', ''));
        $secuencial = trim((string) gpdf_valor($cabecera, 'secuencial', ''));
        return trim($estab . '-' . $ptoEmi . '-' . $secuencial, '-');
    }
}

if (!function_exists('gpdf_logo_data_uri')) {
    function gpdf_logo_data_uri($rutaLogo = null)
    {
        $rutas = array();
        if ($rutaLogo) {
            $rutas[] = $rutaLogo;
        }
        $rutas[] = __DIR__ . DIRECTORY_SEPARATOR . 'no_tiene_logo.jpg';

        foreach ($rutas as $ruta) {
            if (is_file($ruta) && is_readable($ruta)) {
                $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
                $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($ruta));
            }
        }
        return '';
    }
}

if (!function_exists('gpdf_html_documento')) {
    function gpdf_html_documento($documento, $rutaLogo = null)
    {
        $cabecera      = gpdf_valor($documento, 'cabecera', array());
        $detalles      = gpdf_valor($documento, 'detalles', array());
        $pagos         = gpdf_valor($documento, 'pagos', array());
        $infoAdicional = gpdf_valor($documento, 'info_adicional', array());
        $logo          = gpdf_logo_data_uri($rutaLogo);

        $contenido = gpdf_bloque_superior($cabecera, $logo);
        $contenido .= gpdf_tipo_documento($cabecera) === 'RETENCION'
            ? gpdf_retencion($cabecera, $detalles, $infoAdicional)
            : gpdf_factura($cabecera, $detalles, $pagos, $infoAdicional);

        return gpdf_html_base($contenido, (bool) gpdf_valor($cabecera, 'anulado', false));
    }
}

if (!function_exists('gpdf_html_base')) {
    function gpdf_html_base($contenido, $anulado = false)
    {
        $marcaAgua = $anulado
            ? '<div style="position:fixed;top:40%;left:5%;width:90%;text-align:center;
                transform:rotate(-30deg);font-size:70px;font-weight:bold;color:#dc3545;opacity:0.25;
                z-index:1000;">ANULADO</div>'
            : '';
        return '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 18px 18px 22px 18px; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 4px 5px; vertical-align: top; }
    .gpdf-fila-superior { width: 100%; }
    .gpdf-col-izq { width: 45%; padding-right: 10px; }
    .gpdf-col-der { width: 55%; padding-left: 10px; }
    .gpdf-logo { height: 118px; margin-bottom: 18px; }
    .gpdf-logo img { max-width: 330px; max-height: 112px; }
    .gpdf-box { border: 1.5px solid #000; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
    .gpdf-box p { margin: 4px 0; line-height: 1.25; }
    .gpdf-titulo-doc { font-size: 18px; margin: 8px 0 18px 0; }
    .gpdf-ruc { font-size: 21px; letter-spacing: 1px; margin-bottom: 10px; }
    .gpdf-label { font-weight: bold; }
    .gpdf-receptor { border: 1.3px solid #000; margin: 10px 0; }
    .gpdf-tabla th { border: 1px solid #000; text-align: center; font-weight: bold; }
    .gpdf-tabla td { border: 1px solid #000; }
    .gpdf-derecha { text-align: right; }
    .gpdf-centro { text-align: center; }
    .gpdf-totales { width: 42%; float: right; margin-top: 8px; }
    .gpdf-pagos { width: 52%; float: left; margin-top: 8px; }
    .gpdf-limpiar { clear: both; }
    .gpdf-barcode { text-align: center; margin-top: 7px; overflow: hidden; }
    .gpdf-barcode-text { font-size: 8px; margin-top: 2px; letter-spacing: .5px; }
    .gpdf-info { margin-top: 10px; }
</style>
</head>
<body>' . $marcaAgua . $contenido . '</body>
</html>';
    }
}

if (!function_exists('gpdf_bloque_superior')) {
    function gpdf_bloque_superior($cabecera, $logo)
    {
        $clave              = (string) gpdf_valor($cabecera, 'clave_acceso', '');
        $numeroAutorizacion = (string) gpdf_valor($cabecera, 'numero_autorizacion', $clave);
        $tipo               = gpdf_nombre_tipo_documento($cabecera);
        $numero             = gpdf_numero_documento($cabecera);

        $logoHtml = $logo !== ''
            ? '<img src="' . gpdf_e($logo) . '" alt="Logo">'
            : '<strong>NO TIENE LOGO</strong>';

        return '<table class="gpdf-fila-superior">
            <tr>
                <td class="gpdf-col-izq">
                    <div class="gpdf-logo">' . $logoHtml . '</div>
                    <div class="gpdf-box">
                        <p>' . gpdf_e(gpdf_valor($cabecera, 'razon_social_emisor', '')) . '</p>
                        <p>' . gpdf_e(gpdf_valor($cabecera, 'nombre_comercial_emisor', '')) . '</p>
                        <p><span class="gpdf-label">Direccion Matriz:</span> ' . gpdf_e(gpdf_valor($cabecera, 'dir_matriz', '')) . '</p>
                        <p><span class="gpdf-label">Direccion Sucursal:</span> ' . gpdf_e(gpdf_valor($cabecera, 'dir_sucursal', '')) . '</p>
                        <p><span class="gpdf-label">OBLIGADO A LLEVAR CONTABILIDAD:</span> ' . gpdf_e(gpdf_valor($cabecera, 'obligado_contabilidad', '')) . '</p>
                    </div>
                </td>
                <td class="gpdf-col-der">
                    <div class="gpdf-box">
                        <div class="gpdf-ruc">R.U.C.: &nbsp; ' . gpdf_e(gpdf_valor($cabecera, 'ruc_emisor', '')) . '</div>
                        <div class="gpdf-titulo-doc">' . gpdf_e($tipo) . '</div>
                        <p><span class="gpdf-label">No.</span> &nbsp;&nbsp; ' . gpdf_e($numero) . '</p>
                        <p><span class="gpdf-label">NUMERO DE AUTORIZACION</span></p>
                        <p>' . gpdf_e($numeroAutorizacion) . '</p>
                        <p><span class="gpdf-label">FECHA Y HORA DE AUTORIZACION:</span> &nbsp; ' . gpdf_e(gpdf_valor($cabecera, 'fecha_autorizacion', '')) . '</p>
                        <p><span class="gpdf-label">AMBIENTE:</span> &nbsp; ' . gpdf_e(gpdf_valor($cabecera, 'ambiente', '')) . '</p>
                        <p><span class="gpdf-label">EMISION:</span> &nbsp; ' . gpdf_e(gpdf_valor($cabecera, 'tipo_emision', 'NORMAL')) . '</p>
                        <p class="gpdf-label">CLAVE DE ACCESO</p>
                        <div class="gpdf-barcode">' . gpdf_barcode_code128_svg($clave, 345, 45) . '<div class="gpdf-barcode-text">' . gpdf_e(gpdf_barcode_texto_clave($clave)) . '</div></div>
                    </div>
                </td>
            </tr>
        </table>';
    }
}

if (!function_exists('gpdf_receptor_factura')) {
    function gpdf_receptor_factura($cabecera)
    {
        return '<table class="gpdf-receptor">
            <tr>
                <td style="width: 22%;"><span class="gpdf-label">Razon Social / Nombres y Apellidos:</span></td>
                <td style="width: 78%;">' . gpdf_e(gpdf_valor($cabecera, 'razon_social_receptor', '')) . '</td>
            </tr>
            <tr>
                <td><span class="gpdf-label">Identificacion</span></td>
                <td>' . gpdf_e(gpdf_valor($cabecera, 'identificacion_receptor', '')) . '</td>
            </tr>
            <tr>
                <td><span class="gpdf-label">Fecha</span> &nbsp; ' . gpdf_e(gpdf_valor($cabecera, 'fecha_emision', '')) . '</td>
                <td><span class="gpdf-label">Placa / Matricula:</span> &nbsp; ' . gpdf_e(gpdf_valor($cabecera, 'placa', '')) . ' &nbsp;&nbsp;&nbsp; <span class="gpdf-label">Guia:</span> &nbsp; ' . gpdf_e(gpdf_valor($cabecera, 'guia_remision', '')) . '</td>
            </tr>
            <tr>
                <td><span class="gpdf-label">Direccion:</span></td>
                <td>' . gpdf_e(gpdf_valor($cabecera, 'direccion_receptor', '')) . '</td>
            </tr>
        </table>';
    }
}

if (!function_exists('gpdf_factura')) {
    function gpdf_factura($cabecera, $detalles, $pagos, $infoAdicional)
    {
        $html = gpdf_receptor_factura($cabecera);
        $html .= '<table class="gpdf-tabla">
            <thead>
                <tr>
                    <th>Cod. Principal</th>
                    <th>Cantidad</th>
                    <th>Descripcion</th>
                    <th>Precio Unitario</th>
                    <th>Descuento</th>
                    <th>Precio Total</th>
                </tr>
            </thead><tbody>';

        foreach ((array) $detalles as $detalle) {
            $html .= '<tr>
                <td>' . gpdf_e(gpdf_valor($detalle, 'codigo_principal', '')) . '</td>
                <td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($detalle, 'cantidad', 0)) . '</td>
                <td>' . gpdf_e(gpdf_valor($detalle, 'descripcion', '')) . '</td>
                <td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($detalle, 'precio_unitario', 0)) . '</td>
                <td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($detalle, 'descuento', 0)) . '</td>
                <td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($detalle, 'precio_total_sin_impuesto', 0)) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
        $html .= gpdf_bloque_pagos($pagos);
        $html .= '<table class="gpdf-tabla gpdf-totales">
            <tr><td>SUBTOTAL 12%</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'subtotal_iva', 0)) . '</td></tr>
            <tr><td>SUBTOTAL 0%</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'subtotal_0', 0)) . '</td></tr>
            <tr><td>SUBTOTAL NO OBJETO DE IVA</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'subtotal_no_objeto_iva', 0)) . '</td></tr>
            <tr><td>SUBTOTAL EXENTO DE IVA</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'subtotal_exento_iva', 0)) . '</td></tr>
            <tr><td>SUBTOTAL SIN IMPUESTOS</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'total_sin_impuestos', 0)) . '</td></tr>
            <tr><td>TOTAL DESCUENTO</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'total_descuento', 0)) . '</td></tr>
            <tr><td>IVA 12%</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'iva', 0)) . '</td></tr>
            <tr><td>VALOR TOTAL</td><td class="gpdf-derecha">' . gpdf_moneda(gpdf_valor($cabecera, 'importe_total', 0)) . '</td></tr>
        </table><div class="gpdf-limpiar"></div>';
        $html .= gpdf_info_adicional($infoAdicional);

        return $html;
    }
}

if (!function_exists('gpdf_bloque_pagos')) {
    function gpdf_bloque_pagos($pagos)
    {
        $html = '<table class="gpdf-tabla gpdf-pagos"><tr><th>Forma de pago</th><th>Valor</th></tr>';

        if (!$pagos) {
            $html .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>';
        }

        foreach ((array) $pagos as $pago) {
            $codigo      = gpdf_valor($pago, 'forma_pago', gpdf_valor($pago, 'codigo', ''));
            $descripcion = gpdf_valor($pago, 'descripcion', gpdf_valor($pago, 'forma_pago_descripcion', ''));
            $total       = gpdf_valor($pago, 'total', gpdf_valor($pago, 'valor', 0));
            $html .= '<tr><td>' . gpdf_e(trim($codigo . ' - ' . $descripcion, ' -')) . '</td><td class="gpdf-derecha">' . gpdf_moneda($total) . '</td></tr>';
        }

        return $html . '</table>';
    }
}

if (!function_exists('gpdf_info_adicional')) {
    function gpdf_info_adicional($infoAdicional)
    {
        if (!$infoAdicional) {
            return '';
        }

        $html = '<table class="gpdf-tabla gpdf-info"><tr><th colspan="2">Informacion Adicional</th></tr>';
        foreach ((array) $infoAdicional as $item) {
            $html .= '<tr><td>' . gpdf_e(gpdf_valor($item, 'nombre', '')) . '</td><td>' . gpdf_e(gpdf_valor($item, 'valor', '')) . '</td></tr>';
        }

        return $html . '</table>';
    }
}
