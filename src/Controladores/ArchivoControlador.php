<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\GestorArchivosModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Intesis\Servicios\AlmacenArchivosServicio;
use Throwable;

/**
 * Modulo Archivos: carpetas por usuario, versiones y compartir.
 *
 * Solo el dueño sube, crea carpetas, renombra, elimina y comparte. Con quien se comparte
 * puede ver y descargar. La visibilidad la resuelve GestorArchivosModelo.
 * Carpeta 0 = raiz "Mis archivos" del usuario.
 */
final class ArchivoControlador
{
    use ControladorComun;

    /** Limite por archivo del modulo (el servicio nunca acepta mas de 80 MB). */
    private const MAX_MB = 80;

    public function __construct(
        private Vista                   $vista,
        private Sesion                  $sesion,
        private GestorArchivosModelo    $gestorModelo,
        private AlmacenArchivosServicio $almacenArchivos,
        private MenuModelo              $menuModelo,
        private Configuracion           $configuracion,
        private RegistroErrores         $registroErrores
    ) {
    }

    public function index(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/archivos');
        $eId = (int) $usuario['empresa_id'];
        $pId = (int) $usuario['perfil_id'];

        $this->vista->renderizar('archivos/index', [
            'titulo'   => 'Archivos',
            'usuario'  => $usuario,
            'menus'    => $this->menuModelo->listarMenusPorPerfil($eId, $pId),
            'permisos' => [
                'subir'     => $this->menuModelo->tienePermiso($eId, $pId, '/archivos/subir'),
                'carpetas'  => $this->menuModelo->tienePermiso($eId, $pId, '/archivos/carpetas'),
                'compartir' => $this->menuModelo->tienePermiso($eId, $pId, '/archivos/compartir'),
                'eliminar'  => $this->menuModelo->tienePermiso($eId, $pId, '/archivos/eliminar'),
            ],
            'maxMb'    => self::MAX_MB,
        ]);
    }

    // =========================================================================
    // CONSULTA
    // =========================================================================

    public function arbol(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos');
        $this->responderJson(true, 'OK', '', [
            'carpetas' => $this->gestorModelo->carpetasVisibles((int) $usuario['empresa_id'], (int) $usuario['id']),
        ]);
    }

