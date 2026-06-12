-- ============================================================
-- Migración: 20260612_0100
-- Agrega acciones de "Códigos de Proveedor" como hijos de /compras/proveedores
-- NO crea entrada visible en el menú lateral (acceso solo por botón en Proveedores)
-- ============================================================

-- 1. Acciones (hijas de /compras/proveedores, tipo 'B' — no aparecen en sidebar)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver códigos proveedor',       '/compras/codigos-proveedor/ver',       10),
    ('Crear código proveedor',      '/compras/codigos-proveedor/crear',     11),
    ('Editar código proveedor',     '/compras/codigos-proveedor/editar',    12),
    ('Inactivar código proveedor',  '/compras/codigos-proveedor/inactivar', 13),
    ('Eliminar código proveedor',   '/compras/codigos-proveedor/eliminar',  14)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/compras/proveedores'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 2. Permisos para superusuario
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN (
      '/compras/codigos-proveedor/ver',
      '/compras/codigos-proveedor/crear',
      '/compras/codigos-proveedor/editar',
      '/compras/codigos-proveedor/inactivar',
      '/compras/codigos-proveedor/eliminar'
  )
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );
