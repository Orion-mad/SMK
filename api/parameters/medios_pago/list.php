<?php
// /api/parameters/medios_pago/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/list_helper.php';

try {
  $db = DB::get();
  
  $checkTable = $db->query("SHOW TABLES LIKE 'prm_medios_pago'");
  if ($checkTable->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla prm_medios_pago no existe']);
    exit;
  }

  $cfg = [
    'table' => 'prm_medios_pago',
    'select' => [
      'id' => 'id',
      'codigo' => 'codigo',
      'nombre' => 'nombre',
      'activo' => 'activo',
      'notas' => 'notas',
      'orden' => 'orden',
      'creado_en' => 'creado_en',
      'actualizado_en' => 'actualizado_en',
    ],
    'orderable' => ['id', 'codigo', 'nombre', 'orden'],
    'default_order' => ['orden' => 'ASC', 'nombre' => 'ASC'],
    'searchable' => ['codigo', 'nombre', 'notas'],
    'numeric' => [
      'id' => 'int',
      'activo' => 'int',
      'orden' => 'int',
    ],
    'filters' => [
      'activo' => [
        'col' => 'activo',
        'type' => 'int',
        'in' => [0, 1]
      ]
    ],
    'per_page' => 50,
  ];

  $result = lcars_list($cfg, $_GET);
 
  // Asegurar estructura correcta
  if (!isset($result['items'])) {
    $result = ['items' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]];
  }
  
  // El helper devuelve {items: [...], meta: {...}}
  // Pero el JS espera solo el array directo, así que extraemos items
  echo json_encode($result['items'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[medios_pago/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}