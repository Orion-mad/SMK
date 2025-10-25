<?php
// /api/parameters/conceptos/categorias/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../inc/conect.php';
require_once __DIR__ . '/../../../core/list_helper.php';

try {
  $db = DB::get();
  
  // Verificar que la tabla existe
  $checkTable = $db->query("SHOW TABLES LIKE 'prm_conceptos_categorias'");
  if ($checkTable->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla prm_conceptos_categorias no existe']);
    exit;
  }

  $cfg = [
    'table' => 'prm_conceptos_categorias',
    'select' => [
      'id'            => 'id',
      'codigo'        => 'codigo',
      'nombre'        => 'nombre',
      'descripcion'   => 'descripcion',
      'tipo_flujo'    => 'tipo_flujo',
      'color'         => 'color',
      'icono'         => 'icono',
      'activo'        => 'activo',
      'orden'         => 'orden',
      'creado_en'     => 'creado_en',
      'actualizado_en'=> 'actualizado_en',
    ],
    'orderable' => ['id', 'codigo', 'nombre', 'tipo_flujo', 'orden'],
    'default_order' => ['orden' => 'ASC', 'nombre' => 'ASC'],
    'searchable' => ['codigo', 'nombre', 'descripcion'],
    'numeric' => [
      'id'     => 'int',
      'activo' => 'int',
      'orden'  => 'int',
    ],
    'filters' => [
      'tipo_flujo' => [
        'col' => 'tipo_flujo',
        'type' => 'str',
        'in' => ['ingreso', 'egreso', 'ambos']
      ],
      'activo' => [
        'col' => 'activo',
        'type' => 'int',
        'in' => [0, 1]
      ]
    ],
    'per_page' => 50,
  ];

  $result = lcars_list($cfg, $_GET);
  
  // Agregar contador de conceptos por categoría
  if (isset($result['items']) && count($result['items']) > 0) {
    $ids = array_column($result['items'], 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $db->prepare("
      SELECT categoria_id, COUNT(*) as total_conceptos 
      FROM prm_conceptos_caja 
      WHERE categoria_id IN ($placeholders)
      GROUP BY categoria_id
    ");
    
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $countResult = $stmt->get_result();
    
    $counts = [];
    while ($row = $countResult->fetch_assoc()) {
      $counts[$row['categoria_id']] = (int)$row['total_conceptos'];
    }
    
    foreach ($result['items'] as &$item) {
      $item['total_conceptos'] = $counts[$item['id']] ?? 0;
    }
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[conceptos/categorias/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}