BEGIN;

INSERT INTO sis_estado (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo, sis_estado_nombre, sis_estado_descripcion, sis_estado_orden, sis_estado_activo, usuario_crea)
VALUES
    ('SISTEMA', 'SIS_TIPO_DOCUMENTO', 'ACTIVO', 'Activo', 'REGISTRO DISPONIBLE PARA USO', 1, TRUE, 1),
    ('SISTEMA', 'SIS_TIPO_DOCUMENTO', 'INACTIVO', 'Inactivo', 'REGISTRO NO DISPONIBLE PARA USO', 2, TRUE, 1),
    ('SISTEMA', 'SIS_SECUENCIAS', 'ACTIVO', 'Activo', 'REGISTRO DISPONIBLE PARA USO', 1, TRUE, 1),
    ('SISTEMA', 'SIS_SECUENCIAS', 'INACTIVO', 'Inactivo', 'REGISTRO NO DISPONIBLE PARA USO', 2, TRUE, 1),
    ('SISTEMA', 'SIS_SECUENCIAS', 'AGOTADO', 'Agotado', 'SECUENCIA SIN NUMEROS DISPONIBLES', 3, TRUE, 1)
ON CONFLICT (sis_estado_modulo, sis_estado_entidad, sis_estado_codigo) DO NOTHING;

DO $$
BEGIN
    IF to_regclass('public.sis_tipo_documento') IS NULL AND to_regclass('public.sis_documento_tipo') IS NOT NULL THEN
        ALTER TABLE sis_documento_tipo RENAME TO sis_tipo_documento;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS sis_tipo_documento (
    sis_tipo_documento_id BIGSERIAL PRIMARY KEY,
    sis_tipo_documento_codigo VARCHAR(30) NOT NULL,
    sis_tipo_documento_nombre VARCHAR(120) NOT NULL,
    sis_tipo_documento_modulo VARCHAR(30) NOT NULL DEFAULT 'SISTEMA',
    sis_tipo_documento_descripcion VARCHAR(250) DEFAULT '',
    sis_tipo_documento_afecta_contabilidad BOOLEAN NOT NULL DEFAULT FALSE,
    sis_tipo_documento_afecta_inventario BOOLEAN NOT NULL DEFAULT FALSE,
    sis_estado_id BIGINT,
    usuario_crea BIGINT DEFAULT 1,
    usuario_modifica BIGINT,
    fecha_crea TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    fecha_modifica TIMESTAMP WITHOUT TIME ZONE
);

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_tipo_documento' AND column_name = 'sis_documento_tipo_id') THEN
        ALTER TABLE sis_tipo_documento RENAME COLUMN sis_documento_tipo_id TO sis_tipo_documento_id;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_tipo_documento' AND column_name = 'sis_documento_tipo_codigo') THEN
        ALTER TABLE sis_tipo_documento RENAME COLUMN sis_documento_tipo_codigo TO sis_tipo_documento_codigo;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_tipo_documento' AND column_name = 'sis_documento_tipo_nombre') THEN
        ALTER TABLE sis_tipo_documento RENAME COLUMN sis_documento_tipo_nombre TO sis_tipo_documento_nombre;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_tipo_documento' AND column_name = 'sis_documento_tipo_afecta_cuentas') THEN
        ALTER TABLE sis_tipo_documento RENAME COLUMN sis_documento_tipo_afecta_cuentas TO sis_tipo_documento_afecta_contabilidad;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_tipo_documento' AND column_name = 'sis_documento_tipo_afecta_stock') THEN
        ALTER TABLE sis_tipo_documento RENAME COLUMN sis_documento_tipo_afecta_stock TO sis_tipo_documento_afecta_inventario;
    END IF;
END $$;

ALTER TABLE sis_tipo_documento ADD COLUMN IF NOT EXISTS sis_tipo_documento_modulo VARCHAR(30) NOT NULL DEFAULT 'SISTEMA';
ALTER TABLE sis_tipo_documento ADD COLUMN IF NOT EXISTS sis_tipo_documento_descripcion VARCHAR(250) DEFAULT '';
ALTER TABLE sis_tipo_documento ADD COLUMN IF NOT EXISTS sis_estado_id BIGINT;

