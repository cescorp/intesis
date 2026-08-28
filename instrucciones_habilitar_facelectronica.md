# Instrucciones: habilitar Facturación Electrónica SRI

Pasos para dejar una instalación de INTESIS lista para firmar y enviar comprobantes al SRI (ambiente de Pruebas o Producción). Sigue el orden — cada paso depende del anterior.

## 1. Java JRE/JDK 8+ instalado y en el PATH

La firma del XML se hace invocando `sri2.jar` vía `java -jar` (`src/Servicios/SriFirmadorServicio.php`).

1. Instalar un JRE (recomendado: Eclipse Temurin 17):
   ```
   winget install --id EclipseAdoptium.Temurin.17.JRE -e --accept-package-agreements --accept-source-agreements
   ```
2. Verificar que quedó en el **PATH del sistema** (no solo del usuario). Si el instalador no lo agregó solo:
   ```powershell
   [Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path','Machine') + ';C:\Program Files\Eclipse Adoptium\jre-XX.X.X.X-hotspot\bin', 'Machine')
   ```
   (ajustar la ruta exacta a la carpeta instalada).
3. **Reiniciar la máquina** (no basta con abrir una terminal nueva ni con reiniciar solo Apache — un proceso ya corriendo, incluido Apache/XAMPP, mantiene cacheado el PATH viejo hasta que arranca de nuevo desde cero).
4. Verificar en una terminal nueva:
   ```
   java -version
   ```

## 2. Extensión `soap` de PHP habilitada

El envío al SRI usa `SoapClient` (`src/Servicios/SriSoapServicio.php`).

1. Abrir `C:\xampp\php\php.ini`.
2. Buscar la línea `;extension=soap` y quitarle el `;` inicial → `extension=soap`.
3. Reiniciar Apache desde el Panel de Control de XAMPP (Stop → Start).
4. Verificar: `C:\xampp\php\php.exe -m` debe listar `soap`.

## 3. `sri2.jar` presente

Debe existir el archivo en:
```
tools/java/dist/sri2.jar
```
La ruta es configurable en `.env` con `SRI_JAR_RUTA` (por defecto ya apunta ahí — no tocar salvo que se mueva el archivo).

## 4. Configurar la Empresa (Sistema → Empresas → Editar)

1. **Ambiente SRI**: elegir "Pruebas" o "Producción" (radio button). Pruebas = ambiente `1`, Producción = ambiente `2`. Las URLs del webservice del SRI (`celcer.sri.gob.ec` para pruebas, `cel.sri.gob.ec` para producción) se seleccionan **automáticamente** según esto — no hay nada más que configurar ahí.
2. **Certificado digital (.p12)**: subir el archivo del certificado de firma electrónica. Queda guardado en `almacenamiento/archivos/firmas/`.
   - ⚠️ **Nunca subir el archivo `.p12` a Git** (ya está en `.gitignore` vía `almacenamiento/archivos/`).
3. **Clave del certificado**: la contraseña del `.p12`.
4. RUC, razón social, dirección de la empresa deben estar completos (van en el XML).

## 5. Configurar la Bodega (Inventario → Bodegas)

Cada bodega que vaya a facturar necesita:
- **Establecimiento** (3 dígitos, ej. `001`) — debe coincidir con un establecimiento **registrado y activo** en el SRI para ese RUC. Si no coincide, el SRI rechaza el comprobante con un error tipo `ESTABLECIMIENTO CERRADO` (no es un bug del sistema, es una validación del SRI).
- **Punto de emisión** (3 dígitos, ej. `001`).

Para el RUC de pruebas genérico del SRI (`9999999999999`), el establecimiento habitual es `001` con punto de emisión `001`.

## 6. Secuencias

Al crear la empresa, el sistema ya auto-crea las secuencias base (FACTURA_VENTA, NOTA_VENTA, PROFORMA, AJUSTE, TRANSFERENCIA) en `001-001`. Revisar en Sistema → Configuración → Secuencias si el número inicial necesita ajustarse.

## 7. Variables de entorno adicionales (`.env`)

Para el envío automático del PDF/XML por correo al cliente tras la autorización:
```
MAIL_HOST=...
MAIL_PORT=...
MAIL_USER=...
MAIL_PASS=...
MAIL_FROM_NAME=...
```
Si no se configuran, el envío de la factura sigue funcionando — simplemente no se manda el correo.

## 8. Probar el envío

1. Ventas → Facturas → crear una factura en estado **CREADA**.
2. En la lista, botón "Enviar al SRI" (ícono avión ✈️).
3. Si todo está bien configurado, el estado pasa a **AUTORIZADA** y queda la clave de acceso/autorización guardada.

## Errores comunes (ya vistos resolviendo esto)

| Error | Causa | Solución |
|---|---|---|
| `"java" no se reconoce como un comando...` | Java no instalado o no en el PATH del proceso que corre Apache | Ver paso 1. Reiniciar la máquina completa, no solo Apache. |
| `Class "SoapClient" not found` | Extensión `soap` deshabilitada en `php.ini` | Ver paso 2. |
| `SQLSTATE[23514] ... chk_ven_documento_ambiente_sri` | Bug ya corregido: `FacturaModelo::actualizarEstadoSri()` guardaba el código numérico SRI (`1`/`2`) en una columna que exige el texto `PRUEBAS`/`PRODUCCION`. Si vuelve a aparecer, revisar esa función. | — |
| `56 ERROR ESTABLECIMIENTO CERRADO` (viene del SRI, no es un error de la app) | El Establecimiento/Punto de emisión de la bodega no coincide con lo registrado en el SRI para ese RUC | Ver paso 5. |
