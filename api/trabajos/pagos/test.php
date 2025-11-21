<?php
// Test endpoint para debugging
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/crud_helper.php';

try {
  $rawInput = file_get_contents('php://input');
  $input = json_decode($rawInput, true);

  if (!$input) {
    echo json_encode(['ok' => false, 'error' => 'JSON inválido', 'raw' => $rawInput]);
    exit;
  }

  // Solo validación sin guardar
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
      ]
    ]
  ];

  [$cols, $errors] = lcars_validate($cfg['fields'], $input);

  echo json_encode([
    'ok' => empty($errors),
    'input_received' => $input,
    'validated_cols' => $cols,
    'errors' => $errors
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode([
    'ok' => false,
    'error' => 'exception',
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'trace' => $e->getTraceAsString()
  ]);
}
