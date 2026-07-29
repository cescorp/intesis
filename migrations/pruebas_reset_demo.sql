-- =============================================================================
-- INTESIS — RESET COMPLETO PARA PRUEBAS
-- Borra TODOS los datos y deja solo:
--   - EMPRESA DEMO (RUC 9999999999999)
--   - Usuario ADMINISTRADOR (admin@demo.com / 123456)
--   - Perfil SUPERUSUARIO vinculado a la empresa demo
--   - Secuencias base para todos los tipos de documento
--   - Formas de pago (catálogo)
--   - Perfiles y permisos (se conservan)
-- USAR SOLO EN DESARROLLO
-- =============================================================================

BEGIN;

-- -----------------------------------------------------------------------------
-- 1. BORRAR DATOS DE NEGOCIO (orden FK)
-- -----------------------------------------------------------------------------

-- Ventas
DELETE FROM public.ven_cxc;
DELETE FROM public.ven_documento_detalle;
DELETE FROM public.ven_documento;
DELETE FROM public.ven_lista_precio_detalle;
DELETE FROM public.ven_lista_precio;
DELETE FROM public.ven_cliente;

-- Compras
DELETE FROM public.com_documento_detalle;
DELETE FROM public.com_documento;
DELETE FROM public.com_archivos_sri;
DELETE FROM public.inv_codigo_proveedor;
DELETE FROM public.com_proveedor;

-- Secuencias (antes que bodegas por FK RESTRICT)
DELETE FROM public.sis_secuencias;

-- Inventario
DELETE FROM public.inv_kardex;
DELETE FROM public.inv_movimientos_detalle;
DELETE FROM public.inv_movimientos;
DELETE FROM public.inv_stock;
DELETE FROM public.inv_bodega_usuarios;
DELETE FROM public.inv_producto;
DELETE FROM public.inv_categoria;
DELETE FROM public.inv_marca;
DELETE FROM public.inv_bodega;

-- Archivos (imágenes productos)
DELETE FROM public.sis_archivos;

-- Sistema
DELETE FROM public.sis_usuario_empresa;
DELETE FROM public.sis_perfil_permisos;
DELETE FROM public.sis_perfil;
DELETE FROM public.ven_forma_pago;
DELETE FROM public.sis_iva;
DELETE FROM public.sis_usuarios;
DELETE FROM public.sis_empresa;

-- Resetear sequences de PostgreSQL
ALTER SEQUENCE public.sis_empresa_sis_empresa_id_seq RESTART WITH 1;
ALTER SEQUENCE public.sis_usuarios_sis_usuarios_id_seq RESTART WITH 1;
ALTER SEQUENCE public.sis_perfil_sis_perfil_id_seq RESTART WITH 1;
ALTER SEQUENCE public.sis_usuario_empresa_sis_usuario_empresa_id_seq RESTART WITH 1;
ALTER SEQUENCE public.sis_secuencias_sis_secuencias_id_seq RESTART WITH 1;
ALTER SEQUENCE public.inv_bodega_inv_bodega_id_seq RESTART WITH 1;
ALTER SEQUENCE public.inv_producto_inv_producto_id_seq RESTART WITH 1;
ALTER SEQUENCE public.ven_documento_ven_documento_id_seq RESTART WITH 1;
ALTER SEQUENCE public.com_documento_com_documento_id_seq RESTART WITH 1;

-- -----------------------------------------------------------------------------
-- 2. CREAR EMPRESA DEMO
-- -----------------------------------------------------------------------------

INSERT INTO public.sis_empresa (
    sis_empresa_ruc,
    sis_empresa_razon_social,
    sis_empresa_nombre_comercial,
    sis_empresa_direccion,
    sis_empresa_telefono,
    sis_empresa_email,
    sis_empresa_obligado_contabilidad,
    sis_empresa_contribuyente_especial,
    sis_estado_id,
    sis_empresa_ambiente_sri,
    usuario_crea,
    fecha_crea
) VALUES (
    '9999999999999',
    'EMPRESA DEMO S.A.',
    'EMPRESA DEMO',
    'DIRECCIÓN DEMO 123',
    '0999999999',
    'demo@empresa.com',
    false,
    false,
    (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='SISTEMA' AND sis_estado_entidad='SIS_EMPRESA' AND sis_estado_codigo='ACTIVO'),
    '1',
    1,
    now()
);

-- -----------------------------------------------------------------------------
-- 3. CREAR USUARIO ADMINISTRADOR
-- -----------------------------------------------------------------------------
-- Contraseña: 123456  (hash bcrypt)

