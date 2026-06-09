-- =============================================================================
-- MODULO COMPRAS: TABLA PROVEEDORES
-- =============================================================================

-- 1. ESTADOS PARA COM_PROVEEDOR
INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'COMPRAS', 'COM_PROVEEDOR', 'ACTIVO',   'Activo',   1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'COMPRAS' AND sis_estado_entidad = 'COM_PROVEEDOR' AND sis_estado_codigo = 'ACTIVO'
);

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'COMPRAS', 'COM_PROVEEDOR', 'INACTIVO', 'Inactivo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'COMPRAS' AND sis_estado_entidad = 'COM_PROVEEDOR' AND sis_estado_codigo = 'INACTIVO'
);

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'COMPRAS', 'COM_PROVEEDOR', 'ELIMINADO', 'Eliminado', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'COMPRAS' AND sis_estado_entidad = 'COM_PROVEEDOR' AND sis_estado_codigo = 'ELIMINADO'
);

-- 2. TABLA PRINCIPAL
CREATE TABLE IF NOT EXISTS com_proveedor (
    com_proveedor_id                    SERIAL PRIMARY KEY,
    sis_empresa_id                      INTEGER NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    com_proveedor_tipo_identificacion   VARCHAR(20)  NOT NULL,
    com_proveedor_identificacion        VARCHAR(20)  NOT NULL,
    com_proveedor_razon_social          VARCHAR(200) NOT NULL,
    com_proveedor_telefono              VARCHAR(20)  NULL,
    com_proveedor_celular               VARCHAR(20)  NULL,
    com_proveedor_correo                VARCHAR(150) NULL,
    com_proveedor_direccion             VARCHAR(300) NULL,
    sis_estado_id                       INTEGER NOT NULL REFERENCES sis_estado(sis_estado_id),
    usuario_crea                        INTEGER NOT NULL,
    fecha_crea                          TIMESTAMP NOT NULL DEFAULT now(),
    usuario_modifica                    INTEGER NULL,
    fecha_modifica                      TIMESTAMP NULL,
    CONSTRAINT uq_proveedor_identificacion UNIQUE (sis_empresa_id, com_proveedor_tipo_identificacion, com_proveedor_identificacion)
);

-- 3. MENU COMPRAS (padre)
INSERT INTO sis_menu (sis_menu_padre_id, sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_tipo, sis_estado_id, usuario_crea)
SELECT NULL, 'Compras', '/compras', 'bi bi-cart3', 30, 'MODULO',
       (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_MENU' AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
       1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_menu WHERE sis_menu_url = '/compras'
);

-- 4. MENU PROVEEDORES (hijo)
INSERT INTO sis_menu (sis_menu_padre_id, sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_tipo, sis_estado_id, usuario_crea)
SELECT m.sis_menu_id, 'Proveedores', '/compras/proveedores', 'bi bi-people', 10, 'OPCION',
       (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_MENU' AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
       1
FROM sis_menu m
WHERE m.sis_menu_url = '/compras'
  AND NOT EXISTS (
      SELECT 1 FROM sis_menu WHERE sis_menu_url = '/compras/proveedores'
  );

-- 5. SUB-ACCIONES DE PROVEEDORES
INSERT INTO sis_menu (sis_menu_padre_id, sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_tipo, sis_estado_id, usuario_crea)
SELECT m.sis_menu_id, nombre, url, 'bi bi-dot', orden,
       'ACCION',
       (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_MENU' AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
       1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver proveedores',       '/compras/proveedores/ver',       1),
    ('Crear proveedor',       '/compras/proveedores/crear',     2),
    ('Editar proveedor',      '/compras/proveedores/editar',    3),
    ('Activar proveedor',     '/compras/proveedores/activar',   4),
    ('Inactivar proveedor',   '/compras/proveedores/inactivar', 5)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/compras/proveedores'
  AND NOT EXISTS (
      SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url
  );

-- 6. PERMISOS SUPERUSUARIO (perfil_id = 1 — ajustar si difiere)
INSERT INTO sis_perfil_menu (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT 1, e.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_menu sm
CROSS JOIN sis_empresa e
WHERE sm.sis_menu_url IN (
    '/compras',
    '/compras/proveedores',
    '/compras/proveedores/ver',
    '/compras/proveedores/crear',
    '/compras/proveedores/editar',
    '/compras/proveedores/activar',
    '/compras/proveedores/inactivar'
)
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_menu pm
      WHERE pm.sis_perfil_id = 1
        AND pm.sis_empresa_id = e.sis_empresa_id
        AND pm.sis_menu_id = sm.sis_menu_id
  );
