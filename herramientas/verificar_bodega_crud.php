<?php

declare(strict_types=1);

require __DIR__ . '/../src/Nucleo/Configuracion.php';
require __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require __DIR__ . '/../src/Modelos/BodegaModelo.php';

use Intesis\Modelos\BodegaModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$pdo = $conexion->obtener();
$modelo = new BodegaModelo($conexion);

$empresaId = (int) $pdo->query('SELECT sis_empresa_id FROM sis_empresa ORDER BY sis_empresa_id LIMIT 1')->fetchColumn();
if ($empresaId <= 0) {
    echo "BODEGA_CRUD_SIN_EMPRESA\n";
    exit;
}

$bodegaId = 0;
try {
    $codigo = 'VERIF_' . time();
    $modelo->crear([
        'empresa_id' => $empresaId,
        'codigo' => $codigo,
        'nombre' => 'Bodega verificacion',
        'descripcion' => 'VERIFICACION TEMPORAL',
        'direccion' => 'Direccion temporal',
        'principal' => true,
        'virtual' => false,
    ], 1);

    $bodegaId = (int) $pdo->query("SELECT inv_bodega_id FROM inv_bodega WHERE inv_bodega_codigo = '{$codigo}' LIMIT 1")->fetchColumn();
    $modelo->actualizar($bodegaId, [
        'empresa_id' => $empresaId,
        'codigo' => $codigo,
        'nombre' => 'Bodega verificacion editada',
        'descripcion' => 'VERIFICACION TEMPORAL EDITADA',
        'direccion' => 'Direccion editada',
        'principal' => false,
        'virtual' => true,
    ], 1);
    $modelo->cambiarEstado($bodegaId, 'INACTIVO', 1);
    $modelo->cambiarEstado($bodegaId, 'ACTIVO', 1);

    $bodega = $modelo->buscar($bodegaId);
    $ok = $bodega
        && $bodega['inv_bodega_nombre'] === 'Bodega verificacion editada'
        && (bool) $bodega['inv_bodega_virtual'] === true
        && (bool) $bodega['inv_bodega_es_principal'] === false
        && $modelo->existeCodigo($empresaId, $codigo, null)
        && !$modelo->estaUsada($bodegaId);

    echo $ok ? "BODEGA_CRUD_OK\n" : "BODEGA_CRUD_ERROR\n";
} finally {
    if ($bodegaId > 0) {
        $pdo->prepare('DELETE FROM inv_bodega WHERE inv_bodega_id = :bodega_id')->execute(['bodega_id' => $bodegaId]);
    }
}
