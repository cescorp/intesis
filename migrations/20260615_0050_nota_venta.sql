-- =============================================================================
-- NOTA DE VENTA: tipo documento + secuencias por empresa
-- =============================================================================

-- 1. TIPO DOCUMENTO NOTA_VENTA
INSERT INTO sis_tipo_documento (
    sis_tipo_documento_codigo, sis_tipo_documento_nombre,
    sis_tipo_documento_modulo, sis_tipo_documento_afecta_inventario,
    sis_tipo_documento_afecta_contabilidad, sis_estado_id, usuario_crea
)
SELECT 'NOTA_VENTA', 'Nota de Venta', 'VENTAS', false, false,
       (SELECT sis_estado_id FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_TIPO_DOCUMENTO'
          AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
       1
WHERE NOT EXISTS (
    SELECT 1 FROM sis_tipo_documento
    WHERE sis_tipo_documento_codigo = 'NOTA_VENTA' AND sis_tipo_documento_modulo = 'VENTAS'
);

-- 2. SECUENCIA DE NOTA_VENTA POR EMPRESA (001-001)
INSERT INTO sis_secuencias (
    sis_empresa_id, sis_tipo_documento_id,
    sis_secuencias_establecimiento, sis_secuencias_punto_emision,
    sis_secuencias_desde, sis_secuencias_hasta, sis_secuencias_actual,
    sis_estado_id, usuario_crea
)
SELECT e.sis_empresa_id,
       (SELECT sis_tipo_documento_id FROM sis_tipo_documento
        WHERE sis_tipo_documento_codigo = 'NOTA_VENTA' AND sis_tipo_documento_modulo = 'VENTAS' LIMIT 1),
       '001', '001',
       1, 999999999, 0,
       (SELECT sis_estado_id FROM sis_estado
        WHERE sis_estado_modulo = 'SISTEMA' AND sis_estado_entidad = 'SIS_SECUENCIAS'
          AND sis_estado_codigo = 'ACTIVO' LIMIT 1),
       1
FROM sis_empresa e
WHERE NOT EXISTS (
    SELECT 1 FROM sis_secuencias s
    WHERE s.sis_empresa_id = e.sis_empresa_id
      AND s.sis_tipo_documento_id = (
          SELECT sis_tipo_documento_id FROM sis_tipo_documento
          WHERE sis_tipo_documento_codigo = 'NOTA_VENTA' AND sis_tipo_documento_modulo = 'VENTAS' LIMIT 1
      )
);
