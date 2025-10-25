<?php
// /api/parameters/conceptos/categorias/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id requerido']);
    exit;
  }

  $db = DB::get();
  
  // Obtener categoría
  $stmt = $db->prepare("
    SELECT 
      id,
      codigo,
      nombre,
      descripcion,
      tipo_flujo,
      color,
      icono,
      activo,
      orden,
      creado_en,
      actualizado_en
    FROM prm_conceptos_categorias
    WHERE id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $categoria = $result->fetch_assoc();
  
  if (!$categoria) {
    http_response_code(404);
    echo json_encode(['error' => 'no encontrado']);
    exit;
  }
  
  // Obtener total de conceptos asociados
  $stmtCount = $db->prepare("
    SELECT COUNT(*) as total 
    FROM prm_conceptos_caja 
    WHERE categoria_id = ?
  ");
  $stmtCount->bind_param('i', $id);
  $stmtCount->execute();
  $countResult = $stmtCount->get_result();
  $count = $countResult->fetch_assoc();
  
  $categoria['total_conceptos'] = (int)$count['total'];
  $categoria['activo'] = (int)$categoria['activo'];
  $categoria['orden'] = (int)$categoria['orden'];
  
  http_response_code(200);
  echo json_encode($categoria, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[conceptos/categorias/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}