<?php

/******************************************************************************/
/*                                                                            */
/*  PARAMETROS GENERALES DEL MINI PROYECTO DE FACTURACION ELECTRONICA        */
/*  - SIN BASE DE DATOS                                                       */
/*  - SIN FRAMEWORK                                                           */
/*  - SIN COMPOSER                                                            */
/*                                                                            */
/******************************************************************************/

return [
    'rutas' => [
        'raiz' => dirname(__DIR__),
        'certificados' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certificados',
        'xml_generados' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'xml_generados',
        'xml_firmados' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'xml_firmados',
        'xml_autorizados' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'xml_autorizados',
        'respuestas' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'respuestas',
        'logs' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
        'jar_firma' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'java' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'sri2.jar',
        'java_dist' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'java' . DIRECTORY_SEPARATOR . 'dist',
    ],
    'sri' => [
        '1' => [
            'nombre' => 'PRUEBAS',
            'wsdl_recepcion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'wsdl_autorizacion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
        '2' => [
            'nombre' => 'PRODUCCION',
            'wsdl_recepcion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'wsdl_autorizacion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ],
    'tipos_comprobante' => [
        '01' => 'FACTURA',
        '04' => 'NOTA_CREDITO',
        '05' => 'NOTA_DEBITO',
        '06' => 'GUIA_REMISION',
        '07' => 'RETENCION',
    ],
    'tipos_identificacion' => [
        '04' => 'RUC',
        '05' => 'CEDULA',
        '06' => 'PASAPORTE',
        '07' => 'CONSUMIDOR_FINAL',
    ],
    'porcentajes_iva' => [
        '0' => '0',
        '2' => '12',
        '3' => '14',
        '4' => '15',
    ],
];
