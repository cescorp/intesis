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
 * El tipo se valida por el contenido real del archivo (finfo), no solo por la extension,
 * para que un ejecutable renombrado a .pdf no pase.
 */
final class AlmacenArchivosServicio
{
    /** Tope absoluto por archivo; cada modulo puede pedir menos, nunca mas. */
    public const MAX_MB = 80;

    /** Tipos que el navegador puede mostrar en el visor (y su extension en disco). */
    private const EXTENSIONES_VISOR = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
    ];

    /** Para modulos que solo aceptan lo que se puede previsualizar (ej. adjuntos de facturas). */
    public const TIPOS_PDF_E_IMAGENES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Para modulos que aceptan cualquier archivo excepto los peligrosos (ej. modulo Archivos). */
    public const CUALQUIER_TIPO = null;

    /**
     * Extensiones que pueden ejecutarse en Windows o en el servidor. Se bloquean aunque el
     * contenido parezca inocente: el riesgo es que alguien las descargue y las ejecute.
     */
    public const EXTENSIONES_PELIGROSAS = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'msp', 'scr', 'pif', 'cpl', 'dll', 'sys', 'drv',
        'ps1', 'psm1', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'hta', 'jar', 'reg', 'lnk',
        'inf', 'sh', 'bash', 'php', 'phtml', 'phar', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py',
    ];

    /** MIME de binarios ejecutables: delatan un ejecutable renombrado (ej. programa.exe -> foto.jpg). */
    private const MIMES_PELIGROSOS = [
        'application/x-dosexec', 'application/x-msdownload', 'application/x-executable',
        'application/x-sharedlib', 'application/x-mach-binary', 'application/x-elf',
        'application/x-msi', 'text/x-php', 'application/x-httpd-php', 'text/x-shellscript',
    ];

    public function __construct(
        private Configuracion $configuracion,
        private ArchivoModelo $archivoModelo
    ) {
    }

    /** Nombre visible por defecto: el nombre original sin extension. */
    public static function nombreVisible(string $nombreOriginal): string
    {
        return mb_substr(trim(pathinfo($nombreOriginal, PATHINFO_FILENAME)), 0, 150) ?: 'archivo';
    }

    /** Extension original normalizada (minusculas, solo letras/numeros). */
    public static function extensionOriginal(string $nombreOriginal): string
    {
        return substr(preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION))), 0, 10);
    }

    /**
     * Guarda un archivo de $_FILES y lo registra. Devuelve el registro listo para la vista.
     *
     * @param array      $archivo         Un elemento de $_FILES (name, tmp_name, size, error).
     * @param array|null $mimesPermitidos Lista de MIME aceptados, o CUALQUIER_TIPO (todo salvo peligrosos).
     * @param int        $maxMB           Limite del modulo; se recorta a MAX_MB.
     * @param int        $version         Numero de version (1 = archivo nuevo).
     * @param int|null   $originalId      Id de la version 1 cuando $version > 1.
     */
    public function guardar(
        array $archivo, int $empresaId, string $tabla, int $idPadre, int $usuarioId,
        ?array $mimesPermitidos, int $maxMB, int $version = 1, ?int $originalId = null
    ): array {
        $limiteMB = max(1, min($maxMB, self::MAX_MB));
        $this->validarSubida($archivo, $limiteMB);

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $archivo['tmp_name']);
        $extension = $this->extensionSegura($mime, (string) $archivo['name'], $mimesPermitidos);

        $tablaCarpeta = preg_replace('/[^a-z0-9_]/', '', strtolower($tabla));
        $relativo = sprintf('almacenamiento/archivos/adjuntos/empresa_%d/%s/%s', $empresaId, $tablaCarpeta, date('Y/m'));
        $directorio = $this->rutaAbsoluta($relativo);
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new \RuntimeException('No se pudo crear la carpeta de adjuntos.');
        }

        $fisico = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
        $destino = $directorio . DIRECTORY_SEPARATOR . $fisico;
        if (!move_uploaded_file((string) $archivo['tmp_name'], $destino)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        $nombre = self::nombreVisible((string) $archivo['name']);
        try {
            $id = $this->archivoModelo->registrar([
                'empresa_id'  => $empresaId,
                'archivo'     => $fisico,
                'nombre'      => $nombre,
                'tabla'       => $tabla,
                'id_padre'    => $idPadre,
                'ubicacion'   => $relativo . '/' . $fisico,
                'tipo'        => $mime === 'application/pdf' ? 'PDF' : (str_starts_with($mime, 'image/') ? 'IMAGEN' : 'ARCHIVO'),
                'mime'        => $mime,
                'tamano'      => (int) $archivo['size'],
                'version'     => $version,
                'original_id' => $originalId,
            ], $usuarioId);
        } catch (\Throwable $e) {
            // Sin registro en BD el archivo quedaria huerfano e inaccesible: se borra
            @unlink($destino);
            throw $e;
        }

        return [
            'sis_archivos_id'      => $id,
            'sis_archivos_nombre'  => $nombre,
            'sis_archivos_mime'    => $mime,
            'sis_archivos_tamano'  => (int) $archivo['size'],
            'sis_archivos_version' => $version,
        ];
    }

    /** True si el navegador puede mostrar el archivo en el visor (PDF o imagen). */
    public static function esPrevisualizable(?string $mime): bool
    {
        return isset(self::EXTENSIONES_VISOR[(string) $mime]);
    }

    /**
     * Envia el archivo al navegador. Solo PDF e imagenes se muestran en linea; el resto
     * siempre se descarga, para que el navegador no interprete HTML/SVG subidos por usuarios.
     */
    public function servir(array $registro, bool $descargar = false): never
    {
        $ruta = $this->rutaAbsoluta((string) $registro['sis_archivos_ubicacion']);
        if (!is_file($ruta)) {
            throw new \RuntimeException('El archivo no existe en el servidor.');
        }
        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $nombre = str_replace(['"', "\r", "\n"], '', (string) ($registro['sis_archivos_nombre'] ?? 'archivo')) . ($extension !== '' ? '.' . $extension : '');
        $enLinea = !$descargar && self::esPrevisualizable($registro['sis_archivos_mime'] ?? null);

        header('Content-Type: ' . ($enLinea ? $registro['sis_archivos_mime'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('X-Content-Type-Options: nosniff');
        readfile($ruta);
        exit;
    }

    /** Decide la extension en disco y rechaza lo que el modulo no permite o es peligroso. */
    private function extensionSegura(string $mime, string $nombreOriginal, ?array $mimesPermitidos): string
    {
        if ($mimesPermitidos !== self::CUALQUIER_TIPO) {
            if (!isset(self::EXTENSIONES_VISOR[$mime]) || !in_array($mime, $mimesPermitidos, true)) {
                throw new \InvalidArgumentException('Tipo de archivo no permitido. Solo se aceptan PDF e imágenes (JPG, PNG, WEBP, GIF).');
            }
            return self::EXTENSIONES_VISOR[$mime];
        }

        $extension = self::extensionOriginal($nombreOriginal);
        if (in_array($extension, self::EXTENSIONES_PELIGROSAS, true) || in_array($mime, self::MIMES_PELIGROSOS, true)) {
            throw new \InvalidArgumentException('Por seguridad no se permiten archivos ejecutables o scripts (.exe, .bat, .js, .php, etc.).');
        }
        return $extension;
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
