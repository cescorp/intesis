BEGIN;

/******************************************************************************/
/*                                                                            */
/*  CORRIGE NOMBRE DE BODEGA PREDETERMINADA POR USUARIO                       */
/*                                                                            */
/******************************************************************************/

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'inv_bodega_usuarios'
          AND column_name = 'inv_bodega_usuarios_predetermada'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'inv_bodega_usuarios'
          AND column_name = 'inv_bodega_usuarios_predeterminada'
    ) THEN
        ALTER TABLE public.inv_bodega_usuarios
            RENAME COLUMN inv_bodega_usuarios_predetermada TO inv_bodega_usuarios_predeterminada;
    END IF;
END $$;

COMMENT ON COLUMN public.inv_bodega_usuarios.inv_bodega_usuarios_predeterminada IS 'INDICA SI ES LA BODEGA PREDETERMINADA DEL USUARIO';

COMMIT;
