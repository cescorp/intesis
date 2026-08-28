<?php

declare(strict_types=1);

namespace Intesis\Servicios;

final class SriXmlFacturaServicio
{
    public function __construct(private SriUtilidadesServicio $util) {}

    /**
     * Genera el XML sin firmar de una factura de venta según esquema SRI Ecuador v1.1.0.
     *
     * @param array $e      Empresa emisora (sis_empresa.*)
     * @param array $c      Cliente (ven_cliente.*)
     * @param array $doc    Cabecera del documento (ven_documento.*)
     * @param array $lineas Detalles del documento (ven_documento_detalle.*)
     * @param array $sri    Datos extra SRI: clave_acceso, ambiente, estab, pto_emi, secuencial, codigo_numerico, forma_pago_sri
     */
    public function generar(array $e, array $c, array $doc, array $lineas, array $sri): string
    {
        $xml  = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $raiz = $xml->createElement('factura');
        $raiz->setAttribute('id', 'comprobante');
        $raiz->setAttribute('version', '1.1.0');
        $xml->appendChild($raiz);

        // ── infoTributaria ────────────────────────────────────────────────────
        $infoTrib = $xml->createElement('infoTributaria');
        $this->t($xml, $infoTrib, 'ambiente',        $sri['ambiente']);
        $this->t($xml, $infoTrib, 'tipoEmision',     '1');
        $this->t($xml, $infoTrib, 'razonSocial',     $this->seg($e['sis_empresa_razon_social'] ?? ''));
        $this->t($xml, $infoTrib, 'nombreComercial',  $this->seg($e['sis_empresa_nombre_comercial'] ?? $e['sis_empresa_razon_social'] ?? ''));
        $this->t($xml, $infoTrib, 'ruc',             $e['sis_empresa_ruc'] ?? '');
        $this->t($xml, $infoTrib, 'claveAcceso',     $sri['clave_acceso']);
        $this->t($xml, $infoTrib, 'codDoc',          '01');
        $this->t($xml, $infoTrib, 'estab',           $sri['estab']);
        $this->t($xml, $infoTrib, 'ptoEmi',          $sri['pto_emi']);
        $this->t($xml, $infoTrib, 'secuencial',      $sri['secuencial']);
        $this->t($xml, $infoTrib, 'dirMatriz',       $this->seg($e['sis_empresa_direccion'] ?? ''));
        $raiz->appendChild($infoTrib);

        // ── infoFactura ───────────────────────────────────────────────────────
        $info = $xml->createElement('infoFactura');
        $fechaEmision = substr((string)($doc['ven_documento_fecha_emision'] ?? ''), 0, 10);
        $this->t($xml, $info, 'fechaEmision',      $this->util->fechaSri($fechaEmision));
        $this->t($xml, $info, 'dirEstablecimiento', $this->seg($e['sis_empresa_direccion'] ?? ''));

        $numCont = trim((string)($e['sis_empresa_num_contribuyente_especial'] ?? ''));
        if ($numCont !== '') {
            $this->t($xml, $info, 'contribuyenteEspecial', $numCont);
        }

        $this->t($xml, $info, 'obligadoContabilidad', 'SI');
        $this->t($xml, $info, 'tipoIdentificacionComprador',
            $this->util->tipoIdentificacionSri($c['ven_cliente_tipo_identificacion'] ?? 'O'));
        $this->t($xml, $info, 'razonSocialComprador', $this->seg($c['ven_cliente_razon_social'] ?? 'CONSUMIDOR FINAL'));
        $this->t($xml, $info, 'identificacionComprador', $c['ven_cliente_identificacion'] ?? '9999999999999');

        $dirCli = trim((string)($c['ven_cliente_direccion'] ?? ''));
        if ($dirCli !== '') {
            $this->t($xml, $info, 'direccionComprador', $this->seg($dirCli));
        }

        $subtotal  = (float)($doc['ven_documento_subtotal_sin_impuestos'] ?? $doc['ven_documento_subtotal'] ?? 0);
        $descTotal = (float)($doc['ven_documento_descuento'] ?? 0);
        $ivaTotal  = (float)($doc['ven_documento_impuesto_total'] ?? $doc['ven_documento_iva'] ?? 0);
        $total     = (float)($doc['ven_documento_valor_total'] ?? $doc['ven_documento_total'] ?? 0);

        $this->t($xml, $info, 'totalSinImpuestos', $this->util->f2($subtotal));
        $this->t($xml, $info, 'totalDescuento',     $this->util->f2($descTotal));

        // totalConImpuestos — IVA
        $ivaLineas    = $this->calcularBaseIva($lineas);
        $totalConImp  = $xml->createElement('totalConImpuestos');
        foreach ($ivaLineas as $tarifa => $datos) {
            $ti = $xml->createElement('totalImpuesto');
            $this->t($xml, $ti, 'codigo',            '2');
            $this->t($xml, $ti, 'codigoPorcentaje',  $this->util->codigoPorcentajeIva((float)$tarifa));
            $this->t($xml, $ti, 'baseImponible',     $this->util->f2($datos['base']));
            $this->t($xml, $ti, 'valor',             $this->util->f2($datos['iva']));
            $totalConImp->appendChild($ti);
        }
        if (count($ivaLineas) === 0) {
            // Fallback: un solo bloque con IVA cero
            $ti = $xml->createElement('totalImpuesto');
            $this->t($xml, $ti, 'codigo',           '2');
            $this->t($xml, $ti, 'codigoPorcentaje', '0');
            $this->t($xml, $ti, 'baseImponible',    $this->util->f2($subtotal));
            $this->t($xml, $ti, 'valor',            '0.00');
            $totalConImp->appendChild($ti);
        }
        $info->appendChild($totalConImp);

        $this->t($xml, $info, 'propina',      '0.00');
        $this->t($xml, $info, 'importeTotal', $this->util->f2($total));
        $this->t($xml, $info, 'moneda',       'DOLAR');

        // pagos
        $pagos = $xml->createElement('pagos');
        $pago  = $xml->createElement('pago');
        $this->t($xml, $pago, 'formaPago', $sri['forma_pago_sri'] ?? '01');
        $this->t($xml, $pago, 'total',     $this->util->f2($total));
        $plazo = (int)($doc['ven_documento_plazo_pago_dias'] ?? 0);
        $this->t($xml, $pago, 'plazo',          (string)$plazo);
        $this->t($xml, $pago, 'unidadTiempo',   'DIAS');
        $pagos->appendChild($pago);
        $info->appendChild($pagos);
        $raiz->appendChild($info);

        // ── detalles ─────────────────────────────────────────────────────────
        $nDetalles = $xml->createElement('detalles');
        foreach ($lineas as $l) {
            $det = $xml->createElement('detalle');
            $this->t($xml, $det, 'codigoPrincipal',
                $this->seg($l['ven_documento_detalle_codigo'] ?? $l['codigo_interno'] ?? 'N/A', 25));
            $this->t($xml, $det, 'descripcion',
                $this->seg($l['ven_documento_detalle_descripcion'] ?? $l['producto_nombre'] ?? '', 300));

            $cant   = (float)($l['ven_documento_detalle_cantidad']      ?? 0);
            $precio = (float)($l['ven_documento_detalle_precio_unitario'] ?? 0);
            $desc   = (float)($l['ven_documento_detalle_descuento_val']  ?? $l['ven_documento_detalle_descuento'] ?? 0);
            $base   = (float)($l['ven_documento_detalle_precio_total_sin_impuestos'] ?? 0);
            // sis_iva_valor = porcentaje real (ej. 15.00). ven_documento_detalle_iva_valor
            // es el MONTO en dolares del IVA de la linea, no un porcentaje — no usar aqui.
            $ivaPct = (float)($l['sis_iva_valor'] ?? 0);
            $ivaVal = (float)($l['ven_documento_detalle_impuesto_total'] ?? 0);

            $this->t($xml, $det, 'cantidad',              $this->util->f2($cant));
            $this->t($xml, $det, 'precioUnitario',        $this->util->f2($precio));
            $this->t($xml, $det, 'descuento',             $this->util->f2($desc));
            $this->t($xml, $det, 'precioTotalSinImpuesto', $this->util->f2($base));

            $impuestos = $xml->createElement('impuestos');
            $imp       = $xml->createElement('impuesto');
            $this->t($xml, $imp, 'codigo',           '2');
            $this->t($xml, $imp, 'codigoPorcentaje', $this->util->codigoPorcentajeIva($ivaPct));
            $this->t($xml, $imp, 'tarifa',           $this->util->f2($ivaPct));
            $this->t($xml, $imp, 'baseImponible',    $this->util->f2($base));
            $this->t($xml, $imp, 'valor',            $this->util->f2($ivaVal));
            $impuestos->appendChild($imp);
            $det->appendChild($impuestos);
            $nDetalles->appendChild($det);
        }
        $raiz->appendChild($nDetalles);

        // ── infoAdicional ────────────────────────────────────────────────────
        $adicional = $xml->createElement('infoAdicional');
        $email     = trim((string)($c['ven_cliente_email'] ?? ''));
        $tel       = trim((string)($c['ven_cliente_telefono'] ?? ''));
        $obs       = trim((string)($doc['ven_documento_observacion'] ?? ''));
        if ($email !== '') $this->campo($xml, $adicional, 'Email',     $email);
        if ($tel   !== '') $this->campo($xml, $adicional, 'Telefono',  $tel);
        if ($obs   !== '') $this->campo($xml, $adicional, 'Observacion', $this->seg($obs, 100));
        $raiz->appendChild($adicional);

        return $xml->saveXML() ?: throw new \RuntimeException('Error al serializar XML de factura.');
    }

