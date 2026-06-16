<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use RuntimeException;
use SoapClient;
use SoapFault;

final class SriSoapServicio
{
    private const WSDL_RECEPCION = [
        '1' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
        '2' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
    ];

    private const WSDL_AUTORIZACION = [
        '1' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        '2' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
    ];

    /**
     * Envía el XML firmado al servicio de recepción del SRI.
     *
     * @return array ['estado' => 'RECIBIDA'|'DEVUELTA', 'mensajes' => [...]]
     */
    public function enviarRecepcion(string $xmlFirmado, string $ambiente): array
    {
        $wsdl = self::WSDL_RECEPCION[$ambiente] ?? self::WSDL_RECEPCION['1'];

        try {
            $cliente = new SoapClient($wsdl, [
                'trace'          => 1,
                'exceptions'     => true,
                'stream_context' => stream_context_create(['http' => ['protocol_version' => 1.0]]),
                'cache_wsdl'     => WSDL_CACHE_NONE,
            ]);

            $respuesta = $cliente->validarComprobante(['xml' => $this->quitarBom($xmlFirmado)]);
            $rrc = $respuesta->RespuestaRecepcionComprobante ?? null;

            if ($rrc === null) {
                throw new RuntimeException('Respuesta de recepción inválida del SRI.');
            }

            $estado    = (string)($rrc->estado ?? 'DEVUELTA');
            $mensajes  = $this->extraerMensajes($rrc->comprobantes->comprobante->mensajes ?? null);

            return ['estado' => $estado, 'mensajes' => $mensajes];
        } catch (SoapFault $e) {
            throw new RuntimeException('Error SOAP al enviar al SRI: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Consulta la autorización de un comprobante por su clave de acceso.
     *
     * @return array ['estado' => 'AUTORIZADO'|'NO AUTORIZADO', 'numero_autorizacion' => '', 'fecha_autorizacion' => '', 'xml_autorizado' => '', 'mensajes' => [...]]
     */
    public function consultarAutorizacion(string $claveAcceso, string $ambiente): array
    {
        $wsdl = self::WSDL_AUTORIZACION[$ambiente] ?? self::WSDL_AUTORIZACION['1'];

        try {
            $cliente = new SoapClient($wsdl, [
                'trace'          => 1,
                'exceptions'     => true,
                'stream_context' => stream_context_create(['http' => ['protocol_version' => 1.0]]),
                'cache_wsdl'     => WSDL_CACHE_NONE,
            ]);

            $respuesta = $cliente->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);
            $rac  = $respuesta->RespuestaAutorizacionComprobante ?? null;
            $auth = $rac->autorizaciones->autorizacion ?? null;

            if ($auth === null) {
                return [
                    'estado'              => 'NO AUTORIZADO',
                    'numero_autorizacion' => '',
                    'fecha_autorizacion'  => '',
                    'xml_autorizado'      => '',
                    'mensajes'            => ['No se recibió respuesta de autorización.'],
                ];
            }

            $estado   = strtoupper(trim((string)($auth->estado ?? 'NO AUTORIZADO')));
            $mensajes = $this->extraerMensajes($auth->mensajes ?? null);

            return [
                'estado'              => $estado,
                'numero_autorizacion' => (string)($auth->numeroAutorizacion ?? $claveAcceso),
                'fecha_autorizacion'  => (string)($auth->fechaAutorizacion ?? ''),
                'xml_autorizado'      => (string)($auth->comprobante ?? ''),
                'mensajes'            => $mensajes,
            ];
        } catch (SoapFault $e) {
            throw new RuntimeException('Error SOAP al consultar autorización SRI: ' . $e->getMessage(), 0, $e);
        }
    }

    private function quitarBom(string $xml): string
    {
        $bom = pack('CCC', 0xef, 0xbb, 0xbf);
        return strncmp($xml, $bom, 3) === 0 ? substr($xml, 3) : $xml;
    }

    private function extraerMensajes(mixed $nodo): array
    {
        if ($nodo === null) return [];
        $items = is_array($nodo->mensaje ?? null) ? $nodo->mensaje : [$nodo->mensaje ?? null];
        $out   = [];
        foreach ($items as $m) {
            if ($m === null) continue;
            $identificador = (string)($m->identificador ?? '');
            $mensaje       = (string)($m->mensaje       ?? '');
            $informacion   = (string)($m->informacionAdicional ?? '');
            $out[] = trim($identificador . ' ' . $mensaje . ' ' . $informacion);
        }
        return array_filter($out);
    }
}
