BEGIN;

/******************************************************************************/
/*                                                                            */
/*  COMPLETA SIS_ARCHIVOS PARA GALERIAS DE PRODUCTOS                          */
/*                                                                            */
/******************************************************************************/

ALTER TABLE sis_archivos
    ADD COLUMN IF NOT EXISTS sis_archivos_principal BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS sis_archivos_orden INTEGER NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS sis_archivos_tipo VARCHAR(30) NOT NULL DEFAULT 'ARCHIVO';

COMMENT ON TABLE sis_archivos IS 'ARCHIVOS RELACIONADOS A ENTIDADES DEL SISTEMA';
COMMENT ON COLUMN sis_archivos.sis_archivos_id IS 'IDENTIFICADOR DEL ARCHIVO';
COMMENT ON COLUMN sis_archivos.sis_empresa_id IS 'EMPRESA DUEÑA DEL ARCHIVO';
COMMENT ON COLUMN sis_archivos.sis_archivos_archivo IS 'NOMBRE FISICO DEL ARCHIVO';
COMMENT ON COLUMN sis_archivos.sis_archivos_tabla IS 'ENTIDAD RELACIONADA AL ARCHIVO';
COMMENT ON COLUMN sis_archivos.sis_archivos_id_padre IS 'IDENTIFICADOR DEL REGISTRO PADRE';
COMMENT ON COLUMN sis_archivos.sis_archivos_estado IS 'ESTADO LOGICO DEL ARCHIVO';
COMMENT ON COLUMN sis_archivos.sis_archivos_ubicacion IS 'RUTA RELATIVA DEL ARCHIVO';
COMMENT ON COLUMN sis_archivos.sis_archivos_principal IS 'INDICA SI ES EL ARCHIVO PRINCIPAL';
COMMENT ON COLUMN sis_archivos.sis_archivos_orden IS 'ORDEN DE VISUALIZACION DEL ARCHIVO';
COMMENT ON COLUMN sis_archivos.sis_archivos_tipo IS 'TIPO GENERAL DEL ARCHIVO';

CREATE INDEX IF NOT EXISTS idx_sis_archivos_padre
    ON sis_archivos (sis_empresa_id, sis_archivos_tabla, sis_archivos_id_padre, sis_archivos_estado);

CREATE UNIQUE INDEX IF NOT EXISTS uq_sis_archivos_principal_producto
    ON sis_archivos (sis_empresa_id, sis_archivos_tabla, sis_archivos_id_padre)
    WHERE sis_archivos_estado = 1
      AND sis_archivos_principal = TRUE
      AND sis_archivos_tabla = 'INV_PRODUCTO';

WITH productos AS (
    SELECT sis_menu_id
    FROM sis_menu
    WHERE sis_menu_url = '/inventario/productos'
    LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, productos.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM productos
CROSS JOIN (
    VALUES
        ('Ver galeria producto', 'bi bi-images', '/inventario/productos/archivos/listar', 20),
        ('Subir imagen producto', 'bi bi-cloud-upload', '/inventario/productos/archivos/subir', 21),
        ('Ver imagen producto', 'bi bi-image', '/inventario/productos/archivos/ver', 22),
        ('Principal imagen producto', 'bi bi-star', '/inventario/productos/archivos/principal', 23),
        ('Eliminar imagen producto', 'bi bi-trash3', '/inventario/productos/archivos/eliminar', 24)
) AS datos(nombre, icono, url, orden)
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url IN (
      '/inventario/productos/archivos/listar',
      '/inventario/productos/archivos/subir',
      '/inventario/productos/archivos/ver',
      '/inventario/productos/archivos/principal',
      '/inventario/productos/archivos/eliminar'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
