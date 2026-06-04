BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA CATALOGO EDITABLE DE MENSAJES, ERRORES Y ALERTAS                     */
/*                                                                            */
/******************************************************************************/

CREATE TABLE IF NOT EXISTS public.sis_mensaje_errores (
    sis_mensaje_errores_id BIGSERIAL NOT NULL,
    sis_mensaje_errores_codigo VARCHAR(80) NOT NULL,
    sis_mensaje_errores_tipo VARCHAR(30) NOT NULL,
    sis_mensaje_errores_titulo VARCHAR(120) NOT NULL,
    sis_mensaje_errores_mensaje VARCHAR(500) NOT NULL,
    sis_mensaje_errores_icono VARCHAR(30) NOT NULL,
    sis_mensaje_errores_modulo VARCHAR(30) NOT NULL,
    sis_mensaje_errores_entidad VARCHAR(60) NOT NULL,
    sis_mensaje_errores_activo BOOLEAN NOT NULL DEFAULT TRUE,
    usuario_crea BIGINT NOT NULL,
    usuario_modifica BIGINT,
    fecha_crea TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    fecha_modifica TIMESTAMP WITHOUT TIME ZONE,
    CONSTRAINT sis_mensaje_errores_pk PRIMARY KEY (sis_mensaje_errores_id),
    CONSTRAINT uq_sis_mensaje_errores_codigo UNIQUE (sis_mensaje_errores_codigo)
);

COMMENT ON TABLE public.sis_mensaje_errores IS 'CATALOGO EDITABLE DE MENSAJES, ERRORES, ALERTAS E INFORMACION DEL SISTEMA';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_id IS 'ID UNICO DEL MENSAJE';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_codigo IS 'CODIGO UNICO USADO POR EL SISTEMA PARA BUSCAR EL MENSAJE';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_tipo IS 'TIPO DEL MENSAJE: ERROR, ALERTA, INFO, EXITO, CONFIRMACION';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_titulo IS 'TITULO VISIBLE DEL MENSAJE';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_mensaje IS 'TEXTO DETALLADO DEL MENSAJE';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_icono IS 'ICONO USADO POR SWEETALERT: ERROR, WARNING, INFO, SUCCESS, QUESTION';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_modulo IS 'MODULO FUNCIONAL DONDE SE USA EL MENSAJE';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_entidad IS 'ENTIDAD O PANTALLA DONDE SE USA EL MENSAJE';
COMMENT ON COLUMN public.sis_mensaje_errores.sis_mensaje_errores_activo IS 'INDICA SI EL MENSAJE ESTA DISPONIBLE PARA USO';
COMMENT ON COLUMN public.sis_mensaje_errores.usuario_crea IS 'USUARIO QUE CREO EL REGISTRO';
COMMENT ON COLUMN public.sis_mensaje_errores.usuario_modifica IS 'USUARIO QUE MODIFICO EL REGISTRO';
COMMENT ON COLUMN public.sis_mensaje_errores.fecha_crea IS 'FECHA Y HORA DE CREACION';
COMMENT ON COLUMN public.sis_mensaje_errores.fecha_modifica IS 'FECHA Y HORA DE ULTIMA MODIFICACION';

/******************************************************************************/
/*                                                                            */
/*  INSERTA MENSAJES INICIALES DE CRUD SISTEMA                                */
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
    ('EMPRESA_RUC_INVALIDO', 'ERROR', 'RUC invalido', 'Ingrese un RUC ecuatoriano valido.', 'error', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('EMPRESA_DATOS_OBLIGATORIOS', 'ERROR', 'Datos incompletos', 'Razon social, nombre comercial y direccion son obligatorios.', 'error', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('EMPRESA_EMAIL_INVALIDO', 'ERROR', 'Correo invalido', 'Ingrese un correo electronico valido.', 'error', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('EMPRESA_CREADA', 'EXITO', 'Empresa creada', 'La empresa fue registrada correctamente.', 'success', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('EMPRESA_ACTUALIZADA', 'EXITO', 'Empresa actualizada', 'Los cambios fueron guardados correctamente.', 'success', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('EMPRESA_ACTIVADA', 'EXITO', 'Empresa activada', 'La empresa quedo activa.', 'success', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('EMPRESA_INACTIVADA', 'EXITO', 'Empresa inactivada', 'La empresa quedo inactiva.', 'success', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('EMPRESA_ELIMINADA', 'EXITO', 'Empresa eliminada', 'La empresa fue eliminada logicamente.', 'success', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('CONFIRMAR_INACTIVAR_EMPRESA', 'CONFIRMACION', 'Inactivar empresa', 'La empresa no podra usarse en nuevas operaciones mientras este inactiva.', 'warning', 'SISTEMA', 'SIS_EMPRESA', 1),
    ('CONFIRMAR_ELIMINAR_EMPRESA', 'CONFIRMACION', 'Eliminar empresa', 'La empresa se eliminara logicamente y dejara de aparecer en el listado principal.', 'warning', 'SISTEMA', 'SIS_EMPRESA', 1),

    ('USUARIO_DATOS_OBLIGATORIOS', 'ERROR', 'Datos incompletos', 'Seleccione empresa, perfil, nombre y correo valido.', 'error', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_CLAVE_INVALIDA', 'ERROR', 'Clave invalida', 'La clave debe tener minimo 8 caracteres y coincidir con la confirmacion.', 'error', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_CORREO_ASIGNADO', 'ERROR', 'Correo asignado', 'El correo ya esta asignado a esa empresa.', 'error', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_CORREO_REGISTRADO', 'ERROR', 'Correo registrado', 'El correo ya esta registrado en el sistema.', 'error', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_CREADO', 'EXITO', 'Usuario creado', 'El usuario fue registrado correctamente.', 'success', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_ACTUALIZADO', 'EXITO', 'Usuario actualizado', 'Los cambios fueron guardados correctamente.', 'success', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('USUARIO_CLAVE_RESTABLECIDA', 'EXITO', 'Clave restablecida', 'La nueva clave fue guardada correctamente.', 'success', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('CONFIRMAR_INACTIVAR_USUARIO', 'CONFIRMACION', 'Inactivar usuario', 'El usuario no podra ingresar a esta empresa mientras este inactivo.', 'warning', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('CONFIRMAR_BLOQUEAR_USUARIO', 'CONFIRMACION', 'Bloquear usuario', 'El usuario quedara bloqueado por seguridad.', 'warning', 'SISTEMA', 'SIS_USUARIOS', 1),
    ('CONFIRMAR_ELIMINAR_USUARIO', 'CONFIRMACION', 'Eliminar usuario', 'La asignacion se eliminara logicamente del sistema.', 'warning', 'SISTEMA', 'SIS_USUARIOS', 1),

    ('ERROR_GENERICO_GUARDAR', 'ERROR', 'No se pudo guardar', 'Revise los datos ingresados e intente nuevamente.', 'error', 'SISTEMA', 'GENERAL', 1),
    ('ERROR_SIN_PERMISO', 'ERROR', 'Acceso restringido', 'Su perfil no tiene permiso para esta accion.', 'error', 'SISTEMA', 'GENERAL', 1),
    ('MENSAJE_NO_CONFIGURADO', 'ALERTA', 'Mensaje no configurado', 'No existe configuracion para el codigo de mensaje solicitado.', 'warning', 'SISTEMA', 'GENERAL', 1)
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
