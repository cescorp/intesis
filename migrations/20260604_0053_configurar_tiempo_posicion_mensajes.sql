BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CONFIGURA TIEMPO Y POSICION DE MENSAJES SWEETALERT                        */
/*                                                                            */
/******************************************************************************/

ALTER TABLE public.sis_mensaje_errores
ADD COLUMN IF NOT EXISTS sis_mensaje_errores_tiempo INTEGER NOT NULL DEFAULT 0;

ALTER TABLE public.sis_mensaje_errores
ADD COLUMN IF NOT EXISTS sis_mensaje_errores_posicion INTEGER NOT NULL DEFAULT 4;

COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_tiempo IS '0=FIJO Y ESPERA CLICK, MAYOR A 0=DESAPARECE AUTOMATICAMENTE';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_posicion IS '1=TOP, 2=TOP-END, 3=CENTER-END, 4=CENTER, 5=BOTTOM-END';

UPDATE public.sis_mensaje_errores
SET sis_mensaje_errores_posicion = 2,
    usuario_modifica = 1,
    fecha_modifica = now()
WHERE sis_mensaje_errores_tipo = 'EXITO';

/******************************************************************************/
/*                                                                            */
/*  AGREGA MENSAJES GENERICOS USADOS POR SWEETALERT                           */
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
    sis_mensaje_errores_activo,
    sis_mensaje_errores_tiempo,
    sis_mensaje_errores_posicion,
    usuario_crea
)
VALUES
    ('REGISTRO_GUARDADO', 'EXITO', 'Registro guardado', 'Registro guardado correctamente.', 'success', 'SISTEMA', 'GENERAL', TRUE, 2500, 2, 1),
    ('ERROR_VALIDACION', 'ERROR', 'No se pudo guardar', 'Revise los datos ingresados.', 'error', 'SISTEMA', 'GENERAL', TRUE, 0, 4, 1),
    ('ERROR_SESION', 'ERROR', 'Sesion no activa', 'Inicie sesion para continuar.', 'error', 'SISTEMA', 'SEGURIDAD', TRUE, 0, 4, 1)
ON CONFLICT (sis_mensaje_errores_codigo) DO UPDATE
SET sis_mensaje_errores_tipo = EXCLUDED.sis_mensaje_errores_tipo,
    sis_mensaje_errores_titulo = EXCLUDED.sis_mensaje_errores_titulo,
    sis_mensaje_errores_mensaje = EXCLUDED.sis_mensaje_errores_mensaje,
    sis_mensaje_errores_icono = EXCLUDED.sis_mensaje_errores_icono,
    sis_mensaje_errores_modulo = EXCLUDED.sis_mensaje_errores_modulo,
    sis_mensaje_errores_entidad = EXCLUDED.sis_mensaje_errores_entidad,
    sis_mensaje_errores_tiempo = EXCLUDED.sis_mensaje_errores_tiempo,
    sis_mensaje_errores_posicion = EXCLUDED.sis_mensaje_errores_posicion,
    usuario_modifica = 1,
    fecha_modifica = now();

COMMIT;
