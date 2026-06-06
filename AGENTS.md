- Guardar `log_errores.log` en la raiz del proyecto.
- Comentar con `*` en modo cuadro de forma tecnica en funciones.
- En base de datos, siempre agregar comentarios en MAYUSCULAS sencillos y entendibles.


REGLAS
1.	CRUDS
2.	Todo CRUD de tablas que tengan sis_empresa_id debe filtrar automáticamente por la empresa activa del usuario.
3.	El SUPERUSUARIO puede seleccionar empresa mediante filtro visible cuando el CRUD tenga alcance multiempresa.
4.	Usuarios normales nunca pueden cambiar sis_empresa_id desde formularios.
5.	Usuarios normales siempre trabajan dentro de una empresa activa.
6.	Todas las consultas de usuarios normales sobre tablas con empresa deben incluir sis_empresa_id.
7.	Usar DataTables con filtro en los listados CRUD.
8.	Usar SweetAlert para errores, confirmaciones, éxito e informativos.
9.	Las validaciones no deben cerrar modales ni borrar información.
10.	Si un modal abre otro modal, el modal padre debe quedar en segundo plano sin perder datos.
11.	Al cerrar el modal hijo, debe volver al modal padre con los datos intactos.
12.	No cerrar todos los modales con una sola acción salvo confirmación explícita.
13.	Botones y acciones se muestran según permisos del perfil.
14.	Validar permisos en backend antes de ejecutar cualquier acción.
15.	No confiar solo en ocultar botones del frontend.
16.	Evitar pantallas CRUD largas o con inputs innecesarios.
17.	Preferir formularios compactos y ordenados.
18.	Evitar textos largos en cabeceras y formularios.
19.	Usar placeholders, tooltips o textos pequeños solo cuando ayuden a evitar errores.
20.	Acciones en tablas con iconos funcionales.
21.	No eliminar perfiles con usuarios asignados.
22.	En CRUD Perfiles, SUPERUSUARIO no se edita, inactiva ni elimina.
23.	En permisos de perfiles:
o	Columna izquierda: menús.
o	Columna derecha: acciones del menú seleccionado y de sus hijos.
o	Checkbox del menú marca permiso.
o	Botón/icono carga acciones.
o	Acordeón despliega/recoge menús principales.
o	Menús principales inician recogidos.
o	Checkbox “Todas” por bloque de acciones.
24.	Usuarios y Empresas
25.	SUPERUSUARIO global:
o	Puede ver todas las empresas.
o	Puede crear empresas.
o	Puede activar/inactivar empresas.
o	Puede asignar permisos globales.
o	No pertenece obligatoriamente a una sola empresa.
26.	Usuarios normales:
o	Siempre trabajan dentro de una empresa activa.
o	Todas las consultas deben incluir sis_empresa_id.
27.	Un usuario global puede asignarse a varias empresas con perfil distinto.
28.	Modelo recomendado para usuarios multiempresa:
sis_usuarios - sis_usuarios_id - sis_usuarios_nombre - sis_usuarios_correo - sis_usuarios_password - sis_estado_id sis_usuario_empresa - sis_usuario_empresa_id - sis_usuarios_id - sis_empresa_id - sis_perfil_id - sis_usuario_empresa_predeterminada - sis_estado_id
29.	sis_usuarios no debe depender como relación principal única de sis_empresa_id ni sis_perfil_id.
30.	En login:
o	Si el usuario tiene una sola empresa activa, entra directo.
o	Si tiene varias empresas activas, debe seleccionar empresa.
o	Si tiene empresa predeterminada, puede usarse como sugerencia o entrada automática según regla futura.
31.	Base de Datos
32.	Usar PostgreSQL.
33.	Tablas principales deben usar sis_estado_id cuando aplique.
34.	Estados centralizados en sis_estado.
35.	Mensajes, alertas, errores, informativos y confirmaciones configurables en sis_mensaje_errores.
36.	Comentarios de base de datos en MAYÚSCULAS, simples y entendibles.
37.	Toda migración debe ser limpia.
38.	Usar comentarios técnicos en SQL cuando se creen estructuras.
39.	Nunca construir SQL concatenando variables del usuario.
40.	Usar consultas preparadas con PDO.
41.	Empresas nuevas crean perfiles base automáticamente.
42.	Perfiles base:
o	SUPERUSUARIO
o	GERENCIA
o	CONTADOR
o	GERENTE_VENTAS
o	VENDEDOR
o	COMPRAS
o	BODEGUERO
43.	SUPERUSUARIO recibe todos los permisos existentes.
44.	GERENCIA y CONTADOR reciben permisos básicos de vista.
45.	No dejar empresas activas sin perfiles activos.
46.	Tablas con sis_empresa_id deben tener filtros por empresa en consultas de usuarios normales.
47.	BackEnd
48.	PHP puro con estructura MVC simple.
49.	Rutas limpias.
50.	Usar nombres en español para controladores, modelos, métodos propios y variables del negocio.
51.	Se permite inglés solo para funciones nativas, librerías, clases externas o convenciones técnicas.
52.	Funciones con comentario técnico en cuadro usando *.
53.	Logs centralizados en log_errores.log en la raíz del proyecto.
54.	Validación siempre en servidor, aunque exista validación cliente.
55.	Passwords con hash fuerte Argon2id.
56.	Las contraseñas nunca se guardan ni se muestran en texto plano.
57.	Configuración importante en .env.
58.	APP_NOMBRE configurable desde .env.
59.	Si falta un mensaje en sis_mensaje_errores, mostrar código pendiente y registrarlo en log.
60.	No revertir cambios existentes sin instrucción explícita.
61.	Usar transacciones cuando se creen o actualicen varias tablas relacionadas.
62.	Antes de modificar archivos existentes, revisar su contenido y mantener compatibilidad con rutas, nombres y funciones ya creadas.
63.	Proteger rutas si no hay sesión activa.
64.	Regenerar sesión después del login.
65.	Cerrar sesión debe destruir sesión y token.
66.	Toda acción POST, PUT, PATCH o DELETE debe validar CSRF.
67.	Validar permisos en backend antes de ejecutar cualquier acción.
68.	Frontend
69.	AdminLTE 4.
70.	Bootstrap Icons.
71.	SweetAlert2.
72.	DataTables.
73.	Diseño ERP profesional:
o	formularios compactos;
o	tarjetas limpias;
o	separación visual clara;
o	botones con iconos;
o	colores sobrios;
o	buen espaciado;
o	sin pantallas sobrecargadas.
74.	Evitar depender de internet en producción/local final.
75.	Login con imagen atractiva a la izquierda.
76.	Rutas limpias visibles en navegador.
77.	Encabezado compacto en barra superior, sin franja extra cuando se pueda.
78.	Breadcrumb en barra superior tipo INTESIS > Sistema > Usuarios.
79.	No usar textos descriptivos largos en cabeceras CRUD.
80.	Inputs compactos, ordenados y sin amontonamiento vertical.
81.	Botones con iconos.
82.	Menús con sangría hasta 3 niveles.
83.	Todas las salidas HTML deben escaparse con htmlspecialchars.
84.	No confiar en ocultar botones como única seguridad.
85.	Estructura de Proyecto Recomendada
/app /Controladores /Modelos /Vistas /Nucleo /Ayudantes /Servicios /configuracion /base_datos /migraciones /semillas /publico /assets /css /js /imagenes index.php /rutas /almacenamiento /logs /cache /archivos .env .env.example README.md
86.	Idioma y Nombres
87.	Usar español en todo lo propio del proyecto:
o	carpetas;
o	clases;
o	métodos;
o	variables;
o	comentarios;
o	vistas;
o	nombres de módulos.
88.	Mantener en inglés solo convenciones técnicas estándar:
o	assets
o	css
o	js
o	vendor
o	composer.json
o	.env
o	README.md
o	index.php
89.	Directorios propios del sistema deben estar en español cuando sea posible.
90.	Funciones propias necesarias deben estar en español.
91.	Comentarios técnicos deben usar formato de cuadro con *
