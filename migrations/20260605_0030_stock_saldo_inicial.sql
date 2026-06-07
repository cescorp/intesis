BEGIN;

/******************************************************************************/
/*                                                                            */
/*  PREPARA STOCK, SALDO INICIAL Y PERMISOS DE INVENTARIO                     */
/*                                                                            */
/******************************************************************************/

INSERT INTO sis_tipo_documento (
    sis_tipo_documento_codigo, sis_tipo_documento_nombre,
    sis_tipo_documento_afecta_inventario, sis_tipo_documento_afecta_contabilidad,
    sis_tipo_documento_modulo, sis_tipo_documento_descripcion,
    sis_estado_id, usuario_crea
)
SELECT 'SALDO_INICIAL', 'Saldo inicial', TRUE, FALSE, 'INVENTARIO',
       'DOCUMENTO AUTOMATICO PARA CARGA DE SALDO INICIAL',
       e.sis_estado_id, 1
FROM sis_estado e
WHERE e.sis_estado_modulo = 'SISTEMA'
  AND e.sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
  AND e.sis_estado_codigo = 'ACTIVO'
  AND NOT EXISTS (
      SELECT 1
      FROM sis_tipo_documento td
      WHERE td.sis_tipo_documento_codigo = 'SALDO_INICIAL'
  );

ALTER TABLE inv_kardex DROP CONSTRAINT IF EXISTS chk_inv_kardex_tipo_movimiento;
ALTER TABLE inv_kardex
ADD CONSTRAINT chk_inv_kardex_tipo_movimiento
CHECK (
    inv_kardex_tipo_movimiento IN (
        'COMPRA', 'VENTA', 'AJUSTE_IN', 'AJUSTE_OUT',
        'TRANS_IN', 'TRANS_OUT', 'SALDO_INICIAL'
    )
);

CREATE INDEX IF NOT EXISTS idx_inv_stock_empresa_producto
    ON inv_stock (sis_empresa_id, inv_producto_id);

CREATE INDEX IF NOT EXISTS idx_inv_kardex_empresa_producto
    ON inv_kardex (sis_empresa_id, inv_producto_id, inv_kardex_fecha_movimiento);

CREATE INDEX IF NOT EXISTS idx_inv_codigo_proveedor_producto_estado
    ON inv_codigo_proveedor (inv_producto_id, inv_codigo_proveedor_estado);

COMMENT ON TABLE inv_stock IS 'SALDOS ACTUALES DE PRODUCTOS POR BODEGA';
COMMENT ON COLUMN inv_stock.inv_stock_id IS 'IDENTIFICADOR DEL STOCK';
COMMENT ON COLUMN inv_stock.sis_empresa_id IS 'EMPRESA DUEÑA DEL STOCK';
COMMENT ON COLUMN inv_stock.inv_bodega_id IS 'BODEGA DEL STOCK';
COMMENT ON COLUMN inv_stock.inv_producto_id IS 'PRODUCTO DEL STOCK';
COMMENT ON COLUMN inv_stock.inv_stock_cantidad_disponible IS 'CANTIDAD DISPONIBLE ACTUAL';
COMMENT ON COLUMN inv_stock.inv_stock_costo_promedio IS 'COSTO PROMEDIO DEL STOCK';
COMMENT ON COLUMN inv_stock.inv_stock_ultima_actualizacion IS 'FECHA DE ULTIMA ACTUALIZACION';

COMMENT ON TABLE inv_movimientos IS 'CABECERA DE MOVIMIENTOS DE INVENTARIO';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_id IS 'IDENTIFICADOR DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.sis_empresa_id IS 'EMPRESA DUEÑA DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.sis_tipo_documento_id IS 'TIPO DE DOCUMENTO DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_numero IS 'NUMERO DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_fecha IS 'FECHA DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.inv_bodega_origen_id IS 'BODEGA ORIGEN DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.inv_bodega_destino_id IS 'BODEGA DESTINO DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_referencia IS 'REFERENCIA DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.inv_movimientos_observacion IS 'OBSERVACION DEL MOVIMIENTO';
COMMENT ON COLUMN inv_movimientos.sis_estado_id IS 'ESTADO LOGICO DEL MOVIMIENTO';

COMMENT ON TABLE inv_kardex IS 'HISTORIAL DE ENTRADAS Y SALIDAS DE INVENTARIO';
COMMENT ON COLUMN inv_kardex.inv_kardex_tipo_movimiento IS 'TIPO DE MOVIMIENTO DE INVENTARIO';
COMMENT ON COLUMN inv_kardex.inv_kardex_documento_id IS 'DOCUMENTO RELACIONADO AL KARDEX';
COMMENT ON COLUMN inv_kardex.inv_kardex_documento_numero IS 'NUMERO DEL DOCUMENTO RELACIONADO';

WITH inventario_id AS (
    SELECT sis_menu_id
    FROM sis_menu
    WHERE sis_menu_url = '/inventario'
    LIMIT 1
),
menu_stock AS (
    INSERT INTO sis_menu (
        sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
        sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
    )
    SELECT 'Stock', inventario_id.sis_menu_id, 'bi bi-boxes', '/inventario/stock', 5, 1, 'M', 1
    FROM inventario_id
    WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/inventario/stock')
    RETURNING sis_menu_id
),
stock_id AS (
    SELECT sis_menu_id FROM menu_stock
    UNION ALL
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/stock'
    LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, stock_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM stock_id
CROSS JOIN (
    VALUES
        ('Ver stock', 'bi bi-eye', '/inventario/stock/ver', 1),
        ('Registrar saldo', 'bi bi-plus-square', '/inventario/stock/registrar', 2),
        ('Importar stock', 'bi bi-file-earmark-arrow-up', '/inventario/stock/importar', 3),
        ('Confirmar importacion', 'bi bi-check2-square', '/inventario/stock/confirmar-importacion', 4),
        ('Descargar plantilla', 'bi bi-filetype-csv', '/inventario/stock/plantilla', 5)
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
      '/inventario/stock',
      '/inventario/stock/ver',
      '/inventario/stock/registrar',
      '/inventario/stock/importar',
      '/inventario/stock/confirmar-importacion',
      '/inventario/stock/plantilla'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
