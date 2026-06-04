BEGIN;

/******************************************************************************/
/*                                                                            */
/*  NORMALIZA RELACION A SIS_TIPO_DOCUMENTO                                   */
/*                                                                            */
/******************************************************************************/

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'com_documento' AND column_name = 'sis_documento_tipo_id')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'com_documento' AND column_name = 'sis_tipo_documento_id') THEN
        ALTER TABLE public.com_documento RENAME COLUMN sis_documento_tipo_id TO sis_tipo_documento_id;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'ven_documento' AND column_name = 'sis_documento_tipo_id')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'ven_documento' AND column_name = 'sis_tipo_documento_id') THEN
        ALTER TABLE public.ven_documento RENAME COLUMN sis_documento_tipo_id TO sis_tipo_documento_id;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'inv_movimientos' AND column_name = 'sis_documento_tipo_id')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'inv_movimientos' AND column_name = 'sis_tipo_documento_id') THEN
        ALTER TABLE public.inv_movimientos RENAME COLUMN sis_documento_tipo_id TO sis_tipo_documento_id;
    END IF;
END $$;

COMMENT ON COLUMN public.com_documento.sis_tipo_documento_id IS 'ID DEL TIPO DE DOCUMENTO';
COMMENT ON COLUMN public.ven_documento.sis_tipo_documento_id IS 'ID DEL TIPO DE DOCUMENTO';
COMMENT ON COLUMN public.inv_movimientos.sis_tipo_documento_id IS 'ID DEL TIPO DE DOCUMENTO';

COMMIT;
