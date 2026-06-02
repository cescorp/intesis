BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA CATALOGO CENTRAL DE ESTADOS DEL ERP                                  */
/*                                                                            */
/******************************************************************************/

CREATE TABLE IF NOT EXISTS public.sis_estado (
    sis_estado_id BIGSERIAL NOT NULL,
    sis_estado_modulo VARCHAR(30) NOT NULL,
    sis_estado_entidad VARCHAR(60) NOT NULL,
    sis_estado_codigo VARCHAR(30) NOT NULL,
    sis_estado_nombre VARCHAR(80) NOT NULL,
    sis_estado_descripcion VARCHAR(250),
    sis_estado_orden SMALLINT NOT NULL DEFAULT 1,
    sis_estado_activo BOOLEAN NOT NULL DEFAULT TRUE,
    usuario_crea BIGINT NOT NULL,
    usuario_modifica BIGINT,
    fecha_crea TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    fecha_modifica TIMESTAMP WITHOUT TIME ZONE,
    CONSTRAINT sis_estado_pk PRIMARY KEY (sis_estado_id),
    CONSTRAINT uq_sis_estado_entidad_codigo UNIQUE (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo)
);

COMMENT ON TABLE public.sis_estado IS 'CATALOGO CENTRAL DE ESTADOS UTILIZADOS POR MODULO Y ENTIDAD DEL ERP';
COMMENT ON COLUMN public.sis_estado.sis_estado_id IS 'ID UNICO DEL ESTADO';
COMMENT ON COLUMN public.sis_estado.sis_estado_modulo IS 'MODULO FUNCIONAL DONDE APLICA EL ESTADO';
COMMENT ON COLUMN public.sis_estado.sis_estado_entidad IS 'ENTIDAD O TABLA LOGICA DONDE APLICA EL ESTADO';
COMMENT ON COLUMN public.sis_estado.sis_estado_codigo IS 'CODIGO INTERNO DEL ESTADO';
COMMENT ON COLUMN public.sis_estado.sis_estado_nombre IS 'NOMBRE VISIBLE DEL ESTADO';
COMMENT ON COLUMN public.sis_estado.sis_estado_descripcion IS 'DESCRIPCION FUNCIONAL DEL ESTADO';
COMMENT ON COLUMN public.sis_estado.sis_estado_orden IS 'ORDEN DE PRESENTACION DEL ESTADO';
COMMENT ON COLUMN public.sis_estado.sis_estado_activo IS 'INDICA SI EL ESTADO ESTA DISPONIBLE PARA USO';
COMMENT ON COLUMN public.sis_estado.usuario_crea IS 'USUARIO QUE CREO EL REGISTRO';
COMMENT ON COLUMN public.sis_estado.usuario_modifica IS 'USUARIO QUE MODIFICO EL REGISTRO';
COMMENT ON COLUMN public.sis_estado.fecha_crea IS 'FECHA Y HORA DE CREACION';
COMMENT ON COLUMN public.sis_estado.fecha_modifica IS 'FECHA Y HORA DE ULTIMA MODIFICACION';

