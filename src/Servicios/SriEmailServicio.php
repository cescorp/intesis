<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use RuntimeException;

final class SriEmailServicio
{
    public function __construct(
        private string $smtpHost,
        private int    $smtpPort,
        private string $smtpUser,
        private string $smtpPass,
        private string $fromName
    ) {}

    /**
     * Envía la factura autorizada por correo con PDF y XML adjuntos.
     *
     * @param string $para          Dirección de correo del destinatario
     * @param string $numero        Número de la factura
     * @param string $empresa       Nombre comercial del emisor
     * @param string $pdfContenido  Contenido binario del PDF
     * @param string $xmlContenido  Contenido del XML autorizado
     */
    public function enviarFactura(
        string $para,
        string $numero,
        string $empresa,
        string $pdfContenido,
        string $xmlContenido
    ): void {
        $limite  = 'frontier_' . bin2hex(random_bytes(8));
        $asunto  = '=?UTF-8?B?' . base64_encode("Factura {$numero} - {$empresa}") . '?=';
        $cuerpo  = "Estimado cliente,\r\n\r\nAdjunto encontrará la factura electrónica {$numero} emitida por {$empresa}.\r\n\r\nGracias por su preferencia.";

        $headers  = "From: =?UTF-8?B?" . base64_encode($empresa) . "?= <{$this->smtpUser}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$limite}\"\r\n";

        $cuerpoMime  = "--{$limite}\r\n";
        $cuerpoMime .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$cuerpo}\r\n\r\n";

        $cuerpoMime .= "--{$limite}\r\n";
        $cuerpoMime .= "Content-Type: application/pdf; name=\"factura_{$numero}.pdf\"\r\n";
        $cuerpoMime .= "Content-Transfer-Encoding: base64\r\n";
        $cuerpoMime .= "Content-Disposition: attachment; filename=\"factura_{$numero}.pdf\"\r\n\r\n";
        $cuerpoMime .= chunk_split(base64_encode($pdfContenido)) . "\r\n";

        $cuerpoMime .= "--{$limite}\r\n";
        $cuerpoMime .= "Content-Type: text/xml; name=\"factura_{$numero}.xml\"\r\n";
        $cuerpoMime .= "Content-Transfer-Encoding: base64\r\n";
        $cuerpoMime .= "Content-Disposition: attachment; filename=\"factura_{$numero}.xml\"\r\n\r\n";
        $cuerpoMime .= chunk_split(base64_encode($xmlContenido)) . "\r\n";

        $cuerpoMime .= "--{$limite}--\r\n";

        $this->enviarSmtp($para, $asunto, $headers, $cuerpoMime);
    }

    // ── SMTP via stream_socket_client ────────────────────────────────────────

    private function enviarSmtp(string $para, string $asunto, string $headers, string $cuerpo): void
    {
        $ctx    = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $socket = @stream_socket_client("tcp://{$this->smtpHost}:{$this->smtpPort}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

        if ($socket === false) {
            throw new RuntimeException("No se pudo conectar al servidor SMTP ({$this->smtpHost}:{$this->smtpPort}): {$errstr}");
        }

        stream_set_timeout($socket, 15);

        $this->leer($socket);                                // 220 greeting
        $this->cmd($socket, "EHLO localhost");               // EHLO
        $this->cmd($socket, "STARTTLS");                     // iniciar TLS
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $this->cmd($socket, "EHLO localhost");               // EHLO de nuevo post-TLS
        $this->cmd($socket, "AUTH LOGIN");                   // autenticar
        $this->cmd($socket, base64_encode($this->smtpUser)); // usuario
        $this->cmd($socket, base64_encode($this->smtpPass)); // contraseña
        $this->cmd($socket, "MAIL FROM:<{$this->smtpUser}>"); // remitente
        $this->cmd($socket, "RCPT TO:<{$para}>");            // destinatario
        $this->cmd($socket, "DATA");                         // inicio de datos

        $mensaje  = "To: {$para}\r\n";
        $mensaje .= "Subject: {$asunto}\r\n";
        $mensaje .= $headers;
        $mensaje .= "\r\n";
        $mensaje .= $cuerpo;
        $mensaje .= "\r\n.\r\n";

        fwrite($socket, $mensaje);
        $this->leer($socket);   // 250 OK
        $this->cmd($socket, "QUIT");
        fclose($socket);
    }

    /** @param resource $socket */
    private function cmd($socket, string $texto): string
    {
        fwrite($socket, $texto . "\r\n");
        return $this->leer($socket);
    }

    /** @param resource $socket */
    private function leer($socket): string
    {
        $respuesta = '';
        while (!feof($socket)) {
            $linea = fgets($socket, 515);
            if ($linea === false) break;
            $respuesta .= $linea;
            if (isset($linea[3]) && $linea[3] === ' ') break;
        }
        return $respuesta;
    }
}
