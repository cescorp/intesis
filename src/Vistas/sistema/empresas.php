<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button">
                        <i class="bi bi-list"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <span class="nav-link breadcrumb-navbar">
                        <span><?= htmlspecialchars($appNombre) ?></span>
                        <i class="bi bi-chevron-right"></i>
                        <span>Sistema</span>
                        <i class="bi bi-chevron-right"></i>
                        <strong>Empresas</strong>
                    </span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block">
                    <span class="usuario-navbar"><?= htmlspecialchars((($usuario['bodega'] ?? null) ? $usuario['bodega'] . ' - ' : '') . $usuario['nombre']) ?></span>
                </li>
                <li class="nav-item">
                    <form action="<?= $appUrl ?>/salir" method="post">
                        <button class="btn btn-salir" type="submit" title="Cerrar sesion">
                            <i class="bi bi-power"></i>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="<?= $appUrl ?>/dashboard" class="brand-link">
                <span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span>
                <span class="brand-text"><?= htmlspecialchars($appNombre) ?></span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <section class="panel-crud">
                    <?php if ($permisos['crear']): ?>
                        <div class="panel-crud-cabecera">
                            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalEmpresa" data-modo="crear">
                                <i class="bi bi-building-add"></i>
                                Nueva empresa
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="tablaEmpresas" class="table table-hover tabla-intesis align-middle w-100">
                            <thead>
                                <tr>
                                    <th>RUC</th>
                                    <th>Razon social</th>
                                    <th>Nombre comercial</th>
                                    <th>Telefono</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empresas as $empresa): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_ruc']) ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_razon_social']) ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_telefono'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($empresa['sis_empresa_email'] ?: '-') ?></td>
                                        <td>
                                            <span class="badge estado-badge estado-<?= strtolower((string) $empresa['sis_estado_codigo']) ?>">
                                                <?= htmlspecialchars($empresa['sis_estado_nombre']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end acciones-tabla">
                                            <?php if ($permisos['editar']): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-accion btn-editar-empresa"
                                                    title="Editar empresa"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEmpresa"
                                                    data-modo="editar"
                                                    data-id="<?= (int) $empresa['sis_empresa_id'] ?>"
                                                    data-ruc="<?= htmlspecialchars($empresa['sis_empresa_ruc']) ?>"
                                                    data-razon="<?= htmlspecialchars($empresa['sis_empresa_razon_social']) ?>"
                                                    data-comercial="<?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?>"
                                                    data-direccion="<?= htmlspecialchars($empresa['sis_empresa_direccion']) ?>"
                                                    data-telefono="<?= htmlspecialchars($empresa['sis_empresa_telefono'] ?? '') ?>"
                                                    data-email="<?= htmlspecialchars($empresa['sis_empresa_email'] ?? '') ?>"
                                                    data-obligado="<?= $empresa['sis_empresa_obligado_contabilidad'] ? '1' : '0' ?>"
                                                    data-especial="<?= $empresa['sis_empresa_contribuyente_especial'] ? '1' : '0' ?>"
                                                    data-ambiente="<?= htmlspecialchars($empresa['sis_empresa_ambiente_sri'] ?? '1') ?>"
                                                    data-num-especial="<?= htmlspecialchars($empresa['sis_empresa_num_contribuyente_especial'] ?? '') ?>"
                                                    data-descuento-facturas="<?= htmlspecialchars((string) ($empresa['sis_empresa_descuento_maximo_facturas'] ?? '0')) ?>"
                                                    data-descuento-notas-venta="<?= htmlspecialchars((string) ($empresa['sis_empresa_descuento_maximo_notas_venta'] ?? '0')) ?>"
                                                    data-formula-descuento="<?= htmlspecialchars((string) ($empresa['sis_empresa_formula_descuento'] ?? 'C')) ?>"
                                                    data-cert-ruta="<?= htmlspecialchars($empresa['sis_empresa_certificado_ruta'] ?? '') ?>"
                                                    data-cert-clave="<?= htmlspecialchars($empresa['sis_empresa_certificado_clave'] ?? '') ?>"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($empresa['sis_estado_codigo'] === 'ACTIVO' && $permisos['inactivar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/empresas/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_EMPRESA">
                                                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['sis_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar empresa">
                                                        <i class="bi bi-toggle-off"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($empresa['sis_estado_codigo'] === 'INACTIVO' && $permisos['activar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/empresas/activar" method="post" class="d-inline">
                                                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['sis_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-activar" title="Activar empresa">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($permisos['eliminar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/empresas/eliminar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_ELIMINAR_EMPRESA">
                                                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['sis_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-eliminar" title="Eliminar empresa">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade modal-intesis" id="modalEmpresa" tabindex="-1" aria-labelledby="modalEmpresaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="formularioEmpresa" method="post" enctype="multipart/form-data" action="<?= $appUrl ?>/sistema/empresas/crear">
            <input type="hidden" name="empresa_id" id="empresa_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Sistema</p>
                    <h2 class="modal-title" id="modalEmpresaTitulo">Nueva empresa</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="nav nav-tabs px-3 pt-3" id="tabsEmpresa" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-general-btn" data-bs-toggle="tab" data-bs-target="#tabGeneral" type="button" role="tab">
                            <i class="bi bi-building me-1"></i>Datos generales
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-facturacion-btn" data-bs-toggle="tab" data-bs-target="#tabFacturacion" type="button" role="tab">
                            <i class="bi bi-receipt me-1"></i>Facturación
                        </button>
                    </li>
                </ul>
                <div class="tab-content p-3" id="tabsEmpresaContent">

                    <!-- TAB: Datos generales -->
                    <div class="tab-pane fade show active" id="tabGeneral" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="ruc">RUC</label>
                                <input type="text" class="form-control" id="ruc" name="ruc" maxlength="13" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="razon_social">Razon social</label>
                                <input type="text" class="form-control" id="razon_social" name="razon_social" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="nombre_comercial">Nombre comercial</label>
                                <input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="direccion">Direccion matriz</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="telefono">Telefono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check form-switch switch-intesis mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="contribuyente_especial" name="contribuyente_especial">
                                    <label class="form-check-label" for="contribuyente_especial">Contribuyente especial</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch switch-intesis">
                                    <input class="form-check-input" type="checkbox" role="switch" id="obligado_contabilidad" name="obligado_contabilidad">
                                    <label class="form-check-label" for="obligado_contabilidad">Obligado a llevar contabilidad</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: Facturación -->
                    <div class="tab-pane fade" id="tabFacturacion" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Ambiente SRI</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ambiente_sri" id="ambiente_pruebas" value="1">
                                        <label class="form-check-label" for="ambiente_pruebas">Pruebas (ambiente 1)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ambiente_sri" id="ambiente_produccion" value="2">
                                        <label class="form-check-label" for="ambiente_produccion">Producción (ambiente 2)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="num_contribuyente_especial">
                                    Número de contribuyente especial
                                    <small class="text-muted">(dejar vacío si no aplica)</small>
                                </label>
                                <input type="text" class="form-control" id="num_contribuyente_especial" name="num_contribuyente_especial" maxlength="20">
                            </div>
                            <div class="col-12">
                                <hr class="my-1">
                                <label class="form-label fw-semibold">Límites de descuento</label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="descuento_maximo_facturas">% Descuento máximo facturas</label>
                                <input type="number" class="form-control" id="descuento_maximo_facturas" name="descuento_maximo_facturas" min="0" max="100" step="0.01" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="descuento_maximo_notas_venta">% Descuento máximo notas de venta</label>
                                <input type="number" class="form-control" id="descuento_maximo_notas_venta" name="descuento_maximo_notas_venta" min="0" max="100" step="0.01" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="formula_descuento">Fórmula de aplicación del descuento (Nueva Factura / Nota de Venta)</label>
                                <select class="form-control" id="formula_descuento" name="formula_descuento">
                                    <option value="A">A - Reduce la base antes de IVA (recalcula el IVA)</option>
                                    <option value="B">B - Reduce el total ya con IVA incluido</option>
                                    <option value="C" selected>C - Reduce solo la base, el IVA se suma completo (predeterminado)</option>
                                </select>
                                <small class="text-muted">No aplica a Compras, que siempre usa la fórmula C.</small>
                            </div>
                            <div class="col-12">
                                <hr class="my-1">
                                <label class="form-label fw-semibold">Certificado digital</label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cert_archivo">Archivo .p12</label>
                                <input type="file" class="form-control" id="cert_archivo" name="cert_archivo" accept=".p12">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cert_nombre">Archivo actual</label>
                                <input type="text" class="form-control desactivado" id="cert_nombre" readonly placeholder="Sin certificado">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="certificado_clave">Clave del certificado</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="certificado_clave" name="certificado_clave" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary btn-toggle-clave" type="button" tabindex="-1" title="Mostrar / ocultar clave">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn btn-intesis">
                    <i class="bi bi-save2"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <script>
        window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;
    </script>
<?php endif; ?>
<script>
    window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
(function () {
    'use strict';

    const modal       = document.getElementById('modalEmpresa');
    const form        = document.getElementById('formularioEmpresa');
    const titulo      = document.getElementById('modalEmpresaTitulo');
    const tabGeneralBtn = document.getElementById('tab-general-btn');

    const appUrl = <?= json_encode($appUrl) ?>;

    modal.addEventListener('show.bs.modal', function (e) {
        const btn  = e.relatedTarget;
        const modo = btn ? btn.dataset.modo : 'crear';

        // Siempre volver a la pestaña general al abrir
        bootstrap.Tab.getOrCreateInstance(tabGeneralBtn).show();

        // Limpiar archivo seleccionado
        document.getElementById('cert_archivo').value = '';

        if (modo === 'crear') {
            titulo.textContent = 'Nueva empresa';
            form.action = appUrl + '/sistema/empresas/crear';
            form.reset();
            document.getElementById('empresa_id').value = '';
            document.querySelector('input[name="ambiente_sri"][value="1"]').checked = true;
            document.getElementById('cert_nombre').value = '';
            document.getElementById('descuento_maximo_facturas').value = '0';
            document.getElementById('descuento_maximo_notas_venta').value = '0';
            document.getElementById('formula_descuento').value = 'C';
        } else {
            titulo.textContent = 'Editar empresa';
            form.action = appUrl + '/sistema/empresas/editar';

            document.getElementById('empresa_id').value         = btn.dataset.id;
            document.getElementById('ruc').value                = btn.dataset.ruc;
            document.getElementById('razon_social').value       = btn.dataset.razon;
            document.getElementById('nombre_comercial').value   = btn.dataset.comercial;
            document.getElementById('direccion').value          = btn.dataset.direccion;
            document.getElementById('telefono').value           = btn.dataset.telefono;
            document.getElementById('email').value              = btn.dataset.email;
            document.getElementById('obligado_contabilidad').checked  = btn.dataset.obligado === '1';
            document.getElementById('contribuyente_especial').checked = btn.dataset.especial === '1';

            // Pestaña Facturación
            const ambiente = btn.dataset.ambiente || '1';
            const radioAmbiente = document.querySelector('input[name="ambiente_sri"][value="' + ambiente + '"]');
            if (radioAmbiente) radioAmbiente.checked = true;

            document.getElementById('num_contribuyente_especial').value = btn.dataset.numEspecial || '';
            document.getElementById('certificado_clave').value          = btn.dataset.certClave  || '';
            document.getElementById('descuento_maximo_facturas').value    = btn.dataset.descuentoFacturas   || '0';
            document.getElementById('descuento_maximo_notas_venta').value = btn.dataset.descuentoNotasVenta || '0';
            document.getElementById('formula_descuento').value            = btn.dataset.formulaDescuento    || 'C';

            // Mostrar nombre del archivo actual
            const ruta = btn.dataset.certRuta || '';
            document.getElementById('cert_nombre').value = ruta ? ruta.split('/').pop() : '';
        }
    });

    // Actualizar nombre del archivo al seleccionar uno nuevo
    document.getElementById('cert_archivo').addEventListener('change', function () {
        const nombre = this.files.length > 0 ? this.files[0].name : '';
        document.getElementById('cert_nombre').value = nombre;
    });

    // Toggle mostrar/ocultar clave del certificado: manejado de forma generica en sistema.js (.btn-toggle-clave)
}());
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
