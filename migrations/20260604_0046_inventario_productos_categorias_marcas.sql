BEGIN;

/******************************************************************************/
/*                                                                            */
/*  COMPLETA ESTADOS Y MENUS DE PRODUCTOS CATEGORIAS Y MARCAS                 */
/*                                                                            */
/******************************************************************************/

INSERT INTO sis_estado (
    sis_estado_modulo, sis_estado_entidad, sis_estado_codigo,
    sis_estado_nombre, sis_estado_descripcion, sis_estado_orden, usuario_crea
)
VALUES
    ('INVENTARIO', 'INV_PRODUCTO', 'ACTIVO', 'ACTIVO', 'PRODUCTO DISPONIBLE PARA OPERACIONES', 1, 1),
    ('INVENTARIO', 'INV_PRODUCTO', 'INACTIVO', 'INACTIVO', 'PRODUCTO DESHABILITADO TEMPORALMENTE', 2, 1),
    ('INVENTARIO', 'INV_PRODUCTO', 'ELIMINADO', 'ELIMINADO', 'PRODUCTO ELIMINADO LOGICAMENTE', 3, 1),
    ('INVENTARIO', 'INV_CATEGORIA', 'ACTIVO', 'ACTIVO', 'CATEGORIA DISPONIBLE PARA PRODUCTOS', 1, 1),
    ('INVENTARIO', 'INV_CATEGORIA', 'INACTIVO', 'INACTIVO', 'CATEGORIA DESHABILITADA TEMPORALMENTE', 2, 1),
    ('INVENTARIO', 'INV_CATEGORIA', 'ELIMINADO', 'ELIMINADO', 'CATEGORIA ELIMINADA LOGICAMENTE', 3, 1),
    ('INVENTARIO', 'INV_MARCA', 'ACTIVO', 'ACTIVO', 'MARCA DISPONIBLE PARA PRODUCTOS', 1, 1),
    ('INVENTARIO', 'INV_MARCA', 'INACTIVO', 'INACTIVO', 'MARCA DESHABILITADA TEMPORALMENTE', 2, 1),
    ('INVENTARIO', 'INV_MARCA', 'ELIMINADO', 'ELIMINADO', 'MARCA ELIMINADA LOGICAMENTE', 3, 1)
ON CONFLICT (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo) DO NOTHING;

ALTER TABLE inv_categoria ADD COLUMN IF NOT EXISTS sis_estado_id BIGINT;
ALTER TABLE inv_marca ADD COLUMN IF NOT EXISTS sis_estado_id BIGINT;

UPDATE inv_categoria c
SET sis_estado_id = e.sis_estado_id
FROM sis_estado e
WHERE c.sis_estado_id IS NULL
  AND e.sis_estado_modulo = 'INVENTARIO'
  AND e.sis_estado_entidad = 'INV_CATEGORIA'
  AND e.sis_estado_codigo = CASE c.inv_categoria_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      ELSE 'ACTIVO'
  END;

UPDATE inv_marca m
SET sis_estado_id = e.sis_estado_id
FROM sis_estado e
WHERE m.sis_estado_id IS NULL
  AND e.sis_estado_modulo = 'INVENTARIO'
  AND e.sis_estado_entidad = 'INV_MARCA'
  AND e.sis_estado_codigo = CASE m.inv_marca_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      ELSE 'ACTIVO'
  END;

DO $$
DECLARE
    v_categoria_activo BIGINT;
    v_marca_activo BIGINT;
BEGIN
    SELECT sis_estado_id INTO v_categoria_activo
    FROM sis_estado
    WHERE sis_estado_modulo = 'INVENTARIO'
      AND sis_estado_entidad = 'INV_CATEGORIA'
      AND sis_estado_codigo = 'ACTIVO';

    SELECT sis_estado_id INTO v_marca_activo
    FROM sis_estado
    WHERE sis_estado_modulo = 'INVENTARIO'
      AND sis_estado_entidad = 'INV_MARCA'
      AND sis_estado_codigo = 'ACTIVO';

    EXECUTE format('ALTER TABLE inv_categoria ALTER COLUMN sis_estado_id SET DEFAULT %s', v_categoria_activo);
    EXECUTE format('ALTER TABLE inv_marca ALTER COLUMN sis_estado_id SET DEFAULT %s', v_marca_activo);
