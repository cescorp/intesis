<?php

declare(strict_types=1);

namespace Intesis\Servicios;

use Intesis\Modelos\UsuarioModelo;
use Intesis\Nucleo\RegistroErrores;
use Throwable;

final class AutenticacionServicio
{
    public function __construct(
        private UsuarioModelo $usuarioModelo,
        private RegistroErrores $registroErrores
    ) {
    }

    /**
     * ***************************************************************************
     * * VALIDA LAS CREDENCIALES DEL USUARIO CONTRA EL HASH GUARDADO EN LA BASE.
     * ***************************************************************************
     */
    public function autenticar(string $correo, string $clave): ?array
    {
        try {
            $usuario = $this->usuarioModelo->buscarPorCorreo($correo);
            if (!$usuario || !password_verify($clave, $usuario['sis_usuarios_password'])) {
                return null;
            }

            $empresas = $this->usuarioModelo->listarEmpresasAsignadas((int) $usuario['sis_usuarios_id']);
            if (!$empresas) {
                return null;
            }

            return [
                'id' => (int) $usuario['sis_usuarios_id'],
                'nombre' => $usuario['sis_usuarios_nombre'],
                'correo' => $usuario['sis_usuarios_correo'],
                'empresas' => $empresas,
            ];
        } catch (Throwable $excepcion) {
            $this->registroErrores->escribirExcepcion('FALLO AUTENTICACION', $excepcion);
            return null;
        }
    }
}
