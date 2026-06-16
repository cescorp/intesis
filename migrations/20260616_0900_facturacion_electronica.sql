-- =============================================================================
-- FACTURACIÓN ELECTRÓNICA SRI ECUADOR
-- Migración idempotente. Ejecutar una sola vez.
-- =============================================================================

-- ── sis_empresa: datos de firma y ambiente ────────────────────────────────────
ALTER TABLE sis_empresa
    ADD COLUMN IF NOT EXISTS sis_empresa_ambiente_sri              CHAR(1)        DEFAULT '1',
    ADD COLUMN IF NOT EXISTS sis_empresa_certificado_ruta          VARCHAR(500),
    ADD COLUMN IF NOT EXISTS sis_empresa_certificado_clave         VARCHAR(500),
    ADD COLUMN IF NOT EXISTS sis_empresa_num_contribuyente_especial VARCHAR(20);

-- ── inv_bodega: establecimiento y punto de emisión ────────────────────────────
ALTER TABLE inv_bodega
    ADD COLUMN IF NOT EXISTS inv_bodega_establecimiento VARCHAR(3) DEFAULT '001',
    ADD COLUMN IF NOT EXISTS inv_bodega_punto_emision   VARCHAR(3) DEFAULT '001';

-- ── ven_documento: campos SRI ─────────────────────────────────────────────────
ALTER TABLE ven_documento
    ADD COLUMN IF NOT EXISTS ven_documento_clave_acceso        VARCHAR(49),
    ADD COLUMN IF NOT EXISTS ven_documento_autorizacion_sri    VARCHAR(49),
    ADD COLUMN IF NOT EXISTS ven_documento_fecha_autorizacion  TIMESTAMP,
    ADD COLUMN IF NOT EXISTS ven_documento_xml_autorizado      TEXT,
    ADD COLUMN IF NOT EXISTS ven_documento_respuesta_sri       TEXT,
    ADD COLUMN IF NOT EXISTS ven_documento_base_imponible_iva  NUMERIC(14,4),
    ADD COLUMN IF NOT EXISTS ven_documento_codigo_numerico     VARCHAR(8),
    ADD COLUMN IF NOT EXISTS ven_documento_plazo_pago_dias     INTEGER DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ven_documento_ambiente_sri        CHAR(1);

-- ── ven_forma_pago: código SRI ───────────────────────────────────────────────
ALTER TABLE ven_forma_pago
    ADD COLUMN IF NOT EXISTS ven_forma_pago_codigo_sri VARCHAR(2);

UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '01' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%EFECTIVO%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '19' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%TARJETA%CRÉDITO%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '19' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%TARJETA%CREDITO%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '20' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%TARJETA%DÉBITO%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '20' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%TARJETA%DEBITO%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '16' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%TRANSFERENCIA%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '21' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%CHEQUE%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '18' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%CRÉDITO%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '18' WHERE ven_forma_pago_codigo_sri IS NULL AND upper(ven_forma_pago_nombre) LIKE '%CREDITO%';
UPDATE ven_forma_pago SET ven_forma_pago_codigo_sri = '01' WHERE ven_forma_pago_codigo_sri IS NULL;

-- ── sis_tipo_documento: código SRI ───────────────────────────────────────────
ALTER TABLE sis_tipo_documento
    ADD COLUMN IF NOT EXISTS sis_tipo_documento_codigo_sri VARCHAR(2);

UPDATE sis_tipo_documento
    SET sis_tipo_documento_codigo_sri = '01'
    WHERE sis_tipo_documento_codigo = 'FACTURA_VENTA'
      AND sis_tipo_documento_codigo_sri IS NULL;

-- ── Estados AUTORIZADA y ERROR para VEN_DOCUMENTO ────────────────────────────
INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, sis_estado_descripcion, sis_estado_orden, sis_estado_activo)
SELECT 'VENTAS', 'VEN_DOCUMENTO', 'AUTORIZADA', 'Autorizada', 'Factura autorizada por el SRI', 3, true
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_DOCUMENTO' AND sis_estado_codigo = 'AUTORIZADA'
);

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, sis_estado_descripcion, sis_estado_orden, sis_estado_activo)
SELECT 'VENTAS', 'VEN_DOCUMENTO', 'ERROR', 'Error SRI', 'Error al enviar al SRI', 4, true
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_DOCUMENTO' AND sis_estado_codigo = 'ERROR'
);

-- ── Menú y permisos: acción enviar-sri ───────────────────────────────────────
DO $$
DECLARE v_menu_id BIGINT; v_usu_id BIGINT;
BEGIN
    SELECT sis_usuarios_id INTO v_usu_id FROM sis_usuarios ORDER BY sis_usuarios_id LIMIT 1;

    IF NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/ventas/facturas/enviar-sri') THEN
        INSERT INTO sis_menu (sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_padre, sis_menu_estado, sis_menu_tipo, usuario_crea)
        SELECT 'Enviar al SRI', '/ventas/facturas/enviar-sri', 'bi bi-send', 60, m.sis_menu_id, 1, 'B', v_usu_id
        FROM sis_menu m WHERE m.sis_menu_url = '/ventas/facturas' LIMIT 1
        RETURNING sis_menu_id INTO v_menu_id;

        INSERT INTO sis_perfil_permisos (sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea)
        SELECT e.sis_empresa_id, p.sis_perfil_id, v_menu_id, 1, v_usu_id
        FROM sis_perfil p CROSS JOIN sis_empresa e
        WHERE p.sis_perfil_codigo = 'SUPERUSUARIO';
    END IF;
END $$;
