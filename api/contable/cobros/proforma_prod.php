<?php
// /api/contable/cobros/proforma_prod.php
declare(strict_types=1);

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../../servicios/pdf/documento_proforma.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $action = $_GET['action'] ?? 'download'; // download, view, inline
  
  if ($id <= 0) {
    http_response_code(400);
    die('ID de cobro requerido');
  }

  $db = DB::get();
  
  // Obtener datos completos del cobro y cliente
  $sql = "
    SELECT 
      co.*,
      c.razon_social,
      c.nombre_fantasia,
      c.tipo_doc,
      c.nro_doc,
      c.iva_cond as condicion_iva,
      c.direccion,
      c.localidad,
      c.provincia,
      c.cp,
      c.email,
      c.telefono,
      c.celular
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
  
  // Preparar datos para el PDF
  $cobro = [
    'id' => $row['id'],
    'codigo' => $row['codigo'],
    'numero_factura' => $row['numero_factura'],
    'tipo' => $row['tipo'],
    'concepto' => $row['concepto'],
    'subtotal' => (float)$row['subtotal'],
    'descuento' => (float)$row['descuento'],
    'impuestos' => (float)$row['impuestos'],
    'total' => (float)$row['total'],
    'moneda' => $row['moneda'],
    'fecha_emision' => $row['fecha_emision'],
    'fecha_vencimiento' => $row['fecha_vencimiento'],
    'estado' => $row['estado'],
    'monto_pagado' => (float)$row['monto_pagado'],
    'saldo' => (float)$row['saldo'],
    'observaciones' => $row['observaciones']
  ];
  
  $nombreCliente = !empty($row['nombre_fantasia']) ? $row['nombre_fantasia'] : $row['razon_social'];
  $documentoCliente = $row['tipo_doc'] . ': ' . $row['nro_doc'];
  
  $cliente = [
    'razon_social' => $nombreCliente,
    'documento' => $documentoCliente,
    'condicion_iva' => $row['condicion_iva'],
    'direccion' => $row['direccion'],
    'localidad' => $row['localidad'],
    'provincia' => $row['provincia'],
    'cp' => $row['cp'],
    'email' => $row['email'],
    'telefono' => $row['telefono'] ?: $row['celular']
  ];
  
  // Crear el PDF
  $pdf = new DocumentoProforma($cobro, $cliente);
  $pdf->generarProforma();
  
  // Nombre del archivo
  $filename = 'proforma_' . $cobro['codigo'] . '.pdf';
  
  // Determinar cómo enviar el PDF
  switch ($action) {
    case 'view':
    case 'inline':
      // Ver en el navegador
      $pdf->Output('I', $filename);
      break;
      
    case 'download':
    default:
      // Forzar descarga
      $pdf->Output('D', $filename);
      break;
  }
  
} catch (Throwable $e) {
  error_log('[contable/cobros/proforma_prod] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  die('Error generando proforma: ' . $e->getMessage());
}