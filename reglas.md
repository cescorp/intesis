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
