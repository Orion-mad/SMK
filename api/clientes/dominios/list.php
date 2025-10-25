<?php
// /api/clientes/dominios/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/list_helper.php';

try {
  $db = DB::get();
  
  // Verificar que la tabla existe
  $checkTable = $db->query("SHOW TABLES LIKE 'dominios'");
  if ($checkTable->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla dominios no existe']);
    exit;
  }

  $cfg = [
    'table' => 'dominios d 
                LEFT JOIN clientes c ON c.id = d.cliente_id
                LEFT JOIN prm_planes p ON p.id = d.plan_id',
    'select' => [
      'id'                  => 'd.id',
      'codigo'              => 'd.codigo',
      'dominio'             => 'd.dominio',
      'tipo_dominio'        => 'd.tipo_dominio',
      'cliente_id'          => 'd.cliente_id',
      'cliente_razon_social'      => 'c.razon_social',
      'cliente_nombre_fantasia'   => 'c.nombre_fantasia',
      'plan_id'             => 'd.plan_id',
      'plan_nombre'         => 'p.nombre',
      'proveedor_hosting'   => 'd.proveedor_hosting',
      'registrador'         => 'd.registrador',
      'fecha_registro'      => 'd.fecha_registro',
      'fecha_vencimiento'   => 'd.fecha_vencimiento',
      'ssl_activo'          => 'd.ssl_activo',
      'ssl_vencimiento'     => 'd.ssl_vencimiento',
      'estado'              => 'd.estado',
      'renovacion_auto'     => 'd.renovacion_auto',
      'orden'               => 'd.orden',
      'creado_en'           => 'd.creado_en',
      'actualizado_en'      => 'd.actualizado_en',
    ],
    'orderable' => ['id', 'codigo', 'dominio', 'fecha_vencimiento', 'fecha_registro', 'orden'],
    'default_order' => ['orden' => 'ASC', 'dominio' => 'ASC'],
    'searchable' => ['d.codigo', 'd.dominio', 'd.proveedor_hosting', 'd.registrador', 'c.nombre_completo', 'c.empresa'],
    'numeric' => [
      'id'         => 'int',
      'cliente_id' => 'int',
      'plan_id'    => 'int',
      'ssl_activo' => 'int',
      'renovacion_auto' => 'int',
      'orden'      => 'int',
    ],
    'filters' => [
      'estado' => [
        'col' => 'd.estado',
        'type' => 'str',
        'in' => ['activo', 'suspendido', 'vencido', 'transferencia', 'cancelado']
      ],
      'tipo_dominio' => [
        'col' => 'd.tipo_dominio',
        'type' => 'str',
        'in' => ['principal', 'adicional', 'subdominio', 'redireccion']
      ],
      'cliente_id' => [
        'col' => 'd.cliente_id',
        'type' => 'int'
      ],
      'plan_id' => [
        'col' => 'd.plan_id',
        'type' => 'int'
      ],
      'ssl_activo' => [
        'col' => 'd.ssl_activo',
        'type' => 'int',
        'in' => [0, 1]
      ],
      'vencimiento_proximo' => [
        'custom' => function($val) {
          if ($val === '1' || $val === 'true') {
            $fecha = date('Y-m-d', strtotime('+30 days'));
            return "d.fecha_vencimiento IS NOT NULL AND d.fecha_vencimiento <= '$fecha' AND d.fecha_vencimiento >= CURDATE()";
          }
          return '';
        }
      ]
    ],
    'per_page' => 50,
  ];

  $result = lcars_list($cfg, $_GET);
  
  // Asegurar estructura correcta
  if (!isset($result['items'])) {
    $result = ['items' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]];
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/dominios/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}