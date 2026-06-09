-- =============================================================================
-- MENU COMPRAS + PERMISOS SUPERUSUARIO (schema corregido)
-- sis_menu usa: sis_menu_padre (bigint), sis_menu_estado (smallint), sis_menu_tipo ('M'/'B')
-- permisos en: sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
-- =============================================================================

-- 1. MENU PADRE: Compras
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Compras', NULL, 'bi bi-cart3', '/compras', 30, 1, 'M', 1
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/compras');

-- 2. MENU HIJO: Proveedores
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Proveedores', m.sis_menu_id, 'bi bi-people', '/compras/proveedores', 10, 1, 'M', 1
FROM sis_menu m
WHERE m.sis_menu_url = '/compras'
  AND NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/compras/proveedores');

-- 3. ACCIONES (tipo 'B' = boton)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver proveedores',     '/compras/proveedores/ver',       1),
    ('Crear proveedor',     '/compras/proveedores/crear',     2),
    ('Editar proveedor',    '/compras/proveedores/editar',    3),
    ('Activar proveedor',   '/compras/proveedores/activar',   4),
    ('Inactivar proveedor', '/compras/proveedores/inactivar', 5)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/compras/proveedores'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 4. PERMISOS PARA TODOS LOS PERFILES SUPERUSUARIO
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN (
      '/compras',
      '/compras/proveedores',
      '/compras/proveedores/ver',
      '/compras/proveedores/crear',
      '/compras/proveedores/editar',
      '/compras/proveedores/activar',
      '/compras/proveedores/inactivar'
  )
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );
