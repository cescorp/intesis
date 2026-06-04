<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../src/Nucleo/ConexionBaseDatos.php';

use Intesis\Nucleo\ConexionBaseDatos;
use Intesis\Nucleo\Configuracion;

$pdo = (new ConexionBaseDatos(new Configuracion(dirname(__DIR__))))->obtener();

$sentencia = $pdo->query("
    SELECT count(*)
    FROM sis_empresa e
    INNER JOIN sis_estado es ON es.sis_estado_id = e.sis_estado_id
    WHERE es.sis_estado_codigo = 'ACTIVO'
      AND NOT EXISTS (
          SELECT 1
          FROM sis_perfil p
          WHERE p.sis_empresa_id = e.sis_empresa_id
            AND p.sis_perfil_estado = 1
      )
");

echo (int) $sentencia->fetchColumn() === 0 ? "EMPRESA_PERFILES_OK\n" : "EMPRESA_PERFILES_ERROR\n";
