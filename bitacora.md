# Bitácora

Una entrada por tarea terminada (ver [reglas.md](reglas.md)). Lo más reciente arriba.

## 2026-11-13

- **Movimientos internos: el usuario de la bodega origen no veía sus transferencias** — `3e78d1d`
  La lista, para quien no es superusuario, solo mostraba movimientos cuya bodega **destino** era del usuario. El gerente de MATRIZ no veía las transferencias MATRIZ → MACHACHI que él mismo creó y debe aprobar/despachar (ni sus ajustes de egreso). Ahora cuenta si alguna línea tiene su bodega como origen **o** destino.
  Archivos: `src/Modelos/MovimientoInternoModelo.php`, `src/Controladores/MovimientoInternoControlador.php`.

- **Aviso de secuencia faltante al abrir los documentos** — `6afac6f`
  Antes el "no hay secuencia" salía recién al guardar. Ahora Factura/Nota de venta (nueva), Proforma (nueva), Proformas › Facturar y Movimientos › Ajuste/Transferencia consultan al abrir (`GET /sistema/configuracion/secuencias/estado`, no consume número) y, si no hay secuencia activa con números libres, muestran una barra y deshabilitan el botón que crearía el documento. Con permiso de crear secuencias, "Crear secuencia" abre Tipos documento en otra pestaña con el modal "Nueva secuencia" ya abierto y prellenado (`?nueva_secuencia=ID&empresa=ID`); al volver a la pestaña se revisa sola. Función compartida `IntesisSecuencia.vigilar()`. Sin SQL pendiente.
  Archivos: `src/Modelos/SecuenciaModelo.php`, `src/Controladores/ConfiguracionControlador.php`, `src/Nucleo/Enrutador.php`, `publico/js/sistema.js`, `publico/css/estilos.css`, `src/Vistas/ventas/factura_form.php`, `src/Vistas/ventas/proforma_form.php`, `src/Vistas/ventas/proformas.php`, `src/Vistas/inventario/movimientos.php`.

- **Usuarios: un no superusuario no podía crear ni editar usuarios** — `ff8a72c`
  Causa real del aviso "datos incompletos": el JS detectaba el bloque de varias empresas con `!document.querySelector('.asignaciones-multiples')?.classList.contains('d-none')`; ese bloque solo existe para el superusuario, así que para los demás daba `!undefined` = true, validaba cero asignaciones y rechazaba siempre. Ahora solo cuenta como visible si existe y no está oculto.
  Archivo: `publico/js/sistema.js`.

- **Usuarios: el aviso de datos incompletos dice qué falta** — `f33cdc6`
  Al crear/editar usuario el aviso era genérico ("Seleccione empresa, perfil, nombre y correo valido") y parecía pedir la empresa aunque esta ya va sola cuando el usuario tiene una sola empresa; lo que faltaba era el nombre. Ahora lista solo lo que falta (ej. "Falta nombre.") y lleva el cursor a ese campo.
  Archivo: `publico/js/sistema.js`.

- **Perfiles: perfil SUPERUSUARIO oculto y protegido para no superusuarios** — `b272e5a`
  Seguridad, igual que en Usuarios. La lista de Perfiles ya no muestra SUPERUSUARIO a quien no lo es, y `permisos()` rechaza cambiar los permisos de ese perfil si no se es superusuario (antes se le podían quitar y dejar al administrador sin acceso). Editar y eliminar ya estaban protegidos.
  Archivos: `src/Controladores/PerfilControlador.php`, `src/Modelos/PerfilModelo.php`.

- **Usuarios: un no superusuario ya no ve ni administra superusuarios** — `e89b0d2`
  Seguridad. Antes, un usuario con permisos de Usuarios (ej. GERENCIA) veía al superusuario y podía editarlo, bloquearlo o restablecerle la clave (y entrar con sus privilegios); además `obtenerAsignacionValida()` no revisaba la empresa (se podía actuar sobre usuarios de otra empresa por id) y se podía asignar el perfil SUPERUSUARIO a uno mismo o a un usuario nuevo. Ahora: la lista y el combo de perfiles excluyen SUPERUSUARIO para no superusuarios, y en el servidor toda acción sobre un usuario valida empresa y que no sea superusuario, y crear/editar rechaza asignar ese perfil.
  Archivos: `src/Controladores/UsuarioControlador.php`, `src/Modelos/UsuarioEmpresaModelo.php`.

- **PDF de factura autorizada en formato oficial del SRI (RIDE)** — `51c7d6b`
  `generarFacturaAutorizada()` ahora usa la misma plantilla SRI que Compras (`src/Servicios/PdfSri/Plantillas.php`, `gpdf_html_documento()`), con los datos armados igual que el XML enviado al SRI (dirección de la empresa, una forma de pago por el total, obligado a contabilidad SI, info adicional Email/Teléfono/Observación). La plantilla ya no dice "12%" fijo: usa `iva_porcentaje` o lo deduce de iva/subtotal (corrige también el PDF de Compras). Factura CREADA y Nota de venta conservan su formato.
  Archivos: `src/Servicios/GeneradorPdf.php`, `src/Servicios/PdfSri/Plantillas.php`.

