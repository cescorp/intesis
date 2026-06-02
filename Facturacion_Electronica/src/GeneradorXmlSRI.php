<?php

/******************************************************************************/
/*                                                                            */
/*  GENERADOR XML BASADO EN ESTRUCTURA DEL PROYECTO ORIGINAL                 */
/*  - UTF-8                                                                   */
/*  - MULTIPLES LINEAS                                                        */
/*                                                                            */
/******************************************************************************/

class GeneradorXmlSRI
{
    /**************************************************************************/
    /*  ENRUTADOR PRINCIPAL                                                    */
    /**************************************************************************/
    public static function generarSegunTipo(array $cabecera, array $detalles = [], array $retenciones = []): string
    {
        switch ($cabecera['codigo_comprobante']) {
            case '01':
                return self::generarFactura($cabecera, $detalles);
            case '04':
                return self::generarNotaCredito($cabecera, $detalles);
            case '05':
                return self::generarNotaDebito($cabecera);
            case '06':
                return self::generarGuiaRemision($cabecera, $detalles);
            case '07':
                return self::generarRetencion($cabecera, $retenciones);
            default:
                throw new Exception('Tipo de comprobante no soportado.');
        }
    }

    /**************************************************************************/
    /*  FACTURA 01 (ESTILO PROYECTO)                                           */
    /**************************************************************************/
    private static function generarFactura(array $c, array $detalles): string
    {
        $xml = self::nuevoDocumento();
        $raiz = $xml->createElement('factura');
        $raiz->setAttribute('id', 'comprobante');
        $raiz->setAttribute('version', '1.1.0');
        $xml->appendChild($raiz);

        $raiz->appendChild(self::nodoInfoTributaria($xml, $c));

        $infoFactura = $xml->createElement('infoFactura');
        self::agregarTexto($xml, $infoFactura, 'fechaEmision', $c['fecha_emision']);
        self::agregarTexto($xml, $infoFactura, 'dirEstablecimiento', $c['dir_establecimiento']);
        if ($c['contribuyente_especial'] !== '') {
            self::agregarTexto($xml, $infoFactura, 'contribuyenteEspecial', $c['contribuyente_especial']);
        }
        self::agregarTexto($xml, $infoFactura, 'obligadoContabilidad', $c['obligado_contabilidad']);
        self::agregarTexto($xml, $infoFactura, 'tipoIdentificacionComprador', $c['tipo_id_comprador']);
        self::agregarTexto($xml, $infoFactura, 'razonSocialComprador', $c['razon_social_comprador']);
        self::agregarTexto($xml, $infoFactura, 'identificacionComprador', $c['id_comprador']);
        if ($c['direccion_comprador'] !== '') {
            self::agregarTexto($xml, $infoFactura, 'direccionComprador', $c['direccion_comprador']);
        }
        self::agregarTexto($xml, $infoFactura, 'totalSinImpuestos', self::f2($c['total_sin_impuestos']));
        self::agregarTexto($xml, $infoFactura, 'totalDescuento', self::f2($c['total_descuento']));

        $totalConImpuestos = $xml->createElement('totalConImpuestos');
        $totalImpuesto = $xml->createElement('totalImpuesto');
        self::agregarTexto($xml, $totalImpuesto, 'codigo', '2');
        self::agregarTexto($xml, $totalImpuesto, 'codigoPorcentaje', self::codigoPorcentajeIva((float)$c['porcentaje_iva']));
        self::agregarTexto($xml, $totalImpuesto, 'baseImponible', self::f2($c['base_imponible_iva']));
        self::agregarTexto($xml, $totalImpuesto, 'valor', self::f2($c['valor_iva']));
        $totalConImpuestos->appendChild($totalImpuesto);
        $infoFactura->appendChild($totalConImpuestos);

        self::agregarTexto($xml, $infoFactura, 'propina', '0.00');
        self::agregarTexto($xml, $infoFactura, 'importeTotal', self::f2($c['importe_total']));
        self::agregarTexto($xml, $infoFactura, 'moneda', 'DOLAR');

        $pagos = $xml->createElement('pagos');
        $pago = $xml->createElement('pago');
        self::agregarTexto($xml, $pago, 'formaPago', $c['forma_pago']);
        self::agregarTexto($xml, $pago, 'total', self::f2($c['importe_total']));
        self::agregarTexto($xml, $pago, 'plazo', $c['plazo_pago_dias']);
        self::agregarTexto($xml, $pago, 'unidadTiempo', 'DIAS');
        $pagos->appendChild($pago);
        $infoFactura->appendChild($pagos);

        $raiz->appendChild($infoFactura);

        $nodoDetalles = $xml->createElement('detalles');
        foreach ($detalles as $d) {
            $detalle = $xml->createElement('detalle');
            self::agregarTexto($xml, $detalle, 'codigoPrincipal', $d['codigo']);
            self::agregarTexto($xml, $detalle, 'codigoAuxiliar', $d['codigo_auxiliar']);
            self::agregarTexto($xml, $detalle, 'descripcion', $d['descripcion']);
            self::agregarTexto($xml, $detalle, 'cantidad', self::f2($d['cantidad']));
            self::agregarTexto($xml, $detalle, 'precioUnitario', self::f2($d['precio_unitario']));
            self::agregarTexto($xml, $detalle, 'descuento', self::f2($d['descuento']));
            self::agregarTexto($xml, $detalle, 'precioTotalSinImpuesto', self::f2($d['subtotal']));

            $impuestos = $xml->createElement('impuestos');
            $impuesto = $xml->createElement('impuesto');
            self::agregarTexto($xml, $impuesto, 'codigo', '2');
            self::agregarTexto($xml, $impuesto, 'codigoPorcentaje', self::codigoPorcentajeIva((float)$d['iva_pct']));
            self::agregarTexto($xml, $impuesto, 'tarifa', self::f2($d['iva_pct']));
            self::agregarTexto($xml, $impuesto, 'baseImponible', self::f2($d['subtotal']));
            self::agregarTexto($xml, $impuesto, 'valor', self::f2($d['iva_valor']));
            $impuestos->appendChild($impuesto);
            $detalle->appendChild($impuestos);

            $nodoDetalles->appendChild($detalle);
        }
        $raiz->appendChild($nodoDetalles);

        $raiz->appendChild(self::nodoInfoAdicional($xml, $c));

        return $xml->saveXML();
    }

