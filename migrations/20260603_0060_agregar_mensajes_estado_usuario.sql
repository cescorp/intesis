BEGIN;

/******************************************************************************/
/*                                                                            */
/*  AGREGA MENSAJES EDITABLES PARA ESTADOS DE USUARIO                         */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_mensaje_errores (
    sis_mensaje_errores_codigo,
    sis_mensaje_errores_tipo,
    sis_mensaje_errores_titulo,
    sis_mensaje_errores_mensaje,
    sis_mensaje_errores_icono,
    sis_mensaje_errores_modulo,
    sis_mensaje_errores_entidad,
    usuario_crea
)
VALUES
    ('USUARIO_ACTIVADO', 'EXITO', 'Usuario activado', 'El usuario quedo activo.', 'success', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_INACTIVADO', 'EXITO', 'Usuario inactivado', 'El usuario quedo inactivo.', 'success', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_BLOQUEADO', 'EXITO', 'Usuario bloqueado', 'El usuario quedo bloqueado.', 'success', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_ELIMINADO', 'EXITO', 'Usuario eliminado', 'El usuario fue eliminado logicamente.', 'success', 'SISTEMA', 'SIS_USUARIOS', 1)
ON CONFLICT (sis_mensaje_errores_codigo) DO UPDATE
SET sis_mensaje_errores_tipo = EXCLUDED.sis_mensaje_errores_tipo,
    sis_mensaje_errores_titulo = EXCLUDED.sis_mensaje_errores_titulo,
    sis_mensaje_errores_mensaje = EXCLUDED.sis_mensaje_errores_mensaje,
    sis_mensaje_errores_icono = EXCLUDED.sis_mensaje_errores_icono,
    sis_mensaje_errores_modulo = EXCLUDED.sis_mensaje_errores_modulo,
    sis_mensaje_errores_entidad = EXCLUDED.sis_mensaje_errores_entidad,
    fecha_modifica = now(),
    usuario_modifica = 1;

COMMIT;