    /** Archivos de una carpeta; con ?compartidos=1 los compartidos directamente con el usuario. */
    public function contenido(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos');
        try {
            $empresaId = (int) $usuario['empresa_id'];
            $usuarioId = (int) $usuario['id'];
            if (!empty($_GET['compartidos'])) {
                $archivos = $this->gestorModelo->listarArchivosCompartidosConmigo($empresaId, $usuarioId);
            } else {
                $carpetaId = (int) ($_GET['carpeta_id'] ?? 0);
                if ($carpetaId > 0) {
                    $this->obtenerCarpetaVisible($carpetaId, $usuario);
                }
                $archivos = $this->gestorModelo->listarArchivos($empresaId, $carpetaId, $usuarioId);
            }
            $this->responderJson(true, 'OK', '', ['archivos' => array_map(fn ($a) => $this->paraVista($a, $usuarioId), $archivos)]);
        } catch (Throwable $excepcion) {
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    /** Metadatos, versiones, con quien esta compartido y accesos. */
    public function info(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos');
        try {
            $archivo = $this->obtenerArchivoVisible((int) ($_GET['archivo_id'] ?? 0), $usuario);
            $grupoId = (int) $archivo['grupo_id'];
            $this->responderJson(true, 'OK', '', [
                'versiones'  => $this->gestorModelo->listarVersiones($grupoId),
                'compartido' => $this->gestorModelo->compartidosDeArchivo($grupoId, (int) $archivo['sis_archivos_id_padre']),
                'accesos'    => $this->gestorModelo->listarAccesos($grupoId),
            ]);
        } catch (Throwable $excepcion) {
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function ver(): void
    {
        $this->entregar(false);
    }

    public function descargar(): void
    {
        $this->entregar(true);
    }

    /** Muestra o descarga una version y deja constancia de quien lo hizo y cuando. */
    private function entregar(bool $descargar): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/archivos');
        try {
            $archivo = $this->obtenerArchivoVisible((int) ($_GET['archivo_id'] ?? 0), $usuario);
            $this->gestorModelo->registrarAcceso((int) $archivo['sis_archivos_id'], (int) $usuario['id'], $descargar ? 'DESCARGAR' : 'ABRIR');
            $this->almacenArchivos->servir($archivo, $descargar);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud($descargar ? 'DESCARGAR ARCHIVO' : 'VER ARCHIVO', $excepcion);
            http_response_code(404);
            echo 'Archivo no encontrado.';
            exit;
        }
    }

    // =========================================================================
    // CARPETAS
    // =========================================================================

    public function crearCarpeta(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/carpetas');
        try {
            $padreId = (int) ($_POST['padre_id'] ?? 0);
            if ($padreId > 0) {
                $this->obtenerCarpetaPropia($padreId, $usuario);
            }
            $nombre = $this->validarNombre($_POST['nombre'] ?? '');
            $padre  = $padreId > 0 ? $padreId : null;
            if ($this->gestorModelo->existeNombreCarpeta((int) $usuario['empresa_id'], (int) $usuario['id'], $padre, $nombre)) {
                throw new \InvalidArgumentException("Ya existe una carpeta \"$nombre\" en esta ubicación.");
            }
            $id = $this->gestorModelo->crearCarpeta((int) $usuario['empresa_id'], $padre, $nombre, (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Carpeta creada.', ['carpeta_id' => $id]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('CREAR CARPETA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function renombrarCarpeta(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/carpetas');
        try {
            $carpeta = $this->obtenerCarpetaPropia((int) ($_POST['carpeta_id'] ?? 0), $usuario);
            $nombre  = $this->validarNombre($_POST['nombre'] ?? '');
            $padre   = $carpeta['sis_carpeta_padre_id'] !== null ? (int) $carpeta['sis_carpeta_padre_id'] : null;
            if ($this->gestorModelo->existeNombreCarpeta((int) $usuario['empresa_id'], (int) $usuario['id'], $padre, $nombre, (int) $carpeta['sis_carpeta_id'])) {
                throw new \InvalidArgumentException("Ya existe una carpeta \"$nombre\" en esta ubicación.");
            }
            $this->gestorModelo->renombrarCarpeta((int) $carpeta['sis_carpeta_id'], $nombre, (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Carpeta renombrada.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('RENOMBRAR CARPETA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function eliminarCarpeta(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/eliminar');
        try {
            $carpeta = $this->obtenerCarpetaPropia((int) ($_POST['carpeta_id'] ?? 0), $usuario);
            // Se exige vaciarla primero para no esconder de golpe archivos que otros usan
            if ($this->gestorModelo->carpetaTieneContenido((int) $carpeta['sis_carpeta_id'])) {
                throw new \InvalidArgumentException('La carpeta tiene archivos o subcarpetas. Vacíela antes de eliminarla.');
            }
            $this->gestorModelo->eliminarCarpeta((int) $carpeta['sis_carpeta_id'], (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Carpeta eliminada.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ELIMINAR CARPETA', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    // =========================================================================
    // ARCHIVOS
    // =========================================================================

    /** Un archivo por peticion. Mismo nombre y extension en la carpeta = version nueva. */
    public function subir(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/subir');
        try {
            $empresaId = (int) $usuario['empresa_id'];
            $usuarioId = (int) $usuario['id'];
            $carpetaId = (int) ($_POST['carpeta_id'] ?? 0);
            if ($carpetaId > 0) {
                $this->obtenerCarpetaPropia($carpetaId, $usuario);
            }
            $archivo = $_FILES['archivo'] ?? [];
            $anterior = $this->gestorModelo->buscarParaNuevaVersion(
                $empresaId, $carpetaId, $usuarioId,
                AlmacenArchivosServicio::nombreVisible((string) ($archivo['name'] ?? '')),
                AlmacenArchivosServicio::extensionOriginal((string) ($archivo['name'] ?? ''))
            );
            $guardado = $this->almacenArchivos->guardar(
                $archivo, $empresaId, GestorArchivosModelo::TABLA, $carpetaId, $usuarioId,
                AlmacenArchivosServicio::CUALQUIER_TIPO, self::MAX_MB,
                $anterior ? (int) $anterior['ultima_version'] + 1 : 1,
                $anterior ? (int) $anterior['grupo_id'] : null
            );
            $this->responderJson(true, 'OK', $anterior ? 'Se guardó como versión nueva.' : 'Archivo subido.', ['archivo' => $guardado]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('SUBIR ARCHIVO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function renombrar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/subir');
        try {
            $archivo = $this->obtenerArchivoPropio((int) ($_POST['archivo_id'] ?? 0), $usuario);
            $nombre  = $this->validarNombre($_POST['nombre'] ?? '');
            $this->gestorModelo->renombrarArchivo((int) $archivo['grupo_id'], $nombre, (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Nombre actualizado.', ['nombre' => $nombre]);
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('RENOMBRAR ARCHIVO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function eliminar(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/eliminar');
        try {
            $archivo = $this->obtenerArchivoPropio((int) ($_POST['archivo_id'] ?? 0), $usuario);
            $this->gestorModelo->eliminarArchivo((int) $archivo['grupo_id'], (int) $usuario['id']);
            $this->responderJson(true, 'OK', 'Archivo eliminado.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ELIMINAR ARCHIVO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    // =========================================================================
    // COMPARTIR
    // =========================================================================

    /** Usuarios de la empresa con la marca de si ya tienen compartido el objeto. */
    public function compartidos(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/compartir');
        try {
            [$tipo, $objetoId] = $this->objetoPropio((string) ($_GET['tipo'] ?? ''), (int) ($_GET['id'] ?? 0), $usuario);
            $this->responderJson(true, 'OK', '', [
                'usuarios' => $this->gestorModelo->usuariosParaCompartir((int) $usuario['empresa_id'], (int) $usuario['id'], $tipo, $objetoId),
            ]);
        } catch (Throwable $excepcion) {
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    public function compartir(): void
    {
        $usuario = $this->exigirSesionJson();
        $this->exigirPermisoJson('/archivos/compartir');
        try {
            [$tipo, $objetoId] = $this->objetoPropio((string) ($_POST['tipo'] ?? ''), (int) ($_POST['id'] ?? 0), $usuario);
            // Solo usuarios de la propia empresa: se descarta cualquier id que no este en la lista valida
            $validos = array_map('intval', array_column(
                $this->gestorModelo->usuariosParaCompartir((int) $usuario['empresa_id'], (int) $usuario['id'], $tipo, $objetoId),
                'sis_usuarios_id'
            ));
            $elegidos = array_values(array_intersect(array_map('intval', (array) ($_POST['usuarios'] ?? [])), $validos));
            $this->gestorModelo->sincronizarCompartidos((int) $usuario['empresa_id'], $tipo, $objetoId, $elegidos, (int) $usuario['id']);
            $this->responderJson(true, 'OK', $elegidos ? 'Compartido actualizado.' : 'Se dejó de compartir.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('COMPARTIR ARCHIVO', $excepcion);
            $this->responderJson(false, 'ERROR_VALIDACION', $excepcion->getMessage());
        }
    }

    // =========================================================================
    // VALIDACIONES DE ACCESO
    // =========================================================================

    private function obtenerCarpetaVisible(int $carpetaId, array $usuario): array
    {
        $carpeta = $this->gestorModelo->buscarCarpeta($carpetaId, (int) $usuario['empresa_id']);
        if (!$carpeta || !$this->gestorModelo->carpetaVisible($carpetaId, (int) $usuario['id'])) {
            throw new \InvalidArgumentException('La carpeta no existe o no tiene acceso a ella.');
        }
        return $carpeta;
    }

    private function obtenerCarpetaPropia(int $carpetaId, array $usuario): array
    {
        $carpeta = $this->gestorModelo->buscarCarpeta($carpetaId, (int) $usuario['empresa_id']);
        if (!$carpeta || (int) $carpeta['usuario_crea'] !== (int) $usuario['id']) {
            throw new \InvalidArgumentException('Solo el dueño de la carpeta puede modificarla.');
        }
        return $carpeta;
    }

    private function obtenerArchivoVisible(int $archivoId, array $usuario): array
    {
        $archivo = $this->gestorModelo->buscarArchivo($archivoId, (int) $usuario['empresa_id']);
        $usuarioId = (int) $usuario['id'];
        $visible = $archivo && (
            (int) $archivo['usuario_crea'] === $usuarioId
            || $this->gestorModelo->compartidoCon('ARCHIVO', (int) $archivo['grupo_id'], $usuarioId)
            || ((int) $archivo['sis_archivos_id_padre'] > 0 && $this->gestorModelo->carpetaVisible((int) $archivo['sis_archivos_id_padre'], $usuarioId))
        );
        if (!$visible) {
            throw new \InvalidArgumentException('El archivo no existe o no tiene acceso a él.');
        }
        return $archivo;
    }

    private function obtenerArchivoPropio(int $archivoId, array $usuario): array
    {
        $archivo = $this->gestorModelo->buscarArchivo($archivoId, (int) $usuario['empresa_id']);
        if (!$archivo || (int) $archivo['usuario_crea'] !== (int) $usuario['id']) {
            throw new \InvalidArgumentException('Solo el dueño del archivo puede modificarlo.');
        }
        return $archivo;
    }

    /** Valida el objeto a compartir; para archivos devuelve el id del grupo (todas las versiones). */
    private function objetoPropio(string $tipo, int $id, array $usuario): array
    {
        if ($tipo === 'CARPETA') {
            return ['CARPETA', (int) $this->obtenerCarpetaPropia($id, $usuario)['sis_carpeta_id']];
        }
        if ($tipo === 'ARCHIVO') {
            return ['ARCHIVO', (int) $this->obtenerArchivoPropio($id, $usuario)['grupo_id']];
        }
        throw new \InvalidArgumentException('Tipo de objeto no válido.');
    }

    private function validarNombre(mixed $valor): string
    {
        $nombre = mb_substr(trim((string) $valor), 0, 150);
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }
        if (preg_match('#[\\\\/:*?"<>|]#', $nombre)) {
            throw new \InvalidArgumentException('El nombre no puede contener \\ / : * ? " < > |');
        }
        return $nombre;
    }

    private function paraVista(array $a, int $usuarioId): array
    {
        $a['propio'] = (int) $a['dueno_id'] === $usuarioId;
        $a['previsualizable'] = AlmacenArchivosServicio::esPrevisualizable($a['sis_archivos_mime'] ?? null);
        return $a;
    }
}
