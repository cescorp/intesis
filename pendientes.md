# Pendientes

Marcar `[x]` cuando quede resuelto y verificado.

Orden basado en dependencias reales entre módulos (Sistema → Inventario → Compras → Ventas)
y en qué ítems bloquean o son prerrequisito de otros.

## General

- [x] Al crear empresa, en Documentos no hay secuencias y da error (debe crear secuencia automática).
- [x] En barra principal superior, al lado del usuario/nombre, agregar qué bodega está activa: "BODEGA1 - Administrador".

## Empresa

- [x] Agregar dos campos que afectan facturación y nota de venta: % descuento máximo facturas, % descuento máximo notas de venta.
- [x] Verificar que al dar todos los permisos a un usuario con perfil GERENTE, no le deje editar su propia empresa. (No era bug de código: faltaba otorgar el permiso "Editar empresa" al perfil desde Sistema → Perfiles. Ya asignado y confirmado en BD)
- [x] Nueva factura: en Empresa agregar opción para vender solo lo de su propia bodega o de todas (predeterminado solo su propia bodega). (Se implementó por Perfil en vez de por Empresa: switch "Vender stock de todas las bodegas" en Sistema → Perfiles, ya que permite distinguir GERENTE (ve todas) de VENDEDOR (solo su bodega) dentro de la misma empresa. Aplica a Nueva factura/Nota de venta; Proforma no fue tocada)

## Producto

- [x] En /Nuevo producto: al crear otro usuario y darle permisos por perfil, no se ve el "+" y no permite crear categoría y marca. (Causa: los menús padre "Categorias"/"Marcas" estaban inactivos, lo que ocultaba "Crear categoria"/"Crear marca" del árbol de permisos de Perfiles. Se reparentaron bajo "Productos" — sin crear menús propios — y ya se pueden otorgar por perfil)
- [x] Al importar producto dice "omitido" y da el detalle al hacer clic; agregar por qué está mal el detalle. Ej. precio: 45as4,00. (StockControlador::validarCampoNumerico() ahora distingue "valor no numérico" de "usa punto decimal" y muestra el valor recibido en el mensaje)

## Ajustes

- [x] Al buscar y escoger producto: hay un "visto" (esto no va como el resto del sistema), pasar a dar clic en toda la fila. (Modal "Buscar producto" de Ajustes/Movimientos ahora usa fila completa clickeable, igual que Facturación)
- [x] Al existir una sola bodega, debe elegirla predeterminadamente. (Solo aplicado a Ajuste, no a Transferencia — ver nota en Movimientos Internos)
- [x] El modal de detalles de ajuste es diferente a lo que va con el resto del sistema. (Revisado: el modal de Ajuste/Movimiento ya usa el patrón `doc-*`, formal y con badges de color — es de los 3 el mejor estructurado. El problema real es que Compras y Facturas/Proformas usan cada uno su propio estilo distinto entre sí. Ver nota en "Deuda técnica" al final)

## Kardex

- [ ] En detalles del movimiento hay documentos que dejan hora = 00:00. (EN ESPERA: falta que el usuario especifique de qué tipo de documento se trata)
- [x] En detalles del movimiento no se pudo generar PDF de ajuste (debería ser una llamada a una función común). (No era Ajuste sino Factura/Nota de venta: `abrirPdfInventario()` en sistema.js comparaba contra 'FACTURA_VENTA'/'NOTA_VENTA' pero el kardex guarda 'VEN_FACTURA' — el string nunca coincidía y caía al endpoint equivocado. Corregido para que compare contra el valor real)
- [x] Si solo hay una bodega, seleccionarla automáticamente. (No aplica: Kardex no tiene selector de bodega — las bodegas se muestran como columnas paralelas, no como un combo a elegir. Era un duplicado del ítem ya resuelto en Ajustes)

## Movimientos Internos

- [x] Nueva transferencia: mover/quitar origen y destino por línea y que sea en encabezado por todo el documento. (Origen/Destino ahora son 2 combos en el encabezado que aplican a todas las líneas; se sincronizan automáticamente y validan que no sean la misma bodega. La columna "Accion" y el resto de columnas de la tabla no se tocaron. Ajuste queda sin cambios)
- [x] Nueva transferencia: moviendo origen/destino al encabezado, en modal "Buscar Producto" cambiar esas bodegas a columnas horizontales y quitar la fila de stock vertical. Ejemplo: si en encabezado escogí origen=Matriz, destino=Bodega1, en modal "Buscar Producto" las columnas de stock serían: MATRIZ | BODEGA1. (Se bloquea la búsqueda con aviso si falta elegir Origen/Destino. Ajuste no cambia, sigue con el formato apilado de todas las bodegas)
- [x] En consulta: el filtro debe ser por DESTINO = mis bodegas activas. (Agregado botón "Filtrar". Corregido en el camino: el destino de Ajuste vive por línea en inv_movimientos_detalle, no en el encabezado — filtrar por encabezado hubiera ocultado todos los ajustes)
- [x] PDF de transferencia: el detalle debe ser horizontal, no vertical por movimiento. Columnas: CODIGO | PRODUCTO | ACCION (+/-) | CANTIDAD | SALDO. (Solo aplicado a Transferencia; Ajuste conserva su formato con columna Bodega)
- [x] PDF de transferencia: en el encabezado agregar Bodega Origen y Bodega Destino. (Implementado junto con el anterior)

