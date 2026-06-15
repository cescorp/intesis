-- =============================================================================
-- AMPLIAR CAMPO ven_documento_numero DE VARCHAR(20) A VARCHAR(25)
-- FORMATO MAXIMO: PRF-001-001-000000000 = 21 CARACTERES
-- =============================================================================

ALTER TABLE ven_documento
    ALTER COLUMN ven_documento_numero TYPE VARCHAR(25);

COMMENT ON COLUMN ven_documento.ven_documento_numero IS 'NUMERO DE DOCUMENTO FORMATO: PREFIJO-EST-PTO-SECUENCIA (MAX 25 CHARS)';
