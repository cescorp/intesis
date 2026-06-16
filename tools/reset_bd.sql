-- =============================================================================
-- INTESIS — RESET BASE DE DATOS PARA PRUEBAS
-- Limpia todos los datos operativos y re-siembra empresa + admin + catálogos
-- por empresa (sis_iva, ven_forma_pago).
--
-- USO:
--   psql -U postgres -d intesis -f reset_bd.sql
-- =============================================================================

BEGIN;

-- -----------------------------------------------------------------------------
-- 1. TRUNCAR TODO LO OPERATIVO (incluye sis_iva y ven_forma_pago que son
--    por empresa, no globales)
-- -----------------------------------------------------------------------------

TRUNCATE TABLE
    -- Contabilidad
    con_asiento_detalle,
    con_asiento,
    con_conciliacion_bancaria,
    con_periodo,
    con_plan_cuentas,
    con_plantilla_integracion,
    con_retencion_concepto,
    -- Compras
    com_documento_detalle,
    com_documento,
    com_archivos_sri,
    com_proveedor,
    -- Ventas
    ven_documento_detalle,
    ven_cxc,
    ven_documento,
    ven_lista_precio_detalle,
    ven_lista_precio,
    ven_cliente,
    ven_forma_pago,
    -- Inventario
    inv_kardex,
    inv_movimientos_detalle,
    inv_movimientos,
    inv_stock,
    inv_codigo_proveedor,
    inv_producto,
    inv_bodega_usuarios,
    inv_bodega,
    inv_categoria,
    inv_marca,
    -- Sistema por empresa
    sis_iva,
    sis_bancos,
    sis_licencia,
    sis_archivos,
    sis_auditoria,
    -- Reportes
    rep_componente,
    rep_usuario_config,
    rep_reporte,
    -- Sistema core
    sis_perfil_permisos,
    sis_usuario_empresa,
    sis_secuencias,
    sis_perfil,
    sis_usuarios,
    sis_empresa
CASCADE;

-- Con CASCADE solo arrastra tablas que no están en esta lista y referencian
-- a las listadas. Las tablas catálogo globales (sis_estado, sis_menu,
-- sis_tipo_documento, sis_mensaje_errores, sis_modulo, sis_plan,
-- sis_plan_modulo) NO tienen FK a ninguna de las anteriores, así que
-- no se tocan.

-- -----------------------------------------------------------------------------
-- 2. EMPRESA DE PRUEBA
-- -----------------------------------------------------------------------------

