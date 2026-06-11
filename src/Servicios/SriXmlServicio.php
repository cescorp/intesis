<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use DateTime;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use SimpleXMLElement;

/**
 * Parsea facturas electrónicas del SRI (todas las versiones) y descarga XML vía SOAP.
 * Adaptado del subproyecto importar_sri_leer_xml/servicios/compras/compras_servicio.php
 */
final class SriXmlServicio
{
    // =========================================================================
    // RUTAS DE ALMACENAMIENTO
    // =========================================================================

    /**
     * Retorna rutas absolutas para XML.
     * Ambas rutas (pendientes y procesados) se organizan por RUC de la EMPRESA ACTIVA
     * cuando se proporciona $rucEmpresa. Si no, se usa $rucEmisor como fallback.
     */
    public function rutasXml(string $raiz, string $rucEmisor, string $rucEmpresa = ''): array
    {
        $base   = $raiz . DIRECTORY_SEPARATOR . 'almacenamiento' . DIRECTORY_SEPARATOR
                . 'archivos' . DIRECTORY_SEPARATOR . 'xml';
        $rucEm  = preg_replace('/[^0-9]/', '', $rucEmisor)  ?: 'desconocido';
        $rucEmp = preg_replace('/[^0-9]/', '', $rucEmpresa) ?: $rucEm;
        return [
            'pendientes' => $base . DIRECTORY_SEPARATOR . 'pendientes' . DIRECTORY_SEPARATOR . $rucEmp,
            'procesados' => $base . DIRECTORY_SEPARATOR . 'procesados' . DIRECTORY_SEPARATOR . $rucEmp,
        ];
    }

    /**
     * Retorna rutas absolutas para TXT de la empresa dado su RUC.
     */
    public function rutasTxt(string $raiz, string $rucEmpresa): array
    {
        $base = $raiz . DIRECTORY_SEPARATOR . 'almacenamiento' . DIRECTORY_SEPARATOR
              . 'archivos' . DIRECTORY_SEPARATOR . 'txt';
        $ruc  = preg_replace('/[^0-9]/', '', $rucEmpresa) ?: 'desconocido';
        return [
            'pendientes' => $base . DIRECTORY_SEPARATOR . 'pendientes' . DIRECTORY_SEPARATOR . $ruc,
            'procesados' => $base . DIRECTORY_SEPARATOR . 'procesados' . DIRECTORY_SEPARATOR . $ruc,
        ];
    }

    public function asegurarDirectorio(string $ruta): void
    {
        if (!is_dir($ruta)) {
            mkdir($ruta, 0777, true);
        }
    }

    /**
     * Mueve un archivo a un directorio destino sin colisiones de nombre.
     */
    public function moverArchivo(string $origen, string $dirDestino, string $nombre): string
    {
        $this->asegurarDirectorio($dirDestino);
        $destino = $dirDestino . DIRECTORY_SEPARATOR . $nombre;
        $partes   = pathinfo($destino);
        $contador = 1;
        while (is_file($destino)) {
            $destino = $partes['dirname'] . DIRECTORY_SEPARATOR
                     . $partes['filename'] . '_' . $contador . '.' . ($partes['extension'] ?? '');
            $contador++;
        }
        if (!rename($origen, $destino)) {
            throw new RuntimeException('No se pudo mover archivo: ' . $origen);
        }
        return $destino;
    }

    // =========================================================================
    // PARSER XML
    // =========================================================================

