<?php
// /api/contable/cobros/proforma.php
declare(strict_types=1);

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $action = $_GET['action'] ?? 'download'; // download o view
  
  if ($id <= 0) {
    http_response_code(400);
    die('ID de cobro requerido');
  }

  $db = DB::get();
  
  // Obtener datos del cobro y cliente
  $sql = "
    SELECT 
      co.*,
      c.razon_social as cliente_razon,
      c.nombre_fantasia as cliente_fantasia,
      c.tipo_doc as cliente_tipo_doc,
      c.nro_doc as cliente_doc,
      c.iva_cond as cliente_iva,
      c.direccion as cliente_dir,
      c.localidad as cliente_loc,
      c.provincia as cliente_prov,
      c.cp as cliente_cp,
      c.email as cliente_email,
      c.telefono as cliente_tel
    FROM cnt_cobros co
    INNER JOIN clientes c ON c.id = co.cliente_id
    WHERE co.id = ? AND co.activo = 1
    LIMIT 1
  ";
  
  $stmt = $db->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if (!$row = $result->fetch_assoc()) {
    http_response_code(404);
    die('Cobro no encontrado');
  }
  
  $stmt->close();
  
  // Extraer datos
  $cobro = $row;
  
  // Función helper para formatear moneda
  function formatMoney($value) {
    return number_format((float)$value, 2, ',', '.');
  }
  
  // Función helper para formatear fecha
  function formatDate($date) {
    if (!$date) return '-';
    $dt = new DateTime($date);
    return $dt->format('d/m/Y');
  }
  
  // Generar HTML de la proforma
  $html = generateProformaHTML($cobro);
  
  // Si tenemos una librería de PDF instalada (TCPDF, DOMPDF, etc.)
  // la usaríamos aquí. Por ahora, generamos un HTML estilizado
  // que puede ser convertido a PDF por el navegador o una librería externa.
  
  if ($action === 'download') {
    // Forzar descarga
    header('Content-Type: application/text');
    header('Content-Disposition: attachment; filename="proforma_' . $cobro['codigo'] . '.html"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Por ahora retornamos HTML que puede ser impreso como PDF
    // En producción, usar TCPDF o similar
    echo $html;
  } else {
    // Ver en navegador
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
  }
  
} catch (Throwable $e) {
  error_log('[contable/cobros/proforma] ' . $e->getMessage());
  http_response_code(500);
  die('Error generando proforma: ' . $e->getMessage());
}

