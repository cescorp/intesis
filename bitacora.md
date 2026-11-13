# Bitácora

Una entrada por tarea terminada (ver [reglas.md](reglas.md)). Lo más reciente arriba.

## 2026-11-12

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
