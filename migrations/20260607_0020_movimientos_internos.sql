BEGIN;

/******************************************************************************/
/*                                                                            */
/*  PREPARA MOVIMIENTOS INTERNOS DE INVENTARIO                                */
/*                                                                            */
/******************************************************************************/

ALTER TABLE inv_bodega
    ADD COLUMN IF NOT EXISTS inv_bodega_negativos boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS inv_bodega_autoaprobado boolean DEFAULT false;

ALTER TABLE inv_bodega
    ALTER COLUMN inv_bodega_negativos SET DEFAULT false,
    ALTER COLUMN inv_bodega_autoaprobado SET DEFAULT false;

UPDATE inv_bodega
SET inv_bodega_negativos = COALESCE(inv_bodega_negativos, false),
    inv_bodega_autoaprobado = false;

ALTER TABLE inv_movimientos
    ADD COLUMN IF NOT EXISTS inv_movimientos_tipo character varying(30) DEFAULT 'AJUSTE',
    ADD COLUMN IF NOT EXISTS inv_movimientos_recibido_fecha timestamp without time zone;

ALTER TABLE inv_movimientos_detalle
    ADD COLUMN IF NOT EXISTS inv_bodega_origen_id bigint,
    ADD COLUMN IF NOT EXISTS inv_bodega_destino_id bigint,
    ADD COLUMN IF NOT EXISTS inv_movimientos_detalle_pvp numeric DEFAULT 0,
    ADD COLUMN IF NOT EXISTS inv_movimientos_detalle_observacion character varying(250) DEFAULT '';

COMMENT ON COLUMN inv_bodega.inv_bodega_negativos IS 'PERMITE STOCK NEGATIVO EN LA BODEGA';
COMMENT ON COLUMN inv_bodega.inv_bodega_autoaprobado IS 'APRUEBA MOVIMIENTOS AUTOMATICAMENTE';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_tipo IS 'TIPO INTERNO DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_recibido_fecha IS 'FECHA DE RECEPCION DE TRANSFERENCIA';
COMMENT ON COLUMN inv_movimientos_detalle.inv_bodega_origen_id IS 'BODEGA ORIGEN DE LA LINEA';
COMMENT ON COLUMN inv_movimientos_detalle.inv_bodega_destino_id IS 'BODEGA DESTINO DE LA LINEA';
COMMENT ON COLUMN inv_movimientos_detalle.inv_movimientos_detalle_pvp IS 'PVP REFERENCIAL DE LA LINEA';
COMMENT ON COLUMN inv_movimientos_detalle.inv_movimientos_detalle_observacion IS 'OBSERVACION DE LA LINEA';

INSERT INTO sis_estado (
    sis_estado_modulo, sis_estado_entidad, sis_estado_codigo,
    sis_estado_nombre, sis_estado_descripcion, sis_estado_orden, sis_estado_activo, usuario_crea
)
SELECT 'INVENTARIO', 'INV_MOVIMIENTOS', datos.codigo, datos.nombre, datos.descripcion, datos.orden, true, 1
FROM (
    VALUES
        ('PENDIENTE', 'Pendiente', 'MOVIMIENTO PENDIENTE DE APROBACION', 1),
        ('PROCESADO', 'Procesado', 'MOVIMIENTO PROCESADO EN INVENTARIO', 2),
        ('EN_TRANSITO', 'En transito', 'TRANSFERENCIA DESCONTADA DE ORIGEN Y PENDIENTE DE RECEPCION', 3),
        ('RECIBIDO', 'Recibido', 'TRANSFERENCIA RECIBIDA EN DESTINO', 4),
        ('ANULADO', 'Anulado', 'MOVIMIENTO ANULADO', 5)
) AS datos(codigo, nombre, descripcion, orden)
WHERE NOT EXISTS (
    SELECT 1
    FROM sis_estado e
    WHERE e.sis_estado_modulo = 'INVENTARIO'
      AND e.sis_estado_entidad = 'INV_MOVIMIENTOS'
      AND e.sis_estado_codigo = datos.codigo
);

INSERT INTO sis_tipo_documento (
    sis_tipo_documento_codigo, sis_tipo_documento_nombre,
    sis_tipo_documento_afecta_inventario, sis_tipo_documento_afecta_contabilidad,
    sis_tipo_documento_modulo, sis_tipo_documento_descripcion,
    sis_estado_id, usuario_crea
)
SELECT datos.codigo, datos.nombre, true, false, 'INVENTARIO', datos.descripcion, estado.sis_estado_id, 1
FROM (
    VALUES
        ('AJUSTE', 'Ajuste interno', 'DOCUMENTO INTERNO DE AJUSTE MIXTO'),
        ('AJUSTE_IN', 'Ajuste de entrada', 'DOCUMENTO INTERNO DE AJUSTE POSITIVO'),
        ('AJUSTE_OUT', 'Ajuste de salida', 'DOCUMENTO INTERNO DE AJUSTE NEGATIVO'),
        ('TRANSFERENCIA', 'Transferencia interna', 'DOCUMENTO INTERNO DE TRANSFERENCIA ENTRE BODEGAS')
) AS datos(codigo, nombre, descripcion)
CROSS JOIN LATERAL (
    SELECT sis_estado_id
    FROM sis_estado
    WHERE sis_estado_modulo = 'SISTEMA'
      AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
      AND sis_estado_codigo = 'ACTIVO'
    LIMIT 1
) estado
WHERE NOT EXISTS (
    SELECT 1 FROM sis_tipo_documento td WHERE td.sis_tipo_documento_codigo = datos.codigo
);

WITH inventario_id AS (
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario' LIMIT 1
),
menu_movimientos AS (
    INSERT INTO sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT 'Movimientos internos', inventario_id.sis_menu_id, 'bi bi-arrow-left-right', '/inventario/movimientos', 7, 1, 'M', 1
    FROM inventario_id
    WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/inventario/movimientos')
    RETURNING sis_menu_id
),
movimientos_id AS (
    SELECT sis_menu_id FROM menu_movimientos
    UNION ALL
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/movimientos'
    LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, movimientos_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM movimientos_id
CROSS JOIN (
    VALUES
        ('Ver movimientos', 'bi bi-eye', '/inventario/movimientos/ver', 1),
        ('Crear ajuste ingreso', 'bi bi-plus-circle', '/inventario/movimientos/ajuste-ingreso', 2),
        ('Crear ajuste egreso', 'bi bi-dash-circle', '/inventario/movimientos/ajuste-egreso', 3),
        ('Crear transferencia', 'bi bi-arrow-left-right', '/inventario/movimientos/transferencia', 4),
        ('Editar movimiento', 'bi bi-pencil-square', '/inventario/movimientos/editar', 5),
        ('Aprobar movimiento', 'bi bi-check2-square', '/inventario/movimientos/aprobar', 6),
        ('Recibir transferencia', 'bi bi-box-arrow-in-down', '/inventario/movimientos/recibir', 7),
        ('Anular movimiento', 'bi bi-x-octagon', '/inventario/movimientos/anular', 8),
        ('Buscar productos', 'bi bi-search', '/inventario/movimientos/productos', 9)
) AS datos(nombre, icono, url, orden)
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url LIKE '/inventario/movimientos%'
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