UPDATE sis_tipo_documento
SET sis_tipo_documento_modulo = CASE
        WHEN sis_tipo_documento_codigo IN ('FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO', 'RETENCION') THEN 'VENTAS'
        WHEN sis_tipo_documento_codigo IN ('AJUSTE') THEN 'INVENTARIO'
        WHEN sis_tipo_documento_codigo IN ('ASIENTO') THEN 'CONTABILIDAD'
        ELSE sis_tipo_documento_modulo
    END,
    sis_estado_id = COALESCE(sis_estado_id, (
        SELECT sis_estado_id FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA'
          AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
          AND sis_estado_codigo = 'ACTIVO'
        LIMIT 1
    ));

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_tipo_documento' AND column_name = 'sis_documento_tipo_estado') THEN
        EXECUTE $sql$
            UPDATE sis_tipo_documento
            SET sis_estado_id = (
                SELECT sis_estado_id FROM sis_estado
                WHERE sis_estado_modulo = 'SISTEMA'
                  AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
                  AND sis_estado_codigo = CASE WHEN sis_tipo_documento.sis_documento_tipo_estado = 1 THEN 'ACTIVO' ELSE 'INACTIVO' END
                LIMIT 1
            )
        $sql$;
        ALTER TABLE sis_tipo_documento DROP COLUMN sis_documento_tipo_estado;
    END IF;
END $$;

ALTER TABLE sis_tipo_documento ALTER COLUMN sis_estado_id SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sis_tipo_documento_estado_fk') THEN
        ALTER TABLE sis_tipo_documento ADD CONSTRAINT sis_tipo_documento_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES sis_estado(sis_estado_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_sis_tipo_documento_modulo_codigo') THEN
        ALTER TABLE sis_tipo_documento ADD CONSTRAINT uq_sis_tipo_documento_modulo_codigo UNIQUE (sis_tipo_documento_modulo, sis_tipo_documento_codigo);
    END IF;
END $$;

COMMENT ON TABLE sis_tipo_documento IS 'TIPOS DE DOCUMENTO DEL SISTEMA';
COMMENT ON COLUMN sis_tipo_documento.sis_tipo_documento_id IS 'IDENTIFICADOR DEL TIPO DE DOCUMENTO';
COMMENT ON COLUMN sis_tipo_documento.sis_tipo_documento_codigo IS 'CODIGO CORTO DEL TIPO DE DOCUMENTO';
COMMENT ON COLUMN sis_tipo_documento.sis_tipo_documento_nombre IS 'NOMBRE DEL TIPO DE DOCUMENTO';
COMMENT ON COLUMN sis_tipo_documento.sis_tipo_documento_modulo IS 'MODULO DONDE SE USA EL TIPO DE DOCUMENTO';
COMMENT ON COLUMN sis_tipo_documento.sis_tipo_documento_descripcion IS 'DESCRIPCION DEL TIPO DE DOCUMENTO';
COMMENT ON COLUMN sis_tipo_documento.sis_tipo_documento_afecta_contabilidad IS 'INDICA SI GENERA MOVIMIENTO CONTABLE';
COMMENT ON COLUMN sis_tipo_documento.sis_tipo_documento_afecta_inventario IS 'INDICA SI GENERA MOVIMIENTO DE INVENTARIO';
COMMENT ON COLUMN sis_tipo_documento.sis_estado_id IS 'ESTADO DEL TIPO DE DOCUMENTO';

CREATE TABLE IF NOT EXISTS sis_secuencias (
    sis_secuencia_id BIGSERIAL PRIMARY KEY,
    sis_empresa_id BIGINT NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    sis_tipo_documento_id BIGINT NOT NULL REFERENCES sis_tipo_documento(sis_tipo_documento_id),
    sis_secuencia_establecimiento VARCHAR(3) NOT NULL,
    sis_secuencia_punto_emision VARCHAR(3) NOT NULL,
    sis_secuencia_desde BIGINT NOT NULL DEFAULT 1,
    sis_secuencia_actual BIGINT NOT NULL DEFAULT 1,
    sis_secuencia_hasta BIGINT NOT NULL DEFAULT 999999999,
    sis_secuencia_observacion VARCHAR(250) DEFAULT '',
    sis_estado_id BIGINT,
    usuario_crea BIGINT DEFAULT 1,
    usuario_modifica BIGINT,
    fecha_crea TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now(),
    fecha_modifica TIMESTAMP WITHOUT TIME ZONE
);

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencias_id') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencias_id TO sis_secuencia_id;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_documento_tipo_id') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_documento_tipo_id TO sis_tipo_documento_id;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencias_establecimiento') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencias_establecimiento TO sis_secuencia_establecimiento;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencias_punto_emision') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencias_punto_emision TO sis_secuencia_punto_emision;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencias_secuencia') THEN
        ALTER TABLE sis_secuencias RENAME COLUMN sis_secuencias_secuencia TO sis_secuencia_actual;
    END IF;
