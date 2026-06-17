-- =============================================================================
-- MENÚ Y ACCIONES: Sistema > Configuración > Licencia
-- =============================================================================

DO $$
DECLARE
    v_menu_lic   BIGINT;
    v_usu_id     BIGINT;
BEGIN
    SELECT sis_usuarios_id INTO v_usu_id FROM sis_usuarios ORDER BY sis_usuarios_id LIMIT 1;

    -- Menú de 3er nivel: Licencia
    IF NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/sistema/configuracion/licencia') THEN
        INSERT INTO sis_menu (sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_padre, sis_menu_estado, sis_menu_tipo, usuario_crea)
        VALUES ('Licencia', '/sistema/configuracion/licencia', 'bi bi-patch-check', 4, 36, 1, 'M', v_usu_id)
        RETURNING sis_menu_id INTO v_menu_lic;
    ELSE
        SELECT sis_menu_id INTO v_menu_lic FROM sis_menu WHERE sis_menu_url = '/sistema/configuracion/licencia';
    END IF;

    -- Acción: Ver
    IF NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/sistema/configuracion/licencia/ver') THEN
        INSERT INTO sis_menu (sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_padre, sis_menu_estado, sis_menu_tipo, usuario_crea)
        VALUES ('Ver licencia', '/sistema/configuracion/licencia/ver', 'bi bi-eye', 1, v_menu_lic, 1, 'B', v_usu_id);
    END IF;

    -- Acción: Activar (subir .json)
    IF NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/sistema/configuracion/licencia/activar') THEN
        INSERT INTO sis_menu (sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_padre, sis_menu_estado, sis_menu_tipo, usuario_crea)
        VALUES ('Activar licencia', '/sistema/configuracion/licencia/activar', 'bi bi-upload', 2, v_menu_lic, 1, 'B', v_usu_id);
    END IF;

    -- Acción: Generar (solo superusuario)
    IF NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/sistema/configuracion/licencia/generar') THEN
        INSERT INTO sis_menu (sis_menu_nombre, sis_menu_url, sis_menu_icono, sis_menu_orden, sis_menu_padre, sis_menu_estado, sis_menu_tipo, usuario_crea)
        VALUES ('Generar licencia', '/sistema/configuracion/licencia/generar', 'bi bi-file-earmark-arrow-down', 3, v_menu_lic, 1, 'B', v_usu_id);
    END IF;

    -- Permisos: SUPERUSUARIO en todas las empresas
    INSERT INTO sis_perfil_permisos (sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea)
    SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, v_usu_id
    FROM sis_perfil p
    CROSS JOIN sis_menu m
    WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
      AND m.sis_menu_url IN (
          '/sistema/configuracion/licencia',
          '/sistema/configuracion/licencia/ver',
          '/sistema/configuracion/licencia/activar',
          '/sistema/configuracion/licencia/generar'
      )
      AND NOT EXISTS (
          SELECT 1 FROM sis_perfil_permisos pp
          WHERE pp.sis_empresa_id = p.sis_empresa_id
            AND pp.sis_perfil_id  = p.sis_perfil_id
            AND pp.sis_menu_id    = m.sis_menu_id
      );
END $$;
