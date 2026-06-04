BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CAMBIA CONTRIBUYENTE ESPECIAL A INDICADOR SI/NO                           */
/*                                                                            */
/******************************************************************************/

ALTER TABLE public.sis_empresa
ALTER COLUMN sis_empresa_contribuyente_especial TYPE BOOLEAN
USING (
    CASE
        WHEN sis_empresa_contribuyente_especial IS NULL THEN FALSE
        WHEN trim(sis_empresa_contribuyente_especial) = '' THEN FALSE
        WHEN upper(trim(sis_empresa_contribuyente_especial)) IN ('S', 'SI', 'TRUE', '1') THEN TRUE
        ELSE TRUE
    END
);

ALTER TABLE public.sis_empresa
ALTER COLUMN sis_empresa_contribuyente_especial SET DEFAULT FALSE;

ALTER TABLE public.sis_empresa
ALTER COLUMN sis_empresa_contribuyente_especial SET NOT NULL;

COMMENT ON COLUMN public.sis_empresa.sis_empresa_contribuyente_especial IS 'INDICA SI LA EMPRESA ES CONTRIBUYENTE ESPECIAL';

COMMIT;
