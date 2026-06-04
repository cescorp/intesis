<?php

declare(strict_types=1);

function renderizarRutaInventario(string $ruta): string
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/intesis' . $ruta;
    $_SERVER['SCRIPT_NAME'] = '/intesis/index.php';
    $_SESSION['usuario'] = [
        'id' => 1,
        'empresa_id' => 1,
        'perfil_id' => 1,
        'nombre' => 'ADMINISTRADOR SISTEMA',
        'correo' => 'cescorp@hotmail.es',
        'perfil' => 'SUPERUSUARIO',
        'perfil_codigo' => 'SUPERUSUARIO',
        'empresa' => 'INTITECH',
    ];

    ob_start();
    require __DIR__ . '/../index.php';

    return ob_get_clean();
}

session_save_path(__DIR__ . '/../almacenamiento/sesiones');
session_name('INTESIS_SESION');
session_start();

$ruta = $argv[1] ?? '/inventario/productos';
$contenido = renderizarRutaInventario($ruta);

$esperados = [
    '/inventario/productos' => ['tablaProductos', 'modalProducto', 'modalCategoriaRapida', 'modalMarcaRapida'],
    '/inventario/categorias' => ['tablaCategorias', 'modalCategoria'],
    '/inventario/marcas' => ['tablaMarcas', 'modalMarca'],
];

$ok = true;
foreach ($esperados[$ruta] ?? [] as $texto) {
    $ok = $ok && str_contains($contenido, $texto);
}

echo $ok ? "INVENTARIO_PAGINA_OK\n" : "INVENTARIO_PAGINA_ERROR\n";
