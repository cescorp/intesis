BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CONVIERTE CONFIGURACION EN MENU CONTENEDOR DE TERCER NIVEL                */
/*                                                                            */
/******************************************************************************/

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
SELECT datos.nombre, configuracion.sis_menu_id, datos.icono, datos.url, datos.orden, 1, 'M', 1
FROM configuracion
CROSS JOIN (
    VALUES
        ('Estados', 'bi bi-toggle2-on', '/sistema/configuracion/estados', 1),
        ('Mensajes Error', 'bi bi-chat-left-text', '/sistema/configuracion/mensajes-error', 2),
        ('Tipos documento', 'bi bi-receipt-cutoff', '/sistema/configuracion/tipos-documento', 3)
) AS datos(nombre, icono, url, orden)
WHERE NOT EXISTS (
    SELECT 1 FROM sis_menu WHERE sis_menu_url = datos.url
);

UPDATE sis_menu
SET sis_menu_estado = 0,
    usuario_modifica = 1,
    fecha_modifica = now()
WHERE sis_menu_url = '/sistema/configuracion/ver';

UPDATE sis_menu accion
SET sis_menu_padre = padre.sis_menu_id,
    usuario_modifica = 1,
    fecha_modifica = now()
FROM sis_menu padre
WHERE padre.sis_menu_url = '/sistema/configuracion/estados'
  AND accion.sis_menu_url IN (
      '/sistema/configuracion/estados/crear',
      '/sistema/configuracion/estados/editar',
      '/sistema/configuracion/estados/activar',
      '/sistema/configuracion/estados/inactivar'
  );

UPDATE sis_menu accion
SET sis_menu_padre = padre.sis_menu_id,
    usuario_modifica = 1,
    fecha_modifica = now()
FROM sis_menu padre
WHERE padre.sis_menu_url = '/sistema/configuracion/mensajes-error'
  AND accion.sis_menu_url IN (
      '/sistema/configuracion/mensajes/crear',
      '/sistema/configuracion/mensajes/editar',
      '/sistema/configuracion/mensajes/activar',
      '/sistema/configuracion/mensajes/inactivar'
  );

UPDATE sis_menu accion
SET sis_menu_padre = padre.sis_menu_id,
    usuario_modifica = 1,
    fecha_modifica = now()
FROM sis_menu padre
WHERE padre.sis_menu_url = '/sistema/configuracion/tipos-documento'
  AND accion.sis_menu_url IN (
      '/sistema/configuracion/tipos/crear',
      '/sistema/configuracion/tipos/editar',
      '/sistema/configuracion/tipos/activar',
      '/sistema/configuracion/tipos/inactivar',
      '/sistema/configuracion/secuencias/crear',
      '/sistema/configuracion/secuencias/editar',
      '/sistema/configuracion/secuencias/activar',
      '/sistema/configuracion/secuencias/inactivar'
  );

INSERT INTO sis_perfil_permisos (
    sis_empresa_id, sis_perfil_id, sis_menu_id, sis_perfil_permisos_estado, usuario_crea
)
SELECT p.sis_empresa_id, p.sis_perfil_id, m.sis_menu_id, 1, 1
FROM sis_perfil p
CROSS JOIN sis_menu m
WHERE p.sis_perfil_codigo = 'SUPERUSUARIO'
  AND m.sis_menu_url IN (
      '/sistema/configuracion/estados',
      '/sistema/configuracion/mensajes-error',
      '/sistema/configuracion/tipos-documento'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM sis_perfil_permisos pp
      WHERE pp.sis_empresa_id = p.sis_empresa_id
        AND pp.sis_perfil_id = p.sis_perfil_id
        AND pp.sis_menu_id = m.sis_menu_id
  );

COMMIT;
