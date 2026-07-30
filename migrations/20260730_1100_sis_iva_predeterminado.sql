ALTER TABLE sis_iva
    ADD COLUMN sis_iva_predeterminado BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN sis_iva.sis_iva_predeterminado IS
    'Tasa de IVA que se preselecciona por defecto en nuevas lineas de Factura/Proforma/Compra. Antes se inferia tomando la tasa activa de menor valor, lo cual seleccionaba 0% por error cuando convivia con una tasa mayor.';

-- Backfill: marca como predeterminada la tasa activa de MAYOR valor por empresa
-- (la tasa "normal", no la exenta), para las empresas que ya tengan catalogo cargado.
UPDATE sis_iva iv
SET sis_iva_predeterminado = TRUE
WHERE iv.sis_iva_id = (
    SELECT iv2.sis_iva_id
    FROM sis_iva iv2
    WHERE iv2.sis_empresa_id = iv.sis_empresa_id
      AND iv2.sis_iva_estado = 1
    ORDER BY iv2.sis_iva_valor DESC, iv2.sis_iva_id
    LIMIT 1
);
