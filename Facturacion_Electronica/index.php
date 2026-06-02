<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php'; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facturacion Electronica - XML SRI</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;margin:18px}
        .caja{background:#fff;border:1px solid #ddd;border-radius:6px;padding:14px;margin-bottom:12px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px}
        label{display:block;font-size:12px;font-weight:bold;margin-bottom:4px}
        input,select,textarea{width:100%;padding:8px;border:1px solid #bbb;border-radius:4px;box-sizing:border-box}
        table{width:100%;border-collapse:collapse}
        th,td{border:1px solid #ddd;padding:6px;font-size:12px}
        button{background:#0b57d0;color:#fff;border:none;padding:10px 14px;border-radius:4px;cursor:pointer}
        .btn-sec{background:#5f6368}
        .nota{font-size:12px;color:#555}
    </style>
</head>
<body>
<div class="caja">
    <h2>Facturacion Electronica SRI (estilo proyecto original)</h2>
    <p class="nota">Ahora genera XML estructurado y permite multiples lineas de detalle.</p>
</div>

<form action="procesar.php" method="post" enctype="multipart/form-data">
<div class="caja">
    <h3>1) Modo</h3>
    <div class="grid">
        <div><label>Ambiente</label><select name="ambiente"><option value="1">PRUEBAS</option><option value="2">PRODUCCION</option></select></div>
        <div><label>Modo envio</label><select name="modo_envio"><option value="simulacion">SIMULACION</option><option value="real">REAL</option></select></div>
        <div><label>Comprobante</label><select name="codigo_comprobante" id="codigo_comprobante"><option value="01">01 FACTURA</option><option value="04">04 NOTA CREDITO</option><option value="05">05 NOTA DEBITO</option><option value="06">06 GUIA REMISION</option><option value="07">07 RETENCION</option></select></div>
    </div>
</div>

<div class="caja">
    <h3>2) Emisor</h3>
    <div class="grid">
        <div><label>RUC emisor</label><input name="ruc_emisor" value="1790012345001" required></div>
        <div><label>Razon social</label><input name="razon_social_emisor" value="EMPRESA DEMO S.A." required></div>
        <div><label>Nombre comercial</label><input name="nombre_comercial" value="EMPRESA DEMO" required></div>
        <div><label>Direccion matriz</label><input name="dir_matriz" value="AV. DEMO 123" required></div>
        <div><label>Direccion establecimiento</label><input name="dir_establecimiento" value="SUCURSAL DEMO" required></div>
        <div><label>Contribuyente especial</label><input name="contribuyente_especial" value=""></div>
        <div><label>Obligado contabilidad</label><select name="obligado_contabilidad"><option value="SI">SI</option><option value="NO">NO</option></select></div>
        <div><label>Estab</label><input name="estab" value="001" required></div>
        <div><label>Pto Emi</label><input name="pto_emi" value="001" required></div>
        <div><label>Secuencial</label><input name="secuencial" value="000000001" required></div>
    </div>
</div>

<div class="caja">
    <h3>3) Receptor</h3>
    <div class="grid">
        <div><label>Tipo ID receptor</label><select name="tipo_id_comprador"><option value="04">04 RUC</option><option value="05" selected>05 CEDULA</option><option value="06">06 PASAPORTE</option><option value="07">07 CONSUMIDOR FINAL</option></select></div>
        <div><label>ID receptor</label><input name="id_comprador" value="0912345678" required></div>
        <div><label>Razon social receptor</label><input name="razon_social_comprador" value="CLIENTE DEMO" required></div>
        <div><label>Direccion receptor</label><input name="direccion_comprador" value="DIRECCION CLIENTE"></div>
        <div><label>Telefono receptor</label><input name="telefono_comprador" value="0999999999"></div>
        <div><label>Email receptor</label><input name="email_comprador" value="correo@demo.com"></div>
    </div>
</div>

<div class="caja">
    <h3>4) Datos documento</h3>
    <div class="grid">
        <div><label>Fecha emision</label><input type="date" name="fecha_emision" value="<?= date('Y-m-d'); ?>"></div>
        <div><label>Documento modificado</label><input name="documento_modificado" value="001-001-000000001"></div>
        <div><label>Fecha doc sustento</label><input type="date" name="fecha_doc_modificado" value="<?= date('Y-m-d'); ?>"></div>
        <div><label>Motivo/Observacion</label><input name="motivo" value="PRUEBA DEMO"></div>
        <div><label>Total sin impuestos</label><input name="total_sin_impuestos" value="100.00"></div>
        <div><label>Total descuento</label><input name="total_descuento" value="0.00"></div>
        <div><label>Base imponible IVA</label><input name="base_imponible_iva" value="100.00"></div>
        <div><label>Porcentaje IVA</label><input name="porcentaje_iva" value="15.00"></div>
        <div><label>Valor IVA</label><input name="valor_iva" value="15.00"></div>
        <div><label>Importe total</label><input name="importe_total" value="115.00"></div>
        <div><label>Forma pago SRI</label><input name="forma_pago" value="20"></div>
        <div><label>Plazo pago (dias)</label><input name="plazo_pago_dias" value="0"></div>
    </div>
</div>

<div class="caja" id="bloque-guia">
    <h3>5) Campos guia remision (06)</h3>
    <div class="grid">
        <div><label>Dir partida</label><input name="dir_partida" value="BODEGA MATRIZ"></div>
        <div><label>Transportista</label><input name="razon_social_transportista" value="TRANSPORTISTA DEMO"></div>
        <div><label>Tipo ID transportista</label><select name="tipo_id_transportista"><option value="04">04 RUC</option><option value="05">05 CEDULA</option></select></div>
        <div><label>RUC transportista</label><input name="ruc_transportista" value="1791111111001"></div>
        <div><label>Fecha inicio transporte</label><input type="date" name="fecha_inicio_transporte" value="<?= date('Y-m-d'); ?>"></div>
        <div><label>Fecha fin transporte</label><input type="date" name="fecha_fin_transporte" value="<?= date('Y-m-d'); ?>"></div>
        <div><label>Placa</label><input name="placa" value="ABC1234"></div>
        <div><label>Ruta</label><input name="ruta" value="QUITO-GYE"></div>
        <div><label>Doc aduanero</label><input name="doc_aduanero" value=""></div>
        <div><label>Cod estab destino</label><input name="cod_estab_destino" value="001"></div>
    </div>
</div>

<div class="caja">
    <h3>6) Multiples lineas de detalle</h3>
    <p class="nota">Aplica para 01/04/06. Puedes agregar filas.</p>
    <table id="tabla-detalles">
        <thead>
            <tr><th>Codigo</th><th>Codigo Auxiliar</th><th>Descripcion</th><th>Cantidad</th><th>P. Unit</th><th>Desc.</th><th>IVA %</th><th>Marca (guia)</th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p style="margin-top:8px"><button type="button" class="btn-sec" onclick="agregarDetalle()">Agregar linea</button></p>
