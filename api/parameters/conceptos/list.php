<?php
// /api/parameters/conceptos/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/list_helper.php';

try {
  $db = DB::get();
  
  // Verificar que la tabla existe
  $checkTable = $db->query("SHOW TABLES LIKE 'prm_conceptos_caja'");
  if ($checkTable->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla prm_conceptos_caja no existe']);
    exit;
  }

  $cfg = [
    'table' => 'prm_conceptos_caja c LEFT JOIN prm_conceptos_categorias cat ON cat.id = c.categoria_id',
    'select' => [
      'id'                => 'c.id',
      'codigo'            => 'c.codigo',
      'nombre'            => 'c.nombre',
      'descripcion'       => 'c.descripcion',
      'categoria_id'      => 'c.categoria_id',
      'categoria_nombre'  => 'cat.nombre',
      'categoria_color'   => 'cat.color',
      'categoria_icono'   => 'cat.icono',
      'tipo_flujo'        => 'c.tipo_flujo',
      'costo_base'        => 'c.costo_base',
      'moneda_costo'      => 'c.moneda_costo',
      'activo'            => 'c.activo',
      'orden'             => 'c.orden',
      'imputable_afip'    => 'c.imputable_afip',
      'periodicidad'      => 'c.periodicidad',
      'creado_en'         => 'c.creado_en',
      'actualizado_en'    => 'c.actualizado_en',
    ],
    'orderable' => ['id', 'codigo', 'nombre', 'costo_base', 'orden'],
    'default_order' => ['orden' => 'ASC', 'nombre' => 'ASC'],
    'searchable' => ['c.codigo', 'c.nombre', 'c.descripcion', 'cat.nombre'],
    'numeric' => [
      'id'           => 'int',
      'categoria_id' => 'int',
      'costo_base'   => 'float',
      'activo'       => 'int',
      'orden'        => 'int',
    ],
    'filters' => [
      'tipo_flujo' => [
        'col' => 'c.tipo_flujo',
        'type' => 'str',
        'in' => ['ingreso', 'egreso']
      ],
      'categoria_id' => [
        'col' => 'c.categoria_id',
        'type' => 'int'
      ],
      'activo' => [
        'col' => 'c.activo',
        'type' => 'int',
        'in' => [0, 1]
      ],
      'moneda_costo' => [
        'col' => 'c.moneda_costo',
        'type' => 'str',
        'in' => ['ARG', 'DOL', 'EUR']
      ],
      'periodicidad' => [
        'col' => 'c.periodicidad',
        'type' => 'str',
        'in' => ['unico', 'mensual', 'anual']
      ],
      'imputable_afip' => [
        'col' => 'c.imputable_afip',
        'type' => 'int',
        'in' => [0, 1]
      ]
    ],
    'per_page' => 50,
  ];

  $result = lcars_list($cfg, $_GET);
  
  // Agregar contador de entidades por concepto
  if (isset($result['items']) && count($result['items']) > 0) {
    $ids = array_column($result['items'], 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $db->prepare("
      SELECT concepto_id, COUNT(*) as total_entidades 
      FROM prm_conceptos_entidades 
      WHERE concepto_id IN ($placeholders)
      GROUP BY concepto_id
    ");
    
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $countResult = $stmt->get_result();
    
    $counts = [];
    while ($row = $countResult->fetch_assoc()) {
      $counts[$row['concepto_id']] = (int)$row['total_entidades'];
    }
    
    foreach ($result['items'] as &$item) {
      $item['total_entidades'] = $counts[$item['id']] ?? 0;
      
      // Convertir campos numéricos
      $item['activo'] = (int)$item['activo'];
      $item['imputable_afip'] = (int)$item['imputable_afip'];
      $item['orden'] = (int)$item['orden'];
      $item['costo_base'] = $item['costo_base'] ? (float)$item['costo_base'] : null;
    }
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[conceptos/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}