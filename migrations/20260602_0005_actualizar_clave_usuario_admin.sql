BEGIN;

/******************************************************************************/
/*                                                                            */
/*  ACTUALIZA CLAVE DEL USUARIO ADMINISTRADOR CON HASH ARGON2ID               */
/*                                                                            */
/******************************************************************************/

UPDATE public.sis_usuarios
SET sis_usuarios_password = '$argon2id$v=19$m=65536,t=4,p=1$Li9yRzIwQS9PNEs3Q09GYg$226mv3mxLYP8G8wmVstIYqIwDTrd1WT5SbYOvUb/zqQ',
    usuario_modifica = 1,
    fecha_modifica = now()
WHERE sis_usuarios_correo = 'cescorp@hotmail.es';

COMMIT;
