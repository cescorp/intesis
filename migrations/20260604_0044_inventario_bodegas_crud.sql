BEGIN;

/******************************************************************************/
/*                                                                            */
/*  COMPLETA INV_BODEGA Y CREA MENU INVENTARIO BODEGAS                        */
/*                                                                            */
/******************************************************************************/

ALTER TABLE inv_bodega
    ADD COLUMN IF NOT EXISTS inv_bodega_descripcion VARCHAR(250) DEFAULT '',
    ADD COLUMN IF NOT EXISTS inv_bodega_virtual BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON TABLE inv_bodega IS 'BODEGAS DE INVENTARIO POR EMPRESA';
COMMENT ON COLUMN inv_bodega.inv_bodega_id IS 'IDENTIFICADOR DE LA BODEGA';
COMMENT ON COLUMN inv_bodega.sis_empresa_id IS 'EMPRESA DUEÑA DE LA BODEGA';
COMMENT ON COLUMN inv_bodega.inv_bodega_codigo IS 'CODIGO UNICO DE BODEGA POR EMPRESA';
COMMENT ON COLUMN inv_bodega.inv_bodega_nombre IS 'NOMBRE DE LA BODEGA';
COMMENT ON COLUMN inv_bodega.inv_bodega_descripcion IS 'DESCRIPCION DE LA BODEGA';
COMMENT ON COLUMN inv_bodega.inv_bodega_direccion IS 'DIRECCION FISICA O REFERENCIAL DE LA BODEGA';
COMMENT ON COLUMN inv_bodega.inv_bodega_es_principal IS 'INDICA SI ES LA BODEGA PRINCIPAL DE LA EMPRESA';
COMMENT ON COLUMN inv_bodega.inv_bodega_virtual IS 'INDICA SI LA BODEGA ES VIRTUAL Y NO DISPONIBLE PARA VENTA';
COMMENT ON COLUMN inv_bodega.sis_estado_id IS 'ESTADO LOGICO DE LA BODEGA';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_inv_bodega_virtual_no_principal') THEN
        ALTER TABLE inv_bodega ADD CONSTRAINT ck_inv_bodega_virtual_no_principal CHECK (NOT (inv_bodega_virtual = TRUE AND inv_bodega_es_principal = TRUE));
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_bodega_empresa_codigo
    ON inv_bodega (sis_empresa_id, upper(inv_bodega_codigo));

CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_bodega_principal_empresa
    ON inv_bodega (sis_empresa_id)
    WHERE inv_bodega_es_principal = TRUE;

WITH sistema AS (
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/sistema' LIMIT 1
),
inventario AS (
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
bodegas AS (
    INSERT INTO sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT 'Bodegas', inventario_id.sis_menu_id, 'bi bi-buildings', '/inventario/bodegas', 1, 1, 'M', 1
    FROM inventario_id
    WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/inventario/bodegas')
    RETURNING sis_menu_id
),
bodegas_id AS (
    SELECT sis_menu_id FROM bodegas
    UNION ALL
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/bodegas'
    LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, bodegas_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM bodegas_id
CROSS JOIN (
    VALUES
        ('Ver bodegas', 'bi bi-eye', '/inventario/bodegas/ver', 1),
        ('Crear bodega', 'bi bi-plus-square', '/inventario/bodegas/crear', 2),
        ('Editar bodega', 'bi bi-pencil-square', '/inventario/bodegas/editar', 3),
        ('Activar bodega', 'bi bi-toggle-on', '/inventario/bodegas/activar', 4),
        ('Inactivar bodega', 'bi bi-toggle-off', '/inventario/bodegas/inactivar', 5),
        ('Eliminar bodega', 'bi bi-trash3', '/inventario/bodegas/eliminar', 6)
) AS datos(nombre, icono, url, orden)
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url LIKE '/inventario%'
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
