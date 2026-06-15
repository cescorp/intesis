<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl    = rtrim($configuracion->obtener('APP_URL', ''), '/');
$tiposId   = [
    'R' => 'RUC',
    'C' => 'Cedula',
    'P' => 'Pasaporte',
    'O' => 'Consumidor Final',
];
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Ventas</span><i class="bi bi-chevron-right"></i><strong>Clientes</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">
        <?php if ($permisos['crear']): ?>
        <div class="panel-crud-cabecera">
            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalCliente" data-modo="crear">
                <i class="bi bi-plus-square"></i> Nuevo cliente
            </button>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table id="tablaClientes" class="table table-hover tabla-intesis align-middle w-100">
                <thead><tr>
                    <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                    <th>Tipo ID</th>
                    <th>Identificacion</th>
                    <th>Razon social</th>
                    <th>Telefono</th>
                    <th>Correo</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($clientes as $cli): ?>
                <tr>
                    <?php if ($esSuperusuario): ?><td><?= htmlspecialchars($cli['sis_empresa_nombre_comercial']) ?></td><?php endif; ?>
                    <td><?= htmlspecialchars($tiposId[$cli['ven_cliente_tipo_identificacion']] ?? $cli['ven_cliente_tipo_identificacion']) ?></td>
                    <td><?= htmlspecialchars($cli['ven_cliente_identificacion']) ?></td>
                    <td><?= htmlspecialchars($cli['ven_cliente_razon_social']) ?></td>
                    <td><?= htmlspecialchars($cli['ven_cliente_telefono']) ?></td>
                    <td><?= htmlspecialchars($cli['ven_cliente_email']) ?></td>
                    <td><span class="badge estado-badge estado-<?= strtolower((string) $cli['sis_estado_codigo']) ?>"><?= htmlspecialchars($cli['sis_estado_nombre']) ?></span></td>
                    <td class="text-end acciones-tabla">
                        <button type="button" class="btn btn-accion btn-fila-cliente" title="Ver cliente"
                            data-bs-toggle="modal" data-bs-target="#modalCliente" data-modo="ver"
                            data-id="<?= (int) $cli['ven_cliente_id'] ?>"
                            data-empresa="<?= (int) $cli['sis_empresa_id'] ?>"
                            data-tipo="<?= htmlspecialchars($cli['ven_cliente_tipo_identificacion']) ?>"
                            data-identificacion="<?= htmlspecialchars($cli['ven_cliente_identificacion']) ?>"
                            data-razon="<?= htmlspecialchars($cli['ven_cliente_razon_social']) ?>"
                            data-telefono="<?= htmlspecialchars($cli['ven_cliente_telefono']) ?>"
                            data-email="<?= htmlspecialchars($cli['ven_cliente_email']) ?>"
                            data-nombre-comercial="<?= htmlspecialchars((string)($cli['ven_cliente_nombre_comercial'] ?? '')) ?>"
                            data-direccion="<?= htmlspecialchars($cli['ven_cliente_direccion']) ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($permisos['editar']): ?>
                        <button type="button" class="btn btn-accion btn-fila-cliente" title="Editar cliente"
                            data-bs-toggle="modal" data-bs-target="#modalCliente" data-modo="editar"
                            data-id="<?= (int) $cli['ven_cliente_id'] ?>"
                            data-empresa="<?= (int) $cli['sis_empresa_id'] ?>"
                            data-tipo="<?= htmlspecialchars($cli['ven_cliente_tipo_identificacion']) ?>"
                            data-identificacion="<?= htmlspecialchars($cli['ven_cliente_identificacion']) ?>"
                            data-razon="<?= htmlspecialchars($cli['ven_cliente_razon_social']) ?>"
                            data-telefono="<?= htmlspecialchars($cli['ven_cliente_telefono']) ?>"
                            data-email="<?= htmlspecialchars($cli['ven_cliente_email']) ?>"
                            data-nombre-comercial="<?= htmlspecialchars((string)($cli['ven_cliente_nombre_comercial'] ?? '')) ?>"
                            data-direccion="<?= htmlspecialchars($cli['ven_cliente_direccion']) ?>">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($cli['sis_estado_codigo'] !== 'ACTIVO' && $permisos['activar']): ?>
                        <form action="<?= $appUrl ?>/ventas/clientes/activar" method="post" class="d-inline">
                            <input type="hidden" name="cliente_id" value="<?= (int) $cli['ven_cliente_id'] ?>">
                            <button class="btn btn-accion btn-activar" type="submit" title="Activar cliente"><i class="bi bi-toggle-on"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($cli['sis_estado_codigo'] === 'ACTIVO' && $permisos['inactivar']): ?>
                        <form action="<?= $appUrl ?>/ventas/clientes/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_CLIENTE">
                            <input type="hidden" name="cliente_id" value="<?= (int) $cli['ven_cliente_id'] ?>">
                            <button class="btn btn-accion btn-inactivar" type="submit" title="Inactivar cliente"><i class="bi bi-toggle-off"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section></div></div></main>
</div>

