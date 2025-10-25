<?php
// /api/contable/cobros/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/list_helper.php';

try {
  $db = DB::get();
  
  // Verificar que la tabla existe
  $checkTable = $db->query("SHOW TABLES LIKE 'cnt_cobros'");
  if ($checkTable->num_rows === 0) {
    //http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla cnt_cobros no existe']);
    exit;
  }

  $cfg = [
    'table' => 'cnt_cobros c 
                LEFT JOIN clientes cl ON cl.id = c.cliente_id
                LEFT JOIN prm_trabajos t ON t.id = c.trabajo_id
                LEFT JOIN prm_servicios s ON s.id = c.servicio_id',
    'select' => [
      'id'                  => 'c.id',
      'codigo'              => 'c.codigo',
      'numero_factura'      => 'c.numero_factura',
      'cliente_id'          => 'c.cliente_id',
      'cliente_nombre'      => 'CONCAT(COALESCE(cl.razon_social, ""), " ", COALESCE(cl.contacto_nombre, ""))',
      'tipo'                => 'c.tipo',
      'concepto'            => 'c.concepto',
      'trabajo_codigo'      => 't.codigo',
      'servicio_nombre'     => 's.nombre',
      'subtotal'            => 'c.subtotal',
      'descuento'           => 'c.descuento',
      'impuestos'           => 'c.impuestos',
      'total'               => 'c.total',
      'moneda'              => 'c.moneda',
      'fecha_emision'       => 'c.fecha_emision',
      'fecha_vencimiento'   => 'c.fecha_vencimiento',
      'estado'              => 'c.estado',
      'monto_pagado'        => 'c.monto_pagado',
      'saldo'               => 'c.saldo',
      'afip_cae'            => 'c.afip_cae',
      'afip_tipo_comprobante' => 'c.afip_tipo_comprobante',
      'creado_en'           => 'c.creado_en',
      'actualizado_en'      => 'c.actualizado_en',
    ],
    'orderable' => ['id', 'codigo', 'fecha_emision', 'total', 'estado', 'numero_factura'],
    'default_order' => ['fecha_emision' => 'DESC', 'id' => 'DESC'],
    'searchable' => ['codigo', 'numero_factura', 'concepto', 'cl.razon_social', 'cl.contacto_nombre'],
    'numeric' => [
      'id'          => 'int',
      'cliente_id'  => 'int',
      'subtotal'    => 'float',
      'descuento'   => 'float',
      'impuestos'   => 'float',
      'total'       => 'float',
      'monto_pagado'=> 'float',
      'saldo'       => 'float',
    ],
    'filters' => [
      'estado' => [
        'col' => 'c.estado',
        'type' => 'str',
        'in' => ['pendiente', 'parcial', 'pagado', 'vencido', 'cancelado']
      ],
      'tipo' => [
        'col' => 'c.tipo',
        'type' => 'str',
        'in' => ['trabajo', 'servicio', 'otro']
      ],
      'cliente_id' => [
        'col' => 'c.cliente_id',
        'type' => 'int'
      ],
      'moneda' => [
        'col' => 'c.moneda',
        'type' => 'str',
        'in' => ['ARS', 'USD', 'EUR']
      ],
      'fecha_desde' => [
        'col' => 'c.fecha_emision',
        'type' => 'date',
        'op' => '>='
      ],
      'fecha_hasta' => [
        'col' => 'c.fecha_emision',
        'type' => 'date',
        'op' => '<='
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
  error_log('[cobros/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  //http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}