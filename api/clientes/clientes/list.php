<?php
// /api/clientes/clientes/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/list_helper.php';

try {
  $db = DB::get();
  
  $checkTable = $db->query("SHOW TABLES LIKE 'clientes'");
  if ($checkTable->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla clientes no existe']);
    exit;
  }

  $cfg = [
    'table' => 'clientes c LEFT JOIN prm_servicios s ON s.id = c.servicio',
    'select' => [
      'id'                => 'c.id',
      'codigo'            => 'c.codigo',
      'razon_social'      => 'c.razon_social',
      'nombre_fantasia'   => 'c.nombre_fantasia',
      'contacto_nombre'   => 'c.contacto_nombre',
      'tipo_doc'          => 'c.tipo_doc',
      'nro_doc'           => 'c.nro_doc',
      'iva_cond'          => 'c.iva_cond',
      'email'             => 'c.email',
      'telefono'          => 'c.telefono',
      'celular'           => 'c.celular',
      'localidad'         => 'c.localidad',
      'provincia'         => 'c.provincia',
      'moneda_preferida'  => 'c.moneda_preferida',
      'servicio'          => 'c.servicio',
      'servicio_nombre'   => 's.nombre',
      'condicion_venta'   => 'c.condicion_venta',
      'estado'            => 'c.estado',
      'created_at'        => 'c.created_at',
    ],
    'orderable' => ['id', 'codigo', 'razon_social', 'contacto_nombre', 'created_at'],
    'default_order' => ['id' => 'DESC'],
    'searchable' => ['codigo', 'razon_social', 'contacto_nombre', 'nro_doc', 'email'],
    'numeric' => [
      'id'       => 'int',
      'servicio' => 'int',
      'estado'   => 'int',
    ],
    'filters' => [
      'estado' => [
        'col' => 'c.estado',
        'type' => 'int',
        'sin' => [0, 1]
      ],
      'iva_cond' => [
        'col' => 'c.iva_cond',
        'type' => 'str',
        'sin' => ['RI', 'RNI', 'MT', 'EX', 'CF', 'NC']
      ],
      'condicion_venta' => [
        'col' => 'c.condicion_venta',
        'type' => 'str',
        'sin' => ['CONTADO', 'CTA_CTE', 'MIXTA']
      ],
      'provincia' => [
        'col' => 'c.provincia',
        'type' => 'str'
      ]
    ],
    'per_page' => 50,
  ];

  $result = lcars_list($cfg, $_GET);
  
  if (!isset($result['items'])) {
    $result = ['items' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]];
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}