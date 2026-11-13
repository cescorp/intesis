<?php
/**
 * Verificador de migraciones.
 *
 *   php tools/migraciones.php              -> lista las migraciones pendientes
 *   php tools/migraciones.php --aplicar    -> ejecuta las pendientes en orden
 *   php tools/migraciones.php --marcar-todas -> registra todas como aplicadas sin ejecutarlas
 *                                              (solo una vez, en una BD que ya esta al dia)
 *
 * Lo aplicado se guarda en sis_migracion, que viaja dentro del pg_dump.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';

use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ConexionBaseDatos;

$pdo = (new ConexionBaseDatos(new Configuracion(dirname(__DIR__))))->obtener();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS sis_migracion (
        sis_migracion_archivo VARCHAR(200) PRIMARY KEY,
        sis_migracion_fecha   TIMESTAMP NOT NULL DEFAULT now()
    )
");

$archivos  = array_map('basename', glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);
sort($archivos, SORT_STRING);
$aplicadas = $pdo->query('SELECT sis_migracion_archivo FROM sis_migracion')->fetchAll(PDO::FETCH_COLUMN);
$pendientes = array_values(array_diff($archivos, $aplicadas));

$registrar = $pdo->prepare('INSERT INTO sis_migracion (sis_migracion_archivo) VALUES (:a) ON CONFLICT DO NOTHING');
$modo = $argv[1] ?? '';

if ($modo === '--marcar-todas') {
    foreach ($pendientes as $a) {
        $registrar->execute(['a' => $a]);
    }
    echo count($pendientes) . " migraciones marcadas como aplicadas.\n";
    exit(0);
}

if (!$pendientes) {
    echo "OK: la base de datos esta al dia (" . count($archivos) . " migraciones).\n";
    exit(0);
}

echo "FALTAN " . count($pendientes) . " migraciones:\n";
foreach ($pendientes as $a) {
    echo "  - $a\n";
}

if ($modo !== '--aplicar') {
    echo "\nEjecute: php tools/migraciones.php --aplicar\n";
    exit(1);
}

echo "\n";
foreach ($pendientes as $a) {
    echo "Aplicando $a ... ";
    try {
        // Sin transaccion envolvente: varias migraciones traen su propio BEGIN/COMMIT.
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/migrations/' . $a));
        $registrar->execute(['a' => $a]);
        echo "ok\n";
    } catch (Throwable $e) {
        echo "ERROR\n  " . $e->getMessage() . "\nSe detuvo aqui; las siguientes no se aplicaron.\n";
        exit(1);
    }
}
echo "Listo.\n";
