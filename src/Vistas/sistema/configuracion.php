<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
$empresaActivaId = (int) ($usuario['empresa_id'] ?? 0);
$panelConfiguracion = $panelConfiguracion ?? 'estados';
$tituloSeccion = $tituloSeccion ?? 'Configuracion';
?>
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand navbar-intesis">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><button class="nav-link btn btn-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li>
                <li class="nav-item">
                    <span class="nav-link breadcrumb-navbar">
                        <span><?= htmlspecialchars($appNombre) ?></span><i class="bi bi-chevron-right"></i><span>Sistema</span><i class="bi bi-chevron-right"></i><span>Configuracion</span><i class="bi bi-chevron-right"></i><strong><?= htmlspecialchars($tituloSeccion) ?></strong>
                    </span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block"><span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span></li>
                <li class="nav-item"><form action="<?= $appUrl ?>/salir" method="post"><button class="btn btn-salir" type="submit" title="Cerrar sesion"><i class="bi bi-power"></i></button></form></li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar sidebar-intesis" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="<?= $appUrl ?>/dashboard" class="brand-link"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-text"><?= htmlspecialchars($appNombre) ?></span></a>
        </div>
        <div class="sidebar-wrapper"><?php require $configuracion->raiz() . '/src/Vistas/plantillas/menu_lateral.php'; ?></div>
    </aside>

    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <section class="panel-crud">
                    <?php if ($panelConfiguracion === 'estados'): ?>
                        <?php if ($permisos['estado_crear']): ?>
                            <div class="panel-crud-cabecera"><button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalEstado" data-modo="crear"><i class="bi bi-plus-square"></i> Nuevo estado</button></div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table id="tablaEstados" class="table table-hover tabla-intesis align-middle w-100">
                                <thead><tr><th>Modulo</th><th>Entidad</th><th>Codigo</th><th>Nombre</th><th>Orden</th><th>Activo</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($estados as $estado): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($estado['sis_estado_modulo']) ?></td>
                                            <td><?= htmlspecialchars($estado['sis_estado_entidad']) ?></td>
                                            <td><?= htmlspecialchars($estado['sis_estado_codigo']) ?></td>
                                            <td><?= htmlspecialchars($estado['sis_estado_nombre']) ?></td>
                                            <td><?= (int) $estado['sis_estado_orden'] ?></td>
                                            <td><span class="badge estado-badge <?= $estado['sis_estado_activo'] ? 'estado-activo' : 'estado-inactivo' ?>"><?= $estado['sis_estado_activo'] ? 'SI' : 'NO' ?></span></td>
                                            <td class="text-end acciones-tabla">
                                                <?php if ($permisos['estado_editar']): ?>
                                                    <button type="button" class="btn btn-accion btn-editar-estado" title="Editar estado" data-bs-toggle="modal" data-bs-target="#modalEstado" data-modo="editar" data-id="<?= (int) $estado['sis_estado_id'] ?>" data-modulo="<?= htmlspecialchars($estado['sis_estado_modulo']) ?>" data-entidad="<?= htmlspecialchars($estado['sis_estado_entidad']) ?>" data-codigo="<?= htmlspecialchars($estado['sis_estado_codigo']) ?>" data-nombre="<?= htmlspecialchars($estado['sis_estado_nombre']) ?>" data-descripcion="<?= htmlspecialchars($estado['sis_estado_descripcion'] ?? '') ?>" data-orden="<?= (int) $estado['sis_estado_orden'] ?>"><i class="bi bi-pencil-square"></i></button>
                                                <?php endif; ?>
                                                <?php if ($estado['sis_estado_activo'] && $permisos['estado_inactivar']): ?>
                                                    <form action="<?= $appUrl ?>/sistema/configuracion/estados/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_ESTADO"><input type="hidden" name="estado_id" value="<?= (int) $estado['sis_estado_id'] ?>"><button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar estado"><i class="bi bi-toggle-off"></i></button></form>
                                                <?php endif; ?>
                                                <?php if (!$estado['sis_estado_activo'] && $permisos['estado_activar']): ?>
                                                    <form action="<?= $appUrl ?>/sistema/configuracion/estados/activar" method="post" class="d-inline"><input type="hidden" name="estado_id" value="<?= (int) $estado['sis_estado_id'] ?>"><button type="submit" class="btn btn-accion btn-activar" title="Activar estado"><i class="bi bi-toggle-on"></i></button></form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($panelConfiguracion === 'mensajes'): ?>
                        <?php if ($permisos['mensaje_crear']): ?>
                            <div class="panel-crud-cabecera"><button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalMensajeError" data-modo="crear"><i class="bi bi-plus-square"></i> Nuevo mensaje</button></div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table id="tablaMensajesError" class="table table-hover tabla-intesis align-middle w-100">
                                <thead><tr><th>Codigo</th><th>Tipo</th><th>Titulo</th><th>Tiempo</th><th>Posicion</th><th>Modulo</th><th>Entidad</th><th>Activo</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($mensajesError as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['sis_mensaje_errores_codigo']) ?></td>
                                            <td><?= htmlspecialchars($item['sis_mensaje_errores_tipo']) ?></td>
                                            <td><?= htmlspecialchars($item['sis_mensaje_errores_titulo']) ?></td>
                                            <td><?= (int) ($item['sis_mensaje_errores_tiempo'] ?? 0) ?></td>
                                            <td><?= (int) ($item['sis_mensaje_errores_posicion'] ?? 4) ?></td>
                                            <td><?= htmlspecialchars($item['sis_mensaje_errores_modulo']) ?></td>
                                            <td><?= htmlspecialchars($item['sis_mensaje_errores_entidad']) ?></td>
                                            <td><span class="badge estado-badge <?= $item['sis_mensaje_errores_activo'] ? 'estado-activo' : 'estado-inactivo' ?>"><?= $item['sis_mensaje_errores_activo'] ? 'SI' : 'NO' ?></span></td>
                                            <td class="text-end acciones-tabla">
                                                <?php if ($permisos['mensaje_editar']): ?>
                                                    <button type="button" class="btn btn-accion btn-editar-mensaje" title="Editar mensaje" data-bs-toggle="modal" data-bs-target="#modalMensajeError" data-modo="editar" data-id="<?= (int) $item['sis_mensaje_errores_id'] ?>" data-codigo="<?= htmlspecialchars($item['sis_mensaje_errores_codigo']) ?>" data-tipo="<?= htmlspecialchars($item['sis_mensaje_errores_tipo']) ?>" data-titulo="<?= htmlspecialchars($item['sis_mensaje_errores_titulo']) ?>" data-mensaje="<?= htmlspecialchars($item['sis_mensaje_errores_mensaje']) ?>" data-icono="<?= htmlspecialchars($item['sis_mensaje_errores_icono']) ?>" data-modulo="<?= htmlspecialchars($item['sis_mensaje_errores_modulo']) ?>" data-entidad="<?= htmlspecialchars($item['sis_mensaje_errores_entidad']) ?>" data-tiempo="<?= (int) ($item['sis_mensaje_errores_tiempo'] ?? 0) ?>" data-posicion="<?= (int) ($item['sis_mensaje_errores_posicion'] ?? 4) ?>"><i class="bi bi-pencil-square"></i></button>
                                                <?php endif; ?>
                                                <?php if ($item['sis_mensaje_errores_activo'] && $permisos['mensaje_inactivar']): ?>
                                                    <form action="<?= $appUrl ?>/sistema/configuracion/mensajes-error/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_MENSAJE"><input type="hidden" name="mensaje_id" value="<?= (int) $item['sis_mensaje_errores_id'] ?>"><button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar mensaje"><i class="bi bi-toggle-off"></i></button></form>
                                                <?php endif; ?>
                                                <?php if (!$item['sis_mensaje_errores_activo'] && $permisos['mensaje_activar']): ?>
                                                    <form action="<?= $appUrl ?>/sistema/configuracion/mensajes-error/activar" method="post" class="d-inline"><input type="hidden" name="mensaje_id" value="<?= (int) $item['sis_mensaje_errores_id'] ?>"><button type="submit" class="btn btn-accion btn-activar" title="Activar mensaje"><i class="bi bi-toggle-on"></i></button></form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($panelConfiguracion === 'tipos'): ?>
                        <?php if ($permisos['tipo_crear']): ?>
                            <div class="panel-crud-cabecera"><button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalTipoDocumento" data-modo="crear"><i class="bi bi-plus-square"></i> Nuevo tipo</button></div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table id="tablaTiposDocumento" class="table table-hover tabla-intesis align-middle w-100">
                                <thead>
                                    <tr>
                                        <th>Modulo</th>
                                        <?php if ($esSuperusuario): ?><th>Codigo</th><?php endif; ?>
                                        <th>Nombre</th>
                                        <?php if ($esSuperusuario): ?>
                                            <th>Contabilidad</th>
                                            <th>Inventario</th>
                                            <th>Estado</th>
                                        <?php endif; ?>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tiposDocumento as $tipo): ?>
                                        <?php if (!$esSuperusuario && $tipo['sis_estado_codigo'] !== 'ACTIVO') continue; ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tipo['sis_tipo_documento_modulo']) ?></td>
                                            <?php if ($esSuperusuario): ?><td><?= htmlspecialchars($tipo['sis_tipo_documento_codigo']) ?></td><?php endif; ?>
                                            <td><?= htmlspecialchars($tipo['sis_tipo_documento_nombre']) ?></td>
                                            <?php if ($esSuperusuario): ?>
                                                <td><span class="badge estado-badge <?= $tipo['sis_tipo_documento_afecta_contabilidad'] ? 'estado-activo' : 'estado-inactivo' ?>"><?= $tipo['sis_tipo_documento_afecta_contabilidad'] ? 'SI' : 'NO' ?></span></td>
                                                <td><span class="badge estado-badge <?= $tipo['sis_tipo_documento_afecta_inventario'] ? 'estado-activo' : 'estado-inactivo' ?>"><?= $tipo['sis_tipo_documento_afecta_inventario'] ? 'SI' : 'NO' ?></span></td>
                                                <td><span class="badge estado-badge <?= $tipo['sis_estado_codigo'] === 'ACTIVO' ? 'estado-activo' : 'estado-inactivo' ?>"><?= htmlspecialchars($tipo['sis_estado_nombre']) ?></span></td>
                                            <?php endif; ?>
                                            <td class="text-end acciones-tabla">
                                                <button type="button" class="btn btn-accion btn-secuencias-tipo" title="Secuencias" data-bs-toggle="modal" data-bs-target="#modalSecuenciasTipo" data-tipo="<?= (int) $tipo['sis_tipo_documento_id'] ?>" data-nombre="<?= htmlspecialchars($tipo['sis_tipo_documento_nombre']) ?>"><i class="bi bi-list-ol"></i></button>
                                                <?php if ($esSuperusuario && $permisos['tipo_editar']): ?>
                                                    <button type="button" class="btn btn-accion btn-editar-tipo" title="Editar tipo" data-bs-toggle="modal" data-bs-target="#modalTipoDocumento" data-modo="editar" data-id="<?= (int) $tipo['sis_tipo_documento_id'] ?>" data-modulo="<?= htmlspecialchars($tipo['sis_tipo_documento_modulo']) ?>" data-codigo="<?= htmlspecialchars($tipo['sis_tipo_documento_codigo']) ?>" data-nombre="<?= htmlspecialchars($tipo['sis_tipo_documento_nombre']) ?>" data-descripcion="<?= htmlspecialchars($tipo['sis_tipo_documento_descripcion'] ?? '') ?>" data-contabilidad="<?= $tipo['sis_tipo_documento_afecta_contabilidad'] ? '1' : '0' ?>" data-inventario="<?= $tipo['sis_tipo_documento_afecta_inventario'] ? '1' : '0' ?>"><i class="bi bi-pencil-square"></i></button>
                                                <?php endif; ?>
                                                <?php if ($esSuperusuario && $tipo['sis_estado_codigo'] === 'ACTIVO' && $permisos['tipo_inactivar']): ?>
                                                    <form action="<?= $appUrl ?>/sistema/configuracion/tipos-documento/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_TIPO_DOCUMENTO"><input type="hidden" name="tipo_documento_id" value="<?= (int) $tipo['sis_tipo_documento_id'] ?>"><button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar tipo"><i class="bi bi-toggle-off"></i></button></form>
                                                <?php endif; ?>
                                                <?php if ($esSuperusuario && $tipo['sis_estado_codigo'] !== 'ACTIVO' && $permisos['tipo_activar']): ?>
                                                    <form action="<?= $appUrl ?>/sistema/configuracion/tipos-documento/activar" method="post" class="d-inline"><input type="hidden" name="tipo_documento_id" value="<?= (int) $tipo['sis_tipo_documento_id'] ?>"><button type="submit" class="btn btn-accion btn-activar" title="Activar tipo"><i class="bi bi-toggle-on"></i></button></form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-none">
                            <table><tbody id="filasSecuenciasFuente">
                                <?php foreach ($secuencias as $secuencia): ?>
                                    <tr class="fila-secuencia-fuente" data-tipo="<?= (int) $secuencia['sis_tipo_documento_id'] ?>">
                                        <td><?= htmlspecialchars($secuencia['sis_empresa_nombre_comercial'] ?: $secuencia['sis_empresa_razon_social']) ?></td>
                                        <td><?= htmlspecialchars($secuencia['inv_bodega_nombre'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($secuencia['sis_secuencias_establecimiento']) ?>-<?= htmlspecialchars($secuencia['sis_secuencias_punto_emision']) ?></td>
                                        <td><?= str_pad((string) $secuencia['sis_secuencias_actual'], 9, '0', STR_PAD_LEFT) ?></td>
                                        <td><span class="badge estado-badge <?= $secuencia['sis_estado_codigo'] === 'ACTIVO' ? 'estado-activo' : 'estado-inactivo' ?>"><?= htmlspecialchars($secuencia['sis_estado_nombre']) ?></span></td>
                                        <td class="text-end acciones-tabla">
                                            <?php if ($permisos['secuencia_editar']): ?>
                                                <button type="button" class="btn btn-accion btn-editar-secuencia" title="Editar secuencia" data-bs-toggle="modal" data-bs-target="#modalSecuencia" data-modo="editar" data-id="<?= (int) $secuencia['sis_secuencias_id'] ?>" data-tipo="<?= (int) $secuencia['sis_tipo_documento_id'] ?>" data-empresa="<?= (int) $secuencia['sis_empresa_id'] ?>" data-bodega="<?= (int) ($secuencia['inv_bodega_id'] ?? 0) ?>" data-establecimiento="<?= htmlspecialchars($secuencia['sis_secuencias_establecimiento']) ?>" data-punto="<?= htmlspecialchars($secuencia['sis_secuencias_punto_emision']) ?>" data-desde="<?= (int) $secuencia['sis_secuencias_desde'] ?>" data-actual="<?= (int) $secuencia['sis_secuencias_actual'] ?>" data-hasta="<?= (int) $secuencia['sis_secuencias_hasta'] ?>" data-observacion="<?= htmlspecialchars($secuencia['sis_secuencias_observacion'] ?? '') ?>"><i class="bi bi-pencil-square"></i></button>
                                            <?php endif; ?>
                                            <?php if ($secuencia['sis_estado_codigo'] === 'ACTIVO' && $permisos['secuencia_inactivar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/configuracion/secuencias/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_SECUENCIA"><input type="hidden" name="secuencia_id" value="<?= (int) $secuencia['sis_secuencias_id'] ?>"><button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar secuencia"><i class="bi bi-toggle-off"></i></button></form>
                                            <?php endif; ?>
                                            <?php if ($secuencia['sis_estado_codigo'] !== 'ACTIVO' && $permisos['secuencia_activar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/configuracion/secuencias/activar" method="post" class="d-inline"><input type="hidden" name="secuencia_id" value="<?= (int) $secuencia['sis_secuencias_id'] ?>"><button type="submit" class="btn btn-accion btn-activar" title="Activar secuencia"><i class="bi bi-toggle-on"></i></button></form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody></table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade modal-intesis" id="modalEstado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioEstado" method="post" action="<?= $appUrl ?>/sistema/configuracion/estados/crear">
            <input type="hidden" name="estado_id" id="estado_id">
            <div class="modal-header"><div><p class="modal-etiqueta">Configuracion</p><h2 class="modal-title" id="modalEstadoTitulo">Nuevo estado</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label" for="estado_modulo">Modulo</label><input type="text" class="form-control form-control-sm" id="estado_modulo" name="modulo" required></div>
                    <div class="col-md-4"><label class="form-label" for="estado_entidad">Entidad</label><input type="text" class="form-control form-control-sm" id="estado_entidad" name="entidad" required></div>
                    <div class="col-md-4"><label class="form-label" for="estado_codigo">Codigo</label><input type="text" class="form-control form-control-sm" id="estado_codigo" name="codigo" required></div>
                    <div class="col-md-8"><label class="form-label" for="estado_nombre">Nombre</label><input type="text" class="form-control form-control-sm" id="estado_nombre" name="nombre" required></div>
                    <div class="col-md-4"><label class="form-label" for="estado_orden">Orden</label><input type="number" class="form-control form-control-sm" id="estado_orden" name="orden" min="1" value="1" required></div>
                    <div class="col-12"><label class="form-label" for="estado_descripcion">Descripcion</label><textarea class="form-control form-control-sm" id="estado_descripcion" name="descripcion" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalMensajeError" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioMensajeError" method="post" action="<?= $appUrl ?>/sistema/configuracion/mensajes-error/crear">
            <input type="hidden" name="mensaje_id" id="mensaje_id">
            <div class="modal-header"><div><p class="modal-etiqueta">Configuracion</p><h2 class="modal-title" id="modalMensajeTitulo">Nuevo mensaje</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label" for="mensaje_codigo">Codigo</label><input type="text" class="form-control form-control-sm" id="mensaje_codigo" name="codigo" required></div>
                    <div class="col-md-3"><label class="form-label" for="mensaje_tipo">Tipo</label><select class="form-control form-control-sm" id="mensaje_tipo" name="tipo"><option>ERROR</option><option>ALERTA</option><option>INFO</option><option>EXITO</option><option>CONFIRMACION</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="mensaje_icono">Icono</label><select class="form-control form-control-sm" id="mensaje_icono" name="icono"><option>error</option><option>warning</option><option>info</option><option>success</option><option>question</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="mensaje_tiempo">Tiempo</label><input type="number" class="form-control form-control-sm" id="mensaje_tiempo" name="tiempo" min="0" value="0" required></div>
                    <div class="col-md-3"><label class="form-label" for="mensaje_posicion">Posicion</label><select class="form-control form-control-sm" id="mensaje_posicion" name="posicion"><option value="1">top</option><option value="2">top-end</option><option value="3">center-end</option><option value="4">center</option><option value="5">bottom-end</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="mensaje_modulo">Modulo</label><input type="text" class="form-control form-control-sm" id="mensaje_modulo" name="modulo" required></div>
                    <div class="col-md-6"><label class="form-label" for="mensaje_entidad">Entidad</label><input type="text" class="form-control form-control-sm" id="mensaje_entidad" name="entidad" required></div>
                    <div class="col-12"><label class="form-label" for="mensaje_titulo">Titulo</label><input type="text" class="form-control form-control-sm" id="mensaje_titulo" name="titulo" required></div>
                    <div class="col-12"><label class="form-label" for="mensaje_texto">Mensaje</label><textarea class="form-control form-control-sm" id="mensaje_texto" name="mensaje" rows="3" required></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalTipoDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioTipoDocumento" method="post" action="<?= $appUrl ?>/sistema/configuracion/tipos-documento/crear">
            <input type="hidden" name="tipo_documento_id" id="tipo_documento_id">
            <div class="modal-header"><div><p class="modal-etiqueta">Configuracion</p><h2 class="modal-title" id="modalTipoDocumentoTitulo">Nuevo tipo</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label" for="tipo_documento_modulo">Modulo</label><select class="form-control form-control-sm" id="tipo_documento_modulo" name="modulo" required><option value="VENTAS">VENTAS</option><option value="COMPRAS">COMPRAS</option><option value="INVENTARIO">INVENTARIO</option><option value="CONTABILIDAD">CONTABILIDAD</option><option value="SISTEMA">SISTEMA</option></select></div>
                    <div class="col-md-4"><label class="form-label" for="tipo_documento_codigo">Codigo</label><input type="text" class="form-control form-control-sm" id="tipo_documento_codigo" name="codigo" maxlength="30" required></div>
                    <div class="col-md-4"><label class="form-label" for="tipo_documento_nombre">Nombre</label><input type="text" class="form-control form-control-sm" id="tipo_documento_nombre" name="nombre" maxlength="120" required></div>
                    <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="tipo_documento_contabilidad" name="afecta_contabilidad" value="1"><label class="form-check-label" for="tipo_documento_contabilidad">Afecta contabilidad</label></div></div>
                    <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="tipo_documento_inventario" name="afecta_inventario" value="1"><label class="form-check-label" for="tipo_documento_inventario">Afecta inventario</label></div></div>
                    <div class="col-12"><label class="form-label" for="tipo_documento_descripcion">Descripcion</label><textarea class="form-control form-control-sm" id="tipo_documento_descripcion" name="descripcion" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalSecuenciasTipo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><div><p class="modal-etiqueta">Secuencias</p><h2 class="modal-title" id="modalSecuenciasTipoTitulo">Secuencias</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <?php if ($permisos['secuencia_crear']): ?>
                    <div class="panel-crud-cabecera"><button type="button" class="btn btn-intesis btn-crud" id="btnNuevaSecuencia" data-bs-toggle="modal" data-bs-target="#modalSecuencia" data-modo="crear"><i class="bi bi-plus-square"></i> Nueva secuencia</button></div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-hover tabla-intesis-dinamica align-middle w-100">
                        <thead><tr><th>Empresa</th><th>Bodega</th><th>Punto</th><th>Actual</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody id="tablaSecuenciasTipo"><tr><td colspan="6" class="text-center text-muted">Seleccione un tipo de documento.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i> Volver</button></div>
        </div>
    </div>
</div>

<div class="modal fade modal-intesis" id="modalSecuencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioSecuencia" method="post" action="<?= $appUrl ?>/sistema/configuracion/secuencias/crear">
            <input type="hidden" name="secuencia_id" id="secuencia_id">
            <input type="hidden" name="tipo_documento_id" id="secuencia_tipo_documento_id">
            <?php if (!$esSuperusuario): ?><input type="hidden" name="empresa_id" id="secuencia_empresa_oculta" value="<?= $empresaActivaId ?>"><?php endif; ?>
            <div class="modal-header"><div><p class="modal-etiqueta">Secuencia</p><h2 class="modal-title" id="modalSecuenciaTitulo">Nueva secuencia</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <?php if ($esSuperusuario): ?>
                        <div class="col-md-6"><label class="form-label" for="secuencia_empresa_id">Empresa</label><select class="form-control form-control-sm" id="secuencia_empresa_id" name="empresa_id" required><option value="">Seleccione</option><?php foreach ($empresasSecuencia as $empresa): ?><option value="<?= (int) $empresa['sis_empresa_id'] ?>"><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial'] ?: $empresa['sis_empresa_razon_social']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6" id="contenedor_secuencia_bodega"><label class="form-label" for="secuencia_bodega_id">Bodega</label><select class="form-control form-control-sm" id="secuencia_bodega_id" name="bodega_id"><option value="">Sin bodega</option></select></div>
                    <?php else: ?>
                        <div class="col-12" id="contenedor_secuencia_bodega"><label class="form-label" for="secuencia_bodega_id">Bodega</label><select class="form-control form-control-sm" id="secuencia_bodega_id" name="bodega_id"><option value="">Sin bodega</option><?php foreach ($bodegasPorEmpresa[$empresaActivaId] ?? [] as $bodega): ?><option value="<?= (int) $bodega['inv_bodega_id'] ?>" <?= $bodega['inv_bodega_es_principal'] ? 'data-principal="1"' : '' ?>><?= htmlspecialchars($bodega['inv_bodega_codigo'] . ' - ' . $bodega['inv_bodega_nombre']) ?></option><?php endforeach; ?></select></div>
                    <?php endif; ?>
                    <div class="col-12">
                        <div class="alert alert-info py-2 px-3 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Prefijo del numero:</strong> el numero de documento se genera como <code>PREFIJO-<span id="preview_est">001</span>-<span id="preview_pto">001</span>-000000001</code>.
                            Para movimientos internos use <strong>001-001</strong> si no tiene multiples sedes o puntos de emision.
                        </div>
                    </div>
                    <div class="col-12 d-none" id="alerta_bodega_sin_est">
                        <div class="alert alert-warning py-2 px-3 mb-0 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Esta bodega no tiene establecimiento/punto configurados. Los valores que ingreses aqui se guardaran tambien en la bodega.
                        </div>
                    </div>
                    <div class="<?= $esSuperusuario ? 'col-md-3' : 'col-md-4' ?>">
                        <label class="form-label" for="secuencia_establecimiento">Sede / Establecimiento <span class="text-muted">(3 dig.)</span></label>
                        <input type="text" class="form-control form-control-sm" id="secuencia_establecimiento" name="establecimiento" maxlength="3" inputmode="numeric" placeholder="001" required>
                        <div class="form-text">Numero de local o sede (ej. 001)</div>
                    </div>
                    <div class="<?= $esSuperusuario ? 'col-md-3' : 'col-md-4' ?>">
                        <label class="form-label" for="secuencia_punto_emision">Punto de emision <span class="text-muted">(3 dig.)</span></label>
                        <input type="text" class="form-control form-control-sm" id="secuencia_punto_emision" name="punto_emision" maxlength="3" inputmode="numeric" placeholder="001" required>
                        <div class="form-text">Punto o terminal dentro del local (ej. 001)</div>
                    </div>
                    <div class="col-md-3"><label class="form-label" for="secuencia_desde">Numero inicial</label><input type="number" class="form-control form-control-sm" id="secuencia_desde" name="desde" min="1" max="999999999" value="1" required><div class="form-text">Primer numero valido</div></div>
                    <div class="col-md-3"><label class="form-label" for="secuencia_actual">Numero actual</label><input type="number" class="form-control form-control-sm" id="secuencia_actual" name="actual" min="1" max="999999999" value="1" required><div class="form-text">Siguiente a emitir</div></div>
                    <div class="col-md-3"><label class="form-label" for="secuencia_hasta">Numero final</label><input type="number" class="form-control form-control-sm" id="secuencia_hasta" name="hasta" min="1" max="999999999" value="999999999" required><div class="form-text">Ultimo numero permitido</div></div>
                    <div class="col-12"><label class="form-label" for="secuencia_observacion">Nota interna</label><textarea class="form-control form-control-sm" id="secuencia_observacion" name="observacion" rows="2" placeholder="Descripcion opcional de esta secuencia..."></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancelar</button><button type="submit" class="btn btn-intesis"><i class="bi bi-save2"></i> Guardar</button></div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje)): ?><script>window.INTESIS_MENSAJE = <?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
<script>
window.INTESIS_MENSAJES = <?= json_encode($mensajesSistema ?? [], JSON_UNESCAPED_UNICODE) ?>;
window.INTESIS_TIPO_SELECCIONADO = '';
window.INTESIS_BODEGAS_POR_EMPRESA = <?= json_encode($bodegasPorEmpresa ?? [], JSON_UNESCAPED_UNICODE) ?>;
window.INTESIS_TIPOS_DOCUMENTO_INVENTARIO = <?= json_encode(
    array_column(
        array_filter($tiposDocumento ?? [], fn($t) => !empty($t['sis_tipo_documento_afecta_inventario'])),
        null, 'sis_tipo_documento_id'
    ),
    JSON_UNESCAPED_UNICODE
) ?>;
/* Preview en vivo del prefijo al escribir establecimiento/punto_emision */
(function () {
    function actualizarPreview() {
        var est = (document.getElementById('secuencia_establecimiento')?.value || '001').padStart(3, '0');
        var pto = (document.getElementById('secuencia_punto_emision')?.value || '001').padStart(3, '0');
        var el1 = document.getElementById('preview_est');
        var el2 = document.getElementById('preview_pto');
        if (el1) el1.textContent = est;
        if (el2) el2.textContent = pto;
    }
    document.addEventListener('input', function (e) {
        if (e.target.id === 'secuencia_establecimiento' || e.target.id === 'secuencia_punto_emision') {
            actualizarPreview();
        }
    });
})();
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
