BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA ASIGNACION DE USUARIOS A EMPRESAS Y PERFILES                         */
/*                                                                            */
/******************************************************************************/

CREATE TABLE IF NOT EXISTS public.sis_usuario_empresa (
    sis_usuario_empresa_id BIGSERIAL NOT NULL,
    sis_usuarios_id BIGINT NOT NULL,
    sis_empresa_id BIGINT NOT NULL,
    sis_perfil_id BIGINT NOT NULL,
    sis_estado_id BIGINT NOT NULL,
    usuario_crea BIGINT NOT NULL,
    usuario_modifica BIGINT,
    fecha_crea TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    fecha_modifica TIMESTAMP WITHOUT TIME ZONE,
    CONSTRAINT sis_usuario_empresa_pk PRIMARY KEY (sis_usuario_empresa_id),
    CONSTRAINT sis_usuario_empresa_usuario_fk FOREIGN KEY (sis_usuarios_id) REFERENCES public.sis_usuarios (sis_usuarios_id),
    CONSTRAINT sis_usuario_empresa_empresa_fk FOREIGN KEY (sis_empresa_id) REFERENCES public.sis_empresa (sis_empresa_id),
    CONSTRAINT sis_usuario_empresa_perfil_fk FOREIGN KEY (sis_perfil_id) REFERENCES public.sis_perfil (sis_perfil_id),
    CONSTRAINT sis_usuario_empresa_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id)
);

COMMENT ON TABLE public.sis_usuario_empresa IS 'ASIGNACION DE USUARIOS A EMPRESAS CON PERFIL Y ESTADO';
COMMENT ON COLUMN public.sis_usuario_empresa.sis_usuario_empresa_id IS 'ID UNICO DE LA ASIGNACION USUARIO EMPRESA';
COMMENT ON COLUMN public.sis_usuario_empresa.sis_usuarios_id IS 'ID DEL USUARIO GLOBAL';
COMMENT ON COLUMN public.sis_usuario_empresa.sis_empresa_id IS 'ID DE LA EMPRESA ASIGNADA';
COMMENT ON COLUMN public.sis_usuario_empresa.sis_perfil_id IS 'ID DEL PERFIL DEL USUARIO EN LA EMPRESA';
COMMENT ON COLUMN public.sis_usuario_empresa.sis_estado_id IS 'ESTADO ACTUAL DE LA ASIGNACION';
COMMENT ON COLUMN public.sis_usuario_empresa.usuario_crea IS 'USUARIO QUE CREO EL REGISTRO';
COMMENT ON COLUMN public.sis_usuario_empresa.usuario_modifica IS 'USUARIO QUE MODIFICO EL REGISTRO';
COMMENT ON COLUMN public.sis_usuario_empresa.fecha_crea IS 'FECHA Y HORA DE CREACION';
COMMENT ON COLUMN public.sis_usuario_empresa.fecha_modifica IS 'FECHA Y HORA DE ULTIMA MODIFICACION';

CREATE UNIQUE INDEX IF NOT EXISTS uq_sis_usuario_empresa
ON public.sis_usuario_empresa (sis_usuarios_id, sis_empresa_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_sis_usuarios_correo_global
ON public.sis_usuarios (lower(sis_usuarios_correo));

/******************************************************************************/
/*                                                                            */
/*  MIGRA RELACION ACTUAL DE USUARIO, EMPRESA Y PERFIL                        */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_usuario_empresa (
    sis_usuarios_id,
    sis_empresa_id,
    sis_perfil_id,
    sis_estado_id,
    usuario_crea
)
SELECT
    u.sis_usuarios_id,
    u.sis_empresa_id,
    u.sis_perfil_id,
    u.sis_estado_id,
    COALESCE(u.usuario_crea, 1)
FROM public.sis_usuarios u
WHERE NOT EXISTS (
    SELECT 1
    FROM public.sis_usuario_empresa ue
    WHERE ue.sis_usuarios_id = u.sis_usuarios_id
      AND ue.sis_empresa_id = u.sis_empresa_id
);

COMMIT;
