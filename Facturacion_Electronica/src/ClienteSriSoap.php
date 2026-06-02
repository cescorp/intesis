<?php

/******************************************************************************/
/*                                                                            */
/*  CLIENTE SRI SOAP (RECEPCION Y AUTORIZACION)                               */
/*                                                                            */
/******************************************************************************/

class ClienteSriSoap
{
    private string $wsdlRecepcion;
    private string $wsdlAutorizacion;

    public function __construct(string $wsdlRecepcion, string $wsdlAutorizacion)
    {
        $this->wsdlRecepcion = $wsdlRecepcion;
        $this->wsdlAutorizacion = $wsdlAutorizacion;
    }

    /**************************************************************************/
    /*  ENVIA XML FIRMADO AL SERVICIO DE RECEPCION                             */
    /**************************************************************************/
    public function validarComprobante(string $xmlFirmado): object
    {
        $cliente = new SoapClient($this->wsdlRecepcion, [
            'trace' => 1,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'http' => ['protocol_version' => 1.0],
            ]),
        ]);

        $datos = ['xml' => $this->quitarBom($xmlFirmado)];
        $respuesta = $cliente->validarComprobante($datos);

        if (!isset($respuesta->RespuestaRecepcionComprobante)) {
            throw new Exception('Respuesta invalida en recepcion de comprobante.');
        }

        return $respuesta->RespuestaRecepcionComprobante;
    }

    /**************************************************************************/
    /*  CONSULTA AUTORIZACION POR CLAVE DE ACCESO                              */
    /**************************************************************************/
    public function autorizarComprobante(string $claveAcceso): mixed
    {
        $cliente = new SoapClient($this->wsdlAutorizacion, [
            'trace' => 1,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'http' => ['protocol_version' => 1.0],
            ]),
        ]);

        $datos = ['claveAccesoComprobante' => $claveAcceso];
        $respuesta = $cliente->autorizacionComprobante($datos);

        if (!isset($respuesta->RespuestaAutorizacionComprobante)) {
            throw new Exception('Respuesta invalida en autorizacion.');
        }

        $autorizaciones = $respuesta->RespuestaAutorizacionComprobante->autorizaciones ?? null;
        return $autorizaciones->autorizacion ?? null;
    }

    /**************************************************************************/
    /*  QUITA BOM UTF-8 SI EXISTE                                              */
    /**************************************************************************/
    private function quitarBom(string $xml): string
    {
        $bom = pack('CCC', 0xef, 0xbb, 0xbf);
        if (strncmp($xml, $bom, 3) === 0) {
            return substr($xml, 3);
        }
        return $xml;
    }
}
