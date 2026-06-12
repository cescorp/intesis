<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\DocumentoCompraModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\SriImportacionModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Intesis\Servicios\SriXmlServicio;
use Throwable;

final class DocumentoCompraControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private DocumentoCompraModelo $documentoCompraModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores,
        private SriXmlServicio $sriXmlServicio,
        private SriImportacionModelo $sriImportacionModelo
    ) {
    }

    // =========================================================================
    // VISTAS
    // =========================================================================

    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/documentos/ver');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];
        $usuarioId = (int) $usuario['id'];

        $estadoFiltro = trim((string) ($_GET['estado'] ?? ''));
        $estadosValidos = ['BORRADOR', 'REGISTRADO', 'ANULADO'];
        if (!in_array($estadoFiltro, $estadosValidos, true)) {
            $estadoFiltro = '';
        }

        // Tipo FACTURA_COMPRA id (para el modal subir XML)
        $tiposDoc = $this->documentoCompraModelo->listarTiposDocumento();
        $tipoFacturaId = null;
        foreach ($tiposDoc as $td) {
            if ($td['sis_tipo_documento_codigo'] === 'FACTURA_COMPRA') {
                $tipoFacturaId = (int) $td['sis_tipo_documento_id'];
                break;
            }
        }

        $this->vista->renderizar('compras/documentos', [
            'titulo'          => 'Documentos de Compra',
            'usuario'         => $usuario,
            'menus'           => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'documentos'      => $this->documentoCompraModelo->listar($empresaId, $verTodas, $estadoFiltro),
            'bodegas'         => $this->documentoCompraModelo->listarBodegasPermitidas($empresaId, $usuarioId, $verTodas),
            'esSuperusuario'  => $verTodas,
            'estadoFiltro'    => $estadoFiltro,
            'permisos'        => $this->obtenerPermisos($usuario),
            'tipoFacturaId'   => $tipoFacturaId,
            'mensaje'         => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos(['CONFIRMAR_ANULAR_DOCUMENTO']),
            'pendientesSri'   => $this->sriImportacionModelo->listarProveedoresConPendientes($empresaId),
        ]);
    }

    public function nuevo(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/documentos/crear');
        $verTodas   = $this->esSuperusuario($usuario);
        $empresaId  = (int) $usuario['empresa_id'];
        $usuarioId  = (int) $usuario['id'];

        $this->vista->renderizar('compras/documento_form', [
            'titulo'          => 'Nuevo Documento de Compra',
            'usuario'         => $usuario,
            'menus'           => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'empresas'        => $this->documentoCompraModelo->listarEmpresasActivas($verTodas, $empresaId),
            'tiposDocumento'  => $this->documentoCompraModelo->listarTiposDocumento(),
            'bodegas'         => $this->documentoCompraModelo->listarBodegasPermitidas($empresaId, $usuarioId, $verTodas),
            'ivaList'         => $this->documentoCompraModelo->listarIva($empresaId),
            'esSuperusuario'  => $verTodas,
            'mensaje'         => $this->sesion->consumirMensaje(),
        ]);
    }

    public function editar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/documentos/crear');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];
        $usuarioId = (int) $usuario['id'];

        $documentoId = (int) ($_GET['id'] ?? 0);
        $doc = $this->obtenerDocumentoPermitido($documentoId, $usuario);

        if ($doc['sis_estado_codigo'] !== 'BORRADOR') {
            $this->sesion->guardarMensaje('error', 'No editable', 'Solo se pueden editar documentos en estado BORRADOR.');
            $this->redirigir('/compras/documentos');
        }

        $lineas = $this->documentoCompraModelo->listarDetalle($documentoId);

        $this->vista->renderizar('compras/documento_form', [
            'titulo'         => 'Editar Documento de Compra',
            'usuario'        => $usuario,
            'menus'          => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'empresas'       => $this->documentoCompraModelo->listarEmpresasActivas($verTodas, $empresaId),
            'tiposDocumento' => $this->documentoCompraModelo->listarTiposDocumento(),
            'bodegas'        => $this->documentoCompraModelo->listarBodegasPermitidas($empresaId, $usuarioId, $verTodas),
            'ivaList'        => $this->documentoCompraModelo->listarIva($empresaId),
            'esSuperusuario' => $verTodas,
            'documento'      => $doc,
            'lineas'         => $lineas,
            'mensaje'        => $this->sesion->consumirMensaje(),
        ]);
    }

    // =========================================================================
    // ACCIONES POST
    // =========================================================================

    public function crear(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/documentos/crear');
        try {
            $empresaId = $this->esSuperusuario($usuario) ? (int) ($_POST['empresa_id'] ?? 0) : (int) $usuario['empresa_id'];
            $cabecera  = $this->normalizarCabecera($empresaId);
            $lineas    = $this->normalizarLineas($_POST['lineas'] ?? []);
            $this->validarCabecera($cabecera);
            $this->validarLineas($lineas);
            $id = $this->documentoCompraModelo->crear($cabecera, $lineas, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Documento guardado', 'El documento fue guardado como BORRADOR.');
            $this->redirigir('/compras/documentos');
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribirExcepcion('CREAR DOCUMENTO COMPRA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
            $this->redirigir('/compras/documentos/nuevo');
        }
    }

    public function actualizar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/documentos/crear');
        try {
            $documentoId = (int) ($_POST['documento_id'] ?? 0);
            $empresaId   = $this->esSuperusuario($usuario) ? (int) ($_POST['empresa_id'] ?? 0) : (int) $usuario['empresa_id'];
            $doc = $this->obtenerDocumentoPermitido($documentoId, $usuario);
            $cabecera = $this->normalizarCabecera($empresaId);
            $lineas   = $this->normalizarLineas($_POST['lineas'] ?? []);
            $this->validarCabecera($cabecera);
            $this->validarLineas($lineas);
            $this->documentoCompraModelo->actualizar((int) $doc['com_documento_id'], $cabecera, $lineas, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Documento actualizado', 'Los cambios fueron guardados.');
            $this->redirigir('/compras/documentos');
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribirExcepcion('ACTUALIZAR DOCUMENTO COMPRA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo actualizar', $excepcion->getMessage());
            $documentoId = (int) ($_POST['documento_id'] ?? 0);
            $this->redirigir('/compras/documentos/editar?id=' . $documentoId);
        }
    }

    public function registrar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermiso('/compras/documentos/registrar');
        try {
            $documentoId = (int) ($_POST['documento_id'] ?? 0);
            $doc = $this->obtenerDocumentoPermitido($documentoId, $usuario);
            $this->documentoCompraModelo->registrar((int) $doc['com_documento_id'], (int) $doc['sis_empresa_id'], (int) $usuario['id']);
            $this->jsonOk(['mensaje' => 'Documento registrado. Inventario actualizado.']);
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribirExcepcion('REGISTRAR DOCUMENTO COMPRA', $excepcion);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensaje' => $excepcion->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function anular(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/documentos/anular');
        try {
            $documentoId = (int) ($_POST['documento_id'] ?? 0);
            $doc = $this->obtenerDocumentoPermitido($documentoId, $usuario);
            $this->documentoCompraModelo->anular((int) $doc['com_documento_id'], (int) $doc['sis_empresa_id'], (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Documento anulado', 'El documento fue anulado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribirExcepcion('ANULAR DOCUMENTO COMPRA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo anular', $excepcion->getMessage());
        }
        $this->redirigir('/compras/documentos');
    }

    // =========================================================================
    // SRI: SUBIR XML
    // =========================================================================

    /**
     * POST /compras/documentos/subir-xml
     * Recibe un archivo XML del SRI, lo parsea y devuelve JSON con la previsualización.
     * No guarda el documento todavía — solo almacena el XML en pendientes y retorna datos.
     */
    public function subirXml(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermiso('/compras/documentos/subir-xml');

        try {
            $empresaId       = (int) $usuario['empresa_id'];
            $usuarioId       = (int) $usuario['id'];
            $rucEmpresaActiva = $this->sriImportacionModelo->obtenerRucEmpresa($empresaId);
            $bodegaId        = (int) ($_POST['bodega_id'] ?? 0);
            if ($bodegaId <= 0) {
                throw new \InvalidArgumentException('Debe seleccionar una bodega.');
            }

            // Validar archivo subido
            $archivo = $_FILES['xml_file'] ?? null;
            if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('No se recibió el archivo XML.');
            }
            $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if ($ext !== 'xml') {
                throw new \InvalidArgumentException('El archivo debe ser .xml');
            }

            $contenido = file_get_contents($archivo['tmp_name']);
            if ($contenido === false || strlen($contenido) < 10) {
                throw new \InvalidArgumentException('El archivo XML está vacío o no se pudo leer.');
            }

            // Parsear
            $factura = $this->sriXmlServicio->parsearFactura($contenido);
            $ruc     = $factura['ruc_emisor'] ?? 'desconocido';
            $clave   = $factura['clave_acceso'] ?? null;

            // Verificar duplicado
            $duplicado = $clave && $this->sriImportacionModelo->claveYaExiste($empresaId, $clave);
            if ($duplicado) {
                // Eliminar .xml de pendientes si ya existe
                $rutas = $this->sriXmlServicio->rutasXml($this->raizProyecto(), $ruc, $rucEmpresaActiva);
                $nombreArchivo = ($clave ?? uniqid()) . '.xml';
                $rutaPendiente = $rutas['pendientes'] . DIRECTORY_SEPARATOR . $nombreArchivo;
                if (is_file($rutaPendiente)) {
                    @unlink($rutaPendiente);
                }
                $this->jsonOk([
                    'duplicado'   => true,
                    'clave_acceso' => $clave,
                    'numero'      => ($factura['estab'] ?? '') . '-' . ($factura['pto_emi'] ?? '') . '-' . ($factura['secuencial'] ?? ''),
                ]);
            }

            // Guardar XML en pendientes
            $rutas = $this->sriXmlServicio->rutasXml($this->raizProyecto(), $ruc, $rucEmpresaActiva);
            $this->sriXmlServicio->asegurarDirectorio($rutas['pendientes']);
            $nombreArchivo = ($clave ?? uniqid()) . '.xml';
            $rutaDestino   = $rutas['pendientes'] . DIRECTORY_SEPARATOR . $nombreArchivo;
            file_put_contents($rutaDestino, $contenido);

            // Registrar en com_archivos_sri (guarda también el XML en base)
            $archivoId = $this->sriImportacionModelo->registrarArchivo(
                $empresaId, 'XML', $rutaDestino, $clave, null, $usuarioId, $contenido
            );

            // Buscar/crear proveedor
            $contacto    = [
                'telefono'  => $factura['telefono_emisor'],
                'correo'    => $factura['correo_emisor'],
                'direccion' => $factura['direccion_emisor'],
            ];
            $proveedorId = $this->sriImportacionModelo->buscarOCrearProveedor(
                $empresaId,
                $factura['ruc_emisor'] ?? '',
                $factura['razon_social'] ?? $factura['ruc_emisor'] ?? '',
                $contacto,
                $usuarioId
            );

            // Resolver productos (detectar cuáles faltan)
            $facturasTemp = [array_merge($factura, ['proveedor_id' => $proveedorId])];
            $facturasRes  = $this->sriImportacionModelo->resolverProductos($empresaId, $facturasTemp);
            $factura      = $facturasRes[0];

            $codigosSinMapeo = $this->sriImportacionModelo->filtrarCodigosSinMapeo($empresaId, [
                array_merge($factura, ['proveedor_id' => $proveedorId])
            ]);

            $this->jsonOk([
                'duplicado'        => false,
                'archivo_id'       => $archivoId,
                'archivo_ruta'     => $rutaDestino,
                'factura'          => $factura,
                'proveedor_id'     => $proveedorId,
                'bodega_id'        => $bodegaId,
                'pendientes_codigos' => $codigosSinMapeo,
            ]);
        } catch (Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'TIPO_NO_SOPORTADO:')) {
                $tipo = substr($e->getMessage(), strlen('TIPO_NO_SOPORTADO:'));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok'               => false,
                    'tipo_no_soportado' => true,
                    'mensaje'          => 'Tipo de comprobante no soportado: ' . trim($tipo),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->registroErrores->escribirExcepcion('SUBIR XML SRI', $e);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================================
    // SRI: PROCESAR TXT
    // =========================================================================

    /**
     * POST /compras/documentos/procesar-txt
     * Recibe el TXT del SRI, descarga XMLs vía SOAP, pre-procesa y retorna resumen JSON.
     */
    public function procesarTxt(): void
    {
        set_time_limit(300);

        $usuario = $this->exigirSesionJson();
        $this->exigirPermiso('/compras/documentos/procesar-txt');

        try {
            $empresaId = (int) $usuario['empresa_id'];
            $usuarioId = (int) $usuario['id'];
            $bodegaId  = (int) ($_POST['bodega_id'] ?? 0);
            if ($bodegaId <= 0) {
                throw new \InvalidArgumentException('Debe seleccionar una bodega.');
            }

            $archivo = $_FILES['txt_file'] ?? null;
            if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('No se recibió el archivo TXT.');
            }
            $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if ($ext !== 'txt') {
                throw new \InvalidArgumentException('El archivo debe ser .txt');
            }

            // Guardar TXT en pendientes (directorio = RUC de la empresa receptora)
            $rucEmpresa  = $this->sriImportacionModelo->obtenerRucEmpresa($empresaId);
            $rutasTxt    = $this->sriXmlServicio->rutasTxt($this->raizProyecto(), $rucEmpresa);
            $this->sriXmlServicio->asegurarDirectorio($rutasTxt['pendientes']);
            $nombreTxt   = date('YmdHis') . '_' . uniqid() . '.txt';
            $rutaTxtDest = $rutasTxt['pendientes'] . DIRECTORY_SEPARATOR . $nombreTxt;
            move_uploaded_file($archivo['tmp_name'], $rutaTxtDest);

            // Parsear TXT
            $parsed      = $this->sriImportacionModelo->parsearTxt($rutaTxtDest);
            $facturasTxt = $parsed['facturas'];
            $ignorados   = $parsed['ignorados'];
            $totalLineas = $parsed['total_lineas'];

            // Registrar TXT
            $txtArchivoId = $this->sriImportacionModelo->registrarArchivo(
                $empresaId, 'TXT', $rutaTxtDest, null, $totalLineas, $usuarioId
            );

            $resultados        = [];
            $duplicados        = [];
            $errores           = [];
            $codigosSinMapeo   = [];
            $facturasResueltas = [];

            foreach ($facturasTxt as $fila) {
                $clave = $fila['clave_acceso'] ?? '';

                // Verificar duplicado
                if ($clave && $this->sriImportacionModelo->claveYaExiste($empresaId, $clave)) {
                    // Borrar XML pendiente si existe
                    $rutas = $this->sriXmlServicio->rutasXml($this->raizProyecto(), $fila['ruc_emisor'] ?? 'desconocido', $rucEmpresa);
                    $rutaXmlPend = $rutas['pendientes'] . DIRECTORY_SEPARATOR . $clave . '.xml';
                    if (is_file($rutaXmlPend)) {
                        @unlink($rutaXmlPend);
                    }
                    $duplicados[] = $fila;
                    continue;
                }

                try {
                    // Descargar XML vía SOAP
                    $xmlContenido = $this->sriXmlServicio->descargarXmlSoap($clave);
                    if ($xmlContenido === null) {
                        // Registrar solo si aún no existe en com_archivos_sri
                        $archivoId = $this->sriImportacionModelo->registrarArchivo(
                            $empresaId, 'XML', '', $clave, null, $usuarioId
                        );
                        $this->sriImportacionModelo->actualizarEstadoArchivo(
                            $archivoId, 'SIN_INFORMACION', 'SRI no retornó información para esta clave.', $usuarioId
                        );
                        $errores[] = ['fila' => $fila, 'mensaje' => 'SRI sin información para clave ' . $clave];
                        continue;
                    }

                    // Parsear XML descargado
                    $factura   = $this->sriXmlServicio->parsearFactura($xmlContenido);
                    $rucEmisor = $factura['ruc_emisor'] ?? ($fila['ruc_emisor'] ?? 'desconocido');

                    // Guardar XML en pendientes
                    $rutas = $this->sriXmlServicio->rutasXml($this->raizProyecto(), $rucEmisor, $rucEmpresa);
                    $this->sriXmlServicio->asegurarDirectorio($rutas['pendientes']);
                    $claveXml    = $factura['clave_acceso'] ?? $clave;
                    $nombreXml   = $claveXml . '.xml';
                    $rutaXmlDest = $rutas['pendientes'] . DIRECTORY_SEPARATOR . $nombreXml;
                    file_put_contents($rutaXmlDest, $xmlContenido);

                    // Registrar XML (UPSERT por clave, guarda contenido en base)
                    $xmlArchivoId = $this->sriImportacionModelo->registrarArchivo(
                        $empresaId, 'XML', $rutaXmlDest, $claveXml, null, $usuarioId, $xmlContenido
                    );
                    $this->sriImportacionModelo->actualizarEstadoArchivo($xmlArchivoId, 'DESCARGADO', null, $usuarioId);

                    // Proveedor
                    $contacto    = [
                        'telefono'  => $factura['telefono_emisor'],
                        'correo'    => $factura['correo_emisor'],
                        'direccion' => $factura['direccion_emisor'],
                    ];
                    $proveedorId = $this->sriImportacionModelo->buscarOCrearProveedor(
                        $empresaId,
                        $factura['ruc_emisor'] ?? '',
                        $factura['razon_social'] ?? $fila['razon_social'] ?? '',
                        $contacto,
                        $usuarioId
                    );

                    $facturasResueltas[] = array_merge($factura, [
                        'proveedor_id' => $proveedorId,
                        'bodega_id'    => $bodegaId,
                        'archivo_id'   => $xmlArchivoId,
                        'archivo_ruta' => $rutaXmlDest,
                    ]);
                    $resultados[] = [
                        'clave'         => $clave,
                        'numero'        => ($factura['estab'] ?? '') . '-' . ($factura['pto_emi'] ?? '') . '-' . ($factura['secuencial'] ?? ''),
                        'ruc_emisor'    => $factura['ruc_emisor'] ?? '',
                        'razon_social'  => $factura['razon_social'] ?? '',
                        'importe_total' => $factura['importe_total'],
                    ];
                } catch (Throwable $e) {
                    // TIPO_NO_SOPORTADO: registrar como ignorado; demás errores → errores[]
                    if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'TIPO_NO_SOPORTADO:')) {
                        try {
                            $archivoId = $this->sriImportacionModelo->registrarArchivo(
                                $empresaId, 'XML', '', $clave, null, $usuarioId
                            );
                            $this->sriImportacionModelo->actualizarEstadoArchivo(
                                $archivoId, 'ERROR', $e->getMessage(), $usuarioId
                            );
                        } catch (Throwable) { /* ignora si no se puede registrar */ }
                        $ignorados[] = array_merge($fila, [
                            'mensaje_ignorado' => trim(substr($e->getMessage(), strlen('TIPO_NO_SOPORTADO:'))),
                        ]);
                    } else {
                        $errores[] = ['fila' => $fila, 'mensaje' => $e->getMessage()];
                    }
                }
            }

            // Resolver productos para facturas descargadas
            if (!empty($facturasResueltas)) {
                $facturasResueltas = $this->sriImportacionModelo->resolverProductos($empresaId, $facturasResueltas);
                $codigosSinMapeo   = $this->sriImportacionModelo->filtrarCodigosSinMapeo($empresaId, $facturasResueltas);
            }

            // Actualizar estado TXT
            $this->sriImportacionModelo->actualizarEstadoArchivo($txtArchivoId, 'PROCESADO', null, $usuarioId, $totalLineas);

            $this->jsonOk([
                'facturas'               => $resultados,
                'ignorados'              => $ignorados,
                'duplicados'             => $duplicados,
                'errores'                => $errores,
                'codigos_sin_mapeo'      => $codigosSinMapeo,
                'facturas_para_guardar'  => $facturasResueltas,
                'bodega_id'              => $bodegaId,
                'hay_pendientes'         => !empty($errores),
            ]);
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('PROCESAR TXT SRI', $e);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================================
    // SRI: ASIGNAR CÓDIGO
    // =========================================================================

    /**
     * POST /compras/documentos/asignar-codigo
     * Guarda el mapeo cod_proveedor → producto y propaga a documentos pendientes.
     */
    public function asignarCodigo(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/compras/documentos/subir-xml'); // reutiliza permiso

        try {
            $empresaId  = (int) $usuario['empresa_id'];
            $usuarioId  = (int) $usuario['id'];
            $proveedorId = (int) ($_POST['proveedor_id'] ?? 0);
            $codPrincipal = trim((string) ($_POST['cod_principal'] ?? ''));
            $productoId  = (int) ($_POST['producto_id'] ?? 0);

            if ($proveedorId <= 0) {
                throw new \InvalidArgumentException('Proveedor no válido.');
            }
            if ($codPrincipal === '') {
                throw new \InvalidArgumentException('Código proveedor vacío.');
            }
            if ($productoId <= 0) {
                throw new \InvalidArgumentException('Producto no válido.');
            }

            $this->sriImportacionModelo->guardarMapeo($empresaId, $proveedorId, $codPrincipal, $productoId, $usuarioId);
            $this->jsonOk(['mensaje' => 'Mapeo guardado.']);
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('ASIGNAR CODIGO SRI', $e);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================================
    // SRI: GUARDAR DOCUMENTOS
    // =========================================================================

    /**
     * POST /compras/documentos/guardar-sri
     * Recibe las facturas (parseadas) en formato JSON, valida y crea documentos BORRADOR.
     * Mueve los XMLs de pendientes a procesados.
     */
    public function guardarSri(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermiso('/compras/documentos/subir-xml');

        try {
            $empresaId       = (int) $usuario['empresa_id'];
            $usuarioId       = (int) $usuario['id'];
            $rucEmpresaActiva = $this->sriImportacionModelo->obtenerRucEmpresa($empresaId);
            $facturasBruto   = json_decode(
                (string) ($_POST['facturas_json'] ?? '[]'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $bodegaId = (int) ($_POST['bodega_id'] ?? 0);
            if ($bodegaId <= 0) {
                throw new \InvalidArgumentException('Bodega no válida.');
            }

            // Tipo FACTURA_COMPRA
            $tiposDoc = $this->documentoCompraModelo->listarTiposDocumento();
            $tipoFacturaId = null;
            foreach ($tiposDoc as $td) {
                if ($td['sis_tipo_documento_codigo'] === 'FACTURA_COMPRA') {
                    $tipoFacturaId = (int) $td['sis_tipo_documento_id'];
                    break;
                }
            }
            if (!$tipoFacturaId) {
                throw new \RuntimeException('Tipo FACTURA_COMPRA no configurado.');
            }

            $guardados = [];
            $errores   = [];

            foreach ($facturasBruto as $factura) {
                try {
                    $proveedorId = (int) ($factura['proveedor_id'] ?? 0);
                    $detalles    = $factura['detalles'] ?? [];
                    $clave       = $factura['clave_acceso'] ?? null;

                    // Validar que todos los detalles tengan producto
                    foreach ($detalles as $i => $det) {
                        if (empty($det['inv_producto_id'])) {
                            $cod = $det['cod_principal'] ?? ("línea " . ($i + 1));
                            throw new \InvalidArgumentException("Código sin asignar: {$cod}. Asigne todos los productos antes de guardar.");
                        }
                    }

                    // Duplicado
                    if ($clave && $this->sriImportacionModelo->claveYaExiste($empresaId, $clave)) {
                        $errores[] = [
                            'numero'  => ($factura['estab'] ?? '') . '-' . ($factura['pto_emi'] ?? '') . '-' . ($factura['secuencial'] ?? ''),
                            'mensaje' => 'Documento duplicado (ya importado).',
                        ];
                        continue;
                    }

                    $numero = ($factura['estab'] ?? '') . '-' . ($factura['pto_emi'] ?? '') . '-' . ($factura['secuencial'] ?? '');

                    $pagosJson        = !empty($factura['pagos'])          ? json_encode($factura['pagos'],          JSON_UNESCAPED_UNICODE) : null;
                    $infoAdicionalJson = !empty($factura['info_adicional']) ? json_encode($factura['info_adicional'], JSON_UNESCAPED_UNICODE) : null;

                    $cabecera = [
                        'sis_tipo_documento_id'                    => $tipoFacturaId,
                        'com_proveedor_id'                         => $proveedorId,
                        'inv_bodega_id'                            => $bodegaId,
                        'com_documento_numero'                     => $numero,
                        'com_documento_fecha'                      => $factura['fecha_emision'],
                        'com_documento_observacion'                => 'Importado desde SRI',
                        'com_documento_subtotal'                   => $factura['total_sin_impuestos'] ?? 0,
                        'com_documento_iva'                        => $factura['iva'] ?? 0,
                        'com_documento_total'                      => $factura['importe_total'] ?? 0,
                        'com_documento_clave_acceso'               => $clave,
                        'com_documento_num_autorizacion'           => $factura['numero_autorizacion'] ?? null,
                        'com_documento_fecha_autorizacion'         => $factura['fecha_autorizacion'] ?? null,
                        'com_documento_ruc_emisor'                 => $factura['ruc_emisor'] ?? null,
                        'com_documento_razon_emisor'               => $factura['razon_social'] ?? null,
                        'com_documento_nombre_comercial_emisor'    => $factura['nombre_comercial'] ?? null,
                        'com_documento_dir_matriz'                 => $factura['dir_matriz'] ?? null,
                        'com_documento_ambiente'                   => $factura['ambiente'] ?? null,
                        'com_documento_tipo_emision'               => $factura['tipo_emision'] ?? null,
                        'com_documento_pagos_json'                 => $pagosJson,
                        'com_documento_info_adicional_json'        => $infoAdicionalJson,
                        'com_documento_obligado_contabilidad'      => $factura['obligado_contabilidad'] ?? null,
                    ];

                    $docId = $this->documentoCompraModelo->crearDesdeSri($empresaId, $cabecera, $detalles, $usuarioId);

                    // Mover XML de pendientes → procesados (directorio = RUC empresa activa)
                    $rutaXml = $factura['archivo_ruta'] ?? null;
                    if ($rutaXml && is_file($rutaXml)) {
                        $rucEmisor = $factura['ruc_emisor'] ?? 'desconocido';
                        $rutas     = $this->sriXmlServicio->rutasXml(
                            $this->raizProyecto(),
                            $rucEmisor,
                            $rucEmpresaActiva   // RUC de la empresa receptora
                        );
                        $this->sriXmlServicio->moverArchivo($rutaXml, $rutas['procesados'], basename($rutaXml));
                    }

                    // Actualizar registro com_archivos_sri si tiene archivo_id
                    $archivoId = (int) ($factura['archivo_id'] ?? 0);
                    if ($archivoId > 0) {
                        $this->sriImportacionModelo->actualizarEstadoArchivo($archivoId, 'PROCESADO', null, $usuarioId);
                    }

                    $guardados[] = ['numero' => $numero, 'documento_id' => $docId];
                } catch (Throwable $e2) {
                    $errores[] = [
                        'numero'  => ($factura['estab'] ?? '') . '-' . ($factura['pto_emi'] ?? '') . '-' . ($factura['secuencial'] ?? ''),
                        'mensaje' => $e2->getMessage(),
                    ];
                }
            }

            $this->jsonOk([
                'guardados' => $guardados,
                'errores'   => $errores,
                'mensaje'   => count($guardados) . ' documento(s) guardados como BORRADOR.',
            ]);
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('GUARDAR SRI', $e);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    // =========================================================================
    // PDF Y XML
    // =========================================================================

    /**
     * GET /compras/documentos/pdf?id=X[&modo=descargar]
     * Genera el PDF de un documento desde la base de datos y lo entrega inline
     * (para iframe) o como descarga según el parámetro modo.
     */
    public function generarPdf(): void
    {
        $usuario     = $this->exigirSesion();
        $empresaId   = (int) $usuario['empresa_id'];
        $documentoId = (int) ($_GET['id'] ?? 0);
        $modoDesc    = strtolower(trim((string) ($_GET['modo'] ?? ''))) === 'descargar';

        try {
            $doc = $this->obtenerDocumentoPermitido($documentoId, $usuario);
            $datos = $this->documentoCompraModelo->obtenerDatosParaPdf($documentoId, $empresaId);
            if (!$datos) {
                throw new \RuntimeException('Documento no encontrado o sin acceso.');
            }

            // Cargar plantillas SRI (funciones gpdf_*)
            require_once $this->raizProyecto() . '/src/Servicios/PdfSri/Plantillas.php';

            // Ruta del logo
            $rutaLogo = $this->raizProyecto() . '/publico/images/no_tiene_logo.jpg';
            if (!is_file($rutaLogo)) {
                $rutaLogo = null;
            }

            $html = gpdf_html_documento($datos, $rutaLogo);

            // Renderizar con dompdf (clase ya disponible via vendor/autoload.php)
            if (!class_exists(\Dompdf\Dompdf::class)) {
                throw new \RuntimeException('Dompdf no está instalado.');
            }
            $opciones = new \Dompdf\Options();
            $opciones->set('isRemoteEnabled', true);
            $opciones->set('isHtml5ParserEnabled', true);
            $opciones->set('defaultFont', 'Arial');
            $dompdf = new \Dompdf\Dompdf($opciones);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $numero   = preg_replace('/[^A-Za-z0-9\-]/', '_', (string) ($doc['com_documento_numero'] ?? 'documento'));
            $filename = 'Factura_' . $numero . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($modoDesc ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $dompdf->output();
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('GENERAR PDF DOCUMENTO', $e);
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(500);
            echo 'Error al generar el PDF: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * GET /compras/documentos/xml?id=X
     * Descarga el XML del SRI almacenado en base de datos para el documento indicado.
     */
    public function descargarXml(): void
    {
        $usuario     = $this->exigirSesion();
        $empresaId   = (int) $usuario['empresa_id'];
        $documentoId = (int) ($_GET['id'] ?? 0);

        try {
            $doc = $this->obtenerDocumentoPermitido($documentoId, $usuario);
            $xml = $this->documentoCompraModelo->obtenerXmlContenido($documentoId, $empresaId);

            if ($xml === null || $xml === '') {
                header('Content-Type: text/plain; charset=utf-8');
                http_response_code(404);
                echo 'Este documento no tiene XML del SRI disponible (puede ser un ingreso manual).';
                exit;
            }

            $numero   = preg_replace('/[^A-Za-z0-9\-]/', '_', (string) ($doc['com_documento_numero'] ?? 'documento'));
            $filename = 'Factura_' . $numero . '.xml';

            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $xml;
        } catch (Throwable $e) {
            $this->registroErrores->escribirExcepcion('DESCARGAR XML DOCUMENTO', $e);
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(500);
            echo 'Error al obtener el XML: ' . $e->getMessage();
        }
        exit;
    }

    public function buscarProveedor(): void
    {
        $usuario   = $this->exigirSesionJson();
        $empresaId = (int) $usuario['empresa_id'];
        $ruc       = trim((string) ($_GET['ruc'] ?? ''));
        if ($ruc === '') {
            $this->jsonOk(['proveedor' => null]);
        }
        $proveedor = $this->documentoCompraModelo->buscarProveedorPorRuc($empresaId, $ruc);
        $this->jsonOk(['proveedor' => $proveedor]);
    }

    public function buscarProveedores(): void
    {
        $usuario   = $this->exigirSesionJson();
        $empresaId = (int) $usuario['empresa_id'];
        $q         = trim((string) ($_GET['q'] ?? ''));
        $lista     = $this->documentoCompraModelo->buscarProveedores($empresaId, $q);
        $this->jsonOk(['proveedores' => $lista]);
    }

    public function productos(): void
    {
        $usuario   = $this->exigirSesionJson();
        $empresaId = (int) $usuario['empresa_id'];
        $tipo      = $_GET['tipo'] ?? 'codigo';
        $q         = trim((string) ($_GET['q'] ?? ''));

        $lista = $tipo === 'cod_proveedor'
            ? $this->documentoCompraModelo->buscarProductosPorCodProveedor($empresaId, $q)
            : $this->documentoCompraModelo->buscarProductosPorCodigo($empresaId, $q);

        $this->jsonOk(['productos' => $lista]);
    }

    public function detalle(): void
    {
        $usuario     = $this->exigirSesionJson();
        $documentoId = (int) ($_GET['documento_id'] ?? 0);
        $doc = $this->obtenerDocumentoPermitido($documentoId, $usuario);
        $lineas = $this->documentoCompraModelo->listarDetalle($documentoId);
        $this->jsonOk(['documento' => $doc, 'lineas' => $lineas]);
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private function normalizarCabecera(int $empresaId): array
    {
        return [
            'empresa_id'    => $empresaId,
            'proveedor_id'  => (int) ($_POST['proveedor_id']   ?? 0),
            'tipo_id'       => (int) ($_POST['tipo_id']        ?? 0),
            'bodega_id'     => (int) ($_POST['bodega_id']      ?? 0),
            'numero'        => trim((string) ($_POST['numero']        ?? '')),
            'fecha_emision' => trim((string) ($_POST['fecha_emision'] ?? '')),
            'subtotal'      => (float) ($_POST['subtotal']     ?? 0),
            'descuento'     => (float) ($_POST['descuento']    ?? 0),
            'iva'           => (float) ($_POST['iva_total']    ?? 0),
            'total'         => (float) ($_POST['total']        ?? 0),
            'observacion'   => trim((string) ($_POST['observacion']   ?? '')),
        ];
    }

    private function normalizarLineas(array $raw): array
    {
        $lineas = [];
        foreach ($raw as $item) {
            $productoId = (int) ($item['producto_id'] ?? 0);
            if ($productoId <= 0) {
                continue;
            }
            $cantidad  = (float) ($item['cantidad']  ?? 0);
            $costo     = (float) ($item['costo']     ?? 0);
            $descuento = (float) ($item['descuento'] ?? 0);
            $ivaId     = (int)   ($item['iva_id']    ?? 0);
            $ivaValor  = (float) ($item['iva_valor'] ?? 0);
            $pvp       = (float) ($item['pvp']       ?? 0);
            $base      = ($cantidad * $costo) - $descuento;
            $ivaLinea  = $base * ($ivaValor / 100);
            $lineas[]  = [
                'producto_id'   => $productoId,
                'cantidad'      => $cantidad,
                'costo'         => $costo,
                'descuento'     => $descuento,
                'total'         => round($base + $ivaLinea, 2),
                'iva_id'        => $ivaId > 0 ? $ivaId : null,
                'iva_valor'     => $ivaValor,
                'pvp'           => $pvp,
                'cod_proveedor' => trim((string) ($item['cod_proveedor'] ?? '')),
            ];
        }
        return $lineas;
    }

    private function validarCabecera(array $c): void
    {
        if ($c['empresa_id'] <= 0)   { throw new \InvalidArgumentException('Empresa no valida.'); }
        if ($c['proveedor_id'] <= 0) { throw new \InvalidArgumentException('Debe seleccionar un proveedor.'); }
        if ($c['tipo_id'] <= 0)      { throw new \InvalidArgumentException('Debe seleccionar el tipo de documento.'); }
        if ($c['bodega_id'] <= 0)    { throw new \InvalidArgumentException('Debe seleccionar la bodega destino.'); }
        if ($c['numero'] === '')     { throw new \InvalidArgumentException('El numero de documento es obligatorio.'); }
        if ($c['fecha_emision'] === '') { throw new \InvalidArgumentException('La fecha de emision es obligatoria.'); }
    }

    private function validarLineas(array $lineas): void
    {
        if (empty($lineas)) {
            throw new \InvalidArgumentException('Debe agregar al menos un producto al detalle.');
        }
        foreach ($lineas as $i => $l) {
            $n = $i + 1;
            if ($l['cantidad'] <= 0) { throw new \InvalidArgumentException("Linea {$n}: la cantidad debe ser mayor a 0."); }
            if ($l['costo'] < 0)     { throw new \InvalidArgumentException("Linea {$n}: el costo no puede ser negativo."); }
        }
    }

    private function obtenerDocumentoPermitido(int $documentoId, array $usuario): array
    {
        $doc = $documentoId > 0 ? $this->documentoCompraModelo->buscar($documentoId) : null;
        if (!$doc) {
            throw new \InvalidArgumentException('Documento no valido.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $doc['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede operar documentos de otra empresa.');
        }
        return $doc;
    }

    private function obtenerPermisos(array $usuario): array
    {
        $eId = (int) $usuario['empresa_id'];
        $pId = (int) $usuario['perfil_id'];
        $puedeCrear = $this->menuModelo->tienePermiso($eId, $pId, '/compras/documentos/crear');
        return [
            'crear'       => $puedeCrear,
            'editar'      => $puedeCrear,  // misma autorización que crear
            'registrar'   => $this->menuModelo->tienePermiso($eId, $pId, '/compras/documentos/registrar'),
            'anular'      => $this->menuModelo->tienePermiso($eId, $pId, '/compras/documentos/anular'),
            'subir_xml'   => $this->menuModelo->tienePermiso($eId, $pId, '/compras/documentos/subir-xml'),
            'procesar_txt' => $this->menuModelo->tienePermiso($eId, $pId, '/compras/documentos/procesar-txt'),
        ];
    }

    private function raizProyecto(): string
    {
        return dirname(__DIR__, 2); // src/../ = raiz del proyecto
    }

    private function jsonOk(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, ...$data], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
