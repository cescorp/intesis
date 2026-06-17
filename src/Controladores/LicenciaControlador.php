<?php

declare(strict_types=1);

namespace Intesis\Controladores;

use Intesis\Modelos\LicenciaModelo;
use Intesis\Modelos\MenuModelo;
use Intesis\Modelos\MensajeSistemaModelo;
use Intesis\Nucleo\Configuracion;
use Intesis\Nucleo\ControladorComun;
use Intesis\Nucleo\RegistroErrores;
use Intesis\Nucleo\Sesion;
use Intesis\Nucleo\Vista;
use Throwable;

final class LicenciaControlador
{
    use ControladorComun;

    public function __construct(
        private Vista $vista,
        private Sesion $sesion,
        private LicenciaModelo $licenciaModelo,
        private MenuModelo $menuModelo,
        private MensajeSistemaModelo $mensajeSistemaModelo,
        private Configuracion $configuracion,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * MUESTRA LA PÁGINA DE LICENCIA CON LAS DOS PESTAÑAS.
     * ***************************************************************************
     */
    public function listar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/configuracion/licencia/ver');
        $esSuperusuario = $this->esSuperusuario($usuario);
        $empresaId = $esSuperusuario ? null : (int) $usuario['empresa_id'];

        $this->vista->renderizar('sistema/licencia', [
            'titulo'         => 'Licencia',
            'usuario'        => $usuario,
            'menus'          => $this->menuModelo->listarMenusPorPerfil((int) $usuario['empresa_id'], (int) $usuario['perfil_id']),
            'licencias'      => $this->licenciaModelo->listar($empresaId),
            'modulos'        => $this->licenciaModelo->listarModulos(),
            'empresas'       => $this->licenciaModelo->listarEmpresasActivas(),
            'esSuperusuario' => $esSuperusuario,
            'permisos'       => $this->obtenerPermisos($usuario),
            'mensaje'        => $this->sesion->consumirMensaje(),
        ]);
    }

    /**
     * ***************************************************************************
     * * PROCESA LA SUBIDA DEL ARCHIVO .JSON DE LICENCIA.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/configuracion/licencia/activar');

        try {
            $archivo = $_FILES['licencia_json'] ?? null;

            if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new \InvalidArgumentException('Debe seleccionar un archivo de licencia .json.');
            }
            if ($archivo['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Error al subir el archivo.');
            }

            $extension = strtolower((string) pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if ($extension !== 'json') {
                throw new \InvalidArgumentException('El archivo debe ser .json.');
            }

            $contenido = file_get_contents($archivo['tmp_name']);
            if ($contenido === false) {
                throw new \RuntimeException('No se pudo leer el archivo.');
            }

            $json = json_decode($contenido, true);
            if (!is_array($json) || empty($json['modulos'])) {
                throw new \InvalidArgumentException('El archivo JSON no tiene el formato esperado (falta "modulos").');
            }

            // La empresa de la licencia: viene en el JSON o se usa la empresa del usuario
            $empresaId = isset($json['empresa_id'])
                ? (int) $json['empresa_id']
                : (int) $usuario['empresa_id'];

            if (!$this->esSuperusuario($usuario) && $empresaId !== (int) $usuario['empresa_id']) {
                throw new \InvalidArgumentException('No puede activar licencias de otra empresa.');
            }

            $this->licenciaModelo->guardarDesdeJson($empresaId, $json, (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Licencia activada', 'Los módulos han sido activados correctamente.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ACTIVAR LICENCIA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo activar', $excepcion->getMessage());
        }

        $this->redirigir('/sistema/configuracion/licencia');
    }

    /**
     * ***************************************************************************
     * * GENERA Y DESCARGA UN JSON DE LICENCIA (SOLO SUPERUSUARIO).
     * ***************************************************************************
     */
    public function generar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/configuracion/licencia/generar');

        if (!$this->esSuperusuario($usuario)) {
            $this->sesion->guardarMensaje('error', 'Sin permiso', 'Solo el superusuario puede generar licencias.');
            $this->redirigir('/sistema/configuracion/licencia');
            return;
        }

        try {
            $empresaId   = (int) ($_POST['empresa_id'] ?? 0);
            $tipo        = in_array($_POST['tipo'] ?? '', ['TRIAL', 'FULL', 'MODULAR'], true) ? $_POST['tipo'] : 'TRIAL';
            $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? date('Y-m-d')));
            $fechaFin    = trim((string) ($_POST['fecha_fin'] ?? date('Y-m-d', strtotime('+1 year'))));
            $modulosIds  = array_map('intval', $_POST['modulos'] ?? []);

            if ($empresaId <= 0 || empty($modulosIds)) {
                throw new \InvalidArgumentException('Empresa y al menos un módulo son obligatorios.');
            }

            $modulos = array_map(fn (int $id) => ['modulo_id' => $id], $modulosIds);

            $licenciaJson = [
                'empresa_id'   => $empresaId,
                'tipo'         => $tipo,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin'    => $fechaFin,
                'generada_el'  => date('Y-m-d H:i:s'),
                'generada_por' => $usuario['nombre'] ?? 'SISTEMA',
                'modulos'      => $modulos,
            ];

            $contenido = json_encode($licenciaJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $nombreArchivo = 'licencia_' . $empresaId . '_' . date('Ymd_His') . '.json';

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . strlen($contenido));
            header('Cache-Control: no-cache');
            echo $contenido;
            exit;
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('GENERAR LICENCIA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo generar', $excepcion->getMessage());
            $this->redirigir('/sistema/configuracion/licencia');
        }
    }

    /**
     * ***************************************************************************
     * * OBTIENE PERMISOS DE BOTONES DEL CRUD.
     * ***************************************************************************
     */
    private function obtenerPermisos(array $usuario): array
    {
        $empresaId = (int) $usuario['empresa_id'];
        $perfilId  = (int) $usuario['perfil_id'];

        return [
            'ver'     => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/licencia/ver'),
            'activar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/licencia/activar'),
            'generar' => $this->menuModelo->tienePermiso($empresaId, $perfilId, '/sistema/configuracion/licencia/generar'),
        ];
    }

    /**
     * ***************************************************************************
     * * VALIDA EL PERMISO DEL USUARIO ACTUAL PARA UNA ACCIÓN.
     * ***************************************************************************
     */
    private function exigirPermiso(string $url): void
    {
        $usuario = $this->exigirSesion();
        if (!$this->menuModelo->tienePermiso((int) $usuario['empresa_id'], (int) $usuario['perfil_id'], $url)) {
            $this->sesion->guardarMensaje('error', 'Sin permiso', 'No tiene acceso a esta sección.');
            $this->redirigir('/dashboard');
        }
    }
}
