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

### 6. Verificar migraciones pendientes

Cada vez que se actualice el código en el PC destino (copia o `git pull`):

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\intesis\tools\migraciones.php
```

- `OK: la base de datos esta al dia` → nada que hacer.
- `FALTAN N migraciones: ...` → lista los archivos de `migrations/` que esa BD no tiene. Para ejecutarlas en orden:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\intesis\tools\migraciones.php --aplicar
```

Lo aplicado queda registrado en la tabla `sis_migracion` (viaja dentro del dump). Si una falla, se detiene ahí y no ejecuta las siguientes.

> `--marcar-todas` registra todo como aplicado **sin ejecutar nada**. Úsalo solo una vez en una BD que sabes que ya está al día pero aún no tiene `sis_migracion` (por ejemplo, restaurada de un dump anterior al 2026-11-12). Nunca lo uses para "silenciar" el aviso.

### 7. Cambiar la clave de un usuario (si se olvidó)

En PowerShell, desde `C:\xampp\htdocs\intesis` (la clave se pide oculta y no queda en el historial):

```powershell
$s = Read-Host "Nueva clave" -AsSecureString
$env:NUEVA_CLAVE = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))
C:\xampp\php\php.exe tools\cambiar_clave.php usuario@dominio.com
Remove-Item Env:NUEVA_CLAVE
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
  -t sis_migracion ^
  -f "C:\intesis_catalogo.dump"
```

Luego en el destino:

```bat
"C:\Program Files\PostgreSQL\17\bin\pg_restore.exe" -U postgres -d intesis -F c "C:\intesis_schema.dump"
"C:\Program Files\PostgreSQL\17\bin\pg_restore.exe" -U postgres -d intesis -F c "C:\intesis_catalogo.dump"

REM Luego correr el reset para crear admin + empresa de prueba:
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -d intesis -f "C:\xampp\htdocs\intesis\tools\reset_bd.sql"
```
