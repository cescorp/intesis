-- =============================================================================
-- MODULO COMPRAS: IMPORTACION SRI (XML / TXT)
-- =============================================================================

-- 1. COLUMNAS NUEVAS EN com_documento
ALTER TABLE com_documento
    ADD COLUMN IF NOT EXISTS com_documento_origen            VARCHAR(10)  NOT NULL DEFAULT 'MANUAL',
    ADD COLUMN IF NOT EXISTS com_documento_clave_acceso      VARCHAR(49)  NULL,
    ADD COLUMN IF NOT EXISTS com_documento_num_autorizacion  VARCHAR(49)  NULL,
    ADD COLUMN IF NOT EXISTS com_documento_fecha_autorizacion TIMESTAMP   NULL,
    ADD COLUMN IF NOT EXISTS com_documento_ruc_emisor        VARCHAR(20)  NULL,
    ADD COLUMN IF NOT EXISTS com_documento_razon_emisor      VARCHAR(200) NULL;

-- 2. COLUMNA DESCRIPCION SRI EN DETALLE
ALTER TABLE com_documento_detalle
    ADD COLUMN IF NOT EXISTS com_documento_detalle_descripcion_sri VARCHAR(300) NULL;

-- 3. TABLA DE ARCHIVOS SRI (TXT y XML)
CREATE TABLE IF NOT EXISTS com_archivos_sri (
    com_archivos_sri_id           SERIAL       PRIMARY KEY,
    sis_empresa_id                INTEGER      NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    com_archivos_sri_tipo         VARCHAR(3)   NOT NULL CHECK (com_archivos_sri_tipo IN ('TXT','XML')),
    com_archivos_sri_archivo      TEXT         NOT NULL,
    com_archivos_sri_estado       VARCHAR(20)  NOT NULL DEFAULT 'PENDIENTE'
        CHECK (com_archivos_sri_estado IN ('PENDIENTE','PROCESADO','DESCARGADO','SIN_INFORMACION','ERROR')),
    com_archivos_sri_clave        VARCHAR(49)  NULL,
    com_archivos_sri_total_lineas INTEGER      NULL,
    com_archivos_sri_mensaje      TEXT         NULL,
    usuario_crea                  INTEGER      NOT NULL,
    fecha_crea                    TIMESTAMP    NOT NULL DEFAULT now(),
    usuario_modifica              INTEGER      NULL,
    fecha_modifica                TIMESTAMP    NULL
);

-- 4. INDICE UNICO: evita duplicar el mismo XML por clave en la misma empresa
CREATE UNIQUE INDEX IF NOT EXISTS uq_com_archivos_sri_clave
    ON com_archivos_sri (sis_empresa_id, com_archivos_sri_clave)
    WHERE com_archivos_sri_tipo = 'XML' AND com_archivos_sri_clave IS NOT NULL;

-- 5. MENU: Subir XML y Procesar TXT como acciones de Documentos
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, icono, url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Subir XML SRI',    '/compras/documentos/subir-xml',    'bi bi-file-earmark-code', 5),
    ('Procesar TXT SRI', '/compras/documentos/procesar-txt', 'bi bi-file-earmark-text', 6)
) AS acciones(nombre, url, icono, orden)
WHERE m.sis_menu_url = '/compras/documentos'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

-- 6. PERMISOS SUPERUSUARIO
INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url IN ('/compras/documentos/subir-xml', '/compras/documentos/procesar-txt')
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id  = p.sis_perfil_id
        AND pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_menu_id    = sm.sis_menu_id
  );
