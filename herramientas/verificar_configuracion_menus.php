<?php

declare(strict_types=1);

$caso = $argv[1] ?? 'estados';
$rutas = [
    'estados' => ['/sistema/configuracion/estados', ['tablaEstados', 'modalEstado']],
    'mensajes' => ['/sistema/configuracion/mensajes-error', ['tablaMensajesError', 'modalMensajeError']],
    'tipos' => ['/sistema/configuracion/tipos-documento', ['tablaTiposDocumento', 'modalTipoDocumento', 'modalSecuenciasTipo']],
];

[$ruta, $fragmentos] = $rutas[$caso] ?? $rutas['estados'];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/intesis' . $ruta;
$_SERVER['SCRIPT_NAME'] = '/intesis/index.php';

session_save_path(__DIR__ . '/../almacenamiento/sesiones');
session_name('INTESIS_SESION');
session_start();

$_SESSION['usuario'] = [
    'id' => 1,
    'empresa_id' => 1,
    'perfil_id' => 1,
    'nombre' => 'ADMINISTRADOR SISTEMA',
    'correo' => 'cescorp@hotmail.es',
    'perfil' => 'SUPERUSUARIO',
    'empresa' => 'INTITECH',
];

ob_start();
require __DIR__ . '/../index.php';
$contenido = ob_get_clean();

$ok = true;
foreach ($fragmentos as $fragmento) {
    if (!str_contains($contenido, $fragmento)) {
        $ok = false;
        break;
    }
}

echo $ok ? "CONFIGURACION_MENU_" . strtoupper($caso) . "_OK\n" : "CONFIGURACION_MENU_" . strtoupper($caso) . "_ERROR\n";
