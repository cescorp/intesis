<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/../src/Modelos/MenuModelo.php';

use Intesis\Modelos\MenuModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$conexion = new ConexionBaseDatos(new Configuracion(dirname(__DIR__)));
$pdo = $conexion->obtener();
$modelo = new MenuModelo($conexion);
$menuId = null;
$accionId = null;

try {
    $menuId = $modelo->crear([
        'nombre' => 'TEMP MENU CRUD',
        'padre' => null,
        'icono' => 'bi bi-circle',
        'url' => '/temp/menu-crud',
        'orden' => 99,
        'tipo' => 'M',
        'crear_ver' => true,
    ], 1);

    $sentencia = $pdo->prepare("SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/temp/menu-crud/ver'");
    $sentencia->execute();
    $accionId = (int) $sentencia->fetchColumn();

    $modelo->actualizar($menuId, [
        'nombre' => 'TEMP MENU CRUD EDITADO',
        'padre' => null,
        'icono' => 'bi bi-sliders',
        'url' => '/temp/menu-crud-editado',
        'orden' => 98,
        'tipo' => 'M',
    ], 1);
    $modelo->cambiarEstado($menuId, 0, 1);
    $menu = $modelo->buscar($menuId);

    $sentencia = $pdo->prepare("
        SELECT count(*)
        FROM sis_perfil_permisos pp
        INNER JOIN sis_perfil p ON p.sis_perfil_id = pp.sis_perfil_id
        WHERE pp.sis_menu_id IN (:menu_id, :accion_id)
          AND p.sis_perfil_codigo = 'SUPERUSUARIO'
    ");
    $sentencia->execute([
        'menu_id' => $menuId,
        'accion_id' => $accionId,
    ]);
    $permisosSuperusuario = (int) $sentencia->fetchColumn();

    $ok = $menu
        && $accionId > 0
        && $menu['sis_menu_nombre'] === 'TEMP MENU CRUD EDITADO'
        && (int) $menu['sis_menu_estado'] === 0
        && $permisosSuperusuario >= 2;

    echo $ok ? "MENU_CRUD_OK\n" : "MENU_CRUD_ERROR\n";
} catch (Throwable $excepcion) {
    echo "MENU_CRUD_ERROR\n";
} finally {
    $ids = array_filter([$menuId, $accionId]);
    if ($ids) {
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $sentencia = $pdo->prepare("DELETE FROM sis_perfil_permisos WHERE sis_menu_id IN ({$marcadores})");
        $sentencia->execute(array_values($ids));
        $sentencia = $pdo->prepare("DELETE FROM sis_menu WHERE sis_menu_id IN ({$marcadores})");
        $sentencia->execute(array_values($ids));
    }
}
