<?php

/******************************************************************************/
/*                                                                            */
/*  SIMULADOR LOCAL DE RESPUESTAS SRI                                         */
/*                                                                            */
/******************************************************************************/

class SimuladorSRI
{
    /**************************************************************************/
    /*  SIMULA RECEPCION                                                       */
    /**************************************************************************/
    public static function simularRecepcion(string $claveAcceso): object
    {
        $obj = new stdClass();
        $obj->estado = 'RECIBIDA';
        $obj->claveAcceso = $claveAcceso;
        $obj->mensaje = 'SIMULACION: RECIBIDA';
        return $obj;
    }

    /**************************************************************************/
    /*  SIMULA AUTORIZACION                                                    */
    /**************************************************************************/
    public static function simularAutorizacion(string $claveAcceso, string $xmlFirmado): object
    {
        $obj = new stdClass();
        $obj->estado = 'AUTORIZADO';
        $obj->numeroAutorizacion = $claveAcceso;
        $obj->fechaAutorizacion = date('Y-m-d H:i:s');
        $obj->ambiente = 'SIMULACION';
        $obj->comprobante = $xmlFirmado;
        return $obj;
    }
}
