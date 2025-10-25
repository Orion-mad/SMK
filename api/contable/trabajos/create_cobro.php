<?php
// /api/contable/trabajos/create_cobro.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/codigo_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Validaciones básicas
  if (empty($input['cliente_id']) || empty($input['trabajo_id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'cliente_id y trabajo_id son requeridos']);
    exit;
  }

  if (empty($input['concepto'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El concepto es requerido']);
    exit;
  }

  // Autogenerar código si no viene
  lcars_autocodigo($db, 'cnt_cobros', $input, 'codigo', 'id', 'COBT');

  // Preparar datos
  $codigo           = $input['codigo'];
  $numero_factura   = $input['numero_factura'] ?? null;
  $cliente_id       = (int)$input['cliente_id'];
  $tipo             = $input['tipo'] ?? 'trabajo';
  $concepto         = $input['concepto'];
  $trabajo_id       = (int)$input['trabajo_id'];
  $servicio_id      = !empty($input['servicio_id']) ? (int)$input['servicio_id'] : null;
  $subtotal         = (float)($input['subtotal'] ?? 0);
  $descuento        = (float)($input['descuento'] ?? 0);
  $impuestos        = (float)($input['impuestos'] ?? 0);
  $total            = (float)($input['total'] ?? 0);
  $moneda           = $input['moneda'] ?? 'ARS';
  $fecha_emision    = $input['fecha_emision'];
  $fecha_vencimiento = $input['fecha_vencimiento'] ?? null;
  $estado           = $input['estado'] ?? 'pendiente';
  $monto_pagado     = (float)($input['monto_pagado'] ?? 0);
  $saldo            = (float)($input['saldo'] ?? $total);
  $observaciones    = $input['observaciones'] ?? null;

  // Verificar código único
  $checkStmt = $db->prepare("SELECT id FROM cnt_cobros WHERE codigo = ? LIMIT 1");
  $checkStmt->bind_param('s', $codigo);
  $checkStmt->execute();
  $checkStmt->store_result();
  
  if ($checkStmt->num_rows > 0) {
    $checkStmt->close();
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'duplicate_code', 'detail' => 'El código ya existe']);
    exit;
  }
  $checkStmt->close();

  // Verificar que el trabajo exista
  $trabajoStmt = $db->prepare("SELECT id, total, saldo FROM prm_trabajos WHERE id = ? LIMIT 1");
  $trabajoStmt->bind_param('i', $trabajo_id);
  $trabajoStmt->execute();
  $trabajoStmt->store_result();
  
  if ($trabajoStmt->num_rows === 0) {
    $trabajoStmt->close();
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Trabajo no encontrado']);
    exit;
  }
  $trabajoStmt->close();

  // Insertar cobro
  $sql = "INSERT INTO cnt_cobros (
            codigo, numero_factura, cliente_id, tipo, concepto, 
            trabajo_id, servicio_id, subtotal, descuento, impuestos, 
            total, moneda, fecha_emision, fecha_vencimiento, estado, 
            monto_pagado, saldo, observaciones, activo, orden
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)";

  $stmt = $db->prepare($sql);
  $stmt->bind_param(
    'ssissiiddddssssdds',
    $codigo,
    $numero_factura,
    $cliente_id,
    $tipo,
    $concepto,
    $trabajo_id,
    $servicio_id,
    $subtotal,
    $descuento,
    $impuestos,
    $total,
    $moneda,
    $fecha_emision,
    $fecha_vencimiento,
    $estado,
    $monto_pagado,
    $saldo,
    $observaciones
  );

  if (!$stmt->execute()) {
    throw new Exception('Error al insertar: ' . $stmt->error);
  }

  $newId = $db->insert_id;
  $stmt->close();

  http_response_code(201);
  echo json_encode([
    'ok' => true,
    'id' => $newId,
    'codigo' => $codigo,
    'message' => 'Cobro creado exitosamente'
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[contable/trabajos/create_cobro] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}