<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\FacturaModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class FacturaControlador
{
    use ControladorComun;

    public function __construct(
        private Vista                 $vista,
        private Sesion                $sesion,
        private FacturaModelo         $facturaModelo,
        private MenuModelo            $menuModelo,
        private MensajeSistemaModelo  $mensajeSistemaModelo,
        private Configuracion         $configuracion,
        private RegistroErrores       $registroErrores
    ) {
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
            'esSuperusuario' => $verTodas,
            'permisos'       => $this->obtenerPermisos($usuario),
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
            'esSuperusuario' => $verTodas,
            'permisos'       => $this->obtenerPermisos($usuario),
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

            if ($esEdicion) {
                $this->facturaModelo->actualizar($facturaId, $cabecera, $lineas, (int) $usuario['id']);
                $this->responderJson(true, 'OK', 'Factura actualizada correctamente.', ['factura_id' => $facturaId]);
            } else {
                $nuevoId = $this->facturaModelo->crear($cabecera, $lineas, (int) $usuario['id']);
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
            $empresaId = (int) $usuario['empresa_id'];

            if ($facturaId <= 0) {
                $this->responderJson(false, 'ERROR_VALIDACION', 'ID de factura inválido.');
            }

            $this->facturaModelo->anular($facturaId, $empresaId, (int) $usuario['id']);
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

        if (strlen($termino) < 2) {
            $this->responderJson(true, 'OK', '', ['clientes' => []]);
        }

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

        $this->responderJson(true, 'OK', '', [
            'productos' => $this->facturaModelo->buscarProductos($empresaId, $termino),
        ]);
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
            'tipo_doc'      => in_array(trim((string)($body['tipo_doc'] ?? '')), ['FACTURA_VENTA', 'NOTA_VENTA'], true)
                                ? trim((string)$body['tipo_doc'])
                                : 'FACTURA_VENTA',
        ];
    }

    private function normalizarLineas(array $raw): array
    {
        $lineas = [];
        foreach ($raw as $l) {
            $lineas[] = [
                'producto_id'     => (int)   ($l['producto_id']    ?? 0),
                'codigo'          => trim((string) ($l['codigo']   ?? '')),
                'descripcion'     => trim((string) ($l['descripcion'] ?? '')),
                'cantidad'        => round((float) ($l['cantidad']  ?? 0), 4),
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

    private function registrarErrorCrud(string $contexto, Throwable $e): void
    {
        $this->registroErrores->registrar("[{$contexto}] " . $e->getMessage(), $e);
    }
}