/******************************************************************************/
/*                                                                            */
/*  INSERTA ESTADOS APLICABLES POR MODULO Y ENTIDAD                           */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_estado (
    sis_estado_modulo,
    sis_estado_entidad,
    sis_estado_codigo,
    sis_estado_nombre,
    sis_estado_descripcion,
    sis_estado_orden,
    usuario_crea
)
VALUES
    ('SISTEMA', 'SIS_USUARIOS', 'ACTIVO', 'ACTIVO', 'USUARIO HABILITADO PARA INGRESAR AL SISTEMA', 1, 1),
    ('SISTEMA', 'SIS_USUARIOS', 'INACTIVO', 'INACTIVO', 'USUARIO DESHABILITADO TEMPORALMENTE', 2, 1),
    ('SISTEMA', 'SIS_USUARIOS', 'ELIMINADO', 'ELIMINADO', 'USUARIO ELIMINADO LOGICAMENTE', 3, 1),
    ('SISTEMA', 'SIS_USUARIOS', 'BLOQUEADO', 'BLOQUEADO', 'USUARIO BLOQUEADO POR SEGURIDAD', 4, 1),

    ('COMPRAS', 'COM_PROVEEDOR', 'ACTIVO', 'ACTIVO', 'PROVEEDOR DISPONIBLE PARA OPERACIONES', 1, 1),
    ('COMPRAS', 'COM_PROVEEDOR', 'INACTIVO', 'INACTIVO', 'PROVEEDOR DESHABILITADO TEMPORALMENTE', 2, 1),
    ('COMPRAS', 'COM_PROVEEDOR', 'ELIMINADO', 'ELIMINADO', 'PROVEEDOR ELIMINADO LOGICAMENTE', 3, 1),

    ('COMPRAS', 'COM_DOCUMENTO', 'BORRADOR', 'BORRADOR', 'DOCUMENTO DE COMPRA EN EDICION', 1, 1),
    ('COMPRAS', 'COM_DOCUMENTO', 'REGISTRADO', 'REGISTRADO', 'DOCUMENTO DE COMPRA REGISTRADO CONTABLEMENTE', 2, 1),
    ('COMPRAS', 'COM_DOCUMENTO', 'ANULADO', 'ANULADO', 'DOCUMENTO DE COMPRA ANULADO', 3, 1),
    ('COMPRAS', 'COM_DOCUMENTO', 'PENDIENTE', 'PENDIENTE', 'DOCUMENTO DE COMPRA PENDIENTE DE PROCESO', 4, 1),
    ('COMPRAS', 'COM_DOCUMENTO', 'RECHAZADO', 'RECHAZADO', 'DOCUMENTO DE COMPRA RECHAZADO', 5, 1),

    ('VENTAS', 'VEN_CLIENTE', 'ACTIVO', 'ACTIVO', 'CLIENTE DISPONIBLE PARA OPERACIONES', 1, 1),
    ('VENTAS', 'VEN_CLIENTE', 'INACTIVO', 'INACTIVO', 'CLIENTE DESHABILITADO TEMPORALMENTE', 2, 1),
    ('VENTAS', 'VEN_CLIENTE', 'ELIMINADO', 'ELIMINADO', 'CLIENTE ELIMINADO LOGICAMENTE', 3, 1),

    ('VENTAS', 'VEN_DOCUMENTO', 'BORRADOR', 'BORRADOR', 'DOCUMENTO DE VENTA EN EDICION', 1, 1),
    ('VENTAS', 'VEN_DOCUMENTO', 'EMITIDO', 'EMITIDO', 'DOCUMENTO DE VENTA EMITIDO', 2, 1),
    ('VENTAS', 'VEN_DOCUMENTO', 'AUTORIZADO', 'AUTORIZADO', 'DOCUMENTO DE VENTA AUTORIZADO', 3, 1),
    ('VENTAS', 'VEN_DOCUMENTO', 'ANULADO', 'ANULADO', 'DOCUMENTO DE VENTA ANULADO', 4, 1),
    ('VENTAS', 'VEN_DOCUMENTO', 'RECHAZADO', 'RECHAZADO', 'DOCUMENTO DE VENTA RECHAZADO', 5, 1),
    ('VENTAS', 'VEN_DOCUMENTO', 'PENDIENTE', 'PENDIENTE', 'DOCUMENTO DE VENTA PENDIENTE DE PROCESO', 6, 1),

    ('INVENTARIO', 'INV_PRODUCTO', 'ACTIVO', 'ACTIVO', 'PRODUCTO DISPONIBLE PARA OPERACIONES', 1, 1),
    ('INVENTARIO', 'INV_PRODUCTO', 'INACTIVO', 'INACTIVO', 'PRODUCTO DESHABILITADO TEMPORALMENTE', 2, 1),
    ('INVENTARIO', 'INV_PRODUCTO', 'ELIMINADO', 'ELIMINADO', 'PRODUCTO ELIMINADO LOGICAMENTE', 3, 1),

    ('INVENTARIO', 'INV_BODEGA', 'ACTIVO', 'ACTIVO', 'BODEGA DISPONIBLE PARA OPERACIONES', 1, 1),
    ('INVENTARIO', 'INV_BODEGA', 'INACTIVO', 'INACTIVO', 'BODEGA DESHABILITADA TEMPORALMENTE', 2, 1),
    ('INVENTARIO', 'INV_BODEGA', 'ELIMINADO', 'ELIMINADO', 'BODEGA ELIMINADA LOGICAMENTE', 3, 1),

    ('INVENTARIO', 'INV_MOVIMIENTOS', 'BORRADOR', 'BORRADOR', 'MOVIMIENTO DE INVENTARIO EN EDICION', 1, 1),
    ('INVENTARIO', 'INV_MOVIMIENTOS', 'REGISTRADO', 'REGISTRADO', 'MOVIMIENTO DE INVENTARIO REGISTRADO', 2, 1),
    ('INVENTARIO', 'INV_MOVIMIENTOS', 'ANULADO', 'ANULADO', 'MOVIMIENTO DE INVENTARIO ANULADO', 3, 1),

    ('CONTABILIDAD', 'CON_ASIENTO', 'BORRADOR', 'BORRADOR', 'ASIENTO CONTABLE EN EDICION', 1, 1),
    ('CONTABILIDAD', 'CON_ASIENTO', 'REGISTRADO', 'REGISTRADO', 'ASIENTO CONTABLE REGISTRADO', 2, 1),
    ('CONTABILIDAD', 'CON_ASIENTO', 'ANULADO', 'ANULADO', 'ASIENTO CONTABLE ANULADO', 3, 1),

    ('CONTABILIDAD', 'CON_PLAN_CUENTAS', 'ACTIVO', 'ACTIVO', 'CUENTA CONTABLE DISPONIBLE PARA USO', 1, 1),
    ('CONTABILIDAD', 'CON_PLAN_CUENTAS', 'INACTIVO', 'INACTIVO', 'CUENTA CONTABLE DESHABILITADA TEMPORALMENTE', 2, 1),
    ('CONTABILIDAD', 'CON_PLAN_CUENTAS', 'ELIMINADO', 'ELIMINADO', 'CUENTA CONTABLE ELIMINADA LOGICAMENTE', 3, 1)
