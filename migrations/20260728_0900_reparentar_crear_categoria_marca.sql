BEGIN;

/******************************************************************************/
/*                                                                            */
/*  "CREAR CATEGORIA" Y "CREAR MARCA" SOLO SE USAN COMO CREACION RAPIDA       */
/*  DESDE EL FORMULARIO DE PRODUCTO (BOTON "+"), NO COMO PANTALLAS PROPIAS.  */
/*  SUS MENUS PADRE "CATEGORIAS"/"MARCAS" ESTAN INACTIVOS (SIS_MENU_ESTADO   */
/*  = 0), LO QUE LOS DEJABA INVISIBLES EN EL ARBOL DE PERMISOS DE PERFILES   */
/*  (PerfilModelo::listarMenusPermisos() FILTRA POR PADRE ACTIVO). SE LOS    */
/*  REPARENTA BAJO "PRODUCTOS" (MENU ACTIVO) PARA QUE PUEDAN OTORGARSE POR   */
/*  PERFIL SIN NECESIDAD DE UN MENU PROPIO.                                  */
/*                                                                            */
/******************************************************************************/

UPDATE sis_menu
SET sis_menu_padre = (SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/productos'),
    sis_menu_orden = 6
WHERE sis_menu_url = '/inventario/categorias/crear';

UPDATE sis_menu
SET sis_menu_padre = (SELECT sis_menu_id FROM sis_menu WHERE sis_menu_url = '/inventario/productos'),
    sis_menu_orden = 7
WHERE sis_menu_url = '/inventario/marcas/crear';

COMMIT;
