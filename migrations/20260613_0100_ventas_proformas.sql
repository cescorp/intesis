-- =============================================================================
-- MODULO VENTAS: PROFORMAS
-- Tablas: ven_documento, ven_documento_detalle
-- Estados: CREADA, FACTURADA, ANULADA
-- Tipos de documento: PROFORMA, FACTURA_VENTA
-- Menu: Ventas > Proformas
-- =============================================================================

-- 1. TIPOS DE DOCUMENTO
INSERT INTO sis_tipo_documento (
    sis_tipo_documento_codigo, sis_tipo_documento_nombre,
    sis_tipo_documento_modulo, sis_tipo_documento_afecta_inventario,
    sis_tipo_documento_afecta_contabilidad, sis_estado_id, usuario_crea
)
SELECT cod, nom, 'VENTAS', false, false,
       (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO' AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
       1
FROM (VALUES
    ('PROFORMA',      'Proforma'),
    ('FACTURA_VENTA', 'Factura de Venta')
) AS t(cod, nom)
WHERE NOT EXISTS (
    SELECT 1 FROM sis_tipo_documento
    WHERE sis_tipo_documento_modulo = 'VENTAS' AND sis_tipo_documento_codigo = t.cod
);

-- 2. ESTADOS PARA VEN_DOCUMENTO
INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'VENTAS', 'VEN_DOCUMENTO', 'CREADA', 'Creada', 1
WHERE NOT EXISTS (SELECT 1 FROM sis_estado WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_DOCUMENTO' AND sis_estado_codigo = 'CREADA');

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'VENTAS', 'VEN_DOCUMENTO', 'FACTURADA', 'Facturada', 1
WHERE NOT EXISTS (SELECT 1 FROM sis_estado WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_DOCUMENTO' AND sis_estado_codigo = 'FACTURADA');

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'VENTAS', 'VEN_DOCUMENTO', 'ANULADA', 'Anulada', 1
WHERE NOT EXISTS (SELECT 1 FROM sis_estado WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_DOCUMENTO' AND sis_estado_codigo = 'ANULADA');

-- 3. TABLA ven_documento
CREATE TABLE IF NOT EXISTS ven_documento (
    ven_documento_id            SERIAL        PRIMARY KEY,
    sis_empresa_id              INTEGER       NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    ven_cliente_id              INTEGER       NOT NULL REFERENCES ven_cliente(ven_cliente_id),
    sis_tipo_documento_id       INTEGER       NOT NULL REFERENCES sis_tipo_documento(sis_tipo_documento_id),
    ven_documento_numero        VARCHAR(40)   NOT NULL,
    ven_documento_fecha_emision DATE,
    ven_documento_subtotal      NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_descuento     NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_iva           NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_total         NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_observacion   TEXT,
    ven_documento_proforma_id   INTEGER       REFERENCES ven_documento(ven_documento_id),
    sis_estado_id               INTEGER       NOT NULL REFERENCES sis_estado(sis_estado_id),
    usuario_crea                INTEGER       NOT NULL,
    fecha_crea                  TIMESTAMP     NOT NULL DEFAULT now(),
    usuario_modifica            INTEGER,
    fecha_modifica              TIMESTAMP
);

-- Agregar columnas si la tabla ya existía antes de esta migración
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS ven_documento_fecha_emision  DATE;
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS ven_documento_subtotal       NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS ven_documento_descuento      NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS ven_documento_iva            NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS ven_documento_total          NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS ven_documento_observacion    TEXT;
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS ven_documento_proforma_id    INTEGER REFERENCES ven_documento(ven_documento_id);
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS usuario_modifica             INTEGER;
ALTER TABLE ven_documento ADD COLUMN IF NOT EXISTS fecha_modifica               TIMESTAMP;

COMMENT ON TABLE  ven_documento                              IS 'DOCUMENTOS DE VENTA (PROFORMAS, FACTURAS) POR EMPRESA';
COMMENT ON COLUMN ven_documento.ven_documento_proforma_id   IS 'REFERENCIA A LA PROFORMA ORIGEN CUANDO EL DOCUMENTO ES UNA FACTURA';

-- 4. TABLA ven_documento_detalle
CREATE TABLE IF NOT EXISTS ven_documento_detalle (
    ven_documento_detalle_id            SERIAL        PRIMARY KEY,
    sis_empresa_id                      INTEGER       NOT NULL,
    ven_documento_id                    INTEGER       NOT NULL REFERENCES ven_documento(ven_documento_id),
    inv_producto_id                     INTEGER       REFERENCES inv_producto(inv_producto_id),
    ven_documento_detalle_codigo        VARCHAR(100),
    ven_documento_detalle_descripcion   TEXT,
    ven_documento_detalle_cantidad      NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_detalle_precio        NUMERIC(16,6) NOT NULL DEFAULT 0,
    ven_documento_detalle_descuento     NUMERIC(5,2)  NOT NULL DEFAULT 0,
    ven_documento_detalle_descuento_val NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_detalle_total         NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_detalle_marca_iva     CHAR(1)       NOT NULL DEFAULT 'N',
    sis_iva_id                          INTEGER       REFERENCES sis_iva(sis_iva_id),
    ven_documento_detalle_iva_valor     NUMERIC(16,4) NOT NULL DEFAULT 0,
    ven_documento_detalle_pvp           NUMERIC(16,6) NOT NULL DEFAULT 0,
    ven_documento_detalle_precio_min    NUMERIC(16,6) NOT NULL DEFAULT 0,
    ven_documento_detalle_aprobado_por  INTEGER,
    ven_documento_detalle_proforma_linea_id INTEGER   REFERENCES ven_documento_detalle(ven_documento_detalle_id),
    ven_documento_detalle_estado        SMALLINT      NOT NULL DEFAULT 1,
    usuario_crea                        INTEGER       NOT NULL,
    fecha_crea                          TIMESTAMP     NOT NULL DEFAULT now(),
    usuario_modifica                    INTEGER,
    fecha_modifica                      TIMESTAMP
);

-- Agregar columnas si la tabla ya existía antes de esta migración
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_codigo        VARCHAR(100);
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_descripcion   TEXT;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_descuento     NUMERIC(5,2)  NOT NULL DEFAULT 0;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_descuento_val NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_marca_iva     CHAR(1)       NOT NULL DEFAULT 'N';
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS sis_iva_id                          INTEGER REFERENCES sis_iva(sis_iva_id);
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_iva_valor     NUMERIC(16,4) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_pvp           NUMERIC(16,6) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_precio_min    NUMERIC(16,6) NOT NULL DEFAULT 0;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_aprobado_por  INTEGER;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_proforma_linea_id INTEGER REFERENCES ven_documento_detalle(ven_documento_detalle_id);
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS ven_documento_detalle_estado        SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS usuario_modifica                    INTEGER;
ALTER TABLE ven_documento_detalle ADD COLUMN IF NOT EXISTS fecha_modifica                      TIMESTAMP;

COMMENT ON TABLE  ven_documento_detalle                                     IS 'LINEAS DE DETALLE DE DOCUMENTOS DE VENTA';
COMMENT ON COLUMN ven_documento_detalle.ven_documento_detalle_precio_min    IS 'PRECIO MINIMO AL MOMENTO DE LA PROFORMA (PVP * (1 - DESC_MAX/100))';
COMMENT ON COLUMN ven_documento_detalle.ven_documento_detalle_aprobado_por  IS 'ID DE USUARIO QUE APROBO PRECIO POR DEBAJO DEL MINIMO';
COMMENT ON COLUMN ven_documento_detalle.ven_documento_detalle_proforma_linea_id IS 'LINEA DE PROFORMA ORIGEN CUANDO ES UNA LINEA DE FACTURA';

-- 5. INDICES
CREATE INDEX IF NOT EXISTS idx_ven_documento_empresa     ON ven_documento(sis_empresa_id);
CREATE INDEX IF NOT EXISTS idx_ven_documento_cliente     ON ven_documento(ven_cliente_id);
CREATE INDEX IF NOT EXISTS idx_ven_documento_estado      ON ven_documento(sis_estado_id);
CREATE INDEX IF NOT EXISTS idx_ven_documento_proforma    ON ven_documento(ven_documento_proforma_id);
CREATE INDEX IF NOT EXISTS idx_ven_det_documento         ON ven_documento_detalle(ven_documento_id);
CREATE INDEX IF NOT EXISTS idx_ven_det_producto          ON ven_documento_detalle(inv_producto_id);

-- 6. MENU HIJO: Proformas (bajo /ventas que ya existe)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Proformas', m.sis_menu_id, 'bi bi-file-earmark-ruled', '/ventas/proformas', 20, 1, 'M', 1
FROM sis_menu m
WHERE m.sis_menu_url = '/ventas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/ventas/proformas');

-- 7. ACCIONES (tipo 'B')
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver proformas',        '/ventas/proformas/ver',              1),
    ('Crear proforma',       '/ventas/proformas/crear',            2),
    ('Editar proforma',      '/ventas/proformas/editar',           3),
    ('Anular proforma',      '/ventas/proformas/anular',           4),
    ('Facturar proforma',    '/ventas/proformas/facturar',         5),
    ('Aprobar precio',       '/ventas/proformas/aprobar-precio',   6)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/ventas/proformas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 8. PERMISOS PARA SUPERUSUARIO
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN (
      '/ventas',
      '/ventas/proformas',
      '/ventas/proformas/ver',
      '/ventas/proformas/crear',
      '/ventas/proformas/editar',
      '/ventas/proformas/anular',
      '/ventas/proformas/facturar',
      '/ventas/proformas/aprobar-precio'
  )
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );

-- 9. MENSAJES DE CONFIRMACION
INSERT INTO sis_mensaje_errores (sis_mensaje_errores_codigo, sis_mensaje_errores_tipo, sis_mensaje_errores_titulo, sis_mensaje_errores_mensaje, sis_mensaje_errores_icono, sis_mensaje_errores_modulo, sis_mensaje_errores_entidad, usuario_crea)
VALUES ('CONFIRMAR_ANULAR_PROFORMA', 'CONFIRMACION', 'Anular proforma', '¿Desea anular esta proforma? Esta acción no se puede deshacer.', 'warning', 'VENTAS', 'VEN_DOCUMENTO', 1)
ON CONFLICT (sis_mensaje_errores_codigo) DO NOTHING;

INSERT INTO sis_mensaje_errores (sis_mensaje_errores_codigo, sis_mensaje_errores_tipo, sis_mensaje_errores_titulo, sis_mensaje_errores_mensaje, sis_mensaje_errores_icono, sis_mensaje_errores_modulo, sis_mensaje_errores_entidad, usuario_crea)
VALUES ('CONFIRMAR_FACTURAR_PROFORMA', 'CONFIRMACION', 'Facturar proforma', '¿Confirma la facturación de la proforma con las cantidades indicadas?', 'question', 'VENTAS', 'VEN_DOCUMENTO', 1)
ON CONFLICT (sis_mensaje_errores_codigo) DO NOTHING;