    /**************************************************************************/
    /*  NOTA CREDITO 04 (ESTILO PROYECTO)                                      */
    /**************************************************************************/
    private static function generarNotaCredito(array $c, array $detalles): string
    {
        $xml = self::nuevoDocumento();
        $raiz = $xml->createElement('notaCredito');
        $raiz->setAttribute('id', 'comprobante');
        $raiz->setAttribute('version', '1.1.0');
        $xml->appendChild($raiz);

        $raiz->appendChild(self::nodoInfoTributaria($xml, $c));

        $info = $xml->createElement('infoNotaCredito');
        self::agregarTexto($xml, $info, 'fechaEmision', $c['fecha_emision']);
        self::agregarTexto($xml, $info, 'dirEstablecimiento', $c['dir_establecimiento']);
        self::agregarTexto($xml, $info, 'tipoIdentificacionComprador', $c['tipo_id_comprador']);
        self::agregarTexto($xml, $info, 'razonSocialComprador', $c['razon_social_comprador']);
        self::agregarTexto($xml, $info, 'identificacionComprador', $c['id_comprador']);
        if ($c['contribuyente_especial'] !== '') {
            self::agregarTexto($xml, $info, 'contribuyenteEspecial', $c['contribuyente_especial']);
        }
        self::agregarTexto($xml, $info, 'obligadoContabilidad', $c['obligado_contabilidad']);
        self::agregarTexto($xml, $info, 'codDocModificado', '01');
        self::agregarTexto($xml, $info, 'numDocModificado', $c['documento_modificado']);
        self::agregarTexto($xml, $info, 'fechaEmisionDocSustento', $c['fecha_doc_modificado']);
        self::agregarTexto($xml, $info, 'totalSinImpuestos', self::f2($c['total_sin_impuestos']));
        self::agregarTexto($xml, $info, 'valorModificacion', self::f2($c['importe_total']));
        self::agregarTexto($xml, $info, 'moneda', 'DOLAR');

        $tci = $xml->createElement('totalConImpuestos');
        $ti = $xml->createElement('totalImpuesto');
        self::agregarTexto($xml, $ti, 'codigo', '2');
        self::agregarTexto($xml, $ti, 'codigoPorcentaje', self::codigoPorcentajeIva((float)$c['porcentaje_iva']));
        self::agregarTexto($xml, $ti, 'baseImponible', self::f2($c['base_imponible_iva']));
        self::agregarTexto($xml, $ti, 'valor', self::f2($c['valor_iva']));
        $tci->appendChild($ti);
        $info->appendChild($tci);
        self::agregarTexto($xml, $info, 'motivo', $c['motivo']);
        $raiz->appendChild($info);

        $nodoDetalles = $xml->createElement('detalles');
        foreach ($detalles as $d) {
            $detalle = $xml->createElement('detalle');
            self::agregarTexto($xml, $detalle, 'codigoInterno', $d['codigo']);
            self::agregarTexto($xml, $detalle, 'codigoAdicional', $d['codigo_auxiliar']);
            self::agregarTexto($xml, $detalle, 'descripcion', $d['descripcion']);
            self::agregarTexto($xml, $detalle, 'cantidad', self::f2($d['cantidad']));
            self::agregarTexto($xml, $detalle, 'precioUnitario', self::f2($d['precio_unitario']));
            self::agregarTexto($xml, $detalle, 'descuento', self::f2($d['descuento']));
            self::agregarTexto($xml, $detalle, 'precioTotalSinImpuesto', self::f2($d['subtotal']));

            $impuestos = $xml->createElement('impuestos');
            $impuesto = $xml->createElement('impuesto');
            self::agregarTexto($xml, $impuesto, 'codigo', '2');
            self::agregarTexto($xml, $impuesto, 'codigoPorcentaje', self::codigoPorcentajeIva((float)$d['iva_pct']));
            self::agregarTexto($xml, $impuesto, 'tarifa', self::f2($d['iva_pct']));
            self::agregarTexto($xml, $impuesto, 'baseImponible', self::f2($d['subtotal']));
            self::agregarTexto($xml, $impuesto, 'valor', self::f2($d['iva_valor']));
            $impuestos->appendChild($impuesto);
            $detalle->appendChild($impuestos);

            $nodoDetalles->appendChild($detalle);
        }
        $raiz->appendChild($nodoDetalles);

        $raiz->appendChild(self::nodoInfoAdicional($xml, $c));

        return $xml->saveXML();
    }

