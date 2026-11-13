<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl    = rtrim($configuracion->obtener('APP_URL', ''), '/');
$tiposId   = ['RUC' => 'RUC', 'CEDULA' => 'Cedula', 'PASAPORTE' => 'Pasaporte'];
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li><li class="nav-item"><span class="nav-link breadcrumb-navbar"><span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Compras</span><i class="bi bi-chevron-right"></i><strong>Proveedores</strong></span></li></ul><ul class="navbar-nav ms-auto align-items-center"><li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars((($usuario['bodega'] ?? null) ? $usuario['bodega'] . ' - ' : '') . $usuario['nombre']) ?></span></li><li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li></ul></div></nav>
    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark"><div class="sidebar-brand"><a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a></div><div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div></aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid"><section class="panel-crud">
        <div class="panel-crud-cabecera">
            <?php if ($permisos['crear']): ?>
            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalProveedor" data-modo="crear">
                <i class="bi bi-plus-square"></i> Nuevo proveedor
            </button>
            <?php endif; ?>
            <?php if ($permisos['ver_codigos']): ?>
            <a href="<?= $appUrl ?>/compras/codigos-proveedor" class="btn btn-secundario btn-crud">
                <i class="bi bi-upc-scan"></i> Códigos de proveedor
            </a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table id="tablaProveedores" class="table table-hover tabla-intesis align-middle w-100">
                <thead><tr>
                    <?php if ($esSuperusuario): ?><th>Empresa</th><?php endif; ?>
                    <th>Identificacion</th>
                    <th>Razon social</th>
                    <?php if ($esSuperusuario): ?><th>Nombre proveedor</th><?php endif; ?>
                    <th>Telefonos</th>
                    <th>Correo</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($proveedores as $prov): ?>
                <tr>
                    <?php if ($esSuperusuario): ?><td><?= htmlspecialchars($prov['sis_empresa_nombre_comercial']) ?></td><?php endif; ?>
                    <td><?= htmlspecialchars($prov['com_proveedor_identificacion']) ?></td>
                    <td><?= htmlspecialchars($prov['com_proveedor_razon_social']) ?></td>
                    <?php if ($esSuperusuario): ?><td><?= htmlspecialchars((string) ($prov['com_proveedor_nombre_comercial'] ?? '')) ?></td><?php endif; ?>
                    <td><?= nl2br(htmlspecialchars(str_replace(', ', "\n", (string) ($prov['com_proveedor_telefono'] ?? '')))) ?></td>
                    <td><?= htmlspecialchars((string) ($prov['com_proveedor_email']    ?? '')) ?></td>
                    <td><span class="badge estado-badge estado-<?= strtolower((string) $prov['sis_estado_codigo']) ?>"><?= htmlspecialchars($prov['sis_estado_nombre']) ?></span></td>
                    <td class="text-end acciones-tabla">
                        <button type="button" class="btn btn-accion btn-editar-proveedor" title="Ver proveedor"
                            data-bs-toggle="modal" data-bs-target="#modalProveedor" data-modo="ver"
                            data-id="<?= (int) $prov['com_proveedor_id'] ?>"
                            data-empresa="<?= (int) $prov['sis_empresa_id'] ?>"
                            data-tipo="<?= htmlspecialchars($prov['com_proveedor_tipo_identificacion']) ?>"
                            data-identificacion="<?= htmlspecialchars($prov['com_proveedor_identificacion']) ?>"
                            data-razon="<?= htmlspecialchars($prov['com_proveedor_razon_social']) ?>"
                            data-nombre-comercial="<?= htmlspecialchars((string) ($prov['com_proveedor_nombre_comercial'] ?? '')) ?>"
                            data-telefono="<?= htmlspecialchars((string) ($prov['com_proveedor_telefono'] ?? '')) ?>"
                            data-email="<?= htmlspecialchars((string) ($prov['com_proveedor_email'] ?? '')) ?>"
                            data-direccion="<?= htmlspecialchars((string) ($prov['com_proveedor_direccion'] ?? '')) ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($permisos['editar']): ?>
                        <button type="button" class="btn btn-accion btn-editar-proveedor" title="Editar proveedor"
                            data-bs-toggle="modal" data-bs-target="#modalProveedor" data-modo="editar"
                            data-id="<?= (int) $prov['com_proveedor_id'] ?>"
                            data-empresa="<?= (int) $prov['sis_empresa_id'] ?>"
                            data-tipo="<?= htmlspecialchars($prov['com_proveedor_tipo_identificacion']) ?>"
                            data-identificacion="<?= htmlspecialchars($prov['com_proveedor_identificacion']) ?>"
                            data-razon="<?= htmlspecialchars($prov['com_proveedor_razon_social']) ?>"
                            data-nombre-comercial="<?= htmlspecialchars((string) ($prov['com_proveedor_nombre_comercial'] ?? '')) ?>"
                            data-telefono="<?= htmlspecialchars((string) ($prov['com_proveedor_telefono'] ?? '')) ?>"
                            data-email="<?= htmlspecialchars((string) ($prov['com_proveedor_email'] ?? '')) ?>"
                            data-direccion="<?= htmlspecialchars((string) ($prov['com_proveedor_direccion'] ?? '')) ?>">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($prov['sis_estado_codigo'] !== 'ACTIVO' && $permisos['activar']): ?>
                        <form action="<?= $appUrl ?>/compras/proveedores/activar" method="post" class="d-inline">
                            <input type="hidden" name="proveedor_id" value="<?= (int) $prov['com_proveedor_id'] ?>">
                            <button class="btn btn-accion btn-activar" type="submit" title="Activar proveedor"><i class="bi bi-toggle-on"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($prov['sis_estado_codigo'] === 'ACTIVO' && $permisos['inactivar']): ?>
                        <form action="<?= $appUrl ?>/compras/proveedores/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_PROVEEDOR">
                            <input type="hidden" name="proveedor_id" value="<?= (int) $prov['com_proveedor_id'] ?>">
                            <button class="btn btn-accion btn-inactivar" type="submit" title="Inactivar proveedor"><i class="bi bi-toggle-off"></i></button>
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

