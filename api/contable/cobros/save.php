<?php
// /api/contable/cobros/save.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/crud_helper.php';
require_once __DIR__ . '/../../core/codigo_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Autogenerar código solo si es un INSERT (id=0) y código está vacío
  if (empty($input['id']) || $input['id'] == 0) {
    lcars_autocodigo($db, 'cnt_cobros', $input, 'codigo', 'id');
  }

  // Calcular totales antes de validar
  $subtotal = (float)($input['subtotal'] ?? 0);
  $descuento = (float)($input['descuento'] ?? 0);
  $impuestos = (float)($input['impuestos'] ?? 0);
  $total = $subtotal - $descuento + $impuestos;
  $monto_pagado = (float)($input['monto_pagado'] ?? 0);
  $saldo = $total - $monto_pagado;

  // Actualizar valores calculados en input
  $input['total'] = $total;
  $input['saldo'] = $saldo;

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
        'nullable' => true
      ],
      'tipo' => [
        'col' => 'tipo',
        'type' => 'set',
        'default' => 'trabajo',
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
        'default' => 0.0
      ],
      'moneda' => [
        'col' => 'moneda',
        'type' => 'set',
        'default' => 'ARS',
        'syn' => ['ARS', 'USD', 'EUR']
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

  // Validaciones adicionales
  if (!empty($input['cliente_id'])) {
    $clienteId = (int)$input['cliente_id'];
    $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El cliente seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

  if (!empty($input['trabajo_id'])) {
    $trabajoId = (int)$input['trabajo_id'];
    $stmt = $db->prepare("SELECT id FROM prm_trabajos WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $trabajoId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El trabajo seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

  if (!empty($input['servicio_id'])) {
    $servicioId = (int)$input['servicio_id'];
    $stmt = $db->prepare("SELECT id FROM prm_servicios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $servicioId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El servicio seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

  // Iniciar transacción para manejar cobro + items
  $db->begin_transaction();

  try {
    // Guardar cobro principal
    $result = lcars_save($cfg, $input);

    if (!$result['ok']) {
      throw new Exception($result['error'] ?? 'Error guardando cobro');
    }

    $cobroId = (int)$result['id'];

    // Eliminar items existentes si es UPDATE
    if ($cobroId > 0 && !empty($input['id']) && $input['id'] > 0) {
      $db->query("DELETE FROM cnt_cobros_items WHERE cobro_id = $cobroId");
    }

    // Insertar items si vienen
    if (isset($input['items']) && is_array($input['items'])) {
      foreach ($input['items'] as $idx => $item) {
        if (empty($item['descripcion'])) continue;

        $cantidad = (float)($item['cantidad'] ?? 1);
        $precio_unitario = (float)($item['precio_unitario'] ?? 0);
        $itemSubtotal = $cantidad * $precio_unitario;
        $alicuota_iva = (float)($item['alicuota_iva'] ?? 0);
        $monto_iva = $itemSubtotal * ($alicuota_iva / 100);
        $trabajo_pago_id = !empty($item['trabajo_pago_id']) ? (int)$item['trabajo_pago_id'] : null;
        $orden = $idx + 1;
        
        $stmtItem = $db->prepare("
          INSERT INTO cnt_cobros_items 
          (cobro_id, descripcion, cantidad, precio_unitario, subtotal, alicuota_iva, monto_iva, trabajo_pago_id, orden)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Todas las variables deben estar en variables separadas para bind_param
        $descripcion = $item['descripcion'];
        
        $stmtItem->bind_param(
          'isddddiii',
          $cobroId,
          $descripcion,
          $cantidad,
          $precio_unitario,
          $itemSubtotal,
          $alicuota_iva,
          $monto_iva,
          $trabajo_pago_id,
          $orden
        );
        
        if (!$stmtItem->execute()) {
          throw new Exception('Error insertando item: ' . $stmtItem->error);
        }
      }
    }

    $db->commit();

    // Respuesta exitosa
    echo json_encode([
      'ok' => true,
      'id' => $cobroId,
      'codigo' => $input['codigo'],
      'message' => empty($input['id']) ? 'Cobro creado exitosamente' : 'Cobro actualizado exitosamente'
    ], JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  error_log('[cobros/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}