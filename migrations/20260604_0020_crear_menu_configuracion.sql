BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA MENU CONFIGURACION Y ACCIONES DE ESTADOS Y MENSAJES                  */
/*                                                                            */
/******************************************************************************/

WITH sistema AS (
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema' LIMIT 1
),
configuracion AS (
    INSERT INTO public.sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT 'Configuración', sis_menu_id, 'bi bi-gear-wide-connected', '/sistema/configuracion', 5, 1, 'M', 1
    FROM sistema
    WHERE NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion')
    RETURNING sis_menu_id
),
configuracion_id AS (
    SELECT sis_menu_id FROM configuracion
    UNION ALL
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema/configuracion'
    LIMIT 1
)
INSERT INTO public.sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT a.nombre, c.sis_menu_id, a.icono, a.url, a.orden, 1, 'B', 1
FROM configuracion_id c
CROSS JOIN (
    VALUES
        ('Ver configuración', 'bi bi-eye', '/sistema/configuracion/ver', 1),
        ('Crear estado', 'bi bi-plus-square', '/sistema/configuracion/estados/crear', 2),
        ('Editar estado', 'bi bi-pencil-square', '/sistema/configuracion/estados/editar', 3),
        ('Activar estado', 'bi bi-toggle-on', '/sistema/configuracion/estados/activar', 4),
        ('Inactivar estado', 'bi bi-toggle-off', '/sistema/configuracion/estados/inactivar', 5),
        ('Crear mensaje error', 'bi bi-plus-square', '/sistema/configuracion/mensajes/crear', 6),
        ('Editar mensaje error', 'bi bi-pencil-square', '/sistema/configuracion/mensajes/editar', 7),
        ('Activar mensaje error', 'bi bi-toggle-on', '/sistema/configuracion/mensajes/activar', 8),
        ('Inactivar mensaje error', 'bi bi-toggle-off', '/sistema/configuracion/mensajes/inactivar', 9)
) AS a(nombre, icono, url, orden)
WHERE NOT EXISTS (SELECT 1 FROM public.sis_menu WHERE sis_menu_url = a.url);

/******************************************************************************/
/*                                                                            */
/*  ASIGNA CONFIGURACION A SUPERUSUARIO                                       */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM public.sis_perfil p
CROSS JOIN public.sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url LIKE '/sistema/configuracion%'
  AND NOT EXISTS (
      SELECT 1 FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
