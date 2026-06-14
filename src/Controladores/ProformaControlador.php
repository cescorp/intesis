<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\ProformaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class ProformaControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private ProformaModelo $proformaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    // =========================================================================
    // VISTAS
    // =========================================================================

    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/ventas/proformas/ver');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];

        $estadoFiltro = trim((string) ($_GET['estado'] ?? ''));
        $estadosValidos = ['CREADA', 'FACTURADA', 'ANULADA'];
        if (!in_array($estadoFiltro, $estadosValidos, true)) {
            $estadoFiltro = '';
        }

        $hoy   = date('Y-m-d');
        $desde = trim((string) ($_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'))));
        $hasta = trim((string) ($_GET['hasta'] ?? $hoy));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = date('Y-m-d', strtotime('-30 days')); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = $hoy; }

        $this->vista->renderizar('ventas/proformas', [
            'titulo'        => 'Proformas',
            'usuario'       => $usuario,
            'menus'         => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'proformas'     => $this->proformaModelo->listar($empresaId, $verTodas, $estadoFiltro, $desde, $hasta),
            'permisos'      => $this->obtenerPermisos($usuario),
            'estadoFiltro'  => $estadoFiltro,
            'desdeFiltro'   => $desde,
            'hastaFiltro'   => $hasta,
            'esSuperusuario'=> $verTodas,
            'mensaje'       => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos([
                'CONFIRMAR_ANULAR_PROFORMA',
                'CONFIRMAR_FACTURAR_PROFORMA',
            ]),
        ]);
    }

    public function nuevo(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/ventas/proformas/crear');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];

        $this->vista->renderizar('ventas/proforma_form', [
            'titulo'        => 'Nueva Proforma',
            'usuario'       => $usuario,
            'menus'         => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'empresas'      => $this->proformaModelo->listarEmpresasActivas($verTodas, $empresaId),
            'ivaList'       => $this->proformaModelo->listarIva($empresaId),
            'esSuperusuario'=> $verTodas,
            'permisos'      => $this->obtenerPermisos($usuario),
            'mensaje'       => $this->sesion->consumirMensaje(),
        ]);
    }

    public function editar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/ventas/proformas/editar');
        $verTodas  = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];

        $proformaId = (int) ($_GET['id'] ?? 0);
        $proforma   = $this->proformaModelo->buscar($proformaId, $empresaId);

        if (!$proforma) {
            $this->sesion->guardarMensaje('error', 'No encontrada', 'La proforma no existe o no pertenece a su empresa.');
            $this->redirigir('/ventas/proformas');
        }

        if ($proforma['sis_estado_codigo'] !== 'CREADA') {
            $this->sesion->guardarMensaje('error', 'No editable', 'Solo se pueden editar proformas en estado CREADA.');
            $this->redirigir('/ventas/proformas');
        }

        $lineas = $this->proformaModelo->listarDetalle($proformaId);

        $this->vista->renderizar('ventas/proforma_form', [
            'titulo'        => 'Editar Proforma',
            'usuario'       => $usuario,
            'menus'         => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'empresas'      => $this->proformaModelo->listarEmpresasActivas($verTodas, $empresaId),
            'ivaList'       => $this->proformaModelo->listarIva($empresaId),
            'esSuperusuario'=> $verTodas,
            'permisos'      => $this->obtenerPermisos($usuario),
            'proforma'      => $proforma,
            'lineas'        => $lineas,
            'mensaje'       => $this->sesion->consumirMensaje(),
        ]);
    }

    // =========================================================================
    // ACCIONES POST (JSON)
    // =========================================================================

    public function guardar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/crear');

        try {
            $body      = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
            $proformaId = (int) ($body['proforma_id'] ?? 0);
            $esEdicion  = $proformaId > 0;

            if ($esEdicion) {
                $this->exigirPermisoJson('/ventas/proformas/editar');
            }

            $empresaId = $this->esSuperusuario($usuario)
                ? (int) ($body['empresa_id'] ?? 0)
                : (int) $usuario['empresa_id'];

            $cabecera = $this->normalizarCabecera($body, $empresaId);
            $lineas   = $this->normalizarLineas($body['lineas'] ?? []);

            $this->validarCabecera($cabecera);
            $this->validarLineas($lineas);

            if ($esEdicion) {
                $this->proformaModelo->actualizar($proformaId, $cabecera, $lineas, (int) $usuario['id']);
                $this->responderJson(true, 'OK', 'Proforma actualizada correctamente.', ['proforma_id' => $proformaId]);
            } else {
                $nuevoId = $this->proformaModelo->crear($cabecera, $lineas, (int) $usuario['id']);
                $this->responderJson(true, 'OK', 'Proforma creada correctamente.', ['proforma_id' => $nuevoId]);
            }
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('GUARDAR PROFORMA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function anular(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/anular');

        try {
            $body       = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
            $proformaId = (int) ($body['proforma_id'] ?? 0);
            $empresaId  = (int) $usuario['empresa_id'];

            if ($proformaId <= 0) {
                $this->responderJson(false, 'ERROR_VALIDACION', 'ID de proforma inválido.');
            }

            $this->proformaModelo->anular($proformaId, $empresaId, (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Proforma anulada correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ANULAR PROFORMA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function facturar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/facturar');

        try {
            $body       = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
            $proformaId = (int) ($body['proforma_id'] ?? 0);
            $lineas     = $body['lineas'] ?? [];
            $empresaId  = (int) $usuario['empresa_id'];

            if ($proformaId <= 0) {
                $this->responderJson(false, 'ERROR_VALIDACION', 'ID de proforma inválido.');
            }
            if (!is_array($lineas) || empty($lineas)) {
                $this->responderJson(false, 'ERROR_VALIDACION', 'Debe enviar las líneas a facturar.');
            }

            $facturaId = $this->proformaModelo->facturar($proformaId, $empresaId, $lineas, (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Factura generada correctamente.', ['factura_id' => $facturaId]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('FACTURAR PROFORMA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    // =========================================================================
    // ENDPOINTS JSON DE APOYO
    // =========================================================================

    public function detalle(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/ver');

        $proformaId = (int) ($_GET['id'] ?? 0);
        $empresaId  = (int) $usuario['empresa_id'];

        $proforma = $this->proformaModelo->buscar($proformaId, $empresaId);
        if (!$proforma) {
            $this->responderJson(false, 'NO_ENCONTRADO', 'Proforma no encontrada.');
        }

        $lineas = $this->proformaModelo->listarDetalle($proformaId);
        $this->responderJson(true, 'OK', '', ['proforma' => $proforma, 'lineas' => $lineas]);
    }

    public function buscarCliente(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/crear');

        $empresaId = (int) $usuario['empresa_id'];
        $ruc       = trim((string) ($_GET['ruc'] ?? ''));

        if ($ruc === '') {
            $this->responderJson(false, 'ERROR_VALIDACION', 'Ingrese una identificación para buscar.');
        }

        $cliente = $this->proformaModelo->buscarClientePorRuc($empresaId, $ruc);
        if (!$cliente) {
            $this->responderJson(false, 'NO_ENCONTRADO', 'Cliente no encontrado.');
        }

        $this->responderJson(true, 'OK', '', ['cliente' => $cliente]);
    }

    public function buscarClientes(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/crear');

        $empresaId = (int) $usuario['empresa_id'];
        $termino   = trim((string) ($_GET['q'] ?? ''));

        if (strlen($termino) < 2) {
            $this->responderJson(true, 'OK', '', ['clientes' => []]);
        }

        $clientes = $this->proformaModelo->buscarClientes($empresaId, $termino);
        $this->responderJson(true, 'OK', '', ['clientes' => $clientes]);
    }

    public function productos(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/crear');

        $empresaId = (int) $usuario['empresa_id'];
        $termino   = trim((string) ($_GET['q'] ?? ''));

        if (strlen($termino) < 1) {
            $this->responderJson(true, 'OK', '', ['productos' => []]);
        }

        $productos = $this->proformaModelo->buscarProductos($empresaId, $termino);
        $this->responderJson(true, 'OK', '', ['productos' => $productos]);
    }

    public function verificarAprobacion(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/ventas/proformas/crear');

        try {
            $body      = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
            $empresaId = (int) $usuario['empresa_id'];
            $aprobUser = trim((string) ($body['usuario'] ?? ''));
            $aprobClave= (string) ($body['clave'] ?? '');

            if ($aprobUser === '' || $aprobClave === '') {
                $this->responderJson(false, 'ERROR_VALIDACION', 'Ingrese usuario y contraseña del aprobador.');
            }

            $aprobadorId = $this->proformaModelo->verificarAprobador($empresaId, $aprobUser, $aprobClave);
            if ($aprobadorId === null) {
                $this->responderJson(false, 'SIN_PERMISO', 'Usuario o contraseña incorrectos, o el usuario no tiene permiso para aprobar precios.');
            }

            $this->responderJson(true, 'OK', 'Precio aprobado.', ['aprobador_id' => $aprobadorId]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('VERIFICAR APROBACION PRECIO', $excepcion);
            $this->responderJson(false, 'ERROR_INTERNO', $excepcion->getMessage());
        }
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function normalizarCabecera(array $body, int $empresaId): array
    {
        return [
            'empresa_id'    => $empresaId,
            'cliente_id'    => (int) ($body['cliente_id'] ?? 0),
            'fecha_emision' => trim((string) ($body['fecha_emision'] ?? '')),
            'subtotal'      => round((float) ($body['subtotal'] ?? 0), 4),
            'descuento'     => round((float) ($body['descuento'] ?? 0), 4),
            'iva'           => round((float) ($body['iva'] ?? 0), 4),
            'total'         => round((float) ($body['total'] ?? 0), 4),
            'observacion'   => trim((string) ($body['observacion'] ?? '')),
        ];
    }

    private function normalizarLineas(array $lineasRaw): array
    {
        $lineas = [];
        foreach ($lineasRaw as $l) {
            $lineas[] = [
                'producto_id'    => (int) ($l['producto_id'] ?? 0),
                'codigo'         => trim((string) ($l['codigo'] ?? '')),
                'descripcion'    => trim((string) ($l['descripcion'] ?? '')),
                'cantidad'       => round((float) ($l['cantidad'] ?? 0), 4),
                'precio'         => round((float) ($l['precio'] ?? 0), 6),
                'descuento'      => round((float) ($l['descuento'] ?? 0), 2),
                'descuento_valor'=> round((float) ($l['descuento_valor'] ?? 0), 4),
                'total'          => round((float) ($l['total'] ?? 0), 4),
                'iva_id'         => (int) ($l['iva_id'] ?? 0),
                'iva_valor'      => round((float) ($l['iva_valor'] ?? 0), 4),
                'pvp'            => round((float) ($l['pvp'] ?? 0), 6),
                'precio_min'     => round((float) ($l['precio_min'] ?? 0), 6),
                'aprobado_por'   => (int) ($l['aprobado_por'] ?? 0),
            ];
        }
        return $lineas;
    }

    private function validarCabecera(array $c): void
    {
        if ($c['cliente_id'] <= 0) {
            throw new \InvalidArgumentException('Seleccione un cliente.');
        }
        if ($c['fecha_emision'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['fecha_emision'])) {
            throw new \InvalidArgumentException('Formato de fecha inválido.');
        }
    }

    private function validarLineas(array $lineas): void
    {
        if (empty($lineas)) {
            throw new \InvalidArgumentException('La proforma debe tener al menos una línea de detalle.');
        }
        foreach ($lineas as $i => $l) {
            $n = $i + 1;
            if ($l['producto_id'] <= 0) {
                throw new \InvalidArgumentException("Línea {$n}: seleccione un producto.");
            }
            if ($l['cantidad'] <= 0) {
                throw new \InvalidArgumentException("Línea {$n}: la cantidad debe ser mayor a cero.");
            }
            if ($l['precio'] < 0) {
                throw new \InvalidArgumentException("Línea {$n}: el precio no puede ser negativo.");
            }
        }
    }

    private function obtenerPermisos(array $usuario): array
    {
        $eId = (int) $usuario['empresa_id'];
        $pId = (int) $usuario['perfil_id'];
        return [
            'crear'    => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/proformas/crear'),
            'editar'   => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/proformas/editar'),
            'anular'   => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/proformas/anular'),
            'facturar' => $this->menuModelo->tienePermiso($eId, $pId, '/ventas/proformas/facturar'),
        ];
    }
}
