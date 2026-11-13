# Reglas de trabajo — INTESIS

Reglas obligatorias para cualquier cambio en el proyecto (humano o IA).

## 1. Bitácora
Al terminar cada tarea, anotarla en [bitacora.md](bitacora.md): fecha, qué se hizo, archivos/migraciones afectadas y commit.

## 2. Comentarios en el código
Comentar el código pensando en quien le dará soporte: **por qué** existe una regla o decisión, no qué hace cada línea.
- Sí: `// Credito no se combina en Mixto: genera CXC por el total del documento`
- No: comentarios narrativos tipo "Ahora vamos a recorrer las filas…" o "Agregué esta función para…".

## 3. Diseño web
Toda pantalla o modal nuevo o rediseñado usa la skill de diseño web (`frontend-design`) y respeta el estilo existente del sistema (clases `modal-intesis`, `btn-intesis`, `formulario-compacto`, etc.).

## 4. Sin parches
Corregir la causa raíz, no el síntoma. Si una función compartida falla, se arregla en la función, no en cada lugar que la llama. Nada de valores quemados ni condiciones especiales para "que funcione".

## 5. Adjuntos y archivos
Todo archivo que suba un usuario se guarda con `AlmacenArchivosServicio::guardar()` (`src/Servicios/AlmacenArchivosServicio.php`) y se registra en `sis_archivos` (entidad = `tabla` + `id_padre`). No se escribe `move_uploaded_file` ni SQL de `sis_archivos` en otros lugares.
- Ubicación central: `almacenamiento/archivos/adjuntos/empresa_{id}/{tabla}/{año}/{mes}/{código-único}.{ext}`.
- Límite por archivo: cada módulo lo pasa por parámetro; el máximo absoluto es **80 MB** (`AlmacenArchivosServicio::MAX_MB`).
- El tipo se valida por el contenido real del archivo, no por la extensión. Cada módulo elige `TIPOS_PDF_E_IMAGENES` o `CUALQUIER_TIPO` (todo excepto ejecutables y scripts).
- En el navegador la subida se hace con `publico/js/subida-archivos.js` (`IntesisSubida`): cola, % por archivo y validación previa.
- Eliminar es lógico (`sis_archivos_estado = 0`); el archivo físico se conserva.
- Excepción histórica: la galería de productos sigue en `almacenamiento/archivos/productos/` con su propio controlador.
