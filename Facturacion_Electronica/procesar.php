<?php

/******************************************************************************/
/*                                                                            */
/*  PROCESO COMPLETO SRI                                                      */
/*  - GENERA XML SEGUN ESTRUCTURA PROYECTO                                    */
/*  - FIRMA                                                                    */
/*  - ENVIA RECEPCION / AUTORIZACION                                           */
/*  - VALIDA XML REAL (ANTI TEXTO PLANO)                                      */
/*                                                                            */
/******************************************************************************/

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

UtilidadesSistema::asegurarDirectorios([
    $parametros['rutas']['xml_generados'],
    $parametros['rutas']['xml_firmados'],
    $parametros['rutas']['xml_autorizados'],
    $parametros['rutas']['respuestas'],
    $parametros['rutas']['logs'],
]);

$errores = [];
$resultado = [];

try {
    $ambiente = UtilidadesSistema::postTexto('ambiente', '1');
    $modoEnvio = UtilidadesSistema::postTexto('modo_envio', 'simulacion');
    $codigoComprobante = UtilidadesSistema::postTexto('codigo_comprobante', '01');

    if (!isset($parametros['tipos_comprobante'][$codigoComprobante])) {
        throw new Exception('Tipo de comprobante no valido.');
    }

    $fechaEmision = UtilidadesSistema::fechaEmisionNormalizada(UtilidadesSistema::postTexto('fecha_emision', date('Y-m-d')));
    $fechaDoc = UtilidadesSistema::fechaEmisionNormalizada(UtilidadesSistema::postTexto('fecha_doc_modificado', date('Y-m-d')));

    $cab = [
        'ambiente' => $ambiente,
        'codigo_comprobante' => $codigoComprobante,
        'ruc_emisor' => UtilidadesSistema::soloNumeros(UtilidadesSistema::postTexto('ruc_emisor')),
        'razon_social_emisor' => UtilidadesSistema::postTexto('razon_social_emisor'),
        'nombre_comercial' => UtilidadesSistema::postTexto('nombre_comercial'),
        'dir_matriz' => UtilidadesSistema::postTexto('dir_matriz'),
        'dir_establecimiento' => UtilidadesSistema::postTexto('dir_establecimiento'),
        'contribuyente_especial' => UtilidadesSistema::postTexto('contribuyente_especial'),
        'obligado_contabilidad' => UtilidadesSistema::postTexto('obligado_contabilidad', 'SI'),
        'estab' => str_pad(UtilidadesSistema::soloNumeros(UtilidadesSistema::postTexto('estab', '001')), 3, '0', STR_PAD_LEFT),
        'pto_emi' => str_pad(UtilidadesSistema::soloNumeros(UtilidadesSistema::postTexto('pto_emi', '001')), 3, '0', STR_PAD_LEFT),
        'secuencial' => str_pad(UtilidadesSistema::soloNumeros(UtilidadesSistema::postTexto('secuencial', '1')), 9, '0', STR_PAD_LEFT),
        'fecha_emision' => $fechaEmision,
        'tipo_id_comprador' => UtilidadesSistema::postTexto('tipo_id_comprador', '05'),
        'id_comprador' => UtilidadesSistema::soloNumeros(UtilidadesSistema::postTexto('id_comprador')),
        'razon_social_comprador' => UtilidadesSistema::postTexto('razon_social_comprador'),
        'direccion_comprador' => UtilidadesSistema::postTexto('direccion_comprador'),
        'telefono_comprador' => UtilidadesSistema::postTexto('telefono_comprador'),
        'email_comprador' => UtilidadesSistema::postTexto('email_comprador'),
        'documento_modificado' => UtilidadesSistema::postTexto('documento_modificado', '001-001-000000001'),
        'fecha_doc_modificado' => $fechaDoc,
        'motivo' => UtilidadesSistema::postTexto('motivo', 'SIN MOTIVO'),
        'total_sin_impuestos' => UtilidadesSistema::postTexto('total_sin_impuestos', '0'),
        'total_descuento' => UtilidadesSistema::postTexto('total_descuento', '0'),
        'base_imponible_iva' => UtilidadesSistema::postTexto('base_imponible_iva', '0'),
        'porcentaje_iva' => UtilidadesSistema::postTexto('porcentaje_iva', '0'),
        'valor_iva' => UtilidadesSistema::postTexto('valor_iva', '0'),
        'importe_total' => UtilidadesSistema::postTexto('importe_total', '0'),
        'forma_pago' => UtilidadesSistema::postTexto('forma_pago', '20'),
        'plazo_pago_dias' => UtilidadesSistema::postTexto('plazo_pago_dias', '0'),
        'dir_partida' => UtilidadesSistema::postTexto('dir_partida', 'BODEGA'),
        'razon_social_transportista' => UtilidadesSistema::postTexto('razon_social_transportista', 'TRANSPORTISTA'),
        'tipo_id_transportista' => UtilidadesSistema::postTexto('tipo_id_transportista', '04'),
        'ruc_transportista' => UtilidadesSistema::soloNumeros(UtilidadesSistema::postTexto('ruc_transportista', '1791111111001')),
        'fecha_inicio_transporte' => UtilidadesSistema::fechaEmisionNormalizada(UtilidadesSistema::postTexto('fecha_inicio_transporte', date('Y-m-d'))),
        'fecha_fin_transporte' => UtilidadesSistema::fechaEmisionNormalizada(UtilidadesSistema::postTexto('fecha_fin_transporte', date('Y-m-d'))),
        'placa' => UtilidadesSistema::postTexto('placa', 'ABC1234'),
        'ruta' => UtilidadesSistema::postTexto('ruta', ''),
        'doc_aduanero' => UtilidadesSistema::postTexto('doc_aduanero', ''),
        'cod_estab_destino' => UtilidadesSistema::postTexto('cod_estab_destino', ''),
        'direccion_destinatario' => UtilidadesSistema::postTexto('direccion_comprador', ''),
        'periodo_fiscal' => UtilidadesSistema::postTexto('periodo_fiscal', date('m/Y')),
        'num_aut_doc_sustento' => UtilidadesSistema::postTexto('num_aut_doc_sustento', ''),
    ];

    if ($cab['ruc_emisor'] === '' || $cab['razon_social_emisor'] === '' || $cab['id_comprador'] === '') {
        throw new Exception('Datos minimos faltantes (emisor/receptor).');
    }

    $cab['clave_acceso'] = ClaveAccesoSRI::generar(
        $cab['fecha_emision'],
        $cab['codigo_comprobante'],
        $cab['ruc_emisor'],
        $cab['ambiente'],
        $cab['estab'],
        $cab['pto_emi'],
        $cab['secuencial']
    );

    $detalles = construirDetallesDesdePost();
    $retenciones = construirRetencionesDesdePost();

    if (in_array($codigoComprobante, ['01', '04', '06'], true) && count($detalles) === 0) {
        throw new Exception('Debes enviar al menos una linea de detalle para este comprobante.');
    }

    if ($codigoComprobante === '07' && count($retenciones) === 0) {
        throw new Exception('Debes enviar al menos una linea de retencion para comprobante 07.');
    }

    $nombreXml = $cab['clave_acceso'] . '.xml';
    $rutaXmlGenerado = $parametros['rutas']['xml_generados'] . DIRECTORY_SEPARATOR . $nombreXml;
    $rutaXmlFirmado = $parametros['rutas']['xml_firmados'] . DIRECTORY_SEPARATOR . $nombreXml;
    $rutaXmlAutorizado = $parametros['rutas']['xml_autorizados'] . DIRECTORY_SEPARATOR . $nombreXml;

    $xmlGenerado = GeneradorXmlSRI::generarSegunTipo($cab, $detalles, $retenciones);
    UtilidadesSistema::guardarArchivo($rutaXmlGenerado, $xmlGenerado);
    validarContenidoXmlArchivo($rutaXmlGenerado, 'XML generado');

    $respuestaRecepcion = null;
    $respuestaAutorizacion = null;

    if ($modoEnvio === 'simulacion') {
        copy($rutaXmlGenerado, $rutaXmlFirmado);
        copy($rutaXmlGenerado, $rutaXmlAutorizado);

        validarContenidoXmlArchivo($rutaXmlFirmado, 'XML firmado (simulacion)');
        validarContenidoXmlArchivo($rutaXmlAutorizado, 'XML autorizado (simulacion)');

        $respuestaRecepcion = SimuladorSRI::simularRecepcion($cab['clave_acceso']);
        $respuestaAutorizacion = SimuladorSRI::simularAutorizacion($cab['clave_acceso'], $xmlGenerado);
    }

    if ($modoEnvio === 'real') {
        if (!isset($_FILES['archivo_p12']) || $_FILES['archivo_p12']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('En modo REAL debes subir .p12');
        }
        $claveCertificado = UtilidadesSistema::postTexto('clave_certificado');
        if ($claveCertificado === '') {
            throw new Exception('Falta clave de certificado.');
        }

        $ext = strtolower(pathinfo($_FILES['archivo_p12']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'p12') {
            throw new Exception('El archivo no es .p12');
        }

        $rutaCertificado = __DIR__ . DIRECTORY_SEPARATOR . 'certificado_actual.p12';
        if (!move_uploaded_file($_FILES['archivo_p12']['tmp_name'], $rutaCertificado)) {
            throw new Exception('No se pudo guardar certificado_actual.p12');
        }

        $firmador = new FirmadorElectronico($parametros['rutas']['jar_firma'], $parametros['rutas']['xml_firmados']);
        $infoFirma = $firmador->firmarXml($rutaCertificado, $claveCertificado, $rutaXmlGenerado, $nombreXml);

        validarContenidoXmlArchivo($rutaXmlFirmado, 'XML firmado');

        $xmlFirmado = UtilidadesSistema::cargarArchivo($rutaXmlFirmado);

        $wsdlRecepcion = $parametros['sri'][$ambiente]['wsdl_recepcion'] ?? '';
        $wsdlAutorizacion = $parametros['sri'][$ambiente]['wsdl_autorizacion'] ?? '';
        $clienteSri = new ClienteSriSoap($wsdlRecepcion, $wsdlAutorizacion);

        $respuestaRecepcion = $clienteSri->validarComprobante($xmlFirmado);
        if (($respuestaRecepcion->estado ?? '') !== 'RECIBIDA') {
            throw new Exception('SRI recepcion estado: ' . ($respuestaRecepcion->estado ?? 'SIN_ESTADO'));
        }

        $respuestaAutorizacion = $clienteSri->autorizarComprobante($cab['clave_acceso']);

        if (is_array($respuestaAutorizacion)) {
            $respuestaAutorizacion = $respuestaAutorizacion[0] ?? null;
        }

        if (!$respuestaAutorizacion) {
            throw new Exception('SRI no devolvio autorizacion.');
        }

        $estadoAut = $respuestaAutorizacion->estado ?? '';
        if ($estadoAut !== 'AUTORIZADO') {
            throw new Exception('SRI autorizacion estado: ' . $estadoAut);
        }

        $xmlAutorizado = isset($respuestaAutorizacion->comprobante)
            ? html_entity_decode((string)$respuestaAutorizacion->comprobante)
            : $xmlFirmado;

        UtilidadesSistema::guardarArchivo($rutaXmlAutorizado, $xmlAutorizado);
        validarContenidoXmlArchivo($rutaXmlAutorizado, 'XML autorizado');

        UtilidadesSistema::registrarLog(
            $parametros['rutas']['logs'] . DIRECTORY_SEPARATOR . 'firma.log',
            'Firma OK ' . json_encode($infoFirma)
        );
    }

    $archivoJson = $parametros['rutas']['respuestas'] . DIRECTORY_SEPARATOR . $cab['clave_acceso'] . '.json';
    UtilidadesSistema::guardarArchivo($archivoJson, json_encode([
        'fecha' => date('Y-m-d H:i:s'),
        'modo' => $modoEnvio,
        'ambiente' => $ambiente,
        'tipo' => $codigoComprobante,
        'clave_acceso' => $cab['clave_acceso'],
        'recepcion' => $respuestaRecepcion,
        'autorizacion' => $respuestaAutorizacion,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $resultado = [
        'ok' => true,
        'clave' => $cab['clave_acceso'],
        'tipo' => $parametros['tipos_comprobante'][$codigoComprobante],
        'modo' => strtoupper($modoEnvio),
        'ambiente' => $parametros['sri'][$ambiente]['nombre'],
        'xml_generado' => $nombreXml,
        'xml_firmado' => $nombreXml,
        'xml_autorizado' => $nombreXml,
        'respuesta_json' => basename($archivoJson),
    ];
} catch (Throwable $e) {
    $errores[] = $e->getMessage();
    UtilidadesSistema::registrarLog($parametros['rutas']['logs'] . DIRECTORY_SEPARATOR . 'errores.log', $e->getMessage());
}

function construirDetallesDesdePost(): array
{
    $codigos = $_POST['detalle_codigo'] ?? [];
    $aux = $_POST['detalle_codigo_aux'] ?? [];
    $desc = $_POST['detalle_descripcion'] ?? [];
    $cant = $_POST['detalle_cantidad'] ?? [];
    $precio = $_POST['detalle_precio'] ?? [];
    $descuento = $_POST['detalle_descuento'] ?? [];
    $ivaPct = $_POST['detalle_iva_pct'] ?? [];
    $marca = $_POST['detalle_marca'] ?? [];

    $out = [];
    $max = count($codigos);
    for ($i = 0; $i < $max; $i++) {
        $codigo = trim((string)($codigos[$i] ?? ''));
        $descripcion = trim((string)($desc[$i] ?? ''));
        if ($codigo === '' && $descripcion === '') {
            continue;
        }

        $cantidad = (float)($cant[$i] ?? 0);
        $punit = (float)($precio[$i] ?? 0);
        $descVal = (float)($descuento[$i] ?? 0);
        $iva = (float)($ivaPct[$i] ?? 0);
        $subtotal = max(0, ($cantidad * $punit) - $descVal);
        $ivaValor = $subtotal * $iva / 100;

        $out[] = [
            'codigo' => $codigo !== '' ? $codigo : 'ITEM' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
            'codigo_auxiliar' => trim((string)($aux[$i] ?? '')),
            'descripcion' => $descripcion !== '' ? $descripcion : 'SIN DESCRIPCION',
            'cantidad' => $cantidad,
            'precio_unitario' => $punit,
            'descuento' => $descVal,
            'subtotal' => $subtotal,
            'iva_pct' => $iva,
            'iva_valor' => $ivaValor,
            'marca' => trim((string)($marca[$i] ?? '')),
        ];
    }

    return $out;
}

function construirRetencionesDesdePost(): array
{
    $cod = $_POST['ret_codigo'] ?? [];
    $codRet = $_POST['ret_codigo_retencion'] ?? [];
    $base = $_POST['ret_base'] ?? [];
    $porc = $_POST['ret_porcentaje'] ?? [];
    $valor = $_POST['ret_valor'] ?? [];

    $out = [];
    $max = count($cod);
    for ($i = 0; $i < $max; $i++) {
        $codigo = trim((string)($cod[$i] ?? ''));
        $codigoRet = trim((string)($codRet[$i] ?? ''));
        if ($codigo === '' || $codigoRet === '') {
            continue;
        }

        $out[] = [
            'codigo' => $codigo,
            'codigo_retencion' => $codigoRet,
            'base' => (float)($base[$i] ?? 0),
            'porcentaje' => (float)($porc[$i] ?? 0),
            'valor' => (float)($valor[$i] ?? 0),
        ];
    }

    return $out;
}

function validarContenidoXmlArchivo(string $ruta, string $etiqueta): void
{
    if (!file_exists($ruta)) {
        throw new Exception($etiqueta . ': archivo no encontrado.');
    }

    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        throw new Exception($etiqueta . ': no se pudo leer archivo.');
    }

    if (substr_count($contenido, '<') < 4 || stripos($contenido, '<?xml') === false) {
        throw new Exception($etiqueta . ': parece texto plano, no XML valido. Inicio: ' . substr($contenido, 0, 180));
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $ok = $dom->loadXML($contenido);
    if (!$ok) {
        $errors = libxml_get_errors();
        $msg = isset($errors[0]) ? trim($errors[0]->message) : 'Error XML desconocido';
        libxml_clear_errors();
        throw new Exception($etiqueta . ': XML mal formado. ' . $msg);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Resultado</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f5f5f5;margin:18px}
    .caja{background:#fff;border:1px solid #ddd;border-radius:6px;padding:14px;margin-bottom:12px}
    .ok{border-left:6px solid #198754}
    .er{border-left:6px solid #dc3545}
    .mono{font-family:Consolas,monospace;background:#efefef;padding:2px 6px;border-radius:4px}
  </style>
</head>
<body>
<div class="caja"><h2>Resultado del proceso</h2><a href="index.php">Volver</a></div>
<?php if ($errores): ?>
<div class="caja er"><h3>Error</h3><?php foreach($errores as $e){echo '<div>'.htmlspecialchars($e, ENT_QUOTES, 'UTF-8').'</div>';} ?></div>
<?php else: ?>
<div class="caja ok">
  <div><b>Tipo:</b> <?= htmlspecialchars($resultado['tipo'], ENT_QUOTES, 'UTF-8'); ?></div>
  <div><b>Modo:</b> <?= htmlspecialchars($resultado['modo'], ENT_QUOTES, 'UTF-8'); ?></div>
  <div><b>Ambiente:</b> <?= htmlspecialchars($resultado['ambiente'], ENT_QUOTES, 'UTF-8'); ?></div>
  <div><b>Clave:</b> <span class="mono"><?= htmlspecialchars($resultado['clave'], ENT_QUOTES, 'UTF-8'); ?></span></div>
  <div style="margin-top:10px"><b>Ver XML crudo</b></div>
  <div><a target="_blank" href="ver_xml.php?tipo=generado&archivo=<?= urlencode($resultado['xml_generado']); ?>">XML generado</a></div>
  <div><a target="_blank" href="ver_xml.php?tipo=firmado&archivo=<?= urlencode($resultado['xml_firmado']); ?>">XML firmado</a></div>
  <div><a target="_blank" href="ver_xml.php?tipo=autorizado&archivo=<?= urlencode($resultado['xml_autorizado']); ?>">XML autorizado</a></div>
  <div><a target="_blank" href="ver_xml.php?tipo=respuestas&archivo=<?= urlencode($resultado['respuesta_json']); ?>">Respuesta JSON</a></div>
</div>
<?php endif; ?>
</body>
</html>