</div>

<div class="caja" id="bloque-retencion">
    <h3>7) Multiples lineas de retencion (07)</h3>
    <table id="tabla-retenciones">
        <thead><tr><th>Codigo (1 renta / 2 IVA)</th><th>Codigo Retencion</th><th>Base</th><th>%</th><th>Valor</th><th></th></tr></thead>
        <tbody></tbody>
    </table>
    <p style="margin-top:8px"><button type="button" class="btn-sec" onclick="agregarRetencion()">Agregar retencion</button></p>
    <div class="grid" style="margin-top:8px">
        <div><label>Periodo fiscal (mm/yyyy)</label><input name="periodo_fiscal" value="<?= date('m/Y'); ?>"></div>
        <div><label>Num autorizacion sustento (49)</label><input name="num_aut_doc_sustento" value=""></div>
    </div>
</div>

<div class="caja">
    <h3>8) Certificado .p12 (modo REAL)</h3>
    <div class="grid">
        <div><label>Archivo p12</label><input type="file" name="archivo_p12" accept=".p12"></div>
        <div><label>Clave certificado</label><input type="password" name="clave_certificado"></div>
    </div>
    <p class="nota">Se guarda en la raiz como certificado_actual.p12.</p>
</div>

<div class="caja"><button type="submit">Ejecutar flujo SRI</button></div>
</form>

<script>
function filaDetalle(){
  return '<tr>'+ 
    '<td><input name="detalle_codigo[]" value="ITEM001"></td>'+ 
    '<td><input name="detalle_codigo_aux[]" value="AUX001"></td>'+ 
    '<td><input name="detalle_descripcion[]" value="ITEM DEMO"></td>'+ 
    '<td><input name="detalle_cantidad[]" value="1"></td>'+ 
    '<td><input name="detalle_precio[]" value="100"></td>'+ 
    '<td><input name="detalle_descuento[]" value="0"></td>'+ 
    '<td><input name="detalle_iva_pct[]" value="15"></td>'+ 
    '<td><input name="detalle_marca[]" value=""></td>'+ 
    '<td><button type="button" class="btn-sec" onclick="this.closest(\'tr\').remove()">X</button></td>'+ 
    '</tr>';
}
function filaRetencion(){
  return '<tr>'+ 
    '<td><input name="ret_codigo[]" value="1"></td>'+ 
    '<td><input name="ret_codigo_retencion[]" value="332"></td>'+ 
    '<td><input name="ret_base[]" value="100"></td>'+ 
    '<td><input name="ret_porcentaje[]" value="1"></td>'+ 
    '<td><input name="ret_valor[]" value="1"></td>'+ 
    '<td><button type="button" class="btn-sec" onclick="this.closest(\'tr\').remove()">X</button></td>'+ 
    '</tr>';
}
function agregarDetalle(){document.querySelector('#tabla-detalles tbody').insertAdjacentHTML('beforeend', filaDetalle());}
function agregarRetencion(){document.querySelector('#tabla-retenciones tbody').insertAdjacentHTML('beforeend', filaRetencion());}

for(let i=0;i<2;i++){agregarDetalle();}
agregarRetencion();
</script>
</body>
</html>
