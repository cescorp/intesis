<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\KardexModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Intesis\Servicios\GeneradorPdf;
use Throwable;

final class KardexControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private KardexModelo $kardexModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores,
        private GeneradorPdf $generadorPdf
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA LA CONSULTA DE KARDEX POR PRODUCTOS Y BODEGAS.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/kardex/ver');
        $verTodas = $this->esSuperusuario($usuario);
        $empresaId = $verTodas ? (int) ($_GET['empresa_id'] ?? $usuario['empresa_id']) : (int) $usuario['empresa_id'];

        $this->vista->renderizar('inventario/kardex', [
            'titulo' => 'Kardex',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'empresas' => $this->kardexModelo->listarEmpresasActivas($verTodas, (int) $usuario['empresa_id']),
            'empresaSeleccionada' => $empresaId,
            'bodegas' => $this->kardexModelo->listarBodegas($empresaId),
            'kardex' => $this->kardexModelo->listarKardexGlobal($empresaId),
            'esSuperusuario' => $verTodas,
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos(['USUARIO_DATOS_OBLIGATORIOS']),
        ]);
    }

    /**
     * ***************************************************************************
     * * DEVUELVE EL DETALLE HISTORICO DE KARDEX DE UN PRODUCTO.
     * ***************************************************************************
     */
    public function detalle(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/kardex/detalle');
        try {
            $empresaId = $this->obtenerEmpresaPermitida($usuario, (int) ($_GET['empresa_id'] ?? 0));
            $productoId = (int) ($_GET['producto_id'] ?? 0);
            $desde = $this->normalizarFecha($_GET['desde'] ?? null);
            $hasta = $this->normalizarFecha($_GET['hasta'] ?? null);
            if (!$this->kardexModelo->productoPerteneceEmpresa($empresaId, $productoId)) {
                throw new \InvalidArgumentException('Producto no valido.');
            }
            $this->responderJson(true, 'KARDEX_DETALLE_OK', 'Detalle kardex listado.', [
                'bodegas' => $this->kardexModelo->listarBodegas($empresaId),
                'movimientos' => array_map(fn (array $fila): array => $this->formatearMovimiento($fila), $this->kardexModelo->listarDetalleProducto($empresaId, $productoId, $desde, $hasta)),
            ]);
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribir('DETALLE KARDEX: ' . $excepcion->getMessage());
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * GENERA PDF DE DOCUMENTO INTERNO RELACIONADO AL KARDEX.
     * ***************************************************************************
     */
    public function documento(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/kardex/documento');
        try {
            $empresaId = $this->obtenerEmpresaPermitida($usuario, (int) ($_GET['empresa_id'] ?? 0));
            $documentoTipo = strtoupper(trim((string) ($_GET['documento_tipo'] ?? '')));
            $documentoId = (int) ($_GET['documento_id'] ?? 0);
            if ($documentoTipo !== 'INV_MOVIMIENTOS' || $documentoId <= 0) {
                throw new \InvalidArgumentException('Documento no valido para PDF.');
            }
            $documento = $this->kardexModelo->obtenerDocumentoInventario($empresaId, $documentoId);
            if (!$documento) {
                throw new \InvalidArgumentException('Documento no encontrado.');
            }
            $cabeceraPdf = $this->normalizarCabeceraPdf($documento['cabecera']);
            $pdf = $this->generadorPdf->generarMovimientoInventario($cabeceraPdf, $documento['detalles']);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="documento_' . $documentoId . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribir('PDF KARDEX: ' . $excepcion->getMessage());
            http_response_code(404);
            echo 'No se pudo generar el PDF.';
            exit;
        }
    }

    /**
     * ***************************************************************************
     * * PREPARA MOVIMIENTO DE KARDEX PARA RESPUESTA JSON.
     * ***************************************************************************
     */
    private function formatearMovimiento(array $fila): array
    {
        $entrada = (float) $fila['inv_kardex_cantidad_entrada'];
        $salida = (float) $fila['inv_kardex_cantidad_salida'];
        $cantidad = $entrada > 0 ? $entrada : ($salida > 0 ? -$salida : 0);

        return [
            'id' => (int) $fila['inv_kardex_id'],
            'bodega_id' => (int) $fila['inv_bodega_id'],
            'detalle' => $this->traducirTipoMovimiento((string) $fila['inv_kardex_tipo_movimiento']),
            'documento_tipo' => (string) $fila['inv_kardex_documento_tipo'],
            'documento_id' => (int) $fila['inv_kardex_documento_id'],
            'documento_numero' => (string) $fila['inv_kardex_documento_numero'],
            'cantidad' => $cantidad,
            'saldo' => (float) $fila['inv_kardex_saldo_cantidad'],
            'observacion' => (string) ($fila['inv_kardex_observacion'] ?? ''),
            'fecha' => (string) $fila['fecha'],
            'hora' => (string) $fila['hora'],
        ];
    }

    /**
     * ***************************************************************************
     * * CONVIERTE CODIGOS TECNICOS DE KARDEX EN ETIQUETAS DE USUARIO.
     * ***************************************************************************
     */
    private function traducirTipoMovimiento(string $tipo): string
    {
        return [
            'SALDO_INICIAL' => 'Ingreso',
            'VENTA' => 'Salida',
            'COMPRA' => 'Ingreso',
            'AJUSTE_IN' => 'Ajuste ingreso',
            'AJUSTE_OUT' => 'Ajuste salida',
            'TRANS_IN' => 'Transferencia ingreso',
            'TRANS_OUT' => 'Transferencia salida',
        ][$tipo] ?? $tipo;
    }

    /**
     * ***************************************************************************
     * * NORMALIZA CABECERA DE DOCUMENTO PARA EL GENERADOR PDF.
     * ***************************************************************************
     */
    private function normalizarCabeceraPdf(array $cabecera): array
    {
        return [
            'empresa_nombre' => $cabecera['sis_empresa_nombre_comercial'] ?: $cabecera['sis_empresa_razon_social'],
            'empresa_ruc' => $cabecera['sis_empresa_ruc'],
            'empresa_direccion' => $cabecera['sis_empresa_direccion'],
            'tipo_documento' => $cabecera['sis_tipo_documento_nombre'],
            'numero' => $cabecera['inv_movimientos_numero'],
            'fecha' => (string) $cabecera['inv_movimientos_fecha'],
            'hora' => (string) $cabecera['hora'],
            'referencia' => $cabecera['inv_movimientos_referencia'],
            'observacion' => $cabecera['inv_movimientos_observacion'],
            'responsable' => $cabecera['responsable'],
        ];
    }

    private function normalizarFecha(mixed $fecha): ?string
    {
        $valor = trim((string) $fecha);
        if ($valor === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            throw new \InvalidArgumentException('Fecha no valida.');
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

}
