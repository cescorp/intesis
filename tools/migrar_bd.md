# Migración de BD a otro computador

## PC ORIGEN — Exportar

```bat
REM Abre CMD en el PC actual y ejecuta:

set PGPASSWORD=276241Sc
"C:\Program Files\PostgreSQL\17\bin\pg_dump.exe" ^
  -U postgres ^
  -d intesis ^
  -F c ^
  -f "C:\intesis_backup.dump"
```

Eso genera `C:\intesis_backup.dump` (~formato comprimido).

---

## PC DESTINO — Preparar e importar

### 1. Instalar PostgreSQL 17 (misma versión)

### 2. Crear la BD vacía

```bat
set PGPASSWORD=tu_clave_postgres_destino
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -c "CREATE DATABASE intesis;"
```

### 3. Importar el dump

```bat
set PGPASSWORD=tu_clave_postgres_destino
"C:\Program Files\PostgreSQL\17\bin\pg_restore.exe" ^
  -U postgres ^
  -d intesis ^
  -F c ^
  "C:\intesis_backup.dump"
```

### 4. Copiar el proyecto

Copiar toda la carpeta `C:\xampp\htdocs\intesis\` al nuevo PC.

### 5. Actualizar .env

Editar `intesis\.env` si cambia el password de postgres:

```
DB_PASSWORD=nueva_clave_postgres
APP_RAIZ=C:/xampp/htdocs/intesis
```

---

## Alternativa: dump solo estructura + catálogo (sin datos operativos)

Si quieres llevar solo el esquema más los catálogos (para empezar limpio en el otro PC):

```bat
set PGPASSWORD=276241Sc

REM 1. Solo estructura (sin datos)
"C:\Program Files\PostgreSQL\17\bin\pg_dump.exe" -U postgres -d intesis -F c --schema-only -f "C:\intesis_schema.dump"

REM 2. Solo datos de tablas catálogo
"C:\Program Files\PostgreSQL\17\bin\pg_dump.exe" -U postgres -d intesis -F c --data-only ^
  -t sis_estado ^
  -t sis_tipo_documento ^
  -t sis_mensaje_errores ^
  -t sis_menu ^
  -t sis_modulo ^
  -t sis_iva ^
  -t sis_plan ^
  -t sis_plan_modulo ^
  -t ven_forma_pago ^
  -f "C:\intesis_catalogo.dump"
```

Luego en el destino:

```bat
"C:\Program Files\PostgreSQL\17\bin\pg_restore.exe" -U postgres -d intesis -F c "C:\intesis_schema.dump"
"C:\Program Files\PostgreSQL\17\bin\pg_restore.exe" -U postgres -d intesis -F c "C:\intesis_catalogo.dump"

REM Luego correr el reset para crear admin + empresa de prueba:
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -d intesis -f "C:\xampp\htdocs\intesis\tools\reset_bd.sql"
```
