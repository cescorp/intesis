<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\MovimientoInternoModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class MovimientoInternoControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private MovimientoInternoModelo $movimientoInternoModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA MOVIMIENTOS INTERNOS Y TRANSFERENCIAS PENDIENTES.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/movimientos/ver');
        $empresaId = (int) $usuario['empresa_id'];
        $superusuario = $this->esSuperusuario($usuario);

        $this->vista->renderizar('inventario/movimientos', [
            'titulo' => 'Movimientos internos',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'movimientos' => $this->movimientoInternoModelo->listar($empresaId),
            'transferenciasPendientes' => $this->movimientoInternoModelo->listarTransferenciasPendientes($empresaId, (int) $usuario['id'], $superusuario),
            'bodegas' => $this->movimientoInternoModelo->listarBodegasPermitidas($empresaId, (int) $usuario['id'], $superusuario),
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos(['USUARIO_DATOS_OBLIGATORIOS']),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA MOVIMIENTO INTERNO DESDE FORMULARIO.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $usuario = $this->exigirSesion();
        try {
            $datos = $this->normalizarMovimiento($usuario);
            $this->exigirPermisoCrearMovimiento($datos['tipo'], $usuario);
            $bodegasPermitidas = $this->movimientoInternoModelo->listarBodegasPermitidas((int) $usuario['empresa_id'], (int) $usuario['id'], $this->esSuperusuario($usuario));
            $lineas = $this->normalizarLineas($datos['tipo'], $bodegasPermitidas);
            $datos['autoaprobado'] = $this->resolverAutoaprobado($lineas, $bodegasPermitidas);
            $this->movimientoInternoModelo->crear($datos, $lineas, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Movimiento guardado', 'El movimiento fue registrado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribir('CREAR MOVIMIENTO INTERNO: ' . $excepcion->getMessage());
            $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
        }
        $this->redirigir('/inventario/movimientos');
    }

    /**
     * ***************************************************************************
     * * EDITA OBSERVACION DE MOVIMIENTO PENDIENTE O EN TRANSITO.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/movimientos/editar');
        try {
            $this->movimientoInternoModelo->actualizarObservacion((int) $usuario['empresa_id'], (int) ($_POST['movimiento_id'] ?? 0), trim((string) ($_POST['detalle'] ?? '')), (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Movimiento actualizado', 'La observacion fue actualizada.');
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribir('EDITAR MOVIMIENTO INTERNO: ' . $excepcion->getMessage());
            $this->sesion->guardarMensaje('error', 'No se pudo editar', $excepcion->getMessage());
        }
        $this->redirigir('/inventario/movimientos');
    }

    public function aprobar(): void
    {
        $this->accionEstado('/inventario/movimientos/aprobar', 'aprobar', 'Movimiento aprobado');
    }

    public function recibir(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/movimientos/recibir');
        try {
            $movimientoId = (int) ($_POST['movimiento_id'] ?? 0);
            if (!$this->movimientoInternoModelo->usuarioPuedeRecibir((int) $usuario['empresa_id'], $movimientoId, (int) $usuario['id'], $this->esSuperusuario($usuario))) {
                throw new \InvalidArgumentException('No tiene acceso a la bodega destino.');
            }
            $this->movimientoInternoModelo->recibir((int) $usuario['empresa_id'], $movimientoId, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Transferencia recibida', 'La accion fue aplicada correctamente.');
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribir('RECIBIR MOVIMIENTO: ' . $excepcion->getMessage());
            $this->sesion->guardarMensaje('error', 'No se pudo procesar', $excepcion->getMessage());
        }
        $this->redirigir('/inventario/movimientos');
    }

    public function anular(): void
    {
        $this->accionEstado('/inventario/movimientos/anular', 'anular', 'Movimiento anulado');
    }

    /**
     * ***************************************************************************
     * * BUSCA PRODUCTOS PARA EL MODAL DE SELECCION.
     * ***************************************************************************
     */
    public function productos(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/movimientos/productos');
        $termino = trim((string) ($_GET['q'] ?? ''));
        $codigo = trim((string) ($_GET['codigo'] ?? ''));
        $productos = $codigo !== ''
            ? array_filter([$this->movimientoInternoModelo->buscarProductoPorCodigo((int) $usuario['empresa_id'], $codigo)])
            : $this->movimientoInternoModelo->buscarProductos((int) $usuario['empresa_id'], $termino);
        $this->responderJson(true, 'PRODUCTOS_OK', 'Productos listados.', ['productos' => array_values($productos)]);
    }

    private function accionEstado(string $permiso, string $accion, string $titulo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $movimientoId = (int) ($_POST['movimiento_id'] ?? 0);
            $this->movimientoInternoModelo->{$accion}((int) $usuario['empresa_id'], $movimientoId, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, 'La accion fue aplicada correctamente.');
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribir(strtoupper($accion) . ' MOVIMIENTO: ' . $excepcion->getMessage());
            $this->sesion->guardarMensaje('error', 'No se pudo procesar', $excepcion->getMessage());
        }
        $this->redirigir('/inventario/movimientos');
    }

    private function normalizarMovimiento(array $usuario): array
    {
        $tipo = (string) ($_POST['tipo'] ?? '');
        if (!in_array($tipo, ['AJUSTE', 'AJUSTE_IN', 'AJUSTE_OUT', 'TRANSFERENCIA'], true)) {
            throw new \InvalidArgumentException('Tipo de movimiento no valido.');
        }
        if ($tipo === 'AJUSTE_IN' || $tipo === 'AJUSTE_OUT') {
            $tipo = 'AJUSTE';
        }
        $detalle = trim((string) ($_POST['detalle'] ?? ''));
        if ($detalle === '') {
            throw new \InvalidArgumentException('El detalle es obligatorio.');
        }

        return [
            'empresa_id' => (int) $usuario['empresa_id'],
            'tipo' => $tipo,
            'fecha' => $_POST['fecha'] ?: date('Y-m-d'),
            'detalle' => $detalle,
            'autoaprobado' => false,
        ];
    }

    /**
     * ***************************************************************************
     * * NORMALIZA LINEAS VALIDANDO BODEGAS Y PERMISOS POR ACCION.
     * ***************************************************************************
     */
    private function normalizarLineas(string $tipo, array $bodegasPermitidas): array
    {
        $idsPermitidos = array_map(fn (array $bodega): int => (int) $bodega['inv_bodega_id'], $bodegasPermitidas);
        $lineas = json_decode((string) ($_POST['lineas_json'] ?? '[]'), true);
        if (!is_array($lineas) || !$lineas) {
            throw new \InvalidArgumentException('Agregue al menos un producto.');
        }
        $normalizadas = [];
        foreach ($lineas as $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $origen = (int) ($linea['origen_id'] ?? 0);
            $destino = (int) ($linea['destino_id'] ?? 0);
            $accion = strtoupper(trim((string) ($linea['accion'] ?? '')));
            if ((int) ($linea['producto_id'] ?? 0) <= 0 || $cantidad <= 0) {
                throw new \InvalidArgumentException('Producto y cantidad son obligatorios.');
            }
            if ($tipo === 'TRANSFERENCIA' && ($origen <= 0 || $destino <= 0 || $origen === $destino)) {
                throw new \InvalidArgumentException('Transferencia requiere origen y destino diferentes.');
            }
            if ($origen > 0 && !in_array($origen, $idsPermitidos, true)) {
                throw new \InvalidArgumentException('No tiene permiso para usar la bodega origen.');
            }
            if ($destino > 0 && !in_array($destino, $idsPermitidos, true)) {
                throw new \InvalidArgumentException('No tiene permiso para usar la bodega destino.');
            }
            if ($tipo === 'AJUSTE') {
                if (!in_array($accion, ['INGRESO', 'EGRESO'], true)) {
                    throw new \InvalidArgumentException('Seleccione ingreso o egreso por linea.');
                }
                if ($accion === 'INGRESO') {
                    $this->exigirPermiso('/inventario/movimientos/ajuste-ingreso');
                }
                if ($accion === 'EGRESO') {
                    $this->exigirPermiso('/inventario/movimientos/ajuste-egreso');
                }
                if ($accion === 'INGRESO' && $destino <= 0) {
                    throw new \InvalidArgumentException('Ingreso requiere bodega destino.');
                }
                if ($accion === 'EGRESO' && $origen <= 0) {
                    throw new \InvalidArgumentException('Egreso requiere bodega origen.');
                }
            }
            $normalizadas[] = [
                'producto_id' => (int) $linea['producto_id'],
                'origen_id' => $origen,
                'destino_id' => $destino,
                'accion' => $tipo === 'TRANSFERENCIA' ? 'TRANSFERENCIA' : $accion,
                'cantidad' => $cantidad,
                'costo' => (float) ($linea['costo'] ?? 0),
                'pvp' => (float) ($linea['pvp'] ?? 0),
                'observacion' => trim((string) ($linea['observacion'] ?? '')),
            ];
        }

        return $normalizadas;
    }

    /**
     * ***************************************************************************
     * * RESUELVE AUTOAPROBACION EXIGIENDO QUE TODAS LAS BODEGAS APLIQUEN.
     * ***************************************************************************
     */
    private function resolverAutoaprobado(array $lineas, array $bodegasPermitidas): bool
    {
        $autoaprobado = [];
        foreach ($bodegasPermitidas as $bodega) {
            $autoaprobado[(int) $bodega['inv_bodega_id']] = !empty($bodega['inv_bodega_autoaprobado']);
        }
        foreach ($lineas as $linea) {
            $bodegaId = $linea['origen_id'] ?: $linea['destino_id'];
            if (empty($autoaprobado[(int) $bodegaId])) {
                return false;
            }
        }

        return true;
    }

    /**
     * ***************************************************************************
     * * VALIDA PERMISO GENERAL DE CREACION SEGUN TIPO DE MOVIMIENTO.
     * ***************************************************************************
     */
    private function exigirPermisoCrearMovimiento(string $tipo, array $usuario): void
    {
        if ($tipo === 'TRANSFERENCIA') {
            $this->exigirPermiso('/inventario/movimientos/transferencia');
            return;
        }
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];
        if (
            !$this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/movimientos/ajuste-ingreso')
            && !$this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/movimientos/ajuste-egreso')
        ) {
            $this->sesion->guardarMensaje('error', 'Acceso restringido', 'Su perfil no tiene permiso para crear ajustes.');
            $this->redirigir('/inventario/movimientos');
        }
    }

    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];
        $urls = [
            'ajusteIngreso' => '/inventario/movimientos/ajuste-ingreso',
            'ajusteEgreso' => '/inventario/movimientos/ajuste-egreso',
            'transferencia' => '/inventario/movimientos/transferencia',
            'editar' => '/inventario/movimientos/editar',
            'aprobar' => '/inventario/movimientos/aprobar',
            'recibir' => '/inventario/movimientos/recibir',
            'anular' => '/inventario/movimientos/anular',
        ];
        $permisos = [];
        foreach ($urls as $clave => $url) {
            $permisos[$clave] = $this->menuModelo->tienePermiso($empresaId, $perfilId, $url);
        }

        return $permisos;
    }

    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
    }

    private function exigirSesion(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->redirigir('/login');
        }

        return $usuario;
    }

    private function exigirSesionJson(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->responderJson(false, 'ERROR_SESION', 'Sesion no activa.');
        }

        return $usuario;
    }

    private function exigirPermiso(string $url): void
    {
        $usuario = $this->exigirSesion();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->sesion->guardarMensaje('error', 'Acceso restringido', 'Su perfil no tiene permiso para esta accion.');
            $this->redirigir('/dashboard');
        }
    }

    private function exigirPermisoJson(string $url): void
    {
        $usuario = $this->exigirSesionJson();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->responderJson(false, 'ERROR_SIN_PERMISO', 'Su perfil no tiene permiso para esta accion.');
        }
    }

    private function responderJson(bool $ok, string $codigo, string $mensaje, array $data = []): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'codigo' => $codigo, 'mensaje' => $mensaje, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function redirigir(string $ruta): never
    {
        header('Location: ' . rtrim($this->configuracion->obtener('APP_URL', ''), '/') . $ruta);
        exit;
    }
}
