-- =============================================================================
-- ADJUNTOS: datos que necesita AlmacenArchivosServicio en sis_archivos
-- y permisos para adjuntar archivos a facturas.
-- =============================================================================

-- Nombre visible (editable por el usuario; el fisico es un codigo unico),
-- tipo MIME real detectado al subir y tamano en bytes.
ALTER TABLE sis_archivos ADD COLUMN IF NOT EXISTS sis_archivos_nombre VARCHAR(150) NULL;
ALTER TABLE sis_archivos ADD COLUMN IF NOT EXISTS sis_archivos_mime   VARCHAR(100) NULL;
ALTER TABLE sis_archivos ADD COLUMN IF NOT EXISTS sis_archivos_tamano BIGINT       NULL;

COMMENT ON COLUMN sis_archivos.sis_archivos_nombre IS 'NOMBRE VISIBLE DEL ARCHIVO (EDITABLE)';
COMMENT ON COLUMN sis_archivos.sis_archivos_mime   IS 'TIPO MIME REAL DETECTADO AL SUBIR';
COMMENT ON COLUMN sis_archivos.sis_archivos_tamano IS 'TAMANO DEL ARCHIVO EN BYTES';

-- Las imagenes de productos ya existentes usan el nombre fisico como visible
UPDATE sis_archivos SET sis_archivos_nombre = sis_archivos_archivo WHERE sis_archivos_nombre IS NULL;

-- Permisos de adjuntos de facturas (hijos de /ventas/facturas, tipo 'B', sin entrada en sidebar)
INSERT INTO sis_menu (sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url, sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea)
SELECT nombre, m.sis_menu_id, 'bi bi-paperclip', url, orden, 1, 'B', 1
FROM sis_menu m
CROSS JOIN (VALUES
    ('Ver adjuntos factura',      '/ventas/facturas/adjuntos/ver',      40),
    ('Subir adjuntos factura',    '/ventas/facturas/adjuntos/subir',    41),
    ('Renombrar adjunto factura', '/ventas/facturas/adjuntos/editar',   42),
    ('Eliminar adjunto factura',  '/ventas/facturas/adjuntos/eliminar', 43)
) AS acciones(nombre, url, orden)
WHERE m.sis_menu_url = '/ventas/facturas'
  AND NOT EXISTS (SELECT 1 FROM sis_menu sm WHERE sm.sis_menu_url = acciones.url);

INSERT INTO sis_perfil_permisos (sis_perfil_id, sis_empresa_id, sis_menu_id, usuario_crea)
SELECT p.sis_perfil_id, p.sis_empresa_id, sm.sis_menu_id, 1
FROM sis_perfil p
CROSS JOIN sis_menu sm
WHERE lower(p.sis_perfil_nombre) = 'superusuario'
  AND sm.sis_menu_url LIKE '/ventas/facturas/adjuntos/%'
  AND NOT EXISTS (
      SELECT 1 FROM sis_perfil_permisos pp
      WHERE pp.sis_perfil_id = p.sis_perfil_id AND pp.sis_empresa_id = p.sis_empresa_id AND pp.sis_menu_id = sm.sis_menu_id
  );
