BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CAMBIA inv_movimientos_fecha DE date A timestamp WITHOUT TIME ZONE        */
/*  PARA REGISTRAR HORA DEL MOVIMIENTO ADEMAS DE LA FECHA.                   */
/*                                                                            */
/******************************************************************************/

ALTER TABLE inv_movimientos
    ALTER COLUMN inv_movimientos_fecha
    TYPE timestamp without time zone
    USING inv_movimientos_fecha::timestamp without time zone;

COMMENT ON COLUMN inv_movimientos.inv_movimientos_fecha IS 'FECHA Y HORA DEL MOVIMIENTO';

COMMIT;
