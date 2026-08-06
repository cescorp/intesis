ALTER TABLE ven_forma_pago
    ADD COLUMN IF NOT EXISTS ven_forma_pago_tipo VARCHAR(20) NOT NULL DEFAULT 'OTRO';

ALTER TABLE ven_forma_pago DROP CONSTRAINT IF EXISTS chk_ven_forma_pago_tipo;
ALTER TABLE ven_forma_pago
    ADD CONSTRAINT chk_ven_forma_pago_tipo CHECK (ven_forma_pago_tipo IN (
        'EFECTIVO', 'TARJETA_CREDITO', 'TRANSFERENCIA', 'CREDITO', 'DEPOSITO', 'DEUNA', 'OTRO'
    ));

COMMENT ON COLUMN ven_forma_pago.ven_forma_pago_tipo IS
    'Clasifica cada forma de pago para que Nueva Factura/Nota de Venta sepa que campos extra pedir (Nombre+No.Comprobante para TARJETA_CREDITO/TRANSFERENCIA/DEPOSITO/DEUNA, Dias para CREDITO, ninguno para EFECTIVO/OTRO) y si se puede combinar en un pago Mixto (CREDITO es exclusivo, no combinable).';

-- Backfill de las formas de pago que EmpresaModelo::crear() ya auto-crea por empresa
UPDATE ven_forma_pago SET ven_forma_pago_tipo = 'EFECTIVO'
    WHERE upper(ven_forma_pago_nombre) = 'EFECTIVO' AND ven_forma_pago_tipo = 'OTRO';
UPDATE ven_forma_pago SET ven_forma_pago_tipo = 'TRANSFERENCIA'
    WHERE upper(ven_forma_pago_nombre) = 'TRANSFERENCIA' AND ven_forma_pago_tipo = 'OTRO';
UPDATE ven_forma_pago SET ven_forma_pago_tipo = 'TARJETA_CREDITO'
    WHERE upper(ven_forma_pago_nombre) = 'TARJETA CRÉDITO' AND ven_forma_pago_tipo = 'OTRO';
