BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA MENU SISTEMA MENUS Y BOTONES CON ACCIONES INTERNAS                   */
/*                                                                            */
/******************************************************************************/

WITH sistema AS (
    SELECT sis_menu_id
    FROM public.sis_menu
    WHERE sis_menu_url = '/sistema'
    LIMIT 1
),
menu_menus AS (
    INSERT INTO public.sis_menu (
        sis_menu_nombre,
        sis_menu_padre,
        sis_menu_icono,
        sis_menu_url,
        sis_menu_orden,
        sis_menu_estado,
        sis_menu_tipo,
        usuario_crea
    )
    SELECT 'Menus y botones', sis_menu_id, 'bi bi-menu-button-wide', '/sistema/menus', 4, 1, 'M', 1
    FROM sistema
    WHERE NOT EXISTS (
        SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/menus'
    )
    RETURNING sis_menu_id
),
menu_menus_id AS (
    SELECT sis_menu_id FROM menu_menus
    UNION ALL
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema/menus'
    LIMIT 1
)
INSERT INTO public.sis_menu (
    sis_menu_nombre,
    sis_menu_padre,
    sis_menu_icono,
    sis_menu_url,
    sis_menu_orden,
    sis_menu_estado,
    sis_menu_tipo,
    usuario_crea
)
SELECT a.nombre, m.sis_menu_id, a.icono, a.url, a.orden, 1, 'B', 1
FROM menu_menus_id m
CROSS JOIN (
    VALUES
        ('Ver menu', 'bi bi-eye', '/sistema/menus/ver', 1),
        ('Crear menu', 'bi bi-plus-square', '/sistema/menus/crear', 2),
        ('Editar menu', 'bi bi-pencil-square', '/sistema/menus/editar', 3),
        ('Activar menu', 'bi bi-toggle-on', '/sistema/menus/activar', 4),
        ('Inactivar menu', 'bi bi-toggle-off', '/sistema/menus/inactivar', 5)
) AS a(nombre, icono, url, orden)
WHERE NOT EXISTS (
    SELECT 1 FROM public.sis_menu WHERE sis_menu_url = a.url
);

/******************************************************************************/
/*                                                                            */
/*  ASIGNA MENU Y ACCIONES A SUPERUSUARIO                                     */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_perfil_permisos (
    sis_empresa_id,
    sis_perfil_id,
    sis_menu_id,
    sis_perfil_permisos_estado,
    usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM public.sis_perfil p
CROSS JOIN public.sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url LIKE '/sistema/menus%'
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
