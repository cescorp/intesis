<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use Intesis\Nucleo\Configuracion;
use RuntimeException;

/**
 * Orquesta el proceso completo de facturación electrónica SRI Ecuador.
 *
 * Flujo:
 *   1. Generar código numérico aleatorio
 *   2. Generar clave de acceso (49 dígitos)
 *   3. Generar XML sin firmar
 *   4. Firmar XML con sri2.jar
 *   5. Enviar al SRI (recepción)
 *   6. Consultar autorización
 *   7. Guardar XML en filesystem
 *   8. Enviar email al cliente (si tiene correo)
 */
final class FacturacionElectronicaServicio
{
    public function __construct(
        private Configuracion          $configuracion,
        private SriUtilidadesServicio  $util,
        private SriClaveAccesoServicio $claveAcceso,
        private SriXmlFacturaServicio  $xmlFactura,
        private SriFirmadorServicio    $firmador,
        private SriSoapServicio        $soap,
        private SriEmailServicio       $email
    ) {}

    /**
     * Procesa una factura de venta y la envía al SRI.
     *
     * @param array $empresa   Datos de sis_empresa (incluyendo campos SRI nuevos)
     * @param array $doc       Datos de ven_documento (incluyendo bodega.establecimiento/pto_emision)
     * @param array $lineas    Detalles del documento
     * @param int   $usuarioId ID del usuario que ejecuta la acción
     *
     * @return array {
     *   estado:              'AUTORIZADA'|'ERROR',
     *   clave_acceso:        string,
     *   autorizacion:        string,
     *   fecha_autorizacion:  string,
     *   xml_autorizado:      string,
     *   respuesta_sri:       string,
     *   ambiente:            string ('1'|'2'),
     *   usuario_id:          int,
     * }
     */
    public function procesar(array $empresa, array $doc, array $lineas, int $usuarioId): array
    {
        $raiz    = $this->configuracion->raiz();
        $ruc     = $this->util->soloNumeros($empresa['sis_empresa_ruc'] ?? '');
        $ambiente = (string)($empresa['sis_empresa_ambiente_sri'] ?? '1');
        if (!in_array($ambiente, ['1', '2'], true)) $ambiente = '1';

        // Directorios de salida XML
        $this->util->crearDirectoriosSiNoExisten($raiz, $ruc);
        $basePath = $raiz . '/almacenamiento/ventas/' . $ruc;

        // Datos del número de documento
        $partes     = $this->util->parsearNumeroDocumento((string)($doc['ven_documento_numero'] ?? ''));
        $estab      = $doc['inv_bodega_establecimiento'] ?? $partes['estab'];
        $ptoEmi     = $doc['inv_bodega_punto_emision']  ?? $partes['pto_emi'];
        $secuencial = $partes['secuencial'];

        // Forma de pago SRI
        $formaPagoSri = (string)($doc['ven_forma_pago_codigo_sri'] ?? '01');

        // Código numérico (si ya tiene uno guardado, usar ese para reintento; sino generar)
        $codigoNumerico = trim((string)($doc['ven_documento_codigo_numerico'] ?? ''));
        if ($codigoNumerico === '') {
            $codigoNumerico = $this->util->codigoNumericoAleatorio();
        }

        // ── 1. Clave de acceso ────────────────────────────────────────────────
        $fechaEmision = substr((string)($doc['ven_documento_fecha_emision'] ?? date('Y-m-d')), 0, 10);
        $clave = $this->claveAcceso->generar(
            $fechaEmision,
            '01',
            $ruc,
            $ambiente,
            $estab,
            $ptoEmi,
            $secuencial,
            $codigoNumerico
        );

        // ── 2. XML sin firmar ─────────────────────────────────────────────────
        $sri = [
            'clave_acceso'    => $clave,
            'ambiente'        => $ambiente,
            'estab'           => $estab,
            'pto_emi'         => $ptoEmi,
            'secuencial'      => $secuencial,
            'codigo_numerico' => $codigoNumerico,
            'forma_pago_sri'  => $formaPagoSri,
        ];

        $cliente = [
            'ven_cliente_tipo_identificacion' => $doc['ven_cliente_tipo_identificacion'] ?? 'O',
            'ven_cliente_razon_social'         => $doc['ven_cliente_razon_social']        ?? 'CONSUMIDOR FINAL',
            'ven_cliente_identificacion'       => $doc['ven_cliente_identificacion']      ?? '9999999999999',
            'ven_cliente_email'                => $doc['ven_cliente_email']               ?? '',
            'ven_cliente_telefono'             => $doc['ven_cliente_telefono']            ?? '',
            'ven_cliente_direccion'            => $doc['ven_cliente_direccion']           ?? '',
        ];

        $xmlSinFirmar = $this->xmlFactura->generar($empresa, $cliente, $doc, $lineas, $sri);

        // ── 3. Firmar XML ────────────────────────────────────────────────────
        $rutaJar  = $raiz . '/' . ltrim($this->configuracion->obtener('SRI_JAR_RUTA', 'tools/java/dist/sri2.jar') ?? '', '/');
        $rutaCert = $this->resolverRutaCertificado($raiz, $empresa);
        $claveCert = (string)($empresa['sis_empresa_certificado_clave'] ?? '');

        if ($claveCert === '') {
            throw new RuntimeException('No está configurada la clave del certificado digital en sis_empresa.');
        }

        $dirTemp       = $basePath . '/temp';
        $nombreArchivo = $clave . '.xml';

        $xmlFirmado = $this->firmador->firmar(
            $xmlSinFirmar,
            $rutaCert,
            $claveCert,
            $rutaJar,
            $dirTemp,
            $nombreArchivo
        );

        // ── 4. Enviar al SRI (recepción) ─────────────────────────────────────
        $respRecepcion = $this->soap->enviarRecepcion($xmlFirmado, $ambiente);

        if ($respRecepcion['estado'] !== 'RECIBIDA') {
            $errMsg = implode('; ', $respRecepcion['mensajes']) ?: 'Comprobante devuelto por el SRI.';
            $this->guardarXml($basePath . '/error', $nombreArchivo, $xmlFirmado);
            return $this->resultadoError($clave, $codigoNumerico, $ambiente, $errMsg, $usuarioId);
        }

        // ── 5. Consultar autorización ────────────────────────────────────────
        // SRI puede tardar; reintentar hasta 3 veces con pausa
        $auth = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                $auth = $this->soap->consultarAutorizacion($clave, $ambiente);
                if ($auth['estado'] === 'AUTORIZADO') break;
            } catch (\Throwable) {
                // intento fallido — reintentamos
            }
            if ($i < 2) sleep(2);
        }

        if ($auth === null || $auth['estado'] !== 'AUTORIZADO') {
            $errMsg = $auth ? implode('; ', $auth['mensajes']) : 'Sin respuesta de autorización.';
            $this->guardarXml($basePath . '/error', $nombreArchivo, $xmlFirmado);
            return $this->resultadoError($clave, $codigoNumerico, $ambiente, $errMsg, $usuarioId);
        }

        // ── 6. Guardar XML autorizado ────────────────────────────────────────
        $xmlAutorizado = $auth['xml_autorizado'] ?: $xmlFirmado;
        $this->guardarXml($basePath . '/autorizados', $nombreArchivo, $xmlAutorizado);

        // ── 7. Enviar email al cliente ───────────────────────────────────────
        $emailCliente = trim($cliente['ven_cliente_email']);
        if ($emailCliente !== '') {
            try {
                // Para el email intentamos generar el PDF más adelante (desde el controlador);
                // aquí sólo marcamos que hay email pendiente — el controlador lo gestiona.
            } catch (\Throwable) {
                // El email no puede bloquear la autorización
            }
        }

        return [
            'estado'             => 'AUTORIZADA',
            'clave_acceso'       => $clave,
            'codigo_numerico'    => $codigoNumerico,
            'autorizacion'       => $auth['numero_autorizacion'],
            'fecha_autorizacion' => $auth['fecha_autorizacion'],
            'xml_autorizado'     => $xmlAutorizado,
            'respuesta_sri'      => 'AUTORIZADO',
            'ambiente'           => $ambiente,
            'usuario_id'         => $usuarioId,
            'email_cliente'      => $emailCliente,
        ];
    }

    // ── helpers privados ─────────────────────────────────────────────────────

    private function resolverRutaCertificado(string $raiz, array $empresa): string
    {
        $ruta = trim((string)($empresa['sis_empresa_certificado_ruta'] ?? ''));

        if ($ruta !== '' && is_file($ruta)) {
            return $ruta;
        }

        // Buscar en directorio por defecto
        $dirFirmas = $raiz . '/almacenamiento/archivos/firmas';
        if ($ruta !== '') {
            $candidato = $dirFirmas . '/' . basename($ruta);
            if (is_file($candidato)) return $candidato;
        }

        // Fallback: primer .p12 en el directorio de firmas
        $archivos = glob($dirFirmas . '/*.p12') ?: [];
        if (!empty($archivos)) return $archivos[0];

        throw new RuntimeException('Certificado digital (.p12) no encontrado. Configure sis_empresa.sis_empresa_certificado_ruta.');
    }

    private function guardarXml(string $directorio, string $nombre, string $contenido): void
    {
        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        file_put_contents($directorio . DIRECTORY_SEPARATOR . $nombre, $contenido);
    }

    private function resultadoError(string $clave, string $codigoNumerico, string $ambiente, string $errMsg, int $usuarioId): array
    {
        return [
            'estado'             => 'ERROR',
            'clave_acceso'       => $clave,
            'codigo_numerico'    => $codigoNumerico,
            'autorizacion'       => '',
            'fecha_autorizacion' => null,
            'xml_autorizado'     => null,
            'respuesta_sri'      => $errMsg,
            'ambiente'           => $ambiente,
            'usuario_id'         => $usuarioId,
            'email_cliente'      => '',
        ];
    }
}
