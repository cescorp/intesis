BEGIN;

/******************************************************************************/
/*                                                                            */
/*  NORMALIZA PREFIJOS DE CAMPOS PROPIOS EN TABLAS CONTABLES                  */
/*                                                                            */
/******************************************************************************/

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_conciliacion_bancaria' AND column_name = 'con_conciliacion_fecha_banco') THEN
        ALTER TABLE public.con_conciliacion_bancaria RENAME COLUMN con_conciliacion_fecha_banco TO con_conciliacion_bancaria_fecha_banco;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_conciliacion_bancaria' AND column_name = 'con_conciliacion_estado') THEN
        ALTER TABLE public.con_conciliacion_bancaria RENAME COLUMN con_conciliacion_estado TO con_conciliacion_bancaria_estado;
    END IF;
END $$;

COMMENT ON COLUMN public.con_conciliacion_bancaria.con_conciliacion_bancaria_fecha_banco IS 'FECHA DE CONCILIACION REGISTRADA POR EL BANCO';
COMMENT ON COLUMN public.con_conciliacion_bancaria.con_conciliacion_bancaria_estado IS 'ESTADO ACTUAL DE LA CONCILIACION BANCARIA';

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_plantilla_integracion' AND column_name = 'con_plantilla_modulo') THEN
        ALTER TABLE public.con_plantilla_integracion RENAME COLUMN con_plantilla_modulo TO con_plantilla_integracion_modulo;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_plantilla_integracion' AND column_name = 'con_plantilla_evento') THEN
        ALTER TABLE public.con_plantilla_integracion RENAME COLUMN con_plantilla_evento TO con_plantilla_integracion_evento;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_plantilla_integracion' AND column_name = 'con_plantilla_tipo_linea') THEN
        ALTER TABLE public.con_plantilla_integracion RENAME COLUMN con_plantilla_tipo_linea TO con_plantilla_integracion_tipo_linea;
    END IF;
END $$;

COMMENT ON COLUMN public.con_plantilla_integracion.con_plantilla_integracion_modulo IS 'MODULO FUNCIONAL QUE USA LA PLANTILLA';
COMMENT ON COLUMN public.con_plantilla_integracion.con_plantilla_integracion_evento IS 'EVENTO OPERATIVO QUE DISPARA LA PLANTILLA';
COMMENT ON COLUMN public.con_plantilla_integracion.con_plantilla_integracion_tipo_linea IS 'TIPO DE LINEA CONTABLE DE LA PLANTILLA';

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_retencion_concepto' AND column_name = 'con_retencion_codigo_sri') THEN
        ALTER TABLE public.con_retencion_concepto RENAME COLUMN con_retencion_codigo_sri TO con_retencion_concepto_codigo_sri;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_retencion_concepto' AND column_name = 'con_retencion_porcentaje') THEN
        ALTER TABLE public.con_retencion_concepto RENAME COLUMN con_retencion_porcentaje TO con_retencion_concepto_porcentaje;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_retencion_concepto' AND column_name = 'con_retencion_tipo') THEN
        ALTER TABLE public.con_retencion_concepto RENAME COLUMN con_retencion_tipo TO con_retencion_concepto_tipo;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'con_retencion_concepto' AND column_name = 'con_retencion_estado') THEN
        ALTER TABLE public.con_retencion_concepto RENAME COLUMN con_retencion_estado TO con_retencion_concepto_estado;
    END IF;
END $$;

COMMENT ON COLUMN public.con_retencion_concepto.con_retencion_concepto_codigo_sri IS 'CODIGO SRI DEL CONCEPTO DE RETENCION';
COMMENT ON COLUMN public.con_retencion_concepto.con_retencion_concepto_porcentaje IS 'PORCENTAJE APLICADO AL CONCEPTO DE RETENCION';
COMMENT ON COLUMN public.con_retencion_concepto.con_retencion_concepto_tipo IS 'TIPO DE RETENCION DEL CONCEPTO';
COMMENT ON COLUMN public.con_retencion_concepto.con_retencion_concepto_estado IS 'ESTADO ACTUAL DEL CONCEPTO DE RETENCION';

/******************************************************************************/
/*                                                                            */
/*  NORMALIZA PREFIJOS DE CAMPOS PROPIOS EN SECUENCIAS                        */
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

COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_id IS 'ID UNICO DE LA SECUENCIA';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_establecimiento IS 'CODIGO DE ESTABLECIMIENTO DE TRES DIGITOS';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_punto_emision IS 'CODIGO DE PUNTO DE EMISION DE TRES DIGITOS';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_desde IS 'NUMERO INICIAL PERMITIDO';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_actual IS 'NUMERO ACTUAL DE EMISION';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_hasta IS 'NUMERO FINAL PERMITIDO';
COMMENT ON COLUMN public.sis_secuencias.sis_secuencias_observacion IS 'OBSERVACION INTERNA DE LA SECUENCIA';

/******************************************************************************/
/*                                                                            */
/*  RENOMBRA LICENCIAS Y NORMALIZA SUS CAMPOS PROPIOS                         */
/*                                                                            */
/******************************************************************************/

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'sis_licencia_empresa')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'sis_licencia') THEN
        ALTER TABLE public.sis_licencia_empresa RENAME TO sis_licencia;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sis_licencia' AND column_name = 'sis_licencia_empresa_id') THEN
        ALTER TABLE public.sis_licencia RENAME COLUMN sis_licencia_empresa_id TO sis_licencia_id;
    END IF;
END $$;

COMMENT ON TABLE public.sis_licencia IS 'LICENCIAS ASIGNADAS A EMPRESAS POR MODULO';
COMMENT ON COLUMN public.sis_licencia.sis_licencia_id IS 'ID UNICO DE LA LICENCIA';
COMMENT ON COLUMN public.sis_licencia.sis_licencia_tipo IS 'TIPO DE LICENCIA ASIGNADA';
COMMENT ON COLUMN public.sis_licencia.sis_licencia_fecha_inicio IS 'FECHA DE INICIO DE LA LICENCIA';
COMMENT ON COLUMN public.sis_licencia.sis_licencia_fecha_fin IS 'FECHA DE FINALIZACION DE LA LICENCIA';
COMMENT ON COLUMN public.sis_licencia.sis_licencia_estado IS 'ESTADO ACTUAL DE LA LICENCIA';

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sis_licencia_empresa_pk') THEN
        ALTER TABLE public.sis_licencia RENAME CONSTRAINT sis_licencia_empresa_pk TO sis_licencia_pk;
    END IF;

    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sis_licencia_empresa_empresa_fk') THEN
        ALTER TABLE public.sis_licencia RENAME CONSTRAINT sis_licencia_empresa_empresa_fk TO sis_licencia_empresa_fk;
    END IF;

    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sis_licencia_empresa_modulo_fk') THEN
        ALTER TABLE public.sis_licencia RENAME CONSTRAINT sis_licencia_empresa_modulo_fk TO sis_licencia_modulo_fk;
    END IF;
END $$;

ALTER INDEX IF EXISTS public.uq_licencia_empresa_modulo
    RENAME TO uq_sis_licencia_empresa_modulo;

ALTER SEQUENCE IF EXISTS public.sis_licencia_empresa_sis_licencia_empresa_id_seq
    RENAME TO sis_licencia_sis_licencia_id_seq;

ALTER SEQUENCE IF EXISTS public.sis_licencia_sis_licencia_id_seq
    OWNED BY public.sis_licencia.sis_licencia_id;

COMMIT;