    /**
     * Parsea el contenido de un XML autorizado del SRI.
     * Soporta versiones 1.0, 1.0.0, 1.1.0, 2.0.0, 2.1.0 con wrapper <autorizacion> o sin él.
     * Solo procesa codDoc=01 (Factura). Lanza RuntimeException con prefijo "TIPO_NO_SOPORTADO:" para otros tipos.
     *
     * @throws RuntimeException
     * @return array{
     *   estado_autorizacion: ?string,
     *   numero_autorizacion: ?string,
     *   fecha_autorizacion:  ?string,
     *   ambiente:            ?string,
     *   version_xml:         string,
     *   clave_acceso:        ?string,
     *   ruc_emisor:          ?string,
     *   razon_social:        ?string,
     *   nombre_comercial:    ?string,
     *   estab:               ?string,
     *   pto_emi:             ?string,
     *   secuencial:          ?string,
     *   fecha_emision:       ?string,
     *   total_sin_impuestos: float,
     *   total_descuento:     float,
     *   iva:                 float,
     *   importe_total:       float,
     *   telefono_emisor:     ?string,
     *   correo_emisor:       ?string,
     *   direccion_emisor:    ?string,
     *   detalles:            array
     * }
     */
    public function parsearFactura(string $xmlContenido): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        if (!$dom->loadXML($xmlContenido)) {
            throw new RuntimeException('El archivo XML no es válido.');
        }

        $xpath = new DOMXPath($dom);
        $auth  = ['estado_autorizacion' => null, 'numero_autorizacion' => null,
                  'fecha_autorizacion'  => null, 'ambiente' => null];

        // Detectar wrapper <autorizacion>
        $nodoAut = $xpath->query('//*[local-name()="autorizacion"]')->item(0);
        if ($nodoAut instanceof DOMNode) {
            $auth['estado_autorizacion'] = $this->domTexto($xpath, $nodoAut, './*[local-name()="estado"]');
            $auth['numero_autorizacion'] = $this->domTexto($xpath, $nodoAut, './*[local-name()="numeroAutorizacion"]');
            $auth['fecha_autorizacion']  = $this->fechaAutorizacion(
                $this->domTexto($xpath, $nodoAut, './*[local-name()="fechaAutorizacion"]')
            );
            $auth['ambiente'] = $this->domTexto($xpath, $nodoAut, './*[local-name()="ambiente"]');

            $comprobanteTexto = $this->domTexto($xpath, $nodoAut, './*[local-name()="comprobante"]');
            if ($comprobanteTexto !== null) {
                $domComp = new DOMDocument('1.0', 'UTF-8');
                $domComp->preserveWhiteSpace = false;
                if (!$domComp->loadXML($comprobanteTexto)) {
                    throw new RuntimeException('El comprobante interno del XML no es válido.');
                }
                $dom   = $domComp;
                $xpath = new DOMXPath($dom);
            }
        }

        // Nodos principales
        $factura    = $xpath->query('//*[local-name()="factura"]')->item(0);
        $info       = $xpath->query('//*[local-name()="infoFactura"]')->item(0);
        $tributaria = $xpath->query('//*[local-name()="infoTributaria"]')->item(0);

        if (!$factura instanceof DOMElement || !$info instanceof DOMNode || !$tributaria instanceof DOMNode) {
            // Intentar detectar el tipo para el mensaje
            $codDoc = $this->detectarCodDoc($dom);
            if ($codDoc !== null && $codDoc !== '01') {
                throw new RuntimeException('TIPO_NO_SOPORTADO: ' . $this->nombreTipoDoc($codDoc));
            }
            throw new RuntimeException('Estructura de factura no encontrada en el XML.');
        }

        $codDoc = $this->domTexto($xpath, $tributaria, './*[local-name()="codDoc"]');
        if ($codDoc !== '01') {
            throw new RuntimeException('TIPO_NO_SOPORTADO: ' . $this->nombreTipoDoc($codDoc));
        }

        // IVA total sumando todos los impuestos
        $iva = 0.0;
        $nodosImpTotal = $xpath->query(
            './*[local-name()="totalConImpuestos"]/*[local-name()="totalImpuesto"]', $info
        );
        if ($nodosImpTotal !== false) {
            foreach ($nodosImpTotal as $imp) {
                $iva += (float) ($this->numero($this->domTexto($xpath, $imp, './*[local-name()="valor"]')) ?? 0);
            }
        }

