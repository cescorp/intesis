<?php

declare(strict_types=1);

require __DIR__ . '/../src/Nucleo/Configuracion.php';
require __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require __DIR__ . '/../src/Modelos/CategoriaModelo.php';
require __DIR__ . '/../src/Modelos/MarcaModelo.php';
require __DIR__ . '/../src/Modelos/ProductoModelo.php';

use Intesis\Modelos\CategoriaModelo;
use Intesis\Modelos\MarcaModelo;
use Intesis\Modelos\ProductoModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$pdo = $conexion->obtener();
$categoriaModelo = new CategoriaModelo($conexion);
$marcaModelo = new MarcaModelo($conexion);
$productoModelo = new ProductoModelo($conexion);
$empresaId = (int) $pdo->query('SELECT sis_empresa_id FROM sis_empresa ORDER BY sis_empresa_id LIMIT 1')->fetchColumn();
$codigo = 'TMP-PROD-' . time();
$categoriaId = null;
$marcaId = null;
$productoId = null;

try {
    $categoriaId = $categoriaModelo->crear([
        'empresa_id' => $empresaId,
        'nombre' => 'TEMP CATEGORIA ' . time(),
        'descripcion' => 'Temporal',
    ], 1);
    $marcaId = $marcaModelo->crear([
        'empresa_id' => $empresaId,
        'nombre' => 'TEMP MARCA ' . time(),
    ], 1);
    $productoModelo->crear([
        'empresa_id' => $empresaId,
        'categoria_id' => $categoriaId,
        'marca_id' => $marcaId,
        'codigo_principal' => $codigo,
        'codigo_auxiliar' => $codigo . '-AUX',
        'nombre' => 'TEMP PRODUCTO',
        'descripcion' => 'Temporal',
        'lleva_iva' => 1,
        'costo_ultimo' => '1.25',
        'stock_minimo' => '2',
        'stock_maximo' => '10',
    ], 1);

    $sentencia = $pdo->prepare('SELECT inv_producto_id FROM inv_producto WHERE inv_producto_codigo_principal = :codigo LIMIT 1');
    $sentencia->execute(['codigo' => $codigo]);
    $productoId = (int) $sentencia->fetchColumn();

    $productoModelo->actualizar($productoId, [
        'empresa_id' => $empresaId,
        'categoria_id' => $categoriaId,
        'marca_id' => $marcaId,
        'codigo_principal' => $codigo,
        'codigo_auxiliar' => '',
        'nombre' => 'TEMP PRODUCTO EDITADO',
        'descripcion' => 'Temporal editado',
        'lleva_iva' => 0,
        'costo_ultimo' => '2.50',
        'stock_minimo' => '1',
        'stock_maximo' => '20',
    ], 1);
    $productoModelo->cambiarEstado($productoId, 'INACTIVO', 1);
    $productoModelo->cambiarEstado($productoId, 'ACTIVO', 1);
    $categoriaModelo->cambiarEstado($categoriaId, 'INACTIVO', 1);
    $categoriaModelo->cambiarEstado($categoriaId, 'ACTIVO', 1);
    $marcaModelo->cambiarEstado($marcaId, 'INACTIVO', 1);
    $marcaModelo->cambiarEstado($marcaId, 'ACTIVO', 1);

    $ok = $productoId > 0
        && $categoriaModelo->perteneceEmpresa($categoriaId, $empresaId)
        && $marcaModelo->perteneceEmpresa($marcaId, $empresaId)
        && $productoModelo->existeCodigo($empresaId, $codigo, null);

    echo $ok ? "INVENTARIO_PRODUCTOS_CRUD_OK\n" : "INVENTARIO_PRODUCTOS_CRUD_ERROR\n";
} finally {
    if ($productoId) {
        $pdo->prepare('DELETE FROM inv_producto WHERE inv_producto_id = :id')->execute(['id' => $productoId]);
    }
    if ($categoriaId) {
        $pdo->prepare('DELETE FROM inv_categoria WHERE inv_categoria_id = :id')->execute(['id' => $categoriaId]);
    }
    if ($marcaId) {
        $pdo->prepare('DELETE FROM inv_marca WHERE inv_marca_id = :id')->execute(['id' => $marcaId]);
    }
}
