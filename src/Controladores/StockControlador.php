<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\StockModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class StockControlador
{
    use ControladorComun;

    private const COLUMNAS_CSV = ['codigo_producto', 'nombre_producto', 'marca', 'codigo_bodega', 'cantidad_inicial', 'costo_unitario', 'pvp'];

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private StockModelo $stockModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA LA CONSULTA GLOBAL DE STOCK POR BODEGAS.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/stock/ver');
        $verTodas = $this->esSuperusuario($usuario);
        $empresaId = $verTodas ? (int) ($_GET['empresa_id'] ?? $usuario['empresa_id']) : (int) $usuario['empresa_id'];

        $this->vista->renderizar('inventario/stock', [
            'titulo' => 'Stock',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'empresas' => $this->stockModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'empresaSeleccionada' => $empresaId,
            'bodegas' => $this->stockModelo->listarBodegas($empresaId),
            'stock' => $this->stockModelo->listarStockGlobal($empresaId),
            'esSuperusuario' => $verTodas,
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos(['USUARIO_DATOS_OBLIGATORIOS']),
        ]);
    }

    /**
     * ***************************************************************************
     * * REGISTRA SALDO INICIAL MANUAL POR PRODUCTO Y BODEGA.
     * ***************************************************************************
     */
    public function registrar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/stock/registrar');
        try {
            $empresaId = $this->obtenerEmpresaPermitida($usuario, (int) ($_POST['empresa_id'] ?? 0));
            $producto = $this->stockModelo->buscarProductoPorCodigo($empresaId, strtoupper(trim((string) ($_POST['codigo_producto'] ?? ''))));
            $bodega = $this->stockModelo->buscarBodegaPorCodigo($empresaId, strtoupper(trim((string) ($_POST['codigo_bodega'] ?? ''))));
            if (!$producto || !$bodega) {
                throw new \InvalidArgumentException('Producto y bodega deben existir.');
            }
            $datos = $this->normalizarSaldo($empresaId, (int) $producto['inv_producto_id'], (int) $bodega['inv_bodega_id']);
            $this->stockModelo->registrarSaldoInicial($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Saldo registrado', 'El saldo inicial fue registrado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('REGISTRAR STOCK', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/stock');
    }

    /**
     * ***************************************************************************
     * * PREVISUALIZA ARCHIVO CSV DE IMPORTACION DE STOCK.
     * ***************************************************************************
     */
    public function importar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/stock/importar');
        try {
            $empresaId = $this->obtenerEmpresaPermitida($usuario, (int) ($_POST['empresa_id'] ?? 0));
            $accion = $this->validarOpcion((string) ($_POST['accion_stock'] ?? 'sumar'), ['sumar', 'reemplazar', 'saltar']);
            $productoInexistente = $this->validarOpcion((string) ($_POST['producto_inexistente'] ?? 'crear'), ['crear', 'saltar']);
            $archivo = $this->obtenerArchivoCsv();
            $respaldo = $this->guardarCsv($archivo, $empresaId, (int) $usuario['id']);
            $filas = $this->previsualizarCsv((string) $archivo['tmp_name'], $empresaId, $accion, $productoInexistente);
            $_SESSION['stock_importacion'] = [
                'empresa_id' => $empresaId,
                'accion' => $accion,
                'producto_inexistente' => $productoInexistente,
                'filas' => $filas,
                'archivo_id' => $respaldo,
            ];
            $this->responderJson(true, 'PREVISUALIZACION_OK', 'Archivo previsualizado correctamente.', [
                'filas' => $filas,
                'total' => count($filas),
                'validas' => count(array_filter($filas, fn (array $fila): bool => $fila['estado'] === 'OK')),
                'omitidas' => count(array_filter($filas, fn (array $fila): bool => $fila['estado'] !== 'OK')),
                'subidos' => 1,
            ]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('IMPORTAR STOCK', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * CONFIRMA IMPORTACION PREVISUALIZADA EN SESION.
     * ***************************************************************************
     */
    public function confirmarImportacion(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/stock/confirmar-importacion');
        try {
            $importacion = $_SESSION['stock_importacion'] ?? null;
            if (!$importacion) {
                throw new \InvalidArgumentException('No existe una previsualizacion pendiente.');
            }
            $empresaId = $this->obtenerEmpresaPermitida($usuario, (int) $importacion['empresa_id']);
            $registradas = 0;
            $omitidas = 0;
            $productosCreados = [];
            foreach ($importacion['filas'] as $fila) {
                if ($fila['estado'] !== 'OK') {
                    $omitidas++;
                    continue;
                }
                $productoId = (int) $fila['producto_id'];
                if ($productoId <= 0) {
                    if ($importacion['producto_inexistente'] === 'saltar') {
                        $omitidas++;
                        continue;
                    }
                    $codigoUpper = strtoupper($fila['codigo_producto']);
                    if (isset($productosCreados[$codigoUpper])) {
                        $productoId = $productosCreados[$codigoUpper];
                    } else {
                        $categoriaId = $this->stockModelo->obtenerOCrearCategoriaGeneral($empresaId, (int) $usuario['id']);
                        $marcaId = $this->stockModelo->obtenerOCrearMarca($empresaId, $fila['marca'], (int) $usuario['id']);
                        $productoId = $this->stockModelo->crearProductoImportado([
                            'empresa_id'  => $empresaId,
                            'categoria_id'=> $categoriaId,
                            'marca_id'    => $marcaId,
                            'codigo'      => $fila['codigo_producto'],
                            'nombre'      => $fila['nombre_producto'],
                            'descripcion' => 'IMPORTACION MASIVA',
                            'costo'       => $fila['costo'],
                        ], (int) $usuario['id']);
                        $productosCreados[$codigoUpper] = $productoId;
                    }
                }
                $this->stockModelo->registrarSaldoInicial([
                    'empresa_id'  => $empresaId,
                    'producto_id' => $productoId,
                    'bodega_id'   => (int) $fila['bodega_id'],
                    'cantidad'    => $fila['cantidad'],
                    'costo'       => $fila['costo'],
                    'descripcion' => 'IMPORTACION MASIVA',
                    'accion'      => $importacion['accion'],
                    'referencia'  => 'IMPORTACION MASIVA',
                ], (int) $usuario['id']);
                $this->stockModelo->actualizarPvp($empresaId, $productoId, (float) $fila['pvp'], (int) $usuario['id']);
                $registradas++;
            }
            unset($_SESSION['stock_importacion']);
            $this->responderJson(true, 'IMPORTACION_CONFIRMADA', 'Importacion confirmada correctamente.', ['registradas' => $registradas, 'omitidas' => $omitidas]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CONFIRMAR IMPORTACION STOCK', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * DESCARGA PLANTILLA CSV DE STOCK.
     * ***************************************************************************
     */
    public function plantilla(): void
    {
        $this->exigirSesion();
        $this->exigirPermiso('/inventario/stock/plantilla');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_stock.csv"');
        echo implode(';', self::COLUMNAS_CSV) . "\n";
        echo "COD001;LAMPARA LED;GENIUS;BODEGA001;5;12.40;13.64\n";
        exit;
    }

    /**
     * ***************************************************************************
     * * DEVUELVE PRECIOS DE PRODUCTO PARA MODAL.
     * ***************************************************************************
     */
    public function precios(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/stock/ver');
        try {
            $empresaId = $this->obtenerEmpresaPermitida($usuario, (int) ($_GET['empresa_id'] ?? 0));
            $productoId = (int) ($_GET['producto_id'] ?? 0);
            if (!$this->stockModelo->productoPerteneceEmpresa($empresaId, $productoId)) {
                throw new \InvalidArgumentException('Producto no valido.');
            }
            $this->responderJson(true, 'PRECIOS_OK', 'Precios listados.', [
                'precios' => $this->stockModelo->listarPreciosProducto($empresaId, $productoId),
            ]);
        } catch (Throwable $excepcion) {
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * DEVUELVE CODIGOS DE PROVEEDOR PARA MODAL.
     * ***************************************************************************
     */
    public function codigosProveedor(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/stock/ver');
        $productoId = (int) ($_GET['producto_id'] ?? 0);
        $empresaId = $this->obtenerEmpresaPermitida($usuario, (int) ($_GET['empresa_id'] ?? 0));
        if (!$this->stockModelo->productoPerteneceEmpresa($empresaId, $productoId)) {
            $this->responderJson(false, 'ERROR_VALIDACION', 'Producto no valido.');
        }
        $this->responderJson(true, 'CODIGOS_OK', 'Codigos listados.', [
            'codigos' => $this->stockModelo->listarCodigosProveedor($productoId),
        ]);
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DEL FORMULARIO MANUAL.
     * ***************************************************************************
     */
    private function normalizarSaldo(int $empresaId, int $productoId, int $bodegaId): array
    {
        $cantidad = $this->normalizarNumero($_POST['cantidad'] ?? 0);
        $costo = $this->normalizarNumero($_POST['costo'] ?? 0);
        if (!is_numeric($cantidad) || !is_numeric($costo) || (float) $cantidad < 0 || (float) $costo < 0) {
            throw new \InvalidArgumentException('Cantidad y costo deben ser numeros mayores o iguales a cero.');
        }

        return [
            'empresa_id' => $empresaId,
            'producto_id' => $productoId,
            'bodega_id' => $bodegaId,
            'cantidad' => $cantidad,
            'costo' => $costo,
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'accion' => $this->validarOpcion((string) ($_POST['accion_stock'] ?? 'sumar'), ['sumar', 'reemplazar', 'saltar']),
            'referencia' => 'SALDO INICIAL MANUAL',
        ];
    }

    /**
     * ***************************************************************************
     * * PREVISUALIZA CSV VALIDANDO COLUMNAS, DECIMALES Y RELACIONES.
     * ***************************************************************************
     */
    private function previsualizarCsv(string $ruta, int $empresaId, string $accion, string $productoInexistente): array
    {
        $manejador = fopen($ruta, 'rb');
        if (!$manejador) {
            throw new \InvalidArgumentException('No se pudo leer el archivo CSV.');
        }
        $cabecera = fgetcsv($manejador, 0, ';');
        if ($cabecera) {
            $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cabecera[0]);
        }
        if ($cabecera !== self::COLUMNAS_CSV) {
            fclose($manejador);
            throw new \InvalidArgumentException('La cabecera del CSV no coincide con la plantilla.');
        }

        $productos = $this->stockModelo->cargarProductosPorEmpresa($empresaId);
        $bodegas   = $this->stockModelo->cargarBodegasPorEmpresa($empresaId);

        $filas = [];
        $linea = 2;
        while (($datos = fgetcsv($manejador, 0, ';')) !== false) {
            if (count($datos) !== count(self::COLUMNAS_CSV)) {
                $filas[] = $this->filaError($linea, $datos, 'Cantidad de columnas incorrecta.');
                $linea++;
                continue;
            }
            $fila = array_combine(self::COLUMNAS_CSV, array_map('trim', $datos));
            $errores  = [];
            $avisos   = [];
            foreach (['cantidad_inicial', 'costo_unitario', 'pvp'] as $campo) {
                $errores = array_merge($errores, $this->validarCampoNumerico($campo, (string) $fila[$campo]));
            }
            if (!$errores && (float) $fila['pvp'] === 0.0) {
                $avisos[] = 'PVP en 0, se actualizara a 0.';
            }
            $codigoProd = strtoupper($fila['codigo_producto']);
            $codigoBod  = strtoupper($fila['codigo_bodega']);
            $producto   = $productos[$codigoProd] ?? null;
            $bodega     = $bodegas[$codigoBod]    ?? null;
            if (!$bodega) {
                $errores[] = 'Bodega no existe.';
            }
            if (!$producto && $productoInexistente === 'saltar') {
                $errores[] = 'Producto no existe, sera omitido.';
            }
            if (!$producto && ($fila['nombre_producto'] === '' || $fila['marca'] === '')) {
                $errores[] = 'Producto nuevo requiere nombre y marca.';
            }
            $estado  = $errores ? 'OMITIDA' : 'OK';
            $mensaje = $errores
                ? implode(' ', array_unique($errores))
                : (($producto ? 'Producto existente.' : 'Producto nuevo.') . ($avisos ? ' ' . implode(' ', $avisos) : ''));
            $filas[] = [
                'linea'           => $linea,
                'codigo_producto' => $codigoProd,
                'nombre_producto' => $fila['nombre_producto'],
                'marca'           => $fila['marca'] !== '' ? strtoupper($fila['marca']) : 'GENERAL',
                'codigo_bodega'   => $codigoBod,
                'cantidad'        => $fila['cantidad_inicial'],
                'costo'           => $fila['costo_unitario'],
                'pvp'             => $fila['pvp'],
                'producto_id'     => $producto ? (int) $producto['inv_producto_id'] : 0,
                'bodega_id'       => $bodega   ? (int) $bodega['inv_bodega_id']    : 0,
                'accion'          => $accion,
                'estado'          => $estado,
                'mensaje'         => $mensaje,
            ];
            $linea++;
        }
        fclose($manejador);

        return $filas;
    }

    /**
     * ***************************************************************************
     * * VALIDA UN CAMPO NUMERICO DEL CSV E INDICA EL VALOR RECIBIDO EN EL ERROR.
     * ***************************************************************************
     */
    private function validarCampoNumerico(string $campo, string $valor): array
    {
        if ($valor === '') {
            return ["{$campo} invalido: el valor esta vacio."];
        }
        if (str_contains($valor, ',')) {
            $normalizado = str_replace(',', '.', $valor);
            if (is_numeric($normalizado) && (float) $normalizado >= 0) {
                return ["Use punto decimal en {$campo} (recibido: \"{$valor}\")."];
            }

            return ["{$campo} invalido: \"{$valor}\" no es un numero valido."];
        }
        if (!is_numeric($valor) || (float) $valor < 0) {
            return ["{$campo} invalido: \"{$valor}\" no es un numero valido."];
        }

        return [];
    }

    private function filaError(int $linea, array $datos, string $mensaje): array
    {
        return [
            'linea'           => $linea,
            'codigo_producto' => $datos[0] ?? '',
            'nombre_producto' => $datos[1] ?? '',
            'marca'           => $datos[2] ?? '',
            'codigo_bodega'   => $datos[3] ?? '',
            'cantidad'        => $datos[4] ?? '',
            'costo'           => $datos[5] ?? '',
            'pvp'             => $datos[6] ?? '',
            'producto_id'     => 0,
            'bodega_id'       => 0,
            'accion'          => '',
            'estado'          => 'OMITIDA',
            'mensaje'         => $mensaje,
        ];
    }

    /**
     * ***************************************************************************
     * * GUARDA CSV FISICO Y REGISTRA RESPALDO EN SIS_ARCHIVOS.
     * ***************************************************************************
     */
    private function guardarCsv(array $archivo, int $empresaId, int $usuarioId): int
    {
        $directorioRelativo = 'almacenamiento/archivos/stock/empresa_' . $empresaId;
        $directorio = $this->configuracion->raiz() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directorioRelativo);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $nombre = 'stock_' . date('Ymd_His') . '.csv';
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;
        if (!copy((string) $archivo['tmp_name'], $destino)) {
            throw new \InvalidArgumentException('No se pudo guardar respaldo CSV.');
        }

        return $this->stockModelo->registrarArchivoStock($empresaId, $nombre, $directorioRelativo . '/' . $nombre, $usuarioId);
    }

    /**
     * ***************************************************************************
     * * OBTIENE CSV SUBIDO POR LA FUNCION GLOBAL DE ARCHIVOS.
     * ***************************************************************************
     */
    private function obtenerArchivoCsv(): array
    {
        $entrada = $_FILES['archivos'] ?? null;
        if (!$entrada || empty($entrada['name'][0])) {
            throw new \InvalidArgumentException('Seleccione un archivo CSV.');
        }
        $archivo = [
            'name' => $entrada['name'][0],
            'tmp_name' => $entrada['tmp_name'][0],
            'error' => $entrada['error'][0],
            'size' => $entrada['size'][0],
        ];
        if ((int) $archivo['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('No se pudo cargar el CSV.');
        }
        if ((int) $archivo['size'] > 7340032) {
            throw new \InvalidArgumentException('El CSV debe pesar maximo 7MB.');
        }
        if (strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION)) !== 'csv') {
            throw new \InvalidArgumentException('Solo se permite archivo CSV.');
        }

        return $archivo;
    }

    private function normalizarNumero(mixed $valor): string
    {
        $numero = trim((string) $valor);
        if (str_contains($numero, ',')) {
            throw new \InvalidArgumentException('Use punto decimal, no coma.');
        }

        return $numero === '' ? '0' : $numero;
    }

    private function validarOpcion(string $valor, array $permitidos): string
    {
        if (!in_array($valor, $permitidos, true)) {
            throw new \InvalidArgumentException('Opcion no valida.');
        }

        return $valor;
    }

    private function obtenerEmpresaPermitida(array $usuario, int $empresaId): int
    {
        if (!$this->esSuperusuario($usuario)) {
            return (int) $usuario['empresa_id'];
        }
        if ($empresaId <= 0) {
            return (int) $usuario['empresa_id'];
        }

        return $empresaId;
    }

    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];

        return [
            'registrar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/stock/registrar'),
            'importar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/stock/importar'),
            'confirmar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/stock/confirmar-importacion'),
            'plantilla' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/stock/plantilla'),
        ];
    }

}
