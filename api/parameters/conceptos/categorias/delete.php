<?php
// /api/parameters/conceptos/categorias/delete.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id requerido']);
    exit;
  }

  $db = DB::get();
  
  // Verificar si tiene conceptos asociados
  $stmt = $db->prepare("SELECT COUNT(*) as total FROM prm_conceptos_caja WHERE categoria_id = ?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $count = $result->fetch_assoc();
  
  if ((int)$count['total'] > 0) {
    http_response_code(409);
    echo json_encode([
      'ok' => false, 
      'error' => 'conflict',
      'detail' => 'No se puede eliminar: hay ' . $count['total'] . ' concepto(s) asociado(s)'
    ]);
    exit;
  }
  
  // Eliminar categoría
  $stmtDel = $db->prepare("DELETE FROM prm_conceptos_categorias WHERE id = ?");
  $stmtDel->bind_param('i', $id);
  
  if ($stmtDel->execute()) {
    http_response_code(204); // No Content
    exit;
  } else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'delete_failed']);
  }

} catch (Throwable $e) {
  error_log('[conceptos/categorias/delete] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}