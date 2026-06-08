(() => {
    const obtenerPosicionSweetAlert = (posicion) => ({
        1: 'top',
        2: 'top-end',
        3: 'center-end',
        4: 'center',
        5: 'bottom-end',
    }[Number(posicion)] || 'center');

    const construirOpcionesSweetAlert = (mensaje, opciones = {}) => {
        const tiempo = Number(mensaje.tiempo ?? opciones.tiempo ?? 0);
        const configuracion = {
            icon: mensaje.icono || mensaje.tipo || opciones.icono || 'info',
            title: mensaje.titulo || opciones.titulo || 'Mensaje del sistema',
            text: mensaje.texto || opciones.texto || '',
            position: obtenerPosicionSweetAlert(mensaje.posicion ?? opciones.posicion ?? 4),
            confirmButtonText: opciones.confirmButtonText || 'Aceptar',
            confirmButtonColor: opciones.confirmButtonColor || '#1f6f68',
            ...opciones.extra,
        };

        if (tiempo > 0 && !opciones.omitirTimer) {
            configuracion.timer = tiempo;
            configuracion.timerProgressBar = true;
            configuracion.showConfirmButton = false;
        }

        return configuracion;
    };

    const mostrarAlerta = (mensaje, opciones = {}) => Swal.fire(construirOpcionesSweetAlert(mensaje, opciones));

    const obtenerMensaje = (codigo, defecto = {}) => {
        const mensajes = window.INTESIS_MENSAJES || {};
        if (mensajes[codigo]) {
            return mensajes[codigo];
        }

        return {
            icono: defecto.icono || 'warning',
            titulo: defecto.titulo || 'Mensaje no configurado',
            texto: defecto.texto || `Codigo pendiente de configurar: ${codigo}`,
            tiempo: defecto.tiempo || 0,
            posicion: defecto.posicion || 4,
        };
    };

    const mostrarMensaje = (codigo, defecto = {}) => {
        const mensaje = obtenerMensaje(codigo, defecto);
        return mostrarAlerta(mensaje);
    };

    const validarCedulaEcuador = (cedula) => {
        if (!/^\d{10}$/.test(cedula)) return false;
        const provincia = Number(cedula.slice(0, 2));
        const tercerDigito = Number(cedula[2]);
        if (provincia < 1 || provincia > 24 || tercerDigito > 5) return false;

        let suma = 0;
        for (let i = 0; i < 9; i += 1) {
            let valor = Number(cedula[i]);
            if (i % 2 === 0) {
                valor *= 2;
                if (valor > 9) valor -= 9;
            }
            suma += valor;
        }

        const verificador = suma % 10 === 0 ? 0 : 10 - (suma % 10);
        return verificador === Number(cedula[9]);
    };

    const validarModuloOnce = (base, verificador, coeficientes) => {
        const suma = coeficientes.reduce((total, coeficiente, indice) => total + Number(base[indice]) * coeficiente, 0);
        const residuo = suma % 11;
        const resultado = residuo === 0 ? 0 : 11 - residuo;
        return resultado === Number(verificador);
    };

    const validarRucEcuador = (ruc) => {
        if (!/^\d{13}$/.test(ruc) || ruc.slice(10, 13) !== '001') return false;
        const tercerDigito = Number(ruc[2]);
        if (tercerDigito <= 5) return validarCedulaEcuador(ruc.slice(0, 10));
        if (tercerDigito === 6) return validarModuloOnce(ruc.slice(0, 8), ruc[8], [3, 2, 7, 6, 5, 4, 3, 2]);
        if (tercerDigito === 9) return validarModuloOnce(ruc.slice(0, 9), ruc[9], [4, 3, 2, 7, 6, 5, 4, 3, 2]);
        return false;
    };

    const mensaje = window.INTESIS_MENSAJE;
    if (mensaje && window.Swal) {
        mostrarAlerta(mensaje);
    }

    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('.tabla-intesis').DataTable({
            language: {
                search: 'Filtrar:',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Sin registros disponibles',
                infoFiltered: '(filtrado de _MAX_ registros)',
                zeroRecords: 'No se encontraron resultados',
                paginate: {
                    first: 'Primero',
                    last: 'Ultimo',
                    next: 'Siguiente',
                    previous: 'Anterior',
                },
            },
            pageLength: 10,
            order: [],
        });
    }

    document.querySelectorAll('.formulario-confirmar').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            if (!window.Swal || formulario.dataset.confirmado === '1') {
                return;
            }

            evento.preventDefault();
            const mensajeConfirmacion = formulario.dataset.codigoMensaje
                ? obtenerMensaje(formulario.dataset.codigoMensaje)
                : {
                    titulo: formulario.dataset.titulo || 'Confirmar accion',
                    texto: formulario.dataset.texto || 'Esta accion cambiara el estado del registro.',
                    icono: 'warning',
                };

            mostrarAlerta(mensajeConfirmacion, {
                omitirTimer: true,
                extra: {
                    showCancelButton: true,
                    showConfirmButton: true,
                    cancelButtonText: 'Cancelar',
                    cancelButtonColor: '#263a5f',
                },
                confirmButtonText: 'Confirmar',
                confirmButtonColor: '#d65f5f',
            }).then((resultado) => {
                if (resultado.isConfirmed) {
                    formulario.dataset.confirmado = '1';
                    formulario.submit();
                }
            });
        });
    });

    const formularioEmpresa = document.getElementById('formularioEmpresa');
    if (formularioEmpresa) {
        formularioEmpresa.addEventListener('submit', (evento) => {
            const ruc = document.getElementById('ruc').value.replace(/\D+/g, '');
            const razon = document.getElementById('razon_social').value.trim();
            const comercial = document.getElementById('nombre_comercial').value.trim();
            const direccion = document.getElementById('direccion').value.trim();
            const email = document.getElementById('email').value.trim();

            if (!validarRucEcuador(ruc)) {
                evento.preventDefault();
                mostrarMensaje('EMPRESA_RUC_INVALIDO');
                return;
            }

            if (!razon || !comercial || !direccion) {
                evento.preventDefault();
                mostrarMensaje('EMPRESA_DATOS_OBLIGATORIOS');
                return;
            }

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                evento.preventDefault();
                mostrarMensaje('EMPRESA_EMAIL_INVALIDO');
            }
        });
    }

    const modalEmpresa = document.getElementById('modalEmpresa');
    if (modalEmpresa) {
        modalEmpresa.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioEmpresa');
            const titulo = document.getElementById('modalEmpresaTitulo');

            formulario.reset();
            document.getElementById('empresa_id').value = '';

            if (modo === 'editar') {
                titulo.textContent = 'Editar empresa';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/empresas/editar`;
                document.getElementById('empresa_id').value = boton.dataset.id || '';
                document.getElementById('ruc').value = boton.dataset.ruc || '';
                document.getElementById('razon_social').value = boton.dataset.razon || '';
                document.getElementById('nombre_comercial').value = boton.dataset.comercial || '';
                document.getElementById('direccion').value = boton.dataset.direccion || '';
                document.getElementById('telefono').value = boton.dataset.telefono || '';
                document.getElementById('email').value = boton.dataset.email || '';
                document.getElementById('contribuyente_especial').checked = boton.dataset.especial === '1';
                document.getElementById('obligado_contabilidad').checked = boton.dataset.obligado === '1';
            } else {
                titulo.textContent = 'Nueva empresa';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/empresas/crear`;
            }
        });
    }

    const filtrarPerfilesUsuario = () => {
        const empresa = document.getElementById('usuario_empresa_id');
        const perfil = document.getElementById('usuario_perfil_id');
        if (!empresa || !perfil) {
            return;
        }

        const empresaId = empresa.value;
        Array.from(perfil.options).forEach((opcion) => {
            if (!opcion.value) {
                opcion.hidden = false;
                return;
            }
            opcion.hidden = opcion.dataset.empresa !== empresaId;
        });

        if (perfil.selectedOptions[0]?.hidden) {
            perfil.value = '';
        }
    };

    document.getElementById('usuario_empresa_id')?.addEventListener('change', filtrarPerfilesUsuario);

    const filtrarPerfilAsignacion = (fila) => {
        const empresa = fila.querySelector('.asignacion-empresa');
        const perfil = fila.querySelector('.asignacion-perfil');
        if (!empresa || !perfil) {
            return;
        }

        Array.from(perfil.options).forEach((opcion) => {
            if (!opcion.value) {
                opcion.hidden = false;
                return;
            }
            opcion.hidden = opcion.dataset.empresa !== empresa.value;
        });

        if (perfil.selectedOptions[0]?.hidden) {
            perfil.value = '';
        }
    };

    const prepararAsignacionesMultiples = () => {
        const contenedor = document.getElementById('asignacionesUsuario');
        if (!contenedor) {
            return;
        }

        contenedor.querySelectorAll('.asignacion-usuario-fila').forEach((fila, indice) => {
            fila.dataset.indice = String(indice);
            const empresa = fila.querySelector('.asignacion-empresa');
            const perfil = fila.querySelector('.asignacion-perfil');
            const asignacionId = fila.querySelector('.asignacion-id');
            const predeterminada = fila.querySelector('.asignacion-predeterminada');
            const inactivar = fila.querySelector('.asignacion-inactivar');
            if (empresa) {
                empresa.id = `asignacion_empresa_${indice}`;
                empresa.name = `asignaciones[${indice}][empresa_id]`;
                empresa.onchange = () => filtrarPerfilAsignacion(fila);
            }
            if (perfil) {
                perfil.id = `asignacion_perfil_${indice}`;
                perfil.name = `asignaciones[${indice}][perfil_id]`;
            }
            if (asignacionId) {
                asignacionId.name = `asignaciones[${indice}][asignacion_id]`;
            }
            if (predeterminada) {
                predeterminada.name = `asignaciones[${indice}][predeterminada]`;
            }
            if (inactivar) {
                inactivar.name = `asignaciones[${indice}][inactivar]`;
            }
            fila.querySelector('.quitar-asignacion')?.classList.toggle('d-none', indice === 0 && !asignacionId?.value);
            filtrarPerfilAsignacion(fila);
        });
    };

    const reiniciarAsignacionesMultiples = () => {
        const contenedor = document.getElementById('asignacionesUsuario');
        if (!contenedor) {
            return;
        }

        contenedor.querySelectorAll('.asignacion-usuario-fila').forEach((fila, indice) => {
            if (indice > 0) {
                fila.remove();
                return;
            }

            fila.querySelectorAll('select').forEach((select) => {
                select.value = '';
            });
            fila.querySelectorAll('input').forEach((input) => {
                if (input.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });
            fila.classList.remove('asignacion-inactiva');
        });
        prepararAsignacionesMultiples();
    };

    const agregarFilaAsignacion = (datos = {}) => {
        const contenedor = document.getElementById('asignacionesUsuario');
        const filaBase = contenedor?.querySelector('.asignacion-usuario-fila');
        if (!contenedor || !filaBase) {
            return null;
        }

        const nuevaFila = filaBase.cloneNode(true);
        nuevaFila.querySelectorAll('select').forEach((select) => {
            select.value = '';
            select.disabled = false;
        });
        nuevaFila.querySelectorAll('input').forEach((input) => {
            input.disabled = false;
            if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        nuevaFila.classList.remove('asignacion-inactiva');
        contenedor.appendChild(nuevaFila);
        if (datos.asignacion_id) nuevaFila.querySelector('.asignacion-id').value = datos.asignacion_id;
        if (datos.empresa_id) nuevaFila.querySelector('.asignacion-empresa').value = datos.empresa_id;
        filtrarPerfilAsignacion(nuevaFila);
        if (datos.perfil_id) nuevaFila.querySelector('.asignacion-perfil').value = datos.perfil_id;
        nuevaFila.querySelector('.asignacion-predeterminada').checked = Boolean(datos.predeterminada);
        nuevaFila.querySelector('.asignacion-inactivar').checked = datos.estado === 'INACTIVO';
        nuevaFila.classList.toggle('asignacion-inactiva', datos.estado === 'INACTIVO');
        prepararAsignacionesMultiples();
        return nuevaFila;
    };

    const cargarAsignacionesEdicionUsuario = (usuarioId) => {
        const contenedor = document.getElementById('asignacionesUsuario');
        if (!contenedor) return;
        const asignaciones = window.INTESIS_ASIGNACIONES_USUARIOS?.[usuarioId] || [];
        reiniciarAsignacionesMultiples();
        asignaciones.forEach((asignacion, indice) => {
            const fila = indice === 0
                ? contenedor.querySelector('.asignacion-usuario-fila')
                : agregarFilaAsignacion();
            if (!fila) return;
            const datos = {
                asignacion_id: asignacion.sis_usuario_empresa_id,
                empresa_id: asignacion.sis_empresa_id,
                perfil_id: asignacion.sis_perfil_id,
                predeterminada: asignacion.sis_usuario_empresa_predeterminada,
                estado: asignacion.sis_estado_codigo,
            };
            fila.querySelector('.asignacion-id').value = datos.asignacion_id || '';
            fila.querySelector('.asignacion-empresa').value = datos.empresa_id || '';
            filtrarPerfilAsignacion(fila);
            fila.querySelector('.asignacion-perfil').value = datos.perfil_id || '';
            fila.querySelector('.asignacion-predeterminada').checked = Boolean(datos.predeterminada);
            fila.querySelector('.asignacion-inactivar').checked = datos.estado === 'INACTIVO';
            fila.classList.toggle('asignacion-inactiva', datos.estado === 'INACTIVO');
        });
        if (!asignaciones.length) {
            reiniciarAsignacionesMultiples();
        }
        prepararAsignacionesMultiples();
    };

    document.getElementById('agregarAsignacionUsuario')?.addEventListener('click', () => {
        agregarFilaAsignacion();
    });

    document.getElementById('asignacionesUsuario')?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.quitar-asignacion');
        if (!boton) {
            return;
        }

        const fila = boton.closest('.asignacion-usuario-fila');
        if (fila?.querySelector('.asignacion-id')?.value) {
            const inactivar = fila.querySelector('.asignacion-inactivar');
            if (inactivar) inactivar.checked = true;
            fila.classList.add('asignacion-inactiva');
        } else {
            fila?.remove();
        }
        prepararAsignacionesMultiples();
    });

    const modalUsuario = document.getElementById('modalUsuario');
    if (modalUsuario) {
        modalUsuario.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioUsuario');
            const titulo = document.getElementById('modalUsuarioTitulo');
            const camposClave = document.querySelectorAll('.campos-clave-crear input');
            const asignacionSimple = document.querySelectorAll('.asignacion-simple');
            const asignacionesMultiples = document.querySelector('.asignaciones-multiples');

            formulario.reset();
            document.getElementById('usuario_asignacion_id').value = '';

            if (modo === 'editar') {
                titulo.textContent = 'Editar usuario';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/usuarios/editar`;
                document.getElementById('usuario_asignacion_id').value = boton.dataset.asignacion || '';
                if (window.INTESIS_ES_SUPERUSUARIO && asignacionesMultiples) {
                    asignacionSimple.forEach((campo) => campo.classList.add('d-none'));
                    asignacionesMultiples.classList.remove('d-none');
                    document.getElementById('usuario_empresa_id').required = false;
                    document.getElementById('usuario_perfil_id').required = false;
                    cargarAsignacionesEdicionUsuario(boton.dataset.usuario || '');
                } else {
                    asignacionSimple.forEach((campo) => campo.classList.remove('d-none'));
                    asignacionesMultiples?.classList.add('d-none');
                    document.getElementById('usuario_empresa_id').required = true;
                    document.getElementById('usuario_perfil_id').required = true;
                    document.getElementById('usuario_empresa_id').value = boton.dataset.empresa || '';
                    filtrarPerfilesUsuario();
                    document.getElementById('usuario_perfil_id').value = boton.dataset.perfil || '';
                }
                document.getElementById('usuario_nombre').value = boton.dataset.nombre || '';
                document.getElementById('usuario_correo').value = boton.dataset.correo || '';
                camposClave.forEach((campo) => {
                    campo.closest('.campos-clave-crear').classList.add('d-none');
                    campo.required = false;
                });
            } else {
                titulo.textContent = 'Nuevo usuario';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/usuarios/crear`;
                if (window.INTESIS_ES_SUPERUSUARIO && asignacionesMultiples) {
                    asignacionSimple.forEach((campo) => campo.classList.add('d-none'));
                    asignacionesMultiples.classList.remove('d-none');
                    document.getElementById('usuario_empresa_id').required = false;
                    document.getElementById('usuario_perfil_id').required = false;
                    reiniciarAsignacionesMultiples();
                } else {
                    asignacionSimple.forEach((campo) => campo.classList.remove('d-none'));
                    asignacionesMultiples?.classList.add('d-none');
                    document.getElementById('usuario_empresa_id').required = true;
                    document.getElementById('usuario_perfil_id').required = true;
                    filtrarPerfilesUsuario();
                }
                camposClave.forEach((campo) => {
                    campo.closest('.campos-clave-crear').classList.remove('d-none');
                    campo.required = true;
                });
            }
        });
    }

    const modalClave = document.getElementById('modalClave');
    if (modalClave) {
        modalClave.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            document.getElementById('formularioClave').reset();
            document.getElementById('clave_asignacion_id').value = boton?.dataset.asignacion || '';
            document.getElementById('clave_usuario_nombre').textContent = boton?.dataset.nombre
                ? `Usuario: ${boton.dataset.nombre}`
                : '';
        });
    }

    const formularioUsuario = document.getElementById('formularioUsuario');
    if (formularioUsuario) {
        formularioUsuario.addEventListener('submit', (evento) => {
            const empresa = document.getElementById('usuario_empresa_id').value;
            const perfil = document.getElementById('usuario_perfil_id').value;
            const nombre = document.getElementById('usuario_nombre').value.trim();
            const correo = document.getElementById('usuario_correo').value.trim();
            const claveVisible = !document.querySelector('.campos-clave-crear')?.classList.contains('d-none');
            const asignacionMultipleVisible = !document.querySelector('.asignaciones-multiples')?.classList.contains('d-none');
            const clave = document.getElementById('usuario_clave').value;
            const confirmarClave = document.getElementById('usuario_confirmar_clave').value;

            let asignacionesValidas = Boolean(empresa && perfil);
            if (asignacionMultipleVisible) {
                const empresasAsignadas = new Set();
                asignacionesValidas = true;
                document.querySelectorAll('.asignacion-usuario-fila').forEach((fila) => {
                    const empresaFila = fila.querySelector('.asignacion-empresa')?.value || '';
                    const perfilFila = fila.querySelector('.asignacion-perfil')?.value || '';
                    const inactiva = fila.querySelector('.asignacion-inactivar')?.checked || false;
                    if (!empresaFila || !perfilFila || (!inactiva && empresasAsignadas.has(empresaFila))) {
                        asignacionesValidas = false;
                    }
                    if (!inactiva) {
                        empresasAsignadas.add(empresaFila);
                    }
                });
                asignacionesValidas = asignacionesValidas && empresasAsignadas.size > 0;
            }

            if (!asignacionesValidas || !nombre || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_DATOS_OBLIGATORIOS');
                return;
            }

            if (claveVisible && (clave.length < 8 || clave !== confirmarClave)) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_CLAVE_INVALIDA');
            }
        });
    }

    const formularioClave = document.getElementById('formularioClave');
    if (formularioClave) {
        formularioClave.addEventListener('submit', (evento) => {
            const clave = document.getElementById('clave_nueva').value;
            const confirmarClave = document.getElementById('clave_confirmar').value;
            if (clave.length < 8 || clave !== confirmarClave) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_CLAVE_INVALIDA');
            }
        });
    }

    const modalPerfil = document.getElementById('modalPerfil');
    if (modalPerfil) {
        modalPerfil.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioPerfil');
            const titulo = document.getElementById('modalPerfilTitulo');

            formulario.reset();
            document.getElementById('perfil_id').value = '';
            if (modo === 'editar') {
                titulo.textContent = 'Editar perfil';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/perfiles/editar`;
                document.getElementById('perfil_id').value = boton.dataset.id || '';
                document.getElementById('perfil_empresa_id').value = boton.dataset.empresa || '';
                document.getElementById('perfil_nombre').value = boton.dataset.nombre || '';
            } else {
                titulo.textContent = 'Nuevo perfil';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/perfiles/crear`;
            }
        });
    }

    const formularioPerfil = document.getElementById('formularioPerfil');
    if (formularioPerfil) {
        formularioPerfil.addEventListener('submit', (evento) => {
            if (!document.getElementById('perfil_empresa_id').value || !document.getElementById('perfil_nombre').value.trim()) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_DATOS_OBLIGATORIOS', {
                    titulo: 'Datos incompletos',
                    texto: 'Seleccione empresa e ingrese el nombre del perfil.',
                    icono: 'error',
                });
            }
        });
    }

    const modalPermisosPerfil = document.getElementById('modalPermisosPerfil');
    if (modalPermisosPerfil) {
        let menuSeleccionadoPermisos = '';

        const esDescendientePermiso = (nodo, menuId) => {
            let padreId = nodo?.dataset.padre || '0';
            while (padreId !== '0') {
                if (padreId === menuId) {
                    return true;
                }
                const padre = document.querySelector(`.permiso-menu[data-menu-id="${padreId}"]`);
                padreId = padre?.dataset.padre || '0';
            }
            return false;
        };

        const actualizarDetallePermisos = () => {
            const total = document.querySelectorAll('#formularioPermisosPerfil input[type="checkbox"]:checked').length;
            document.querySelectorAll('.acciones-permiso-bloque').forEach((bloque) => {
                const visible = menuSeleccionadoPermisos
                    && (bloque.dataset.menuId === menuSeleccionadoPermisos || esDescendientePermiso(bloque, menuSeleccionadoPermisos));
                bloque.classList.toggle('d-none', !visible);
            });

            const visibles = document.querySelectorAll('.acciones-permiso-bloque:not(.d-none)').length;
            document.getElementById('permisosVacioPerfil')?.classList.toggle('d-none', visibles > 0);

            const vacio = document.getElementById('permisosVacioPerfil');
            if (vacio && visibles === 0 && menuSeleccionadoPermisos) {
                vacio.innerHTML = `<strong>${total} permisos seleccionados</strong><span>El menu seleccionado no tiene acciones directas.</span>`;
            }
        };

        const actualizarAcordeonPermisos = (menuPrincipal, desplegado) => {
            const menuId = menuPrincipal.dataset.menuId || '';
            menuPrincipal.classList.toggle('menu-colapsado', !desplegado);
            menuPrincipal.classList.toggle('menu-desplegado', desplegado);
            menuPrincipal.querySelector('.btn-menu-acordeon i')?.classList.toggle('bi-chevron-down', desplegado);
            menuPrincipal.querySelector('.btn-menu-acordeon i')?.classList.toggle('bi-chevron-right', !desplegado);

            document.querySelectorAll(`.permiso-menu[data-padre="${menuId}"]`).forEach((hijo) => {
                hijo.classList.toggle('d-none', !desplegado);
                if (!desplegado) {
                    actualizarAcordeonPermisos(hijo, false);
                }
            });
        };

        const marcarPadresPermiso = (padreId) => {
            while (padreId !== '0') {
                const padre = document.querySelector(`.permiso-menu[data-menu-id="${padreId}"] input`);
                if (!padre) break;
                padre.checked = true;
                padreId = padre.closest('.permiso-menu')?.dataset.padre || '0';
            }
        };

        const marcarAccionVer = (menuId) => {
            const accionVer = Array.from(document.querySelectorAll(`.permiso-accion[data-padre="${menuId}"] input`))
                .find((checkbox) => checkbox.closest('.permiso-accion')?.textContent.trim().toLowerCase().startsWith('ver '));
            if (accionVer) {
                accionVer.checked = true;
            }
        };

        const desmarcarAccionesMenu = (menuId) => {
            document.querySelectorAll(`.permiso-accion[data-padre="${menuId}"] input`).forEach((accion) => {
                accion.checked = false;
            });
            document.querySelectorAll(`.permiso-menu[data-padre="${menuId}"]`).forEach((hijo) => {
                hijo.querySelector('input').checked = false;
                desmarcarAccionesMenu(hijo.dataset.menuId);
            });
        };

        modalPermisosPerfil.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const perfilId = boton?.dataset.id || '';
            const permisos = window.INTESIS_PERMISOS_PERFIL?.[perfilId] || [];
            document.getElementById('permisos_perfil_id').value = perfilId;
            document.getElementById('modalPermisosPerfilTitulo').textContent = `Permisos ${boton?.dataset.nombre || ''}`;

            document.querySelectorAll('#formularioPermisosPerfil input[type="checkbox"]').forEach((checkbox) => {
                checkbox.checked = permisos.includes(Number(checkbox.value));
            });
            document.querySelectorAll('.permiso-menu[data-padre="0"]').forEach((menuPrincipal) => {
                actualizarAcordeonPermisos(menuPrincipal, true);
            });
            menuSeleccionadoPermisos = '';
            document.querySelectorAll('.permiso-menu').forEach((nodo) => {
                nodo.classList.toggle('activo', nodo.dataset.menuId === menuSeleccionadoPermisos);
            });
            actualizarDetallePermisos();
        });

        document.querySelectorAll('.permiso-menu input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const nodo = checkbox.closest('.permiso-menu');
                const menuId = nodo?.dataset.menuId || '';
                menuSeleccionadoPermisos = menuId;
                document.querySelectorAll('.permiso-menu').forEach((item) => item.classList.toggle('activo', item === nodo));
                if (checkbox.checked) {
                    marcarPadresPermiso(nodo.dataset.padre || '0');
                    marcarAccionVer(menuId);
                } else {
                    desmarcarAccionesMenu(menuId);
                    document.querySelectorAll(`.permiso-menu[data-padre="${menuId}"] input`).forEach((hijo) => {
                        hijo.checked = false;
                    });
                }
                actualizarDetallePermisos();
            });
        });

        document.querySelectorAll('.btn-cargar-acciones').forEach((boton) => {
            boton.addEventListener('click', () => {
                const nodo = boton.closest('.permiso-menu');
                menuSeleccionadoPermisos = nodo.dataset.menuId || '';
                document.querySelectorAll('.permiso-menu').forEach((item) => item.classList.toggle('activo', item === nodo));
                actualizarDetallePermisos();
            });
        });

        document.querySelectorAll('.btn-menu-acordeon').forEach((boton) => {
            boton.addEventListener('click', () => {
                const nodo = boton.closest('.permiso-menu');
                const desplegado = !nodo.classList.contains('menu-desplegado');
                actualizarAcordeonPermisos(nodo, desplegado);
            });
        });

        document.querySelectorAll('.permiso-accion input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const padreId = checkbox.closest('.permiso-accion')?.dataset.padre || '0';
                if (checkbox.checked && padreId !== '0') {
                    const menuPadre = document.querySelector(`.permiso-menu[data-menu-id="${padreId}"] input`);
                    if (menuPadre) {
                        menuPadre.checked = true;
                        marcarPadresPermiso(menuPadre.closest('.permiso-menu')?.dataset.padre || '0');
                    }
                }
                actualizarDetallePermisos();
            });
        });

        document.querySelectorAll('.marcar-todas-acciones').forEach((checkboxTodas) => {
            checkboxTodas.addEventListener('change', () => {
                const bloque = checkboxTodas.closest('.acciones-permiso-bloque');
                const menuId = bloque?.dataset.menuId || '0';
                bloque?.querySelectorAll('.permiso-accion input[type="checkbox"]').forEach((accion) => {
                    accion.checked = checkboxTodas.checked;
                });

                if (checkboxTodas.checked && menuId !== '0') {
                    const menuPadre = document.querySelector(`.permiso-menu[data-menu-id="${menuId}"] input`);
                    if (menuPadre) {
                        menuPadre.checked = true;
                        marcarPadresPermiso(menuPadre.closest('.permiso-menu')?.dataset.padre || '0');
                    }
                }
                actualizarDetallePermisos();
            });
        });
    }

    const modalMenu = document.getElementById('modalMenu');
    if (modalMenu) {
        const mostrarAccionesMenuAdmin = (menuId) => {
            document.querySelectorAll('.acciones-menu-admin-bloque').forEach((bloque) => {
                bloque.classList.toggle('d-none', bloque.dataset.menuId !== menuId);
            });
            document.getElementById('menusAdminVacio')?.classList.toggle('d-none', Boolean(menuId));
            document.querySelectorAll('.menu-admin-nodo').forEach((nodo) => {
                nodo.classList.toggle('activo', nodo.dataset.menuId === menuId);
            });
        };

        const actualizarAcordeonMenuAdmin = (nodoPrincipal, desplegado) => {
            const menuId = nodoPrincipal.dataset.menuId || '';
            nodoPrincipal.classList.toggle('menu-admin-colapsado', !desplegado);
            nodoPrincipal.classList.toggle('menu-admin-desplegado', desplegado);
            nodoPrincipal.querySelector('.btn-menu-admin-acordeon i')?.classList.toggle('bi-chevron-down', desplegado);
            nodoPrincipal.querySelector('.btn-menu-admin-acordeon i')?.classList.toggle('bi-chevron-right', !desplegado);

            document.querySelectorAll(`.menu-admin-nodo[data-padre="${menuId}"]`).forEach((hijo) => {
                hijo.classList.toggle('d-none', !desplegado);
                if (!desplegado) {
                    actualizarAcordeonMenuAdmin(hijo, false);
                }
            });
        };

        document.querySelectorAll('.btn-cargar-menu-admin').forEach((boton) => {
            boton.addEventListener('click', () => {
                mostrarAccionesMenuAdmin(boton.closest('.menu-admin-nodo')?.dataset.menuId || '');
            });
        });

        document.querySelectorAll('.btn-menu-admin-acordeon').forEach((boton) => {
            boton.addEventListener('click', () => {
                const nodo = boton.closest('.menu-admin-nodo');
                actualizarAcordeonMenuAdmin(nodo, !nodo.classList.contains('menu-admin-desplegado'));
            });
        });

        const actualizarVistaIconoMenu = () => {
            const icono = document.getElementById('menu_icono')?.value.trim() || 'bi bi-circle';
            const vista = document.getElementById('menu_icono_vista');
            if (vista) {
                vista.className = icono;
            }
        };

        const actualizarTipoMenu = () => {
            const tipo = document.getElementById('menu_tipo')?.value || 'M';
            const padre = document.getElementById('menu_padre');
            const crearVer = document.getElementById('contenedorCrearVer');
            const estado = document.getElementById('contenedorMenuEstado');
            const esHijo = Boolean(padre.value);
            if (tipo === 'B') {
                padre.required = true;
                crearVer?.classList.add('d-none');
            } else {
                padre.required = false;
                crearVer?.classList.remove('d-none');
            }
            estado?.classList.toggle('d-none', !esHijo);
        };

        document.getElementById('menu_icono')?.addEventListener('input', actualizarVistaIconoMenu);
        document.getElementById('menu_tipo')?.addEventListener('change', actualizarTipoMenu);
        document.getElementById('menu_padre')?.addEventListener('change', actualizarTipoMenu);

        modalMenu.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioMenu');
            const titulo = document.getElementById('modalMenuTitulo');

            formulario.reset();
            document.getElementById('menu_id').value = '';
            document.getElementById('menu_icono').value = 'bi bi-circle';
            document.getElementById('menu_orden').value = '1';
            document.getElementById('menu_crear_ver').checked = true;
            document.getElementById('menu_estado').value = '1';

            if (modo === 'editar') {
                titulo.textContent = 'Editar menu';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/menus/editar`;
                document.getElementById('menu_id').value = boton.dataset.id || '';
                document.getElementById('menu_nombre').value = boton.dataset.nombre || '';
                document.getElementById('menu_padre').value = boton.dataset.padre === '0' ? '' : (boton.dataset.padre || '');
                document.getElementById('menu_tipo').value = boton.dataset.tipo || 'M';
                document.getElementById('menu_url').value = boton.dataset.url || '';
                document.getElementById('menu_icono').value = boton.dataset.icono || 'bi bi-circle';
                document.getElementById('menu_orden').value = boton.dataset.orden || '1';
                document.getElementById('menu_estado').value = boton.dataset.estado || '1';
                document.getElementById('contenedorCrearVer')?.classList.add('d-none');
            } else {
                titulo.textContent = 'Nuevo menu';
                formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/menus/crear`;
                document.getElementById('menu_tipo').value = boton?.dataset.tipo || 'M';
                document.getElementById('menu_padre').value = boton?.dataset.padre || '';
                if (boton?.dataset.tipo === 'B') {
                    titulo.textContent = 'Nueva accion';
                    document.getElementById('menu_icono').value = 'bi bi-dot';
                }
            }

            actualizarTipoMenu();
            if (modo === 'editar') {
                document.getElementById('contenedorCrearVer')?.classList.add('d-none');
            }
            actualizarVistaIconoMenu();
        });
    }

    const formularioMenu = document.getElementById('formularioMenu');
    if (formularioMenu) {
        formularioMenu.addEventListener('submit', (evento) => {
            const nombre = document.getElementById('menu_nombre').value.trim();
            const tipo = document.getElementById('menu_tipo').value;
            const padre = document.getElementById('menu_padre').value;
            const url = document.getElementById('menu_url').value.trim();
            const icono = document.getElementById('menu_icono').value.trim();
            if (!nombre || !tipo || !url || !icono || (tipo === 'B' && !padre)) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_DATOS_OBLIGATORIOS', {
                    titulo: 'Datos incompletos',
                    texto: 'Ingrese nombre, tipo, URL, icono y padre cuando sea una accion.',
                    icono: 'error',
                });
            }
        });
    }

    const modalBodega = document.getElementById('modalBodega');
    if (modalBodega) {
        modalBodega.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioBodega');
            formulario.reset();
            document.getElementById('bodega_id').value = '';
            document.getElementById('modalBodegaTitulo').textContent = modo === 'editar' ? 'Editar bodega' : 'Nueva bodega';
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/bodegas/${modo === 'editar' ? 'editar' : 'crear'}`;
            if (modo === 'editar') {
                document.getElementById('bodega_id').value = boton.dataset.id || '';
                const empresa = document.getElementById('bodega_empresa_id');
                if (empresa) {
                    empresa.value = boton.dataset.empresa || '';
                }
                document.getElementById('bodega_codigo').value = boton.dataset.codigo || '';
                document.getElementById('bodega_nombre').value = boton.dataset.nombre || '';
                document.getElementById('bodega_descripcion').value = boton.dataset.descripcion || '';
                document.getElementById('bodega_direccion').value = boton.dataset.direccion || '';
                document.getElementById('bodega_principal').checked = boton.dataset.principal === '1';
                document.getElementById('bodega_virtual').checked = boton.dataset.virtual === '1';
            }
        });
    }

    const formularioBodega = document.getElementById('formularioBodega');
    if (formularioBodega) {
        formularioBodega.addEventListener('submit', (evento) => {
            const empresa = document.getElementById('bodega_empresa_id')?.value || '1';
            const codigo = document.getElementById('bodega_codigo').value.trim();
            const nombre = document.getElementById('bodega_nombre').value.trim();
            const principal = document.getElementById('bodega_principal').checked;
            const virtual = document.getElementById('bodega_virtual').checked;
            if (!empresa || !codigo || !nombre || (principal && virtual)) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_DATOS_OBLIGATORIOS', {
                    titulo: 'Datos incompletos',
                    texto: principal && virtual ? 'Una bodega virtual no puede ser principal.' : 'Ingrese empresa, codigo y nombre.',
                    icono: 'error',
                });
            }
        });
    }

    const obtenerEmpresaProducto = () => document.getElementById('producto_empresa_id')?.value || String(window.INTESIS_EMPRESA_ACTIVA || '');

    const filtrarSelectsProductoPorEmpresa = () => {
        const empresaId = obtenerEmpresaProducto();
        document.querySelectorAll('.producto-select-empresa').forEach((select) => {
            Array.from(select.options).forEach((opcion) => {
                if (!opcion.value) {
                    opcion.hidden = false;
                    return;
                }
                opcion.hidden = empresaId && opcion.dataset.empresa !== empresaId;
            });
            if (select.selectedOptions[0]?.hidden) {
                select.value = '';
            }
        });
        const categoriaRapidaEmpresa = document.getElementById('categoria_rapida_empresa_id');
        const marcaRapidaEmpresa = document.getElementById('marca_rapida_empresa_id');
        if (categoriaRapidaEmpresa) categoriaRapidaEmpresa.value = empresaId;
        if (marcaRapidaEmpresa) marcaRapidaEmpresa.value = empresaId;
    };

    document.getElementById('producto_empresa_id')?.addEventListener('change', filtrarSelectsProductoPorEmpresa);

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-bs-target="#modalCategoriaRapida"], [data-bs-target="#modalMarcaRapida"]');
        const modalProductoAbierto = document.getElementById('modalProducto')?.classList.contains('show');
        if (!boton || !modalProductoAbierto) {
            return;
        }

        evento.preventDefault();
        evento.stopPropagation();
        evento.stopImmediatePropagation();
        filtrarSelectsProductoPorEmpresa();

        const modalHijo = document.querySelector(boton.dataset.bsTarget || '');
        const formularioHijo = modalHijo?.querySelector('form');
        formularioHijo?.reset();
        filtrarSelectsProductoPorEmpresa();

        if (modalHijo) {
            bootstrap.Modal.getOrCreateInstance(modalHijo).show();
        }
    }, true);

    const modalProducto = document.getElementById('modalProducto');
    if (modalProducto) {
        modalProducto.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioProducto');
            formulario.reset();
            document.getElementById('producto_id').value = '';
            document.getElementById('modalProductoTitulo').textContent = modo === 'ver' ? 'Ver producto' : (modo === 'editar' ? 'Editar producto' : 'Nuevo producto');
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/productos/${modo === 'editar' ? 'editar' : 'crear'}`;
            document.getElementById('producto_lleva_iva').checked = true;
            ['producto_costo_ultimo', 'producto_stock_minimo', 'producto_stock_maximo'].forEach((id) => {
                document.getElementById(id).value = '0';
            });
            if (modo !== 'crear') {
                document.getElementById('producto_id').value = boton.dataset.id || '';
                const empresa = document.getElementById('producto_empresa_id');
                if (empresa) empresa.value = boton.dataset.empresa || '';
                document.getElementById('producto_codigo_principal').value = boton.dataset.codigo || '';
                document.getElementById('producto_codigo_auxiliar').value = boton.dataset.auxiliar || '';
                document.getElementById('producto_nombre').value = boton.dataset.nombre || '';
                document.getElementById('producto_descripcion').value = boton.dataset.descripcion || '';
                document.getElementById('producto_costo_ultimo').value = boton.dataset.costo || '0';
                document.getElementById('producto_stock_minimo').value = boton.dataset.minimo || '0';
                document.getElementById('producto_stock_maximo').value = boton.dataset.maximo || '0';
                document.getElementById('producto_lleva_iva').checked = boton.dataset.iva === '1';
                filtrarSelectsProductoPorEmpresa();
                document.getElementById('producto_categoria_id').value = boton.dataset.categoria || '';
                document.getElementById('producto_marca_id').value = boton.dataset.marca || '';
                habilitarGaleriaProducto(boton.dataset.id || '', boton.dataset.empresa || '');
            } else {
                filtrarSelectsProductoPorEmpresa();
                habilitarGaleriaProducto('', obtenerEmpresaProducto());
            }
            const soloVer = modo === 'ver';
            formulario.querySelectorAll('input, select, textarea').forEach((campo) => {
                if (campo.type !== 'hidden') campo.disabled = soloVer;
            });
            document.querySelectorAll('[data-bs-target="#modalCategoriaRapida"], [data-bs-target="#modalMarcaRapida"], #btnGuardarProducto, #btnSeleccionarImagenesProducto').forEach((elemento) => {
                elemento.classList.toggle('d-none', soloVer);
            });
        });
    }

    const formularioProducto = document.getElementById('formularioProducto');
    if (formularioProducto) {
        formularioProducto.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const empresa = obtenerEmpresaProducto();
            const codigo = document.getElementById('producto_codigo_principal').value.trim();
            const nombre = document.getElementById('producto_nombre').value.trim();
            const categoria = document.getElementById('producto_categoria_id').value;
            const marca = document.getElementById('producto_marca_id').value;
            const costo = Number(document.getElementById('producto_costo_ultimo').value || 0);
            const minimo = Number(document.getElementById('producto_stock_minimo').value || 0);
            const maximo = Number(document.getElementById('producto_stock_maximo').value || 0);
            if (!empresa || !codigo || !nombre || !categoria || !marca || costo < 0 || minimo < 0 || maximo < 0) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_DATOS_OBLIGATORIOS', {
                    titulo: 'Datos incompletos',
                    texto: 'Ingrese empresa, codigo, nombre, categoria, marca y valores numericos validos.',
                    icono: 'error',
                });
                return;
            }

            try {
                const datos = new FormData(formularioProducto);
                const respuesta = await fetch(formularioProducto.action, {
                    method: 'POST',
                    body: datos,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await respuesta.json();
                if (!json.ok) {
                    throw new Error(json.mensaje || 'No se pudo guardar.');
                }
                document.getElementById('producto_id').value = String(json.data.producto_id || '');
                formularioProducto.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/productos/editar`;
                document.getElementById('modalProductoTitulo').textContent = 'Editar producto';
                habilitarGaleriaProducto(json.data.producto_id || '', json.data.empresa_id || empresa);
                await mostrarAlerta({
                    icono: 'success',
                    titulo: 'Producto guardado',
                    texto: json.mensaje || 'Producto guardado correctamente.',
                    tiempo: 0,
                    posicion: 2,
                });
            } catch (error) {
                mostrarAlerta({
                    icono: 'error',
                    titulo: 'No se pudo guardar',
                    texto: error.message || 'Revise los datos ingresados.',
                    tiempo: 0,
                    posicion: 4,
                });
            }
        });
    }

    const crearBloqueoCargaArchivos = (archivos) => {
        const bloqueo = document.createElement('div');
        bloqueo.className = 'bloqueo-carga-archivos';
        bloqueo.innerHTML = `
            <div class="bloqueo-carga-archivos-panel">
                <strong>Cargando archivos</strong>
                <div id="listaProgresoArchivos"></div>
            </div>
        `;
        document.body.appendChild(bloqueo);
        const lista = bloqueo.querySelector('#listaProgresoArchivos');
        archivos.forEach((archivo, indice) => {
            const fila = document.createElement('div');
            fila.className = 'progreso-archivo';
            fila.innerHTML = `
                <div>
                    <div class="text-truncate">${archivo.name}</div>
                    <div class="progress" style="height: 6px;"><div class="progress-bar" id="progreso_archivo_${indice}" style="width: 0%"></div></div>
                </div>
                <span id="porcentaje_archivo_${indice}">0%</span>
            `;
            lista.appendChild(fila);
        });
        return bloqueo;
    };

    const actualizarProgresoArchivo = (indice, porcentaje) => {
        const barra = document.getElementById(`progreso_archivo_${indice}`);
        const texto = document.getElementById(`porcentaje_archivo_${indice}`);
        if (barra) barra.style.width = `${porcentaje}%`;
        if (texto) texto.textContent = `${porcentaje}%`;
    };

    window.INTESIS_SUBIR_ARCHIVOS = async ({ url, archivos, parametros = {}, extensiones = ['jpg', 'jpeg', 'png', 'webp'], maximoMb = 7 }) => {
        const listaArchivos = Array.from(archivos || []);
        if (!listaArchivos.length) {
            throw new Error('Seleccione al menos un archivo.');
        }
        listaArchivos.forEach((archivo) => {
            const extension = archivo.name.split('.').pop().toLowerCase();
            if (!extensiones.includes(extension)) {
                throw new Error('Solo se permiten imagenes JPG, PNG o WEBP.');
            }
            if (archivo.size > maximoMb * 1024 * 1024) {
                throw new Error(`Cada archivo debe pesar maximo ${maximoMb}MB.`);
            }
        });

        const bloqueo = crearBloqueoCargaArchivos(listaArchivos);
        let subidos = 0;
        const respuestas = [];
        try {
            for (const [indice, archivo] of listaArchivos.entries()) {
                const datos = new FormData();
                Object.entries(parametros).forEach(([clave, valor]) => datos.append(clave, valor));
                datos.append('archivos[]', archivo);
                await new Promise((resolver, rechazar) => {
                    const solicitud = new XMLHttpRequest();
                    solicitud.open('POST', url);
                    solicitud.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    solicitud.upload.addEventListener('progress', (evento) => {
                        if (evento.lengthComputable) {
                            actualizarProgresoArchivo(indice, Math.round((evento.loaded / evento.total) * 100));
                        }
                    });
                    solicitud.addEventListener('load', () => {
                        try {
                            const json = JSON.parse(solicitud.responseText || '{}');
                            if (!json.ok) {
                                rechazar(new Error(json.mensaje || 'No se pudo cargar el archivo.'));
                                return;
                            }
                            actualizarProgresoArchivo(indice, 100);
                            subidos += Number(json.data?.subidos || 1);
                            respuestas.push(json);
                            resolver(json);
                        } catch (error) {
                            rechazar(error);
                        }
                    });
                    solicitud.addEventListener('error', () => rechazar(new Error('No se pudo cargar el archivo.')));
                    solicitud.send(datos);
                });
            }
            bloqueo.remove();
            await Swal.fire({
                icon: 'success',
                title: `CARGA DE ${subidos} ARCHIVOS COMPLETA`,
                position: 'top-end',
                toast: true,
                timer: 3500,
                timerProgressBar: true,
                showConfirmButton: false,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#1f6f68',
            });
            return { subidos, respuestas, ultima: respuestas[respuestas.length - 1] || null };
        } finally {
            bloqueo.remove();
        }
    };

    const habilitarGaleriaProducto = (productoId, empresaId) => {
        const botonSubir = document.getElementById('btnSeleccionarImagenesProducto');
        const botonGaleria = document.getElementById('btnAbrirGaleriaProductoModal');
        const ayuda = document.getElementById('ayudaGaleriaProducto');
        if (botonSubir) botonSubir.disabled = !productoId;
        if (botonGaleria) {
            botonGaleria.disabled = !productoId;
            botonGaleria.dataset.producto = productoId || '';
            botonGaleria.dataset.empresa = empresaId || '';
            botonGaleria.dataset.nombre = document.getElementById('producto_nombre')?.value || '';
        }
        if (ayuda) {
            ayuda.textContent = productoId ? 'Puede seleccionar multiples imagenes JPG, PNG o WEBP de hasta 7MB.' : 'Guarde el producto para habilitar la carga de imagenes.';
        }
    };

    const renderizarGaleriaProducto = (imagenes) => {
        const contenedor = document.getElementById('galeriaProductoContenedor');
        if (!contenedor) return;
        contenedor.innerHTML = '';
        if (!imagenes.length) {
            contenedor.innerHTML = '<div class="text-muted">Sin imagenes registradas.</div>';
            return;
        }
        imagenes.forEach((imagen) => {
            const item = document.createElement('div');
            item.className = 'galeria-producto-item';
            const soloLectura = contenedor.dataset.soloLectura === '1';
            item.innerHTML = `
                <img src="${imagen.url}" alt="${imagen.archivo}">
                <div class="galeria-producto-acciones">
                    ${soloLectura ? '' : `<button type="button" class="btn btn-accion btn-galeria-principal" data-archivo="${imagen.id}" title="Principal"><i class="bi ${imagen.principal ? 'bi-star-fill' : 'bi-star'}"></i></button>`}
                    <a class="btn btn-accion" href="${imagen.url}" target="_blank" title="Ver"><i class="bi bi-eye"></i></a>
                    ${soloLectura ? '' : `<button type="button" class="btn btn-accion btn-eliminar-galeria" data-archivo="${imagen.id}" title="Eliminar"><i class="bi bi-trash3"></i></button>`}
                </div>
            `;
            contenedor.appendChild(item);
        });
    };

    const cargarGaleriaProducto = async (productoId) => {
        if (!productoId) return;
        const respuesta = await fetch(`${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/productos/archivos/listar?producto_id=${productoId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await respuesta.json();
        if (!json.ok) {
            throw new Error(json.mensaje || 'No se pudo cargar la galeria.');
        }
        renderizarGaleriaProducto(json.data.imagenes || []);
    };

    document.getElementById('btnSeleccionarImagenesProducto')?.addEventListener('click', () => {
        document.getElementById('producto_imagenes')?.click();
    });

    document.getElementById('producto_imagenes')?.addEventListener('change', async (evento) => {
        const productoId = document.getElementById('producto_id')?.value || '';
        if (!productoId) {
            mostrarAlerta({ icono: 'warning', titulo: 'Producto requerido', texto: 'Guarde el producto antes de subir imagenes.' });
            return;
        }
        try {
            await window.INTESIS_SUBIR_ARCHIVOS({
                url: `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/productos/archivos/subir`,
                archivos: evento.target.files,
                parametros: { producto_id: productoId },
                maximoMb: 7,
            });
            evento.target.value = '';
            await cargarGaleriaProducto(productoId);
        } catch (error) {
            mostrarAlerta({ icono: 'error', titulo: 'No se pudo cargar', texto: error.message || 'Revise los archivos seleccionados.' });
        }
    });

    const modalGaleriaProducto = document.getElementById('modalGaleriaProducto');
    if (modalGaleriaProducto) {
        modalGaleriaProducto.addEventListener('show.bs.modal', async (evento) => {
            const boton = evento.relatedTarget || document.getElementById('btnAbrirGaleriaProductoModal');
            const productoId = boton?.dataset.producto || document.getElementById('producto_id')?.value || '';
            document.getElementById('galeria_producto_id').value = productoId;
            document.getElementById('galeria_empresa_id').value = boton?.dataset.empresa || obtenerEmpresaProducto();
            document.getElementById('modalGaleriaProductoTitulo').textContent = `Galeria ${boton?.dataset.nombre || document.getElementById('producto_nombre')?.value || 'producto'}`.trim();
            try {
                await cargarGaleriaProducto(productoId);
            } catch (error) {
                renderizarGaleriaProducto([]);
                mostrarAlerta({ icono: 'error', titulo: 'No se pudo cargar', texto: error.message || 'No se pudo cargar la galeria.' });
            }
        });

        modalGaleriaProducto.addEventListener('click', async (evento) => {
            const principal = evento.target.closest('.btn-galeria-principal');
            const eliminar = evento.target.closest('.btn-eliminar-galeria');
            const boton = principal || eliminar;
            if (!boton) return;
            const datos = new FormData();
            datos.append('archivo_id', boton.dataset.archivo || '');
            const ruta = principal ? 'principal' : 'eliminar';
            try {
                const respuesta = await fetch(`${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/productos/archivos/${ruta}`, {
                    method: 'POST',
                    body: datos,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await respuesta.json();
                if (!json.ok) throw new Error(json.mensaje || 'No se pudo actualizar la galeria.');
                await cargarGaleriaProducto(document.getElementById('galeria_producto_id').value);
            } catch (error) {
                mostrarAlerta({ icono: 'error', titulo: 'No se pudo actualizar', texto: error.message || 'Revise la imagen seleccionada.' });
            }
        });
    }

    const guardarCatalogoRapido = (formulario, selectId, modalId, etiqueta) => {
        formulario?.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const nombre = formulario.querySelector('[name="nombre"]')?.value.trim() || '';
            const empresa = formulario.querySelector('[name="empresa_id"]')?.value || obtenerEmpresaProducto();
            if (!nombre || !empresa) {
                mostrarMensaje('USUARIO_DATOS_OBLIGATORIOS', {
                    titulo: 'Datos incompletos',
                    texto: `Ingrese empresa y nombre de ${etiqueta}.`,
                    icono: 'error',
                });
                return;
            }
            try {
                const datos = new FormData(formulario);
                if (!datos.get('empresa_id')) datos.set('empresa_id', empresa);
                const respuesta = await fetch(formulario.action, {
                    method: 'POST',
                    body: datos,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await respuesta.json();
                if (!json.ok) {
                    throw new Error(json.mensaje || 'No se pudo guardar.');
                }
                const select = document.getElementById(selectId);
                const opcion = new Option(json.data.nombre, json.data.id, true, true);
                opcion.dataset.empresa = String(json.data.empresa_id || empresa);
                select.add(opcion);
                select.value = String(json.data.id);
                filtrarSelectsProductoPorEmpresa();
                select.value = String(json.data.id);
                bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
                formulario.reset();
                mostrarAlerta(json.mensaje_sistema || {
                    icono: 'success',
                    titulo: 'Registro guardado',
                    texto: json.mensaje || 'Registro guardado correctamente.',
                    tiempo: 0,
                    posicion: 2,
                });
            } catch (error) {
                mostrarAlerta({
                    icono: 'error',
                    titulo: 'No se pudo guardar',
                    texto: error.message || 'Revise los datos ingresados.',
                    tiempo: 0,
                    posicion: 4,
                });
            }
        });
    };

    guardarCatalogoRapido(document.getElementById('formularioCategoriaRapida'), 'producto_categoria_id', 'modalCategoriaRapida', 'categoria');
    guardarCatalogoRapido(document.getElementById('formularioMarcaRapida'), 'producto_marca_id', 'modalMarcaRapida', 'marca');

    const modalCategoria = document.getElementById('modalCategoria');
    if (modalCategoria) {
        modalCategoria.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioCategoria');
            formulario.reset();
            document.getElementById('categoria_id').value = '';
            document.getElementById('modalCategoriaTitulo').textContent = modo === 'ver' ? 'Ver categoria' : (modo === 'editar' ? 'Editar categoria' : 'Nueva categoria');
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/categorias/${modo === 'editar' ? 'editar' : 'crear'}`;
            if (modo !== 'crear') {
                document.getElementById('categoria_id').value = boton.dataset.id || '';
                const empresa = document.getElementById('categoria_empresa_id');
                if (empresa) empresa.value = boton.dataset.empresa || '';
                document.getElementById('categoria_nombre').value = boton.dataset.nombre || '';
                document.getElementById('categoria_descripcion').value = boton.dataset.descripcion || '';
            }
            const soloVer = modo === 'ver';
            formulario.querySelectorAll('input, select, textarea').forEach((campo) => {
                if (campo.type !== 'hidden') campo.disabled = soloVer;
            });
            document.getElementById('btnGuardarCategoria')?.classList.toggle('d-none', soloVer);
        });
    }

    const modalMarca = document.getElementById('modalMarca');
    if (modalMarca) {
        modalMarca.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioMarca');
            formulario.reset();
            document.getElementById('marca_id').value = '';
            document.getElementById('modalMarcaTitulo').textContent = modo === 'ver' ? 'Ver marca' : (modo === 'editar' ? 'Editar marca' : 'Nueva marca');
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/marcas/${modo === 'editar' ? 'editar' : 'crear'}`;
            if (modo !== 'crear') {
                document.getElementById('marca_id').value = boton.dataset.id || '';
                const empresa = document.getElementById('marca_empresa_id');
                if (empresa) empresa.value = boton.dataset.empresa || '';
                document.getElementById('marca_nombre').value = boton.dataset.nombre || '';
            }
            const soloVer = modo === 'ver';
            formulario.querySelectorAll('input, select').forEach((campo) => {
                if (campo.type !== 'hidden') campo.disabled = soloVer;
            });
            document.getElementById('btnGuardarMarca')?.classList.toggle('d-none', soloVer);
        });
    }

    const modalEstado = document.getElementById('modalEstado');
    if (modalEstado) {
        modalEstado.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioEstado');
            formulario.reset();
            document.getElementById('estado_id').value = '';
            document.getElementById('modalEstadoTitulo').textContent = modo === 'editar' ? 'Editar estado' : 'Nuevo estado';
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/configuracion/estados/${modo === 'editar' ? 'editar' : 'crear'}`;
            if (modo === 'editar') {
                document.getElementById('estado_id').value = boton.dataset.id || '';
                document.getElementById('estado_modulo').value = boton.dataset.modulo || '';
                document.getElementById('estado_entidad').value = boton.dataset.entidad || '';
                document.getElementById('estado_codigo').value = boton.dataset.codigo || '';
                document.getElementById('estado_nombre').value = boton.dataset.nombre || '';
                document.getElementById('estado_descripcion').value = boton.dataset.descripcion || '';
                document.getElementById('estado_orden').value = boton.dataset.orden || '1';
            }
        });
    }

    const modalMensajeError = document.getElementById('modalMensajeError');
    if (modalMensajeError) {
        modalMensajeError.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioMensajeError');
            formulario.reset();
            document.getElementById('mensaje_id').value = '';
            document.getElementById('mensaje_tiempo').value = '0';
            document.getElementById('mensaje_posicion').value = '4';
            document.getElementById('modalMensajeTitulo').textContent = modo === 'editar' ? 'Editar mensaje' : 'Nuevo mensaje';
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/configuracion/mensajes-error/${modo === 'editar' ? 'editar' : 'crear'}`;
            if (modo === 'editar') {
                document.getElementById('mensaje_id').value = boton.dataset.id || '';
                document.getElementById('mensaje_codigo').value = boton.dataset.codigo || '';
                document.getElementById('mensaje_tipo').value = boton.dataset.tipo || 'ERROR';
                document.getElementById('mensaje_titulo').value = boton.dataset.titulo || '';
                document.getElementById('mensaje_texto').value = boton.dataset.mensaje || '';
                document.getElementById('mensaje_icono').value = boton.dataset.icono || 'error';
                document.getElementById('mensaje_modulo').value = boton.dataset.modulo || '';
                document.getElementById('mensaje_entidad').value = boton.dataset.entidad || '';
                document.getElementById('mensaje_tiempo').value = boton.dataset.tiempo || '0';
                document.getElementById('mensaje_posicion').value = boton.dataset.posicion || '4';
            }
        });
    }

    const modalTipoDocumento = document.getElementById('modalTipoDocumento');
    if (modalTipoDocumento) {
        modalTipoDocumento.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioTipoDocumento');
            formulario.reset();
            document.getElementById('tipo_documento_id').value = '';
            document.getElementById('modalTipoDocumentoTitulo').textContent = modo === 'editar' ? 'Editar tipo' : 'Nuevo tipo';
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/configuracion/tipos-documento/${modo === 'editar' ? 'editar' : 'crear'}`;
            if (modo === 'editar') {
                document.getElementById('tipo_documento_id').value = boton.dataset.id || '';
                document.getElementById('tipo_documento_modulo').value = boton.dataset.modulo || 'VENTAS';
                document.getElementById('tipo_documento_codigo').value = boton.dataset.codigo || '';
                document.getElementById('tipo_documento_nombre').value = boton.dataset.nombre || '';
                document.getElementById('tipo_documento_descripcion').value = boton.dataset.descripcion || '';
                document.getElementById('tipo_documento_contabilidad').checked = boton.dataset.contabilidad === '1';
                document.getElementById('tipo_documento_inventario').checked = boton.dataset.inventario === '1';
            }
        });
    }

    const modalSecuenciasTipo = document.getElementById('modalSecuenciasTipo');
    if (modalSecuenciasTipo) {
        const renderizarSecuenciasTipo = (tipoId) => {
            const destino = document.getElementById('tablaSecuenciasTipo');
            const filas = Array.from(document.querySelectorAll('.fila-secuencia-fuente'))
                .filter((fila) => fila.dataset.tipo === tipoId);
            destino.innerHTML = '';
            if (!filas.length) {
                destino.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sin secuencias registradas.</td></tr>';
                return;
            }
            filas.forEach((fila) => {
                destino.appendChild(fila.cloneNode(true));
            });
        };

        modalSecuenciasTipo.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const tipoId = boton?.dataset.tipo || window.INTESIS_TIPO_SELECCIONADO || '';
            window.INTESIS_TIPO_SELECCIONADO = tipoId;
            document.getElementById('modalSecuenciasTipoTitulo').textContent = `Secuencias ${boton?.dataset.nombre || ''}`.trim();
            const botonNuevo = document.getElementById('btnNuevaSecuencia');
            if (botonNuevo) {
                botonNuevo.dataset.tipo = tipoId;
            }
            renderizarSecuenciasTipo(tipoId);
        });
    }

    const modalSecuencia = document.getElementById('modalSecuencia');
    if (modalSecuencia) {
        const normalizarTresDigitos = (campo) => {
            campo.value = campo.value.replace(/\D+/g, '').slice(0, 3).padStart(3, '0');
        };

        ['secuencia_establecimiento', 'secuencia_punto_emision'].forEach((id) => {
            document.getElementById(id)?.addEventListener('blur', (evento) => normalizarTresDigitos(evento.target));
        });

        modalSecuencia.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            const modo = boton?.dataset.modo || 'crear';
            const formulario = document.getElementById('formularioSecuencia');
            formulario.reset();
            document.getElementById('secuencia_id').value = '';
            document.getElementById('secuencia_tipo_documento_id').value = boton?.dataset.tipo || window.INTESIS_TIPO_SELECCIONADO || '';
            document.getElementById('secuencia_desde').value = '1';
            document.getElementById('secuencia_actual').value = '1';
            document.getElementById('secuencia_hasta').value = '999999999';
            document.getElementById('modalSecuenciaTitulo').textContent = modo === 'editar' ? 'Editar secuencia' : 'Nueva secuencia';
            formulario.action = `${window.location.origin}${document.body.dataset.baseUrl || ''}/sistema/configuracion/secuencias/${modo === 'editar' ? 'editar' : 'crear'}`;

            if (modo === 'editar') {
                document.getElementById('secuencia_id').value = boton.dataset.id || '';
                document.getElementById('secuencia_tipo_documento_id').value = boton.dataset.tipo || '';
                const empresa = document.getElementById('secuencia_empresa_id');
                if (empresa) {
                    empresa.value = boton.dataset.empresa || '';
                }
                document.getElementById('secuencia_establecimiento').value = boton.dataset.establecimiento || '';
                document.getElementById('secuencia_punto_emision').value = boton.dataset.punto || '';
                document.getElementById('secuencia_desde').value = boton.dataset.desde || '1';
                document.getElementById('secuencia_actual').value = boton.dataset.actual || '1';
                document.getElementById('secuencia_hasta').value = boton.dataset.hasta || '999999999';
                document.getElementById('secuencia_observacion').value = boton.dataset.observacion || '';
            }
        });
    }

    const formularioSecuencia = document.getElementById('formularioSecuencia');
    if (formularioSecuencia) {
        formularioSecuencia.addEventListener('submit', (evento) => {
            const tipo = document.getElementById('secuencia_tipo_documento_id').value;
            const empresa = document.getElementById('secuencia_empresa_id')?.value || document.getElementById('secuencia_empresa_oculta')?.value || '';
            const establecimiento = document.getElementById('secuencia_establecimiento').value;
            const punto = document.getElementById('secuencia_punto_emision').value;
            const desde = Number(document.getElementById('secuencia_desde').value);
            const actual = Number(document.getElementById('secuencia_actual').value);
            const hasta = Number(document.getElementById('secuencia_hasta').value);
            if (!tipo || !empresa || !/^\d{3}$/.test(establecimiento) || !/^\d{3}$/.test(punto) || desde < 1 || actual < desde || actual > hasta || hasta > 999999999) {
                evento.preventDefault();
                mostrarMensaje('USUARIO_DATOS_OBLIGATORIOS', {
                    titulo: 'Datos incompletos',
                    texto: 'Revise empresa, tipo, punto de emision y rango de secuencia.',
                    icono: 'error',
                });
            }
        });
    }

    if (window.location.hash === '#mensajes') {
        const tab = document.getElementById('tabMensajes');
        if (tab && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }
    }

    if (window.location.hash === '#tipos') {
        const tab = document.getElementById('tabTipos');
        if (tab && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }
    }

    const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[caracter]));

    const renderizarPrevisualizacionStock = (filas) => {
        const cuerpo = document.querySelector('#tablaPrevisualizacionStock tbody');
        const botonConfirmar = document.getElementById('btnConfirmarImportacionStock');
        if (!cuerpo) return;
        if (!filas.length) {
            cuerpo.innerHTML = '<tr><td colspan="9" class="text-muted">Sin registros.</td></tr>';
            if (botonConfirmar) botonConfirmar.disabled = true;
            return;
        }
        cuerpo.innerHTML = filas.map((fila) => `
            <tr>
                <td>${escaparHtml(fila.linea)}</td>
                <td>${escaparHtml(fila.codigo_producto)}</td>
                <td>${escaparHtml(fila.nombre_producto)}</td>
                <td>${escaparHtml(fila.marca)}</td>
                <td>${escaparHtml(fila.codigo_bodega)}</td>
                <td class="text-end">${escaparHtml(fila.cantidad)}</td>
                <td class="text-end">${escaparHtml(fila.costo)}</td>
                <td><span class="badge estado-badge ${fila.estado === 'OK' ? 'estado-activo' : 'estado-inactivo'}">${escaparHtml(fila.estado)}</span></td>
                <td>${escaparHtml(fila.mensaje)}</td>
            </tr>
        `).join('');
        if (botonConfirmar) {
            botonConfirmar.disabled = !filas.some((fila) => fila.estado === 'OK');
        }
    };

    document.getElementById('btnPrevisualizarStock')?.addEventListener('click', async () => {
        const archivo = document.getElementById('stock_importar_archivo');
        try {
            const resultado = await window.INTESIS_SUBIR_ARCHIVOS({
                url: `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/stock/importar`,
                archivos: archivo?.files || [],
                parametros: {
                    empresa_id: document.getElementById('stock_importar_empresa_id')?.value || '',
                    accion_stock: document.getElementById('stock_importar_accion')?.value || 'sumar',
                    producto_inexistente: document.getElementById('stock_importar_producto')?.value || 'crear',
                },
                extensiones: ['csv'],
                maximoMb: 7,
            });
            renderizarPrevisualizacionStock(resultado.ultima?.data?.filas || []);
            if (archivo) archivo.value = '';
        } catch (error) {
            mostrarAlerta({ icono: 'error', titulo: 'No se pudo previsualizar', texto: error.message || 'Revise el CSV.' });
        }
    });

    document.getElementById('btnConfirmarImportacionStock')?.addEventListener('click', async () => {
        try {
            const respuesta = await fetch(`${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/stock/confirmar-importacion`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await respuesta.json();
            if (!json.ok) {
                throw new Error(json.mensaje || 'No se pudo confirmar la importacion.');
            }
            await Swal.fire({
                icon: 'success',
                title: 'Importacion confirmada',
                text: `Registradas: ${json.data?.registradas || 0}. Omitidas: ${json.data?.omitidas || 0}.`,
                position: 'top-end',
                toast: true,
                timer: 3500,
                timerProgressBar: true,
                showConfirmButton: false,
            });
            window.location.reload();
        } catch (error) {
            mostrarAlerta({ icono: 'error', titulo: 'No se pudo confirmar', texto: error.message || 'Revise la importacion.' });
        }
    });

    const abrirDetalleStock = async ({ url, titulo, columnas, mapear }) => {
        const modal = document.getElementById('modalStockDetalle');
        if (!modal) return;
        const respuesta = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await respuesta.json();
        if (!json.ok) throw new Error(json.mensaje || 'No se pudo cargar el detalle.');
        document.getElementById('modalStockDetalleTitulo').textContent = titulo;
        document.getElementById('stockDetalleHead').innerHTML = `<tr>${columnas.map((columna) => `<th>${escaparHtml(columna)}</th>`).join('')}</tr>`;
        const registros = mapear(json.data || {});
        document.getElementById('stockDetalleBody').innerHTML = registros.length
            ? registros.join('')
            : `<tr><td colspan="${columnas.length}" class="text-muted">Sin registros.</td></tr>`;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    };

    document.querySelectorAll('.btn-stock-precios').forEach((boton) => {
        boton.addEventListener('click', async () => {
            try {
                await abrirDetalleStock({
                    url: `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/stock/precios?empresa_id=${encodeURIComponent(boton.dataset.empresa || '')}&producto_id=${encodeURIComponent(boton.dataset.producto || '')}`,
                    titulo: 'Precios',
                    columnas: ['Lista', 'PVP', 'Pred.'],
                    mapear: (data) => (data.precios || []).map((precio) => `<tr><td>${escaparHtml(precio.ven_lista_precio_descripcion)}</td><td class="text-end">${Number(precio.precio || 0).toFixed(2)}</td><td>${Number(precio.ven_lista_precio_predeterminado || 0) === 1 ? 'SI' : ''}</td></tr>`),
                });
            } catch (error) {
                mostrarAlerta({ icono: 'error', titulo: 'No se pudo cargar', texto: error.message || 'Revise precios.' });
            }
        });
    });

    document.querySelectorAll('.btn-stock-codigos').forEach((boton) => {
        boton.addEventListener('click', async () => {
            try {
                await abrirDetalleStock({
                    url: `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/stock/codigos-proveedor?empresa_id=${encodeURIComponent(boton.dataset.empresa || '')}&producto_id=${encodeURIComponent(boton.dataset.producto || '')}`,
                    titulo: 'Codigos proveedor',
                    columnas: ['Cod. proveedor', 'Proveedor'],
                    mapear: (data) => (data.codigos || []).map((codigo) => `<tr><td>${escaparHtml(codigo.inv_codigo_proveedor_codigo)}</td><td>${escaparHtml(codigo.proveedor)}</td></tr>`),
                });
            } catch (error) {
                mostrarAlerta({ icono: 'error', titulo: 'No se pudo cargar', texto: error.message || 'Revise codigos.' });
            }
        });
    });

    const formatearNumeroKardex = (valor) => Number(valor || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });

    const formatearCantidadKardex = (valor) => {
        const numero = Number(valor || 0);
        if (numero > 0) return `+${formatearNumeroKardex(numero)}`;
        if (numero < 0) return `-${formatearNumeroKardex(Math.abs(numero))}`;
        return '0.00';
    };

    const cargarDetalleKardex = async () => {
        const tabla = document.getElementById('tablaDetalleKardex');
        if (!tabla) return;
        const empresaId = document.getElementById('kardex_detalle_empresa_id')?.value || '';
        const productoId = document.getElementById('kardex_detalle_producto_id')?.value || '';
        const desde = document.getElementById('kardex_detalle_desde')?.value || '';
        const hasta = document.getElementById('kardex_detalle_hasta')?.value || '';
        const parametros = new URLSearchParams({ empresa_id: empresaId, producto_id: productoId });
        if (desde) parametros.set('desde', desde);
        if (hasta) parametros.set('hasta', hasta);

        const respuesta = await fetch(`${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/kardex/detalle?${parametros.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await respuesta.json();
        if (!json.ok) throw new Error(json.mensaje || 'No se pudo cargar kardex.');
        
        const movimientos = json.data?.movimientos || [];
        const bodegas = json.data?.bodegas || [];
        const columnasBodega = bodegas.flatMap((bodega) => [`Cant ${bodega.inv_bodega_codigo}`, `Saldo ${bodega.inv_bodega_codigo}`]);
        const columnas = [ `${movimientos.length}`, 'Detalle', 'No. Documento', ...columnasBodega, 'Fecha', 'Hora'];
        tabla.querySelector('thead').innerHTML = `<tr>${columnas.map((columna) => `<th class="text-center">${escaparHtml(columna)}</th>`).join('')}</tr>`;

        tabla.querySelector('tbody').innerHTML = movimientos.length
            ? movimientos.map((movimiento, indice) => {
                const celdasBodega = bodegas.map((bodega) => {
                    const coincide = Number(bodega.inv_bodega_id) === Number(movimiento.bodega_id);
                    const cantidad = coincide ? formatearCantidadKardex(movimiento.cantidad) : '0.00';
                    const saldo = coincide ? formatearNumeroKardex(movimiento.saldo) : '0.00';
                    const claseCantidad = Number(movimiento.cantidad || 0) > 0 ? 'text-success' : (Number(movimiento.cantidad || 0) < 0 ? 'text-danger' : '');
                    return `<td class="text-center ${coincide ? claseCantidad : ''}">${cantidad}</td><td class="text-center">${saldo}</td>`;
                }).join('');
                return `
                    <tr>
                        <td class="text-center">${indice + 1}</td>
                        <td class="text-center">${escaparHtml(movimiento.detalle)}</td>
                        <td class="text-center"><button type="button" class="btn btn-link btn-sm btn-kardex-pdf" data-empresa="${escaparHtml(empresaId)}" data-documento-tipo="${escaparHtml(movimiento.documento_tipo)}" data-documento-id="${escaparHtml(movimiento.documento_id)}" data-documento-numero="${escaparHtml(movimiento.documento_numero || 'Sin numero')}" title="Ver Documento">${escaparHtml(movimiento.documento_numero || 'Sin numero')}</button></td>
                        ${celdasBodega}
                        <td class="text-center">${escaparHtml(movimiento.fecha)}</td>
                        <td class="text-center">${escaparHtml(movimiento.hora)}</td>
                    </tr>
                `;
            }).join('')
            : `<tr><td colspan="${columnas.length}" class="text-muted">Sin movimientos.</td></tr>`;
    };

    document.querySelectorAll('.btn-kardex-detalle').forEach((boton) => {
        boton.addEventListener('click', async () => {
            const modal = document.getElementById('modalDetalleKardex');
            if (!modal) return;
            document.getElementById('kardex_detalle_empresa_id').value = boton.dataset.empresa || '';
            document.getElementById('kardex_detalle_producto_id').value = boton.dataset.producto || '';
            document.getElementById('modalDetalleKardexTitulo').textContent = `Codigo: ${boton.dataset.codigo || ''} - ${boton.dataset.nombre || ''}`.trim();
            try {
                await cargarDetalleKardex();
                bootstrap.Modal.getOrCreateInstance(modal).show();
            } catch (error) {
                mostrarAlerta({ icono: 'error', titulo: 'No se pudo cargar', texto: error.message || 'Revise kardex.' });
            }
        });
    });

    document.getElementById('btnConsultarDetalleKardex')?.addEventListener('click', async () => {
        try {
            await cargarDetalleKardex();
        } catch (error) {
            mostrarAlerta({ icono: 'error', titulo: 'No se pudo consultar', texto: error.message || 'Revise las fechas.' });
        }
    });

    document.getElementById('tablaDetalleKardex')?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.btn-kardex-pdf');
        if (!boton) return;
        const modal = document.getElementById('modalPdfKardex');
        const visor = document.getElementById('visorPdfKardex');
        if (!modal || !visor) return;
        const parametros = new URLSearchParams({
            empresa_id: boton.dataset.empresa || '',
            documento_tipo: boton.dataset.documentoTipo || '',
            documento_id: boton.dataset.documentoId || '',
        });
        document.getElementById('modalPdfKardexTitulo').textContent = `Documento ${boton.dataset.documentoNumero || ''}`.trim();
        visor.src = `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/kardex/documento?${parametros.toString()}`;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    const actualizarCapasModales = () => {
        const modalesAbiertos = Array.from(document.querySelectorAll('.modal.show'));
        modalesAbiertos.forEach((modal, indice) => {
            const zIndex = 1055 + (indice * 20);
            modal.style.zIndex = String(zIndex);
            modal.classList.toggle('modal-en-segundo-plano', indice < modalesAbiertos.length - 1);
        });
    };

    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('show.bs.modal', () => {
            const cantidadAbiertos = document.querySelectorAll('.modal.show').length;
            modal.style.zIndex = String(1055 + (cantidadAbiertos * 20));

            setTimeout(() => {
                const respaldos = document.querySelectorAll('.modal-backdrop:not(.modal-backdrop-apilado)');
                const respaldo = respaldos[respaldos.length - 1];
                if (respaldo) {
                    respaldo.classList.add('modal-backdrop-apilado');
                    respaldo.style.zIndex = String(1050 + (cantidadAbiertos * 20));
                }
                actualizarCapasModales();
            }, 0);
        });

        modal.addEventListener('hidden.bs.modal', () => {
            modal.style.zIndex = '';
            modal.classList.remove('modal-en-segundo-plano');
            if (document.querySelector('.modal.show')) {
                document.body.classList.add('modal-open');
            }
            actualizarCapasModales();
        });
    });

    document.querySelectorAll('form').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            if (evento.defaultPrevented) {
                return;
            }

            const boton = formulario.querySelector('button[type="submit"]');
            if (boton) {
                boton.classList.add('procesando');
            }
        });
    });
})();