END $$;

ALTER TABLE sis_secuencias ADD COLUMN IF NOT EXISTS sis_secuencia_desde BIGINT NOT NULL DEFAULT 1;
ALTER TABLE sis_secuencias ADD COLUMN IF NOT EXISTS sis_secuencia_hasta BIGINT NOT NULL DEFAULT 999999999;
ALTER TABLE sis_secuencias ADD COLUMN IF NOT EXISTS sis_secuencia_observacion VARCHAR(250) DEFAULT '';
ALTER TABLE sis_secuencias ADD COLUMN IF NOT EXISTS sis_estado_id BIGINT;

UPDATE sis_secuencias
SET sis_secuencia_establecimiento = lpad(regexp_replace(sis_secuencia_establecimiento, '\D', '', 'g'), 3, '0'),
    sis_secuencia_punto_emision = lpad(regexp_replace(sis_secuencia_punto_emision, '\D', '', 'g'), 3, '0'),
    sis_secuencia_desde = COALESCE(sis_secuencia_desde, 1),
    sis_secuencia_hasta = COALESCE(NULLIF(sis_secuencia_hasta, 0), 999999999),
    sis_estado_id = COALESCE(sis_estado_id, (
        SELECT sis_estado_id FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA'
          AND sis_estado_entidad = 'SIS_SECUENCIAS'
          AND sis_estado_codigo = 'ACTIVO'
        LIMIT 1
    ));

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sis_secuencias' AND column_name = 'sis_secuencias_estado') THEN
        EXECUTE $sql$
            UPDATE sis_secuencias
            SET sis_estado_id = (
                SELECT sis_estado_id FROM sis_estado
                WHERE sis_estado_modulo = 'SISTEMA'
                  AND sis_estado_entidad = 'SIS_SECUENCIAS'
                  AND sis_estado_codigo = CASE WHEN sis_secuencias.sis_secuencias_estado = 1 THEN 'ACTIVO' ELSE 'INACTIVO' END
                LIMIT 1
            )
        $sql$;
        ALTER TABLE sis_secuencias DROP COLUMN sis_secuencias_estado;
    END IF;
END $$;

