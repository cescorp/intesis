<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\DocumentoCompraModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
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
        private RegistroErrores $registroErrores
    ) {
    }

    // =========================================================================
    // VISTAS
    // =========================================================================

    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/compras/documentos/ver');
        $verTodas = $this->esSuperusuario($usuario);

        $estadoFiltro = trim((string) ($_GET['estado'] ?? ''));
        $estadosValidos = ['BORRADOR', 'REGISTRADO', 'ANULADO'];
        if (!in_array($estadoFiltro, $estadosValidos, true)) {
            $estadoFiltro = '';
        }

        $this->vista->renderizar('compras/documentos', [
            'titulo'         => 'Documentos de Compra',
            'usuario'        => $usuario,
            'menus'          => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'documentos'     => $this->documentoCompraModelo->listar((int) $usuario['empresa_id'], $verTodas, $estadoFiltro),
            'esSuperusuario' => $verTodas,
            'estadoFiltro'   => $estadoFiltro,
            'permisos'       => $this->obtenerPermisos($usuario),
            'mensaje'        => $this->sesion->consumirMensaje(),
            'mensajesSistema'=> $this->mensajeSistemaModelo->listarPorCodigos(['CONFIRMAR_ANULAR_DOCUMENTO']),
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
    // AJAX
    // =========================================================================

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
            'crear'     => $puedeCrear,
            'editar'    => $puedeCrear,  // misma autorización que crear
            'registrar' => $this->menuModelo->tienePermiso($eId, $pId, '/compras/documentos/registrar'),
            'anular'    => $this->menuModelo->tienePermiso($eId, $pId, '/compras/documentos/anular'),
        ];
    }

    private function jsonOk(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, ...$data], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
