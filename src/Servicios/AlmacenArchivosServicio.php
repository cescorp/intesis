<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use Intesis\Modelos\ArchivoModelo;
use Intesis\Nucleo\Configuracion;

/**
 * Punto unico para subir, mostrar y eliminar adjuntos de cualquier modulo (ver reglas.md).
 *
 * Ubicacion central en disco:
 *   almacenamiento/archivos/adjuntos/empresa_{id}/{tabla}/{año}/{mes}/{codigo-unico}.{ext}
 * El nombre fisico nunca es el que subio el usuario (evita choques y rutas maliciosas);
 * el nombre visible se guarda aparte en sis_archivos_nombre.
 *
 * El tipo se valida por el contenido real del archivo (finfo), no por la extension,
 * para que un ejecutable renombrado a .pdf no pase.
 */
final class AlmacenArchivosServicio
{
    /** Tope absoluto por archivo; cada modulo puede pedir menos, nunca mas. */
    public const MAX_MB = 80;

    /** MIME aceptados por el sistema y la extension con que se guardan. */
    private const EXTENSIONES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
    ];

    /** Conjunto usado por los adjuntos que se pueden previsualizar en el navegador. */
    public const TIPOS_PDF_E_IMAGENES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private Configuracion $configuracion,
        private ArchivoModelo $archivoModelo
    ) {
    }

    /**
     * Guarda un archivo de $_FILES y lo registra. Devuelve el registro listo para la vista.
     *
     * @param array $archivo         Un elemento de $_FILES (name, tmp_name, size, error).
     * @param array $mimesPermitidos Subconjunto de TIPOS_PDF_E_IMAGENES que acepta el modulo.
     * @param int   $maxMB           Limite del modulo; se recorta a MAX_MB.
     */
    public function guardar(array $archivo, int $empresaId, string $tabla, int $idPadre, int $usuarioId, array $mimesPermitidos, int $maxMB): array
    {
        $limiteMB = max(1, min($maxMB, self::MAX_MB));
        $this->validarSubida($archivo, $limiteMB);

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $archivo['tmp_name']);
        if (!isset(self::EXTENSIONES[$mime]) || !in_array($mime, $mimesPermitidos, true)) {
            throw new \InvalidArgumentException('Tipo de archivo no permitido. Solo se aceptan PDF e imágenes (JPG, PNG, WEBP, GIF).');
        }

        $tablaCarpeta = preg_replace('/[^a-z0-9_]/', '', strtolower($tabla));
        $relativo = sprintf('almacenamiento/archivos/adjuntos/empresa_%d/%s/%s', $empresaId, $tablaCarpeta, date('Y/m'));
        $directorio = $this->rutaAbsoluta($relativo);
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new \RuntimeException('No se pudo crear la carpeta de adjuntos.');
        }

        $fisico = bin2hex(random_bytes(16)) . '.' . self::EXTENSIONES[$mime];
        $destino = $directorio . DIRECTORY_SEPARATOR . $fisico;
        if (!move_uploaded_file((string) $archivo['tmp_name'], $destino)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        // Nombre visible: el original sin extension (el usuario puede cambiarlo despues)
        $nombre = mb_substr(trim(pathinfo((string) $archivo['name'], PATHINFO_FILENAME)), 0, 150) ?: 'archivo';

        try {
            $id = $this->archivoModelo->registrar([
                'empresa_id' => $empresaId,
                'archivo'    => $fisico,
                'nombre'     => $nombre,
                'tabla'      => $tabla,
                'id_padre'   => $idPadre,
                'ubicacion'  => $relativo . '/' . $fisico,
                'tipo'       => $mime === 'application/pdf' ? 'PDF' : 'IMAGEN',
                'mime'       => $mime,
                'tamano'     => (int) $archivo['size'],
            ], $usuarioId);
        } catch (\Throwable $e) {
            // Sin registro en BD el archivo quedaria huerfano e inaccesible: se borra
            @unlink($destino);
            throw $e;
        }

        return [
            'sis_archivos_id'     => $id,
            'sis_archivos_nombre' => $nombre,
            'sis_archivos_mime'   => $mime,
            'sis_archivos_tamano' => (int) $archivo['size'],
        ];
    }

    /** Envia el archivo al navegador para verlo en linea (visor PDF/imagen). */
    public function servir(array $registro): never
    {
        $ruta = $this->rutaAbsoluta((string) $registro['sis_archivos_ubicacion']);
        if (!is_file($ruta)) {
            throw new \RuntimeException('El archivo no existe en el servidor.');
        }
        $nombre = (string) ($registro['sis_archivos_nombre'] ?? 'archivo') . '.' . pathinfo($ruta, PATHINFO_EXTENSION);
        header('Content-Type: ' . ($registro['sis_archivos_mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $nombre) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($ruta);
        exit;
    }

    private function validarSubida(array $archivo, int $limiteMB): void
    {
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException("El archivo supera el tamaño permitido por el servidor.");
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($archivo['tmp_name'] ?? ''))) {
            throw new \InvalidArgumentException('No se recibió el archivo. Intente de nuevo.');
        }
        if ((int) $archivo['size'] > $limiteMB * 1024 * 1024) {
            throw new \InvalidArgumentException("El archivo supera el máximo de {$limiteMB} MB.");
        }
    }

    private function rutaAbsoluta(string $relativa): string
    {
        return $this->configuracion->raiz() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativa);
    }
}
