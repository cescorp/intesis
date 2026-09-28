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
use Intesis\Servicios\LicenciaCifradoServicio;
use Intesis\Servicios\ValidadorIdentificacion;
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
        private RegistroErrores $registroErrores,
        private LicenciaCifradoServicio $licenciaCifradoServicio
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
            'esSuperusuario' => $esSuperusuario,
            'permisos'       => $this->obtenerPermisos($usuario),
            'mensaje'        => $this->sesion->consumirMensaje(),
        ]);
    }

    /**
     * ***************************************************************************
     * * PROCESA LA SUBIDA DEL ARCHIVO .LIC DE LICENCIA (CIFRADO).
     * * LA EMPRESA SE RESUELVE POR RUC CONTRA LA BASE LOCAL, NUNCA POR UN ID
     * * INTERNO, PORQUE EL ARCHIVO SE GENERA EN OTRA INSTALACION/BASE DE DATOS.
     * ***************************************************************************
     */
    public function activar(): void
    {
        $usuario = $this->exigirSesion();
        $this->exigirPermiso('/sistema/configuracion/licencia/activar');

        try {
            $archivo = $_FILES['licencia_json'] ?? null;

            if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new \InvalidArgumentException('Debe seleccionar un archivo de licencia .lic.');
            }
            if ($archivo['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Error al subir el archivo.');
            }

            $extension = strtolower((string) pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if ($extension !== 'lic') {
                throw new \InvalidArgumentException('El archivo debe ser .lic.');
            }

            $contenido = file_get_contents($archivo['tmp_name']);
            if ($contenido === false || trim($contenido) === '') {
                throw new \RuntimeException('No se pudo leer el archivo.');
            }

            $payload = $this->licenciaCifradoServicio->descifrar($contenido);

            $ruc = trim((string) ($payload['ruc'] ?? ''));
            $modulos = $payload['modulos'] ?? [];
            if ($ruc === '' || !is_array($modulos) || empty($modulos)) {
                throw new \InvalidArgumentException('La licencia no tiene el formato esperado (falta RUC o módulos).');
            }

            $empresa = $this->licenciaModelo->buscarEmpresaPorRuc($ruc);
            if ($empresa === null) {
                throw new \InvalidArgumentException("No existe una empresa activa con RUC {$ruc} registrada en este sistema.");
            }
            $empresaId = (int) $empresa['sis_empresa_id'];

            if (!$this->esSuperusuario($usuario) && $empresaId !== (int) $usuario['empresa_id']) {
                throw new \InvalidArgumentException('No puede activar licencias de otra empresa.');
            }

            $tipo        = trim((string) ($payload['tipo'] ?? 'DEMO'));
            $fechaInicio = trim((string) ($payload['fecha_inicio'] ?? date('Y-m-d')));
            $fechaFin    = trim((string) ($payload['fecha_fin'] ?? date('Y-m-d')));

            $this->licenciaModelo->guardarLicencia($empresaId, $tipo, $fechaInicio, $fechaFin, $modulos, trim($contenido), (int) $usuario['id']);
            $this->sesion->guardarMensaje('success', 'Licencia activada', 'Módulos activados para ' . $empresa['sis_empresa_razon_social'] . '.');
        } catch (Throwable $excepcion) {
            $this->registrarErrorCrud('ACTIVAR LICENCIA', $excepcion);
            $this->sesion->guardarMensaje('error', 'No se pudo activar', $excepcion->getMessage());
        }

        $this->redirigir('/sistema/configuracion/licencia');
    }

    /**
     * ***************************************************************************
     * * GENERA Y DESCARGA UN ARCHIVO .LIC CIFRADO (SOLO SUPERUSUARIO).
     * * NO DEPENDE DE NINGUNA EMPRESA REGISTRADA EN ESTA BASE: SE IDENTIFICA POR
     * * RUC PORQUE ESTA PENSADO PARA CLIENTES EN OTRAS INSTALACIONES.
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
            $ruc         = trim((string) ($_POST['ruc'] ?? ''));
            $razonSocial = trim((string) ($_POST['razon_social'] ?? ''));
            $tipo        = in_array($_POST['tipo'] ?? '', ['DEMO', 'PAGO', 'GRATUITO'], true) ? $_POST['tipo'] : 'DEMO';
            $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? date('Y-m-d')));
            $fechaFin    = trim((string) ($_POST['fecha_fin'] ?? date('Y-m-d', strtotime('+1 year'))));
            $modulos     = array_values(array_filter(array_map('strval', $_POST['modulos'] ?? [])));

            if (!ValidadorIdentificacion::validarRuc($ruc)) {
                throw new \InvalidArgumentException('El RUC ingresado no es válido.');
            }
            if ($razonSocial === '') {
                throw new \InvalidArgumentException('La razón social es obligatoria.');
            }
            if (empty($modulos)) {
                throw new \InvalidArgumentException('Debe seleccionar al menos un módulo.');
            }

            $payload = [
                'ruc'          => $ruc,
                'razon_social' => $razonSocial,
                'tipo'         => $tipo,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin'    => $fechaFin,
                'generada_el'  => date('Y-m-d H:i:s'),
                'generada_por' => $usuario['nombre'] ?? 'SISTEMA',
                'modulos'      => $modulos,
            ];

            $contenido = $this->licenciaCifradoServicio->cifrar($payload);
            $nombreArchivo = 'licencia_' . $ruc . '_' . date('Ymd_His') . '.lic';

            header('Content-Type: application/octet-stream');
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
