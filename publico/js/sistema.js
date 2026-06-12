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
            if (document.getElementById('bodega_codigo')) document.getElementById('bodega_codigo').dataset.manual = '';
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
                document.getElementById('bodega_negativos').checked = boton.dataset.negativos === '1';
                document.getElementById('bodega_autoaprobado').checked = boton.dataset.autoaprobado === '1';
            }
        });
    }

    const inpBodegaNombre = document.getElementById('bodega_nombre');
    const inpBodegaCodigo = document.getElementById('bodega_codigo');
    if (inpBodegaNombre && inpBodegaCodigo) {
        inpBodegaNombre.addEventListener('input', () => {
            if (inpBodegaCodigo.dataset.manual === '1') return;
            const mapa = {á:'a',é:'e',í:'i',ó:'o',ú:'u',à:'a',è:'e',ì:'i',ò:'o',ù:'u',ä:'a',ë:'e',ï:'i',ö:'o',ü:'u',ñ:'n',ç:'c',Á:'A',É:'E',Í:'I',Ó:'O',Ú:'U',À:'A',È:'E',Ì:'I',Ò:'O',Ù:'U',Ä:'A',Ë:'E',Ï:'I',Ö:'O',Ü:'U',Ñ:'N',Ç:'C'};
            const codigo = inpBodegaNombre.value
                .split('').map(c => mapa[c] ?? c).join('')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .substring(0, 30);
            inpBodegaCodigo.value = codigo;
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
            if (!empresa || !codigo || !nombre) {
                evento.preventDefault();
                Swal.fire({ icon: 'error', title: 'Datos incompletos', text: 'Ingrese empresa, codigo y nombre.' });
            } else if (principal && virtual) {
                evento.preventDefault();
                Swal.fire({ icon: 'error', title: 'Datos incompletos', text: 'Una bodega virtual no puede ser principal.' });
            }
        });
    }

    const modalBodegaUsuarios = document.getElementById('modalBodegaUsuarios');
    const formularioBodegaUsuarios = document.getElementById('formularioBodegaUsuarios');
    const permisosBodegaUsuarios = window.INTESIS_BODEGA_USUARIO_PERMISOS || {};
    let bodegaUsuariosActual = '';

    const urlBodegaUsuarios = (ruta = '') => `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/bodegas/usuarios${ruta}`;

    const cargarUsuariosBodega = async () => {
        if (!bodegaUsuariosActual) return;
        const respuesta = await fetch(`${urlBodegaUsuarios()}?bodega_id=${encodeURIComponent(bodegaUsuariosActual)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await respuesta.json();
        if (!json.ok) {
            throw new Error(json.mensaje || 'No se pudo cargar usuarios de bodega.');
        }

        const usuarios = json.data?.usuarios || [];
        const asignaciones = json.data?.asignaciones || [];
        const usuariosAsignadosActivos = new Set(asignaciones.filter((fila) => Number(fila.inv_bodega_usuarios_estado) === 1).map((fila) => String(fila.sis_usuarios_id)));
        const selectUsuario = document.getElementById('bodega_usuarios_usuario_id');
        if (selectUsuario) {
            const opciones = usuarios
                .filter((usuario) => !usuariosAsignadosActivos.has(String(usuario.sis_usuarios_id)))
                .map((usuario) => `<option value="${usuario.sis_usuarios_id}">${escaparHtml(usuario.sis_usuarios_nombre)} - ${escaparHtml(usuario.sis_usuarios_correo)}</option>`)
                .join('');
            selectUsuario.innerHTML = `<option value="">Seleccione</option>${opciones}`;
        }

        const cuerpo = document.querySelector('#tablaBodegaUsuarios tbody');
        if (cuerpo) {
            cuerpo.innerHTML = asignaciones.length ? asignaciones.map((asignacion) => {
                const activo = Number(asignacion.inv_bodega_usuarios_estado) === 1;
                const predeterminada = asignacion.inv_bodega_usuarios_predeterminada === true || asignacion.inv_bodega_usuarios_predeterminada === 't' || Number(asignacion.inv_bodega_usuarios_predeterminada) === 1;
                const botonEstado = activo
                    ? (permisosBodegaUsuarios.usuariosInactivar ? `<button type="button" class="btn btn-accion btn-bodega-usuario-estado" data-ruta="/inactivar" data-id="${asignacion.inv_bodega_usuarios_id}" title="Inactivar usuario"><i class="bi bi-toggle-off"></i></button>` : '')
                    : (permisosBodegaUsuarios.usuariosActivar ? `<button type="button" class="btn btn-accion btn-bodega-usuario-estado" data-ruta="/activar" data-id="${asignacion.inv_bodega_usuarios_id}" title="Activar usuario"><i class="bi bi-toggle-on"></i></button>` : '');
                return `<tr>
                    <td>${escaparHtml(asignacion.sis_usuarios_nombre)}</td>
                    <td>${escaparHtml(asignacion.sis_usuarios_correo)}</td>
                    <td><span class="badge estado-badge ${predeterminada ? 'estado-activo' : 'estado-inactivo'}">${predeterminada ? 'SI' : 'NO'}</span></td>
                    <td><span class="badge estado-badge ${activo ? 'estado-activo' : 'estado-inactivo'}">${activo ? 'ACTIVO' : 'INACTIVO'}</span></td>
                    <td class="text-end">${botonEstado}</td>
                </tr>`;
            }).join('') : '<tr><td colspan="5" class="text-center text-muted">Sin usuarios asignados.</td></tr>';
        }
    };

    /* Modal de solo lectura: usuarios asignados a una bodega */
    const modalVerUsuariosBodega = document.getElementById('modalVerUsuariosBodega');
    if (modalVerUsuariosBodega) {
        document.addEventListener('click', (evento) => {
            const boton = evento.target.closest('.btn-ver-usuarios-bodega');
            if (!boton) return;
            const bodegaId = boton.dataset.id || '';
            const bodegaNombre = boton.dataset.nombre || '';
            document.getElementById('modalVerUsuariosBodegaTitulo').textContent = bodegaNombre;
            const cargando = document.getElementById('verUsuariosBodegaCargando');
            const contenedor = document.getElementById('verUsuariosBodegaTablaContenedor');
            const cuerpo = document.getElementById('verUsuariosBodegaCuerpo');
            cargando.classList.remove('d-none');
            contenedor.classList.add('d-none');
            cuerpo.innerHTML = '';
            bootstrap.Modal.getOrCreateInstance(modalVerUsuariosBodega).show();
            fetch(`${window.INTESIS_APP_URL || ''}/inventario/bodegas/usuarios?bodega_id=${bodegaId}`)
                .then((r) => r.json())
                .then((res) => {
                    cargando.classList.add('d-none');
                    if (!res.exito) { cuerpo.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${escaparHtml(res.mensaje || 'Error al cargar.')}</td></tr>`; contenedor.classList.remove('d-none'); return; }
                    const asignaciones = (res.datos?.asignaciones || []).filter((a) => a.inv_bodega_usuarios_estado == 1);
                    if (!asignaciones.length) {
                        cuerpo.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sin usuarios asignados activos.</td></tr>';
                    } else {
                        cuerpo.innerHTML = asignaciones.map((a, i) => {
                            const fecha = a.fecha_asignacion ? a.fecha_asignacion.substring(0, 10) : '—';
                            return `<tr><td>${i + 1}</td><td>${escaparHtml(a.sis_usuarios_nombre)}</td><td>${escaparHtml(bodegaNombre)}</td><td>${escaparHtml(fecha)}</td></tr>`;
                        }).join('');
                    }
                    contenedor.classList.remove('d-none');
                })
                .catch(() => {
                    cargando.classList.add('d-none');
                    cuerpo.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error de conexion.</td></tr>';
                    contenedor.classList.remove('d-none');
                });
        });
    }

    if (modalBodegaUsuarios) {
        modalBodegaUsuarios.addEventListener('show.bs.modal', async (evento) => {
            const boton = evento.relatedTarget;
            bodegaUsuariosActual = boton?.dataset.id || '';
            document.getElementById('bodega_usuarios_bodega_id').value = bodegaUsuariosActual;
            document.getElementById('bodegaUsuariosTitulo').textContent = boton?.dataset.nombre ? `- ${boton.dataset.nombre}` : '';
            document.querySelector('#tablaBodegaUsuarios tbody').innerHTML = '<tr><td colspan="5" class="text-center text-muted">Cargando...</td></tr>';
            try {
                await cargarUsuariosBodega();
            } catch (error) {
                mostrarAlerta({ icono: 'error', titulo: 'No se pudo cargar', texto: error.message });
            }
        });
    }

    if (formularioBodegaUsuarios) {
        formularioBodegaUsuarios.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const usuarioId = document.getElementById('bodega_usuarios_usuario_id')?.value || '';
            if (!usuarioId) {
                mostrarAlerta({ icono: 'error', titulo: 'Datos incompletos', texto: 'Seleccione un usuario.' });
                return;
            }
            try {
                const datos = new FormData(formularioBodegaUsuarios);
                const respuesta = await fetch(urlBodegaUsuarios('/guardar'), {
                    method: 'POST',
                    body: datos,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await respuesta.json();
                if (!json.ok) {
                    throw new Error(json.mensaje || 'No se pudo guardar.');
                }
                formularioBodegaUsuarios.reset();
                document.getElementById('bodega_usuarios_bodega_id').value = bodegaUsuariosActual;
                await cargarUsuariosBodega();
                mostrarAlerta({ icono: 'success', titulo: 'Guardado', texto: json.mensaje || 'Cambios guardados.', posicion: 2, tiempo: 1800 });
            } catch (error) {
                mostrarAlerta({ icono: 'error', titulo: 'No se pudo guardar', texto: error.message });
            }
        });
    }

    document.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('.btn-bodega-usuario-estado');
        if (!boton) return;
        const confirmar = await mostrarAlerta({
            icono: 'warning',
            titulo: 'Confirmar cambio',
            texto: 'El permiso de la bodega cambiara para este usuario.',
        }, {
            omitirTimer: true,
            extra: {
                showCancelButton: true,
                showConfirmButton: true,
                cancelButtonText: 'Cancelar',
                cancelButtonColor: '#263a5f',
            },
            confirmButtonText: 'Confirmar',
            confirmButtonColor: '#d65f5f',
        });
        if (!confirmar.isConfirmed) return;
        try {
            const datos = new FormData();
            datos.append('bodega_id', bodegaUsuariosActual);
            datos.append('asignacion_id', boton.dataset.id || '');
            const respuesta = await fetch(urlBodegaUsuarios(boton.dataset.ruta || ''), {
                method: 'POST',
                body: datos,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await respuesta.json();
            if (!json.ok) {
                throw new Error(json.mensaje || 'No se pudo cambiar estado.');
            }
            await cargarUsuariosBodega();
            mostrarAlerta({ icono: 'success', titulo: 'Actualizado', texto: json.mensaje || 'Cambio aplicado.', posicion: 2, tiempo: 1800 });
        } catch (error) {
            mostrarAlerta({ icono: 'error', titulo: 'No se pudo actualizar', texto: error.message });
        }
    });

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
            const fuente = document.getElementById('filasSecuenciasFuente');
            const filas = Array.from(fuente ? fuente.querySelectorAll('.fila-secuencia-fuente') : [])
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

    const formatearNumeroKardex = (valor) => Math.round(Number(valor || 0)).toLocaleString('en-US');

    const formatearCantidadKardex = (valor) => {
        const numero = Number(valor || 0);
        if (numero > 0) return `+${formatearNumeroKardex(numero)}`;
        if (numero < 0) return `-${formatearNumeroKardex(Math.abs(numero))}`;
        return '0';
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
                    const cantidad = coincide ? formatearCantidadKardex(movimiento.cantidad) : '0';
                    const saldo = coincide ? formatearNumeroKardex(movimiento.saldo) : '0';
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

    const abrirPdfInventario = (empresaId, documentoTipo, documentoId, documentoNumero) => {
        const modal = document.getElementById('modalPdfKardex');
        const visor = document.getElementById('visorPdfKardex');
        if (!modal || !visor) return;
        const parametros = new URLSearchParams({ empresa_id: empresaId, documento_tipo: documentoTipo, documento_id: documentoId });
        document.getElementById('modalPdfKardexTitulo').textContent = `Documento ${documentoNumero || ''}`.trim();
        visor.src = `${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/kardex/documento?${parametros.toString()}`;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    };

    document.getElementById('tablaDetalleKardex')?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.btn-kardex-pdf');
        if (!boton) return;
        abrirPdfInventario(boton.dataset.empresa || '', boton.dataset.documentoTipo || '', boton.dataset.documentoId || '', boton.dataset.documentoNumero || '');
    });

    document.getElementById('tablaMovimientosLista')?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.btn-mov-pdf');
        if (!boton) return;
        abrirPdfInventario(boton.dataset.empresa || '', 'INV_MOVIMIENTOS', boton.dataset.documentoId || '', boton.dataset.documentoNumero || '');
    });

    let indiceLineaMovimiento = 0;
    let lineaProductoMovimientoActiva = null;
    let terminoPendienteBusquedaMovimiento = null;
    const bodegasMovimiento = window.INTESIS_BODEGAS_MOVIMIENTO || [];
    const permisosMovimiento = window.INTESIS_MOVIMIENTO_PERMISOS || {};

    const opcionesBodegaMovimiento = (seleccionado = '') => bodegasMovimiento.map((bodega) => `<option value="${bodega.inv_bodega_id}" ${String(seleccionado) === String(bodega.inv_bodega_id) ? 'selected' : ''}>${escaparHtml(bodega.inv_bodega_codigo)} - ${escaparHtml(bodega.inv_bodega_nombre)}</option>`).join('');

    const accionesMovimiento = (tipo) => {
        if (tipo === 'AJUSTE') {
            const opciones = [];
            if (permisosMovimiento.ajusteIngreso) opciones.push('<option value="INGRESO">Ingreso +</option>');
            if (permisosMovimiento.ajusteEgreso) opciones.push('<option value="EGRESO">Egreso -</option>');
            return opciones.join('');
        }
        return '<option value="TRANSFERENCIA">Transferencia</option>';
    };

    const configurarBodegasLineaMovimiento = (fila) => {
        const tipo = document.getElementById('movimiento_tipo')?.value || '';
        const accion = fila.querySelector('.mov-linea-accion')?.value || '';
        const origen = fila.querySelector('.mov-linea-origen');
        const destino = fila.querySelector('.mov-linea-destino');
        origen.disabled = tipo === 'AJUSTE' && accion === 'INGRESO';
        destino.disabled = tipo === 'AJUSTE' && accion === 'EGRESO';
        if (tipo === 'AJUSTE' && accion === 'INGRESO') origen.value = '';
        if (tipo === 'AJUSTE' && accion === 'EGRESO') destino.value = '';
        if (tipo === 'TRANSFERENCIA') {
            origen.disabled = false;
            destino.disabled = false;
        }
        actualizarStockOrigen(fila);
    };

    const recalcularMovimiento = () => {
        let total = 0;
        document.querySelectorAll('#tablaLineasMovimiento tbody tr').forEach((fila) => {
            const cantidad = Number(fila.querySelector('.mov-linea-cantidad')?.value || 0);
            const costo = Number(fila.dataset.costo || 0);
            const subtotal = cantidad * costo;
            total += subtotal;
            const celda = fila.querySelector('.mov-linea-total');
            if (celda) celda.textContent = subtotal.toFixed(2);
        });
        const totalNodo = document.getElementById('movimiento_total');
        if (totalNodo) totalNodo.textContent = total.toFixed(2);
    };

    const agregarLineaMovimiento = (producto = {}) => {
        const cuerpo = document.querySelector('#tablaLineasMovimiento tbody');
        if (!cuerpo) return;
        indiceLineaMovimiento += 1;
        const tipo = document.getElementById('movimiento_tipo')?.value || 'AJUSTE_IN';
        const fila = document.createElement('tr');
        fila.dataset.indice = String(indiceLineaMovimiento);
        fila.dataset.producto = producto.inv_producto_id || '';
        fila.dataset.costo = producto.costo_promedio || 0;
        fila.dataset.pvp = producto.pvp || 0;
        fila.dataset.saldos = JSON.stringify(producto.saldos || []);
        fila.innerHTML = `
            <td><div class="input-group input-group-sm"><input class="form-control mov-linea-codigo" value="${escaparHtml(producto.inv_producto_codigo_principal || '')}"><button class="btn btn-secundario btn-buscar-linea-movimiento" type="button"><i class="bi bi-search"></i></button></div></td>
            <td><input class="form-control form-control-sm mov-linea-descripcion" value="${escaparHtml(producto.inv_producto_nombre || '')}" disabled></td>
            <td><input class="form-control form-control-sm text-end mov-linea-pvp" value="${Number(producto.pvp || 0).toFixed(2)}" disabled></td>
            <td><select class="form-control form-control-sm mov-linea-accion" ${tipo === 'TRANSFERENCIA' ? 'disabled' : ''}>${accionesMovimiento(tipo)}</select></td>
            <td><select class="form-control form-control-sm mov-linea-origen"><option value="">Origen</option>${opcionesBodegaMovimiento()}</select></td>
            <td class="text-center mov-linea-stock-origen text-muted">—</td>
            <td><select class="form-control form-control-sm mov-linea-destino"><option value="">Destino</option>${opcionesBodegaMovimiento()}</select></td>
            <td class="text-center"><input type="number" min="1" step="1" class="form-control form-control-sm text-center mov-linea-cantidad" value="1"></td>
            <td class="text-end mov-linea-total">0.00</td>
            <td><button type="button" class="btn btn-accion btn-eliminar-linea-movimiento"><i class="bi bi-trash3"></i></button></td>
        `;
        cuerpo.appendChild(fila);
        configurarBodegasLineaMovimiento(fila);
        recalcularMovimiento();
    };

    const actualizarStockOrigen = (fila) => {
        const tipo   = document.getElementById('movimiento_tipo')?.value || '';
        const accion = fila.querySelector('.mov-linea-accion')?.value || '';
        const celda  = fila.querySelector('.mov-linea-stock-origen');
        if (!celda) return;
        const necesita = tipo === 'TRANSFERENCIA' || (tipo === 'AJUSTE' && accion === 'EGRESO');
        if (!necesita) { celda.textContent = '—'; celda.classList.add('text-muted'); return; }
        const bodegaId = fila.querySelector('.mov-linea-origen')?.value || '';
        const saldos   = JSON.parse(fila.dataset.saldos || '[]');
        const saldo    = saldos.find((s) => String(s.inv_bodega_id) === String(bodegaId));
        celda.classList.remove('text-muted');
        celda.textContent = saldo ? String(Math.round(Number(saldo.inv_stock_cantidad_disponible || 0))) : '0';
    };

    const cargarProductoEnLineaMovimiento = (fila, producto) => {
        fila.dataset.producto = producto.inv_producto_id || '';
        fila.dataset.costo = producto.costo_promedio || 0;
        fila.dataset.pvp = producto.pvp || 0;
        fila.dataset.saldos = JSON.stringify(producto.saldos || []);
        fila.querySelector('.mov-linea-codigo').value = producto.inv_producto_codigo_principal || '';
        fila.querySelector('.mov-linea-descripcion').value = producto.inv_producto_nombre || '';
        fila.querySelector('.mov-linea-pvp').value = Number(producto.pvp || 0).toFixed(2);
        actualizarStockOrigen(fila);
        recalcularMovimiento();
    };

    const buscarProductosMovimiento = async (termino = '', codigo = '') => {
        const parametros = new URLSearchParams();
        if (termino) parametros.set('q', termino);
        if (codigo) parametros.set('codigo', codigo);
        const bodegaId = lineaProductoMovimientoActiva?.querySelector('.mov-linea-origen')?.value || '';
        if (bodegaId) parametros.set('bodega_id', bodegaId);
        const respuesta = await fetch(`${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/movimientos/productos?${parametros.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await respuesta.json();
        if (!json.ok) throw new Error(json.mensaje || 'No se pudo buscar productos.');
        return json.data?.productos || [];
    };

    const renderizarBusquedaMovimiento = (productos) => {
        const cuerpo = document.getElementById('tablaBuscarProductoMovimiento');
        if (!cuerpo) return;
        cuerpo.innerHTML = productos.length ? productos.map((producto, indice) => {
            const stock = (producto.saldos || []).map((saldo) => `${escaparHtml(saldo.inv_bodega_codigo)}: ${Math.round(Number(saldo.inv_stock_cantidad_disponible || 0))}`).join('<br>');
            const imagen = producto.imagen_principal_id ? `<img src="${window.location.origin}${document.body.dataset.baseUrl || ''}/inventario/productos/archivos/ver?archivo_id=${producto.imagen_principal_id}" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:5px;">` : '<i class="bi bi-image"></i>';
            return `<tr>
                <td>${imagen}</td>
                <td>${escaparHtml(producto.inv_producto_codigo_principal)}</td>
                <td>${escaparHtml(producto.inv_producto_nombre)}</td>
                <td>${escaparHtml(producto.inv_marca_nombre)}</td>
                <td class="text-end">${Number(producto.pvp || 0).toFixed(2)}</td>
                <td>${stock || '0.00'}</td>
                <td><button type="button" class="btn btn-accion btn-seleccionar-producto-movimiento" data-indice="${indice}"><i class="bi bi-check2"></i></button></td>
            </tr>`;
        }).join('') : '<tr><td colspan="7" class="text-muted">Sin productos.</td></tr>';
        window.INTESIS_PRODUCTOS_MOVIMIENTO = productos;
    };

    let _tipoMovimientoPendiente = 'AJUSTE';
    document.querySelectorAll('.btn-nuevo-movimiento').forEach((btn) => {
        btn.addEventListener('click', () => { _tipoMovimientoPendiente = btn.dataset.tipo || 'AJUSTE'; });
    });

    const modalMovimiento = document.getElementById('modalMovimientoInterno');
    if (modalMovimiento) {
        modalMovimiento.addEventListener('show.bs.modal', (evento) => {
            const tipo = evento.relatedTarget?.dataset.tipo || _tipoMovimientoPendiente || 'AJUSTE';
            _tipoMovimientoPendiente = tipo;
            document.getElementById('formularioMovimientoInterno').reset();
            document.getElementById('movimiento_tipo').value = tipo;
            document.getElementById('modalMovimientoTitulo').textContent = tipo === 'TRANSFERENCIA' ? 'Transferencia entre bodegas' : 'Ajuste de inventario';
            document.querySelector('#tablaLineasMovimiento tbody').innerHTML = '';
            indiceLineaMovimiento = 0;
            agregarLineaMovimiento();
        });
    }

    document.getElementById('btnAgregarLineaMovimiento')?.addEventListener('click', () => agregarLineaMovimiento());

    document.getElementById('formularioMovimientoInterno')?.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter') return;
        evento.preventDefault();
        if (evento.target.classList.contains('mov-linea-codigo')) {
            evento.target.blur();
        }
    });

    document.getElementById('tablaLineasMovimiento')?.addEventListener('click', async (evento) => {
        const fila = evento.target.closest('tr');
        if (!fila) return;
        if (evento.target.closest('.btn-eliminar-linea-movimiento')) {
            fila.remove();
            recalcularMovimiento();
            return;
        }
        if (evento.target.closest('.btn-buscar-linea-movimiento')) {
            lineaProductoMovimientoActiva = fila;
            const codigoBuscar = fila.querySelector('.mov-linea-codigo')?.value?.trim() || '';
            const inputBuscarModal = document.getElementById('buscar_producto_movimiento_texto');
            if (inputBuscarModal) inputBuscarModal.value = codigoBuscar;
            terminoPendienteBusquedaMovimiento = codigoBuscar;
            renderizarBusquedaMovimiento([]);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscarProductoMovimiento')).show();
        }
    });

    document.getElementById('tablaLineasMovimiento')?.addEventListener('change', (evento) => {
        const fila = evento.target.closest('tr');
        if (!fila) return;
        if (evento.target.classList.contains('mov-linea-origen') && fila.querySelector('.mov-linea-destino')?.value === evento.target.value) {
            fila.querySelector('.mov-linea-destino').value = '';
        }
        if (evento.target.classList.contains('mov-linea-destino') && fila.querySelector('.mov-linea-origen')?.value === evento.target.value) {
            fila.querySelector('.mov-linea-origen').value = '';
        }
        if (evento.target.classList.contains('mov-linea-accion')) {
            configurarBodegasLineaMovimiento(fila);
        }
        if (evento.target.classList.contains('mov-linea-origen') || evento.target.classList.contains('mov-linea-accion')) {
            actualizarStockOrigen(fila);
        }
        recalcularMovimiento();
    });

    document.getElementById('tablaLineasMovimiento')?.addEventListener('focusout', async (evento) => {
        if (!evento.target.classList.contains('mov-linea-codigo')) return;
        const fila = evento.target.closest('tr');
        const codigo = evento.target.value.trim();
        const inputBuscarFocusout = document.getElementById('buscar_producto_movimiento_texto');
        if (!codigo) {
            lineaProductoMovimientoActiva = fila;
            if (inputBuscarFocusout) inputBuscarFocusout.value = '';
            terminoPendienteBusquedaMovimiento = null;
            renderizarBusquedaMovimiento([]);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscarProductoMovimiento')).show();
            return;
        }
        try {
            const productos = await buscarProductosMovimiento('', codigo);
            if (productos.length === 1) {
                cargarProductoEnLineaMovimiento(fila, productos[0]);
            } else {
                lineaProductoMovimientoActiva = fila;
                if (inputBuscarFocusout) inputBuscarFocusout.value = codigo;
                terminoPendienteBusquedaMovimiento = null;
                renderizarBusquedaMovimiento(productos);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscarProductoMovimiento')).show();
            }
        } catch (error) {
            mostrarAlerta({ icono: 'error', titulo: 'No se pudo buscar', texto: error.message || 'Revise producto.' });
        }
    });

    document.getElementById('modalBuscarProductoMovimiento')?.addEventListener('shown.bs.modal', () => {
        const inputBuscar = document.getElementById('buscar_producto_movimiento_texto');
        if (inputBuscar) { inputBuscar.focus(); inputBuscar.select(); }
        setTimeout(() => { document.getElementById('btnBuscarProductoMovimiento')?.click();
            $("#buscar_producto_movimiento_texto").focus();
         }, 700);
    });

    document.getElementById('buscar_producto_movimiento_texto')?.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            document.getElementById('btnBuscarProductoMovimiento')?.click();
        }
    });

    document.getElementById('btnBuscarProductoMovimiento')?.addEventListener('click', async () => {
        try {
            renderizarBusquedaMovimiento(await buscarProductosMovimiento(document.getElementById('buscar_producto_movimiento_texto')?.value || ''));
        } catch (error) {
            mostrarAlerta({ icono: 'error', titulo: 'No se pudo buscar', texto: error.message || 'Revise producto.' });
        }
    });

    document.getElementById('tablaBuscarProductoMovimiento')?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.btn-seleccionar-producto-movimiento');
        if (!boton || !lineaProductoMovimientoActiva) return;
        cargarProductoEnLineaMovimiento(lineaProductoMovimientoActiva, (window.INTESIS_PRODUCTOS_MOVIMIENTO || [])[Number(boton.dataset.indice)] || {});
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscarProductoMovimiento')).hide();
    });

    document.getElementById('formularioMovimientoInterno')?.addEventListener('submit', (evento) => {
        const lineas = [];
        document.querySelectorAll('#tablaLineasMovimiento tbody tr').forEach((fila) => {
            lineas.push({
                producto_id: fila.dataset.producto || '',
                codigo: fila.querySelector('.mov-linea-codigo')?.value || '',
                accion: fila.querySelector('.mov-linea-accion')?.value || '',
                origen_id: fila.querySelector('.mov-linea-origen')?.value || '',
                destino_id: fila.querySelector('.mov-linea-destino')?.value || '',
                cantidad: fila.querySelector('.mov-linea-cantidad')?.value || '0',
                costo: fila.dataset.costo || '0',
                pvp: fila.dataset.pvp || '0',
                observacion: document.getElementById('movimiento_detalle')?.value || '',
            });
        });
        document.getElementById('movimiento_lineas_json').value = JSON.stringify(lineas);
        const tipoMovimiento = document.getElementById('movimiento_tipo')?.value || '';
        const lineasInvalidas = lineas.some((linea) => {
            if (!linea.producto_id || Number(linea.cantidad) <= 0) return true;
            if (tipoMovimiento === 'TRANSFERENCIA') return !linea.origen_id || !linea.destino_id || linea.origen_id === linea.destino_id;
            if (linea.accion === 'INGRESO') return !linea.destino_id;
            if (linea.accion === 'EGRESO') return !linea.origen_id;
            return true;
        });
        if (!document.getElementById('movimiento_detalle').value.trim() || !lineas.length || lineasInvalidas) {
            evento.preventDefault();
            mostrarAlerta({ icono: 'error', titulo: 'Datos incompletos', texto: 'Revise detalle, producto, accion, bodega y cantidad.' });
        }
    });

    const modalEditarMovimiento = document.getElementById('modalEditarMovimiento');
    if (modalEditarMovimiento) {
        modalEditarMovimiento.addEventListener('show.bs.modal', (evento) => {
            const boton = evento.relatedTarget;
            document.getElementById('editar_movimiento_id').value = boton?.dataset.id || '';
            document.getElementById('editar_movimiento_detalle').value = boton?.dataset.detalle || '';
        });
    }

    // ─── FILTROS Y DATATABLES: MOVIMIENTOS INTERNOS ───────────────────────────

    if (window.jQuery && jQuery.fn.DataTable && document.getElementById('tablaMovimientosLista')) {
        const idiomaTablaMovimiento = {
            search: 'Filtrar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Sin registros',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron resultados',
            paginate: { first: 'Primero', last: 'Ultimo', next: 'Siguiente', previous: 'Anterior' },
        };

        /*
         * Funcion de filtrado por rango de fechas y estado.
         * Se registra una vez y usa el ID de la tabla para no afectar otras tablas.
         */
        if (!window.INTESIS_MOV_FILTRO_REGISTRADO) {
            window.INTESIS_MOV_FILTRO_REGISTRADO = true;
            jQuery.fn.dataTable.ext.search.push(function (settings, _data, dataIndex) {
                const tableId = settings.nTable?.id;
                if (tableId !== 'tablaMovimientosLista' && tableId !== 'tablaTransferenciasLista') {
                    return true;
                }
                const fila = settings.aoData[dataIndex]?.nTr;
                const fechaFila = fila ? (fila.dataset.fecha || '') : '';
                const desde = document.getElementById('filtroMovDesde')?.value || '';
                const hasta = document.getElementById('filtroMovHasta')?.value || '';
                if (desde && fechaFila < desde) { return false; }
                if (hasta && fechaFila > hasta) { return false; }
                if (tableId === 'tablaMovimientosLista') {
                    const estadoFiltro = document.getElementById('filtroMovEstado')?.value || '';
                    if (estadoFiltro) {
                        const estadoFila = fila ? (fila.dataset.estado || '') : '';
                        if (estadoFila !== estadoFiltro) { return false; }
                    }
                }
                return true;
            });
        }

        jQuery('#tablaMovimientosLista').DataTable({
            language: idiomaTablaMovimiento,
            pageLength: 25,
            order: [[1, 'desc']],
        });
        jQuery('#tablaTransferenciasLista').DataTable({
            language: idiomaTablaMovimiento,
            pageLength: 25,
            order: [[1, 'asc']],
        });

        // Valores predeterminados: ultimos 30 dias y estado PENDIENTE
        const hoyMov = new Date();
        const hace30Mov = new Date(hoyMov);
        hace30Mov.setDate(hace30Mov.getDate() - 30);
        const isoFecha = (d) => d.toISOString().slice(0, 10);
        const elDesde = document.getElementById('filtroMovDesde');
        const elHasta = document.getElementById('filtroMovHasta');
        const elEstado = document.getElementById('filtroMovEstado');
        if (elDesde && !elDesde.value) { elDesde.value = isoFecha(hace30Mov); }
        if (elHasta && !elHasta.value) { elHasta.value = isoFecha(hoyMov); }
        if (elEstado && !elEstado.value) { elEstado.value = 'PENDIENTE'; }

        // Redibujar con filtros iniciales
        jQuery('#tablaMovimientosLista').DataTable().draw();
        jQuery('#tablaTransferenciasLista').DataTable().draw();

        // Listeners de filtros
        ['filtroMovDesde', 'filtroMovHasta'].forEach((id) => {
            document.getElementById(id)?.addEventListener('change', () => {
                jQuery('#tablaMovimientosLista').DataTable().draw();
                jQuery('#tablaTransferenciasLista').DataTable().draw();
            });
        });
        document.getElementById('filtroMovEstado')?.addEventListener('change', () => {
            jQuery('#tablaMovimientosLista').DataTable().draw();
        });
    }

    // ─── MODAL VER DETALLE DE MOVIMIENTO ──────────────────────────────────────

    const modalDetalleMovimiento = document.getElementById('modalDetalleMovimiento');

    const etiquetaEstado = (codigo, nombre) => {
        const clase = `estado-${String(codigo).toLowerCase()}`;
        return `<span class="badge estado-badge ${escaparHtml(clase)}">${escaparHtml(nombre)}</span>`;
    };

    const renderizarDetalleMovimiento = (datos) => {
        const cab = datos.cabecera;
        const lineas = datos.lineas || [];
        const puedeAnularProcesado = Boolean(datos.puede_anular_procesado);
        const puedeRecibir = Boolean(datos.puede_recibir);
        const estado = String(cab.sis_estado_codigo || '');
        const permisos = window.INTESIS_MOVIMIENTO_PERMISOS || {};
        const appUrl = window.INTESIS_APP_URL || '';

        const totalMov = lineas.reduce((s, l) => s + Number(l.inv_movimientos_detalle_total || 0), 0);


        // ── Accion badge ──
        const iconoAccion = (tipo) => {
            if (tipo === 'INGRESO')       return '<span class="badge bg-success-subtle text-success"><i class="bi bi-plus-lg"></i> Ingreso</span>';
            if (tipo === 'EGRESO')        return '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-dash-lg"></i> Egreso</span>';
            if (tipo === 'TRANSFERENCIA') return '<span class="badge bg-primary-subtle text-primary"><i class="bi bi-arrow-left-right"></i> Transfer.</span>';
            return `<span class="badge bg-secondary-subtle text-secondary">${escaparHtml(tipo)}</span>`;
        };

        // ── Filas de productos ──
        const filas = lineas.map((l) => `
            <tr>
                <td><code class="text-body">${escaparHtml(l.producto_codigo || '')}</code></td>
                <td>${escaparHtml(l.producto_nombre || '')}</td>
                <td>${iconoAccion(l.inv_movimientos_detalle_tipo)}</td>
                <td class="text-muted small">${escaparHtml(l.bodega_origen || '—')}</td>
                <td class="text-muted small">${escaparHtml(l.bodega_destino || '—')}</td>
                <td class="text-end">${Number(l.inv_movimientos_detalle_cantidad || 0).toFixed(2)}</td>
                <td class="text-end text-muted small">${Number(l.inv_movimientos_detalle_costo || 0).toFixed(2)}</td>
                <td class="text-end fw-semibold">${Number(l.inv_movimientos_detalle_total || 0).toFixed(2)}</td>
            </tr>
        `).join('');

        // ── Layout tipo documento formal ──
        const filasDetalle = `
        <div class="doc-contenedor">

            <!-- FILA 1: tipo + numero + estado -->
            <div class="doc-cabecera">
                <div class="doc-cabecera-izq">
                    <div class="doc-tipo-label">MOVIMIENTO DE INVENTARIO</div>
                    <div class="doc-tipo-valor">${escaparHtml(cab.sis_tipo_documento_nombre || '')}</div>
                </div>
                <div class="doc-cabecera-der">
                    <div class="doc-etiqueta">Numero</div>
                    <div class="doc-numero">${escaparHtml(cab.inv_movimientos_numero || '')}</div>
                    <div class="mt-1">${etiquetaEstado(cab.sis_estado_codigo, cab.sis_estado_nombre)}</div>
                </div>
            </div>

            <!-- FILAS DE CAMPOS: tabla con rowspan en Observacion -->
            <table class="doc-tabla-campos">
                <tr>
                    <td class="doc-celda-t" style="width:13%">
                        <div class="doc-etiqueta">Fecha</div>
                        <div class="doc-valor">${escaparHtml(String(cab.inv_movimientos_fecha || ''))}</div>
                    </td>
                    <td class="doc-celda-t" style="width:22%">
                        <div class="doc-etiqueta">Usuario crea</div>
                        <div class="doc-valor">${escaparHtml(cab.usuario_nombre || '')}</div>
                    </td>
                    <td class="doc-celda-t" style="width:22%">
                        <div class="doc-etiqueta">Registrado</div>
                        <div class="doc-valor">${escaparHtml(String(cab.fecha_crea || '').slice(0, 16).replace('T', ' '))}</div>
                    </td>
                    <td class="doc-celda-t doc-celda-obs" rowspan="2">
                        <div class="doc-etiqueta">Detalle / Observacion</div>
                        <div class="doc-valor mt-1">${escaparHtml(cab.inv_movimientos_observacion || '—')}</div>
                    </td>
                </tr>
                <tr>
                    <td class="doc-celda-t">
                        <div class="doc-etiqueta">Bodega origen</div>
                        <div class="doc-valor">${escaparHtml(cab.bodega_origen || '—')}</div>
                    </td>
                    <td class="doc-celda-t" colspan="2">
                        <div class="doc-etiqueta">Bodega destino</div>
                        <div class="doc-valor">${escaparHtml(cab.bodega_destino || '—')}</div>
                    </td>
                </tr>
            </table>

        </div>

        <!-- LINEAS DEL MOVIMIENTO -->
        <div class="doc-seccion-titulo mt-3 mb-2">
            <i class="bi bi-list-ul me-1"></i> Lineas del movimiento
        </div>
        `;

        const tablaLineas = `
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="doc-thead">
                        <tr>
                            <th>Codigo</th><th>Producto</th><th>Accion</th>
                            <th>Origen</th><th>Destino</th>
                            <th class="text-end">Cantidad</th><th class="text-end">Costo</th><th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>${filas || '<tr><td colspan="8" class="text-muted text-center py-3">Sin lineas registradas.</td></tr>'}</tbody>
                    ${lineas.length ? `<tfoot><tr class="doc-tfoot"><td colspan="7" class="text-end fw-semibold">Total general</td><td class="text-end fw-bold">${totalMov.toFixed(2)}</td></tr></tfoot>` : ''}
                </table>
            </div>
        `;

        document.getElementById('detalleMovimientoTitulo').textContent = cab.inv_movimientos_numero || 'Detalle';
        document.getElementById('detalleMovimientoBody').innerHTML = filasDetalle + tablaLineas;

        // Botones del footer segun estado y permisos
        const movId = String(cab.inv_movimientos_id);

        const botonAccion = (icono, texto, claseBtn, accion) =>
            `<button type="button" class="btn ${claseBtn} btn-detalle-accion" data-accion="${accion}" data-id="${escaparHtml(movId)}"><i class="bi ${icono}"></i> ${texto}</button>`;

        let botonesHtml = '';

        if (estado === 'PENDIENTE') {
            if (permisos.editar) {
                botonesHtml += `<button type="button" class="btn btn-secundario btn-detalle-editar" data-id="${escaparHtml(movId)}" data-detalle="${escaparHtml(cab.inv_movimientos_observacion || '')}" data-bs-target="#modalEditarMovimiento" data-bs-toggle="modal"><i class="bi bi-pencil-square"></i> Editar obs.</button>`;
            }
            if (permisos.aprobar) {
                botonesHtml += botonAccion('bi-check2-square', 'Aprobar', 'btn-activar', 'aprobar');
            }
            if (permisos.anular) {
                botonesHtml += botonAccion('bi-x-octagon', 'Anular', 'btn-inactivar', 'anular');
            }
        } else if (estado === 'EN_TRANSITO') {
            if (permisos.recibir && puedeRecibir) {
                botonesHtml += botonAccion('bi-box-arrow-in-down', 'Recibir', 'btn-activar', 'recibir');
            }
            if (permisos.anular) {
                botonesHtml += botonAccion('bi-x-octagon', 'Anular', 'btn-inactivar', 'anular');
            }
        } else if ((estado === 'PROCESADO' || estado === 'RECIBIDO') && permisos.anularProcesado && puedeAnularProcesado) {
            botonesHtml += botonAccion('bi-x-octagon-fill', 'Anular (revertir stock)', 'btn-eliminar', 'anular-procesado');
        }

        const footer = document.getElementById('detalleMovimientoFooter');
        footer.innerHTML = botonesHtml + '<button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button>';

        // Evento editar observacion desde detalle
        footer.querySelector('.btn-detalle-editar')?.addEventListener('click', () => {
            document.getElementById('editar_movimiento_id').value = movId;
            document.getElementById('editar_movimiento_detalle').value = cab.inv_movimientos_observacion || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarMovimiento')).show();
        });

        // Eventos de acciones con confirmacion SweetAlert
        footer.querySelectorAll('.btn-detalle-accion').forEach((boton) => {
            boton.addEventListener('click', async () => {
                const accion = boton.dataset.accion;
                const id = boton.dataset.id;

                const mensajes = {
                    aprobar: { titulo: '¿Aprobar movimiento?', texto: 'Se procesara el stock. Esta accion no se puede revertir facilmente.', icono: 'question' },
                    anular: { titulo: '¿Anular movimiento?', texto: 'El movimiento quedara anulado.', icono: 'warning' },
                    recibir: { titulo: '¿Confirmar recepcion?', texto: 'Se registrara el ingreso en bodega destino.', icono: 'question' },
                    'anular-procesado': { titulo: '¿Anular y revertir stock?', texto: 'Se crearan lineas de kardex de reversa. Solo es posible el mismo dia.', icono: 'warning' },
                };

                const msg = mensajes[accion] || { titulo: '¿Continuar?', texto: '', icono: 'question' };

                if (window.Swal) {
                    const confirmacion = await Swal.fire({
                        icon: msg.icono,
                        title: msg.titulo,
                        text: msg.texto,
                        showCancelButton: true,
                        confirmButtonText: 'Si, continuar',
                        cancelButtonText: 'Cancelar',
                    });
                    if (!confirmacion.isConfirmed) { return; }
                }

                try {
                    boton.disabled = true;
                    const formData = new FormData();
                    formData.append('movimiento_id', id);
                    const url = `${appUrl}/inventario/movimientos/${accion}`;
                    const respuesta = await fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }, redirect: 'manual' });
                    // La accion redirige tras ejecutar; cerramos modal y recargamos
                    bootstrap.Modal.getOrCreateInstance(modalDetalleMovimiento).hide();
                    window.location.href = `${appUrl}/inventario/movimientos`;
                } catch (_e) {
                    boton.disabled = false;
                    if (window.Swal) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo completar la accion. Intente nuevamente.' }); }
                }
            });
        });
    };

    if (modalDetalleMovimiento) {
        modalDetalleMovimiento.addEventListener('show.bs.modal', async (evento) => {
            const boton = evento.relatedTarget;
            const movimientoId = boton?.dataset.id;
            if (!movimientoId) { return; }

            document.getElementById('detalleMovimientoTitulo').textContent = 'Detalle del movimiento';
            document.getElementById('detalleMovimientoBody').innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-hourglass-split"></i> Cargando...</div>';
            document.getElementById('detalleMovimientoFooter').innerHTML = '<button type="button" class="btn btn-secundario" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button>';

            try {
                const appUrl = window.INTESIS_APP_URL || '';
                const respuesta = await fetch(`${appUrl}/inventario/movimientos/detalle?movimiento_id=${encodeURIComponent(movimientoId)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await respuesta.json();
                if (json.ok) {
                    renderizarDetalleMovimiento(json.data || {});
                } else {
                    document.getElementById('detalleMovimientoBody').innerHTML = `<div class="alert alert-danger">${escaparHtml(json.mensaje || 'Error al cargar el detalle.')}</div>`;
                }
            } catch (_e) {
                document.getElementById('detalleMovimientoBody').innerHTML = '<div class="alert alert-danger">Error de conexion al cargar el detalle.</div>';
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────

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