ALTER TABLE sis_secuencias ALTER COLUMN sis_estado_id SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sis_secuencias_estado_fk') THEN
        ALTER TABLE sis_secuencias ADD CONSTRAINT sis_secuencias_estado_fk FOREIGN KEY (sis_estado_id) REFERENCES sis_estado(sis_estado_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_sis_secuencia_empresa_tipo_punto') THEN
        ALTER TABLE sis_secuencias ADD CONSTRAINT uq_sis_secuencia_empresa_tipo_punto UNIQUE (sis_empresa_id, sis_tipo_documento_id, sis_secuencia_establecimiento, sis_secuencia_punto_emision);
    END IF;
END $$;

COMMENT ON TABLE sis_secuencias IS 'SECUENCIAS DE DOCUMENTOS POR EMPRESA';
COMMENT ON COLUMN sis_secuencias.sis_secuencia_id IS 'IDENTIFICADOR DE LA SECUENCIA';
COMMENT ON COLUMN sis_secuencias.sis_empresa_id IS 'EMPRESA DUEÑA DE LA SECUENCIA';
COMMENT ON COLUMN sis_secuencias.sis_tipo_documento_id IS 'TIPO DE DOCUMENTO DE LA SECUENCIA';
COMMENT ON COLUMN sis_secuencias.sis_secuencia_establecimiento IS 'CODIGO DE ESTABLECIMIENTO DE TRES DIGITOS';
COMMENT ON COLUMN sis_secuencias.sis_secuencia_punto_emision IS 'CODIGO DE PUNTO DE EMISION DE TRES DIGITOS';
COMMENT ON COLUMN sis_secuencias.sis_secuencia_desde IS 'NUMERO INICIAL PERMITIDO';
COMMENT ON COLUMN sis_secuencias.sis_secuencia_actual IS 'NUMERO ACTUAL DE EMISION';
COMMENT ON COLUMN sis_secuencias.sis_secuencia_hasta IS 'NUMERO FINAL PERMITIDO';
COMMENT ON COLUMN sis_secuencias.sis_secuencia_observacion IS 'OBSERVACION INTERNA DE LA SECUENCIA';
COMMENT ON COLUMN sis_secuencias.sis_estado_id IS 'ESTADO DE LA SECUENCIA';

WITH estado_tipo AS (
    SELECT sis_estado_id FROM sis_estado
    WHERE sis_estado_modulo = 'SISTEMA'
      AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
      AND sis_estado_codigo = 'ACTIVO'
    LIMIT 1
)
INSERT INTO sis_tipo_documento (
    sis_tipo_documento_codigo, sis_tipo_documento_nombre, sis_tipo_documento_modulo,
    sis_tipo_documento_descripcion, sis_tipo_documento_afecta_contabilidad,
    sis_tipo_documento_afecta_inventario, sis_estado_id, usuario_crea
)
SELECT datos.codigo, datos.nombre, datos.modulo, datos.descripcion, datos.contabilidad, datos.inventario, estado_tipo.sis_estado_id, 1
FROM estado_tipo
CROSS JOIN (
    VALUES
        ('FACTURA', 'Factura', 'VENTAS', 'DOCUMENTO DE VENTA PARA FACTURACION ELECTRONICA', TRUE, TRUE),
        ('NOTA_CREDITO', 'Nota de credito', 'VENTAS', 'DOCUMENTO PARA DISMINUIR VALORES FACTURADOS', TRUE, TRUE),
        ('NOTA_DEBITO', 'Nota de debito', 'VENTAS', 'DOCUMENTO PARA AUMENTAR VALORES FACTURADOS', TRUE, FALSE),
        ('RETENCION', 'Retencion', 'VENTAS', 'COMPROBANTE DE RETENCION ELECTRONICA', TRUE, FALSE),
        ('AJUSTE', 'Ajuste de inventario', 'INVENTARIO', 'DOCUMENTO PARA AJUSTAR EXISTENCIAS', FALSE, TRUE),
        ('ASIENTO', 'Asiento contable', 'CONTABILIDAD', 'DOCUMENTO CONTABLE INTERNO', TRUE, FALSE)
) AS datos(codigo, nombre, modulo, descripcion, contabilidad, inventario)
ON CONFLICT (sis_tipo_documento_modulo, sis_tipo_documento_codigo) DO NOTHING;

WITH configuracion AS (
    SELECT sis_menu_id
    FROM sis_menu
    WHERE sis_menu_url = '/sistema/configuracion'
    LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, configuracion.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM configuracion
CROSS JOIN (
    VALUES
        ('Crear tipo documento', '/sistema/configuracion/tipos/crear', 'bi bi-plus-square', 40),
        ('Editar tipo documento', '/sistema/configuracion/tipos/editar', 'bi bi-pencil-square', 41),
        ('Activar tipo documento', '/sistema/configuracion/tipos/activar', 'bi bi-toggle-on', 42),
        ('Inactivar tipo documento', '/sistema/configuracion/tipos/inactivar', 'bi bi-toggle-off', 43),
        ('Crear secuencia', '/sistema/configuracion/secuencias/crear', 'bi bi-plus-square', 44),
        ('Editar secuencia', '/sistema/configuracion/secuencias/editar', 'bi bi-pencil-square', 45),
        ('Activar secuencia', '/sistema/configuracion/secuencias/activar', 'bi bi-toggle-on', 46),
        ('Inactivar secuencia', '/sistema/configuracion/secuencias/inactivar', 'bi bi-toggle-off', 47)
) AS datos(nombre, url, icono, orden)
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url LIKE '/sistema/configuracion/%'
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
