BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CONFIGURA MENSAJES DE EXITO PARA SWEETALERT                               */
/*                                                                            */
/******************************************************************************/

UPDATE public.sis_mensaje_errores
SET sis_mensaje_errores_tiempo = 5000,
    sis_mensaje_errores_posicion = 2,
    usuario_modifica = 1,
    fecha_modifica = now()
WHERE sis_mensaje_errores_tipo = 'EXITO';

COMMIT;
