<?php
// /api/clientes/dominios/get.php
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

  $stmt = $db->prepare("
    SELECT 
      d.*,
      c.razon_social AS cliente_razon_social,
      c.nombre_fantasia AS cliente_nombre_fantasia,
      c.email AS cliente_email,
      p.codigo AS plan_codigo,
      p.nombre AS plan_nombre
    FROM dominios d
    LEFT JOIN clientes c ON c.id = d.cliente_id
    LEFT JOIN prm_planes p ON p.id = d.plan_id
    WHERE d.id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $dominio = $result->fetch_assoc();

  if (!$dominio) {
    http_response_code(404);
    echo json_encode(['error' => 'no encontrado']);
    exit;
  }

  // Convertir campos numéricos
  $dominio['id'] = (int)$dominio['id'];
  $dominio['cliente_id'] = (int)$dominio['cliente_id'];
  $dominio['plan_id'] = $dominio['plan_id'] ? (int)$dominio['plan_id'] : null;
  $dominio['ssl_activo'] = (int)$dominio['ssl_activo'];
  $dominio['renovacion_auto'] = (int)$dominio['renovacion_auto'];
  $dominio['orden'] = (int)$dominio['orden'];

  http_response_code(200);
  echo json_encode($dominio, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/dominios/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}