    /**************************************************************************/
    /*  NOTA DEBITO 05                                                         */
    /**************************************************************************/
    private static function generarNotaDebito(array $c): string
    {
        $xml = self::nuevoDocumento();
        $raiz = $xml->createElement('notaDebito');
        $raiz->setAttribute('id', 'comprobante');
        $raiz->setAttribute('version', '1.0.0');
        $xml->appendChild($raiz);

        $raiz->appendChild(self::nodoInfoTributaria($xml, $c));

        $info = $xml->createElement('infoNotaDebito');
        self::agregarTexto($xml, $info, 'fechaEmision', $c['fecha_emision']);
        self::agregarTexto($xml, $info, 'dirEstablecimiento', $c['dir_establecimiento']);
        if ($c['contribuyente_especial'] !== '') {
            self::agregarTexto($xml, $info, 'contribuyenteEspecial', $c['contribuyente_especial']);
        }
        self::agregarTexto($xml, $info, 'obligadoContabilidad', $c['obligado_contabilidad']);
        self::agregarTexto($xml, $info, 'tipoIdentificacionComprador', $c['tipo_id_comprador']);
        self::agregarTexto($xml, $info, 'razonSocialComprador', $c['razon_social_comprador']);
        self::agregarTexto($xml, $info, 'identificacionComprador', $c['id_comprador']);
        self::agregarTexto($xml, $info, 'codDocModificado', '01');
        self::agregarTexto($xml, $info, 'numDocModificado', $c['documento_modificado']);
        self::agregarTexto($xml, $info, 'fechaEmisionDocSustento', $c['fecha_doc_modificado']);
        self::agregarTexto($xml, $info, 'totalSinImpuestos', self::f2($c['total_sin_impuestos']));

        $impuestos = $xml->createElement('impuestos');
        $impuesto = $xml->createElement('impuesto');
        self::agregarTexto($xml, $impuesto, 'codigo', '2');
        self::agregarTexto($xml, $impuesto, 'codigoPorcentaje', self::codigoPorcentajeIva((float)$c['porcentaje_iva']));
        self::agregarTexto($xml, $impuesto, 'tarifa', self::f2($c['porcentaje_iva']));
        self::agregarTexto($xml, $impuesto, 'baseImponible', self::f2($c['base_imponible_iva']));
        self::agregarTexto($xml, $impuesto, 'valor', self::f2($c['valor_iva']));
        $impuestos->appendChild($impuesto);
        $info->appendChild($impuestos);

        self::agregarTexto($xml, $info, 'valorTotal', self::f2($c['importe_total']));
        $raiz->appendChild($info);

        $motivos = $xml->createElement('motivos');
        $motivo = $xml->createElement('motivo');
        self::agregarTexto($xml, $motivo, 'razon', $c['motivo']);
        self::agregarTexto($xml, $motivo, 'valor', self::f2($c['importe_total']));
        $motivos->appendChild($motivo);
        $raiz->appendChild($motivos);

        $raiz->appendChild(self::nodoInfoAdicional($xml, $c));

        return $xml->saveXML();
    }

