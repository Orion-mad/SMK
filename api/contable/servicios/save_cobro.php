<?php
// /api/contable/servicios/save_cobro.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/crud_helper.php';
require_once __DIR__ . '/../../core/codigo_helper.php';
require_once __DIR__ . '/../../core/moneda_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    //http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Autogenerar código si no viene o está vacío (solo en alta)
  lcars_autocodigo($db, 'cnt_cobros', $input, 'codigo', 'id', 'COB');

  // Determinar acción
  $accion = $input['accion'] ?? 'guardar';
  
  // Configuración para crud_helper
  $cfg = [
    'table' => 'cnt_cobros',
    'pk' => 'id',
    'unique' => ['codigo'],
    'fields' => [
      'id' => [
        'col' => 'id',
        'type' => 'int',
        'default' => 0
      ],
      'codigo' => [
        'col' => 'codigo',
        'type' => 'str',
        'required' => true,
        'max' => 32
      ],
      'numero_factura' => [
        'col' => 'numero_factura',
        'type' => 'str',
        'nullable' => true,
        'max' => 50
      ],
      'cliente_id' => [
        'col' => 'cliente_id',
        'type' => 'int',
        'required' => true
      ],
      'tipo' => [
        'col' => 'tipo',
        'type' => 'set',
        'default' => 'servicio',
        'syn' => ['trabajo', 'servicio', 'otro']
      ],
      'concepto' => [
        'col' => 'concepto',
        'type' => 'str',
        'required' => true,
        'max' => 200
      ],
      'trabajo_id' => [
        'col' => 'trabajo_id',
        'type' => 'int',
        'nullable' => true
      ],
      'servicio_id' => [
        'col' => 'servicio_id',
        'type' => 'int',
        'nullable' => true
      ],
      'subtotal' => [
        'col' => 'subtotal',
        'type' => 'float',
        'default' => 0.0
      ],
      'descuento' => [
        'col' => 'descuento',
        'type' => 'float',
        'default' => 0.0
      ],
      'impuestos' => [
        'col' => 'impuestos',
        'type' => 'float',
        'default' => 0.0
      ],
      'total' => [
        'col' => 'total',
        'type' => 'float',
        'required' => true
      ],
      'moneda' => [
        'col' => 'moneda',
        'type' => 'str',
        'default' => 'ARS',
        'max' => 3
      ],
      'fecha_emision' => [
        'col' => 'fecha_emision',
        'type' => 'str',
        'required' => true
      ],
      'fecha_vencimiento' => [
        'col' => 'fecha_vencimiento',
        'type' => 'str',
        'nullable' => true
      ],
      'estado' => [
        'col' => 'estado',
        'type' => 'set',
        'default' => 'pendiente',
        'syn' => ['pendiente', 'parcial', 'pagado', 'vencido', 'cancelado']
      ],
      'monto_pagado' => [
        'col' => 'monto_pagado',
        'type' => 'float',
        'default' => 0.0
      ],
      'saldo' => [
        'col' => 'saldo',
        'type' => 'float',
        'default' => 0.0
      ],
      'afip_cae' => [
        'col' => 'afip_cae',
        'type' => 'str',
        'nullable' => true,
        'max' => 20
      ],
      'afip_vencimiento_cae' => [
        'col' => 'afip_vencimiento_cae',
        'type' => 'str',
        'nullable' => true
      ],
      'afip_tipo_comprobante' => [
        'col' => 'afip_tipo_comprobante',
        'type' => 'str',
        'nullable' => true,
        'max' => 10
      ],
      'afip_punto_venta' => [
        'col' => 'afip_punto_venta',
        'type' => 'int',
        'nullable' => true
      ],
      'observaciones' => [
        'col' => 'observaciones',
        'type' => 'str',
        'nullable' => true
      ],
      'activo' => [
        'col' => 'activo',
        'type' => 'int',
        'default' => 1
      ],
      'orden' => [
        'col' => 'orden',
        'type' => 'int',
        'default' => 0
      ]
    ]
  ];

  // Validar cliente
  if (!empty($input['cliente_id'])) {
    $clienteId = (int)$input['cliente_id'];
    $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      //http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El cliente seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

  // Validar servicio
  if (!empty($input['servicio_id'])) {
    $servicioId = (int)$input['servicio_id'];
    $stmt = $db->prepare("SELECT id FROM prm_servicios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $servicioId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      //http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El servicio seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

  // Si la acción es proforma, marcar el estado como proforma
  if ($accion === 'proforma') {
    $input['estado'] = 'pendiente'; // Las proformas quedan pendientes hasta que se registre el pago
  }

  // Guardar el cobro
  $result = lcars_save($cfg, $input);

  if (!$result['ok']) {
    //http_response_code(400);
    echo json_encode($result);
    exit;
  }

  $cobroId = $result['id'];

  // Procesar según la acción
  $response = [
    'ok' => true,
    'id' => $cobroId,
    'codigo' => $input['codigo']
  ];

  switch ($accion) {
    case 'facturar_arca':
      // TODO: Implementar integración con ARCA/AFIP
      // Por ahora, solo marcamos que se intentó facturar
      $stmt = $db->prepare("
        UPDATE cnt_cobros 
        SET afip_tipo_comprobante = 'PENDIENTE_FACTURACION'
        WHERE id = ?
      ");
      $stmt->bind_param('i', $cobroId);
      $stmt->execute();
      $stmt->close();
      
      $response['factura_url'] = null;
      $response['message'] = 'Cobro guardado. La facturación con ARCA se implementará próximamente.';
      break;

    case 'proforma':
      // Generar URL de proforma
      $response['proforma_url'] = "/api/contable/cobros/proforma.php?id={$cobroId}&action=download";
      $response['message'] = 'Proforma generada correctamente';
      break;

    default: // 'guardar'
      $response['message'] = 'Cobro guardado correctamente';
      break;
  }

  http_response_code(200);
  echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[servicios/save_cobro] ' . $e->getMessage());
  //http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}