END $$;

ALTER TABLE inv_categoria ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE inv_marca ALTER COLUMN sis_estado_id SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'inv_categoria_estado_fk') THEN
        ALTER TABLE inv_categoria
        ADD CONSTRAINT inv_categoria_estado_fk
        FOREIGN KEY (sis_estado_id) REFERENCES sis_estado (sis_estado_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'inv_marca_estado_fk') THEN
        ALTER TABLE inv_marca
        ADD CONSTRAINT inv_marca_estado_fk
        FOREIGN KEY (sis_estado_id) REFERENCES sis_estado (sis_estado_id);
    END IF;
END $$;

COMMENT ON TABLE inv_producto IS 'PRODUCTOS DE INVENTARIO POR EMPRESA';
COMMENT ON COLUMN inv_producto.inv_producto_id IS 'IDENTIFICADOR DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.sis_empresa_id IS 'EMPRESA DUEÑA DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_categoria_id IS 'CATEGORIA DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_marca_id IS 'MARCA DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_codigo_principal IS 'CODIGO PRINCIPAL UNICO POR EMPRESA';
COMMENT ON COLUMN inv_producto.inv_producto_codigo_auxiliar IS 'CODIGO AUXILIAR DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_nombre IS 'NOMBRE DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_descripcion IS 'DESCRIPCION DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_unidad_medida IS 'UNIDAD DE MEDIDA DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_lleva_iva IS 'INDICA SI EL PRODUCTO APLICA IVA';
COMMENT ON COLUMN inv_producto.inv_producto_costo_promedio IS 'COSTO PROMEDIO CALCULADO DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_costo_ultimo IS 'ULTIMO COSTO REGISTRADO DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_stock_minimo IS 'STOCK MINIMO DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.inv_producto_stock_maximo IS 'STOCK MAXIMO DEL PRODUCTO';
COMMENT ON COLUMN inv_producto.sis_estado_id IS 'ESTADO LOGICO DEL PRODUCTO';

COMMENT ON TABLE inv_categoria IS 'CATEGORIAS DE PRODUCTOS POR EMPRESA';
COMMENT ON COLUMN inv_categoria.inv_categoria_id IS 'IDENTIFICADOR DE LA CATEGORIA';
COMMENT ON COLUMN inv_categoria.sis_empresa_id IS 'EMPRESA DUEÑA DE LA CATEGORIA';
COMMENT ON COLUMN inv_categoria.inv_categoria_nombre IS 'NOMBRE UNICO DE CATEGORIA POR EMPRESA';
COMMENT ON COLUMN inv_categoria.inv_categoria_descripcion IS 'DESCRIPCION DE LA CATEGORIA';
COMMENT ON COLUMN inv_categoria.sis_estado_id IS 'ESTADO LOGICO DE LA CATEGORIA';

COMMENT ON TABLE inv_marca IS 'MARCAS DE PRODUCTOS POR EMPRESA';
COMMENT ON COLUMN inv_marca.inv_marca_id IS 'IDENTIFICADOR DE LA MARCA';
COMMENT ON COLUMN inv_marca.sis_empresa_id IS 'EMPRESA DUEÑA DE LA MARCA';
COMMENT ON COLUMN inv_marca.inv_marca_nombre IS 'NOMBRE UNICO DE MARCA POR EMPRESA';
COMMENT ON COLUMN inv_marca.sis_estado_id IS 'ESTADO LOGICO DE LA MARCA';

CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_producto_empresa_codigo
    ON inv_producto (sis_empresa_id, upper(inv_producto_codigo_principal));

CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_categoria_empresa_nombre
    ON inv_categoria (sis_empresa_id, upper(inv_categoria_nombre));

CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_marca_empresa_nombre
    ON inv_marca (sis_empresa_id, upper(inv_marca_nombre));

WITH inventario AS (
    INSERT INTO sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT 'Inventario', NULL, 'bi bi-box-seam', '/inventario', 20, 1, 'M', 1
    WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/inventario')
    RETURNING sis_menu_id
),
inventario_id AS (
    SELECT sis_menu_id FROM inventario
    UNION ALL
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario'
    LIMIT 1
),
menus AS (
    INSERT INTO sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT datos.nombre, inventario_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'M', 1
    FROM inventario_id
    CROSS JOIN (
        VALUES
            ('Productos', 'bi bi-box2-heart', '/inventario/productos', 2),
            ('Categorias', 'bi bi-tags', '/inventario/categorias', 3),
            ('Marcas', 'bi bi-bookmark-star', '/inventario/marcas', 4)
    ) AS datos(nombre, icono, url, orden)
    WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url)
    RETURNING sis_menu_id, sis_menu_url
),
menus_id AS (
    SELECT sis_menu_id, sis_menu_url FROM menus
    UNION ALL
    SELECT sis_menu_id, sis_menu_url
    FROM sis_menu
    WHERE sis_menu_url IN ('/inventario/productos', '/inventario/categorias', '/inventario/marcas')
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, menus_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM menus_id
CROSS JOIN (
    VALUES
        ('/inventario/productos', 'Ver productos', 'bi bi-eye', '/inventario/productos/ver', 1),
        ('/inventario/productos', 'Crear producto', 'bi bi-plus-square', '/inventario/productos/crear', 2),
        ('/inventario/productos', 'Editar producto', 'bi bi-pencil-square', '/inventario/productos/editar', 3),
        ('/inventario/productos', 'Activar producto', 'bi bi-toggle-on', '/inventario/productos/activar', 4),
        ('/inventario/productos', 'Inactivar producto', 'bi bi-toggle-off', '/inventario/productos/inactivar', 5),
        ('/inventario/categorias', 'Ver categorias', 'bi bi-eye', '/inventario/categorias/ver', 1),
        ('/inventario/categorias', 'Crear categoria', 'bi bi-plus-square', '/inventario/categorias/crear', 2),
        ('/inventario/categorias', 'Editar categoria', 'bi bi-pencil-square', '/inventario/categorias/editar', 3),
        ('/inventario/categorias', 'Activar categoria', 'bi bi-toggle-on', '/inventario/categorias/activar', 4),
        ('/inventario/categorias', 'Inactivar categoria', 'bi bi-toggle-off', '/inventario/categorias/inactivar', 5),
        ('/inventario/marcas', 'Ver marcas', 'bi bi-eye', '/inventario/marcas/ver', 1),
        ('/inventario/marcas', 'Crear marca', 'bi bi-plus-square', '/inventario/marcas/crear', 2),
        ('/inventario/marcas', 'Editar marca', 'bi bi-pencil-square', '/inventario/marcas/editar', 3),
        ('/inventario/marcas', 'Activar marca', 'bi bi-toggle-on', '/inventario/marcas/activar', 4),
        ('/inventario/marcas', 'Inactivar marca', 'bi bi-toggle-off', '/inventario/marcas/inactivar', 5)
) AS datos(padre_url, nombre, icono, url, orden)
WHERE menus_id.sis_menu_url = datos.padre_url
  AND NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url IN (
      '/inventario/productos', '/inventario/productos/ver', '/inventario/productos/crear',
      '/inventario/productos/editar', '/inventario/productos/activar', '/inventario/productos/inactivar',
      '/inventario/categorias', '/inventario/categorias/ver', '/inventario/categorias/crear',
      '/inventario/categorias/editar', '/inventario/categorias/activar', '/inventario/categorias/inactivar',
      '/inventario/marcas', '/inventario/marcas/ver', '/inventario/marcas/crear',
      '/inventario/marcas/editar', '/inventario/marcas/activar', '/inventario/marcas/inactivar'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
