<?php

declare(strict_types=1);

require __DIR__ . '/../src/Nucleo/Configuracion.php';
require __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';
require __DIR__ . '/../src/Modelos/EmpresaModelo.php';

use Intesis\Modelos\EmpresaModelo;
use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$conexion = new ConexionBaseDatos($configuracion);
$pdo = $conexion->obtener();
$modelo = new EmpresaModelo($conexion);

$empresas = $pdo->query("
    SELECT e.sis_empresa_id
    FROM sis_empresa e
    INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
    WHERE es.sis_estado_codigo <> 'ELIMINADO'
    ORDER BY e.sis_empresa_id
")->fetchAll();

if (count($empresas) < 1) {
    echo "EMPRESAS_PROPIA_SIN_DATOS\n";
    exit;
}

$empresaId = (int) $empresas[0]['sis_empresa_id'];
$filtradas = $modelo->listar($empresaId);
$globales = $modelo->listar(null);

$ok = count($filtradas) === 1
    && (int) $filtradas[0]['sis_empresa_id'] === $empresaId
    && count($globales) >= count($filtradas);

echo $ok ? "EMPRESAS_PROPIA_OK\n" : "EMPRESAS_PROPIA_ERROR\n";
