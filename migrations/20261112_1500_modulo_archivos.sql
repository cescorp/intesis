-- =============================================================================
-- MODULO ARCHIVOS: carpetas por usuario, versiones, compartir y registro de accesos.
-- Los archivos fisicos los guarda AlmacenArchivosServicio (ver reglas.md, regla 5)
-- y se registran en sis_archivos con sis_archivos_tabla = 'SIS_CARPETA' e
-- id_padre = carpeta (0 = raiz "Mis archivos" del usuario que lo subio).
-- =============================================================================

CREATE TABLE IF NOT EXISTS sis_carpeta (
    sis_carpeta_id        BIGSERIAL    PRIMARY KEY,
    sis_empresa_id        BIGINT       NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    sis_carpeta_padre_id  BIGINT       NULL REFERENCES sis_carpeta(sis_carpeta_id),
    sis_carpeta_nombre    VARCHAR(150) NOT NULL,
    sis_carpeta_estado    SMALLINT     NOT NULL DEFAULT 1,
    usuario_crea          BIGINT       NOT NULL,
    fecha_crea            TIMESTAMP    NOT NULL DEFAULT now(),
    usuario_modifica      BIGINT       NULL,
    fecha_modifica        TIMESTAMP    NULL
);
COMMENT ON TABLE  sis_carpeta IS 'CARPETAS DEL MODULO ARCHIVOS; EL DUENO ES usuario_crea';
COMMENT ON COLUMN sis_carpeta.sis_carpeta_padre_id IS 'CARPETA CONTENEDORA; NULL = RAIZ DEL DUENO';
CREATE INDEX IF NOT EXISTS ix_sis_carpeta_padre ON sis_carpeta (sis_empresa_id, usuario_crea, sis_carpeta_padre_id, sis_carpeta_estado);

-- Versiones: todas las versiones de un archivo comparten sis_archivos_original_id
-- (el id de la version 1; la version 1 lo tiene NULL).
ALTER TABLE sis_archivos ADD COLUMN IF NOT EXISTS sis_archivos_version     INTEGER NOT NULL DEFAULT 1;
ALTER TABLE sis_archivos ADD COLUMN IF NOT EXISTS sis_archivos_original_id BIGINT  NULL;
COMMENT ON COLUMN sis_archivos.sis_archivos_version     IS 'NUMERO DE VERSION (1 = ORIGINAL)';
COMMENT ON COLUMN sis_archivos.sis_archivos_original_id IS 'ID DE LA VERSION 1; NULL EN LA PROPIA VERSION 1';

-- Compartir: una carpeta (incluye todo su contenido) o un archivo (todas sus versiones;
-- sis_compartido_objeto_id es el id de la version 1). Revocar = estado 0 con fecha_modifica.
CREATE TABLE IF NOT EXISTS sis_compartido (
    sis_compartido_id         BIGSERIAL   PRIMARY KEY,
    sis_empresa_id            BIGINT      NOT NULL REFERENCES sis_empresa(sis_empresa_id),
    sis_compartido_tipo       VARCHAR(10) NOT NULL CHECK (sis_compartido_tipo IN ('CARPETA', 'ARCHIVO')),
    sis_compartido_objeto_id  BIGINT      NOT NULL,
    sis_usuarios_id           BIGINT      NOT NULL,
    sis_compartido_estado     SMALLINT    NOT NULL DEFAULT 1,
    usuario_crea              BIGINT      NOT NULL,
    fecha_crea                TIMESTAMP   NOT NULL DEFAULT now(),
    usuario_modifica          BIGINT      NULL,
    fecha_modifica            TIMESTAMP   NULL
);
COMMENT ON TABLE sis_compartido IS 'CARPETAS/ARCHIVOS COMPARTIDOS CON USUARIOS (SOLO VER Y DESCARGAR); usuario_crea = QUIEN COMPARTIO, fecha_crea = DESDE';
CREATE INDEX IF NOT EXISTS ix_sis_compartido_usuario ON sis_compartido (sis_usuarios_id, sis_compartido_estado);
CREATE INDEX IF NOT EXISTS ix_sis_compartido_objeto  ON sis_compartido (sis_compartido_tipo, sis_compartido_objeto_id);

-- Quien abrio o descargo cada version y cuando
CREATE TABLE IF NOT EXISTS sis_archivo_acceso (
    sis_archivo_acceso_id      BIGSERIAL   PRIMARY KEY,
    sis_archivos_id            BIGINT      NOT NULL REFERENCES sis_archivos(sis_archivos_id),
    sis_usuarios_id            BIGINT      NOT NULL,
    sis_archivo_acceso_accion  VARCHAR(10) NOT NULL CHECK (sis_archivo_acceso_accion IN ('ABRIR', 'DESCARGAR')),
    fecha_crea                 TIMESTAMP   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ix_sis_archivo_acceso_archivo ON sis_archivo_acceso (sis_archivos_id);

-- Menu "Archivos" (primer nivel, enlace directo) y sus acciones
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT 'Archivos', NULL, 'bi bi-folder2-open', '/archivos', 45, 1, 'M', 1
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = '/archivos');

INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-dot', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Subir archivos',        '/archivos/subir',     1),
    ('Crear carpetas',        '/archivos/carpetas',  2),
    ('Compartir archivos',    '/archivos/compartir', 3),
    ('Eliminar archivos',     '/archivos/eliminar',  4)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/archivos'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND (sm.sis_menu_url = '/archivos' OR sm.sis_menu_url LIKE '/archivos/%')
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id = p.sis_perfil_id AND pp.sis_empresa_id = p.sis_empresa_id AND pp.sis_menu_id = sm.sis_menu_id
  );
