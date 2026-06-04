<?php

declare(strict_types=1);

namespace Intesis\Nucleo;

final class Vista
{
    public function __construct(private Configuracion $configuracion)
    {
    }

    /**
     * ***************************************************************************
     * * RENDERIZA UNA VISTA PHP CON VARIABLES AISLADAS.
     * ***************************************************************************
     */
    public function renderizar(string $vista, array $datos = []): void
    {
        extract($datos, EXTR_SKIP);
        $configuracion = $this->configuracion;
        require $this->configuracion->raiz() . '/src/Vistas/' . $vista . '.php';
    }
}
