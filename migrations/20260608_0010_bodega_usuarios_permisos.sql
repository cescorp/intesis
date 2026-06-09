BEGIN;

/******************************************************************************/
/*                                                                            */
/*  AGREGA PERMISOS DE USUARIOS POR BODEGA                                    */
/*                                                                            */
/******************************************************************************/

COMMENT ON TABLE inv_bodega_usuarios IS 'USUARIOS AUTORIZADOS PARA USAR CADA BODEGA';
COMMENT ON COLUMN inv_bodega_usuarios.inv_bodega_usuarios_id IS 'IDENTIFICADOR DEL PERMISO DE BODEGA';
COMMENT ON COLUMN inv_bodega_usuarios.sis_empresa_id IS 'EMPRESA DEL PERMISO DE BODEGA';
COMMENT ON COLUMN inv_bodega_usuarios.sis_usuarios_id IS 'USUARIO AUTORIZADO PARA LA BODEGA';
COMMENT ON COLUMN inv_bodega_usuarios.inv_bodega_id IS 'BODEGA AUTORIZADA AL USUARIO';
COMMENT ON COLUMN inv_bodega_usuarios.inv_bodega_usuarios_estado IS 'ESTADO DEL PERMISO 1 ACTIVO 0 INACTIVO -1 ELIMINADO';
COMMENT ON COLUMN inv_bodega_usuarios.inv_bodega_usuarios_predeterminada IS 'INDICA SI ES LA BODEGA PREDETERMINADA DEL USUARIO';
COMMENT ON COLUMN inv_bodega_usuarios.usuario_crea IS 'USUARIO QUE CREO EL PERMISO';
COMMENT ON COLUMN inv_bodega_usuarios.usuario_modifica IS 'USUARIO QUE MODIFICO EL PERMISO';
COMMENT ON COLUMN inv_bodega_usuarios.fecha_crea IS 'FECHA DE CREACION DEL PERMISO';
COMMENT ON COLUMN inv_bodega_usuarios.fecha_modifica IS 'FECHA DE MODIFICACION DEL PERMISO';

CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_bodega_usuarios_activo
    ON inv_bodega_usuarios (sis_empresa_id, sis_usuarios_id, inv_bodega_id)
    WHERE inv_bodega_usuarios_estado = 1;

CREATE UNIQUE INDEX IF NOT EXISTS uq_inv_bodega_usuarios_predeterminada
    ON inv_bodega_usuarios (sis_empresa_id, sis_usuarios_id)
    WHERE inv_bodega_usuarios_estado = 1
      AND inv_bodega_usuarios_predeterminada = TRUE;

WITH bodegas_id AS (
    SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/bodegas' LIMIT 1
)
INSERT INTO sis_menu (
    sis_menu_nombre, sis_menu_padre, sis_menu_icono, sis_menu_url,
    sis_menu_orden, sis_menu_estado, sis_menu_tipo, usuario_crea
)
SELECT datos.nombre, bodegas_id.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'B', 1
FROM bodegas_id
CROSS JOIN (
    VALUES
        ('Usuarios bodega', 'bi bi-people', '/inventario/bodegas/usuarios', 7),
        ('Guardar usuario bodega', 'bi bi-save2', '/inventario/bodegas/usuarios/guardar', 8),
        ('Activar usuario bodega', 'bi bi-toggle-on', '/inventario/bodegas/usuarios/activar', 9),
        ('Inactivar usuario bodega', 'bi bi-toggle-off', '/inventario/bodegas/usuarios/inactivar', 10)
) AS datos(nombre, icono, url, orden)
WHERE NOT EXISTS (SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url);

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url LIKE '/inventario/bodegas/usuarios%'
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
