<?php
// /api/contable/servicios/get_tc.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();
  
  // Obtener última cotización de USD
  $sql = "
    SELECT 
      fecha,
      valor_ars,
      proveedor
    FROM v_tc_ultima
    WHERE moneda = 'DOL'
    LIMIT 1
  ";
  
  $result = $db->query($sql);
  
  if ($result && $row = $result->fetch_assoc()) {
    $data = [
      'ok' => true,
      'fecha' => $row['fecha'],
      'valor_ars' => (float)$row['valor_ars'],
      'proveedor' => $row['proveedor'],
      'moneda' => 'USD'
    ];
  } else {
    // Si no hay cotización, devolver un valor por defecto
    // En producción, esto debería disparar una alerta
    $data = [
      'ok' => true,
      'fecha' => date('Y-m-d'),
      'valor_ars' => 1000.00, // Valor por defecto - ajustar según necesidad
      'proveedor' => 'DEFAULT',
      'moneda' => 'USD',
      'warning' => 'No se encontró cotización actualizada. Usando valor por defecto.'
    ];
  }
  
  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  
} catch (Throwable $e) {
  error_log('[contable/servicios/get_tc] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
}