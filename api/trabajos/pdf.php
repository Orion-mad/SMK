<?php
// /api/trabajos/pdf.php
declare(strict_types=1);

require_once __DIR__ . '/../../servicios/pdf/TrabajoPDF.php';
require_once __DIR__ . '/../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    die('ID de trabajo requerido');
  }

  $db = DB::get();

  // Obtener datos del trabajo con cliente
  $stmt = $db->prepare("
    SELECT 
      t.*,
      CASE 
        WHEN c.razon_social IS NOT NULL AND c.razon_social != '' THEN c.razon_social
        ELSE c.contacto_nombre
      END AS cliente_nombre,
      c.razon_social,
      c.nro_doc AS cliente_cuit,
      c.email AS cliente_email,
      c.telefono AS cliente_telefono,
      c.direccion AS cliente_direccion,
      c.localidad AS cliente_localidad,
      c.provincia AS cliente_provincia,
      s.nombre AS servicio_nombre,
      s.codigo AS servicio_codigo,
      p.codigo AS presupuesto_codigo
    FROM prm_trabajos t
    INNER JOIN clientes c ON c.id = t.cliente_id
    LEFT JOIN prm_servicios s ON s.id = t.servicio_id
    LEFT JOIN cli_presupuestos p ON p.id = t.presupuesto_id
    WHERE t.id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $trabajo = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  
  if (!$trabajo) {
    die('Trabajo no encontrado');
  }

  // Obtener pagos confirmados
  $stmt = $db->prepare("
    SELECT * FROM prm_trabajos_pagos
    WHERE trabajo_id = ? AND estado = 'confirmado'
    ORDER BY fecha_pago ASC
  ");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $pagos = [];
  while ($row = $result->fetch_assoc()) {
    $pagos[] = $row;
  }
  $stmt->close();

  // Calcular totales
  $total_pagado = 0;
  foreach ($pagos as $pago) {
    $total_pagado += (float)$pago['monto'];
  }

  // Crear PDF
  $pdf = new TrabajoPDF('orion', $trabajo['cliente_nombre']);
  $pdf->AliasNbPages();
  
  // PORTADA
  $pdf->Portada($trabajo['nombre']);
  
  // PÁGINA 1: INFORMACIÓN DEL TRABAJO
  $pdf->PaginaContenido();
  
  // Título del trabajo
  $pdf->SetFont('Arial', 'B', 14);
  $pdf->SetTextColor(31, 73, 125);
  $pdf->Cell(0, 10, utf8_decode($trabajo['nombre']), 0, 1, 'L');
  
  // Información básica
  $pdf->Ln(3);
  $pdf->SetFont('Arial', 'B', 11);
  $pdf->SetTextColor(31, 73, 125);
  $pdf->Cell(0, 6, utf8_decode('Información General'), 0, 1, 'L');
  
  $pdf->SetFont('Arial', '', 10);
  $pdf->SetTextColor(0, 0, 0);
  
  $pdf->Cell(40, 6, utf8_decode('Código:'), 0, 0, 'L');
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->Cell(0, 6, utf8_decode($trabajo['codigo']), 0, 1, 'L');
  $pdf->SetFont('Arial', '', 10);
  
  $pdf->Cell(40, 6, utf8_decode('Estado:'), 0, 0, 'L');
  $pdf->SetFont('Arial', 'B', 10);
  $estadoLabels = [
    'pendiente' => 'PENDIENTE',
    'en_proceso' => 'EN PROCESO',
    'homologacion' => 'HOMOLOGACIÓN',
    'finalizado' => 'FINALIZADO',
    'entregado' => 'ENTREGADO',
    'cancelado' => 'CANCELADO'
  ];
  $pdf->Cell(0, 6, utf8_decode($estadoLabels[$trabajo['estado']] ?? strtoupper($trabajo['estado'])), 0, 1, 'L');
  $pdf->SetFont('Arial', '', 10);
  
  $pdf->Cell(40, 6, utf8_decode('Prioridad:'), 0, 0, 'L');
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->Cell(0, 6, utf8_decode(strtoupper($trabajo['prioridad'])), 0, 1, 'L');
  $pdf->SetFont('Arial', '', 10);
  
  if ($trabajo['servicio_nombre']) {
    $pdf->Cell(40, 6, utf8_decode('Tipo de Servicio:'), 0, 0, 'L');
    $pdf->Cell(0, 6, utf8_decode($trabajo['servicio_nombre']), 0, 1, 'L');
  }
  
  if ($trabajo['presupuesto_codigo']) {
    $pdf->Cell(40, 6, utf8_decode('Presupuesto Origen:'), 0, 0, 'L');
    $pdf->Cell(0, 6, utf8_decode($trabajo['presupuesto_codigo']), 0, 1, 'L');
  }
  
  // Descripción
  if (!empty($trabajo['descripcion'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 6, utf8_decode('Descripción del Trabajo'), 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $texto = strip_tags($trabajo['descripcion']);
    $pdf->MultiCell(0, 5, utf8_decode($texto));
  }
  
  // Fechas
  $pdf->Ln(5);
  $pdf->SetFont('Arial', 'B', 11);
  $pdf->SetTextColor(31, 73, 125);
  $pdf->Cell(0, 6, utf8_decode('Fechas del Proyecto'), 0, 1, 'L');
  
  $pdf->SetFont('Arial', '', 10);
  $pdf->SetTextColor(0, 0, 0);
  
  $fecha_ingreso = date('d/m/Y', strtotime($trabajo['fecha_ingreso']));
  $pdf->Cell(60, 6, utf8_decode('Fecha de Ingreso:'), 0, 0, 'L');
  $pdf->Cell(0, 6, utf8_decode($fecha_ingreso), 0, 1, 'L');
  
  if ($trabajo['fecha_entrega_estimada']) {
    $fecha_entrega_est = date('d/m/Y', strtotime($trabajo['fecha_entrega_estimada']));
    $pdf->Cell(60, 6, utf8_decode('Entrega Estimada:'), 0, 0, 'L');
    $pdf->Cell(0, 6, utf8_decode($fecha_entrega_est), 0, 1, 'L');
  }
  
  if ($trabajo['fecha_entrega_real']) {
    $fecha_entrega_real = date('d/m/Y', strtotime($trabajo['fecha_entrega_real']));
    $pdf->Cell(60, 6, utf8_decode('Entrega Real:'), 0, 0, 'L');
    $pdf->Cell(0, 6, utf8_decode($fecha_entrega_real), 0, 1, 'L');
  }
  
  // PÁGINA 2: INFORMACIÓN FINANCIERA
  $pdf->AddPage();
  
  $pdf->SetFont('Arial', 'B', 14);
  $pdf->SetTextColor(31, 73, 125);
  $pdf->Cell(0, 10, utf8_decode('Información Financiera'), 0, 1, 'L');
  
  // Resumen de montos
  $pdf->Ln(3);
  $pdf->SetFillColor(240, 240, 240);
  $pdf->SetFont('Arial', 'B', 10);
  
  $total_fmt = number_format((float)$trabajo['total'], 2, ',', '.');
  $pdf->Cell(100, 8, utf8_decode('Total del Trabajo:'), 1, 0, 'L', true);
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->Cell(0, 8, utf8_decode($trabajo['moneda'] . ' $ ' . $total_fmt), 1, 1, 'R', true);
  
  $pdf->SetFont('Arial', 'B', 10);
  $total_pagado_fmt = number_format($total_pagado, 2, ',', '.');
  $pdf->Cell(100, 8, utf8_decode('Total Pagado:'), 1, 0, 'L');
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->SetTextColor(0, 128, 0);
  $pdf->Cell(0, 8, utf8_decode($trabajo['moneda'] . ' $ ' . $total_pagado_fmt), 1, 1, 'R');
  
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetTextColor(0, 0, 0);
  $saldo_fmt = number_format((float)$trabajo['saldo'], 2, ',', '.');
  $pdf->Cell(100, 8, utf8_decode('Saldo Pendiente:'), 1, 0, 'L');
  $pdf->SetFont('Arial', 'B', 12);
  $pdf->SetTextColor(255, 0, 0);
  $pdf->Cell(0, 8, utf8_decode($trabajo['moneda'] . ' $ ' . $saldo_fmt), 1, 1, 'R');
  
  $pdf->SetTextColor(0, 0, 0);
  
  if ($trabajo['medio_pago']) {
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 6, utf8_decode('Medio de Pago:'), 0, 0, 'L');
    $pdf->Cell(0, 6, utf8_decode($trabajo['medio_pago']), 0, 1, 'L');
  }
  
  // Historial de pagos
  if (count($pagos) > 0) {
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 8, utf8_decode('Historial de Pagos'), 0, 1, 'L');
    
    // Cabecera de tabla
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(31, 73, 125);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(25, 7, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Monto', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Medio de Pago', 1, 0, 'C', true);
    $pdf->Cell(80, 7, 'Referencia', 1, 1, 'C', true);
    
    // Datos
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    
    foreach ($pagos as $pago) {
      $fecha_pago = date('d/m/Y', strtotime($pago['fecha_pago']));
      $monto_pago = number_format((float)$pago['monto'], 2, ',', '.');
      
      $pdf->SetFillColor(245, 245, 245);
      $pdf->Cell(25, 6, utf8_decode($fecha_pago), 1, 0, 'C', $fill);
      $pdf->Cell(35, 6, utf8_decode($trabajo['moneda'] . ' $ ' . $monto_pago), 1, 0, 'R', $fill);
      $pdf->Cell(40, 6, utf8_decode($pago['medio_pago']), 1, 0, 'L', $fill);
      $pdf->Cell(80, 6, utf8_decode($pago['referencia'] ?: '-'), 1, 1, 'L', $fill);
      
      $fill = !$fill;
    }
  } else {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 6, utf8_decode('No se han registrado pagos para este trabajo'), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
  }
  
  // Homologación (si aplica)
  if ($trabajo['requiere_homologacion']) {
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 8, utf8_decode('Datos de Homologación'), 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    if ($trabajo['homologacion_url']) {
      $pdf->Cell(40, 6, utf8_decode('URL Sistema:'), 0, 0, 'L');
      $pdf->Cell(0, 6, utf8_decode($trabajo['homologacion_url']), 0, 1, 'L');
    }
    
    if ($trabajo['homologacion_usuario']) {
      $pdf->Cell(40, 6, utf8_decode('Usuario:'), 0, 0, 'L');
      $pdf->Cell(0, 6, utf8_decode($trabajo['homologacion_usuario']), 0, 1, 'L');
    }
    
    if ($trabajo['homologacion_estado']) {
      $pdf->Cell(40, 6, utf8_decode('Estado:'), 0, 0, 'L');
      $pdf->Cell(0, 6, utf8_decode(strtoupper($trabajo['homologacion_estado'])), 0, 1, 'L');
    }
    
    if ($trabajo['homologacion_notas']) {
      $pdf->Ln(3);
      $pdf->SetFont('Arial', 'B', 10);
      $pdf->Cell(0, 6, utf8_decode('Notas:'), 0, 1, 'L');
      $pdf->SetFont('Arial', '', 9);
      $texto = strip_tags($trabajo['homologacion_notas']);
      $pdf->MultiCell(0, 5, utf8_decode($texto));
    }
  }
  
  // Observaciones
  if (!empty($trabajo['observaciones'])) {
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 8, 'OBSERVACIONES', 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $texto = strip_tags($trabajo['observaciones']);
    $pdf->MultiCell(0, 5, utf8_decode($texto));
  }
  
  // Salida del PDF
  $nombre_archivo = 'Trabajo_' . $trabajo['codigo'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $trabajo['cliente_nombre']) . '.pdf';
  $pdf->Output('I', $nombre_archivo);

} catch (Throwable $e) {
  error_log('[trabajos/pdf] ' . $e->getMessage());
  die('Error generando PDF: ' . $e->getMessage());
}
?>