    // ── helpers privados ─────────────────────────────────────────────────────

    private function t(\DOMDocument $xml, \DOMElement $padre, string $tag, string $valor): void
    {
        $n = $xml->createElement($tag);
        $n->appendChild($xml->createTextNode($valor));
        $padre->appendChild($n);
    }

    private function campo(\DOMDocument $xml, \DOMElement $padre, string $nombre, string $valor): void
    {
        $n = $xml->createElement('campoAdicional');
        $n->setAttribute('nombre', $nombre);
        $n->appendChild($xml->createTextNode($valor));
        $padre->appendChild($n);
    }

    private function seg(string $valor, int $max = 300): string
    {
        return $this->util->textoSeguro($valor, $max);
    }

    private function calcularBaseIva(array $lineas): array
    {
        $grupos = [];
        foreach ($lineas as $l) {
            $pct  = (float)($l['sis_iva_valor'] ?? 0);
            $base = (float)($l['ven_documento_detalle_precio_total_sin_impuestos'] ?? 0);
            $iva  = (float)($l['ven_documento_detalle_impuesto_total'] ?? 0);
            $key  = number_format($pct, 2);
            if (!isset($grupos[$key])) {
                $grupos[$key] = ['base' => 0.0, 'iva' => 0.0];
            }
            $grupos[$key]['base'] += $base;
            $grupos[$key]['iva']  += $iva;
        }
        return $grupos;
    }
}
