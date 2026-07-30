ALTER TABLE sis_perfil
    ADD COLUMN sis_perfil_venta_todas_bodegas BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN sis_perfil.sis_perfil_venta_todas_bodegas IS
    'Si es TRUE, el perfil vende/ve stock de todas las bodegas de la empresa en Nueva factura; si es FALSE (predeterminado), solo ve su bodega asignada.';
