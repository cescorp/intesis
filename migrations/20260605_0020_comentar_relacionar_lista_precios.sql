BEGIN;

/******************************************************************************/
/*                                                                            */
/*  COMENTA Y RELACIONA LISTAS DE PRECIO PARA CONSULTAS DE STOCK              */
/*                                                                            */
/******************************************************************************/

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ven_lista_precio_empresa_fk') THEN
        ALTER TABLE ven_lista_precio
        ADD CONSTRAINT ven_lista_precio_empresa_fk
        FOREIGN KEY (sis_empresa_id) REFERENCES sis_empresa (sis_empresa_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ven_lista_precio_detalle_lista_fk') THEN
        ALTER TABLE ven_lista_precio_detalle
        ADD CONSTRAINT ven_lista_precio_detalle_lista_fk
        FOREIGN KEY (ven_lista_precio_id) REFERENCES ven_lista_precio (ven_lista_precio_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ven_lista_precio_detalle_producto_fk') THEN
        ALTER TABLE ven_lista_precio_detalle
        ADD CONSTRAINT ven_lista_precio_detalle_producto_fk
        FOREIGN KEY (inv_producto_id) REFERENCES inv_producto (inv_producto_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ven_lista_precio_detalle_empresa_fk') THEN
        ALTER TABLE ven_lista_precio_detalle
        ADD CONSTRAINT ven_lista_precio_detalle_empresa_fk
        FOREIGN KEY (sis_empresa_id) REFERENCES sis_empresa (sis_empresa_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_ven_lista_precio_empresa
    ON ven_lista_precio (sis_empresa_id);

CREATE INDEX IF NOT EXISTS idx_ven_lista_precio_predeterminada
    ON ven_lista_precio (sis_empresa_id, ven_lista_precio_predeterminado);

CREATE INDEX IF NOT EXISTS idx_ven_lista_precio_detalle_lista_producto
    ON ven_lista_precio_detalle (ven_lista_precio_id, inv_producto_id);

CREATE INDEX IF NOT EXISTS idx_ven_lista_precio_detalle_empresa_producto
    ON ven_lista_precio_detalle (sis_empresa_id, inv_producto_id);

COMMENT ON TABLE ven_lista_precio IS 'LISTAS DE PRECIO POR EMPRESA';
COMMENT ON COLUMN ven_lista_precio.ven_lista_precio_id IS 'IDENTIFICADOR DE LA LISTA DE PRECIO';
COMMENT ON COLUMN ven_lista_precio.ven_lista_precio_descripcion IS 'NOMBRE O DESCRIPCION DE LA LISTA';
COMMENT ON COLUMN ven_lista_precio.ven_lista_precio_descuento IS 'PORCENTAJE DE DESCUENTO DE LA LISTA';
COMMENT ON COLUMN ven_lista_precio.sis_estado IS 'ESTADO LEGADO DE LA LISTA';
COMMENT ON COLUMN ven_lista_precio.sis_empresa_id IS 'EMPRESA DUEÑA DE LA LISTA';
COMMENT ON COLUMN ven_lista_precio.ven_lista_precio_predeterminado IS 'INDICA SI LA LISTA ES PREDETERMINADA';
COMMENT ON COLUMN ven_lista_precio.ven_lista_precio_color IS 'COLOR VISUAL DE LA LISTA';
COMMENT ON COLUMN ven_lista_precio.user_crea IS 'USUARIO QUE CREO EL REGISTRO';
COMMENT ON COLUMN ven_lista_precio.fecha_crea IS 'FECHA DE CREACION DEL REGISTRO';
COMMENT ON COLUMN ven_lista_precio.user_modifica IS 'USUARIO QUE MODIFICO EL REGISTRO';
COMMENT ON COLUMN ven_lista_precio.fecha_modifica IS 'FECHA DE MODIFICACION DEL REGISTRO';
COMMENT ON COLUMN ven_lista_precio.id_session IS 'SESION QUE CREO O MODIFICO EL REGISTRO';
COMMENT ON COLUMN ven_lista_precio.ven_lista_precio_orden IS 'ORDEN DE VISUALIZACION DE LA LISTA';

COMMENT ON TABLE ven_lista_precio_detalle IS 'PRECIOS DE PRODUCTOS POR LISTA';
COMMENT ON COLUMN ven_lista_precio_detalle.ven_lista_precio_id IS 'LISTA DE PRECIO RELACIONADA';
COMMENT ON COLUMN ven_lista_precio_detalle.inv_producto_id IS 'PRODUCTO RELACIONADO AL PRECIO';
COMMENT ON COLUMN ven_lista_precio_detalle.ven_lista_precio_detalle_valor IS 'PRECIO DE VENTA DEL PRODUCTO';
COMMENT ON COLUMN ven_lista_precio_detalle.sis_empresa_id IS 'EMPRESA DUEÑA DEL PRECIO';
COMMENT ON COLUMN ven_lista_precio_detalle.user_crea IS 'USUARIO QUE CREO EL REGISTRO';
COMMENT ON COLUMN ven_lista_precio_detalle.fecha_crea IS 'FECHA DE CREACION DEL REGISTRO';
COMMENT ON COLUMN ven_lista_precio_detalle.user_modifica IS 'USUARIO QUE MODIFICO EL REGISTRO';
COMMENT ON COLUMN ven_lista_precio_detalle.fecha_modifica IS 'FECHA DE MODIFICACION DEL REGISTRO';
COMMENT ON COLUMN ven_lista_precio_detalle.id_session IS 'SESION QUE CREO O MODIFICO EL REGISTRO';
COMMENT ON COLUMN ven_lista_precio_detalle.ven_lista_precio_detalle_id IS 'IDENTIFICADOR DEL DETALLE DE PRECIO';
COMMENT ON COLUMN ven_lista_precio_detalle.ven_lista_precio_detalle_estado IS 'ESTADO LEGADO DEL DETALLE';
COMMENT ON COLUMN ven_lista_precio_detalle.codigo IS 'CODIGO LEGADO DEL DETALLE';

COMMIT;