ON CONFLICT (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo) DO NOTHING;

/******************************************************************************/
/*                                                                            */
/*  AGREGA NUEVAS RELACIONES HACIA EL CATALOGO DE ESTADOS                     */
/*                                                                            */
/******************************************************************************/

ALTER TABLE public.sis_usuarios ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.com_proveedor ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.com_documento ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.ven_cliente ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.ven_documento ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.inv_producto ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.inv_bodega ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.inv_movimientos ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.con_asiento ADD COLUMN sis_estado_id BIGINT;
ALTER TABLE public.con_plan_cuentas ADD COLUMN sis_estado_id BIGINT;

COMMENT ON COLUMN public.sis_usuarios.sis_estado_id IS 'ESTADO ACTUAL DEL USUARIO';
COMMENT ON COLUMN public.com_proveedor.sis_estado_id IS 'ESTADO ACTUAL DEL PROVEEDOR';
COMMENT ON COLUMN public.com_documento.sis_estado_id IS 'ESTADO ACTUAL DEL DOCUMENTO DE COMPRA';
COMMENT ON COLUMN public.ven_cliente.sis_estado_id IS 'ESTADO ACTUAL DEL CLIENTE';
COMMENT ON COLUMN public.ven_documento.sis_estado_id IS 'ESTADO ACTUAL DEL DOCUMENTO DE VENTA';
COMMENT ON COLUMN public.inv_producto.sis_estado_id IS 'ESTADO ACTUAL DEL PRODUCTO';
COMMENT ON COLUMN public.inv_bodega.sis_estado_id IS 'ESTADO ACTUAL DE LA BODEGA';
COMMENT ON COLUMN public.inv_movimientos.sis_estado_id IS 'ESTADO ACTUAL DEL MOVIMIENTO DE INVENTARIO';
COMMENT ON COLUMN public.con_asiento.sis_estado_id IS 'ESTADO ACTUAL DEL ASIENTO CONTABLE';
COMMENT ON COLUMN public.con_plan_cuentas.sis_estado_id IS 'ESTADO ACTUAL DE LA CUENTA CONTABLE';

/******************************************************************************/
/*                                                                            */
/*  MIGRA LOS VALORES ANTERIORES HACIA SIS_ESTADO_ID                          */
/*                                                                            */
/******************************************************************************/

UPDATE public.sis_usuarios t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'SISTEMA'
  AND e.sis_estado_entidad = 'SIS_USUARIOS'
  AND e.sis_estado_codigo = CASE t.sis_usuarios_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      WHEN 1 THEN 'ACTIVO'
      WHEN 2 THEN 'BLOQUEADO'
      ELSE 'INACTIVO'
  END;

UPDATE public.com_proveedor t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'COMPRAS'
  AND e.sis_estado_entidad = 'COM_PROVEEDOR'
  AND e.sis_estado_codigo = CASE t.com_proveedor_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      WHEN 1 THEN 'ACTIVO'
      ELSE 'INACTIVO'
  END;

