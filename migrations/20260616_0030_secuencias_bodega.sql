-- Agrega bodega a sis_secuencias para secuencias por empresa+bodega
ALTER TABLE sis_secuencias ADD COLUMN IF NOT EXISTS inv_bodega_id BIGINT NULL
    REFERENCES inv_bodega(inv_bodega_id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- Asignar bodega principal a secuencias existentes de empresa 1
UPDATE sis_secuencias s
SET inv_bodega_id = (
    SELECT b.inv_bodega_id FROM inv_bodega b
    WHERE b.sis_empresa_id = s.sis_empresa_id
      AND b.inv_bodega_es_principal = true
    LIMIT 1
)
WHERE s.sis_empresa_id = 1
  AND s.inv_bodega_id IS NULL;

-- Asignar primera bodega disponible a otras empresas que no tengan bodega principal marcada
UPDATE sis_secuencias s
SET inv_bodega_id = (
    SELECT b.inv_bodega_id FROM inv_bodega b
    WHERE b.sis_empresa_id = s.sis_empresa_id
    ORDER BY b.inv_bodega_id ASC
    LIMIT 1
)
WHERE s.inv_bodega_id IS NULL
  AND EXISTS (SELECT 1 FROM inv_bodega b WHERE b.sis_empresa_id = s.sis_empresa_id);
