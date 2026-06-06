BEGIN;

/******************************************************************************/
/*                                                                            */
/*  HOMOLOGA CODIGOS USADOS POR SWEETALERT                                    */
/*                                                                            */
/******************************************************************************/

INSERT INTO public.sis_mensaje_errores (
    sis_mensaje_errores_codigo,
    sis_mensaje_errores_tipo,
    sis_mensaje_errores_titulo,
    sis_mensaje_errores_mensaje,
    sis_mensaje_errores_icono,
    sis_mensaje_errores_modulo,
    sis_mensaje_errores_entidad,
    sis_mensaje_errores_activo,
    sis_mensaje_errores_tiempo,
    sis_mensaje_errores_posicion,
    usuario_crea
)
VALUES
    ('REGISTROS_LISTADOS', 'EXITO', 'Registros listados', 'Registros listados correctamente.', 'success', 'SISTEMA', 'GENERAL', TRUE, 1800, 2, 1),
    ('CONFIRMAR_INACTIVAR_PERFIL', 'CONFIRMACION', 'Inactivar perfil', 'El perfil quedara inactivo y no podra asignarse a usuarios.', 'warning', 'SISTEMA', 'SIS_PERFIL', TRUE, 0, 4, 1),
    ('CONFIRMAR_ELIMINAR_PERFIL', 'CONFIRMACION', 'Eliminar perfil', 'El perfil se eliminara logicamente si no tiene usuarios asignados.', 'warning', 'SISTEMA', 'SIS_PERFIL', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_MENU', 'CONFIRMACION', 'Inactivar menu', 'El menu dejara de mostrarse a los perfiles que lo tengan asignado.', 'warning', 'SISTEMA', 'SIS_MENU', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_ESTADO', 'CONFIRMACION', 'Inactivar estado', 'El estado dejara de estar disponible para nuevos registros.', 'warning', 'SISTEMA', 'SIS_ESTADO', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_MENSAJE', 'CONFIRMACION', 'Inactivar mensaje', 'El mensaje no se usara mientras permanezca inactivo.', 'warning', 'SISTEMA', 'SIS_MENSAJE_ERRORES', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_TIPO_DOCUMENTO', 'CONFIRMACION', 'Inactivar tipo', 'El tipo de documento dejara de estar disponible para nuevas operaciones.', 'warning', 'SISTEMA', 'SIS_TIPO_DOCUMENTO', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_SECUENCIA', 'CONFIRMACION', 'Inactivar secuencia', 'La secuencia dejara de estar disponible para emitir nuevos documentos.', 'warning', 'SISTEMA', 'SIS_SECUENCIAS', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_PRODUCTO', 'CONFIRMACION', 'Inactivar producto', 'El producto no podra usarse en nuevas operaciones mientras este inactivo.', 'warning', 'INVENTARIO', 'INV_PRODUCTO', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_CATEGORIA', 'CONFIRMACION', 'Inactivar categoria', 'La categoria dejara de estar disponible para nuevos productos.', 'warning', 'INVENTARIO', 'INV_CATEGORIA', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_MARCA', 'CONFIRMACION', 'Inactivar marca', 'La marca dejara de estar disponible para nuevos productos.', 'warning', 'INVENTARIO', 'INV_MARCA', TRUE, 0, 4, 1),
    ('CONFIRMAR_INACTIVAR_BODEGA', 'CONFIRMACION', 'Inactivar bodega', 'La bodega no podra usarse en nuevas operaciones mientras este inactiva.', 'warning', 'INVENTARIO', 'INV_BODEGA', TRUE, 0, 4, 1),
    ('CONFIRMAR_ELIMINAR_BODEGA', 'CONFIRMACION', 'Eliminar bodega', 'La bodega se eliminara logicamente si no tiene stock ni movimientos.', 'warning', 'INVENTARIO', 'INV_BODEGA', TRUE, 0, 4, 1)
ON CONFLICT (sis_mensaje_errores_codigo) DO UPDATE
SET sis_mensaje_errores_tipo = EXCLUDED.sis_mensaje_errores_tipo,
    sis_mensaje_errores_titulo = EXCLUDED.sis_mensaje_errores_titulo,
    sis_mensaje_errores_mensaje = EXCLUDED.sis_mensaje_errores_mensaje,
    sis_mensaje_errores_icono = EXCLUDED.sis_mensaje_errores_icono,
    sis_mensaje_errores_modulo = EXCLUDED.sis_mensaje_errores_modulo,
    sis_mensaje_errores_entidad = EXCLUDED.sis_mensaje_errores_entidad,
    sis_mensaje_errores_activo = EXCLUDED.sis_mensaje_errores_activo,
    sis_mensaje_errores_tiempo = EXCLUDED.sis_mensaje_errores_tiempo,
    sis_mensaje_errores_posicion = EXCLUDED.sis_mensaje_errores_posicion,
    usuario_modifica = 1,
    fecha_modifica = now();

COMMIT;
