<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Nucleo/Aplicacion.php';
require_once __DIR__ . '/src/Nucleo/Configuracion.php';
require_once __DIR__ . '/src/Nucleo/RegistroErrores.php';
require_once __DIR__ . '/src/Nucleo/ConexionBaseDatos.php';
require_once __DIR__ . '/src/Nucleo/Sesion.php';
require_once __DIR__ . '/src/Nucleo/Vista.php';
require_once __DIR__ . '/src/Nucleo/Enrutador.php';
require_once __DIR__ . '/src/Modelos/UsuarioModelo.php';
require_once __DIR__ . '/src/Modelos/ModuloModelo.php';
require_once __DIR__ . '/src/Modelos/MenuModelo.php';
require_once __DIR__ . '/src/Modelos/MensajeSistemaModelo.php';
require_once __DIR__ . '/src/Modelos/EmpresaModelo.php';
require_once __DIR__ . '/src/Modelos/UsuarioEmpresaModelo.php';
require_once __DIR__ . '/src/Modelos/PerfilModelo.php';
require_once __DIR__ . '/src/Modelos/EstadoModelo.php';
require_once __DIR__ . '/src/Modelos/TipoDocumentoModelo.php';
require_once __DIR__ . '/src/Modelos/SecuenciaModelo.php';
require_once __DIR__ . '/src/Modelos/BodegaModelo.php';
require_once __DIR__ . '/src/Modelos/ArchivoProductoModelo.php';
require_once __DIR__ . '/src/Modelos/CategoriaModelo.php';
require_once __DIR__ . '/src/Modelos/MarcaModelo.php';
require_once __DIR__ . '/src/Modelos/ProductoModelo.php';
require_once __DIR__ . '/src/Modelos/StockModelo.php';
require_once __DIR__ . '/src/Modelos/KardexModelo.php';
require_once __DIR__ . '/src/Servicios/AutenticacionServicio.php';
require_once __DIR__ . '/src/Servicios/ValidadorIdentificacion.php';
require_once __DIR__ . '/src/Controladores/AutenticacionControlador.php';
require_once __DIR__ . '/src/Controladores/PanelControlador.php';
require_once __DIR__ . '/src/Controladores/EmpresaControlador.php';
require_once __DIR__ . '/src/Controladores/UsuarioControlador.php';
require_once __DIR__ . '/src/Controladores/PerfilControlador.php';
require_once __DIR__ . '/src/Controladores/MenuControlador.php';
require_once __DIR__ . '/src/Controladores/ConfiguracionControlador.php';
require_once __DIR__ . '/src/Controladores/BodegaControlador.php';
require_once __DIR__ . '/src/Controladores/ArchivoProductoControlador.php';
require_once __DIR__ . '/src/Controladores/CategoriaControlador.php';
require_once __DIR__ . '/src/Controladores/MarcaControlador.php';
require_once __DIR__ . '/src/Controladores/ProductoControlador.php';
require_once __DIR__ . '/src/Controladores/StockControlador.php';
require_once __DIR__ . '/src/Controladores/KardexControlador.php';

use Intesis\Nucleo\Aplicacion;

Aplicacion::iniciar(__DIR__)->ejecutar();
