-- =============================================================================
-- INTESIS — LIMPIAR DATOS TRANSACCIONALES DE PRUEBA
-- Borra SOLO documentos y movimientos (ventas, compras, inventario), reinicia
-- la numeración de secuencias a 0 (el próximo documento sale como 001) y
-- restablece el usuario principal (id=1) a su correo y clave conocidos.
-- NO toca: empresas, perfiles/permisos, clientes, proveedores,
-- productos, categorías, marcas, bodegas, formas de pago, catálogo de IVA,
-- ni el resto de usuarios.
-- Ejecutar en ambiente de DESARROLLO únicamente.
-- =============================================================================

BEGIN;

-- VENTAS (orden por FK: hijos antes que padres)
DELETE FROM public.ven_cxc;
DELETE FROM public.ven_documento_forma_pago;
DELETE FROM public.ven_documento_detalle;
DELETE FROM public.ven_documento;

-- COMPRAS
DELETE FROM public.com_archivos_sri;
DELETE FROM public.com_documento_detalle;
DELETE FROM public.com_documento;

-- INVENTARIO
DELETE FROM public.inv_kardex;
DELETE FROM public.inv_movimientos_detalle;
DELETE FROM public.inv_movimientos;
DELETE FROM public.inv_stock;

-- Reiniciar numeración de documentos (Factura, Nota Venta, Proforma, Compra, Ajuste, Transferencia)
UPDATE public.sis_secuencias SET sis_secuencias_actual = 0;

-- Reiniciar autoincrementales de las tablas vaciadas
ALTER SEQUENCE public.ven_documento_ven_documento_id_seq RESTART WITH 1;
ALTER SEQUENCE public.com_documento_com_documento_id_seq RESTART WITH 1;

-- Restablecer usuario/contraseña principal (id=1) — correo original + clave conocida.
-- Clave en texto plano: Admin123456  (cambiala apenas entres)
UPDATE public.sis_usuarios
SET sis_usuarios_correo   = 'cescorp@hotmail.es',
    sis_usuarios_password = '$argon2id$v=19$m=65536,t=4,p=1$WHNTeTRmME5qYTJnMnQ4NQ$Zq8hqsCxIzR11w71qbEBpNf0WuxOlINeeCx/RJJwnJY'
WHERE sis_usuarios_id = 1;

COMMIT;