        // Detalles de líneas
        $detalles = [];
        $nodosDetalle = $xpath->query('//*[local-name()="detalles"]/*[local-name()="detalle"]');
        if ($nodosDetalle !== false) {
            foreach ($nodosDetalle as $i => $det) {
                $nodosImp  = $xpath->query('./*[local-name()="impuestos"]/*[local-name()="impuesto"]', $det);
                $primerImp = ($nodosImp !== false && $nodosImp->length > 0) ? $nodosImp->item(0) : null;
                $detalles[] = [
                    'linea'                     => $i + 1,
                    'cod_principal'             => (string) ($this->domTexto($xpath, $det, './*[local-name()="codigoPrincipal"]') ?? ''),
                    'cod_auxiliar'              => (string) ($this->domTexto($xpath, $det, './*[local-name()="codigoAuxiliar"]') ?? ''),
                    'descripcion_sri'           => (string) ($this->domTexto($xpath, $det, './*[local-name()="descripcion"]') ?? ''),
                    'cantidad'                  => (float)  ($this->numero($this->domTexto($xpath, $det, './*[local-name()="cantidad"]')) ?? 1),
                    'precio_unitario'           => (float)  ($this->numero($this->domTexto($xpath, $det, './*[local-name()="precioUnitario"]')) ?? 0),
                    'descuento'                 => (float)  ($this->numero($this->domTexto($xpath, $det, './*[local-name()="descuento"]')) ?? 0),
                    'precio_total_sin_impuesto' => (float)  ($this->numero($this->domTexto($xpath, $det, './*[local-name()="precioTotalSinImpuesto"]')) ?? 0),
                    'tarifa_iva'                => $primerImp ? (float) ($this->numero($this->domTexto($xpath, $primerImp, './*[local-name()="tarifa"]')) ?? 0) : 0.0,
                    'valor_iva'                 => $primerImp ? (float) ($this->numero($this->domTexto($xpath, $primerImp, './*[local-name()="valor"]')) ?? 0) : 0.0,
                ];
            }
        }

        // Datos de contacto del emisor desde infoAdicional (best-effort)
        $contacto = $this->extraerContactoEmisor($xpath);

        $dirMatriz = $this->domTexto($xpath, $tributaria, './*[local-name()="dirMatriz"]');

        // Tipo emisión (código SRI → texto)
        $tipoEmisionCod = $this->domTexto($xpath, $tributaria, './*[local-name()="tipoEmision"]');
        $tipoEmision    = match ($tipoEmisionCod) {
            '1'     => 'NORMAL',
            '2'     => 'INDISPONIBILIDAD DEL SISTEMA',
            default => (string) ($tipoEmisionCod ?? ''),
        };

        // Pagos
        $pagos = [];
        $nodosPago = $xpath->query('//*[local-name()="pagos"]/*[local-name()="pago"]');
        if ($nodosPago !== false) {
            foreach ($nodosPago as $pago) {
                $pagos[] = [
                    'forma_pago' => (string) ($this->domTexto($xpath, $pago, './*[local-name()="formaPago"]') ?? ''),
                    'total'      => (float) ($this->numero($this->domTexto($xpath, $pago, './*[local-name()="total"]')) ?? 0),
                ];
            }
        }

        // Info adicional (todos los campos)
        $infoAdicional = [];
        $nodosInfo = $xpath->query('//*[local-name()="infoAdicional"]/*[local-name()="campoAdicional"]');
        if ($nodosInfo !== false) {
            foreach ($nodosInfo as $campo) {
                $nombre = trim((string) $campo->getAttribute('nombre'));
                $valor  = trim((string) $campo->textContent);
                if ($nombre !== '' || $valor !== '') {
                    $infoAdicional[] = ['nombre' => $nombre, 'valor' => $valor];
                }
            }
        }