UPDATE public.com_documento t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'COMPRAS'
  AND e.sis_estado_entidad = 'COM_DOCUMENTO'
  AND e.sis_estado_codigo = CASE t.com_documento_estado
      WHEN -1 THEN 'ANULADO'
      WHEN 0 THEN 'BORRADOR'
      WHEN 1 THEN 'REGISTRADO'
      ELSE 'PENDIENTE'
  END;

UPDATE public.ven_cliente t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'VENTAS'
  AND e.sis_estado_entidad = 'VEN_CLIENTE'
  AND e.sis_estado_codigo = CASE t.ven_cliente_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      WHEN 1 THEN 'ACTIVO'
      ELSE 'INACTIVO'
  END;

UPDATE public.ven_documento t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'VENTAS'
  AND e.sis_estado_entidad = 'VEN_DOCUMENTO'
  AND e.sis_estado_codigo = CASE t.ven_documento_estado
      WHEN -1 THEN 'ANULADO'
      WHEN 0 THEN 'BORRADOR'
      WHEN 1 THEN 'EMITIDO'
      ELSE 'PENDIENTE'
  END;

UPDATE public.inv_producto t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'INVENTARIO'
  AND e.sis_estado_entidad = 'INV_PRODUCTO'
  AND e.sis_estado_codigo = CASE t.inv_producto_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      WHEN 1 THEN 'ACTIVO'
      ELSE 'INACTIVO'
  END;

UPDATE public.inv_bodega t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'INVENTARIO'
  AND e.sis_estado_entidad = 'INV_BODEGA'
  AND e.sis_estado_codigo = CASE t.inv_bodega_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      WHEN 1 THEN 'ACTIVO'
      ELSE 'INACTIVO'
  END;

UPDATE public.inv_movimientos t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'INVENTARIO'
  AND e.sis_estado_entidad = 'INV_MOVIMIENTOS'
  AND e.sis_estado_codigo = CASE t.inv_movimientos_estado
      WHEN -1 THEN 'ANULADO'
      WHEN 0 THEN 'BORRADOR'
      WHEN 1 THEN 'REGISTRADO'
      ELSE 'BORRADOR'
  END;

UPDATE public.con_asiento t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'CONTABILIDAD'
  AND e.sis_estado_entidad = 'CON_ASIENTO'
  AND e.sis_estado_codigo = CASE t.con_asiento_estado
      WHEN -1 THEN 'ANULADO'
      WHEN 0 THEN 'BORRADOR'
      WHEN 1 THEN 'REGISTRADO'
      ELSE 'BORRADOR'
  END;

UPDATE public.con_plan_cuentas t
SET sis_estado_id = e.sis_estado_id
FROM public.sis_estado e
WHERE e.sis_estado_modulo = 'CONTABILIDAD'
  AND e.sis_estado_entidad = 'CON_PLAN_CUENTAS'
  AND e.sis_estado_codigo = CASE t.con_plan_cuentas_estado
      WHEN -1 THEN 'ELIMINADO'
      WHEN 0 THEN 'INACTIVO'
      WHEN 1 THEN 'ACTIVO'
      ELSE 'INACTIVO'
  END;

/******************************************************************************/
/*                                                                            */
/*  DEFINE VALORES POR DEFECTO Y OBLIGATORIEDAD                               */
/*                                                                            */
/******************************************************************************/

ALTER TABLE public.sis_usuarios ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.com_proveedor ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.com_documento ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.ven_cliente ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.ven_documento ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.inv_producto ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.inv_bodega ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.inv_movimientos ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.con_asiento ALTER COLUMN sis_estado_id SET NOT NULL;
ALTER TABLE public.con_plan_cuentas ALTER COLUMN sis_estado_id SET NOT NULL;

DO $$
DECLARE
    v_estado_id BIGINT;
