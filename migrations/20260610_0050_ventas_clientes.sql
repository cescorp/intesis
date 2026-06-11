-- =============================================================================
-- MODULO VENTAS: TABLA CLIENTES
-- sis_menu usa: sis_menu_padre (bigint), sis_menu_estado (smallint), sis_menu_tipo ('M'/'B')
-- permisos en: sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
-- =============================================================================

-- 1. ESTADOS PARA VEN_CLIENTE
INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'VENTAS', 'VEN_CLIENTE', 'ACTIVO', 'Activo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_CLIENTE' AND sis_estado_codigo = 'ACTIVO'
);

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'VENTAS', 'VEN_CLIENTE', 'INACTIVO', 'Inactivo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_CLIENTE' AND sis_estado_codigo = 'INACTIVO'
);

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, usuario_crea)
SELECT 'VENTAS', 'VEN_CLIENTE', 'ELIMINADO', 'Eliminado', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_estado
    WHERE sis_estado_modulo = 'VENTAS' AND sis_estado_entidad = 'VEN_CLIENTE' AND sis_estado_codigo = 'ELIMINADO'
);

-- 2. TABLA PRINCIPAL
CREATE TABLE IF NOT EXISTS ven_cliente (
    ven_cliente_id                      SERIAL PRIMARY KEY,                                           -- ID UNICO DEL CLIENTE
    sis_empresa_id                      INTEGER      NOT NULL REFERENCES sis_empresa(sis_empresa_id), -- EMPRESA PROPIETARIA
    ven_cliente_tipo_identificacion     VARCHAR(20)  NOT NULL,                                        -- RUC, CEDULA, PASAPORTE, CONSUMIDOR_FINAL
    ven_cliente_identificacion          VARCHAR(20)  NOT NULL,                                        -- NUMERO DE IDENTIFICACION
    ven_cliente_razon_social            VARCHAR(300) NOT NULL,                                        -- NOMBRE O RAZON SOCIAL
    ven_cliente_telefono                VARCHAR(20)  NOT NULL,                                        -- TELEFONO DE CONTACTO
    ven_cliente_correo                  VARCHAR(150) NOT NULL,                                        -- CORREO ELECTRONICO
    ven_cliente_direccion               VARCHAR(300) NOT NULL,                                        -- DIRECCION FISCAL
    sis_estado_id                       INTEGER      NOT NULL REFERENCES sis_estado(sis_estado_id),   -- ESTADO DEL REGISTRO
    usuario_crea                        INTEGER      NOT NULL,                                        -- USUARIO QUE CREO EL REGISTRO
    fecha_crea                          TIMESTAMP    NOT NULL DEFAULT now(),                          -- FECHA DE CREACION
    usuario_modifica                    INTEGER      NULL,                                            -- USUARIO QUE MODIFICO
    fecha_modifica                      TIMESTAMP    NULL,                                            -- FECHA DE MODIFICACION
    CONSTRAINT uq_cliente_identificacion UNIQUE (sis_empresa_id, ven_cliente_identificacion)
);

COMMENT ON TABLE  ven_cliente                                 IS 'CLIENTES POR EMPRESA PARA MODULO DE VENTAS';
COMMENT ON COLUMN ven_cliente.ven_cliente_tipo_identificacion IS 'VALORES: RUC, CEDULA, PASAPORTE, CONSUMIDOR_FINAL';

-- 3. MENU PADRE: Ventas
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Ventas', NULL, 'bi bi-receipt', '/ventas', 40, 1, 'M', 1
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/ventas');

-- 4. MENU HIJO: Clientes
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Clientes', m.sis_menu_id, 'bi bi-person-lines-fill', '/ventas/clientes', 10, 1, 'M', 1
FROM sis_menu m
WHERE m.sis_menu_url = '/ventas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/ventas/clientes');

-- 5. ACCIONES (tipo 'B' = boton)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver clientes',      '/ventas/clientes/ver',       1),
    ('Crear cliente',     '/ventas/clientes/crear',     2),
    ('Editar cliente',    '/ventas/clientes/editar',    3),
    ('Activar cliente',   '/ventas/clientes/activar',   4),
    ('Inactivar cliente', '/ventas/clientes/inactivar', 5)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/ventas/clientes'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 6. PERMISOS SUPERUSUARIO
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN (
      '/ventas',
      '/ventas/clientes',
      '/ventas/clientes/ver',
      '/ventas/clientes/crear',
      '/ventas/clientes/editar',
      '/ventas/clientes/activar',
      '/ventas/clientes/inactivar'
  )
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );

-- 7. MENSAJE CONFIRMACION INACTIVAR CLIENTE
INSERT INTO sis_mensaje_errores (
    sis_mensaje_errores_codigo,
    sis_mensaje_errores_tipo,
    sis_mensaje_errores_titulo,
    sis_mensaje_errores_mensaje,
    sis_mensaje_errores_icono,
    sis_mensaje_errores_modulo,
    sis_mensaje_errores_entidad,
    usuario_crea
)
VALUES (
    'CONFIRMAR_INACTIVAR_CLIENTE',
    'CONFIRMACION',
    'Inactivar cliente',
    '¿Desea inactivar este cliente?',
    'warning',
    'VENTAS',
    'VEN_CLIENTE',
    1
)
ON CONFLICT (sis_mensaje_errores_codigo) DO NOTHING;