<!-- MODAL CLIENTE -->
<div class="modal fade modal-intesis" id="modalCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioCliente" method="post" action="<?= $appUrl ?>/ventas/clientes/crear">
            <input type="hidden" name="cliente_id" id="cliente_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Ventas</p>
                    <h2 class="modal-title" id="modalClienteTitulo">Nuevo cliente</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <?php if ($esSuperusuario): ?>
                    <div class="col-12">
                        <label class="form-label" for="cli_empresa_id">Empresa</label>
                        <select class="form-control form-control-sm" id="cli_empresa_id" name="empresa_id" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= (int) $emp['sis_empresa_id'] ?>"><?= htmlspecialchars($emp['sis_empresa_nombre_comercial']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label" for="cli_tipo_identificacion">Tipo</label>
                        <select class="form-control form-control-sm" id="cli_tipo_identificacion" name="tipo_identificacion" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($tiposId as $val => $etiq): ?>
                            <option value="<?= $val ?>"><?= $etiq ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="cli_identificacion">Numero de identificacion</label>
                        <input class="form-control form-control-sm" id="cli_identificacion" name="identificacion" maxlength="20" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cli_razon_social">Razon social</label>
                        <input class="form-control form-control-sm" id="cli_razon_social" name="razon_social" maxlength="300" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cli_nombre_comercial">Nombre comercial</label>
                        <input class="form-control form-control-sm" id="cli_nombre_comercial" name="nombre_comercial" maxlength="300">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cli_telefono">Telefono</label>
                        <input class="form-control form-control-sm" id="cli_telefono" name="telefono" maxlength="20" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cli_email">Correo</label>
                        <input class="form-control form-control-sm" id="cli_email" name="email" type="email" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cli_direccion">Direccion</label>
                        <input class="form-control form-control-sm" id="cli_direccion" name="direccion" maxlength="300" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button>
                <button type="submit" class="btn btn-intesis" id="btnGuardarCliente"><i class="bi bi-save2"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
(function () {
    'use strict';

    const modal = document.getElementById('modalCliente');
    if (!modal) return;

    const camposTexto = [
        'cli_tipo_identificacion',
        'cli_identificacion',
        'cli_razon_social',
        'cli_nombre_comercial',
        'cli_telefono',
        'cli_email',
        'cli_direccion',
    ];

    const tipoSelect = document.getElementById('cli_tipo_identificacion');
    const inputId    = document.getElementById('cli_identificacion');

    if (tipoSelect && inputId) {
        tipoSelect.addEventListener('change', function () {
            if (this.value === 'O') {
                inputId.value    = '9999999999999';
                inputId.disabled = true;
            } else {
                inputId.value    = '';
                inputId.disabled = false;
            }
        });
    }

    modal.addEventListener('show.bs.modal', function (evento) {
        const boton = evento.relatedTarget;
        if (!boton) return;

        const modo   = boton.dataset.modo || 'crear';
        const editar = modo === 'editar';
        const ver    = modo === 'ver';
        const form   = document.getElementById('formularioCliente');
        const appUrl = '<?= $appUrl ?>';

        document.getElementById('modalClienteTitulo').textContent =
            ver ? 'Detalle de cliente' : (editar ? 'Editar cliente' : 'Nuevo cliente');
        form.action = editar
            ? appUrl + '/ventas/clientes/editar'
            : appUrl + '/ventas/clientes/crear';

        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
        const setDis = (dis) => {
            camposTexto.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = dis; });
            const emp = document.getElementById('cli_empresa_id');
            if (emp) emp.disabled = dis;
            const btn = document.getElementById('btnGuardarCliente');
            if (btn) btn.classList.toggle('d-none', ver);
        };

        if (editar || ver) {
            document.getElementById('cliente_id').value = boton.dataset.id || '';
            setVal('cli_empresa_id',          boton.dataset.empresa);
            setVal('cli_tipo_identificacion', boton.dataset.tipo);
            setVal('cli_identificacion',      boton.dataset.identificacion);
            setVal('cli_razon_social',        boton.dataset.razon);
            setVal('cli_nombre_comercial',    boton.dataset.nombreComercial);
            setVal('cli_telefono',            boton.dataset.telefono);
            setVal('cli_email',               boton.dataset.email);
            setVal('cli_direccion',           boton.dataset.direccion);
            if (inputId && tipoSelect && tipoSelect.value === 'CONSUMIDOR_FINAL') {
                inputId.disabled = true;
            }
            setDis(ver);
        } else {
            form.reset();
            document.getElementById('cliente_id').value = '';
            if (inputId) inputId.disabled = false;
            setDis(false);
        }
    });

    modal.addEventListener('hidden.bs.modal', function () {
        document.getElementById('formularioCliente').reset();
        document.getElementById('cliente_id').value = '';
        camposTexto.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = false; });
        const emp = document.getElementById('cli_empresa_id');
        if (emp) emp.disabled = false;
        const btn = document.getElementById('btnGuardarCliente');
        if (btn) btn.classList.remove('d-none');
        if (inputId) inputId.disabled = false;
    });
}());
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
