<?php
// /api/parameters/conceptos/categorias/select.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../inc/conect.php';

try {
  $db = DB::get();
  
  // Filtro opcional por tipo_flujo
  $tipo_flujo = isset($_GET['tipo_flujo']) ? $_GET['tipo_flujo'] : null;
  
  $sql = "SELECT id, codigo, nombre, tipo_flujo, color, icono 
          FROM prm_conceptos_categorias 
          WHERE activo = 1";
  
  $params = [];
  $types = '';
  
  if ($tipo_flujo && in_array($tipo_flujo, ['ingreso', 'egreso'])) {
    $sql .= " AND (tipo_flujo = ? OR tipo_flujo = 'ambos')";
    $params[] = $tipo_flujo;
    $types .= 's';
  }
  
  $sql .= " ORDER BY orden ASC, nombre ASC";
  
  if (!empty($params)) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
  } else {
    $result = $db->query($sql);
  }
  
  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'id'         => (int)$row['id'],
      'codigo'     => $row['codigo'],
      'nombre'     => $row['nombre'],
      'tipo_flujo' => $row['tipo_flujo'],
      'color'      => $row['color'],
      'icono'      => $row['icono']
    ];
  }

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[conceptos/categorias/select] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}