## Compras

- [x] Intercambiar columnas de Nueva compra así: Cod. Interno | Cod. Proveedor.
- [x] En Nueva compra, agregar columna "Marca" -> tipo combo. (Editable: se guarda como historial en com_documento_detalle_marca_id, no actualiza el producto global. La actualización global quedó comentada en DocumentoCompraModelo::crear()/actualizar() por si se necesita más adelante)
- [x] Modal "Crear producto" si en búsqueda producto en Nueva compra, igual que en "Ventas/Facturación/Nueva factura". (Reutiliza el endpoint existente /inventario/productos/crear en modo AJAX. Bug propio corregido en el camino: el campo empresa_id del formulario solo existe para superusuario, causaba error "q(...) is null" al guardar para usuarios normales)
- [x] En Nueva compra, en campo "Cod. Interno" asignar secuencia automáticamente. Ej. si el último código fue 1000-A105, siguiente 1001-A106. (Implementado junto con el modal "Crear producto": `ProductoModelo::sugerirSiguienteCodigo()` mantiene el prefijo de letras y sube el número; se recalcula localmente en JS tras cada producto creado en el mismo documento)
- [x] En Nueva compra, mover descuento del detalle al total (como ya está) pero con un input modificable que actualice el precio en vivo. (Se quitó la columna "Desc." por línea; el detalle ahora calcula su total solo con Cant.×Costo+IVA. En Totales, "Descuento" es un input editable que resta directo del Total y recalcula en vivo con cada tecla. No se tocó BD: se sigue guardando en com_documento_descuento de cabecera, líneas quedan con descuento 0)
- [x] En Nueva compra, cuando el cursor esté en PVP (último campo), agregar nueva línea automáticamente con Enter. (Enter en PVP agrega la fila y mueve el foco a Cod. Interno de la nueva línea; en el resto de campos Enter sigue sin hacer nada, salvo Cod. Interno/Cod. Proveedor que ya abrían el modal de búsqueda)

## Facturar

- [x] Nueva factura: en modal "Buscar Cliente" debe salir el resultado aunque el input de búsqueda esté vacío. (Se quitó el corte de "mínimo 2 caracteres" en FacturaControlador::buscarClientes(); con término vacío ahora trae hasta 30 clientes activos ordenados por razón social, igual que ya hacía Buscar Producto)
- [x] Nueva factura: en modal "Buscar Cliente" el botón "Crear Cliente" debe ocultarse al estar creando el cliente y mostrarse nuevamente al dar Enter en el input "Buscar". (El botón se oculta en expandirFormularioNuevoCliente(); Enter en el buscador cancela el debounce y relanza la búsqueda de inmediato, lo que cierra el panel y regenera el botón si sigue sin coincidencias)
- [x] Nueva factura: en encabezado debe verse correo y teléfono del cliente. (2 campos solo lectura en Fila 2, junto a Forma de pago; se llenan automático por RUC+Enter, desde el modal Buscar Cliente y al crear cliente rápido — las 3 rutas ya devolvían esos datos, solo faltaba mostrarlos)
- [x] Nueva factura: forma de pago (agregar botón "+" al lado del combo para abrir un mini CRUD sencillo de crear, editar, eliminar, similar a Marca y Categoría de producto). (Botón "+" abre modal con lista + form crear/editar/activar-inactivar/eliminar. Permisos granulares nuevos `/ventas/formas-pago/{crear,editar,inactivar,eliminar}` como hijos de `/ventas/facturas` — sin entrada propia en el menú lateral, igual que Códigos de Proveedor. Eliminar se bloquea si la forma de pago está en uso en documentos existentes, solo permite inactivar. Nuevo FormaPagoModelo + métodos en FacturaControlador, requiere `require_once` manual en index.php ya que el proyecto no usa autoload PSR-4)
- [ ] Nueva factura: forma de pago debe crear predeterminados: EFECTIVO, TRANSFERENCIA, TARJETA CRÉDITO.
- [ ] Nueva factura: mover descuento del detalle al total con input editable.
- [ ] Nueva factura: quitar IVA del detalle (ya está en el total).
- [ ] Consulta del PDF: quitar IVA del detalle y dejarlo solo en el total.

## Clientes

- [ ] En acciones, agregar ícono "Historial de ventas": modal para ver listado de facturas, notas de venta y proformas.

## Deuda técnica (no urgente, hacer cuando haya tiempo)

- [ ] Normalizar los modales "ver detalle" de documentos al patrón `doc-*` que ya usa Ajuste/Movimiento (header con badge de estado, tabla de campos, thead estilizado):
  - [ ] Compras → Ver documento (`documentos.php`): hoy usa inputs deshabilitados en grilla; mantener el bloque de Subtotal/Descuento/IVA/Total que es propio de Compras.
  - [ ] Facturas → Ver factura (`facturas.php`): hoy usa tablas clave-valor de 2 columnas sin badge de color.
  - [ ] Proformas → Ver proforma (`proformas.php`): el que menos estructura tiene hoy; mismo tratamiento que Facturas.
  - Kardex y Stock quedan fuera de esto — sus modales no son "detalle de documento" (son listas/desgloses de otra naturaleza).
