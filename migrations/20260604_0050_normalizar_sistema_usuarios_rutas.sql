BEGIN;

/******************************************************************************/
/*                                                                            */
/*  NORMALIZA CAMPOS PROPIOS DE SIS_SECUENCIAS                                */
/*                                                                            */
/******************************************************************************/

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_id') THEN
        ALTER TABLE public.sis_secuencias RENAME COLUMN sis_secuencia_id TO sis_secuencias_id;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_establecimiento') THEN
        ALTER TABLE public.sis_secuencias RENAME COLUMN sis_secuencia_establecimiento TO sis_secuencias_establecimiento;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_punto_emision') THEN
        ALTER TABLE public.sis_secuencias RENAME COLUMN sis_secuencia_punto_emision TO sis_secuencias_punto_emision;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_desde') THEN
        ALTER TABLE public.sis_secuencias RENAME COLUMN sis_secuencia_desde TO sis_secuencias_desde;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_actual') THEN
        ALTER TABLE public.sis_secuencias RENAME COLUMN sis_secuencia_actual TO sis_secuencias_actual;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_hasta') THEN
        ALTER TABLE public.sis_secuencias RENAME COLUMN sis_secuencia_hasta TO sis_secuencias_hasta;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_observacion') THEN
        ALTER TABLE public.sis_secuencias RENAME COLUMN sis_secuencia_observacion TO sis_secuencias_observacion;
    END IF;
END $$;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_sis_secuencia_empresa_tipo_punto') THEN
        ALTER TABLE public.sis_secuencias DROP CONSTRAINT uq_sis_secuencia_empresa_tipo_punto;
    END IF;

    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_sis_secuencias_empresa_tipo_punto') THEN
        ALTER TABLE public.sis_secuencias DROP CONSTRAINT uq_sis_secuencias_empresa_tipo_punto;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_sis_secuencias_empresa_tipo_punto') THEN
        ALTER TABLE public.sis_secuencias
            ADD CONSTRAINT uq_sis_secuencias_empresa_tipo_punto
            UNIQUE (sis_empresa_id, sis_tipo_documento_id, sis_secuencias_establecimiento, sis_secuencias_punto_emision);
    END IF;
END $$;

COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_id IS 'ID UNICO DE LA SECUENCIA';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_establecimiento IS 'CODIGO DE ESTABLECIMIENTO';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_punto_emision IS 'CODIGO DE PUNTO DE EMISION';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_desde IS 'NUMERO INICIAL PERMITIDO';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_actual IS 'NUMERO ACTUAL DE EMISION';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_hasta IS 'NUMERO FINAL PERMITIDO';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_observacion IS 'OBSERVACION INTERNA';

/******************************************************************************/
/*                                                                            */
/*  QUITA EMPRESA Y PERFIL GLOBAL DE SIS_USUARIOS                             */
/*                                                                            */
/******************************************************************************/

DO $$
DECLARE
    v_constraint record;
BEGIN
    FOR v_constraint IN
        SELECT c.conname
        FROM pg_constraint c
        INNER JOIN pg_class t ON t.oid = c.conrelid
        INNER JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = 'public'
          AND t.relname = 'sis_usuarios'
          AND EXISTS (
              SELECT 1
              FROM unnest(c.conkey) AS k(attnum)
              INNER JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = k.attnum
              WHERE a.attname IN ('sis_empresa_id', 'sis_perfil_id')
          )
    LOOP
        EXECUTE format('ALTER TABLE public.sis_usuarios DROP CONSTRAINT IF EXISTS %I', v_constraint.conname);
    END LOOP;
END $$;

DROP INDEX IF EXISTS public.idx_sis_usuarios_estado;

ALTER TABLE public.sis_usuarios DROP COLUMN IF EXISTS sis_empresa_id;
ALTER TABLE public.sis_usuarios DROP COLUMN IF EXISTS sis_perfil_id;

CREATE INDEX IF NOT EXISTS idx_sis_usuarios_estado ON public.sis_usuarios (sis_estado_id);

/******************************************************************************/
/*                                                                            */
/*  NORMALIZA RUTAS DE ACCIONES DE CONFIGURACION                              */
/*                                                                            */
/******************************************************************************/

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/mensajes-error/crear'
WHERE sis_menu_url = '/sistema/configuracion/mensajes/crear'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/mensajes-error/crear');

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/mensajes-error/editar'
WHERE sis_menu_url = '/sistema/configuracion/mensajes/editar'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/mensajes-error/editar');

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/mensajes-error/activar'
WHERE sis_menu_url = '/sistema/configuracion/mensajes/activar'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/mensajes-error/activar');

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/mensajes-error/inactivar'
WHERE sis_menu_url = '/sistema/configuracion/mensajes/inactivar'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/mensajes-error/inactivar');

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/tipos-documento/crear'
WHERE sis_menu_url = '/sistema/configuracion/tipos/crear'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/tipos-documento/crear');

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/tipos-documento/editar'
WHERE sis_menu_url = '/sistema/configuracion/tipos/editar'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/tipos-documento/editar');

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/tipos-documento/activar'
WHERE sis_menu_url = '/sistema/configuracion/tipos/activar'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/tipos-documento/activar');

UPDATE public.sis_menu
SET sis_menu_url = '/sistema/configuracion/tipos-documento/inactivar'
WHERE sis_menu_url = '/sistema/configuracion/tipos/inactivar'
  AND NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion/tipos-documento/inactivar');

INSERT INTO public.sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM public.sis_perfil p
CROSS JOIN public.sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url IN (
      '/sistema/configuracion/mensajes-error/crear',
      '/sistema/configuracion/mensajes-error/editar',
      '/sistema/configuracion/mensajes-error/activar',
      '/sistema/configuracion/mensajes-error/inactivar',
      '/sistema/configuracion/tipos-documento/crear',
      '/sistema/configuracion/tipos-documento/editar',
      '/sistema/configuracion/tipos-documento/activar',
      '/sistema/configuracion/tipos-documento/inactivar'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
