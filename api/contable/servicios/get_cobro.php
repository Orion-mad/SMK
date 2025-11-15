<?php
// /api/contable/servicios/get_cobro.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
  $servicioId = isset($_GET['servicio_id']) ? (int)$_GET['servicio_id'] : 0;

  if ($clienteId <= 0 || $servicioId <= 0) {
    //http_response_code(400);
    echo json_encode(['error' => 'cliente_id y servicio_id requeridos']);
    exit;
  }

  $db = DB::get();

  // Buscar el último cobro para este cliente/servicio
  $stmt = $db->prepare("SELECT * 
                        FROM cnt_cobros 
                        WHERE cliente_id = ? 
                          AND servicio_id = ? 
                          AND tipo = 'servicio'
                        ORDER BY fecha_emision DESC 
                        LIMIT 1");
  
  $stmt->bind_param('ii', $clienteId, $servicioId);
  $stmt->execute();
  $cobro = $stmt->get_result()->fetch_assoc();

  if (!$cobro) {
    //http_response_code(404);
    //echo json_encode(['error' => 'no_encontrado']);
    exit;
  }

  // Formatear respuesta
    /*
    'subtotal' => (float)$cobro['subtotal'],
    'total' => (float)$cobro['total'],
    'observaciones' => $cobro['observaciones'],
    */
    
  $data = [
    'id' => (int)$cobro['id'],
    'codigo' => $cobro['codigo'],
    'numero_factura' => $cobro['numero_factura'],
    'cliente_id' => (int)$cobro['cliente_id'],
    'servicio_id' => (int)$cobro['servicio_id'],
    'tipo' => $cobro['tipo'],
    'concepto' => $cobro['concepto'],
    'descuento' => (float)$cobro['descuento'],
    'impuestos' => (float)$cobro['impuestos'],
    'moneda' => $cobro['moneda'],
    'fecha_emision' => $cobro['fecha_emision'],
    'fecha_vencimiento' => $cobro['fecha_vencimiento'],
    'estado' => $cobro['estado'],
    'creado_en' => $cobro['creado_en'],
    'actualizado_en' => $cobro['actualizado_en']
  ];

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[contable/servicios/get_cobro] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}