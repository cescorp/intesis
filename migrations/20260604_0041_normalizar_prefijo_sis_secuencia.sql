BEGIN;

/******************************************************************************/
/*                                                                            */
/*  NORMALIZA PREFIJO DE CAMPOS SIS_SECUENCIAS A SIS_SECUENCIAS_*             */
/*                                                                            */
/******************************************************************************/

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_id') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencia_id TO sis_secuencias_id;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_establecimiento') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencia_establecimiento TO sis_secuencias_establecimiento;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_punto_emision') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencia_punto_emision TO sis_secuencias_punto_emision;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_actual') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencia_actual TO sis_secuencias_actual;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_desde') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencia_desde TO sis_secuencias_desde;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_hasta') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencia_hasta TO sis_secuencias_hasta;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencia_observacion') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencia_observacion TO sis_secuencias_observacion;
    END IF;
END $$;

COMMENT ON COLUMN sis_secuencias.sis_secuencias_id IS 'IDENTIFICADOR DE LA SECUENCIA';
COMMENT ON COLUMN sis_secuencias.sis_secuencias_establecimiento IS 'CODIGO DE ESTABLECIMIENTO DE TRES DIGITOS';
COMMENT ON COLUMN sis_secuencias.sis_secuencias_punto_emision IS 'CODIGO DE PUNTO DE EMISION DE TRES DIGITOS';
COMMENT ON COLUMN sis_secuencias.sis_secuencias_actual IS 'NUMERO ACTUAL DE EMISION';
COMMENT ON COLUMN sis_secuencias.sis_secuencias_desde IS 'NUMERO INICIAL PERMITIDO';
COMMENT ON COLUMN sis_secuencias.sis_secuencias_hasta IS 'NUMERO FINAL PERMITIDO';
COMMENT ON COLUMN sis_secuencias.sis_secuencias_observacion IS 'OBSERVACION INTERNA DE LA SECUENCIA';

COMMIT;