        return [
            'estado_autorizacion'   => $auth['estado_autorizacion'],
            'numero_autorizacion'   => $auth['numero_autorizacion'],
            'fecha_autorizacion'    => $auth['fecha_autorizacion'],
            'ambiente'              => $auth['ambiente'],
            'tipo_emision'          => $tipoEmision,
            'version_xml'           => $factura->getAttribute('version') ?: '1.0',
            'clave_acceso'          => $this->domTexto($xpath, $tributaria, './*[local-name()="claveAcceso"]'),
            'ruc_emisor'            => $this->domTexto($xpath, $tributaria, './*[local-name()="ruc"]'),
            'razon_social'          => $this->domTexto($xpath, $tributaria, './*[local-name()="razonSocial"]'),
            'nombre_comercial'      => $this->domTexto($xpath, $tributaria, './*[local-name()="nombreComercial"]'),
            'estab'                 => $this->domTexto($xpath, $tributaria, './*[local-name()="estab"]'),
            'pto_emi'               => $this->domTexto($xpath, $tributaria, './*[local-name()="ptoEmi"]'),
            'secuencial'            => $this->domTexto($xpath, $tributaria, './*[local-name()="secuencial"]'),
            'fecha_emision'         => $this->fechaEmision($this->domTexto($xpath, $info, './*[local-name()="fechaEmision"]')),
            'total_sin_impuestos'   => (float) ($this->numero($this->domTexto($xpath, $info, './*[local-name()="totalSinImpuestos"]')) ?? 0),
            'total_descuento'       => (float) ($this->numero($this->domTexto($xpath, $info, './*[local-name()="totalDescuento"]')) ?? 0),
            'iva'                   => $iva,
            'importe_total'         => (float) ($this->numero($this->domTexto($xpath, $info, './*[local-name()="importeTotal"]')) ?? 0),
            'telefono_emisor'       => $contacto['telefono'],
            'correo_emisor'         => $contacto['correo'],
            'dir_matriz'            => $dirMatriz,
            'direccion_emisor'      => $contacto['direccion'] ?? $dirMatriz,
            'pagos'                 => $pagos,
            'info_adicional'        => $infoAdicional,
            'detalles'              => $detalles,
        ];
    }

    /**
     * Busca en infoAdicional campos de contacto del EMISOR (no del cliente).
     * Ignora campos que claramente corresponden al receptor.
     */
    private function extraerContactoEmisor(DOMXPath $xpath): array
    {
        $resultado = ['telefono' => null, 'correo' => null, 'direccion' => null];
        $nodos = $xpath->query('//*[local-name()="infoAdicional"]/*[local-name()="campoAdicional"]');
        if ($nodos === false) {
            return $resultado;
        }

        foreach ($nodos as $nodo) {
            $nombre = $this->normalizar($nodo->getAttribute('nombre'));
            $valor  = trim((string) $nodo->textContent);
            if ($valor === '' || strlen($valor) < 4) {
                continue;
            }
            // Saltar campos que son del receptor
            if ($this->contieneAlguna($nombre, ['cliente', 'comprador', 'receptor', 'cliente', 'comprad'])) {
                continue;
            }

            if ($resultado['telefono'] === null && $this->contieneAlguna($nombre, ['telefon', 'telf', 'celular', 'movil', 'phone'])) {
                $resultado['telefono'] = substr($valor, 0, 20);
            }
            if ($resultado['correo'] === null && $this->contieneAlguna($nombre, ['email', 'mail', 'correo', 'e-mail'])) {
                if (str_contains($valor, '@')) {
                    $resultado['correo'] = substr($valor, 0, 150);
                }
            }
            if ($resultado['direccion'] === null && $this->contieneAlguna($nombre, ['direcc', 'address'])) {
                $resultado['direccion'] = substr($valor, 0, 300);
            }
        }

        return $resultado;
    }

    // =========================================================================
    // DESCARGA SOAP DEL SRI
    // =========================================================================

    /**
     * Consulta el web service del SRI por clave de acceso.
     * Retorna el XML completo (con wrapper <autorizacion>) o null si no hay información.
     *
     * @throws RuntimeException si no hay conexión
     */
    public function descargarXmlSoap(string $claveAcceso): ?string
    {
        $endpoint = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
        $soap = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:aut="http://ec.gob.sri.ws.autorizacion">'
            . '<soapenv:Header/><soapenv:Body><aut:autorizacionComprobante>'
            . '<claveAccesoComprobante>' . htmlspecialchars($claveAcceso, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</claveAccesoComprobante>'
            . '</aut:autorizacionComprobante></soapenv:Body></soapenv:Envelope>';

        $contexto = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: text/xml;charset=UTF-8\r\n",
            'content'       => $soap,
            'ignore_errors' => true,
            'timeout'       => 30,
        ]]);

        $respuesta = @file_get_contents($endpoint, false, $contexto);
        if ($respuesta === false) {
            throw new RuntimeException('No se pudo conectar al web service del SRI.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        if (!$dom->loadXML($respuesta)) {
            throw new RuntimeException('Respuesta del SRI no es XML válido.');
        }

        $xp          = new DOMXPath($dom);
        $nodoAut     = $xp->query('//*[local-name()="autorizacion"]')->item(0);
        if (!$nodoAut instanceof DOMNode) {
            return null; // SIN_INFORMACION
        }

        $estado = $this->domTexto($xp, $nodoAut, './*[local-name()="estado"]');
        if ($estado !== 'AUTORIZADO') {
            return null;
        }

        $numeroAut = $this->domTexto($xp, $nodoAut, './*[local-name()="numeroAutorizacion"]') ?? $claveAcceso;
        $fechaAut  = $this->domTexto($xp, $nodoAut, './*[local-name()="fechaAutorizacion"]') ?? '';
        $ambiente  = $this->domTexto($xp, $nodoAut, './*[local-name()="ambiente"]') ?? '';
        $comp      = html_entity_decode(
            (string) ($this->domTexto($xp, $nodoAut, './*[local-name()="comprobante"]') ?? ''),
            ENT_QUOTES | ENT_XML1, 'UTF-8'
        );

        // Envolver en <autorizacion> para almacenar compatible con parser
        $wrapper = new SimpleXMLElement('<autorizacion/>');
        $wrapper->addChild('estado', $estado);
        $wrapper->addChild('numeroAutorizacion', $numeroAut);
        $wrapper->addChild('fechaAutorizacion', $fechaAut);
        $wrapper->addChild('ambiente', $ambiente);
        $wrapper->addChild('comprobante', htmlspecialchars($comp, ENT_XML1 | ENT_COMPAT, 'UTF-8'));

        return $wrapper->asXML() ?: null;
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function domTexto(DOMXPath $xpath, DOMNode $contexto, string $consulta): ?string
    {
        $nodos = $xpath->query($consulta, $contexto);
        if ($nodos === false || $nodos->length === 0) {
            return null;
        }
        $valor = trim((string) $nodos->item(0)->textContent);
        return $valor === '' ? null : $valor;
    }

    private function numero(?string $valor): ?float
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : (float) str_replace(',', '.', $valor);
    }

    private function fechaEmision(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }
        $obj = DateTime::createFromFormat('d/m/Y', $fecha);
        return $obj ? $obj->format('Y-m-d') : null;
    }

    private function fechaAutorizacion(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }
        try {
            return (new DateTime($fecha))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizar(string $texto): string
    {
        $texto = strtolower(trim($texto));
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ]);
    }

    private function contieneAlguna(string $texto, array $palabras): bool
    {
        foreach ($palabras as $p) {
            if (str_contains($texto, $p)) {
                return true;
            }
        }
        return false;
    }

    private function detectarCodDoc(DOMDocument $dom): ?string
    {
        $xpath   = new DOMXPath($dom);
        $nodo    = $xpath->query('//*[local-name()="codDoc"]')->item(0);
        return $nodo ? trim((string) $nodo->textContent) : null;
    }

    private function nombreTipoDoc(?string $codDoc): string
    {
        return match ($codDoc) {
            '04' => 'Nota de Crédito',
            '05' => 'Nota de Débito',
            '06' => 'Guía de Remisión',
            '07' => 'Comprobante de Retención',
            default => 'Tipo ' . ($codDoc ?? 'desconocido'),
        };
    }
}
