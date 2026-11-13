/**
 * Subida de archivos en el navegador, compartida por todos los modulos (Facturas, Archivos...).
 * Contraparte de AlmacenArchivosServicio en el servidor (ver reglas.md, regla 5).
 *
 * Se usa XMLHttpRequest y no fetch() porque fetch no informa el avance de la subida,
 * y cada archivo debe mostrar su % ("Procesando 64%"). Los archivos se suben de uno en
 * uno (cola) para no saturar el servidor con subidas grandes en paralelo.
 */
window.IntesisSubida = (function () {
    'use strict';

    function esc(texto) {
        return String(texto ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function tamanoLegible(bytes) {
        const b = Number(bytes) || 0;
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        return Math.max(1, Math.round(b / 1024)) + ' KB';
    }

    /** Fila de progreso dentro de `contenedor`; devuelve funciones para actualizarla. */
    function crearFilaCola(contenedor, nombre) {
        const fila = document.createElement('div');
        fila.className = 'subida-cola';
        fila.innerHTML = `
            <div class="d-flex justify-content-between gap-2">
                <span class="text-truncate">${esc(nombre)}</span>
                <span class="subida-estado text-muted">En espera</span>
            </div>
            <div class="progress mt-1"><div class="progress-bar"></div></div>`;
        contenedor.appendChild(fila);
        return {
            avance(pct) {
                fila.querySelector('.progress-bar').style.width = pct + '%';
                fila.querySelector('.subida-estado').textContent = pct < 100 ? `Procesando ${pct}%` : 'Guardando...';
            },
            error(mensaje) {
                fila.querySelector('.progress')?.remove();
                const estado = fila.querySelector('.subida-estado');
                estado.className = 'subida-estado text-danger';
                estado.textContent = 'No se subió';
                const detalle = document.createElement('div');
                detalle.className = 'text-danger small';
                detalle.innerHTML = `${esc(mensaje)} <button type="button" class="btn btn-link btn-sm p-0 align-baseline">Quitar</button>`;
                detalle.querySelector('button').addEventListener('click', () => fila.remove());
                fila.appendChild(detalle);
            },
            quitar() { fila.remove(); },
        };
    }

    /**
     * Validacion previa (el servidor vuelve a validar). Devuelve el mensaje de error o null.
     * reglas: { maxMb, mimes: [...] | null, extensionesBloqueadas: [...] }
     */
    function validar(archivo, reglas) {
        if (reglas.mimes && !reglas.mimes.includes(archivo.type)) {
            return 'Tipo no permitido. Solo PDF e imágenes (JPG, PNG, WEBP, GIF).';
        }
        const extension = (archivo.name.split('.').length > 1 ? archivo.name.split('.').pop() : '').toLowerCase();
        if ((reglas.extensionesBloqueadas || []).includes(extension)) {
            return 'Por seguridad no se permiten archivos ejecutables o scripts.';
        }
        if (archivo.size > reglas.maxMb * 1048576) {
            return `Supera el máximo de ${reglas.maxMb} MB.`;
        }
        return null;
    }

    let cola = Promise.resolve();

    /**
     * Valida y encola la subida de cada archivo. Por cada archivo subido con exito llama a
     * alSubir(respuestaJson). opciones: { url, campos: {...}, contenedorCola, reglas, alSubir }
     */
    function subirArchivos(archivos, opciones) {
        [...archivos].forEach(archivo => {
            const fila = crearFilaCola(opciones.contenedorCola, archivo.name);
            const problema = validar(archivo, opciones.reglas);
            if (problema) { fila.error(problema); return; }
            cola = cola.then(() => subirUno(archivo, fila, opciones));
        });
        return cola;
    }

    function subirUno(archivo, fila, opciones) {
        return new Promise((resolver) => {
            const datos = new FormData();
            Object.entries(opciones.campos || {}).forEach(([k, v]) => datos.append(k, v));
            datos.append('archivo', archivo);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', opciones.url);
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) fila.avance(Math.round(e.loaded * 100 / e.total)); };
            xhr.onload = () => {
                let json = null;
                try { json = JSON.parse(xhr.responseText); } catch { /* respuesta no JSON: error del servidor */ }
                if (json?.ok) {
                    fila.quitar();
                    opciones.alSubir?.(json);
                } else {
                    fila.error(json?.mensaje || 'El servidor rechazó el archivo (puede superar el límite de subida).');
                }
                resolver();
            };
            xhr.onerror = () => { fila.error('Sin conexión con el servidor.'); resolver(); };
            xhr.send(datos);
        });
    }

    /** Conecta una zona (label con input file dentro) para soltar o seleccionar archivos. */
    function conectarZona(zona, input, alElegir) {
        input.addEventListener('change', () => { alElegir(input.files); input.value = ''; });
        ['dragenter', 'dragover'].forEach(ev => zona.addEventListener(ev, (e) => { e.preventDefault(); zona.classList.add('arrastrando'); }));
        ['dragleave', 'drop'].forEach(ev => zona.addEventListener(ev, (e) => { e.preventDefault(); zona.classList.remove('arrastrando'); }));
        zona.addEventListener('drop', (e) => alElegir(e.dataTransfer.files));
    }

    return { esc, tamanoLegible, subirArchivos, conectarZona };
}());
