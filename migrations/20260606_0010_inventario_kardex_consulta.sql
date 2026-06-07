BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA CONSULTA KARDEX Y PERMISOS DE INVENTARIO                             */
/*                                                                            */
/******************************************************************************/

CREATE INDEX IF NOT EXISTS idx_inv_kardex_empresa_producto_fecha
    ON inv_kardex (sis_empresa_id, inv_producto_id, inv_kardex_fecha_movimiento DESC);

COMMENT ON COLUMN inv_kardex.inv_bodega_id IS 'BODEGA DEL MOVIMIENTO KARDEX';
COMMENT ON COLUMN inv_kardex.inv_producto_id IS 'PRODUCTO DEL MOVIMIENTO KARDEX';
COMMENT ON COLUMN inv_kardex.inv_kardex_fecha_movimiento IS 'FECHA Y HORA DEL MOVIMIENTO KARDEX';
COMMENT ON COLUMN inv_kardex.inv_kardex_cantidad_entrada IS 'CANTIDAD QUE INGRESA AL STOCK';
COMMENT ON COLUMN inv_kardex.inv_kardex_cantidad_salida IS 'CANTIDAD QUE SALE DEL STOCK';
COMMENT ON COLUMN inv_kardex.inv_kardex_saldo_cantidad IS 'SALDO DEL PRODUCTO DESPUES DEL MOVIMIENTO';
COMMENT ON COLUMN inv_kardex.inv_kardex_saldo_valor IS 'VALOR DEL SALDO DESPUES DEL MOVIMIENTO';
COMMENT ON COLUMN inv_kardex.inv_kardex_observacion IS 'OBSERVACION DEL MOVIMIENTO KARDEX';

WITH inventario_id AS (
    SELECT sis_menu_id
    FROM sis_menu
    WHERE sis_menu_url = '/inventario'
    LIMIT 1
),
menu_kardex AS (
    INSERT INTO sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT 'Kardex', inventario_id.sis_menu_id, 'bi bi-activity', '/inventario/kardex', 6, 1, 'M', 1
    FROM inventario_id
    WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/inventario/kardex')
    RETURNING sis_menu_id
),
kardex_id AS (
    SELECT sis_menu_id FROM menu_kardex
    UNION ALL
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/kardex'
    LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, kardex_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM kardex_id
CROSS JOIN (
    VALUES
        ('Ver kardex', 'bi bi-eye', '/inventario/kardex/ver', 1),
        ('Detalle kardex', 'bi bi-list-columns-reverse', '/inventario/kardex/detalle', 2)
) AS datos(nombre, icono, url, orden)
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url IN (
      '/inventario/kardex',
      '/inventario/kardex/ver',
      '/inventario/kardex/detalle'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
