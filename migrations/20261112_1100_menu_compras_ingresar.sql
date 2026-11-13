-- Compras: el menu "Documentos" pasa a llamarse "Ingresar".
UPDATE sis_menu
   SET sis_menu_nombre = 'Ingresar', fecha_modifica = now()
 WHERE sis_menu_url = '/compras/documentos' AND sis_menu_tipo = 'M';
