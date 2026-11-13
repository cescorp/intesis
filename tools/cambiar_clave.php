<?php
/**
 * Cambia la clave de un usuario del sistema (por ejemplo, si se olvido).
 *
 *   php tools/cambiar_clave.php correo@dominio.com
 *
 * La nueva clave se lee de la variable de entorno NUEVA_CLAVE para que no quede
 * en el historial de la consola (ver tools/migrar_bd.md para el comando PowerShell).
 * Usa el mismo hash que la aplicacion (ARGON2ID) para que el login la acepte.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';

use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ConexionBaseDatos;

$correo = trim((string) ($argv[1] ?? ''));
$clave  = (string) getenv('NUEVA_CLAVE');

if ($correo === '') {
    fwrite(STDERR, "Uso: php tools/cambiar_clave.php correo@dominio.com\n");
    exit(1);
}
// Mismo minimo que exige la aplicacion al crear usuarios (migracion 20261002_1000_clave_minimo_4)
if (mb_strlen($clave) < 4) {
    fwrite(STDERR, "La clave (variable NUEVA_CLAVE) debe tener al menos 4 caracteres.\n");
    exit(1);
}

$pdo = (new ConexionBaseDatos(new Configuracion(dirname(__DIR__))))->obtener();
$sentencia = $pdo->prepare('
    UPDATE sis_usuarios
       SET sis_usuarios_password = :clave
     WHERE lower(sis_usuarios_correo) = lower(:correo)
');
$sentencia->execute(['clave' => password_hash($clave, PASSWORD_ARGON2ID), 'correo' => $correo]);

if ($sentencia->rowCount() === 0) {
    fwrite(STDERR, "No existe un usuario con el correo $correo.\n");
    exit(1);
}
echo "Clave actualizada para $correo.\n";
