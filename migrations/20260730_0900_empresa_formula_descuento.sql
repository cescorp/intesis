ALTER TABLE sis_empresa
    ADD COLUMN sis_empresa_formula_descuento CHAR(1) NOT NULL DEFAULT 'C';

ALTER TABLE sis_empresa
    ADD CONSTRAINT chk_sis_empresa_formula_descuento CHECK (sis_empresa_formula_descuento IN ('A', 'B', 'C'));

COMMENT ON COLUMN sis_empresa.sis_empresa_formula_descuento IS
    'Formula para aplicar el % de descuento global en Nueva Factura/Nota de Venta: A=reduce base antes de IVA (recalcula IVA), B=reduce el total ya con IVA incluido, C=reduce solo la base y el IVA se suma completo (predeterminado). No aplica a Compras, que usa formula C fija.';
