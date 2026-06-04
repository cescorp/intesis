BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA MENU SISTEMA, EMPRESAS Y ACCIONES DEL CRUD                           */
/*                                                                            */
/******************************************************************************/

WITH menu_sistema AS (
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
    SELECT 'Sistema', NULL, 'bi bi-sliders', '/sistema', 10, 1, 'M', 1
    WHERE NOT EXISTS (
        SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema' AND sis_menu_tipo = 'M'
    )
    RETURNING sis_menu_id
),
menu_sistema_id AS (
    SELECT sis_menu_id FROM menu_sistema
    UNION ALL
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema' AND sis_menu_tipo = 'M'
    LIMIT 1
),
menu_empresas AS (
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
    SELECT 'Empresas', sis_menu_id, 'bi bi-buildings', '/sistema/empresas', 1, 1, 'M', 1
    FROM menu_sistema_id
    WHERE NOT EXISTS (
        SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/empresas' AND sis_menu_tipo = 'M'
    )
    RETURNING sis_menu_id
),
menu_empresas_id AS (
    SELECT sis_menu_id FROM menu_empresas
    UNION ALL
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema/empresas' AND sis_menu_tipo = 'M'
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
SELECT accion.nombre, e.sis_menu_id, accion.icono, accion.url, accion.orden, 1, 'B', 1
FROM menu_empresas_id e
CROSS JOIN (
    VALUES
        ('Ver empresa', 'bi bi-eye', '/sistema/empresas/ver', 1),
        ('Crear empresa', 'bi bi-building-add', '/sistema/empresas/crear', 2),
        ('Editar empresa', 'bi bi-pencil-square', '/sistema/empresas/editar', 3),
        ('Activar empresa', 'bi bi-toggle-on', '/sistema/empresas/activar', 4),
        ('Inactivar empresa', 'bi bi-toggle-off', '/sistema/empresas/inactivar', 5),
        ('Eliminar empresa', 'bi bi-trash3', '/sistema/empresas/eliminar', 6)
) AS accion(nombre, icono, url, orden)
WHERE NOT EXISTS (
    SELECT 1 FROM public.sis_menu m WHERE m.sis_menu_url = accion.url AND m.sis_menu_tipo = 'B'
);

/******************************************************************************/
/*                                                                            */
/*  ASIGNA PERMISOS A PERFILES PERTINENTES                                    */
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
  AND m.sis_menu_url IN (
      '/sistema',
      '/sistema/empresas',
      '/sistema/empresas/ver',
      '/sistema/empresas/crear',
      '/sistema/empresas/editar',
      '/sistema/empresas/activar',
      '/sistema/empresas/inactivar',
      '/sistema/empresas/eliminar'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

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
WHERE p.sis_perfil_codigo IN ('GERENCIA', 'CONTADOR')
  AND m.sis_menu_url IN ('/sistema', '/sistema/empresas', '/sistema/empresas/ver')
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
