<?php
// Generador independiente de licencias locales para Facturas SRI.
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'logger.php';

$raizIntitech = __DIR__;
$rutaLicenciaIntitech = $raizIntitech . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'licencia.dat';
$rutaRegistroIntitech = $raizIntitech . DIRECTORY_SEPARATOR . 'registro.log';
$claveInternaIntitech = 'INTITECH_FACTURAS_SRI_2026_CONTROL_LOCAL';
$mensajeIntitech = '';
$licenciaGeneradaIntitech = '';

// Registra la generacion de licencias en registro.log.
function intitech_registrar(string $mensaje): void
{
    global $rutaRegistroIntitech;

    file_put_contents(
        $rutaRegistroIntitech,
        date('Y-m-d H:i:s P') . ' - ' . $mensaje . PHP_EOL,
        FILE_APPEND
    );
}

// Normaliza texto recibido del formulario.
function intitech_texto(string $clave): string
{
    return trim((string) ($_POST[$clave] ?? ''));
}

// Calcula un codigo corto de control para detectar cambios manuales en la licencia.
function intitech_codigo_control(array $datos): string
{
    global $claveInternaIntitech;

    $base = implode('|', [
        $datos['cliente'] ?? '',
        $datos['ruc'] ?? '',
        $datos['producto'] ?? '',
        $datos['vence'] ?? '',
        implode(',', $datos['modulos'] ?? []),
        $claveInternaIntitech,
    ]);

    return strtoupper(substr(hash('sha256', $base), 0, 8));
}

// Codifica texto en Base64 URL para que el archivo no muestre datos obvios.
function intitech_base64_url(string $texto): string
{
    return rtrim(strtr(base64_encode($texto), '+/', '-_'), '=');
}

// Aplica una transformacion reversible simple para ocultar fechas y separadores visibles.
function intitech_transformar(string $texto, string $semilla): string
{
    $resultado = '';
    $largoSemilla = strlen($semilla);
    for ($indice = 0, $largo = strlen($texto); $indice < $largo; $indice++) {
        $resultado .= chr(ord($texto[$indice]) ^ ord($semilla[$indice % $largoSemilla]));
    }

    return $resultado;
}

// Divide un bloque en segmentos de longitud variable para que no parezca una fecha o JSON.
function intitech_segmentar(string $texto): string
{
    return implode('.', str_split($texto, 17));
}

// Crea el contenido ofuscado que se guarda en licencia.dat.
function intitech_generar_licencia(array $datos): string
{
    global $claveInternaIntitech;

    $fecha = DateTime::createFromFormat('Y-m-d', (string) $datos['vence']);
    if (!$fecha) {
        throw new RuntimeException('La fecha de caducidad no es valida.');
    }

    $semilla = hash('sha256', ($datos['ruc'] ?? '') . '|FSRI|' . $claveInternaIntitech, true);
    $datosJson = json_encode($datos, JSON_UNESCAPED_UNICODE);
    if ($datosJson === false) {
        throw new RuntimeException('No se pudo preparar la licencia.');
    }

    $bloqueDatos = intitech_segmentar(intitech_base64_url(intitech_transformar($datosJson, $semilla)));
    $bloqueFecha = intitech_segmentar(intitech_base64_url(intitech_transformar($fecha->format('Ymd'), $semilla)));
    $ruidoInicial = substr(strtoupper(hash('crc32b', $datos['ruc'] . $datos['vence'])), 0, 4);
    $ruidoFinal = substr(strtoupper(hash('sha1', $datos['cliente'] . $datos['vence'])), 0, 6);
    $codigoControl = intitech_codigo_control($datos);

    return 'FSRI-LIC::' . $ruidoInicial . ':' . $bloqueDatos . ':' . $bloqueFecha . ':' . $ruidoFinal . '::' . $codigoControl;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $modulos = $_POST['modulos'] ?? [];
        $modulos = is_array($modulos) ? array_values(array_map('strval', $modulos)) : [];
        $datosLicencia = [
            'cliente' => intitech_texto('cliente'),
            'ruc' => intitech_texto('ruc'),
            'producto' => 'FACTURAS_SRI',
            'vence' => intitech_texto('vence'),
            'modulos' => $modulos,
            'emitida_por' => 'INTITECH',
            'emitida_el' => date('Y-m-d H:i:s'),
        ];

        if ($datosLicencia['cliente'] === '' || $datosLicencia['ruc'] === '' || $datosLicencia['vence'] === '') {
            throw new RuntimeException('Cliente, RUC y fecha de caducidad son obligatorios.');
        }
        if ($modulos === []) {
            throw new RuntimeException('Debe seleccionar al menos un modulo.');
        }

        $licenciaGeneradaIntitech = intitech_generar_licencia($datosLicencia);
        if (!is_dir(dirname($rutaLicenciaIntitech))) {
            mkdir(dirname($rutaLicenciaIntitech), 0777, true);
        }
        file_put_contents($rutaLicenciaIntitech, $licenciaGeneradaIntitech);
        intitech_registrar('Se genero licencia.dat para RUC ' . $datosLicencia['ruc'] . ' con caducidad ' . $datosLicencia['vence'] . '.');
        $mensajeIntitech = 'Licencia generada correctamente en config/licencia.dat.';
    } catch (Throwable $excepcion) {
        logger_registrar_error_backend($excepcion->getFile(), $excepcion);
        $mensajeIntitech = 'Error: ' . $excepcion->getMessage();
    }
}

