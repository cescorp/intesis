<?php

declare(strict_types=1);

require __DIR__ . '/../src/Nucleo/Configuracion.php';
require __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';

use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$pdo = $conexion->obtener();

$sentencia = $pdo->query("
    SELECT p.sis_perfil_id, p.sis_empresa_id, p.sis_perfil_codigo, e.sis_empresa_ruc
    FROM sis_perfil p
    INNER JOIN sis_empresa e ON e.sis_empresa_id = p.sis_empresa_id
    INNER JOIN sis_perfil_permisos pp ON pp.sis_perfil_id = p.sis_perfil_id
    INNER JOIN sis_menu m ON m.sis_menu_id = pp.sis_menu_id
    WHERE p.sis_perfil_codigo <> 'SUPERUSUARIO'
      AND pp.sis_perfil_permisos_estado = 1
      AND m.sis_menu_url = '/sistema/empresas/ver'
    ORDER BY p.sis_empresa_id, p.sis_perfil_id
    LIMIT 1
");
$perfil = $sentencia->fetch();

if (!$perfil) {
    echo "EMPRESAS_USUARIO_NORMAL_SIN_PERMISO\n";
    exit;
}

$otras = $pdo->prepare("
    SELECT e.sis_empresa_ruc
    FROM sis_empresa e
    INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
    WHERE es.sis_estado_codigo <> 'ELIMINADO'
      AND e.sis_empresa_id <> :empresa_id
");
$otras->execute(['empresa_id' => (int) $perfil['sis_empresa_id']]);
$rucsOtras = array_column($otras->fetchAll(), 'sis_empresa_ruc');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/intesis/sistema/empresas';
$_SERVER['SCRIPT_NAME'] = '/intesis/index.php';

session_save_path(__DIR__ . '/../almacenamiento/sesiones');
session_name('INTESIS_SESION');
session_start();
$_SESSION['usuario'] = [
    'id' => 999,
    'empresa_id' => (int) $perfil['sis_empresa_id'],
    'perfil_id' => (int) $perfil['sis_perfil_id'],
    'nombre' => 'USUARIO NORMAL',
    'correo' => 'normal@intesis.local',
    'perfil' => $perfil['sis_perfil_codigo'],
    'perfil_codigo' => $perfil['sis_perfil_codigo'],
    'empresa' => 'EMPRESA PRUEBA',
];

ob_start();
require __DIR__ . '/../index.php';
$contenido = ob_get_clean();

$ok = str_contains($contenido, (string) $perfil['sis_empresa_ruc']);
foreach ($rucsOtras as $ruc) {
    $ok = $ok && !str_contains($contenido, (string) $ruc);
}

echo $ok ? "EMPRESAS_USUARIO_NORMAL_OK\n" : "EMPRESAS_USUARIO_NORMAL_ERROR\n";
