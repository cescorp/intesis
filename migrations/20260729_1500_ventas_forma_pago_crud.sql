-- ============================================================
-- Migración: 20260729_1500
-- Agrega permisos granulares para el mini-CRUD de "Formas de pago"
-- (accesible solo desde el botón "+" en Nueva Factura, sin entrada
-- propia en el menú lateral, igual que Códigos de Proveedor)
-- ============================================================

-- 1. ACCIONES (hijas de /ventas/facturas, tipo 'B' — no aparecen en sidebar)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Crear forma de pago',     '/ventas/formas-pago/crear',     30),
    ('Editar forma de pago',    '/ventas/formas-pago/editar',    31),
    ('Inactivar forma de pago', '/ventas/formas-pago/inactivar', 32),
    ('Eliminar forma de pago',  '/ventas/formas-pago/eliminar',  33)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/ventas/facturas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 2. PERMISOS PARA SUPERUSUARIO
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN (
      '/ventas/formas-pago/crear',
      '/ventas/formas-pago/editar',
      '/ventas/formas-pago/inactivar',
      '/ventas/formas-pago/eliminar'
  )
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );
