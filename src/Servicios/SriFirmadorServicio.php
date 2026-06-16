<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use RuntimeException;

final class SriFirmadorServicio
{
    /**
     * Firma un XML usando sri2.jar.
     *
     * Invocación del JAR:
     *   java -jar <sri2.jar> <cert.p12> <clave_cert> <xml_entrada> <dir_salida/> <nombre_archivo>
     *
     * @param string $xmlSinFirmar  Contenido XML sin firmar
     * @param string $rutaCert      Ruta absoluta al archivo .p12
     * @param string $claveCert     Contraseña del certificado
     * @param string $rutaJar       Ruta absoluta a sri2.jar
     * @param string $dirTemporal   Directorio para archivos temporales
     * @param string $nombreArchivo Nombre del archivo de salida (sin directorio)
     * @return string Contenido XML firmado
     */
    public function firmar(
        string $xmlSinFirmar,
        string $rutaCert,
        string $claveCert,
        string $rutaJar,
        string $dirTemporal,
        string $nombreArchivo
    ): string {
        if (!is_file($rutaJar)) {
            throw new RuntimeException('sri2.jar no encontrado en: ' . $rutaJar);
        }
        if (!is_file($rutaCert)) {
            throw new RuntimeException('Certificado digital no encontrado en: ' . $rutaCert);
        }

        if (!is_dir($dirTemporal)) {
            mkdir($dirTemporal, 0775, true);
        }

        $rutaXmlEntrada = $dirTemporal . DIRECTORY_SEPARATOR . 'unsigned_' . $nombreArchivo;
        $rutaXmlFirmado = $dirTemporal . DIRECTORY_SEPARATOR . $nombreArchivo;

        file_put_contents($rutaXmlEntrada, $xmlSinFirmar);

        // Limpiar firmado anterior si existe
        if (is_file($rutaXmlFirmado)) {
            unlink($rutaXmlFirmado);
        }

        $comando = sprintf(
            'java -jar %s %s %s %s %s %s',
            escapeshellarg($rutaJar),
            escapeshellarg($rutaCert),
            escapeshellarg($claveCert),
            escapeshellarg($rutaXmlEntrada),
            escapeshellarg($dirTemporal . DIRECTORY_SEPARATOR),
            escapeshellarg($nombreArchivo)
        );

        $salida = [];
        $codigo = 0;
        exec($comando . ' 2>&1', $salida, $codigo);

        // Limpiar XML de entrada temporal
        if (is_file($rutaXmlEntrada)) {
            unlink($rutaXmlEntrada);
        }

        if (!is_file($rutaXmlFirmado)) {
            throw new RuntimeException(
                'Error al firmar XML con sri2.jar (exit=' . $codigo . '): ' . implode(' | ', $salida)
            );
        }

        $xmlFirmado = file_get_contents($rutaXmlFirmado);
        if (is_file($rutaXmlFirmado)) {
            unlink($rutaXmlFirmado);
        }

        if ($xmlFirmado === false || $xmlFirmado === '') {
            throw new RuntimeException('El XML firmado resultó vacío.');
        }

        return $xmlFirmado;
    }
}
