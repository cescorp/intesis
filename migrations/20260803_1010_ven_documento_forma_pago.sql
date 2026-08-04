-- =============================================================================
-- VENTAS: DETALLE DE FORMAS DE PAGO POR DOCUMENTO (soporta pago Mixto)
-- =============================================================================

CREATE TABLE IF NOT EXISTS ven_documento_forma_pago (
    ven_documento_forma_pago_id           SERIAL         PRIMARY KEY,
    ven_documento_id                      BIGINT         NOT NULL REFERENCES ven_documento(ven_documento_id),
    ven_forma_pago_id                     INTEGER        NOT NULL REFERENCES ven_forma_pago(ven_forma_pago_id),
    ven_documento_forma_pago_monto        NUMERIC(16,4)  NOT NULL DEFAULT 0,
    ven_documento_forma_pago_nombre       VARCHAR(120),
    ven_documento_forma_pago_comprobante  VARCHAR(60),
    ven_documento_forma_pago_dias         INTEGER,
    usuario_crea                          INTEGER        NOT NULL,
    fecha_crea                            TIMESTAMP      NOT NULL DEFAULT now()
);

COMMENT ON TABLE ven_documento_forma_pago IS
    'Detalle de las formas de pago usadas en un documento de venta (Factura/Nota de Venta). Una fila por forma de pago; con pago Mixto puede haber varias filas por documento cuya suma de monto debe igualar el total del documento. Nombre/Comprobante aplican a TARJETA_CREDITO/TRANSFERENCIA/DEPOSITO/DEUNA, Dias aplica a CREDITO.';

CREATE INDEX IF NOT EXISTS ix_ven_documento_forma_pago_documento ON ven_documento_forma_pago (ven_documento_id);
