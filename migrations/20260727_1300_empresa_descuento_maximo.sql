BEGIN;

/******************************************************************************/
/*                                                                            */
/*  AGREGA LIMITES DE DESCUENTO MAXIMO POR EMPRESA PARA FACTURAS Y NOTAS DE  */
/*  VENTA. VALOR 0 = SIN LIMITE (COMPORTAMIENTO ACTUAL).                     */
/*                                                                            */
/******************************************************************************/

ALTER TABLE sis_empresa
    ADD COLUMN IF NOT EXISTS sis_empresa_descuento_maximo_facturas NUMERIC(5,2) NOT NULL DEFAULT 0;

ALTER TABLE sis_empresa
    ADD COLUMN IF NOT EXISTS sis_empresa_descuento_maximo_notas_venta NUMERIC(5,2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN sis_empresa.sis_empresa_descuento_maximo_facturas IS 'PORCENTAJE MAXIMO DE DESCUENTO PERMITIDO EN FACTURAS. 0 = SIN LIMITE';
COMMENT ON COLUMN sis_empresa.sis_empresa_descuento_maximo_notas_venta IS 'PORCENTAJE MAXIMO DE DESCUENTO PERMITIDO EN NOTAS DE VENTA. 0 = SIN LIMITE';

INSERT INTO sis_mensaje_errores (sis_mensaje_errores_codigo, sis_mensaje_errores_tipo, sis_mensaje_errores_titulo, sis_mensaje_errores_mensaje, sis_mensaje_errores_icono, sis_mensaje_errores_modulo, sis_mensaje_errores_entidad, usuario_crea)
VALUES ('EMPRESA_DESCUENTO_INVALIDO', 'ERROR', 'Descuento invalido', 'El porcentaje de descuento maximo debe estar entre 0 y 100.', 'error', 'SISTEMA', 'SIS_EMPRESA', 1)
ON CONFLICT (sis_mensaje_errores_codigo) DO NOTHING;

COMMIT;
