<?php

declare(strict_types=1);

namespace Intesis\Modelos;

use PDO;
use RuntimeException;

/**
 * Modelo para importación de documentos electrónicos del SRI.
 * Maneja: registro de archivos (TXT/XML), parseo de TXT, gestión de proveedor,
 * mapeo de códigos y resolución de productos.
 */
final class SriImportacionModelo
{
    public function __construct(private PDO $pdo) {}

    // =========================================================================
    // REGISTRO DE ARCHIVOS
    // =========================================================================

    /**
     * Registra un archivo (TXT o XML) en la tabla de seguimiento.
     * Retorna el ID generado.
     */
    public function registrarArchivo(
        int $empresaId,
        string $tipo,
        string $ruta,
        ?string $clave,
        ?int $lineas,
        int $usuarioId,
        ?string $xmlContenido = null
    ): int {
        // Para XMLs con clave de acceso: reutilizar registro existente si ya fue subido
        if ($tipo === 'XML' && $clave !== null) {
            $stmtSel = $this->pdo->prepare(
                'SELECT com_archivos_sri_id FROM com_archivos_sri
                  WHERE sis_empresa_id = :empresa_id
                    AND com_archivos_sri_clave = :clave
                    AND com_archivos_sri_tipo = \'XML\'
                  LIMIT 1'
            );
            $stmtSel->execute(['empresa_id' => $empresaId, 'clave' => $clave]);
            $existente = $stmtSel->fetchColumn();
            if ($existente !== false) {
                $stmtUpd = $this->pdo->prepare(
                    'UPDATE com_archivos_sri SET
                         com_archivos_sri_archivo = :archivo,
                         com_archivos_sri_xml     = COALESCE(:xml, com_archivos_sri_xml),
                         com_archivos_sri_estado  = \'PENDIENTE\',
                         usuario_modifica         = :usuario,
                         fecha_modifica           = now()
                     WHERE com_archivos_sri_id = :id'
                );
                $stmtUpd->execute([
                    'archivo' => $ruta,
                    'xml'     => $xmlContenido,
                    'usuario' => $usuarioId,
                    'id'      => (int) $existente,
                ]);
                return (int) $existente;
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO com_archivos_sri
                (sis_empresa_id, com_archivos_sri_tipo, com_archivos_sri_archivo,
                 com_archivos_sri_xml,
                 com_archivos_sri_estado, com_archivos_sri_clave, com_archivos_sri_total_lineas,
                 usuario_crea, fecha_crea)
             VALUES (:empresa_id, :tipo, :archivo, :xml, \'PENDIENTE\', :clave, :lineas, :usuario, now())
             RETURNING com_archivos_sri_id'
        );
        $stmt->execute([
            'empresa_id' => $empresaId,
            'tipo'       => $tipo,
            'archivo'    => $ruta,
            'xml'        => $xmlContenido,
            'clave'      => $clave,
            'lineas'     => $lineas,
            'usuario'    => $usuarioId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Actualiza el estado de un archivo y opcionalmente su mensaje y número de líneas.
     */
    public function actualizarEstadoArchivo(
        int $archivoId,
        string $estado,
        ?string $mensaje,
        int $usuarioId,
        ?int $lineas = null
    ): void {
        $this->pdo->prepare(
            'UPDATE com_archivos_sri
             SET com_archivos_sri_estado       = :estado,
                 com_archivos_sri_mensaje      = :mensaje,
                 com_archivos_sri_total_lineas = COALESCE(:lineas, com_archivos_sri_total_lineas),
                 usuario_modifica              = :usuario,
                 fecha_modifica               = now()
             WHERE com_archivos_sri_id = :id'
        )->execute([
            'estado'  => $estado,
            'mensaje' => $mensaje,
            'lineas'  => $lineas,
            'usuario' => $usuarioId,
            'id'      => $archivoId,
        ]);
    }

    /**
     * Retorna el RUC de la empresa (usado para nombrar directorios TXT).
     */
    public function obtenerRucEmpresa(int $empresaId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT sis_empresa_ruc FROM sis_empresa WHERE sis_empresa_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $empresaId]);
        $ruc = $stmt->fetchColumn();
        return $ruc !== false ? (string) $ruc : (string) $empresaId;
    }

    /**
     * Lista los últimos archivos importados de la empresa.
     */
    public function listarArchivos(int $empresaId, int $limite = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT com_archivos_sri_id, com_archivos_sri_tipo, com_archivos_sri_archivo,
                    com_archivos_sri_estado, com_archivos_sri_clave, com_archivos_sri_total_lineas,
                    com_archivos_sri_mensaje, fecha_crea
             FROM com_archivos_sri
             WHERE sis_empresa_id = :empresa_id
             ORDER BY com_archivos_sri_id DESC
             LIMIT :limite'
        );
        $stmt->bindValue('empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PARSEO DEL TXT DEL SRI
    // =========================================================================

    /**
     * Parsea el archivo TXT del SRI (separado por tabulaciones).
     * Columnas esperadas (encabezado en primera fila):
     *   RUC_EMISOR, RAZON_SOCIAL_EMISOR, TIPO_COMPROBANTE, SERIE_COMPROBANTE,
     *   CLAVE_ACCESO, FECHA_AUTORIZACION, FECHA_EMISION,
     *   IDENTIFICACION_RECEPTOR, VALOR_SIN_IMPUESTOS, IVA, IMPORTE_TOTAL,
     *   NUMERO_DOCUMENTO_MODIFICADO
     *
     * Retorna:
     *   facturas  => filas cuyo TIPO_COMPROBANTE = 'FACTURA'
     *   ignorados => filas de otro tipo, con mensaje descriptivo
     *   total_lineas => número de filas de datos (sin encabezado)
     */
    public function parsearTxt(string $rutaArchivo): array
    {
        if (!is_readable($rutaArchivo)) {
            throw new RuntimeException('No se puede leer el archivo TXT: ' . basename($rutaArchivo));
        }

        $handle = fopen($rutaArchivo, 'r');
        if ($handle === false) {
            throw new RuntimeException('Error al abrir TXT: ' . basename($rutaArchivo));
        }

        // Primera línea: encabezado
        $encabezado = fgetcsv($handle, 4096, "\t");
        if ($encabezado === false || count($encabezado) < 5) {
            fclose($handle);
            throw new RuntimeException('El archivo TXT no tiene el formato esperado del SRI.');
        }
        // Normalizar nombres de columna
        $columnas = array_map(fn($c) => trim(strtoupper((string) $c)), $encabezado);

        $facturas  = [];
        $ignorados = [];
        $numLinea  = 0;

        while (($fila = fgetcsv($handle, 4096, "\t")) !== false) {
            $numLinea++;
            if (count($fila) < 5) {
                continue;
            }
            $datos = [];
            foreach ($columnas as $idx => $col) {
                $datos[$col] = isset($fila[$idx]) ? trim((string) $fila[$idx]) : '';
            }

            $tipo  = strtoupper(trim($datos['TIPO_COMPROBANTE'] ?? ''));
            $clave = trim($datos['CLAVE_ACCESO'] ?? '');
            $serie = trim($datos['SERIE_COMPROBANTE'] ?? '');

            // Separar estab-ptoEmi-secuencial del SERIE_COMPROBANTE (formato "001-002-000000001")
            $partesSerie = explode('-', $serie);

            $registro = [
                'ruc_emisor'       => $datos['RUC_EMISOR']          ?? '',
                'razon_social'     => $datos['RAZON_SOCIAL_EMISOR']  ?? '',
                'tipo_comprobante' => $tipo,
                'serie'            => $serie,
                'estab'            => $partesSerie[0] ?? '',
                'pto_emi'          => $partesSerie[1] ?? '',
                'secuencial'       => $partesSerie[2] ?? '',
                'clave_acceso'     => $clave,
                'fecha_autorizacion' => $datos['FECHA_AUTORIZACION'] ?? '',
                'fecha_emision'    => $datos['FECHA_EMISION']        ?? '',
                'total_sin_impuestos' => (float) str_replace(',', '.', $datos['VALOR_SIN_IMPUESTOS'] ?? '0'),
                'iva'              => (float) str_replace(',', '.', $datos['IVA']           ?? '0'),
                'importe_total'    => (float) str_replace(',', '.', $datos['IMPORTE_TOTAL'] ?? '0'),
            ];

            if ($tipo === 'FACTURA') {
                $facturas[] = $registro;
            } else {
                $registro['mensaje_ignorado'] = 'Tipo: ' . ucwords(strtolower($tipo));
                $ignorados[] = $registro;
            }
        }

        fclose($handle);

        return [
            'facturas'    => $facturas,
            'ignorados'   => $ignorados,
            'total_lineas' => $numLinea,
        ];
    }

    // =========================================================================
    // PROVEEDOR
    // =========================================================================

    /**
     * Busca un proveedor por RUC en la empresa. Si no existe, lo crea.
     * Enriquece datos de contacto si el proveedor ya existe pero tiene campos vacíos.
     * Retorna com_proveedor_id.
     */
    public function buscarOCrearProveedor(
        int $empresaId,
        string $ruc,
        string $razonSocial,
        array $contacto,
        int $usuarioId
    ): int {
        // Buscar existente por identificacion (RUC)
        $stmt = $this->pdo->prepare(
            'SELECT com_proveedor_id FROM com_proveedor
             WHERE sis_empresa_id = :empresa_id AND com_proveedor_identificacion = :ruc
             LIMIT 1'
        );
        $stmt->execute(['empresa_id' => $empresaId, 'ruc' => $ruc]);
        $existente = $stmt->fetchColumn();

        if ($existente !== false) {
            $proveedorId = (int) $existente;
            $this->enriquecerProveedor($this->pdo, $proveedorId, $contacto, $usuarioId);
            return $proveedorId;
        }

        // Obtener estado ACTIVO para el proveedor
        $stmtEst = $this->pdo->prepare(
            "SELECT sis_estado_id FROM sis_estado
             WHERE sis_estado_modulo = 'COMPRAS' AND sis_estado_entidad = 'COM_PROVEEDOR'
               AND sis_estado_codigo = 'ACTIVO' LIMIT 1"
        );
        $stmtEst->execute();
        $estadoId = (int) ($stmtEst->fetchColumn() ?: 0);
        if (!$estadoId) {
            throw new \RuntimeException('Estado ACTIVO para COM_PROVEEDOR no encontrado. Verifique la configuración.');
        }

        // Crear nuevo proveedor
        $stmt = $this->pdo->prepare(
            'INSERT INTO com_proveedor
                (sis_empresa_id, com_proveedor_tipo_identificacion, com_proveedor_identificacion,
                 com_proveedor_razon_social,
                 com_proveedor_telefono, com_proveedor_email, com_proveedor_direccion,
                 sis_estado_id, usuario_crea, fecha_crea)
             VALUES
                (:empresa_id, \'RUC\', :ruc, :razon_social,
                 :telefono, :email, :direccion,
                 :estado_id, :usuario, now())
             RETURNING com_proveedor_id'
        );
        $stmt->execute([
            'empresa_id'   => $empresaId,
            'ruc'          => $ruc,
            'razon_social' => $razonSocial,
            'telefono'     => $contacto['telefono']  ?? null,
            'email'        => $contacto['correo']    ?? null,
            'direccion'    => $contacto['direccion'] ?? null,
            'estado_id'    => $estadoId,
            'usuario'      => $usuarioId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Enriquece datos de contacto del proveedor solo si los campos están vacíos (COALESCE).
     */
    public function enriquecerProveedor(PDO $pdo, int $proveedorId, array $contacto, int $usuarioId): void
    {
        $camposTienenDatos = !empty($contacto['telefono']) || !empty($contacto['correo']) || !empty($contacto['direccion']);
        if (!$camposTienenDatos) {
            return;
        }
        $pdo->prepare(
            'UPDATE com_proveedor
             SET com_proveedor_telefono  = COALESCE(NULLIF(com_proveedor_telefono, \'\'),  :telefono),
                 com_proveedor_email     = COALESCE(NULLIF(com_proveedor_email, \'\'),     :email),
                 com_proveedor_direccion = COALESCE(NULLIF(com_proveedor_direccion, \'\'), :direccion),
                 usuario_modifica        = :usuario,
                 fecha_modifica          = now()
             WHERE com_proveedor_id = :id'
        )->execute([
            'telefono'  => $contacto['telefono']  ?? null,
            'email'     => $contacto['correo']    ?? null,
            'direccion' => $contacto['direccion'] ?? null,
            'usuario'   => $usuarioId,
            'id'        => $proveedorId,
        ]);
    }

    // =========================================================================
    // CLAVE DE ACCESO / DUPLICADOS
    // =========================================================================

    /**
     * Verifica si una clave de acceso ya fue importada como documento de compra.
     */
    public function claveYaExiste(int $empresaId, string $claveAcceso): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM com_documento
             WHERE sis_empresa_id = :empresa_id
               AND com_documento_clave_acceso = :clave
             LIMIT 1'
        );
        $stmt->execute(['empresa_id' => $empresaId, 'clave' => $claveAcceso]);
        return (bool) $stmt->fetchColumn();
    }

    // =========================================================================
    // MAPEO DE CÓDIGOS PROVEEDOR → PRODUCTO
    // =========================================================================

    /**
     * Filtra los códigos de proveedor sin mapeo a producto interno.
     * Retorna arreglo de ['proveedor_id', 'cod_principal', 'descripcion_sri', 'primera_vez' => bool]
     * únicos por (proveedor_id, cod_principal).
     */
    public function filtrarCodigosSinMapeo(int $empresaId, array $lineasPorFactura): array
    {
        $unicos    = [];
        $revisados = [];

        foreach ($lineasPorFactura as $factura) {
            $proveedorId = (int) ($factura['proveedor_id'] ?? 0);
            if ($proveedorId === 0) {
                continue;
            }
            foreach ($factura['detalles'] as $det) {
                $cod   = trim((string) ($det['cod_principal'] ?? ''));
                $llave = $proveedorId . '|' . $cod;
                if ($cod === '' || isset($revisados[$llave])) {
                    continue;
                }
                $revisados[$llave] = true;

                // Verificar en inv_codigo_proveedor (sin sis_empresa_id en esta tabla)
                $stmt = $this->pdo->prepare(
                    'SELECT inv_producto_id FROM inv_codigo_proveedor
                     WHERE com_proveedor_id = :proveedor_id
                       AND inv_codigo_proveedor_codigo = :cod
                       AND inv_codigo_proveedor_estado = 1
                     LIMIT 1'
                );
                $stmt->execute(['proveedor_id' => $proveedorId, 'cod' => $cod]);
                if (!$stmt->fetchColumn()) {
                    $unicos[] = [
                        'proveedor_id'    => $proveedorId,
                        'cod_principal'   => $cod,
                        'descripcion_sri' => (string) ($det['descripcion'] ?? ''),
                        'cantidad'        => (float) ($det['cantidad'] ?? 0),
                    ];
                }
            }
        }

        return $unicos;
    }

    /**
     * Guarda o actualiza el mapeo de un código proveedor a un producto interno.
     * UPSERT en inv_codigo_proveedor.
     * También propaga el mapeo a detalles de otros documentos pendientes del mismo proveedor.
     */
    public function guardarMapeo(
        int $empresaId,
        int $proveedorId,
        string $codPrincipal,
        int $productoId,
        int $usuarioId
    ): void {
        // Buscar registro existente por (proveedor_id, codigo)
        $stmtBuscar = $this->pdo->prepare(
            'SELECT inv_codigo_proveedor_id FROM inv_codigo_proveedor
             WHERE com_proveedor_id = :proveedor_id
               AND inv_codigo_proveedor_codigo = :cod
             LIMIT 1'
        );
        $stmtBuscar->execute(['proveedor_id' => $proveedorId, 'cod' => $codPrincipal]);
        $existeId = $stmtBuscar->fetchColumn();

        // Verificar que el producto no esté ya asignado a otro código del mismo proveedor
        $stmtDup = $this->pdo->prepare(
            'SELECT inv_codigo_proveedor_codigo FROM inv_codigo_proveedor
             WHERE com_proveedor_id = :proveedor_id
               AND inv_producto_id  = :producto_id
               AND inv_codigo_proveedor_id != :excluir_id
             LIMIT 1'
        );
        $stmtDup->execute([
            'proveedor_id' => $proveedorId,
            'producto_id'  => $productoId,
            'excluir_id'   => (int) $existeId ?: 0,
        ]);
        $codDuplicado = $stmtDup->fetchColumn();
        if ($codDuplicado !== false) {
            throw new \InvalidArgumentException(
                "Este producto ya está asignado al código \"$codDuplicado\" para este proveedor."
            );
        }

        if ($existeId) {
            $this->pdo->prepare(
                'UPDATE inv_codigo_proveedor
                 SET inv_producto_id            = :producto_id,
                     inv_codigo_proveedor_estado = 1,
                     usuario_modifica            = :usuario,
                     fecha_modifica              = now()
                 WHERE inv_codigo_proveedor_id = :id'
            )->execute(['producto_id' => $productoId, 'usuario' => $usuarioId, 'id' => (int) $existeId]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO inv_codigo_proveedor
                    (com_proveedor_id, inv_codigo_proveedor_codigo, inv_producto_id,
                     inv_codigo_proveedor_estado, usuario_crea, fecha_crea)
                 VALUES (:proveedor_id, :cod, :producto_id, 1, :usuario, now())'
            )->execute([
                'proveedor_id' => $proveedorId,
                'cod'          => $codPrincipal,
                'producto_id'  => $productoId,
                'usuario'      => $usuarioId,
            ]);
        }

        // Propagar a detalles de documentos SRI BORRADOR del mismo proveedor sin producto asignado
        $this->pdo->prepare(
            'UPDATE com_documento_detalle cdd
             SET    inv_producto_id  = :producto_id,
                    usuario_modifica = :usuario,
                    fecha_modifica   = now()
             FROM   com_documento cd
             WHERE  cdd.com_documento_id                   = cd.com_documento_id
               AND  cd.sis_empresa_id                      = :empresa_id
               AND  cd.com_proveedor_id                    = :proveedor_id
               AND  cd.com_documento_origen                = \'SRI\'
               AND  cdd.com_documento_detalle_codigo = :cod
               AND  cdd.inv_producto_id IS NULL'
        )->execute([
            'producto_id'  => $productoId,
            'usuario'      => $usuarioId,
            'empresa_id'   => $empresaId,
            'proveedor_id' => $proveedorId,
            'cod'          => $codPrincipal,
        ]);
    }

    // =========================================================================
    // RESOLUCIÓN DE PRODUCTOS
    // =========================================================================

    /**
     * Para cada detalle de cada factura, agrega inv_producto_id y pvp.
     * Si encuentra el mapeo en inv_codigo_proveedor → busca PVP en ven_lista_precio_detalle.
     * Si no encuentra → deja inv_producto_id = null, pvp = 0.
     *
     * $facturasConProveedor debe tener cada factura con clave 'proveedor_id' y 'detalles'.
     */
    public function resolverProductos(int $empresaId, array $facturasConProveedor): array
    {
        foreach ($facturasConProveedor as &$factura) {
            $proveedorId = (int) ($factura['proveedor_id'] ?? 0);
            foreach ($factura['detalles'] as &$det) {
                $cod = trim((string) ($det['cod_principal'] ?? ''));
                $det['inv_producto_id'] = null;
                $det['pvp']             = 0.0;

                if ($cod === '' || $proveedorId === 0) {
                    continue;
                }

                // Buscar en inv_codigo_proveedor (no tiene sis_empresa_id)
                $stmtCod = $this->pdo->prepare(
                    'SELECT inv_producto_id FROM inv_codigo_proveedor
                     WHERE com_proveedor_id = :proveedor_id
                       AND inv_codigo_proveedor_codigo = :cod
                       AND inv_codigo_proveedor_estado = 1
                     LIMIT 1'
                );
                $stmtCod->execute(['proveedor_id' => $proveedorId, 'cod' => $cod]);
                $productoId = $stmtCod->fetchColumn();

                if (!$productoId) {
                    continue;
                }

                $det['inv_producto_id'] = (int) $productoId;

                // Buscar PVP en lista predeterminada
                $stmtPvp = $this->pdo->prepare(
                    'SELECT vlpd.ven_lista_precio_detalle_valor
                     FROM   ven_lista_precio_detalle vlpd
                     JOIN   ven_lista_precio vlp ON vlp.ven_lista_precio_id = vlpd.ven_lista_precio_id
                     WHERE  vlpd.sis_empresa_id          = :empresa_id
                       AND  vlpd.inv_producto_id         = :producto_id
                       AND  vlp.ven_lista_precio_predeterminado = 1
                       AND  vlp.sis_estado               = 1
                     LIMIT 1'
                );
                $stmtPvp->execute(['empresa_id' => $empresaId, 'producto_id' => (int) $productoId]);
                $pvp = $stmtPvp->fetchColumn();
                $det['pvp'] = $pvp !== false ? (float) $pvp : 0.0;
            }
            unset($det);
        }
        unset($factura);

        return $facturasConProveedor;
    }

    // =========================================================================
    // DOCUMENTOS PENDIENTES DE ASIGNACIÓN
    // =========================================================================

    /**
     * Retorna los documentos SRI en estado BORRADOR con líneas sin producto asignado
     * del proveedor dado. Útil para mostrar en el modal de asignación pendiente.
     */
    public function listarLineasSinProducto(int $empresaId, int $proveedorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cd.com_documento_id,
                    cd.com_documento_numero,
                    cdd.com_documento_detalle_id,
                    cdd.com_documento_detalle_codigo         AS cod_principal,
                    cdd.com_documento_detalle_descripcion    AS com_documento_detalle_descripcion_sri,
                    cdd.com_documento_detalle_cantidad
             FROM   com_documento_detalle cdd
             JOIN   com_documento cd ON cd.com_documento_id = cdd.com_documento_id
             WHERE  cd.sis_empresa_id       = :empresa_id
               AND  cd.com_proveedor_id     = :proveedor_id
               AND  cd.com_documento_origen = \'SRI\'
               AND  cdd.inv_producto_id IS NULL
             ORDER BY cd.com_documento_id, cdd.com_documento_detalle_id'
        );
        $stmt->execute(['empresa_id' => $empresaId, 'proveedor_id' => $proveedorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos los proveedores que tienen documentos SRI BORRADOR con líneas sin producto.
     * Usado para el botón "Procesar Pendientes".
     */
    public function listarProveedoresConPendientes(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT cp.com_proveedor_id,
                    cp.com_proveedor_identificacion AS com_proveedor_ruc,
                    cp.com_proveedor_razon_social,
                    COUNT(cdd.com_documento_detalle_id) AS lineas_pendientes
             FROM   com_documento_detalle cdd
             JOIN   com_documento cd ON cd.com_documento_id = cdd.com_documento_id
             JOIN   com_proveedor cp ON cp.com_proveedor_id = cd.com_proveedor_id
             WHERE  cd.sis_empresa_id       = :empresa_id
               AND  cd.com_documento_origen = \'SRI\'
               AND  cdd.inv_producto_id IS NULL
             GROUP BY cp.com_proveedor_id, cp.com_proveedor_identificacion, cp.com_proveedor_razon_social
             ORDER BY cp.com_proveedor_razon_social'
        );
        $stmt->execute(['empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
