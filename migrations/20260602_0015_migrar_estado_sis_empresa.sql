BEGIN;

/******************************************************************************/
/*                                                                            */
/*  AGREGA ESTADOS DE EMPRESA AL CATALOGO CENTRAL                             */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_estado (
    sis_estado_modulo,
    sis_estado_entidad,
    sis_estado_codigo,
    sis_estado_nombre,
    sis_estado_descripcion,
    sis_estado_orden,
    usuario_crea
)
VALUES
    ('SISTEMA', 'SIS_EMPRESA', 'ACTIVO', 'ACTIVO', 'EMPRESA DISPONIBLE PARA OPERACIONES', 1, 1),
    ('SISTEMA', 'SIS_EMPRESA', 'INACTIVO', 'INACTIVO', 'EMPRESA DESHABILITADA TEMPORALMENTE', 2, 1),
    ('SISTEMA', 'SIS_EMPRESA', 'ELIMINADO', 'ELIMINADO', 'EMPRESA ELIMINADA LOGICAMENTE', 3, 1)
ON CONFLICT (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo) DO NOTHING;

/******************************************************************************/
/*                                                                            */
/*  MIGRA SIS_EMPRESA_ESTADO HACIA SIS_ESTADO_ID                              */
/*                                                                            */
/******************************************************************************/

ALTER TABLE public.sis_empresa ADD COLUMN sis_estado_id BIGINT;
COMMENT ON COLUMN public.sis_empresa.sis_estado_id IS 'ESTADO ACTUAL DE LA EMPRESA';

UPDATE public.sis_empresa t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'SISTEMA'
  AND e.sis_estado_entidad = 'SIS_EMPRESA'
  AND e.sis_estado_codigo = CASE t.sis_empresa_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      WHEN 1 THEN 'ACTIVO'
      ELSE 'INACTIVO'
  END;

ALTER TABLE public.sis_empresa ALTER COLUMN sis_estado_id SET NOT NULL;

DO $$
DECLARE
    v_estado_id BIGINT;
BEGIN
    SELECT sis_estado_id
    INTO v_estado_id
    FROM public.sis_estado
    WHERE sis_estado_modulo = 'SISTEMA'
      AND sis_estado_entidad = 'SIS_EMPRESA'
      AND sis_estado_codigo = 'ACTIVO';

    EXECUTE format('ALTER TABLE public.sis_empresa ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);
END $$;

ALTER TABLE public.sis_empresa
ADD CONSTRAINT sis_empresa_estado_fk
FOREIGN KEY (sis_estado_id)
REFERENCES public.sis_estado (sis_estado_id);

CREATE INDEX idx_sis_empresa_estado ON public.sis_empresa (sis_estado_id);

ALTER TABLE public.sis_empresa DROP COLUMN sis_empresa_estado;

COMMIT;
