<?php
$claseCuerpo = 'layout-fixed sidebar-expand-lg bg-body-tertiary dashboard-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
$esSuperusuario = strtoupper((string) ($usuario['perfil_codigo'] ?? $usuario['perfil'] ?? '')) === 'SUPERUSUARIO';
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
                        <strong>Usuarios</strong>
                    </span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-md-block">
                    <span class="usuario-navbar"><?= htmlspecialchars($usuario['nombre']) ?></span>
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
                            <button type="button" class="btn btn-intesis btn-crud" data-bs-toggle="modal" data-bs-target="#modalUsuario" data-modo="crear">
                                <i class="bi bi-person-plus"></i>
                                Nuevo usuario
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="tablaUsuarios" class="table table-hover tabla-intesis align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>Perfil</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['sis_empresa_nombre_comercial']) ?></td>
                                        <td><?= htmlspecialchars($item['sis_usuarios_nombre']) ?></td>
                                        <td><?= htmlspecialchars($item['sis_usuarios_correo']) ?></td>
                                        <td><?= htmlspecialchars($item['sis_perfil_nombre']) ?></td>
                                        <td>
                                            <span class="badge estado-badge estado-<?= strtolower((string) $item['sis_estado_codigo']) ?>">
                                                <?= htmlspecialchars($item['sis_estado_nombre']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end acciones-tabla">
                                            <?php if ($permisos['editar']): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-accion btn-editar-usuario"
                                                    title="Editar usuario"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalUsuario"
                                                    data-modo="editar"
                                                    data-usuario="<?= (int) $item['sis_usuarios_id'] ?>"
                                                    data-asignacion="<?= (int) $item['sis_usuario_empresa_id'] ?>"
                                                    data-empresa="<?= (int) $item['sis_empresa_id'] ?>"
                                                    data-perfil="<?= (int) $item['sis_perfil_id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($item['sis_usuarios_nombre']) ?>"
                                                    data-correo="<?= htmlspecialchars($item['sis_usuarios_correo']) ?>"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($permisos['clave']): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-accion btn-clave"
                                                    title="Restablecer clave"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalClave"
                                                    data-asignacion="<?= (int) $item['sis_usuario_empresa_id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($item['sis_usuarios_nombre']) ?>"
                                                >
                                                    <i class="bi bi-key"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($item['sis_estado_codigo'] !== 'ACTIVO' && $permisos['activar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/usuarios/activar" method="post" class="d-inline">
                                                    <input type="hidden" name="asignacion_id" value="<?= (int) $item['sis_usuario_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-activar" title="Activar usuario">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($item['sis_estado_codigo'] === 'ACTIVO' && $permisos['inactivar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/usuarios/inactivar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_INACTIVAR_USUARIO">
                                                    <input type="hidden" name="asignacion_id" value="<?= (int) $item['sis_usuario_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-inactivar" title="Inactivar usuario">
                                                        <i class="bi bi-toggle-off"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($item['sis_estado_codigo'] === 'ACTIVO' && $permisos['bloquear']): ?>
                                                <form action="<?= $appUrl ?>/sistema/usuarios/bloquear" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_BLOQUEAR_USUARIO">
                                                    <input type="hidden" name="asignacion_id" value="<?= (int) $item['sis_usuario_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-bloquear" title="Bloquear usuario">
                                                        <i class="bi bi-person-lock"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($permisos['eliminar']): ?>
                                                <form action="<?= $appUrl ?>/sistema/usuarios/eliminar" method="post" class="d-inline formulario-confirmar" data-codigo-mensaje="CONFIRMAR_ELIMINAR_USUARIO">
                                                    <input type="hidden" name="asignacion_id" value="<?= (int) $item['sis_usuario_empresa_id'] ?>">
                                                    <button type="submit" class="btn btn-accion btn-eliminar" title="Eliminar usuario">
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

<div class="modal fade modal-intesis" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="formularioUsuario" method="post" action="<?= $appUrl ?>/sistema/usuarios/crear">
            <input type="hidden" name="asignacion_id" id="usuario_asignacion_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Sistema</p>
                    <h2 class="modal-title" id="modalUsuarioTitulo">Nuevo usuario</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <div class="row g-2">
                    <?php if (count($empresas) > 1): ?>
                    <div class="col-md-6 asignacion-simple">
                        <label class="form-label" for="usuario_empresa_id">Empresa</label>
                        <select class="form-control form-control-sm" id="usuario_empresa_id" name="empresa_id" required>
                            <option value="">Seleccione empresa</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= (int) $empresa['sis_empresa_id'] ?>"><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" id="usuario_empresa_id" name="empresa_id" value="<?= (int) ($empresas[0]['sis_empresa_id'] ?? 0) ?>">
                    <?php endif; ?>
                    <div class="col-md-6 asignacion-simple">
                        <label class="form-label" for="usuario_perfil_id">Perfil</label>
                        <select class="form-control form-control-sm" id="usuario_perfil_id" name="perfil_id" required>
                            <option value="">Seleccione perfil</option>
                            <?php foreach ($perfiles as $perfil): ?>
                                <option value="<?= (int) $perfil['sis_perfil_id'] ?>" data-empresa="<?= (int) $perfil['sis_empresa_id'] ?>">
                                    <?= htmlspecialchars($perfil['sis_perfil_nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($esSuperusuario): ?>
                        <div class="col-12 asignaciones-multiples d-none">
                            <div class="asignaciones-usuario" id="asignacionesUsuario">
                                <div class="asignacion-usuario-fila" data-indice="0">
                                    <input type="hidden" class="asignacion-id" name="asignaciones[0][asignacion_id]" value="">
                                    <div>
                                        <label class="form-label" for="asignacion_empresa_0">Empresa</label>
                                        <select class="form-control form-control-sm asignacion-empresa" id="asignacion_empresa_0" name="asignaciones[0][empresa_id]">
                                            <option value="">Seleccione empresa</option>
                                            <?php foreach ($empresas as $empresa): ?>
                                                <option value="<?= (int) $empresa['sis_empresa_id'] ?>"><?= htmlspecialchars($empresa['sis_empresa_nombre_comercial']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" for="asignacion_perfil_0">Perfil</label>
                                        <select class="form-control form-control-sm asignacion-perfil" id="asignacion_perfil_0" name="asignaciones[0][perfil_id]">
                                            <option value="">Seleccione perfil</option>
                                            <?php foreach ($perfiles as $perfil): ?>
                                                <option value="<?= (int) $perfil['sis_perfil_id'] ?>" data-empresa="<?= (int) $perfil['sis_empresa_id'] ?>">
                                                    <?= htmlspecialchars($perfil['sis_perfil_nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-accion btn-eliminar quitar-asignacion" title="Quitar asignacion">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <div class="asignacion-opciones">
                                        <label class="form-check form-check-inline mb-0">
                                            <input class="form-check-input asignacion-predeterminada" type="checkbox" name="asignaciones[0][predeterminada]" value="1">
                                            <span class="form-check-label">Predeterminada</span>
                                        </label>
                                        <label class="form-check form-check-inline mb-0">
                                            <input class="form-check-input asignacion-inactivar" type="checkbox" name="asignaciones[0][inactivar]" value="1">
                                            <span class="form-check-label">Inactivar</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secundario btn-sm mt-2" id="agregarAsignacionUsuario">
                                <i class="bi bi-plus-lg"></i>
                                Agregar empresa
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label" for="usuario_nombre">Nombre</label>
                        <input type="text" class="form-control form-control-sm" id="usuario_nombre" name="nombre" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="usuario_correo">Correo</label>
                        <input type="email" class="form-control form-control-sm" id="usuario_correo" name="correo" required>
                    </div>
                    <div class="col-md-6 campos-clave-crear">
                        <label class="form-label" for="usuario_clave">Clave</label>
                        <input type="password" class="form-control form-control-sm" id="usuario_clave" name="clave" minlength="8">
                    </div>
                    <div class="col-md-6 campos-clave-crear">
                        <label class="form-label" for="usuario_confirmar_clave">Confirmar clave</label>
                        <input type="password" class="form-control form-control-sm" id="usuario_confirmar_clave" name="confirmar_clave" minlength="8">
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

<div class="modal fade modal-intesis" id="modalClave" tabindex="-1" aria-labelledby="modalClaveTitulo" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form class="modal-content" id="formularioClave" method="post" action="<?= $appUrl ?>/sistema/usuarios/restablecer-clave">
            <input type="hidden" name="asignacion_id" id="clave_asignacion_id">
            <div class="modal-header">
                <div>
                    <p class="modal-etiqueta">Seguridad</p>
                    <h2 class="modal-title" id="modalClaveTitulo">Restablecer clave</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body formulario-compacto">
                <p class="texto-ayuda-modal" id="clave_usuario_nombre"></p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label" for="clave_nueva">Nueva clave</label>
                        <input type="password" class="form-control form-control-sm" id="clave_nueva" name="clave" minlength="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="clave_confirmar">Confirmar clave</label>
                        <input type="password" class="form-control form-control-sm" id="clave_confirmar" name="confirmar_clave" minlength="8" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn btn-intesis">
                    <i class="bi bi-key"></i>
                    Restablecer
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
    window.INTESIS_ES_SUPERUSUARIO = <?= $esSuperusuario ? 'true' : 'false' ?>;
    window.INTESIS_ASIGNACIONES_USUARIOS = <?= json_encode($asignacionesUsuarios ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
