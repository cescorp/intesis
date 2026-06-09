<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\ArchivoProductoModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\ProductoModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Throwable;

final class ArchivoProductoControlador
{
    use ControladorComun;

    private const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_BYTES = 7340032;

    public function __construct(
        private Sesion $sesion,
        private ProductoModelo $productoModelo,
        private ArchivoProductoModelo $archivoProductoModelo,
        private MenuModelo $menuModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * LISTA IMAGENES DE LA GALERIA DEL PRODUCTO.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/productos/ver');
        try {
            $producto = $this->obtenerProductoPermitido((int) ($_GET['producto_id'] ?? 0), $usuario);
            $imagenes = array_map(fn (array $archivo): array => $this->mapearArchivo($archivo), $this->archivoProductoModelo->listarPorProducto((int) $producto['sis_empresa_id'], (int) $producto['inv_producto_id']));
            $this->responderJson(true, 'REGISTROS_LISTADOS', 'Registros listados correctamente.', ['imagenes' => $imagenes]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('LISTAR GALERIA PRODUCTO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * SUBE UNA O VARIAS IMAGENES A LA GALERIA DEL PRODUCTO.
     * ***************************************************************************
     */
    public function subir(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/productos/editar');
        try {
            $producto = $this->obtenerProductoPermitido((int) ($_POST['producto_id'] ?? 0), $usuario);
            $archivos = $this->normalizarArchivos($_FILES['archivos'] ?? null);
            if (!$archivos) {
                throw new \InvalidArgumentException('Seleccione al menos una imagen.');
            }

            $subidos = [];
            $contador = 1;
            foreach ($archivos as $archivo) {
                $subidos[] = $this->guardarArchivoFisico($archivo, $producto, (int) $usuario['id'], $contador);
                $contador++;
            }

            $this->responderJson(true, 'CARGA_COMPLETA', 'Carga de ' . count($subidos) . ' archivos completa.', ['imagenes' => $subidos, 'subidos' => count($subidos)]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('SUBIR GALERIA PRODUCTO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * MUESTRA UNA IMAGEN PROTEGIDA DE PRODUCTO.
     * ***************************************************************************
     */
    public function ver(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/inventario/productos/ver');
        try {
            $archivo = $this->obtenerArchivoPermitido((int) ($_GET['archivo_id'] ?? 0), $usuario);
            $ruta = $this->configuracion->raiz() . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $archivo['sis_archivos_ubicacion']);
            if (!is_file($ruta)) {
                throw new \InvalidArgumentException('Imagen no encontrada.');
            }

            $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            $tipos = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            header('Content-Type: ' . ($tipos[$extension] ?? 'application/octet-stream'));
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            exit;
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('VER IMAGEN PRODUCTO', $excepcion);
            http_response_code(404);
            echo 'Imagen no encontrada.';
            exit;
        }
    }

    /**
     * ***************************************************************************
     * * MARCA UNA IMAGEN COMO PRINCIPAL.
     * ***************************************************************************
     */
    public function principal(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/productos/editar');
        try {
            $archivo = $this->obtenerArchivoPermitido((int) ($_POST['archivo_id'] ?? 0), $usuario);
            $this->archivoProductoModelo->marcarPrincipal((int) $archivo['sis_archivos_id'], (int) $usuario['id']);
            $this->responderJson(true, 'REGISTRO_ACTUALIZADO', 'Imagen principal actualizada.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('PRINCIPAL GALERIA PRODUCTO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * ELIMINA LOGICAMENTE UNA IMAGEN DEL PRODUCTO.
     * ***************************************************************************
     */
    public function eliminar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/inventario/productos/editar');
        try {
            $archivo = $this->obtenerArchivoPermitido((int) ($_POST['archivo_id'] ?? 0), $usuario);
            $this->archivoProductoModelo->eliminarLogico((int) $archivo['sis_archivos_id'], (int) $usuario['id']);
            $this->responderJson(true, 'REGISTRO_ELIMINADO', 'Imagen eliminada correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ELIMINAR GALERIA PRODUCTO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /**
     * ***************************************************************************
     * * GUARDA ARCHIVO FISICO Y REGISTRA SU METADATA.
     * ***************************************************************************
     */
    private function guardarArchivoFisico(array $archivo, array $producto, int $usuarioId, int $contador): array
    {
        if ((int) $archivo['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('No se pudo cargar una imagen.');
        }
        if ((int) $archivo['size'] > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Cada imagen debe pesar maximo 7MB.');
        }

        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES, true)) {
            throw new \InvalidArgumentException('Solo se permiten imagenes JPG, PNG o WEBP.');
        }

        $empresaId = (int) $producto['sis_empresa_id'];
        $productoId = (int) $producto['inv_producto_id'];
        $directorioRelativo = 'almacenamiento/archivos/productos/empresa_' . $empresaId;
        $directorio = $this->configuracion->raiz() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directorioRelativo);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        $nombre = $productoId . '_' . date('Ymd_His') . '_' . $contador . '.' . $extension;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;
        if (!move_uploaded_file((string) $archivo['tmp_name'], $destino)) {
            throw new \InvalidArgumentException('No se pudo guardar la imagen.');
        }

        $archivoId = $this->archivoProductoModelo->registrarImagen($empresaId, $productoId, $nombre, $directorioRelativo . '/' . $nombre, $usuarioId);
        $registro = $this->archivoProductoModelo->buscar($archivoId);

        return $registro ? $this->mapearArchivo($registro) : [];
    }

    /**
     * ***************************************************************************
     * * NORMALIZA ESTRUCTURA MULTIPLE DE ARCHIVOS SUBIDOS.
     * ***************************************************************************
     */
    private function normalizarArchivos(?array $entrada): array
    {
        if (!$entrada || !isset($entrada['name'])) {
            return [];
        }

        $archivos = [];
        foreach ((array) $entrada['name'] as $indice => $nombre) {
            $archivos[] = [
                'name' => $nombre,
                'type' => $entrada['type'][$indice] ?? '',
                'tmp_name' => $entrada['tmp_name'][$indice] ?? '',
                'error' => $entrada['error'][$indice] ?? UPLOAD_ERR_NO_FILE,
                'size' => $entrada['size'][$indice] ?? 0,
            ];
        }

        return $archivos;
    }

    /**
     * ***************************************************************************
     * * MAPEA ARCHIVO A FORMATO JSON DE GALERIA.
     * ***************************************************************************
     */
    private function mapearArchivo(array $archivo): array
    {
        $base = rtrim($this->configuracion->obtener('APP_URL', ''), '/');
        return [
            'id' => (int) $archivo['sis_archivos_id'],
            'archivo' => $archivo['sis_archivos_archivo'],
            'principal' => (bool) $archivo['sis_archivos_principal'],
            'url' => $base . '/inventario/productos/archivos/ver?archivo_id=' . (int) $archivo['sis_archivos_id'],
        ];
    }

    /**
     * ***************************************************************************
     * * OBTIENE PRODUCTO Y VALIDA ALCANCE.
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
     * * OBTIENE ARCHIVO Y VALIDA ALCANCE.
     * ***************************************************************************
     */
    private function obtenerArchivoPermitido(int $archivoId, array $usuario): array
    {
        $archivo = $archivoId > 0 ? $this->archivoProductoModelo->buscar($archivoId) : null;
        if (!$archivo || $archivo['sis_archivos_tabla'] !== 'INV_PRODUCTO') {
            throw new \InvalidArgumentException('Imagen no valida.');
        }
        if (!$this->esSuperusuario($usuario) && (int) $archivo['sis_empresa_id'] !== (int) $usuario['empresa_id']) {
            throw new \InvalidArgumentException('No puede acceder a imagenes de otra empresa.');
        }

        return $archivo;
    }

    /**
     * ***************************************************************************
     * * EXIGE SESION ACTIVA PARA VISTA DE IMAGEN. DEVUELVE 403 SI NO HAY SESION.
     * ***************************************************************************
     */
    private function exigirSesion(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            http_response_code(403);
            exit;
        }

        return $usuario;
    }

    /**
     * ***************************************************************************
     * * EXIGE SESION ACTIVA PARA JSON.
     * ***************************************************************************
     */
    private function exigirSesionJson(): array
    {
        $usuario = $this->sesion->usuario();
        if (!$usuario) {
            $this->responderJson(false, 'ERROR_SESION', 'Sesion no activa.');
        }

        return $usuario;
    }

    /**
     * ***************************************************************************
     * * VALIDA PERMISO PARA VISTA DE IMAGEN.
     * ***************************************************************************
     */
    private function exigirPermiso(string $url): void
    {
        $usuario = $this->exigirSesion();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            http_response_code(403);
            exit;
        }
    }

    /**
     * ***************************************************************************
     * * VALIDA PERMISO PARA JSON.
     * ***************************************************************************
     */
    private function exigirPermisoJson(string $url): void
    {
        $usuario = $this->exigirSesionJson();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->responderJson(false, 'ERROR_SIN_PERMISO', 'Su perfil no tiene permiso para esta accion.');
        }
    }

    /**
     * ***************************************************************************
     * * ENVIA RESPUESTA JSON ESTANDAR.
     * ***************************************************************************
     */
    private function responderJson(bool $ok, string $codigo, string $mensaje, array $data = []): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $respuesta = ['ok' => $ok, 'codigo' => $codigo, 'mensaje' => $mensaje, 'data' => $data];
        if (!$ok) {
            $respuesta['errores'] = [];
        }
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * ***************************************************************************
     * * REGISTRA ERRORES DEL CRUD ARCHIVOS PRODUCTO.
     * ***************************************************************************
     */
    private function registrarErrorCrud(string $accion, Throwable $excepcion): void
    {
        $this->registroErrores->escribirExcepcion($accion, $excepcion);
    }
}
