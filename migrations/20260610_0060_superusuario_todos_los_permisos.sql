-- =============================================================================
-- SINCRONIZA TODOS LOS PERMISOS DE MENU AL PERFIL SUPERUSUARIO EN TODAS LAS EMPRESAS
-- EJECUTAR CADA VEZ QUE SE AGREGUEN NUEVOS MENUS Y EL SUPERUSUARIO NO LOS TENGA
-- =============================================================================

INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_estado = 1
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );
