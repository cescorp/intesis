-- ============================================================
-- Migración: 20260610_0040
-- • Agrega com_proveedor_id a inv_codigo_proveedor
--   (un código por producto+proveedor, único activo)
-- • Agrega com_documento_detalle_cod_proveedor a com_documento_detalle
--   (guarda el código que el usuario ingresó en el documento borrador)
-- ============================================================

ALTER TABLE inv_codigo_proveedor
    ADD COLUMN IF NOT EXISTS com_proveedor_id INTEGER
        REFERENCES com_proveedor(com_proveedor_id);

-- Unicidad: solo un código activo por producto+proveedor
CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_cod_prov_prod_prov
    ON inv_codigo_proveedor (inv_producto_id, com_proveedor_id)
    WHERE inv_codigo_proveedor_estado = 1
      AND com_proveedor_id IS NOT NULL;

-- Columna para persistir el código de proveedor ingresado por línea de compra
ALTER TABLE com_documento_detalle
    ADD COLUMN IF NOT EXISTS com_documento_detalle_cod_proveedor VARCHAR(100);
