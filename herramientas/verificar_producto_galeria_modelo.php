<?php

declare(strict_types=1);

require __DIR__ . '/../src/Nucleo/Configuracion.php';
require __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require __DIR__ . '/../src/Modelos/ArchivoProductoModelo.php';

use Intesis\Modelos\ArchivoProductoModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$pdo = $conexion->obtener();
$modelo = new ArchivoProductoModelo($conexion);

$empresaId = (int) $pdo->query('SELECT sis_empresa_id FROM sis_empresa ORDER BY sis_empresa_id LIMIT 1')->fetchColumn();
$estadoProductoId = (int) $pdo->query("SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='INVENTARIO' AND sis_estado_entidad='INV_PRODUCTO' AND sis_estado_codigo='ACTIVO' LIMIT 1")->fetchColumn();
$estadoCategoriaId = (int) $pdo->query("SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='INVENTARIO' AND sis_estado_entidad='INV_CATEGORIA' AND sis_estado_codigo='ACTIVO' LIMIT 1")->fetchColumn();
$estadoMarcaId = (int) $pdo->query("SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='INVENTARIO' AND sis_estado_entidad='INV_MARCA' AND sis_estado_codigo='ACTIVO' LIMIT 1")->fetchColumn();

if ($empresaId <= 0 || $estadoProductoId <= 0 || $estadoCategoriaId <= 0 || $estadoMarcaId <= 0) {
    echo "PRODUCTO_GALERIA_SIN_DATOS\n";
    exit;
}

$ids = [];
$categoriaId = null;
$marcaId = null;
$productoId = null;
try {
    $codigo = 'TMP-GAL-' . time();
    $sentencia = $pdo->prepare("INSERT INTO inv_categoria (sis_empresa_id, inv_categoria_nombre, inv_categoria_descripcion, sis_estado_id, usuario_crea) VALUES (:empresa_id, :nombre, '', :estado_id, 1) RETURNING inv_categoria_id");
    $sentencia->execute(['empresa_id' => $empresaId, 'nombre' => $codigo . '-CAT', 'estado_id' => $estadoCategoriaId]);
    $categoriaId = (int) $sentencia->fetchColumn();

    $sentencia = $pdo->prepare("INSERT INTO inv_marca (sis_empresa_id, inv_marca_nombre, sis_estado_id, usuario_crea) VALUES (:empresa_id, :nombre, :estado_id, 1) RETURNING inv_marca_id");
    $sentencia->execute(['empresa_id' => $empresaId, 'nombre' => $codigo . '-MAR', 'estado_id' => $estadoMarcaId]);
    $marcaId = (int) $sentencia->fetchColumn();

    $sentencia = $pdo->prepare("
        INSERT INTO inv_producto (
            sis_empresa_id, inv_categoria_id, inv_marca_id,
            inv_producto_codigo_principal, inv_producto_codigo_auxiliar,
            inv_producto_nombre, inv_producto_descripcion,
            inv_producto_unidad_medida, inv_producto_lleva_iva,
            inv_producto_costo_promedio, inv_producto_costo_ultimo,
            inv_producto_stock_minimo, inv_producto_stock_maximo,
            sis_estado_id, usuario_crea
        )
        VALUES (:empresa_id, :categoria_id, :marca_id, :codigo, '', 'TEMP GALERIA', '', 'UND', 1, 0, 0, 0, 0, :estado_id, 1)
        RETURNING inv_producto_id
    ");
    $sentencia->execute([
        'empresa_id' => $empresaId,
        'categoria_id' => $categoriaId,
        'marca_id' => $marcaId,
        'codigo' => $codigo,
        'estado_id' => $estadoProductoId,
    ]);
    $productoId = (int) $sentencia->fetchColumn();

    $ids[] = $modelo->registrarImagen($empresaId, $productoId, 'tmp_1.jpg', 'almacenamiento/archivos/productos/empresa_' . $empresaId . '/tmp_1.jpg', 1);
    $ids[] = $modelo->registrarImagen($empresaId, $productoId, 'tmp_2.jpg', 'almacenamiento/archivos/productos/empresa_' . $empresaId . '/tmp_2.jpg', 1);
    $imagenes = $modelo->listarPorProducto($empresaId, $productoId);
    $principalInicial = array_values(array_filter($imagenes, fn (array $fila): bool => (bool) $fila['sis_archivos_principal']));
    $modelo->marcarPrincipal($ids[1], 1);
    $imagenes = $modelo->listarPorProducto($empresaId, $productoId);
    $principalNueva = array_values(array_filter($imagenes, fn (array $fila): bool => (bool) $fila['sis_archivos_principal']));
    $modelo->eliminarLogico($ids[1], 1);
    $imagenes = $modelo->listarPorProducto($empresaId, $productoId);
    $principalFinal = array_values(array_filter($imagenes, fn (array $fila): bool => (bool) $fila['sis_archivos_principal']));

    $ok = count($principalInicial) === 1
        && (int) $principalNueva[0]['sis_archivos_id'] === $ids[1]
        && count($principalFinal) === 1
        && (int) $principalFinal[0]['sis_archivos_id'] === $ids[0];

    echo $ok ? "PRODUCTO_GALERIA_MODELO_OK\n" : "PRODUCTO_GALERIA_MODELO_ERROR\n";
} finally {
    if ($ids) {
        $sentencia = $pdo->prepare('DELETE FROM sis_archivos WHERE sis_archivos_id = :id');
        foreach ($ids as $id) {
            $sentencia->execute(['id' => $id]);
        }
    }
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
