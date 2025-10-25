<?php
// /api/parameters/medios_pago/get.php
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
  
  $stmt = $db->prepare("SELECT * FROM prm_medios_pago WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_assoc();
  
  if (!$data) {
    http_response_code(404);
    echo json_encode(['error' => 'no encontrado']);
    exit;
  }

  // Convertir tipos
  $data['id'] = (int)$data['id'];
  $data['activo'] = (int)$data['activo'];
  $data['orden'] = (int)$data['orden'];

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[medios_pago/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}