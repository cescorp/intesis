-- Agrega fecha de autorización SRI a ven_documento
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS ven_documento_fecha_autorizacion TIMESTAMP;

COMMENT ON COLUMN ven_documento.ven_documento_fecha_autorizacion IS 'FECHA Y HORA EN QUE EL SRI AUTORIZO EL DOCUMENTO';
