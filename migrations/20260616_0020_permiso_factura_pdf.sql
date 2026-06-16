-- Accion PDF para facturas/notas de venta
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'PDF Factura', m.sis_menu_id, 'bi bi-dot', '/ventas/facturas/pdf', 7, 1, 'B', 1
FROM sis_menu m
WHERE m.sis_menu_url = '/ventas/facturas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/ventas/facturas/pdf');

-- Permiso al superusuario
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url = '/ventas/facturas/pdf'
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );
