<?php
$claseCuerpo = 'login-intesis';
require $configuracion->raiz() . '/src/Vistas/plantillas/encabezado.php';
$appNombre = $configuracion->obtener('APP_NOMBRE', 'INTESIS');
$appUrl = rtrim($configuracion->obtener('APP_URL', ''), '/');
?>
<main class="login-contenedor">
    <section class="login-imagen" style="background-image: url('<?= $appUrl ?>/recursos/imagenes/login-dashboard-financiero.jpg')">
        <div class="login-imagen-capa"></div>
        <div class="login-imagen-contenido">
            <span class="login-etiqueta">Control de informacion</span>
            <h1><?= htmlspecialchars($appNombre) ?></h1>
            <p>Gestion operativa, financiera y administrativa con trazabilidad por empresa, modulo y licencia.</p>
            <div class="login-indicadores">
                <div>
                    <strong>6</strong>
                    <span>Modulos ERP</span>
                </div>
                <div>
                    <strong>30</strong>
                    <span>Dias demo</span>
                </div>
                <div>
                    <strong>24/7</strong>
                    <span>Control local</span>
                </div>
            </div>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-tarjeta">
            <div class="marca-login">
                <span class="marca-icono"><i class="bi bi-grid-1x2-fill"></i></span>
                <div>
                    <strong><?= htmlspecialchars($appNombre) ?></strong>
                    <small>ERP empresarial</small>
                </div>
            </div>

            <div class="login-titulo">
                <h2>Acceso al sistema</h2>
                <p>Ingrese con su usuario autorizado.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alerta-login" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($seleccionEmpresas) && !empty($usuarioPendiente)): ?>
                <form action="<?= $appUrl ?>/login/empresa" method="post" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label" for="empresa_id">Empresa asignada</label>
                        <div class="input-group input-intesis">
                            <span class="input-group-text"><i class="bi bi-buildings"></i></span>
                            <select class="form-control" id="empresa_id" name="empresa_id" required>
                                <option value="">Seleccione empresa</option>
                                <?php foreach ($seleccionEmpresas as $empresa): ?>
                                    <option value="<?= (int) $empresa['sis_empresa_id'] ?>">
                                        <?= htmlspecialchars($empresa['sis_empresa_nombre_comercial'] . ' - ' . $empresa['sis_perfil_nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-intesis w-100">
                        <i class="bi bi-building-check"></i>
                        Continuar
                    </button>
                </form>
            <?php else: ?>
            <form action="<?= $appUrl ?>/login" method="post" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label" for="correo">Correo electronico</label>
                    <div class="input-group input-intesis">
                        <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                        <input type="email" class="form-control" id="correo" name="correo" value="<?= htmlspecialchars($correoIngresado ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="clave">Contrasena</label>
                    <div class="input-group input-intesis">
                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                        <input type="password" class="form-control" id="clave" name="clave" required>
                        <button class="btn btn-outline-secondary btn-toggle-clave" type="button" tabindex="-1" title="Mostrar / ocultar contrasena">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-intesis w-100">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Iniciar sesion
                </button>
            </form>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require $configuracion->raiz() . '/src/Vistas/plantillas/pie.php'; ?>
