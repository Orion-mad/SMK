<?php
// /api/parameters/medios_pago/delete.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/crud_helper.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id requerido']);
    exit;
  }

  $cfg = [
    'table' => 'prm_medios_pago',
    'pk' => 'id',
    'cascade' => []
  ];

  $result = lcars_delete($cfg, $id);
  
  if ($result['ok']) {
    exit; // 204 No Content ya enviado
  } else {
    echo json_encode($result);
  }

} catch (Throwable $e) {
  error_log('[medios_pago/delete] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}