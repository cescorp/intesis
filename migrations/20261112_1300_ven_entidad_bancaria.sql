-- =============================================================================
-- VENTAS: ENTIDADES BANCARIAS (catalogo por empresa) usadas en formas de pago
-- =============================================================================

CREATE TABLE IF NOT EXISTS ven_entidad_bancaria (
    ven_entidad_bancaria_id      SERIAL       PRIMARY KEY,
    sis_empresa_id               INTEGER      NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    ven_entidad_bancaria_nombre  VARCHAR(100) NOT NULL,
    ven_entidad_bancaria_estado  CHAR(1)      NOT NULL DEFAULT 'A' CHECK (ven_entidad_bancaria_estado IN ('A', 'I')),
    usuario_crea                 INTEGER      NOT NULL,
    fecha_crea                   TIMESTAMP    NOT NULL DEFAULT now(),
    usuario_modifica             INTEGER,
    fecha_modifica               TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_ven_entidad_bancaria_nombre
    ON ven_entidad_bancaria (sis_empresa_id, upper(ven_entidad_bancaria_nombre));

-- La forma de pago indica si pide entidad bancaria al facturar
ALTER TABLE ven_forma_pago
    ADD COLUMN IF NOT EXISTS ven_forma_pago_usa_entidad_bancaria CHAR(1) NOT NULL DEFAULT 'N';
UPDATE ven_forma_pago SET ven_forma_pago_usa_entidad_bancaria = 'S'
 WHERE ven_forma_pago_tipo IN ('TRANSFERENCIA', 'DEPOSITO', 'TARJETA_CREDITO', 'TARJETA_DEBITO');

-- Banco usado en cada forma de pago del documento
ALTER TABLE ven_documento_forma_pago
    ADD COLUMN IF NOT EXISTS ven_entidad_bancaria_id INTEGER NULL REFERENCES ven_entidad_bancaria(ven_entidad_bancaria_id);

-- Bancos principales de Ecuador para cada empresa existente
INSERT INTO ven_entidad_bancaria (sis_empresa_id, ven_entidad_bancaria_nombre, usuario_crea)
SELECT e.sis_empresa_id, b.nombre, 1
FROM sis_empresa e
CROSS JOIN (VALUES
    ('BANCO PICHINCHA'), ('BANCO GUAYAQUIL'), ('PRODUBANCO'), ('BANCO DEL PACIFICO'),
    ('BANCO INTERNACIONAL'), ('BANCO BOLIVARIANO'), ('BANCO DEL AUSTRO'), ('COOPERATIVA JEP')
) AS b(nombre)
ON CONFLICT DO NOTHING;

-- Permisos del mini-CRUD (hijos de /ventas/facturas, tipo 'B', sin entrada en sidebar)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Crear entidad bancaria',     '/ventas/entidades-bancarias/crear',     34),
    ('Editar entidad bancaria',    '/ventas/entidades-bancarias/editar',    35),
    ('Inactivar entidad bancaria', '/ventas/entidades-bancarias/inactivar', 36),
    ('Eliminar entidad bancaria',  '/ventas/entidades-bancarias/eliminar',  37)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/ventas/facturas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url LIKE '/ventas/entidades-bancarias/%'
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id = p.sis_perfil_id AND pp.sis_empresa_id = p.sis_empresa_id AND pp.sis_menu_id = sm.sis_menu_id
  );