INSERT INTO sis_empresa (
    sis_empresa_id,
    sis_empresa_ruc,
    sis_empresa_razon_social,
    sis_empresa_nombre_comercial,
    sis_empresa_direccion,
    sis_empresa_email,
    sis_empresa_obligado_contabilidad,
    sis_empresa_contribuyente_especial,
    sis_empresa_ambiente_sri,
    sis_estado_id,
    usuario_crea
)
VALUES (
    1,
    '9999999999999',
    'EMPRESA DE PRUEBA S.A.',
    'EMPRESA PRUEBA',
    'DIRECCIÓN MATRIZ',
    'admin@prueba.com',
    false,
    false,
    '1',
    (SELECT sis_estado_id FROM sis_estado
     WHERE sis_estado_modulo = 'SISTEMA'
       AND sis_estado_entidad = 'SIS_EMPRESA'
       AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
    1
);

SELECT setval(pg_get_serial_sequence('sis_empresa', 'sis_empresa_id'), 1, true);

-- -----------------------------------------------------------------------------
-- 3. USUARIO ADMINISTRADOR
--    Correo : cescorp@hotmail.es
--    Clave  : (misma clave actual — hash Argon2id)
-- -----------------------------------------------------------------------------

INSERT INTO sis_usuarios (
    sis_usuarios_id,
    sis_usuarios_nombre,
    sis_usuarios_correo,
    sis_usuarios_password,
    sis_estado_id,
    usuario_crea
)
VALUES (
    1,
    'ADMINISTRADOR',
    'cescorp@hotmail.es',
    '$argon2id$v=19$m=65536,t=4,p=1$Li9yRzIwQS9PNEs3Q09GYg$226mv3mxLYP8G8wmVstIYqIwDTrd1WT5SbYOvUb/zqQ',
    (SELECT sis_estado_id FROM sis_estado
     WHERE sis_estado_modulo = 'SISTEMA'
       AND sis_estado_entidad = 'SIS_USUARIOS'
       AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
    1
);

SELECT setval(pg_get_serial_sequence('sis_usuarios', 'sis_usuarios_id'), 1, true);

-- -----------------------------------------------------------------------------
-- 4. PERFILES BASE
-- -----------------------------------------------------------------------------

INSERT INTO sis_perfil (sis_empresa_id, sis_perfil_codigo, sis_perfil_nombre, sis_perfil_estado, usuario_crea)
VALUES
    (1, 'SUPERUSUARIO',   'SUPERUSUARIO',      1, 1),
    (1, 'GERENCIA',       'GERENCIA',           1, 1),
    (1, 'CONTADOR',       'CONTADOR',           1, 1),
    (1, 'GERENTE_VENTAS', 'GERENTE DE VENTAS',  1, 1),
    (1, 'VENDEDOR',       'VENDEDOR',           1, 1),
    (1, 'COMPRAS',        'COMPRAS',            1, 1),
    (1, 'BODEGUERO',      'BODEGUERO',          1, 1);

-- -----------------------------------------------------------------------------
-- 5. ASIGNAR ADMIN → SUPERUSUARIO
-- -----------------------------------------------------------------------------

INSERT INTO sis_usuario_empresa (
    sis_usuarios_id, sis_empresa_id, sis_perfil_id, sis_estado_id,
    sis_usuario_empresa_predeterminada, usuario_crea
)
SELECT 1, 1, p.sis_perfil_id,
    (SELECT sis_estado_id FROM sis_estado
     WHERE sis_estado_modulo = 'SISTEMA'
       AND sis_estado_entidad = 'SIS_USUARIOS'
       AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
    true, 1
FROM sis_perfil p
WHERE p.sis_empresa_id = 1 AND p.sis_perfil_codigo = 'SUPERUSUARIO';

-- -----------------------------------------------------------------------------
-- 6. PERMISOS
-- -----------------------------------------------------------------------------

INSERT INTO sis_perfil_permisos (sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea)
SELECT 1, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p CROSS JOIN sis_menu m
WHERE p.sis_empresa_id = 1
  AND p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_estado = 1;

INSERT INTO sis_perfil_permisos (sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea)
SELECT 1, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p CROSS JOIN sis_menu m
WHERE p.sis_empresa_id = 1
  AND p.sis_perfil_codigo IN ('GERENCIA', 'CONTADOR')
  AND m.sis_menu_url IN (
      '/sistema', '/sistema/empresas', '/sistema/empresas/ver',
      '/sistema/usuarios', '/sistema/usuarios/ver'
  );

-- -----------------------------------------------------------------------------
-- 7. BODEGA PRINCIPAL
-- -----------------------------------------------------------------------------

INSERT INTO inv_bodega (
    sis_empresa_id, inv_bodega_codigo, inv_bodega_nombre, inv_bodega_descripcion,
    inv_bodega_es_principal, inv_bodega_establecimiento, inv_bodega_punto_emision,
    sis_estado_id, usuario_crea
)
VALUES (
    1, 'BOD001', 'BODEGA PRINCIPAL', 'Bodega principal de la empresa',
    true, '001', '001',
    (SELECT sis_estado_id FROM sis_estado
     WHERE sis_estado_modulo = 'INVENTARIO'
       AND sis_estado_entidad = 'INV_BODEGA'
       AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
    1
);

-- -----------------------------------------------------------------------------
-- 8. IVA (tasas estándar Ecuador)
-- -----------------------------------------------------------------------------

INSERT INTO sis_iva (sis_empresa_id, sis_iva_valor, sis_iva_estado, usuario_crea)
VALUES
    (1,  0.00, 1, 1),
    (1, 15.00, 1, 1);

-- -----------------------------------------------------------------------------
-- 9. FORMAS DE PAGO
-- -----------------------------------------------------------------------------

INSERT INTO ven_forma_pago (sis_empresa_id, ven_forma_pago_nombre, ven_forma_pago_estado, ven_forma_pago_solicitar_datos, ven_forma_pago_calculadora, ven_forma_pago_codigo_sri, usuario_crea)
VALUES
    (1, 'EFECTIVO',          'A', 'N', 'S', '01', 1),
    (1, 'TARJETA DE CRÉDITO','A', 'S', 'N', '19', 1),
    (1, 'TARJETA DE DÉBITO', 'A', 'S', 'N', '20', 1),
    (1, 'TRANSFERENCIA',     'A', 'S', 'N', '16', 1),
    (1, 'CHEQUE',            'A', 'S', 'N', '21', 1),
    (1, 'CRÉDITO',           'A', 'N', 'N', '18', 1);

COMMIT;
