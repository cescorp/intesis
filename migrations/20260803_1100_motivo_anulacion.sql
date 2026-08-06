ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS ven_documento_motivo_anulacion VARCHAR(300);

ALTER TABLE com_documento
    ADD COLUMN IF NOT EXISTS com_documento_motivo_anulacion VARCHAR(300);

ALTER TABLE inv_movimientos
    ADD COLUMN IF NOT EXISTS inv_movimientos_motivo_anulacion VARCHAR(300);

COMMENT ON COLUMN ven_documento.ven_documento_motivo_anulacion IS
    'Motivo obligatorio capturado al anular Factura/Nota de Venta/Proforma. NULL si el documento no ha sido anulado.';
COMMENT ON COLUMN com_documento.com_documento_motivo_anulacion IS
    'Motivo obligatorio capturado al anular un documento de Compras. NULL si el documento no ha sido anulado.';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_motivo_anulacion IS
    'Motivo obligatorio capturado al anular un Ajuste/Transferencia. NULL si el movimiento no ha sido anulado.';
