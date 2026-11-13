-- Facturas pide el mensaje CONFIRMAR_ANULAR_FACTURA al listar; no existia y cada carga
-- dejaba "MENSAJE NO CONFIGURADO" en el log (la pantalla usaba un texto de respaldo).
INSERT INTO sis_mensaje_errores (
    sis_mensaje_errores_codigo, sis_mensaje_errores_tipo, sis_mensaje_errores_titulo,
    sis_mensaje_errores_mensaje, sis_mensaje_errores_icono, sis_mensaje_errores_modulo,
    sis_mensaje_errores_entidad, sis_mensaje_errores_activo, usuario_crea,
    sis_mensaje_errores_tiempo, sis_mensaje_errores_posicion
)
SELECT 'CONFIRMAR_ANULAR_FACTURA', 'CONFIRMACION', 'Anular factura',
       '¿Desea anular esta factura? Esta acción no se puede deshacer.', 'warning', 'VENTAS',
       'VEN_DOCUMENTO', TRUE, 1, 0, 4
WHERE NOT EXISTS (SELECT 1 FROM sis_mensaje_errores WHERE sis_mensaje_errores_codigo = 'CONFIRMAR_ANULAR_FACTURA');
