<?php
// /api/clientes/clientes/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id requerido']);
    exit;
  }

  $db = DB::get();

  // Cliente con datos del servicio
  $stmt = $db->prepare("
    SELECT 
      c.*,
      s.nombre AS servicio_nombre,
      s.codigo AS servicio_codigo,
      s.precio_usd AS servicio_precio
    FROM clientes c
    LEFT JOIN prm_servicios s ON s.id = c.servicio
    WHERE c.id = ? 
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $cliente = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$cliente) {
    http_response_code(404);
    echo json_encode(['error' => 'no encontrado']);
    exit;
  }

  http_response_code(200);
  echo json_encode($cliente, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/clientes/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}