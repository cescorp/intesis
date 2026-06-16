-- Agrega establecimiento y punto de emision a inv_bodega (para vincular con secuencias SRI)
ALTER TABLE inv_bodega
    ADD COLUMN IF NOT EXISTS inv_bodega_establecimiento VARCHAR(3) NULL,
    ADD COLUMN IF NOT EXISTS inv_bodega_punto_emision   VARCHAR(3) NULL;

-- Pre-popular desde sis_secuencias donde ya existe el vínculo por inv_bodega_id
UPDATE inv_bodega b
SET inv_bodega_establecimiento = s.sis_secuencias_establecimiento,
    inv_bodega_punto_emision   = s.sis_secuencias_punto_emision
FROM sis_secuencias s
WHERE s.inv_bodega_id = b.inv_bodega_id
  AND b.inv_bodega_establecimiento IS NULL
  AND s.sis_secuencias_establecimiento IS NOT NULL;