$modulosDisponiblesIntitech = ['panel', 'administrar', 'consultar', 'xml', 'txt', 'bot'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Intitech - Generador de Licencias</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f5f7; margin: 0; padding: 24px; color: #1f2937; }
        .contenedor { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #d8dee6; border-radius: 8px; padding: 24px; }
        h1 { margin-top: 0; font-size: 24px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input[type="text"], input[type="date"], textarea { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #b9c2cf; border-radius: 6px; font-size: 14px; }
        textarea { min-height: 120px; resize: vertical; }
        .modulos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
        .acciones { margin-top: 18px; }
        button { background: #14532d; color: #fff; border: 0; border-radius: 6px; padding: 10px 16px; font-weight: 700; cursor: pointer; }
        .mensaje { margin: 16px 0; padding: 12px; border-radius: 6px; background: #eef6ff; border: 1px solid #bfdbfe; }
        .salida { margin-top: 18px; }
        @media (max-width: 700px) { .grid, .modulos { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main class="contenedor">
        <h1>Generador de licencias Facturas SRI</h1>
        <?php if ($mensajeIntitech !== ''): ?>
            <div class="mensaje"><?php echo htmlspecialchars($mensajeIntitech, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="grid">
                <div>
                    <label for="cliente">Cliente</label>
                    <input type="text" id="cliente" name="cliente" value="<?php echo htmlspecialchars(intitech_texto('cliente'), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div>
                    <label for="ruc">RUC</label>
                    <input type="text" id="ruc" name="ruc" value="<?php echo htmlspecialchars(intitech_texto('ruc'), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div>
                    <label for="vence">Fecha de caducidad</label>
                    <input type="date" id="vence" name="vence" value="<?php echo htmlspecialchars(intitech_texto('vence') ?: date('Y-m-d', strtotime('+1 year')), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>
            <div class="acciones">
                <label>Modulos</label>
                <div class="modulos">
                    <?php foreach ($modulosDisponiblesIntitech as $modulo): ?>
                        <label>
                            <input type="checkbox" name="modulos[]" value="<?php echo htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8'); ?>" checked>
                            <?php echo htmlspecialchars(strtoupper($modulo), ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="acciones">
                <button type="submit">Generar licencia.dat</button>
            </div>
        </form>
        <?php if ($licenciaGeneradaIntitech !== ''): ?>
            <div class="salida">
                <label for="licencia">Contenido generado</label>
                <textarea id="licencia" readonly><?php echo htmlspecialchars($licenciaGeneradaIntitech, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
