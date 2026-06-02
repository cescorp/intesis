<?php

/******************************************************************************/
/*                                                                            */
/*  FIRMADOR ELECTRONICO USANDO JAVA JAR                                      */
/*                                                                            */
/******************************************************************************/

class FirmadorElectronico
{
    private string $rutaJar;
    private string $rutaSalidaFirmados;

    public function __construct(string $rutaJar, string $rutaSalidaFirmados)
    {
        $this->rutaJar = $rutaJar;
        $this->rutaSalidaFirmados = $rutaSalidaFirmados;
    }

    /**************************************************************************/
    /*  FIRMA EL XML Y RETORNA RESULTADO                                       */
    /**************************************************************************/
    public function firmarXml(string $rutaCertificado, string $claveCertificado, string $rutaXmlGenerado, string $nombreArchivo): array
    {
        if (!file_exists($this->rutaJar)) {
            throw new Exception('No se encontro el archivo sri2.jar en tools/java/dist');
        }

        if (!file_exists($rutaXmlGenerado)) {
            throw new Exception('No se encontro el XML generado: ' . $rutaXmlGenerado);
        }

        $rutaXmlFirmado = $this->rutaSalidaFirmados . DIRECTORY_SEPARATOR . $nombreArchivo;
        if (file_exists($rutaXmlFirmado)) {
            unlink($rutaXmlFirmado);
        }

        $comando = sprintf(
            'java -jar %s %s %s %s %s %s',
            escapeshellarg($this->rutaJar),
            escapeshellarg($rutaCertificado),
            escapeshellarg($claveCertificado),
            escapeshellarg($rutaXmlGenerado),
            escapeshellarg($this->rutaSalidaFirmados . DIRECTORY_SEPARATOR),
            escapeshellarg($nombreArchivo)
        );

        $salida = [];
        $codigo = 0;
        exec($comando . ' 2>&1', $salida, $codigo);

        if (!file_exists($rutaXmlFirmado)) {
            throw new Exception('Error al firmar XML. ExitCode=' . $codigo . '. Detalle: ' . implode(' | ', $salida));
        }

        return [
            'codigo' => $codigo,
            'salida' => $salida,
            'ruta_xml_firmado' => $rutaXmlFirmado,
        ];
    }
}
