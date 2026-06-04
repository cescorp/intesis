BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA PERFILES BASE EN EMPRESAS ACTIVAS QUE NO TIENEN PERFILES             */
/*                                                                            */
/******************************************************************************/

WITH empresas_sin_perfiles AS (
    SELECT e.sis_empresa_id
    FROM public.sis_empresa e
    INNER JOIN public.sis_estado es ON es.sis_estado_id = e.sis_estado_id
    WHERE es.sis_estado_codigo = 'ACTIVO'
      AND NOT EXISTS (
          SELECT 1
          FROM public.sis_perfil p
          WHERE p.sis_empresa_id = e.sis_empresa_id
      )
),
perfiles_base AS (
    SELECT *
    FROM (
        VALUES
            ('SUPERUSUARIO', 'SUPERUSUARIO'),
            ('GERENCIA', 'GERENCIA'),
            ('CONTADOR', 'CONTADOR'),
            ('GERENTE_VENTAS', 'GERENTE DE VENTAS'),
            ('VENDEDOR', 'VENDEDOR'),
            ('COMPRAS', 'COMPRAS'),
            ('BODEGUERO', 'BODEGUERO')
    ) AS perfil(sis_perfil_codigo, sis_perfil_nombre)
),
perfiles_insertados AS (
    INSERT INTO public.sis_perfil (
        sis_empresa_id,
        sis_perfil_codigo,
        sis_perfil_nombre,
        sis_perfil_estado,
        usuario_crea
    )
    SELECT
        e.sis_empresa_id,
        p.sis_perfil_codigo,
        p.sis_perfil_nombre,
        1,
        1
    FROM empresas_sin_perfiles e
    CROSS JOIN perfiles_base p
    WHERE NOT EXISTS (
        SELECT 1
        FROM public.sis_perfil existente
        WHERE existente.sis_empresa_id = e.sis_empresa_id
          AND existente.sis_perfil_codigo = p.sis_perfil_codigo
    )
    RETURNING sis_empresa_id, sis_perfil_id, sis_perfil_codigo
)
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
WHERE (
        p.sis_perfil_codigo = 'SUPERUSUARIO'
        OR (
            p.sis_perfil_codigo IN ('GERENCIA', 'CONTADOR')
            AND m.sis_menu_url IN (
                '/sistema',
                '/sistema/empresas',
                '/sistema/empresas/ver',
                '/sistema/usuarios',
                '/sistema/usuarios/ver'
            )
        )
    )
  AND NOT EXISTS (
      SELECT 1
      FROM public.sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