    /**************************************************************************/
    /*  GUIA REMISION 06                                                       */
    /**************************************************************************/
    private static function generarGuiaRemision(array $c, array $detalles): string
    {
        $xml = self::nuevoDocumento();
        $raiz = $xml->createElement('guiaRemision');
        $raiz->setAttribute('id', 'comprobante');
        $raiz->setAttribute('version', '1.0.0');
        $xml->appendChild($raiz);

        $raiz->appendChild(self::nodoInfoTributaria($xml, $c));

        $info = $xml->createElement('infoGuiaRemision');
        self::agregarTexto($xml, $info, 'dirEstablecimiento', $c['dir_establecimiento']);
        self::agregarTexto($xml, $info, 'dirPartida', $c['dir_partida']);
        self::agregarTexto($xml, $info, 'razonSocialTransportista', $c['razon_social_transportista']);
        self::agregarTexto($xml, $info, 'tipoIdentificacionTransportista', $c['tipo_id_transportista']);
        self::agregarTexto($xml, $info, 'rucTransportista', $c['ruc_transportista']);
        self::agregarTexto($xml, $info, 'obligadoContabilidad', $c['obligado_contabilidad']);
        if ($c['contribuyente_especial'] !== '') {
            self::agregarTexto($xml, $info, 'contribuyenteEspecial', $c['contribuyente_especial']);
        }
        self::agregarTexto($xml, $info, 'fechaIniTransporte', $c['fecha_inicio_transporte']);
        self::agregarTexto($xml, $info, 'fechaFinTransporte', $c['fecha_fin_transporte']);
        self::agregarTexto($xml, $info, 'placa', $c['placa']);
        $raiz->appendChild($info);

        $destinatarios = $xml->createElement('destinatarios');
        $destinatario = $xml->createElement('destinatario');
        self::agregarTexto($xml, $destinatario, 'identificacionDestinatario', $c['id_comprador']);
        self::agregarTexto($xml, $destinatario, 'razonSocialDestinatario', $c['razon_social_comprador']);
        self::agregarTexto($xml, $destinatario, 'dirDestinatario', $c['direccion_destinatario']);
        self::agregarTexto($xml, $destinatario, 'motivoTraslado', $c['motivo']);
        self::agregarTexto($xml, $destinatario, 'docAduaneroUnico', $c['doc_aduanero']);
        self::agregarTexto($xml, $destinatario, 'codEstabDestino', $c['cod_estab_destino']);
        self::agregarTexto($xml, $destinatario, 'ruta', $c['ruta']);
        self::agregarTexto($xml, $destinatario, 'codDocSustento', '01');
        self::agregarTexto($xml, $destinatario, 'numDocSustento', $c['documento_modificado']);
        self::agregarTexto($xml, $destinatario, 'numAutDocSustento', $c['num_aut_doc_sustento']);
        self::agregarTexto($xml, $destinatario, 'fechaEmisionDocSustento', $c['fecha_doc_modificado']);

        $nodoDetalles = $xml->createElement('detalles');
        foreach ($detalles as $d) {
            $detalle = $xml->createElement('detalle');
            self::agregarTexto($xml, $detalle, 'codigoInterno', $d['codigo']);
            if ($d['codigo_auxiliar'] !== '') {
                self::agregarTexto($xml, $detalle, 'codigoAdicional', $d['codigo_auxiliar']);
            }
            self::agregarTexto($xml, $detalle, 'descripcion', $d['descripcion']);
            self::agregarTexto($xml, $detalle, 'cantidad', self::f2($d['cantidad']));
            if ($d['marca'] !== '') {
                $detallesAdicionales = $xml->createElement('detallesAdicionales');
                $detAdicional = $xml->createElement('detAdicional');
                $detAdicional->setAttribute('nombre', 'Marca');
                $detAdicional->setAttribute('valor', UtilidadesSistema::textoSeguro($d['marca'], 120));
                $detallesAdicionales->appendChild($detAdicional);
                $detalle->appendChild($detallesAdicionales);
            }
            $nodoDetalles->appendChild($detalle);
        }
        $destinatario->appendChild($nodoDetalles);
        $destinatarios->appendChild($destinatario);
        $raiz->appendChild($destinatarios);

        $raiz->appendChild(self::nodoInfoAdicional($xml, $c));

        return $xml->saveXML();
    }

