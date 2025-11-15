<?php
// /api/trabajos/pagos/save.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/crud_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Verificar que el trabajo existe
  $trabajoId = (int)($input['trabajo_id'] ?? 0);
  if ($trabajoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'trabajo_id requerido']);
    exit;
  }

  $stmt = $db->prepare("SELECT id, total, saldo FROM prm_trabajos WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $trabajoId);
  $stmt->execute();
  $trabajo = $stmt->get_result()->fetch_assoc();
  
  if (!$trabajo) {
   // http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Trabajo no encontrado']);
    exit;
  }

  // Validar que el monto no exceda el saldo
  $monto = (float)($input['monto'] ?? 0);
  if ($monto > $trabajo['saldo'] && $input['estado'] === 'confirmado') {
    http_response_code(400);
    echo json_encode([
      'ok' => false, 
      'error' => 'El monto excede el saldo pendiente',
      'saldo_actual' => $trabajo['saldo']
    ]);
    exit;
  }

  $cfg = [
    'table' => 'prm_trabajos_pagos',
    'pk' => 'id',
    'fields' => [
      'id' => [
        'col' => 'id',
        'type' => 'int',
        'default' => 0
      ],
      'trabajo_id' => [
        'col' => 'trabajo_id',
        'type' => 'int',
        'required' => true
      ],
      'fecha_pago' => [
        'col' => 'fecha_pago',
        'type' => 'str',
        'required' => true
      ],
      'monto' => [
        'col' => 'monto',
        'type' => 'float',
        'required' => true
      ],
      'moneda' => [
        'col' => 'moneda',
        'type' => 'str',
        'default' => 'ARS',
        'max' => 3
      ],
      'medio_pago' => [
        'col' => 'medio_pago',
        'type' => 'str',
        'required' => true,
        'max' => 50
      ],
      'referencia' => [
        'col' => 'referencia',
        'type' => 'str',
        'nullable' => true,
        'max' => 100
      ],
      'comprobante_tipo' => [
        'col' => 'comprobante_tipo',
        'type' => 'str',
        'nullable' => true,
        'max' => 20
      ],
      'comprobante_numero' => [
        'col' => 'comprobante_numero',
        'type' => 'str',
        'nullable' => true,
        'max' => 50
      ],
      'estado' => [
        'col' => 'estado',
        'type' => 'set',
        'default' => 'confirmado',
        'syn' => ['pendiente','confirmado','rechazado','anulado']
      ],
      'observaciones' => [
        'col' => 'observaciones',
        'type' => 'str',
        'nullable' => true
      ],
      'recibo_generado' => [
        'col' => 'recibo_generado',
        'type' => 'int',
        'default' => 0
      ],
      'recibo_numero' => [
        'col' => 'recibo_numero',
        'type' => 'str',
        'nullable' => true,
        'max' => 50
      ],
      'recibo_fecha' => [
        'col' => 'recibo_fecha',
        'type' => 'str',
        'nullable' => true
      ],
      'recibo_path' => [
        'col' => 'recibo_path',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ]
    ]
  ];

  $result = lcars_save($cfg, $input);
  
  // Si se guardó correctamente, devolver el trabajo actualizado
  if ($result['ok']) {
    $stmt = $db->prepare("SELECT total, saldo FROM prm_trabajos WHERE id = ?");
    $stmt->bind_param('i', $trabajoId);
    $stmt->execute();
    $trabajoActualizado = $stmt->get_result()->fetch_assoc();
    
    $result['trabajo_actualizado'] = $trabajoActualizado;
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[trabajos/pagos/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}