function generateProformaHTML($cobro) {
  $clienteNombre = $cobro['cliente_fantasia'] ?: $cobro['cliente_razon'];
  $clienteDoc = $cobro['cliente_tipo_doc'] . ': ' . $cobro['cliente_doc'];
  
  return '
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Proforma '.$cobro['codigo'].'</title>
  <style>
    @media print {
      body { margin: 0; }
      .no-print { display: none; }
    }
    
    body {
      font-family: Arial, sans-serif;
      font-size: 11pt;
      margin: 20px;
      color: #333;
    }
    
    .header {
      text-align: center;
      margin-bottom: 30px;
      border-bottom: 3px solid #000;
      padding-bottom: 15px;
    }
    
    .header h1 {
      margin: 0;
      font-size: 24pt;
      color: #000;
    }
    
    .header .tipo {
      font-size: 18pt;
      color: #666;
      margin: 5px 0;
    }
    
    .info-grid {
      display: table;
      width: 100%;
      margin-bottom: 20px;
    }
    
    .info-row {
      display: table-row;
    }
    
    .info-cell {
      display: table-cell;
      padding: 8px;
      vertical-align: top;
    }
    
    .info-cell.left {
      width: 50%;
      border-right: 1px solid #ddd;
    }
    
    .info-cell.right {
      width: 50%;
      padding-left: 15px;
    }
    
    .info-label {
      font-weight: bold;
      color: #666;
      font-size: 9pt;
      text-transform: uppercase;
    }
    
    .info-value {
      font-size: 11pt;
      margin-bottom: 10px;
    }
    
    .section-title {
      background: #f0f0f0;
      padding: 8px;
      font-weight: bold;
      margin-top: 20px;
      margin-bottom: 10px;
      border-left: 4px solid #000;
    }
    
    table.items {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    
    table.items th {
      background: #333;
      color: white;
      padding: 10px;
      text-align: left;
      font-size: 10pt;
    }
    
    table.items td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
    }
    
    table.items tr:last-child td {
      border-bottom: none;
    }
    
    .text-right {
      text-align: right;
    }
    
    .totales {
      margin-top: 30px;
      float: right;
      width: 40%;
    }
    
    .totales table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .totales td {
      padding: 8px;
      border-bottom: 1px solid #ddd;
    }
    
    .totales .label {
      text-align: right;
      font-weight: bold;
    }
    
    .totales .total-final {
      background: #333;
      color: white;
      font-size: 14pt;
      font-weight: bold;
    }
    
    .observaciones {
      clear: both;
      margin-top: 30px;
      padding: 15px;
      background: #f9f9f9;
      border-left: 4px solid #000;
    }
    
    .footer {
      margin-top: 50px;
      padding-top: 20px;
      border-top: 1px solid #ddd;
      text-align: center;
      font-size: 9pt;
      color: #666;
    }
    
    .watermark {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-45deg);
      font-size: 72pt;
      color: rgba(0, 0, 0, 0.05);
      z-index: -1;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="watermark">PROFORMA</div>
  
  <div class="header">
    <h1>PROFORMA</h1>
    <div class="tipo">Comprobante No Fiscal</div>
    <div style="margin-top: 10px; font-size: 14pt; font-weight: bold;">
      '.$cobro['codigo'].'
    </div>
  </div>
  
  <div class="info-grid">
    <div class="info-row">
      <div class="info-cell left">
        <div class="info-label">Cliente</div>
        <div class="info-value">'.$clienteNombre.'</div>
        
        <div class="info-label">Documento</div>
        <div class="info-value">'.$clienteDoc.'</div>
        
        <div class="info-label">Condición IVA</div>
        <div class="info-value">'.$cobro['cliente_iva'].'</div>
        
        <div class="info-label">Dirección</div>
        <div class="info-value">
          '.$cobro['cliente_dir'].'<br>
          '.$cobro['cliente_loc'].', '.$cobro['cliente_prov'].' ('.$cobro['cliente_cp'].')
        </div>
      </div>
      
      <div class="info-cell right">
        <div class="info-label">Fecha de Emisión</div>
        <div class="info-value">'.formatDate($cobro['fecha_emision']).'</div>
        
        <div class="info-label">Vencimiento</div>
        <div class="info-value">'.formatDate($cobro['fecha_vencimiento']).'</div>
        
        <div class="info-label">Estado</div>
        <div class="info-value">'.strtoupper($cobro['estado']).'</div>
        
        <div class="info-label">Moneda</div>
        <div class="info-value">'.$cobro['moneda'].'</div>
      </div>
    </div>
  </div>
  
  <div class="section-title">Concepto</div>
  <div style="padding: 10px;">
    '.$cobro['concepto'].'
  </div>
  
  <table class="items">
    <thead>
      <tr>
        <th>Descripción</th>
        <th class="text-right">Importe</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>'.$cobro['concepto'].'</td>
        <td class="text-right">$'.formatMoney($cobro['subtotal']).'</td>
      </tr>
    </tbody>
  </table>
  
  <div class="totales">
    <table>
      <tr>
        <td class="label">Subtotal:</td>
        <td class="text-right">\$'.formatMoney($cobro['subtotal']).'</td>
      </tr>
';

  if ($cobro['descuento'] > 0) {
    $html .= '
      <tr>
        <td class="label">Descuento:</td>
        <td class="text-right">-\$'.formatMoney($cobro['descuento']).'</td>
      </tr>
';
  }

  if ($cobro['impuestos'] > 0) {
    $html .= '
      <tr>
        <td class="label">Impuestos:</td>
        <td class="text-right">\$'.formatMoney($cobro['impuestos']).'</td>
      </tr>
';
  }

  $html .= '
      <tr class="total-final">
        <td class="label">TOTAL:</td>
        <td class="text-right">\$'.formatMoney($cobro['total']).' '.$cobro['moneda'].'</td>
      </tr>
    </table>
  </div>
  
  <div class="observaciones">
    <strong>Observaciones:</strong><br>
    '.nl2br(htmlspecialchars($cobro['observaciones'])).'
  </div>
  
  <div class="footer">
    <p><strong>IMPORTANTE:</strong> Este es un comprobante de proforma, no válido como factura fiscal.</p>
    <p>Una vez efectuado el pago, se emitirá la factura oficial correspondiente.</p>
    <p style="margin-top: 20px;">Generado el '.date('d/m/Y H:i').'</p>
  </div>
  
  <div class="no-print" style="text-align: center; margin-top: 30px;">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 14pt; cursor: pointer;">
      Imprimir / Guardar como PDF
    </button>
  </div>
</body>
</html>
';

  return $html;
}