BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA MENU USUARIOS Y ACCIONES DEL CRUD                                    */
/*                                                                            */
/******************************************************************************/

WITH menu_sistema AS (
    SELECT sis_menu_id
    FROM public.sis_menu
    WHERE sis_menu_url = '/sistema'
      AND sis_menu_tipo = 'M'
    LIMIT 1
),
menu_usuarios AS (
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
    SELECT 'Usuarios', sis_menu_id, 'bi bi-people', '/sistema/usuarios', 2, 1, 'M', 1
    FROM menu_sistema
    WHERE NOT EXISTS (
        SELECT 1 FROM public.sis_menu WHERE sis_menu_url = '/sistema/usuarios' AND sis_menu_tipo = 'M'
    )
    RETURNING sis_menu_id
),
menu_usuarios_id AS (
    SELECT sis_menu_id FROM menu_usuarios
    UNION ALL
    SELECT sis_menu_id FROM public.sis_menu WHERE sis_menu_url = '/sistema/usuarios' AND sis_menu_tipo = 'M'
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
SELECT accion.nombre, u.sis_menu_id, accion.icono, accion.url, accion.orden, 1, 'B', 1
FROM menu_usuarios_id u
CROSS JOIN (
    VALUES
        ('Ver usuario', 'bi bi-eye', '/sistema/usuarios/ver', 1),
        ('Crear usuario', 'bi bi-person-plus', '/sistema/usuarios/crear', 2),
        ('Editar usuario', 'bi bi-pencil-square', '/sistema/usuarios/editar', 3),
        ('Activar usuario', 'bi bi-toggle-on', '/sistema/usuarios/activar', 4),
        ('Inactivar usuario', 'bi bi-toggle-off', '/sistema/usuarios/inactivar', 5),
        ('Bloquear usuario', 'bi bi-person-lock', '/sistema/usuarios/bloquear', 6),
        ('Eliminar usuario', 'bi bi-trash3', '/sistema/usuarios/eliminar', 7),
        ('Restablecer clave', 'bi bi-key', '/sistema/usuarios/restablecer-clave', 8)
) AS accion(nombre, icono, url, orden)
WHERE NOT EXISTS (
    SELECT 1 FROM public.sis_menu m WHERE m.sis_menu_url = accion.url AND m.sis_menu_tipo = 'B'
);

/******************************************************************************/
/*                                                                            */
/*  ASIGNA PERMISOS DE USUARIOS A PERFILES PERTINENTES                        */
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
      '/sistema/usuarios',
      '/sistema/usuarios/ver',
      '/sistema/usuarios/crear',
      '/sistema/usuarios/editar',
      '/sistema/usuarios/activar',
      '/sistema/usuarios/inactivar',
      '/sistema/usuarios/bloquear',
      '/sistema/usuarios/eliminar',
      '/sistema/usuarios/restablecer-clave'
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
  AND m.sis_menu_url IN ('/sistema', '/sistema/usuarios', '/sistema/usuarios/ver')
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