    /**************************************************************************/
    /*  RETENCION 07 (ESTRUCTURA RESUMIDA DEL PROYECTO)                        */
    /**************************************************************************/
    private static function generarRetencion(array $c, array $retenciones): string
    {
        $xml = self::nuevoDocumento();
        $raiz = $xml->createElement('comprobanteRetencion');
        $raiz->setAttribute('id', 'comprobante');
        $raiz->setAttribute('version', '2.0.0');
        $xml->appendChild($raiz);

        $infoTrib = self::nodoInfoTributaria($xml, $c);
        self::agregarTexto($xml, $infoTrib, 'agenteRetencion', '1');
        $raiz->appendChild($infoTrib);

        $info = $xml->createElement('infoCompRetencion');
        self::agregarTexto($xml, $info, 'fechaEmision', $c['fecha_emision']);
        self::agregarTexto($xml, $info, 'dirEstablecimiento', $c['dir_establecimiento']);
        if ($c['contribuyente_especial'] !== '') {
            self::agregarTexto($xml, $info, 'contribuyenteEspecial', $c['contribuyente_especial']);
        }
        self::agregarTexto($xml, $info, 'obligadoContabilidad', $c['obligado_contabilidad']);
        self::agregarTexto($xml, $info, 'tipoIdentificacionSujetoRetenido', $c['tipo_id_comprador']);
        self::agregarTexto($xml, $info, 'parteRel', 'NO');
        self::agregarTexto($xml, $info, 'razonSocialSujetoRetenido', $c['razon_social_comprador']);
        self::agregarTexto($xml, $info, 'identificacionSujetoRetenido', $c['id_comprador']);
        self::agregarTexto($xml, $info, 'periodoFiscal', $c['periodo_fiscal']);
        $raiz->appendChild($info);

        $docsSustento = $xml->createElement('docsSustento');
        $docSustento = $xml->createElement('docSustento');
        self::agregarTexto($xml, $docSustento, 'codSustento', '01');
        self::agregarTexto($xml, $docSustento, 'codDocSustento', '01');
        self::agregarTexto($xml, $docSustento, 'numDocSustento', $c['documento_modificado']);
        self::agregarTexto($xml, $docSustento, 'fechaEmisionDocSustento', $c['fecha_doc_modificado']);
        self::agregarTexto($xml, $docSustento, 'fechaRegistroContable', $c['fecha_doc_modificado']);
        self::agregarTexto($xml, $docSustento, 'numAutDocSustento', $c['num_aut_doc_sustento']);
        self::agregarTexto($xml, $docSustento, 'pagoLocExt', '01');
        self::agregarTexto($xml, $docSustento, 'totalComprobantesReembolso', '0.00');
        self::agregarTexto($xml, $docSustento, 'totalBaseImponibleReembolso', '0.00');
        self::agregarTexto($xml, $docSustento, 'totalImpuestoReembolso', '0.00');
        self::agregarTexto($xml, $docSustento, 'totalSinImpuestos', self::f2($c['total_sin_impuestos']));
        self::agregarTexto($xml, $docSustento, 'importeTotal', self::f2($c['importe_total']));

        $impuestosDocSustento = $xml->createElement('impuestosDocSustento');
        $ids = $xml->createElement('impuestoDocSustento');
        self::agregarTexto($xml, $ids, 'codImpuestoDocSustento', '2');
        self::agregarTexto($xml, $ids, 'codigoPorcentaje', self::codigoPorcentajeIva((float)$c['porcentaje_iva']));
        self::agregarTexto($xml, $ids, 'baseImponible', self::f2($c['base_imponible_iva']));
        self::agregarTexto($xml, $ids, 'tarifa', self::f2($c['porcentaje_iva']));
        self::agregarTexto($xml, $ids, 'valorImpuesto', self::f2($c['valor_iva']));
        $impuestosDocSustento->appendChild($ids);
        $docSustento->appendChild($impuestosDocSustento);

        $retencionesNodo = $xml->createElement('retenciones');
        foreach ($retenciones as $r) {
            $ret = $xml->createElement('retencion');
            self::agregarTexto($xml, $ret, 'codigo', $r['codigo']);
            self::agregarTexto($xml, $ret, 'codigoRetencion', $r['codigo_retencion']);
            self::agregarTexto($xml, $ret, 'baseImponible', self::f2($r['base']));
            self::agregarTexto($xml, $ret, 'porcentajeRetener', self::f2($r['porcentaje']));
            self::agregarTexto($xml, $ret, 'valorRetenido', self::f2($r['valor']));
            $retencionesNodo->appendChild($ret);
        }
        $docSustento->appendChild($retencionesNodo);

        $pagos = $xml->createElement('pagos');
        $pago = $xml->createElement('pago');
        self::agregarTexto($xml, $pago, 'formaPago', '20');
        self::agregarTexto($xml, $pago, 'total', self::f2($c['importe_total']));
        $pagos->appendChild($pago);
        $docSustento->appendChild($pagos);

        $docsSustento->appendChild($docSustento);
        $raiz->appendChild($docsSustento);

        $raiz->appendChild(self::nodoInfoAdicional($xml, $c));

        return $xml->saveXML();
    }

