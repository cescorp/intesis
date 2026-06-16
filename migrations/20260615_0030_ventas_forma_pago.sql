-- =============================================================================
-- VENTAS: CATÁLOGO FORMAS DE PAGO
-- Crea ven_forma_pago, migra ven_documento_forma_pago → ven_forma_pago_id
-- =============================================================================

-- 1. TABLA CATÁLOGO
CREATE TABLE IF NOT EXISTS ven_forma_pago (
    ven_forma_pago_id           SERIAL        PRIMARY KEY,
    sis_empresa_id              INTEGER       NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    ven_forma_pago_nombre       VARCHAR(100)  NOT NULL,
    ven_forma_pago_estado       CHAR(1)       NOT NULL DEFAULT 'A',
    ven_forma_pago_solicitar_datos CHAR(1)    NOT NULL DEFAULT 'N',
    usuario_crea                INTEGER       NOT NULL,
    fecha_crea                  TIMESTAMP     NOT NULL DEFAULT now(),
    usuario_modifica            INTEGER,
    fecha_modifica              TIMESTAMP,
    CONSTRAINT uq_ven_forma_pago_empresa_nombre UNIQUE (sis_empresa_id, ven_forma_pago_nombre)
);

COMMENT ON TABLE  ven_forma_pago                                IS 'CATÁLOGO DE FORMAS DE PAGO POR EMPRESA';
COMMENT ON COLUMN ven_forma_pago.ven_forma_pago_estado          IS 'A=Activo, I=Inactivo';
COMMENT ON COLUMN ven_forma_pago.ven_forma_pago_solicitar_datos IS 'S=Solicitar datos adicionales al usar esta forma de pago, N=No solicitar';

-- 2. DATOS BASE — se insertan para cada empresa existente
INSERT INTO ven_forma_pago (sis_empresa_id, ven_forma_pago_nombre, ven_forma_pago_estado, ven_forma_pago_solicitar_datos, usuario_crea)
SELECT e.sis_empresa_id, fp.nombre, 'A', fp.solicita, 1
FROM sis_empresa e
CROSS JOIN (VALUES
    ('EFECTIVO',              'N'),
    ('TARJETA DE CRÉDITO',    'S'),
    ('TARJETA DE DÉBITO',     'S'),
    ('TRANSFERENCIA',         'S'),
    ('CHEQUE',                'S'),
    ('CRÉDITO',               'N')
) AS fp(nombre, solicita)
WHERE NOT EXISTS (
    SELECT 1 FROM ven_forma_pago
    WHERE sis_empresa_id = e.sis_empresa_id
      AND ven_forma_pago_nombre = fp.nombre
);

-- 3. AGREGAR COLUMNA FK EN ven_documento
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS ven_forma_pago_id INTEGER REFERENCES ven_forma_pago(ven_forma_pago_id);

-- 4. MIGRAR DATOS EXISTENTES
--    Documentos que ya tenían texto → apuntar al id de EFECTIVO de su empresa
UPDATE ven_documento d
SET    ven_forma_pago_id = fp.ven_forma_pago_id
FROM   ven_forma_pago fp
WHERE  fp.sis_empresa_id      = d.sis_empresa_id
  AND  fp.ven_forma_pago_nombre = 'EFECTIVO'
  AND  d.ven_forma_pago_id    IS NULL;

-- 5. ELIMINAR COLUMNA ANTIGUA
ALTER TABLE ven_documento
    DROP COLUMN IF EXISTS ven_documento_forma_pago;

-- 6. COLUMNAS FALTANTES (para valores que tal vez existían antes en ALTER previos)
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS ven_documento_subtotal_sin_impuestos NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS ven_documento_impuesto_total         NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS ven_documento_valor_total            NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS sis_usuarios_id                      INTEGER;
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS inv_bodega_id                        INTEGER;