INSERT INTO public.sis_usuarios (
    sis_usuarios_nombre,
    sis_usuarios_correo,
    sis_usuarios_password,
    sis_estado_id,
    usuario_crea,
    fecha_crea
) VALUES (
    'ADMINISTRADOR',
    'admin@demo.com',
    '$argon2id$v=19$m=65536,t=4,p=1$dDQyOC9ZRXR0d1JvMU1Tdw$oHJEs22iVnIPuv8caoXOW6eNt81jMK3IN8OJCZ/H+zk',
    (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='SISTEMA' AND sis_estado_entidad='SIS_USUARIOS' AND sis_estado_codigo='ACTIVO'),
    1,
    now()
);

-- -----------------------------------------------------------------------------
-- 4. CREAR PERFIL SUPERUSUARIO PARA LA EMPRESA DEMO
-- -----------------------------------------------------------------------------

INSERT INTO public.sis_perfil (
    sis_empresa_id,
    sis_perfil_codigo,
    sis_perfil_nombre,
    sis_perfil_estado,
    usuario_crea,
    fecha_crea
) VALUES (
    (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'),
    'SUPERUSUARIO',
    'SUPERUSUARIO',
    1,
    1,
    now()
);

-- -----------------------------------------------------------------------------
-- 5. ASIGNAR TODOS LOS PERMISOS AL PERFIL SUPERUSUARIO
-- -----------------------------------------------------------------------------

INSERT INTO public.sis_perfil_permisos (sis_perfil_id, sis_menu_id, sis_empresa_id, usuario_crea, fecha_crea)
SELECT
    (SELECT sis_perfil_id FROM sis_perfil WHERE sis_perfil_codigo = 'SUPERUSUARIO' AND sis_empresa_id = (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999')),
    sis_menu_id,
    (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'),
    1,
    now()
FROM public.sis_menu
ON CONFLICT DO NOTHING;

-- -----------------------------------------------------------------------------
-- 6. VINCULAR USUARIO A EMPRESA CON PERFIL SUPERUSUARIO
-- -----------------------------------------------------------------------------

INSERT INTO public.sis_usuario_empresa (
    sis_usuarios_id,
    sis_empresa_id,
    sis_perfil_id,
    sis_estado_id,
    sis_usuario_empresa_predeterminada,
    usuario_crea,
    fecha_crea
) VALUES (
    (SELECT sis_usuarios_id FROM sis_usuarios WHERE sis_usuarios_correo = 'admin@demo.com'),
    (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'),
    (SELECT sis_perfil_id FROM sis_perfil WHERE sis_perfil_codigo = 'SUPERUSUARIO' AND sis_empresa_id = (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999')),
    (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='SISTEMA' AND sis_estado_entidad='SIS_USUARIOS' AND sis_estado_codigo='ACTIVO'),
    true,
    1,
    now()
);

-- -----------------------------------------------------------------------------
-- 7. FORMAS DE PAGO BASE
-- -----------------------------------------------------------------------------

INSERT INTO public.ven_forma_pago (ven_forma_pago_nombre, ven_forma_pago_calculadora, sis_empresa_id, usuario_crea, fecha_crea) VALUES
    ('EFECTIVO',         'S', (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'), 1, now()),
    ('TARJETA CRÉDITO',  'N', (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'), 1, now()),
    ('TARJETA DÉBITO',   'N', (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'), 1, now()),
    ('TRANSFERENCIA',    'N', (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'), 1, now()),
    ('CRÉDITO',          'N', (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'), 1, now());

-- -----------------------------------------------------------------------------
-- 8. IVA BASE
-- -----------------------------------------------------------------------------

INSERT INTO public.sis_iva (sis_iva_valor, sis_iva_estado, sis_empresa_id, usuario_crea, fecha_crea) VALUES
    (15.00, 1, (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'), 1, now()),
    ( 0.00, 1, (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'), 1, now());

-- -----------------------------------------------------------------------------
-- 9. SECUENCIAS BASE PARA TODOS LOS TIPOS DE DOCUMENTO
-- -----------------------------------------------------------------------------

INSERT INTO public.sis_secuencias (
    sis_empresa_id,
    sis_tipo_documento_id,
    sis_secuencias_establecimiento,
    sis_secuencias_punto_emision,
    sis_secuencias_actual,
    sis_secuencias_desde,
    sis_secuencias_hasta,
    sis_estado_id,
    usuario_crea,
    fecha_crea
)
SELECT
    (SELECT sis_empresa_id FROM sis_empresa WHERE sis_empresa_ruc = '9999999999999'),
    sis_tipo_documento_id,
    '001',
    '001',
    0,
    1,
    999999999,
    (SELECT sis_estado_id FROM sis_estado WHERE sis_estado_modulo='SISTEMA' AND sis_estado_entidad='SIS_SECUENCIAS' AND sis_estado_codigo='ACTIVO'
     LIMIT 1),
    1,
    now()
FROM public.sis_tipo_documento;

COMMIT;

-- =============================================================================
-- CREDENCIALES DE ACCESO:
--   Usuario: admin@demo.com
--   Clave:   123456
-- =============================================================================
