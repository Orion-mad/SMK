<?php
// /api/parameters/servicios/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/list_helper.php';

try {
  $db = DB::get();
  
  // Verificar que las tablas existen
  $checkTable = $db->query("SHOW TABLES LIKE 'prm_servicios'");
  if ($checkTable->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla prm_servicios no existe']);
    exit;
  }

  $cfg = [
    'table' => 'prm_servicios s INNER JOIN prm_planes p ON p.id = s.plan_id',
    'select' => [
      'id'              => 's.id',
      'codigo'          => 's.codigo',
      'nombre'          => 's.nombre',
      'plan_id'         => 's.plan_id',
      'plan_codigo'     => 'p.codigo',
      'plan_nombre'     => 'p.nombre',
      'plan_moneda'     => 'p.moneda',
      'descripcion'     => 's.descripcion',
      'precio_usd'      => 's.precio_usd',
      'tipo_cobro'      => 's.tipo_cobro',
      'fecha_inicio'    => 's.fecha_inicio',
      'estado'          => 's.estado',
      'orden'           => 's.orden',
      'observaciones'   => 's.observaciones',
      'creado_en'       => 's.creado_en',
      'actualizado_en'  => 's.actualizado_en',
    ],
    'orderable' => ['id', 'codigo', 'nombre', 'precio_usd', 'fecha_inicio', 'orden'],
    'default_order' => ['orden' => 'ASC', 'id' => 'ASC'],
    'searchable' => ['codigo', 'nombre', 'plan_nombre'],
    'numeric' => [
      'id'         => 'int',
      'plan_id'    => 'int',
      'precio_usd' => 'float',
      'orden'      => 'int',
    ],
    'filters' => [
      'estado' => [
        'col' => 's.estado',
        'type' => 'str',
        'in' => ['activo', 'suspendido', 'cancelado']
      ],
      'tipo_cobro' => [
        'col' => 's.tipo_cobro',
        'type' => 'str',
        'in' => ['mensual', 'anual']
      ],
      'plan_id' => [
        'col' => 's.plan_id',
        'type' => 'int'
      ]
    ],
    'per_page' => 50,
  ];

  $result = lcars_list($cfg, $_GET);
  
  // Asegurar que siempre devuelva la estructura correcta
  if (!isset($result['items'])) {
    $result = ['items' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]];
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[servicios/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}