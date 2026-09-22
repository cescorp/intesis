<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use Intesis\Nucleo\Configuracion;
use RuntimeException;

final class LicenciaCifradoServicio
{
    private const CIFRADO = 'aes-256-gcm';
    private const LARGO_IV = 12;
    private const LARGO_TAG = 16;

    private string $clave;

    public function __construct(Configuracion $configuracion)
    {
        $claveBase64 = $configuracion->obtener('LICENCIA_CLAVE_SECRETA', '') ?? '';
        $clave = $claveBase64 !== '' ? base64_decode($claveBase64, true) : false;
        if ($clave === false || strlen($clave) !== 32) {
            throw new RuntimeException('LICENCIA_CLAVE_SECRETA no está configurada correctamente en .env (debe ser una clave de 32 bytes en base64).');
        }
        $this->clave = $clave;
    }

    /**
     * ***************************************************************************
     * * CIFRA EL PAYLOAD DE LICENCIA CON AES-256-GCM (CONFIDENCIALIDAD + INTEGRIDAD).
     * * DEVUELVE UN STRING BASE64 OPACO: IV + TAG DE AUTENTICACION + TEXTO CIFRADO.
     * ***************************************************************************
     */
    public function cifrar(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo preparar el payload de licencia.');
        }

        $iv  = random_bytes(self::LARGO_IV);
        $tag = '';
        $cifrado = openssl_encrypt($json, self::CIFRADO, $this->clave, OPENSSL_RAW_DATA, $iv, $tag, '', self::LARGO_TAG);
        if ($cifrado === false) {
            throw new RuntimeException('No se pudo cifrar la licencia.');
        }

        return base64_encode($iv . $tag . $cifrado);
    }

    /**
     * ***************************************************************************
     * * DESCIFRA Y VERIFICA UN BLOB DE LICENCIA.
     * * SI EL ARCHIVO FUE EDITADO/CORROMPIDO O VIENE DE OTRA CLAVE, LANZA EXCEPCION.
     * ***************************************************************************
     */
    public function descifrar(string $blob): array
    {
        $datos = base64_decode(trim($blob), true);
        if ($datos === false || strlen($datos) <= self::LARGO_IV + self::LARGO_TAG) {
            throw new RuntimeException('Licencia inválida o alterada.');
        }

        $iv      = substr($datos, 0, self::LARGO_IV);
        $tag     = substr($datos, self::LARGO_IV, self::LARGO_TAG);
        $cifrado = substr($datos, self::LARGO_IV + self::LARGO_TAG);

        $json = openssl_decrypt($cifrado, self::CIFRADO, $this->clave, OPENSSL_RAW_DATA, $iv, $tag);
        if ($json === false) {
            throw new RuntimeException('Licencia inválida o alterada.');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Licencia inválida o alterada.');
        }

        return $payload;
    }
}
