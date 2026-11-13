-- Formas de pago: nuevo tipo TARJETA_DEBITO y codigo SRI deducido del tipo
-- (ya no se captura a mano). 01 sin sistema financiero, 16 tarjeta debito,
-- 19 tarjeta credito, 20 otros con utilizacion del sistema financiero.

ALTER TABLE ven_forma_pago DROP CONSTRAINT IF EXISTS chk_ven_forma_pago_tipo;
ALTER TABLE ven_forma_pago
    ADD CONSTRAINT chk_ven_forma_pago_tipo CHECK (ven_forma_pago_tipo IN (
        'EFECTIVO', 'TARJETA_CREDITO', 'TARJETA_DEBITO', 'TRANSFERENCIA', 'CREDITO', 'DEPOSITO', 'DEUNA', 'OTRO'
    ));

-- Formas de pago "Otro" que por nombre son tarjeta de debito
UPDATE ven_forma_pago SET ven_forma_pago_tipo = 'TARJETA_DEBITO'
 WHERE ven_forma_pago_tipo = 'OTRO'
   AND upper(ven_forma_pago_nombre) IN ('TARJETA DEBITO', 'TARJETA DÉBITO');

UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = CASE ven_forma_pago_tipo
        WHEN 'TARJETA_DEBITO'  THEN '16'
        WHEN 'TARJETA_CREDITO' THEN '19'
        WHEN 'TRANSFERENCIA'   THEN '20'
        WHEN 'DEPOSITO'        THEN '20'
        WHEN 'DEUNA'           THEN '20'
        ELSE '01'
    END;
