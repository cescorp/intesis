<?php

declare(strict_types=1);

require __DIR__ . '/../src/Nucleo/Configuracion.php';
require __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require __DIR__ . '/../src/Modelos/TipoDocumentoModelo.php';
require __DIR__ . '/../src/Modelos/SecuenciaModelo.php';

use Intesis\Modelos\SecuenciaModelo;
use Intesis\Modelos\TipoDocumentoModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$pdo = $conexion->obtener();
$tipoModelo = new TipoDocumentoModelo($conexion);
$secuenciaModelo = new SecuenciaModelo($conexion);

$pdo->beginTransaction();
try {
    $empresaId = (int) $pdo->query('SELECT sis_empresa_id FROM sis_empresa ORDER BY sis_empresa_id LIMIT 1')->fetchColumn();
    if ($empresaId <= 0) {
        throw new RuntimeException('No existe empresa para verificar secuencias.');
    }

    $tipoModelo->crear([
        'codigo' => 'PRUEBA_SEQ',
        'nombre' => 'Prueba secuencia',
        'modulo' => 'SISTEMA',
        'descripcion' => 'VERIFICACION TEMPORAL',
        'afecta_contabilidad' => false,
        'afecta_inventario' => false,
    ], 1);

    $tipoId = (int) $pdo->query("
        SELECT sis_tipo_documento_id
        FROM sis_tipo_documento
        WHERE sis_tipo_documento_modulo = 'SISTEMA'
          AND sis_tipo_documento_codigo = 'PRUEBA_SEQ'
        LIMIT 1
    ")->fetchColumn();

    $secuenciaModelo->crear([
        'empresa_id' => $empresaId,
        'tipo_documento_id' => $tipoId,
        'establecimiento' => '001',
        'punto_emision' => '002',
        'desde' => 1,
        'actual' => 1,
        'hasta' => 999999999,
        'observacion' => 'VERIFICACION TEMPORAL',
    ], 1);

    $ok = $tipoModelo->existeCodigo('SISTEMA', 'PRUEBA_SEQ', null)
        && $secuenciaModelo->existeClave([
            'empresa_id' => $empresaId,
            'tipo_documento_id' => $tipoId,
            'establecimiento' => '001',
            'punto_emision' => '002',
        ], null)
        && count($secuenciaModelo->listar($empresaId)) > 0;

    echo $ok ? "TIPOS_SECUENCIAS_OK\n" : "TIPOS_SECUENCIAS_ERROR\n";
} finally {
    $pdo->rollBack();
}
