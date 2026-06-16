-- =============================================================================
-- MODULO VENTAS: FACTURAS (CRUD DIRECTO, SIN PROFORMA)
-- Estados: CREADA / ANULADA / AUTORIZADA / ERROR
-- Menu: Ventas > Facturas
-- Tabla cxc: ven_cxc
-- =============================================================================

-- 1. ESTADOS PARA VEN_DOCUMENTO (solo inserta los que no existen)
INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'VENTAS', 'VEN_DOCUMENTO', cod, nom, 1
FROM (VALUES
    ('CREADA',      'Creada'),
    ('ANULADA',     'Anulada'),
    ('AUTORIZADA',  'Autorizada'),
    ('ERROR',       'Error')
) AS t(cod, nom)
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo   = 'VENTAS'
      AND sis_estado_entidad  = 'VEN_DOCUMENTO'
      AND sis_estado_codigo   = t.cod
);

-- 2. TIPO DE DOCUMENTO FACTURA_VENTA (idempotente)
INSERT INTO sis_tipo_documento (
    sis_tipo_documento_codigo, sis_tipo_documento_nombre,
    sis_tipo_documento_modulo, sis_tipo_documento_afecta_inventario,
    sis_tipo_documento_afecta_contabilidad, sis_estado_id, usuario_crea
)
SELECT 'FACTURA_VENTA', 'Factura de Venta', 'VENTAS', false, false,
       (SELECT sis_estado_id FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
          AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
       1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_tipo_documento
    WHERE sis_tipo_documento_codigo = 'FACTURA_VENTA' AND sis_tipo_documento_modulo = 'VENTAS'
);

-- 3. COLUMNA ven_forma_pago_calculadora EN ven_forma_pago
ALTER TABLE ven_forma_pago
    ADD COLUMN IF NOT EXISTS ven_forma_pago_calculadora CHAR(1) NOT NULL DEFAULT 'N';

COMMENT ON COLUMN ven_forma_pago.ven_forma_pago_calculadora IS 'S=Mostrar calculadora de cambio al facturar, N=No mostrar';

-- Activar calculadora para EFECTIVO
UPDATE ven_forma_pago SET ven_forma_pago_calculadora = 'S'
WHERE ven_forma_pago_nombre = 'EFECTIVO';

-- 4. TABLA CUENTAS POR COBRAR
CREATE TABLE IF NOT EXISTS ven_cxc (
    ven_cxc_id              SERIAL          PRIMARY KEY,
    sis_empresa_id          INTEGER         NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    ven_documento_id        INTEGER         NOT NULL REFERENCES ven_documento(ven_documento_id),
    ven_cliente_id          INTEGER         NOT NULL REFERENCES ven_cliente(ven_cliente_id),
    ven_cxc_monto           NUMERIC(16,4)   NOT NULL DEFAULT 0,
    ven_cxc_saldo           NUMERIC(16,4)   NOT NULL DEFAULT 0,
    ven_cxc_fecha_emision   DATE            NOT NULL DEFAULT CURRENT_DATE,
    ven_cxc_fecha_vencimiento DATE,
    ven_cxc_estado          CHAR(1)         NOT NULL DEFAULT 'P',
    usuario_crea            INTEGER         NOT NULL,
    fecha_crea              TIMESTAMP       NOT NULL DEFAULT now(),
    usuario_modifica        INTEGER,
    fecha_modifica          TIMESTAMP
);

COMMENT ON TABLE  ven_cxc                      IS 'CUENTAS POR COBRAR GENERADAS POR FACTURAS DE VENTA';
COMMENT ON COLUMN ven_cxc.ven_cxc_estado       IS 'P=Pendiente, C=Cobrada, A=Anulada';
COMMENT ON COLUMN ven_cxc.ven_cxc_saldo        IS 'Saldo pendiente de cobro (se reduce con abonos)';

CREATE INDEX IF NOT EXISTS idx_ven_cxc_documento ON ven_cxc(ven_documento_id);
CREATE INDEX IF NOT EXISTS idx_ven_cxc_cliente   ON ven_cxc(ven_cliente_id);
CREATE INDEX IF NOT EXISTS idx_ven_cxc_empresa   ON ven_cxc(sis_empresa_id);

-- 5. MENU HIJO: Facturas (bajo /ventas)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Facturas', m.sis_menu_id, 'bi bi-receipt', '/ventas/facturas', 25, 1, 'M', 1
FROM sis_menu m
WHERE m.sis_menu_url = '/ventas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/ventas/facturas');

-- 6. ACCIONES DEL MENU FACTURAS
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver facturas',     '/ventas/facturas/ver',     1),
    ('Crear factura',    '/ventas/facturas/crear',   2),
    ('Editar factura',   '/ventas/facturas/editar',  3),
    ('Anular factura',   '/ventas/facturas/anular',  4)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/ventas/facturas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 7. PERMISOS PARA SUPERUSUARIO
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN (
      '/ventas/facturas',
      '/ventas/facturas/ver',
      '/ventas/facturas/crear',
      '/ventas/facturas/editar',
      '/ventas/facturas/anular'
  )
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id = sm.sis_menu_id
  );