    /**************************************************************************/
    /*  NODO INFO TRIBUTARIA                                                   */
    /**************************************************************************/
    private static function nodoInfoTributaria(DOMDocument $xml, array $c): DOMElement
    {
        $info = $xml->createElement('infoTributaria');
        self::agregarTexto($xml, $info, 'ambiente', $c['ambiente']);
        self::agregarTexto($xml, $info, 'tipoEmision', '1');
        self::agregarTexto($xml, $info, 'razonSocial', $c['razon_social_emisor']);
        self::agregarTexto($xml, $info, 'nombreComercial', $c['nombre_comercial']);
        self::agregarTexto($xml, $info, 'ruc', $c['ruc_emisor']);
        self::agregarTexto($xml, $info, 'claveAcceso', $c['clave_acceso']);
        self::agregarTexto($xml, $info, 'codDoc', $c['codigo_comprobante']);
        self::agregarTexto($xml, $info, 'estab', $c['estab']);
        self::agregarTexto($xml, $info, 'ptoEmi', $c['pto_emi']);
        self::agregarTexto($xml, $info, 'secuencial', $c['secuencial']);
        self::agregarTexto($xml, $info, 'dirMatriz', $c['dir_matriz']);
        return $info;
    }

    /**************************************************************************/
    /*  NODO INFO ADICIONAL                                                    */
    /**************************************************************************/
    private static function nodoInfoAdicional(DOMDocument $xml, array $c): DOMElement
    {
        $info = $xml->createElement('infoAdicional');
        self::agregarCampoAdicional($xml, $info, 'Direccion', $c['direccion_comprador']);
        self::agregarCampoAdicional($xml, $info, 'Telefono', $c['telefono_comprador']);
        self::agregarCampoAdicional($xml, $info, 'Email', $c['email_comprador']);
        self::agregarCampoAdicional($xml, $info, 'Observacion', $c['motivo']);
        return $info;
    }

    /**************************************************************************/
    /*  AUXILIARES                                                             */
    /**************************************************************************/
    private static function nuevoDocumento(): DOMDocument
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        return $xml;
    }

    private static function agregarTexto(DOMDocument $xml, DOMElement $padre, string $tag, string $valor): void
    {
        $n = $xml->createElement($tag);
        $n->appendChild($xml->createTextNode(UtilidadesSistema::textoSeguro($valor, 600)));
        $padre->appendChild($n);
    }

    private static function agregarCampoAdicional(DOMDocument $xml, DOMElement $padre, string $nombre, string $valor): void
    {
        if (trim($valor) === '') {
            return;
        }
        $campo = $xml->createElement('campoAdicional', UtilidadesSistema::textoSeguro($valor, 300));
        $campo->setAttribute('nombre', $nombre);
        $padre->appendChild($campo);
    }

    private static function codigoPorcentajeIva(float $tarifa): string
    {
        if (abs($tarifa - 15.0) < 0.01) {
            return '4';
        }
        if (abs($tarifa - 14.0) < 0.01) {
            return '3';
        }
        if (abs($tarifa - 12.0) < 0.01) {
            return '2';
        }
        if (abs($tarifa) < 0.01) {
            return '0';
        }
        return '8';
    }

    private static function f2($v): string
    {
        return number_format((float)$v, 2, '.', '');
    }
}
