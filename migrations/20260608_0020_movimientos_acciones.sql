BEGIN;

/******************************************************************************/
/*                                                                            */
/*  AGREGA ACCIONES DE DETALLE Y ANULACION DE MOVIMIENTOS PROCESADOS          */
/*                                                                            */
/******************************************************************************/

WITH mov_id AS (
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/movimientos' LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, mov_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM mov_id
CROSS JOIN (
    VALUES
        ('Ver detalle movimiento', 'bi bi-eye', '/inventario/movimientos/detalle', 20),
        ('Anular procesado o recibido', 'bi bi-x-octagon-fill', '/inventario/movimientos/anular-procesado', 21)
) AS datos(nombre, icono, url, orden)
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url IN ('/inventario/movimientos/detalle', '/inventario/movimientos/anular-procesado')
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