- **PDF de factura autorizada salía vacío (0 páginas)** — `51c7d6b`
  `GeneradorPdf::generarFacturaAutorizada()` devolvía el HTML sin convertirlo a PDF; el navegador recibía HTML como `application/pdf` y no mostraba nada (sin error en el log). Ahora pasa por `generarDesdeHtml()`, igual que la proforma. Afectaba también al PDF adjunto del correo que se envía al autorizar. Además se agrega el mensaje `CONFIRMAR_ANULAR_FACTURA`, que faltaba y dejaba un aviso en el log en cada carga de Facturas.
  Archivo: `src/Servicios/GeneradorPdf.php`. Migración: `20261113_0900_mensaje_anular_factura.sql`.

## 2026-11-12

- **Visor de imagen con zoom, compartido (galería y adjuntos)** — `3c35641`
  El visor ampliado de la galería pasó a ser un componente genérico en `sistema.js`: cualquier `<img class="imagen-ampliable">` se abre con doble clic, Enter o Espacio (si está dentro de `[data-galeria]` se recorren las del grupo). Zoom con rueda hacia el cursor, botones −/＋/⟲ con %, doble clic 100%↔200%, arrastrar para mover, pellizco en celular y teclas + − 0. Se usa en Galería de productos, Adjuntos de facturas, Archivos e Historial de Clientes.
  Archivos: `publico/js/sistema.js`, `publico/css/estilos.css`, `src/Vistas/ventas/facturas.php`, `src/Vistas/archivos/index.php`, `src/Vistas/ventas/clientes.php`.

- **Botón "Tomar foto" en Adjuntos de facturas y en Archivos** — `e81d355`
  Solo en pantallas táctiles: abre la cámara trasera del celular con el input nativo (`capture="environment"`, funciona por http en la red local; `getUserMedia` exigiría HTTPS). La foto se renombra `Foto AAAA-MM-DD HH.MM.SS` y se sube por la misma cola con %. Función compartida `IntesisSubida.conectarCamara()`.
  Archivos: `publico/js/subida-archivos.js`, `publico/css/estilos.css`, `src/Vistas/ventas/facturas.php`, `src/Vistas/archivos/index.php`.

- **Galería de producto: ampliar imagen en la misma pantalla** — `d7280f2`
  Se quitó el 👁 (abría otra pestaña). Doble clic, Enter o Espacio sobre una imagen la amplía sobre la misma pantalla; ‹ › y ←/→ pasan entre imágenes; Esc, X o clic en el fondo cierran y devuelven el foco a la miniatura. El visor se inserta dentro del modal abierto para que el focus trap de Bootstrap no le quite el teclado. Aplica a Stock, F2, Kardex y Productos (función compartida `renderizarGaleriaProducto`).
  Archivos: `publico/js/sistema.js`, `publico/css/estilos.css`.

- **Módulo Archivos: carpetas por usuario, versiones, compartir y accesos** — `2dd8a09`
  Menú "Archivos": árbol de carpetas colapsable (Mis archivos / Compartido conmigo), subida igual que en Facturas (cualquier tipo excepto ejecutables/scripts, 80 MB), mismo nombre y extensión en la carpeta = versión nueva, visor PDF/imagen o ficha con Descargar, modal Compartir (usuarios de la empresa, solo ver y descargar; compartir una carpeta comparte su contenido) y modal ⓘ con datos, versiones, con quién está compartido (directo o por carpeta) y accesos (quién abrió/descargó y cuándo). La subida en el navegador quedó en `publico/js/subida-archivos.js`, compartida con Adjuntos de facturas. `AlmacenArchivosServicio` ahora acepta `CUALQUIER_TIPO` (bloquea por extensión y por contenido ejecutable) y versiones.
  Migración: `20261112_1500_modulo_archivos.sql` (`sis_carpeta`, `sis_compartido`, `sis_archivo_acceso`, versión en `sis_archivos`, menú y permisos `/archivos/*`).

- **Consulta de stock global con F2** — `c8bb459`
  En cualquier pantalla con sesión, F2 muestra/oculta una capa (por encima de modales y SweetAlert) con la pantalla Stock en un iframe (`/inventario/stock?embebido=1`: sin menú ni Plantilla/Importar/Registrar, no consume el mensaje flash). Solo consulta: la pantalla de abajo no se recarga ni cambia; al cerrar se devuelve el cursor al campo donde estaba. Se crea en el primer F2 y conserva la búsqueda; botón ↻ para recargar. Suspende el focus trap de los modales Bootstrap abiertos mientras está visible. Sin permiso `/inventario/stock/ver` muestra un aviso.
  Archivos: `publico/js/sistema.js`, `publico/css/estilos.css`, `src/Controladores/StockControlador.php`, `src/Vistas/inventario/stock.php`.

- **Clientes › Historial de ventas: adjuntos en solo consulta** — `a94c044`
  En Facturas y Notas de Venta, botón 📎 con contador que abre la lista y el visor PDF/imagen, sin subir, renombrar ni eliminar (Proformas no tiene adjuntos). Reutiliza `/ventas/facturas/adjuntos` y `/ventas/facturas/adjuntos/ver`: ahora también los permite quien tiene `/ventas/clientes/ver` (`FacturaControlador::puedeConsultarAdjuntos()`). `listarPorCliente()` devuelve `adjuntos_total`. Sin migración.
  Archivos: `src/Vistas/ventas/clientes.php`, `src/Controladores/FacturaControlador.php`, `src/Modelos/FacturaModelo.php`.

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
