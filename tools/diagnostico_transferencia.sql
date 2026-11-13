-- Diagnostico de una transferencia (SOLO LECTURA, no modifica nada).
-- Uso: psql -U postgres -d intesis -v numero="'TR-002-002-000000007'" -f tools/diagnostico_transferencia.sql

-- 1) Estado del movimiento y bodegas de sus lineas
SELECT m.inv_movimientos_id, m.inv_movimientos_numero, es.sis_estado_codigo AS estado,
       m.inv_movimientos_recibido_fecha AS recibido,
       d.inv_producto_id, d.inv_movimientos_detalle_cantidad AS cantidad, d.inv_movimientos_detalle_estado AS linea_activa,
       bo.inv_bodega_codigo AS origen, bd.inv_bodega_codigo AS destino
FROM inv_movimientos m
JOIN sis_estado es ON es.sis_estado_id = m.sis_estado_id
LEFT JOIN inv_movimientos_detalle d ON d.inv_movimientos_id = m.inv_movimientos_id
LEFT JOIN inv_bodega bo ON bo.inv_bodega_id = d.inv_bodega_origen_id
LEFT JOIN inv_bodega bd ON bd.inv_bodega_id = d.inv_bodega_destino_id
WHERE m.inv_movimientos_numero = :numero;

-- 2) Kardex que genero (debe haber TRANS_OUT en origen al aprobar y TRANS_IN en destino al recibir)
SELECT k.inv_kardex_id, k.inv_kardex_tipo_movimiento AS tipo, b.inv_bodega_codigo AS bodega,
       k.inv_kardex_cantidad_entrada AS entrada, k.inv_kardex_cantidad_salida AS salida,
       k.inv_kardex_saldo_cantidad AS saldo, k.fecha_crea
FROM inv_kardex k
JOIN inv_bodega b ON b.inv_bodega_id = k.inv_bodega_id
JOIN inv_movimientos m ON m.inv_movimientos_id = k.inv_kardex_documento_id
WHERE k.inv_kardex_documento_tipo = 'INV_MOVIMIENTOS'
  AND m.inv_movimientos_numero = :numero
ORDER BY k.inv_kardex_id;

-- 3) Stock actual del producto en todas las bodegas de la empresa
SELECT b.inv_bodega_codigo AS bodega, s.sis_empresa_id, s.inv_stock_cantidad_disponible AS disponible
FROM inv_movimientos m
JOIN inv_movimientos_detalle d ON d.inv_movimientos_id = m.inv_movimientos_id
JOIN inv_stock s ON s.inv_producto_id = d.inv_producto_id
JOIN inv_bodega b ON b.inv_bodega_id = s.inv_bodega_id
WHERE m.inv_movimientos_numero = :numero
ORDER BY b.inv_bodega_codigo;
