<?php

/******************************************************************************/
/*                                                                            */
/*  BOOTSTRAP BASICO                                                          */
/*                                                                            */
/******************************************************************************/

date_default_timezone_set('America/Guayaquil');

$parametros = require __DIR__ . DIRECTORY_SEPARATOR . 'parametros.php';

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'UtilidadesSistema.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ClaveAccesoSRI.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'GeneradorXmlSRI.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'FirmadorElectronico.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ClienteSriSoap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'SimuladorSRI.php';
