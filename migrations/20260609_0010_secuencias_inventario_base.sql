BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CREA SECUENCIAS BASE DE INVENTARIO PARA TODAS LAS EMPRESAS ACTIVAS.      */
/*  UNA SECUENCIA 001-001 POR CADA TIPO (AJUSTE Y TRANSFERENCIA).             */
/*                                                                            */
/******************************************************************************/

INSERT INTO sis_secuencias (
    sis_empresa_id,
    sis_tipo_documento_id,
    sis_secuencias_establecimiento,
    sis_secuencias_punto_emision,
    sis_secuencias_desde,
    sis_secuencias_actual,
    sis_secuencias_hasta,
    sis_secuencias_observacion,
    sis_estado_id,
    usuario_crea
)
SELECT
    emp.sis_empresa_id,
    td.sis_tipo_documento_id,
    '001',
    '001',
    1,
    1,
    999999999,
    'Secuencia base creada automaticamente',
    estado.sis_estado_id,
    1
FROM sis_empresa emp
CROSS JOIN sis_tipo_documento td
CROSS JOIN LATERAL (
    SELECT sis_estado_id
    FROM sis_estado
    WHERE sis_estado_modulo = 'SISTEMA'
      AND sis_estado_entidad = 'SIS_SECUENCIAS'
      AND sis_estado_codigo = 'ACTIVO'
    LIMIT 1
) estado
WHERE td.sis_tipo_documento_modulo = 'INVENTARIO'
  AND td.sis_tipo_documento_codigo IN ('AJUSTE', 'TRANSFERENCIA')
  AND NOT EXISTS (
      SELECT 1
      FROM sis_secuencias s
      WHERE s.sis_empresa_id = emp.sis_empresa_id
        AND s.sis_tipo_documento_id = td.sis_tipo_documento_id
  );

COMMIT;
