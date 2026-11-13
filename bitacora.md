# Bitácora

Una entrada por tarea terminada (ver [reglas.md](reglas.md)). Lo más reciente arriba.

## 2026-11-12

- **Adjuntos en facturas y servicio global de archivos** — `17e045e`
  `AlmacenArchivosServicio` (+ `ArchivoModelo`) es el punto único para subir/ver/eliminar adjuntos: valida el tipo real (finfo), límite por parámetro con máximo 80 MB, guarda en `almacenamiento/archivos/adjuntos/empresa_{id}/{tabla}/{año}/{mes}/` con nombre único y registra en `sis_archivos`. En Facturas: botón 📎 con contador y modal con arrastrar/soltar varios archivos, % por archivo, lista con nombre editable y visor PDF/imagen. Regla 5 en `reglas.md`.
  Migración: `20261112_1400_adjuntos.sql` (columnas nombre/mime/tamaño en `sis_archivos` + permisos `/ventas/facturas/adjuntos/*`).

- **Nueva factura: no vender más del stock de la bodega de la venta** — `d5363db`
  En la línea, la cantidad se corrige al máximo disponible con aviso "Máx. N disponible"; producto sin stock en la bodega no se carga. Sin bodega asignada: aviso "No tiene bodega asignada" y detalle bloqueado; el backend ya no usa en silencio la primera bodega de la empresa (solo el superusuario). Corregido: con "vender de todas las bodegas" se validaba contra el stock total pero se descontaba de una sola bodega, que podía quedar en negativo; ahora siempre se valida contra la bodega de la que se descuenta.
  Archivos: `src/Vistas/ventas/factura_form.php`, `src/Controladores/FacturaControlador.php`, `src/Modelos/FacturaModelo.php`.

- **Nueva factura: ignorar línea en blanco al guardar y foco en Valor recibido** — `fb3e392`
  Las líneas sin producto y sin código escrito no se envían (`filasAGuardar()`); si hay código escrito sin producto elegido, sigue avisando. Al abrir la calculadora el foco queda en Valor recibido (tras `shown.bs.modal`).
  Archivo: `src/Vistas/ventas/factura_form.php`.

- **Nueva factura: calculadora no permite cobrar de menos** — `bb3d288`
  El modal con calculadora se titula "Calculadora"; si el valor recibido es menor al total, el cambio sale negativo en rojo y Facturar queda deshabilitado (`actualizarCambio()`).
  Archivo: `src/Vistas/ventas/factura_form.php`.

- **Herramienta para cambiar la clave de un usuario** — `ea05957`
  `tools/cambiar_clave.php` actualiza la clave con el mismo hash ARGON2ID del login; la clave llega por la variable `NUEVA_CLAVE`. Comando PowerShell documentado en `tools/migrar_bd.md` (paso 7).

- **Nueva factura: foco a Cantidad, Enter a Precio y sin productos duplicados** — `eefd542`
  Al cargar un producto (Enter con coincidencia exacta o clic en el modal) el foco va a Cantidad; Enter en Cantidad pasa a Precio. Si el producto ya está en otra línea se suma 1 a esa línea, la actual queda vacía y el foco va a la existente (`cargarProductoEnFila()`).
  Archivo: `src/Vistas/ventas/factura_form.php`.

- **Nueva factura / nota de venta: cantidades enteras** — `3c9584b`
  El input de Cantidad solo acepta enteros (min 1, bloquea `.`/`,`) y el backend rechaza cantidades con decimales en vez de redondearlas (`FacturaControlador::validarLineas()`).
  Archivos: `src/Vistas/ventas/factura_form.php`, `src/Controladores/FacturaControlador.php`.

- **Formas de pago con SRI automático y entidades bancarias** — `e5514e2`
  Código SRI deducido del tipo; nuevo tipo Tarjeta Débito; modal distingue crear/editar y muestra ejemplo de calculadora. Catálogo de entidades bancarias por empresa con mini-CRUD en Nueva factura, combo en fila 2 y panel Mixto.
  Migraciones: `20261112_1200_forma_pago_sri_por_tipo.sql`, `20261112_1300_ven_entidad_bancaria.sql`.
- **Crear proveedor desde Buscar Proveedor (Compras)** — `0587dfc`, `03669c0`
  Si no hay resultados y lo buscado es RUC (13) o cédula (10), formulario inline; al guardar queda cargado en la compra. RUC/CI editable.
- **Nueva compra: cantidad 1 por defecto y % PVP para todas las líneas** — `d3e3081`
- **Aviso de código proveedor inexistente: Asignar / Crear directos** — `9cc2e83`
- **Menú Compras "Documentos" → "Ingresar"** — `51739c4`
  Migración: `20261112_1100_menu_compras_ingresar.sql`.
- **Proveedor con varios teléfonos y verificador de migraciones** — `6c86413`
  Modal de proveedor rediseñado; `tools/migraciones.php` lista/aplica migraciones pendientes (tabla `sis_migracion`).
  Migración: `20261112_1000_proveedor_telefonos.sql`.
