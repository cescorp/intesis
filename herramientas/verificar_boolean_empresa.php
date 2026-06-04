<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';

use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$configuracion = new Configuracion(dirname(__DIR__));
$pdo = (new ConexionBaseDatos($configuracion))->obtener();
$pdo->beginTransaction();

try {
    $estadoId = (int) $pdo->query("
        SELECT sis_estado_id
        FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA'
          AND sis_estado_entidad = 'SIS_EMPRESA'
          AND sis_estado_codigo = 'ACTIVO'
        LIMIT 1
    ")->fetchColumn();

    $sentencia = $pdo->prepare("
        INSERT INTO sis_empresa (
            sis_empresa_ruc,
            sis_empresa_razon_social,
            sis_empresa_nombre_comercial,
            sis_empresa_direccion,
            sis_empresa_telefono,
            sis_empresa_email,
            sis_empresa_obligado_contabilidad,
            sis_empresa_contribuyente_especial,
            sis_estado_id,
            usuario_crea
        )
        VALUES (
            '1790012345001',
            'PRUEBA BOOLEAN',
            'PRUEBA BOOLEAN',
            'DIRECCION PRUEBA',
            '',
            '',
            :obligado_contabilidad,
            :contribuyente_especial,
            :estado_id,
            1
        )
    ");
    $sentencia->bindValue(':obligado_contabilidad', false, PDO::PARAM_BOOL);
    $sentencia->bindValue(':contribuyente_especial', false, PDO::PARAM_BOOL);
    $sentencia->bindValue(':estado_id', $estadoId, PDO::PARAM_INT);
    $sentencia->execute();
    echo "BOOLEAN_EMPRESA_OK\n";
} finally {
    $pdo->rollBack();
}
