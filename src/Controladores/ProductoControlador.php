<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\CategoriaModelo;
use Intesis\Modelos\MarcaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Modelos\ProductoModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class ProductoControlador
{
    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private ProductoModelo $productoModelo,
        private CategoriaModelo $categoriaModelo,
        private MarcaModelo $marcaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA EL CRUD DE PRODUCTOS POR EMPRESA.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/productos/ver');
        $verTodas = $this->esSuperusuario($usuario);
        $empresaId = (int) $usuario['empresa_id'];

        $this->vista->renderizar('inventario/productos', [
            'titulo' => 'Productos',
            'usuario' => $usuario,
            'menus' => $this->menuModelo->listarMenusPorPerfil($empresaId, (int) $usuario['perfil_id']),
            'productos' => $this->productoModelo->listar($empresaId, $verTodas),
            'empresas' => $this->productoModelo->listarEmpresasActivas($verTodas, $empresaId),
            'categorias' => $this->categoriaModelo->listar($empresaId, $verTodas, true),
            'marcas' => $this->marcaModelo->listar($empresaId, $verTodas, true),
            'esSuperusuario' => $verTodas,
            'permisos' => $this->obtenerPermisos($usuario),
            'mensaje' => $this->sesion->consumirMensaje(),
            'mensajesSistema' => $this->mensajeSistemaModelo->listarPorCodigos(['USUARIO_DATOS_OBLIGATORIOS']),
        ]);
    }

    /**
     * ***************************************************************************
     * * CREA UN PRODUCTO.
     * ***************************************************************************
     */
    public function crear(): void
    {
        $this->guardar(false);
    }

    /**
     * ***************************************************************************
     * * EDITA UN PRODUCTO.
     * ***************************************************************************
     */
    public function editar(): void
    {
        $this->guardar(true);
    }

    /**
     * ***************************************************************************
     * * ACTIVA UN PRODUCTO.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $this->cambiarEstado('/inventario/productos/activar', 'ACTIVO', 'Producto activado');
    }

    /**
     * ***************************************************************************
     * * INACTIVA UN PRODUCTO.
     * ***************************************************************************
     */
    public function inactivar(): void
    {
        $this->cambiarEstado('/inventario/productos/inactivar', 'INACTIVO', 'Producto inactivado');
    }

    /**
     * ***************************************************************************
     * * GUARDA CREACION O EDICION DE PRODUCTO.
     * ***************************************************************************
     */
    private function guardar(bool $editar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($editar ? '/inventario/productos/editar' : '/inventario/productos/crear');
        try {
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $producto = $editar ? $this->obtenerProductoPermitido($productoId, $usuario) : null;
            $datos = $this->normalizarDatos($usuario);
            $this->validarDatos($datos, $editar ? $productoId : null);
            $editar
                ? $this->productoModelo->actualizar((int) $producto['inv_producto_id'], $datos, (int) $usuario['id'])
                : $this->productoModelo->crear($datos, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $editar ? 'Producto actualizado' : 'Producto creado', 'Los cambios fueron guardados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($editar ? 'EDITAR PRODUCTO' : 'CREAR PRODUCTO', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/productos');
    }

    /**
     * ***************************************************************************
     * * CAMBIA ESTADO ACTIVO O INACTIVO DE PRODUCTO.
     * ***************************************************************************
     */
    private function cambiarEstado(string $permiso, string $estado, string $titulo): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso($permiso);
        try {
            $producto = $this->obtenerProductoPermitido((int) ($_POST['producto_id'] ?? 0), $usuario);
            $this->productoModelo->cambiarEstado((int) $producto['inv_producto_id'], $estado, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', $titulo, 'El cambio fue aplicado correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CAMBIAR ESTADO PRODUCTO', $excepcion);
            $this->guardarMensajeError($excepcion);
        }
        $this->redirigir('/inventario/productos');
    }

    /**
     * ***************************************************************************
     * * NORMALIZA DATOS DE FORMULARIO DE PRODUCTO.
     * ***************************************************************************
     */
    private function normalizarDatos(array $usuario): array
    {
        return [
            'empresa_id' => $this->esSuperusuario($usuario) ? (int) ($_POST['empresa_id'] ?? 0) : (int) $usuario['empresa_id'],
            'categoria_id' => (int) ($_POST['categoria_id'] ?? 0),
            'marca_id' => (int) ($_POST['marca_id'] ?? 0),
            'codigo_principal' => strtoupper(trim((string) ($_POST['codigo_principal'] ?? ''))),
            'codigo_auxiliar' => strtoupper(trim((string) ($_POST['codigo_auxiliar'] ?? ''))),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'lleva_iva' => isset($_POST['lleva_iva']) ? 1 : 0,
            'costo_ultimo' => $this->normalizarNumero($_POST['costo_ultimo'] ?? 0),
            'stock_minimo' => $this->normalizarNumero($_POST['stock_minimo'] ?? 0),
            'stock_maximo' => $this->normalizarNumero($_POST['stock_maximo'] ?? 0),
        ];
    }

    /**
     * ***************************************************************************
     * * NORMALIZA VALORES NUMERICOS DECIMALES.
     * ***************************************************************************
     */
    private function normalizarNumero(mixed $valor): string
    {
        $numero = str_replace(',', '.', trim((string) $valor));
        return $numero === '' ? '0' : $numero;
    }

    /**
     * ***************************************************************************
     * * VALIDA REGLAS DE NEGOCIO DE PRODUCTO.
     * ***************************************************************************
     */
    private function validarDatos(array $datos, ?int $productoId): void
    {
        if ($datos['empresa_id'] <= 0 || $datos['codigo_principal'] === '' || $datos['nombre'] === '' || $datos['categoria_id'] <= 0 || $datos['marca_id'] <= 0) {
            throw new \InvalidArgumentException('Empresa, codigo, nombre, categoria y marca son obligatorios.');
        }
        foreach (['costo_ultimo', 'stock_minimo', 'stock_maximo'] as $campo) {
            if (!is_numeric($datos[$campo]) || (float) $datos[$campo] < 0) {
                throw new \InvalidArgumentException('Costos y stocks deben ser numericos mayores o iguales a cero.');
            }
        }
        if ($this->productoModelo->existeCodigo((int) $datos['empresa_id'], $datos['codigo_principal'], $productoId)) {
            throw new \InvalidArgumentException('Ya existe un producto con ese codigo en la empresa.');
        }
        if (!$this->categoriaModelo->perteneceEmpresa((int) $datos['categoria_id'], (int) $datos['empresa_id'])) {
            throw new \InvalidArgumentException('La categoria no pertenece a la empresa seleccionada.');
        }
        if (!$this->marcaModelo->perteneceEmpresa((int) $datos['marca_id'], (int) $datos['empresa_id'])) {
            throw new \InvalidArgumentException('La marca no pertenece a la empresa seleccionada.');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE PRODUCTO Y VALIDA ALCANCE DE EMPRESA.
     * ***************************************************************************
     */
    private function obtenerProductoPermitido(int $productoId, array $usuario): array
    {
        $producto = $productoId > 0 ? $this->productoModelo->buscar($productoId) : null;
        if (!$producto) {
            throw new \InvalidArgumentException('Producto no valido.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $producto['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede administrar productos de otra empresa.');
        }

        return $producto;
    }

    /**
     * ***************************************************************************
     * * OBTIENE PERMISOS DE BOTONES DEL CRUD.
     * ***************************************************************************
     */
    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId = (int) $usuario['perfil_id'];

        return [
            'crear' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/productos/crear'),
            'editar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/productos/editar'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/productos/activar'),
            'inactivar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/productos/inactivar'),
            'crear_categoria' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/categorias/crear'),
            'crear_marca' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/inventario/marcas/crear'),
        ];
    }

    /**
     * ***************************************************************************
     * * IDENTIFICA PERFIL SUPERUSUARIO.
     * ***************************************************************************
     */
    private function esSuperusuario(array $usuario): bool
    {
        return strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
    }

    /**
     * ***************************************************************************
     * * EXIGE SESION ACTIVA.
     * ***************************************************************************
     */
    private function exigirSesion(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->redirigir('/login');
        }

        return $usuario;
    }

    /**
     * ***************************************************************************
     * * VALIDA PERMISO BACKEND PARA ACCION.
     * ***************************************************************************
     */
    private function exigirPermiso(string $url): void
    {
        $usuario = $this->exigirSesion();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->sesion->guardarMensaje('error', 'Acceso restringido', 'Su perfil no tiene permiso para esta accion.');
            $this->redirigir('/dashboard');
        }
    }

    /**
     * ***************************************************************************
     * * REDIRIGE A RUTA LIMPIA.
     * ***************************************************************************
     */
    private function redirigir(string $ruta): never
    {
        header('Location: ' . rtrim($this->configuracion->obtener('APP_URL', ''), '/') . $ruta);
        exit;
    }

    /**
     * ***************************************************************************
     * * REGISTRA ERRORES DEL CRUD PRODUCTO.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribir($accion . ': ' . $excepcion->getMessage());
    }

    /**
     * ***************************************************************************
     * * GUARDA MENSAJE DE ERROR CONTROLADO.
     * ***************************************************************************
     */
    private function guardarMensajeError(Throwable $excepcion): void
    {
        $this->sesion->guardarMensaje('error', 'No se pudo guardar', $excepcion->getMessage());
    }
}
