<?php

declare(strict_types=1);

namespace Intesis\Nucleo;

use Intesis\Controladores\AutenticacionControlador;
use Intesis\Controladores\ArchivoProductoControlador;
use Intesis\Controladores\BodegaControlador;
use Intesis\Controladores\CategoriaControlador;
use Intesis\Controladores\ConfiguracionControlador;
use Intesis\Controladores\EmpresaControlador;
use Intesis\Controladores\MarcaControlador;
use Intesis\Controladores\MenuControlador;
use Intesis\Controladores\PanelControlador;
use Intesis\Controladores\PerfilControlador;
use Intesis\Controladores\ProductoControlador;
use Intesis\Controladores\StockControlador;
use Intesis\Controladores\KardexControlador;
use Intesis\Controladores\UsuarioControlador;

final class Enrutador
{
    public function __construct(
        private AutenticacionControlador $autenticacionControlador,
        private PanelControlador $panelControlador,
        private EmpresaControlador $empresaControlador,
        private UsuarioControlador $usuarioControlador,
        private PerfilControlador $perfilControlador,
        private MenuControlador $menuControlador,
        private ConfiguracionControlador $configuracionControlador,
        private BodegaControlador $bodegaControlador,
        private ArchivoProductoControlador $archivoProductoControlador,
        private CategoriaControlador $categoriaControlador,
        private MarcaControlador $marcaControlador,
        private ProductoControlador $productoControlador,
        private StockControlador $stockControlador,
        private KardexControlador $kardexControlador,
        private Configuracion $configuracion
    ) {
    }