<!-- MODAL PROVEEDOR -->
<div class="modal fade modal-intesis" id="modalProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioProveedor" method="post" action="<?= $appUrl ?>/compras/proveedores/crear">
            <input type="hidden" name="proveedor_id" id="proveedor_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Compras</p>
                    <h2 class="modal-title" id="modalProveedorTitulo">Nuevo proveedor</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <?php if ($esSuperusuario): ?>
                    <div class="col-md-4">
                        <label class="form-label" for="prov_empresa_id">Empresa</label>
                        <select class="form-control form-control-sm" id="prov_empresa_id" name="empresa_id" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= (int) $emp['sis_empresa_id'] ?>"><?= htmlspecialchars($emp['sis_empresa_nombre_comercial']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label" for="prov_tipo_identificacion">Tipo identificacion</label>
                        <select class="form-control form-control-sm" id="prov_tipo_identificacion" name="tipo_identificacion" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($tiposId as $val => $etiq): ?>
                            <option value="<?= $val ?>"><?= $etiq ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prov_identificacion">Numero de identificacion</label>
                        <input class="form-control form-control-sm" id="prov_identificacion" name="identificacion" maxlength="20" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prov_razon_social">Razon social</label>
                        <input class="form-control form-control-sm" id="prov_razon_social" name="razon_social" maxlength="300" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prov_nombre_comercial">Nombre proveedor <span class="text-muted fw-normal">(opcional)</span></label>
                        <input class="form-control form-control-sm" id="prov_nombre_comercial" name="nombre_comercial" maxlength="300">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prov_email">Correo</label>
                        <input class="form-control form-control-sm" id="prov_email" name="email" type="email" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Telefonos</label>
                        <div id="prov_telefonos"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="prov_direccion">Direccion</label>
                        <textarea class="form-control form-control-sm" id="prov_direccion" name="direccion" maxlength="300" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button>
                <button type="submit" class="btn btn-intesis" id="btnGuardarProveedor"><i class="bi bi-save2"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