BEGIN
    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_USUARIOS' AND sis_estado_codigo = 'ACTIVO';
    EXECUTE format('ALTER TABLE public.sis_usuarios ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'COMPRAS' AND sis_estado_entidad = 'COM_PROVEEDOR' AND sis_estado_codigo = 'ACTIVO';
    EXECUTE format('ALTER TABLE public.com_proveedor ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'COMPRAS' AND sis_estado_entidad = 'COM_DOCUMENTO' AND sis_estado_codigo = 'REGISTRADO';
    EXECUTE format('ALTER TABLE public.com_documento ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_CLIENTE' AND sis_estado_codigo = 'ACTIVO';
    EXECUTE format('ALTER TABLE public.ven_cliente ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_DOCUMENTO' AND sis_estado_codigo = 'EMITIDO';
    EXECUTE format('ALTER TABLE public.ven_documento ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'INVENTARIO' AND sis_estado_entidad = 'INV_PRODUCTO' AND sis_estado_codigo = 'ACTIVO';
    EXECUTE format('ALTER TABLE public.inv_producto ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'INVENTARIO' AND sis_estado_entidad = 'INV_BODEGA' AND sis_estado_codigo = 'ACTIVO';
    EXECUTE format('ALTER TABLE public.inv_bodega ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'INVENTARIO' AND sis_estado_entidad = 'INV_MOVIMIENTOS' AND sis_estado_codigo = 'REGISTRADO';
    EXECUTE format('ALTER TABLE public.inv_movimientos ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'CONTABILIDAD' AND sis_estado_entidad = 'CON_ASIENTO' AND sis_estado_codigo = 'REGISTRADO';
    EXECUTE format('ALTER TABLE public.con_asiento ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);

    SELECT sis_estado_id INTO v_estado_id FROM public.sis_estado WHERE sis_estado_modulo = 'CONTABILIDAD' AND sis_estado_entidad = 'CON_PLAN_CUENTAS' AND sis_estado_codigo = 'ACTIVO';
    EXECUTE format('ALTER TABLE public.con_plan_cuentas ALTER COLUMN sis_estado_id SET DEFAULT %s', v_estado_id);
END $$;

/******************************************************************************/
/*                                                                            */
/*  CREA LLAVES FORANEAS HACIA SIS_ESTADO                                     */
/*                                                                            */
/******************************************************************************/

ALTER TABLE public.sis_usuarios ADD CONSTRAINT sis_usuarios_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.com_proveedor ADD CONSTRAINT com_proveedor_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.com_documento ADD CONSTRAINT com_documento_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.ven_cliente ADD CONSTRAINT ven_cliente_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.ven_documento ADD CONSTRAINT ven_documento_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.inv_producto ADD CONSTRAINT inv_producto_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.inv_bodega ADD CONSTRAINT inv_bodega_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.inv_movimientos ADD CONSTRAINT inv_movimientos_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.con_asiento ADD CONSTRAINT con_asiento_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);
ALTER TABLE public.con_plan_cuentas ADD CONSTRAINT con_plan_cuentas_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES public.sis_estado (sis_estado_id);

/******************************************************************************/
/*                                                                            */
/*  CREA INDICES PARA BUSQUEDAS POR ESTADO                                    */
/*                                                                            */
/******************************************************************************/

CREATE INDEX idx_sis_usuarios_estado ON public.sis_usuarios (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_com_proveedor_estado ON public.com_proveedor (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_com_documento_estado ON public.com_documento (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_ven_cliente_estado ON public.ven_cliente (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_ven_documento_estado ON public.ven_documento (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_inv_producto_estado ON public.inv_producto (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_inv_bodega_estado ON public.inv_bodega (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_inv_movimientos_estado ON public.inv_movimientos (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_con_asiento_estado ON public.con_asiento (sis_empresa_id, sis_estado_id);
CREATE INDEX idx_con_plan_cuentas_estado ON public.con_plan_cuentas (sis_empresa_id, sis_estado_id);

/******************************************************************************/
/*                                                                            */
/*  ELIMINA COLUMNAS ANTIGUAS DE ESTADO                                       */
/*                                                                            */
/******************************************************************************/

ALTER TABLE public.sis_usuarios DROP COLUMN sis_usuarios_estado;
ALTER TABLE public.com_proveedor DROP COLUMN com_proveedor_estado;
ALTER TABLE public.com_documento DROP COLUMN com_documento_estado;
ALTER TABLE public.ven_cliente DROP COLUMN ven_cliente_estado;
ALTER TABLE public.ven_documento DROP COLUMN ven_documento_estado;
ALTER TABLE public.inv_producto DROP COLUMN inv_producto_estado;
ALTER TABLE public.inv_bodega DROP COLUMN inv_bodega_estado;
ALTER TABLE public.inv_movimientos DROP COLUMN inv_movimientos_estado;
ALTER TABLE public.con_asiento DROP COLUMN con_asiento_estado;
ALTER TABLE public.con_plan_cuentas DROP COLUMN con_plan_cuentas_estado;

COMMIT;
