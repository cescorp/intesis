-- =============================================================================
-- INTESIS — LIMPIAR DATOS DE PRUEBA
-- Borra solo datos de negocio (ventas, compras, inventario).
-- NO toca configuración del sistema (empresa, usuarios, menús, estados, etc.)
-- Ejecutar en ambiente de DESARROLLO únicamente.
-- =============================================================================

BEGIN;

-- VENTAS
DELETE FROM public.ven_cxc;
DELETE FROM public.ven_documento_detalle;
DELETE FROM public.ven_documento;
DELETE FROM public.ven_cliente;

-- COMPRAS
DELETE FROM public.com_documento_det;
DELETE FROM public.com_documento;
DELETE FROM public.com_codigo_proveedor;
DELETE FROM public.com_proveedor;

-- INVENTARIO
DELETE FROM public.inv_kardex;
DELETE FROM public.inv_movimiento_det;
DELETE FROM public.inv_movimiento;
DELETE FROM public.inv_stock;
DELETE FROM public.sis_archivos;
DELETE FROM public.inv_producto;
DELETE FROM public.inv_categoria;
DELETE FROM public.inv_marca;
DELETE FROM public.inv_bodega_usuarios;
DELETE FROM public.inv_bodega;

-- Resetear secuencias de documentos (opcional — comentar si no se quiere)
UPDATE public.sis_secuencias SET sis_secuencias_siguiente = 1;

COMMIT;
