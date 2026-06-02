<?php

/******************************************************************************/
/*                                                                            */
/*  VISOR DE XML CRUDO / JSON                                                 */
/*                                                                            */
/******************************************************************************/

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$tipo = UtilidadesSistema::postTexto('tipo', '');
if ($tipo === '') {
    $tipo = isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : '';
}
$archivo = isset($_GET['archivo']) ? basename((string)$_GET['archivo']) : '';

$mapa = [
    'generado' => $parametros['rutas']['xml_generados'],
    'firmado' => $parametros['rutas']['xml_firmados'],
    'autorizado' => $parametros['rutas']['xml_autorizados'],
    'respuestas' => $parametros['rutas']['respuestas'],
];

if (!isset($mapa[$tipo]) || $archivo === '') {
    http_response_code(400);
    echo 'Solicitud invalida';
    exit;
}

$ruta = $mapa[$tipo] . DIRECTORY_SEPARATOR . $archivo;
if (!file_exists($ruta)) {
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

$ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
if ($ext === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
} else {
    header('Content-Type: application/xml; charset=UTF-8');
}

readfile($ruta);
