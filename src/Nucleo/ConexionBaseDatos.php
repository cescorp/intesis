<?php

declare(strict_types=1);

namespace Intesis\Nucleo;

use PDO;

final class ConexionBaseDatos
{
    private ?PDO $conexion = null;

    public function __construct(private Configuracion $configuracion)
    {
    }

    /**
     * ***************************************************************************
     * * CREA Y REUTILIZA LA CONEXION PDO HACIA POSTGRESQL.
     * ***************************************************************************
     */
    public function obtener(): PDO
    {
        if ($this->conexion instanceof PDO) {
            return $this->conexion;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $this->configuracion->obtener('DB_HOST', '127.0.0.1'),
            $this->configuracion->obtener('DB_PORT', '5432'),
            $this->configuracion->obtener('DB_DATABASE', 'intesis')
        );

        $this->conexion = new PDO(
            $dsn,
            $this->configuracion->obtener('DB_USERNAME', 'postgres'),
            $this->configuracion->obtener('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $this->conexion;
    }
}