    /**
     * ***************************************************************************
     * * RESUELVE LA RUTA LIMPIA ACTUAL Y EJECUTA EL CONTROLADOR CORRESPONDIENTE.
     * ***************************************************************************
     */
    public function despachar(): void
    {
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $ruta = $this->obtenerRuta();

        if ($metodo === 'GET' && in_array($ruta, ['/', '/login'], true)) {
            $this->autenticacionControlador->mostrarLogin();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/login') {
            $this->autenticacionControlador->procesarLogin();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/login/empresa') {
            $this->autenticacionControlador->seleccionarEmpresa();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/salir') {
            $this->autenticacionControlador->cerrarSesion();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/dashboard') {
            $this->panelControlador->mostrarDashboard();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/empresas') {
            $this->empresaControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/empresas/crear') {
            $this->empresaControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/empresas/editar') {
            $this->empresaControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/empresas/activar') {
            $this->empresaControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/empresas/inactivar') {
            $this->empresaControlador->inactivar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/empresas/eliminar') {
            $this->empresaControlador->eliminar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/usuarios') {
            $this->usuarioControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/usuarios/crear') {
            $this->usuarioControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/usuarios/editar') {
            $this->usuarioControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/usuarios/activar') {
            $this->usuarioControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/usuarios/inactivar') {
            $this->usuarioControlador->inactivar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/usuarios/bloquear') {
            $this->usuarioControlador->bloquear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/usuarios/eliminar') {
            $this->usuarioControlador->eliminar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/usuarios/restablecer-clave') {
            $this->usuarioControlador->restablecerClave();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/perfiles') {
            $this->perfilControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/perfiles/crear') {
            $this->perfilControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/perfiles/editar') {
            $this->perfilControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/perfiles/permisos') {
            $this->perfilControlador->permisos();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/perfiles/activar') {
            $this->perfilControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/perfiles/inactivar') {
            $this->perfilControlador->inactivar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/perfiles/eliminar') {
            $this->perfilControlador->eliminar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/menus') {
            $this->menuControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/menus/crear') {
            $this->menuControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/menus/editar') {
            $this->menuControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/menus/activar') {
            $this->menuControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/menus/inactivar') {
            $this->menuControlador->inactivar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/configuracion') {
            $this->configuracionControlador->redirigirConfiguracion();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/configuracion/estados') {
            $this->configuracionControlador->listarEstados();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/configuracion/mensajes-error') {
            $this->configuracionControlador->listarMensajes();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/sistema/configuracion/tipos-documento') {
            $this->configuracionControlador->listarTiposDocumento();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/estados/crear') {
            $this->configuracionControlador->crearEstado();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/estados/editar') {
            $this->configuracionControlador->editarEstado();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/estados/activar') {
            $this->configuracionControlador->activarEstado();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/estados/inactivar') {
            $this->configuracionControlador->inactivarEstado();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/mensajes-error/crear', '/sistema/configuracion/mensajes/crear'], true)) {
            $this->configuracionControlador->crearMensaje();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/mensajes-error/editar', '/sistema/configuracion/mensajes/editar'], true)) {
            $this->configuracionControlador->editarMensaje();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/mensajes-error/activar', '/sistema/configuracion/mensajes/activar'], true)) {
            $this->configuracionControlador->activarMensaje();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/mensajes-error/inactivar', '/sistema/configuracion/mensajes/inactivar'], true)) {
            $this->configuracionControlador->inactivarMensaje();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/tipos-documento/crear', '/sistema/configuracion/tipos/crear'], true)) {
            $this->configuracionControlador->crearTipoDocumento();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/tipos-documento/editar', '/sistema/configuracion/tipos/editar'], true)) {
            $this->configuracionControlador->editarTipoDocumento();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/tipos-documento/activar', '/sistema/configuracion/tipos/activar'], true)) {
            $this->configuracionControlador->activarTipoDocumento();
            return;
        }

        if ($metodo === 'POST' && in_array($ruta, ['/sistema/configuracion/tipos-documento/inactivar', '/sistema/configuracion/tipos/inactivar'], true)) {
            $this->configuracionControlador->inactivarTipoDocumento();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/secuencias/crear') {
            $this->configuracionControlador->crearSecuencia();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/secuencias/editar') {
            $this->configuracionControlador->editarSecuencia();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/secuencias/activar') {
            $this->configuracionControlador->activarSecuencia();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/sistema/configuracion/secuencias/inactivar') {
            $this->configuracionControlador->inactivarSecuencia();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/bodegas') {
            $this->bodegaControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/bodegas/crear') {
            $this->bodegaControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/bodegas/editar') {
            $this->bodegaControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/bodegas/activar') {
            $this->bodegaControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/bodegas/inactivar') {
            $this->bodegaControlador->inactivar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/bodegas/eliminar') {
            $this->bodegaControlador->eliminar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/productos/archivos/listar') {
            $this->archivoProductoControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/productos/archivos/subir') {
            $this->archivoProductoControlador->subir();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/productos/archivos/ver') {
            $this->archivoProductoControlador->ver();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/productos/archivos/principal') {
            $this->archivoProductoControlador->principal();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/productos/archivos/eliminar') {
            $this->archivoProductoControlador->eliminar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/productos') {
            $this->productoControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/productos/crear') {
            $this->productoControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/productos/editar') {
            $this->productoControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/productos/activar') {
            $this->productoControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/productos/inactivar') {
            $this->productoControlador->inactivar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/stock') {
            $this->stockControlador->listar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/stock/registrar') {
            $this->stockControlador->registrar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/stock/importar') {
            $this->stockControlador->importar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/stock/confirmar-importacion') {
            $this->stockControlador->confirmarImportacion();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/stock/plantilla') {
            $this->stockControlador->plantilla();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/stock/precios') {
            $this->stockControlador->precios();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/stock/codigos-proveedor') {
            $this->stockControlador->codigosProveedor();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/kardex') {
            $this->kardexControlador->listar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/kardex/detalle') {
            $this->kardexControlador->detalle();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/kardex/documento') {
            $this->kardexControlador->documento();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/categorias') {
            $this->categoriaControlador->listar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/categorias/ajax-listar') {
            $this->categoriaControlador->listarAjax();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/categorias/ajax-crear') {
            $this->categoriaControlador->crearAjax();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/categorias/crear') {
            $this->categoriaControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/categorias/editar') {
            $this->categoriaControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/categorias/activar') {
            $this->categoriaControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/categorias/inactivar') {
            $this->categoriaControlador->inactivar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/marcas') {
            $this->marcaControlador->listar();
            return;
        }

        if ($metodo === 'GET' && $ruta === '/inventario/marcas/ajax-listar') {
            $this->marcaControlador->listarAjax();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/marcas/ajax-crear') {
            $this->marcaControlador->crearAjax();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/marcas/crear') {
            $this->marcaControlador->crear();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/marcas/editar') {
            $this->marcaControlador->editar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/marcas/activar') {
            $this->marcaControlador->activar();
            return;
        }

        if ($metodo === 'POST' && $ruta === '/inventario/marcas/inactivar') {
            $this->marcaControlador->inactivar();
            return;
        }

        http_response_code(404);
        echo 'Ruta no encontrada.';
    }

    /**
     * ***************************************************************************
     * * NORMALIZA LA URL ACTUAL QUITANDO EL DIRECTORIO BASE DEL PROYECTO.
     * ***************************************************************************
     */
    private function obtenerRuta(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(parse_url($this->configuracion->obtener('APP_URL', '') ?? '', PHP_URL_PATH) ?: '', '/\\');
        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $ruta = '/' . trim($uri, '/');
        return $ruta === '/' ? '/' : rtrim($ruta, '/');
    }
}
