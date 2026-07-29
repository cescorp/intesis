BEGIN;

/******************************************************************************/
/*                                                                            */
/*  GUARDA LA MARCA COMPRADA COMO HISTORIAL POR LINEA DE COMPRA, SIN         */
/*  MODIFICAR LA MARCA GLOBAL DEL PRODUCTO (inv_producto.inv_marca_id).      */
/*                                                                            */
/******************************************************************************/

ALTER TABLE com_documento_detalle
    ADD COLUMN IF NOT EXISTS com_documento_detalle_marca_id BIGINT;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'com_documento_detalle_marca_fk') THEN
        ALTER TABLE com_documento_detalle
            ADD CONSTRAINT com_documento_detalle_marca_fk FOREIGN KEY (com_documento_detalle_marca_id) REFERENCES inv_marca(inv_marca_id);
    END IF;
END $$;

COMMENT ON COLUMN com_documento_detalle.com_documento_detalle_marca_id IS 'MARCA DEL PRODUCTO AL MOMENTO DE ESTA COMPRA (HISTORIAL, NO ACTUALIZA EL PRODUCTO)';

COMMIT;
