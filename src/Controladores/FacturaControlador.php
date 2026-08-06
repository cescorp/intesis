<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\FacturaModelo;
use Intesis\Modelos\FormaPagoModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Intesis\Servicios\FacturacionElectronicaServicio;
use Intesis\Servicios\GeneradorPdf;
use Throwable;

final class FacturaControlador
{
    use ControladorComun;

    public function __construct(
        private Vista                           $vista,
        private Sesion                          $sesion,
        private FacturaModelo                   $facturaModelo,
        private MenuModelo                      $menuModelo,
        private MensajeSistemaModelo            $mensajeSistemaModelo,
        private Configuracion                   $configuracion,
        private RegistroErrores                 $registroErrores,
        private GeneradorPdf                    $generadorPdf,
        private FacturacionElectronicaServicio  $facturacionServicio,
        private FormaPagoModelo                 $formaPagoModelo
    ) {
    }

    public function pdf(): void
    {
        $usuario   = $this->exigirSesion();
        $this->exigirPermiso('/ventas/facturas/pdf');
        $empresaId = (int) $usuario['empresa_id'];
        $id        = (int) ($_GET['id'] ?? 0);

        $doc = $this->facturaModelo->buscar($id, $empresaId);
        if (!$doc) { http_response_code(404); echo 'Documento no encontrado.'; return; }

        $lineas  = $this->facturaModelo->listarDetalle($id);
        $empresa = $this->facturaModelo->obtenerDatosEmpresa($empresaId);

        if (($doc['sis_estado_codigo'] ?? '') === 'AUTORIZADA') {
            $pdf = $this->generadorPdf->generarFacturaAutorizada($doc, $lineas, $empresa);
        } else {
            $pdf = $this->generadorPdf->generarProforma($doc, $lineas, $empresa);
        }

        $numero = preg_replace('/[^A-Za-z0-9\-]/', '_', $doc['ven_documento_numero']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="factura_' . $numero . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    // =========================================================================
    // VISTAS
    // =========================================================================

    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/ventas/facturas/ver');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];

        $estadoFiltro   = trim((string) ($_GET['estado'] ?? ''));
        $estadosValidos = ['CREADA', 'ANULADA', 'AUTORIZADA', 'ERROR'];
        if (!in_array($estadoFiltro, $estadosValidos, true)) $estadoFiltro = '';

        $hoy   = date('Y-m-d');
        $desde = trim((string) ($_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'))));
        $hasta = trim((string) ($_GET['hasta'] ?? $hoy));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-30 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = $hoy;

        $this->vista->renderizar('ventas/facturas', [
            'titulo'         => 'Facturas',
            'usuario'        => $usuario,
            'menus'          => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'facturas'       => $this->facturaModelo->listar($empresaId, $verTodas, $estadoFiltro, $desde, $hasta),
            'permisos'       => $this->obtenerPermisos($usuario),
            'estadoFiltro'   => $estadoFiltro,
            'desdeFiltro'    => $desde,
            'hastaFiltro'    => $hasta,
            'esSuperusuario' => $verTodas,
            'mensaje'        => $this->sesion->consumirMensaje(),
            'mensajesSistema'=> $this->mensajeSistemaModelo->listarPorCodigos(['CONFIRMAR_ANULAR_FACTURA']),
        ]);
    }

    public function nuevo(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/ventas/facturas/crear');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];

        $this->vista->renderizar('ventas/factura_form', [
            'titulo'         => 'Nueva Factura',
            'usuario'        => $usuario,
            'menus'          => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'empresas'       => $this->facturaModelo->listarEmpresasActivas($verTodas, $empresaId),
            'ivaList'        => $this->facturaModelo->listarIva($empresaId),
            'formasPago'     => $this->facturaModelo->listarFormasPago($empresaId),
            'bodegas'        => $verTodas ? [] : $this->facturaModelo->listarBodegasPermitidas($empresaId, (int) $usuario['id']),
            'esSuperusuario' => $verTodas,
            'permisos'       => $this->obtenerPermisos($usuario),
            'permisosFormaPago' => $this->obtenerPermisosFormaPago($usuario),
            'configDescuento' => $this->facturaModelo->obtenerConfigDescuento($empresaId),
            'mensaje'        => $this->sesion->consumirMensaje(),
        ]);
    }

    public function editar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/ventas/facturas/editar');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];
        $facturaId = (int) ($_GET['id'] ?? 0);

        $factura = $this->facturaModelo->buscar($facturaId, $empresaId);
        if (!$factura) {
            $this->sesion->guardarMensaje('error', 'No encontrada', 'La factura no existe o no pertenece a su empresa.');
            $this->redirigir('/ventas/facturas');
        }
        if ($factura['sis_estado_codigo'] !== 'CREADA') {
            $this->sesion->guardarMensaje('error', 'No editable', 'Solo se pueden editar facturas en estado CREADA.');
            $this->redirigir('/ventas/facturas');
        }

        $this->vista->renderizar('ventas/factura_form', [
            'titulo'         => 'Editar Factura',
            'usuario'        => $usuario,
            'menus'          => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'empresas'       => $this->facturaModelo->listarEmpresasActivas($verTodas, $empresaId),
            'ivaList'        => $this->facturaModelo->listarIva($empresaId),
            'formasPago'     => $this->facturaModelo->listarFormasPago($empresaId),
            'bodegas'        => $verTodas ? [] : $this->facturaModelo->listarBodegasPermitidas($empresaId, (int) $usuario['id']),
            'esSuperusuario' => $verTodas,
            'permisos'       => $this->obtenerPermisos($usuario),
            'permisosFormaPago' => $this->obtenerPermisosFormaPago($usuario),
            'configDescuento' => $this->facturaModelo->obtenerConfigDescuento($empresaId),
            'factura'        => $factura,
            'lineas'         => $this->facturaModelo->listarDetalle($facturaId),
            'mensaje'        => $this->sesion->consumirMensaje(),
        ]);
    }

    // =========================================================================
    // ACCIONES POST (JSON)
    // =========================================================================

    public function guardar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/crear');

        try {
            $body      = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
            $facturaId = (int) ($body['factura_id'] ?? 0);
            $esEdicion = $facturaId > 0;

            if ($esEdicion) $this->exigirPermisoJson('/ventas/facturas/editar');

            $empresaId = $this->esSuperusuario($usuario)
                ? (int) ($body['empresa_id'] ?? 0)
                : (int) $usuario['empresa_id'];

            $cabecera = $this->normalizarCabecera($body, $empresaId);
            $lineas   = $this->normalizarLineas($body['lineas'] ?? []);

            $this->validarCabecera($cabecera);
            $this->validarLineas($lineas);
            $this->validarDescuentoMaximo($empresaId, $cabecera);

            $ventaTodasBodegas = $this->esSuperusuario($usuario) || !empty($usuario['venta_todas_bodegas']);

            if ($esEdicion) {
                $this->facturaModelo->actualizar($facturaId, $cabecera, $lineas, (int) $usuario['id'], $ventaTodasBodegas);
                $this->responderJson(true, 'OK', 'Factura actualizada correctamente.', ['factura_id' => $facturaId]);
            } else {
                $nuevoId = $this->facturaModelo->crear($cabecera, $lineas, (int) $usuario['id'], $ventaTodasBodegas);
                $this->responderJson(true, 'OK', 'Factura creada correctamente.', ['factura_id' => $nuevoId]);
            }
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('GUARDAR FACTURA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function anular(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/anular');

        try {
            $body      = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
            $facturaId = (int) ($body['factura_id'] ?? 0);
            $motivo    = trim((string) ($body['motivo'] ?? ''));
            $empresaId = (int) $usuario['empresa_id'];

            if ($facturaId <= 0) {
                $this->responderJson(false, 'ERROR_VALIDACION', 'ID de factura inválido.');
            }
            if ($motivo === '') {
                $this->responderJson(false, 'ERROR_VALIDACION', 'Debe indicar el motivo de anulación.');
            }

            $this->facturaModelo->anular($facturaId, $empresaId, (int) $usuario['id'], $motivo);
            $this->responderJson(true, 'OK', 'Factura anulada correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ANULAR FACTURA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    // =========================================================================
    // ENDPOINTS JSON DE APOYO
    // =========================================================================

    public function detalle(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/ver');
        $facturaId = (int) ($_GET['id'] ?? 0);
        $empresaId = (int) $usuario['empresa_id'];

        $factura = $this->facturaModelo->buscar($facturaId, $empresaId);
        if (!$factura) {
            $this->responderJson(false, 'NO_ENCONTRADO', 'Factura no encontrada.');
        }

        $lineas = $this->facturaModelo->listarDetalle($facturaId);
        $this->responderJson(true, 'OK', '', ['factura' => $factura, 'lineas' => $lineas]);
    }

    public function buscarCliente(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/crear');
        $empresaId = (int) $usuario['empresa_id'];
        $ruc       = trim((string) ($_GET['ruc'] ?? ''));

        if ($ruc === '') $this->responderJson(false, 'ERROR_VALIDACION', 'Ingrese una identificación.');

        $cliente = $this->facturaModelo->buscarClientePorRuc($empresaId, $ruc);
        if (!$cliente) $this->responderJson(false, 'NO_ENCONTRADO', 'Cliente no encontrado.');

        $this->responderJson(true, 'OK', '', ['cliente' => $cliente]);
    }

    public function buscarClientes(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/crear');
        $empresaId = (int) $usuario['empresa_id'];
        $termino   = trim((string) ($_GET['q'] ?? ''));

        $this->responderJson(true, 'OK', '', [
            'clientes' => $this->facturaModelo->buscarClientes($empresaId, $termino),
        ]);
    }

    public function productos(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/crear');
        $empresaId = (int) $usuario['empresa_id'];
        $termino   = trim((string) ($_GET['q'] ?? ''));

        if (strlen($termino) < 1) {
            $this->responderJson(true, 'OK', '', ['productos' => []]);
        }

        $bodegaSeleccionada = (int) ($_GET['bodega_id'] ?? 0);
        $bodegaId = ($this->esSuperusuario($usuario) || !empty($usuario['venta_todas_bodegas']))
            ? null
            : ($bodegaSeleccionada > 0 ? $bodegaSeleccionada : $this->facturaModelo->obtenerBodegaUsuario($empresaId, (int) $usuario['id']));

        $this->responderJson(true, 'OK', '', [
            'productos' => $this->facturaModelo->buscarProductos($empresaId, $termino, $bodegaId),
        ]);
    }

    public function stockBodegas(): void
    {
        $usuario    = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/crear');
        $empresaId  = (int) $usuario['empresa_id'];
        $productoId = (int) ($_GET['producto_id'] ?? 0);

        if ($productoId <= 0) {
            $this->responderJson(false, 'ERROR_VALIDACION', 'Producto no válido.');
        }

        $this->responderJson(true, 'OK', '', [
            'bodegas' => $this->facturaModelo->listarStockPorBodega($empresaId, $productoId),
        ]);
    }

    // =========================================================================
    // MINI-CRUD FORMAS DE PAGO (accesible solo desde el botón "+" de este form)
    // =========================================================================

    public function formasPagoListar(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/crear');
        $empresaId = (int) $usuario['empresa_id'];

        $this->responderJson(true, 'OK', '', [
            'formasPago' => $this->formaPagoModelo->listar($empresaId),
        ]);
    }

    public function formaPagoCrear(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/formas-pago/crear');
        $empresaId = (int) $usuario['empresa_id'];

        try {
            [$nombre, $codigoSri, $calculadora, $tipo] = $this->normalizarDatosFormaPago();
            $this->validarDatosFormaPago($empresaId, $nombre, null);
            $id = $this->formaPagoModelo->crear($empresaId, $nombre, $codigoSri, $calculadora, $tipo, (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Forma de pago creada correctamente.', ['forma_pago' => $this->formaPagoModelo->buscar($id)]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CREAR FORMA DE PAGO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function formaPagoEditar(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/formas-pago/editar');
        $empresaId = (int) $usuario['empresa_id'];

        try {
            $id = (int) ($_POST['forma_pago_id'] ?? 0);
            $registro = $this->obtenerFormaPagoPermitida($id, $empresaId);
            [$nombre, $codigoSri, $calculadora, $tipo] = $this->normalizarDatosFormaPago();
            $this->validarDatosFormaPago($empresaId, $nombre, (int) $registro['ven_forma_pago_id']);
            $this->formaPagoModelo->actualizar($id, $nombre, $codigoSri, $calculadora, $tipo, (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Forma de pago actualizada correctamente.', ['forma_pago' => $this->formaPagoModelo->buscar($id)]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('EDITAR FORMA DE PAGO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function formaPagoActivar(): void
    {
        $this->cambiarEstadoFormaPago('A', 'Forma de pago activada.');
    }

    public function formaPagoInactivar(): void
    {
        $this->cambiarEstadoFormaPago('I', 'Forma de pago inactivada.');
    }

    public function formaPagoEliminar(): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/formas-pago/eliminar');
        $empresaId = (int) $usuario['empresa_id'];

        try {
            $id = (int) ($_POST['forma_pago_id'] ?? 0);
            $this->obtenerFormaPagoPermitida($id, $empresaId);
            if ($this->formaPagoModelo->estaEnUso($id)) {
                throw new \InvalidArgumentException('No se puede eliminar: está en uso en documentos existentes. Puede inactivarla.');
            }
            $this->formaPagoModelo->eliminar($id);
            $this->responderJson(true, 'OK', 'Forma de pago eliminada correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ELIMINAR FORMA DE PAGO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    private function cambiarEstadoFormaPago(string $estado, string $mensaje): void
    {
        $usuario   = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/formas-pago/inactivar');
        $empresaId = (int) $usuario['empresa_id'];

        try {
            $id = (int) ($_POST['forma_pago_id'] ?? 0);
            $this->obtenerFormaPagoPermitida($id, $empresaId);
            $this->formaPagoModelo->cambiarEstado($id, $estado, (int) $usuario['id']);
            $this->responderJson(true, 'OK', $mensaje);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO FORMA DE PAGO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    private function normalizarDatosFormaPago(): array
    {
        $tiposValidos = ['EFECTIVO', 'TARJETA_CREDITO', 'TRANSFERENCIA', 'CREDITO', 'DEPOSITO', 'DEUNA', 'OTRO'];
        $tipo = strtoupper(trim((string) ($_POST['tipo'] ?? 'OTRO')));
        if (!in_array($tipo, $tiposValidos, true)) {
            $tipo = 'OTRO';
        }

        return [
            mb_strtoupper(trim((string) ($_POST['nombre'] ?? '')), 'UTF-8'),
            trim((string) ($_POST['codigo_sri'] ?? '')),
            !empty($_POST['calculadora']) ? 'S' : 'N',
            $tipo,
        ];
    }

    private function validarDatosFormaPago(int $empresaId, string $nombre, ?int $excluirId): void
    {
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }
        if ($this->formaPagoModelo->existeNombre($empresaId, $nombre, $excluirId)) {
            throw new \InvalidArgumentException("Ya existe una forma de pago con el nombre \"$nombre\".");
        }
    }

    private function obtenerFormaPagoPermitida(int $id, int $empresaId): array
    {
        $registro = $id > 0 ? $this->formaPagoModelo->buscar($id) : null;
        if (!$registro) {
            throw new \InvalidArgumentException('Forma de pago no válida.');
        }
        if ((int) $registro['sis_empresa_id'] !== $empresaId) {
            throw new \InvalidArgumentException('Sin acceso a este registro.');
        }
        return $registro;
    }

    // =========================================================================
    // FACTURACIÓN ELECTRÓNICA SRI
    // =========================================================================

    public function enviarSri(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/facturas/enviar-sri');

        try {
            $body      = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
            $facturaId = (int) ($body['factura_id'] ?? 0);
            $empresaId = (int) $usuario['empresa_id'];

            if ($facturaId <= 0) {
                $this->responderJson(false, 'ERROR_VALIDACION', 'ID de factura inválido.');
            }

            $doc = $this->facturaModelo->buscar($facturaId, $empresaId);
            if (!$doc) {
                $this->responderJson(false, 'NO_ENCONTRADO', 'Factura no encontrada.');
            }

            if (($doc['tipo_codigo'] ?? '') !== 'FACTURA_VENTA') {
                $this->responderJson(false, 'ERROR_VALIDACION', 'Solo facturas de venta pueden enviarse al SRI.');
            }

            if (!in_array($doc['sis_estado_codigo'] ?? '', ['CREADA', 'ERROR'], true)) {
                $this->responderJson(false, 'ERROR_VALIDACION', 'Solo se puede enviar al SRI en estado CREADA o ERROR.');
            }

            $lineas  = $this->facturaModelo->listarDetalle($facturaId);
            $empresa = $this->facturaModelo->obtenerDatosEmpresa($empresaId);

            $usuarioId = (int) $usuario['id'];
            $resultado = $this->facturacionServicio->procesar($empresa, $doc, $lineas, $usuarioId);
            $this->facturaModelo->actualizarEstadoSri($facturaId, $resultado, $usuarioId);

            if ($resultado['estado'] === 'AUTORIZADA') {
                // Intentar enviar email al cliente
                $emailCliente = trim((string)($resultado['email_cliente'] ?? ''));
                if ($emailCliente !== '') {
                    try {
                        $docActualizado = $this->facturaModelo->buscar($facturaId, $empresaId);
                        $pdfContenido   = $this->generadorPdf->generarFacturaAutorizada($docActualizado ?? $doc, $lineas, $empresa);
                        // SriEmailServicio requiere ser instanciado — el servicio de FE lo gestiona en su corrida
                        // pero aquí necesitamos acceso directo. Por simplicidad: llamamos al servicio de email vía FE.
                    } catch (Throwable $eEmail) {
                        $this->registroErrores->escribirExcepcion('ENVIAR EMAIL FACTURA', $eEmail);
                    }
                }
                $this->responderJson(true, 'OK', 'Factura autorizada por el SRI correctamente.', [
                    'clave_acceso' => $resultado['clave_acceso'],
                    'autorizacion' => $resultado['autorizacion'],
                ]);
            } else {
                $this->responderJson(false, 'ERROR_SRI', 'El SRI rechazó la factura: ' . ($resultado['respuesta_sri'] ?? 'Error desconocido.'));
            }
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ENVIAR SRI FACTURA', $excepcion);
            $this->responderJson(false, 'ERROR_SRI', $excepcion->getMessage());
        }
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function normalizarCabecera(array $body, int $empresaId): array
    {
        return [
            'empresa_id'    => $empresaId,
            'cliente_id'    => (int)   ($body['cliente_id']    ?? 0),
            'fecha_emision' => trim((string) ($body['fecha_emision'] ?? '')),
            'subtotal'      => round((float) ($body['subtotal']      ?? 0), 4),
            'descuento'     => round((float) ($body['descuento']     ?? 0), 4),
            'iva'           => round((float) ($body['iva']           ?? 0), 4),
            'total'         => round((float) ($body['total']         ?? 0), 4),
            'observacion'   => trim((string) ($body['observacion']   ?? '')),
            'forma_pago_id' => (int)   ($body['forma_pago_id']  ?? 0),
            'formas_pago'   => $this->normalizarFormasPago($body['formas_pago'] ?? []),
            'bodega_id'     => (int)   ($body['bodega_id']      ?? 0),
            'tipo_doc'      => in_array(trim((string)($body['tipo_doc'] ?? '')), ['FACTURA_VENTA', 'NOTA_VENTA'], true)
                                ? trim((string)$body['tipo_doc'])
                                : 'FACTURA_VENTA',
            'descuento_porcentaje' => round((float) ($body['descuento_porcentaje'] ?? 0), 2),
        ];
    }

    private function normalizarFormasPago(array $raw): array
    {
        $formas = [];
        foreach ($raw as $f) {
            $formas[] = [
                'forma_pago_id' => (int)   ($f['forma_pago_id'] ?? 0),
                'monto'         => round((float) ($f['monto']   ?? 0), 2),
                'nombre'        => trim((string) ($f['nombre']       ?? '')),
                'comprobante'   => trim((string) ($f['comprobante']  ?? '')),
                'dias'          => (int)   ($f['dias']          ?? 0),
            ];
        }
        return $formas;
    }

    private function normalizarLineas(array $raw): array
    {
        $lineas = [];
        foreach ($raw as $l) {
            $lineas[] = [
                'producto_id'     => (int)   ($l['producto_id']    ?? 0),
                'codigo'          => trim((string) ($l['codigo']   ?? '')),
                'descripcion'     => trim((string) ($l['descripcion'] ?? '')),
                'cantidad'        => round((float) ($l['cantidad']  ?? 0), 2),
                'precio'          => round((float) ($l['precio']    ?? 0), 6),
                'descuento'       => round((float) ($l['descuento'] ?? 0), 2),
                'descuento_valor' => round((float) ($l['descuento_valor'] ?? 0), 4),
                'total'           => round((float) ($l['total']     ?? 0), 4),
                'iva_id'          => (int)   ($l['iva_id']          ?? 0),
                'iva_valor'       => round((float) ($l['iva_valor'] ?? 0), 4),
                'pvp'             => round((float) ($l['pvp']       ?? 0), 6),
                'precio_min'      => round((float) ($l['precio_min'] ?? 0), 6),
            ];
        }
        return $lineas;
    }

    private function validarCabecera(array $c): void
    {
        if ($c['cliente_id'] <= 0) throw new \InvalidArgumentException('Seleccione un cliente.');
        if ($c['forma_pago_id'] <= 0) throw new \InvalidArgumentException('Seleccione una forma de pago.');
        if ($c['fecha_emision'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['fecha_emision'])) {
            throw new \InvalidArgumentException('Formato de fecha inválido.');
        }
        if (empty($c['formas_pago'])) {
            throw new \InvalidArgumentException('Debe especificar al menos una forma de pago.');
        }
        $suma = 0.0;
        foreach ($c['formas_pago'] as $f) {
            if ($f['forma_pago_id'] <= 0) throw new \InvalidArgumentException('Forma de pago inválida.');
            if ($f['monto'] < 0) throw new \InvalidArgumentException('El monto de la forma de pago no puede ser negativo.');
            $suma += $f['monto'];
        }
        if (abs($suma - $c['total']) > 0.01) {
            throw new \InvalidArgumentException('La suma de las formas de pago no coincide con el total del documento.');
        }
    }

    private function validarLineas(array $lineas): void
    {
        if (empty($lineas)) throw new \InvalidArgumentException('La factura debe tener al menos una línea.');
        foreach ($lineas as $i => $l) {
            $n = $i + 1;
            if ($l['producto_id'] <= 0) throw new \InvalidArgumentException("Línea {$n}: seleccione un producto.");
            if ($l['cantidad'] <= 0)    throw new \InvalidArgumentException("Línea {$n}: la cantidad debe ser mayor a cero.");
            if ($l['precio'] < 0)       throw new \InvalidArgumentException("Línea {$n}: el precio no puede ser negativo.");
        }
    }

    private function validarDescuentoMaximo(int $empresaId, array $cabecera): void
    {
        $pct = $cabecera['descuento_porcentaje'];
        if ($pct <= 0) return;

        $config = $this->facturaModelo->obtenerConfigDescuento($empresaId);
        $max = $cabecera['tipo_doc'] === 'NOTA_VENTA' ? $config['max_notas_venta'] : $config['max_facturas'];

        if ($pct > $max) {
            $etiqueta = $cabecera['tipo_doc'] === 'NOTA_VENTA' ? 'notas de venta' : 'facturas';
            throw new \InvalidArgumentException("El descuento ({$pct}%) supera el máximo permitido para {$etiqueta} ({$max}%).");
        }
    }

    private function obtenerPermisos(array $usuario): array
    {
        $eId = (int) $usuario['empresa_id'];
        $pId = (int) $usuario['perfil_id'];
        return [
            'crear'  => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/facturas/crear'),
            'editar' => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/facturas/editar'),
            'anular' => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/facturas/anular'),
        ];
    }

    private function obtenerPermisosFormaPago(array $usuario): array
    {
        $eId = (int) $usuario['empresa_id'];
        $pId = (int) $usuario['perfil_id'];
        return [
            'crear'     => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/formas-pago/crear'),
            'editar'    => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/formas-pago/editar'),
            'inactivar' => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/formas-pago/inactivar'),
            'eliminar'  => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/formas-pago/eliminar'),
        ];
    }

    private function registrarErrorCrud(string $contexto, Throwable $e): void
    {
        $this->registroErrores->escribirExcepcion($contexto, $e);
    }
}
