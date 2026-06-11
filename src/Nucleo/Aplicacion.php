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
use Intesis\Controladores\MovimientoInternoControlador;
use Intesis\Controladores\ProveedorControlador;
use Intesis\Controladores\DocumentoCompraControlador;
use Intesis\Controladores\ClienteControlador;
use Intesis\Controladores\UsuarioControlador;
use Intesis\Modelos\EmpresaModelo;
use Intesis\Modelos\ArchivoProductoModelo;
use Intesis\Modelos\BodegaModelo;
use Intesis\Modelos\CategoriaModelo;
use Intesis\Modelos\EstadoModelo;
use Intesis\Modelos\MarcaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\ModuloModelo;
use Intesis\Modelos\PerfilModelo;
use Intesis\Modelos\ProductoModelo;
use Intesis\Modelos\SecuenciaModelo;
use Intesis\Modelos\StockModelo;
use Intesis\Modelos\KardexModelo;
use Intesis\Modelos\MovimientoInternoModelo;
use Intesis\Modelos\ProveedorModelo;
use Intesis\Modelos\DocumentoCompraModelo;
use Intesis\Modelos\ClienteModelo;
use Intesis\Modelos\TipoDocumentoModelo;
use Intesis\Modelos\UsuarioEmpresaModelo;
use Intesis\Modelos\UsuarioModelo;
use Intesis\Servicios\AutenticacionServicio;
use Intesis\Servicios\GeneradorPdf;

final class Aplicacion
{
    private Enrutador $enrutador;

    /**
     * ***************************************************************************
     * * CONSTRUYE LAS DEPENDENCIAS PRINCIPALES DEL SISTEMA.
     * ***************************************************************************
     */
    private function __construct(string $raiz)
    {
        date_default_timezone_set('America/Guayaquil');

        $configuracion = new Configuracion($raiz);
        $registroErrores = new RegistroErrores($configuracion);
        $registroErrores->registrarManejadores();

        $sesion = new Sesion($configuracion);
        $sesion->iniciar();

        $conexion = new ConexionBaseDatos($configuracion);
        $vista = new Vista($configuracion);
        $usuarioModelo = new UsuarioModelo($conexion);
        $moduloModelo = new ModuloModelo($conexion);
        $menuModelo = new MenuModelo($conexion);
        $mensajeSistemaModelo = new MensajeSistemaModelo($conexion, $registroErrores);
        $empresaModelo = new EmpresaModelo($conexion);
        $usuarioEmpresaModelo = new UsuarioEmpresaModelo($conexion);
        $perfilModelo = new PerfilModelo($conexion);
        $estadoModelo = new EstadoModelo($conexion);
        $tipoDocumentoModelo = new TipoDocumentoModelo($conexion);
        $secuenciaModelo = new SecuenciaModelo($conexion);
        $bodegaModelo = new BodegaModelo($conexion);
        $archivoProductoModelo = new ArchivoProductoModelo($conexion);
        $categoriaModelo = new CategoriaModelo($conexion);
        $marcaModelo = new MarcaModelo($conexion);
        $productoModelo = new ProductoModelo($conexion);
        $stockModelo = new StockModelo($conexion);
        $kardexModelo = new KardexModelo($conexion);
        $movimientoInternoModelo = new MovimientoInternoModelo($conexion, $secuenciaModelo);
        $proveedorModelo = new ProveedorModelo($conexion);
        $documentoCompraModelo = new DocumentoCompraModelo($conexion);
        $clienteModelo = new ClienteModelo($conexion);
        $autenticacionServicio = new AutenticacionServicio($usuarioModelo, $registroErrores);
        $generadorPdf = new GeneradorPdf();

        $this->enrutador = new Enrutador(
            new AutenticacionControlador($vista, $sesion, $autenticacionServicio, $configuracion),
            new PanelControlador($vista, $sesion, $moduloModelo, $menuModelo, $configuracion),
            new EmpresaControlador($vista, $sesion, $empresaModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new UsuarioControlador($vista, $sesion, $usuarioEmpresaModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new PerfilControlador($vista, $sesion, $perfilModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new MenuControlador($vista, $sesion, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new ConfiguracionControlador($vista, $sesion, $estadoModelo, $mensajeSistemaModelo, $tipoDocumentoModelo, $secuenciaModelo, $menuModelo, $configuracion, $registroErrores),
            new BodegaControlador($vista, $sesion, $bodegaModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new ArchivoProductoControlador($sesion, $productoModelo, $archivoProductoModelo, $menuModelo, $configuracion, $registroErrores),
            new CategoriaControlador($vista, $sesion, $categoriaModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new MarcaControlador($vista, $sesion, $marcaModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new ProductoControlador($vista, $sesion, $productoModelo, $categoriaModelo, $marcaModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new StockControlador($vista, $sesion, $stockModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new KardexControlador($vista, $sesion, $kardexModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores, $generadorPdf),
            new MovimientoInternoControlador($vista, $sesion, $movimientoInternoModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new ProveedorControlador($vista, $sesion, $proveedorModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new DocumentoCompraControlador($vista, $sesion, $documentoCompraModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            new ClienteControlador($vista, $sesion, $clienteModelo, $menuModelo, $mensajeSistemaModelo, $configuracion, $registroErrores),
            $configuracion,
            $vista,
            $sesion
        );
    }

    /**
     * ***************************************************************************
     * * INICIA LA APLICACION USANDO LA RAIZ ABSOLUTA DEL PROYECTO.
     * ***************************************************************************
     */
    public static function iniciar(string $raiz): self
    {
        return new self($raiz);
    }

    /**
     * ***************************************************************************
     * * EJECUTA EL ENRUTADOR PRINCIPAL.
     * ***************************************************************************
     */
    public function ejecutar(): void
    {
        $this->enrutador->despachar();
    }
}
