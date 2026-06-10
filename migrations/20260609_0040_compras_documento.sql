-- =============================================================================
-- MODULO COMPRAS: DOCUMENTO DE COMPRA (cabecera + detalle)
-- =============================================================================

-- 1. COLUMNAS NUEVAS EN com_documento_detalle
ALTER TABLE com_documento_detalle
    ADD COLUMN IF NOT EXISTS sis_iva_id BIGINT REFERENCES sis_iva(sis_iva_id),
    ADD COLUMN IF NOT EXISTS com_documento_detalle_pvp NUMERIC(14,2) NOT NULL DEFAULT 0;

-- 2. TIPOS DOCUMENTO COMPRAS
INSERT INTO sis_tipo_documento (
    sis_tipo_documento_codigo, sis_tipo_documento_nombre,
    sis_tipo_documento_modulo, sis_tipo_documento_afecta_inventario,
    sis_tipo_documento_afecta_contabilidad, sis_estado_id, usuario_crea
)
SELECT cod, nom, 'COMPRAS', afecta_inv, false, 50, 1
FROM (VALUES
    ('FACTURA_COMPRA',    'Factura de Compra',      true),
    ('GASTO',            'Gasto',                   false),
    ('NOTA_CRED_COMPRA', 'Nota de Credito Compra',  true)
) AS t(cod, nom, afecta_inv)
WHERE NOT EXISTS (
    SELECT 1 FROM sis_tipo_documento
    WHERE sis_tipo_documento_modulo = 'COMPRAS' AND sis_tipo_documento_codigo = t.cod
);

-- 3. ESTADOS COM_DOCUMENTO (ya existen pero verificamos)
INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'COMPRAS', 'COM_DOCUMENTO', cod, nom, 1
FROM (VALUES
    ('BORRADOR',    'Borrador'),
    ('REGISTRADO',  'Registrado'),
    ('ANULADO',     'Anulado')
) AS t(cod, nom)
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'COMPRAS'
      AND sis_estado_entidad = 'COM_DOCUMENTO'
      AND sis_estado_codigo = t.cod
);

-- 4. MENU DOCUMENTOS DE COMPRA
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Documentos', m.sis_menu_id, 'bi bi-receipt', '/compras/documentos', 20, 1, 'M', 1
FROM sis_menu m
WHERE m.sis_menu_url = '/compras'
  AND NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/compras/documentos');

-- 5. ACCIONES DOCUMENTOS
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver documentos',        '/compras/documentos/ver',        1),
    ('Crear documento',       '/compras/documentos/crear',      2),
    ('Registrar documento',   '/compras/documentos/registrar',  3),
    ('Anular documento',      '/compras/documentos/anular',     4)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/compras/documentos'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 6. PERMISOS SUPERUSUARIO
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN (
      '/compras/documentos',
      '/compras/documentos/ver',
      '/compras/documentos/crear',
      '/compras/documentos/registrar',
      '/compras/documentos/anular'
  )
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );
