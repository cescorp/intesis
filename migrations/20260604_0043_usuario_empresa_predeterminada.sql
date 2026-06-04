BEGIN;

/******************************************************************************/
/*                                                                            */
/*  AGREGA EMPRESA PREDETERMINADA EN ASIGNACIONES DE USUARIO                  */
/*                                                                            */
/******************************************************************************/

ALTER TABLE sis_usuario_empresa
    ADD COLUMN IF NOT EXISTS sis_usuario_empresa_predeterminada BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN sis_usuario_empresa.sis_usuario_empresa_predeterminada IS 'INDICA EMPRESA PREDETERMINADA DEL USUARIO';

WITH primera AS (
    SELECT DISTINCT ON (sis_usuarios_id)
        sis_usuario_empresa_id
    FROM sis_usuario_empresa
    ORDER BY sis_usuarios_id, sis_usuario_empresa_id
)
UPDATE sis_usuario_empresa ue
SET sis_usuario_empresa_predeterminada = TRUE
FROM primera
WHERE primera.sis_usuario_empresa_id = ue.sis_usuario_empresa_id
  AND NOT EXISTS (
      SELECT 1
      FROM sis_usuario_empresa actual
      WHERE actual.sis_usuarios_id = ue.sis_usuarios_id
        AND actual.sis_usuario_empresa_predeterminada = TRUE
  );

COMMIT;
