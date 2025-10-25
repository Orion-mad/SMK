<?php
// /api/contable/cobros/preview-trabajos-pagos.php
// Vista previa de pagos de trabajos ANTES de migrar a cnt_cobros
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();

  // Parámetros de búsqueda
  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

  $sql = "
    SELECT 
      tp.id,
      tp.trabajo_id,
      tp.fecha_pago,
      tp.monto,
      tp.moneda,
      tp.medio_pago,
      tp.referencia,
      tp.estado,
      tp.observaciones,
      t.codigo AS trabajo_codigo,
      t.nombre AS trabajo_nombre,
      t.cliente_id,
      COALESCE(c.razon_social, c.contacto_nombre, 'Sin cliente') AS cliente_nombre,
      -- Verificar si ya fue migrado
      (SELECT COUNT(*) FROM cnt_cobros_items ci WHERE ci.trabajo_pago_id = tp.id) AS ya_migrado
    FROM prm_trabajos_pagos tp
    INNER JOIN prm_trabajos t ON t.id = tp.trabajo_id
    LEFT JOIN clientes c ON c.id = t.cliente_id
    WHERE 1=1
  ";

  // Filtro por búsqueda
  if ($q !== '') {
    $qEsc = $db->real_escape_string($q);
    $sql .= " AND (
      t.codigo LIKE '%{$qEsc}%' 
      OR t.nombre LIKE '%{$qEsc}%'
      OR tp.referencia LIKE '%{$qEsc}%'
      OR c.razon_social LIKE '%{$qEsc}%'
      OR c.contacto_nombre LIKE '%{$qEsc}%'
    )";
  }

  // Filtro por estado
  if ($estado !== '') {
    $estadoEsc = $db->real_escape_string($estado);
    $sql .= " AND tp.estado = '{$estadoEsc}'";
  }

  $sql .= " ORDER BY tp.fecha_pago DESC, tp.id DESC";

  $result = $db->query($sql);
  
  if (!$result) {
    throw new Exception('Error en query: ' . $db->error);
  }

  $items = [];
  while ($row = $result->fetch_assoc()) {
    $items[] = [
      'id' => (int)$row['id'],
      'trabajo_id' => (int)$row['trabajo_id'],
      'trabajo_codigo' => $row['trabajo_codigo'],
      'trabajo_nombre' => $row['trabajo_nombre'],
      'cliente_id' => $row['cliente_id'] ? (int)$row['cliente_id'] : null,
      'cliente_nombre' => $row['cliente_nombre'],
      'fecha_pago' => $row['fecha_pago'],
      'monto' => (float)$row['monto'],
      'moneda' => $row['moneda'],
      'medio_pago' => $row['medio_pago'],
      'referencia' => $row['referencia'],
      'estado' => $row['estado'],
      'observaciones' => $row['observaciones'],
      'ya_migrado' => (int)$row['ya_migrado'] > 0
    ];
  }

  $response = [
    'items' => $items,
    'meta' => [
      'total' => count($items),
      'pendientes_migracion' => count(array_filter($items, fn($i) => !$i['ya_migrado']))
    ]
  ];

  http_response_code(200);
  echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[preview-trabajos-pagos] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error',
    'detail' => $e->getMessage(),
    'items' => []
  ]);
}