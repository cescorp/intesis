BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA MENU PERFILES Y ACCIONES DEL CRUD                                    */
/*                                                                            */
/******************************************************************************/

WITH sistema AS (
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema' LIMIT 1
),
perfil_menu AS (
    INSERT INTO public.sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT 'Perfiles / Roles', sis_menu_id, 'bi bi-person-gear', '/sistema/perfiles', 3, 1, 'M', 1
    FROM sistema
    WHERE NOT EXISTS (
        SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/perfiles'
    )
    RETURNING sis_menu_id
),
perfil_menu_id AS (
    SELECT sis_menu_id FROM perfil_menu
    UNION ALL
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema/perfiles'
    LIMIT 1
)
INSERT INTO public.sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT a.nombre, p.sis_menu_id, a.icono, a.url, a.orden, 1, 'B', 1
FROM perfil_menu_id p
CROSS JOIN (
    VALUES
        ('Ver perfil', 'bi bi-eye', '/sistema/perfiles/ver', 1),
        ('Crear perfil', 'bi bi-person-plus', '/sistema/perfiles/crear', 2),
        ('Editar perfil', 'bi bi-pencil-square', '/sistema/perfiles/editar', 3),
        ('Guardar permisos', 'bi bi-shield-check', '/sistema/perfiles/permisos', 4),
        ('Activar perfil', 'bi bi-toggle-on', '/sistema/perfiles/activar', 5),
        ('Inactivar perfil', 'bi bi-toggle-off', '/sistema/perfiles/inactivar', 6),
        ('Eliminar perfil', 'bi bi-trash3', '/sistema/perfiles/eliminar', 7)
) AS a(nombre, icono, url, orden)
WHERE NOT EXISTS (
    SELECT 1 FROM public.sis_menu WHERE sis_menu_url = a.url
);

/******************************************************************************/
/*                                                                            */
/*  ASIGNA PERMISOS DE PERFILES A SUPERUSUARIO Y VISTA A GERENCIA CONTADOR    */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM public.sis_perfil p
CROSS JOIN public.sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url LIKE '/sistema/perfiles%'
  AND NOT EXISTS (
      SELECT 1 FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

INSERT INTO public.sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM public.sis_perfil p
CROSS JOIN public.sis_menu m
WHERE p.sis_perfil_codigo IN ('GERENCIA', 'CONTADOR')
  AND m.sis_menu_url IN ('/sistema/perfiles', '/sistema/perfiles/ver')
  AND NOT EXISTS (
      SELECT 1 FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