(function () {
    'use strict';

    const modal = document.getElementById('modalProveedor');
    if (!modal) return;

    const camposTexto = [
        'prov_tipo_identificacion',
        'prov_identificacion',
        'prov_razon_social',
        'prov_nombre_comercial',
        'prov_email',
        'prov_direccion',
    ];

    // Telefonos: se guardan en una sola columna separados por coma.
    const contTelefonos = document.getElementById('prov_telefonos');
    const filaTelefono = (valor, primera, soloLectura) => {
        const fila = document.createElement('div');
        fila.className = 'input-group input-group-sm' + (primera ? '' : ' mt-1');
        fila.innerHTML = '<input class="form-control form-control-sm" name="telefonos[]" maxlength="20">'
            + (soloLectura ? '' : (primera
                ? '<button type="button" class="btn btn-outline-secondary btn-tel-agregar" title="Agregar telefono"><i class="bi bi-plus-lg"></i></button>'
                : '<button type="button" class="btn btn-outline-danger btn-tel-quitar" title="Quitar telefono"><i class="bi bi-trash"></i></button>'));
        const input = fila.querySelector('input');
        input.value = valor || '';
        input.disabled = soloLectura;
        contTelefonos.appendChild(fila);
        return input;
    };
    const cargarTelefonos = (texto, soloLectura) => {
        contTelefonos.innerHTML = '';
        const lista = (texto || '').split(',').map(t => t.trim()).filter(Boolean);
        if (!lista.length) lista.push('');
        lista.forEach((t, i) => filaTelefono(t, i === 0, soloLectura));
    };
    contTelefonos.addEventListener('click', function (e) {
        if (e.target.closest('.btn-tel-agregar')) filaTelefono('', false, false).focus();
        const quitar = e.target.closest('.btn-tel-quitar');
        if (quitar) quitar.closest('.input-group').remove();
    });
    cargarTelefonos('', false);

    modal.addEventListener('show.bs.modal', function (evento) {
        const boton = evento.relatedTarget;
        if (!boton) return;

        const modo   = boton.dataset.modo || 'crear';
        const editar = modo === 'editar';
        const ver    = modo === 'ver';
        const form   = document.getElementById('formularioProveedor');
        const appUrl = '<?= $appUrl ?>';

        document.getElementById('modalProveedorTitulo').textContent =
            ver ? 'Detalle de proveedor' : (editar ? 'Editar proveedor' : 'Nuevo proveedor');
        form.action = editar
            ? appUrl + '/compras/proveedores/editar'
            : appUrl + '/compras/proveedores/crear';

        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
        const setDis = (dis) => {
            camposTexto.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = dis; });
            const emp = document.getElementById('prov_empresa_id');
            if (emp) emp.disabled = dis;
            const btn = document.getElementById('btnGuardarProveedor');
            if (btn) btn.classList.toggle('d-none', ver);
        };

        if (editar || ver) {
            document.getElementById('proveedor_id').value = boton.dataset.id || '';
            setVal('prov_empresa_id',          boton.dataset.empresa);
            setVal('prov_tipo_identificacion', boton.dataset.tipo);
            setVal('prov_identificacion',      boton.dataset.identificacion);
            setVal('prov_razon_social',        boton.dataset.razon);
            setVal('prov_nombre_comercial',    boton.dataset.nombreComercial);
            setVal('prov_email',               boton.dataset.email);
            setVal('prov_direccion',           boton.dataset.direccion);
            cargarTelefonos(boton.dataset.telefono, ver);
            setDis(ver);
        } else {
            form.reset();
            document.getElementById('proveedor_id').value = '';
            cargarTelefonos('', false);
            setDis(false);
        }
    });

    modal.addEventListener('hidden.bs.modal', function () {
        document.getElementById('formularioProveedor').reset();
        document.getElementById('proveedor_id').value = '';
        cargarTelefonos('', false);
        camposTexto.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = false; });
        const emp = document.getElementById('prov_empresa_id');
        if (emp) emp.disabled = false;
        const btn = document.getElementById('btnGuardarProveedor');
        if (btn) btn.classList.remove('d-none');
    });
}());
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
