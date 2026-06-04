BEGIN;

/******************************************************************************/
/*                                                                            */
/*  ASIGNA PERMISOS INICIALES A PERFILES BASE EXISTENTES                      */
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
  AND m.sis_menu_estado = 1
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
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
  AND m.sis_menu_url IN (
      '/sistema',
      '/sistema/empresas',
      '/sistema/empresas/ver',
      '/sistema/usuarios',
      '/sistema/usuarios/ver'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
