-- Clave de usuario: minimo 4 caracteres (antes 8)
UPDATE public.sis_mensaje_errores
SET sis_mensaje_errores_mensaje = 'La clave debe tener minimo 4 caracteres y coincidir con la confirmacion.'
WHERE sis_mensaje_errores_codigo = 'USUARIO_CLAVE_INVALIDA';
